<?php
function csv_user_importer_process_csv($csv_path) {
    if (!file_exists($csv_path)) {
        echo '<div class="notice notice-error"><p>CSV file not found.</p></div>';
        return;
    }

    if (!class_exists(\MailPoet\API\API::class)) {
        echo '<div class="notice notice-error"><p>MailPoet is not active.</p></div>';
        return;
    }

    $mailpoet_api = \MailPoet\API\API::MP('v1');

    $file = fopen($csv_path, 'r');
    $headers = fgetcsv($file);

    echo '<div class="notice notice-success"><p>Import started...</p></div>';

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($headers, $row);
        $email = sanitize_email($data['email']);
        $first_name = sanitize_text_field($data['first_name']);
        $last_name = sanitize_text_field($data['last_name']);
        $course_titles = array_map('trim', explode(',', $data['course_title']));

        // Generate unique username: first letter of first name + last name, max 50 chars
        $base_username = strtolower(substr($first_name, 0, 1) . $last_name);
        $base_username = substr(sanitize_user($base_username, true), 0, 50);
        $username = $base_username;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $base_username . $suffix;
            $suffix++;
        }

        if (email_exists($email)) {
            $user = get_user_by('email', $email);
            $user_id = $user->ID;
        } else {
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                echo '<div class="notice notice-error"><p>Error creating user: ' . esc_html($email) . '</p></div>';
                continue;
            }
            wp_update_user([
                'ID' => $user_id,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'subscriber',
            ]);

            // wp_new_user_notification($user_id, null, 'user');
        }

        // Enroll in LearnDash course(s) by title
        foreach ($course_titles as $course_title) {
            $course = get_page_by_title($course_title, OBJECT, 'sfwd-courses');
            if (!$course) {
                echo '<div class="notice notice-error"><p>Course not found: ' . esc_html($course_title) . '</p></div>';
                continue;
            }

            $course_id = $course->ID;

            // Check if the user is already enrolled in the course
            $enrolled_courses = learndash_user_get_enrolled_courses($user_id);
            if (!in_array($course_id, $enrolled_courses)) {
                // Enroll the user in the course
                ld_update_course_access($user_id, $course_id, false);
            }

            // Add to MailPoet list named after the course title
            $mailpoet_lists = $mailpoet_api->getLists();
            $matched_list = array_filter($mailpoet_lists, function ($list) use ($course_title) {
                return $list['name'] === $course_title;
            });

            if (!empty($matched_list)) {
                $list_ids = array_column($matched_list, 'id');

                try {
                    $subscriber = $mailpoet_api->getSubscriber($email);
                    if ($subscriber) {
                        // Resubscribe if unsubscribed
                        if ($subscriber['status'] !== 'subscribed') {
                            $mailpoet_api->subscribeToLists($subscriber['id'], $list_ids);
                        } else {
                            $subscriptions = $mailpoet_api->getSubscriberSubscriptions($subscriber['id']);
                            $already_subscribed = array_column($subscriptions, 'list_id');
                            $new_lists = array_diff($list_ids, $already_subscribed);
                            if (!empty($new_lists)) {
                                $mailpoet_api->subscribeToLists($subscriber['id'], $new_lists);
                            }
                        }
                    } else {
                        $mailpoet_api->addSubscriber([
                            'email' => $email,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                        ], $list_ids);
                    }
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>MailPoet error for ' . esc_html($email) . ': ' . esc_html($e->getMessage()) . '</p></div>';
                }
            }
        }

    }

    fclose($file);
    echo '<div class="notice notice-success"><p>Import completed.</p></div>';
}
