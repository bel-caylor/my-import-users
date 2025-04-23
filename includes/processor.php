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

    $users_added = 0;
    $users_skipped = 0;
    $enrollments_made = 0;
    $errors = [];

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($headers, $row);
        $email = sanitize_email($data['email']);
        $first_name = sanitize_text_field($data['first_name']);
        $last_name = sanitize_text_field($data['last_name']);
        $course_titles = array_map('trim', explode(',', $data['course_title']));

        // Generate unique username
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
            $users_skipped++;

            // Optional: Update user info
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'learner',
            ]);

        } else {
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                $errors[] = "Error creating user: {$email}";
                continue;
            }

            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'learner',
            ]);

            $users_added++;
        }

        // Enroll in LearnDash course(s)
        foreach ($course_titles as $course_title) {
            $course = get_page_by_title($course_title, OBJECT, 'sfwd-courses');
            if (!$course) {
                $errors[] = "Course not found: {$course_title}";
                continue;
            }

            $course_id = $course->ID;
            $enrolled_courses = learndash_user_get_enrolled_courses($user_id);

            if (!in_array($course_id, $enrolled_courses)) {
                ld_update_course_access($user_id, $course_id, false);
                $enrollments_made++;
            }

            // MailPoet Subscription
            try {
                $mailpoet_lists = $mailpoet_api->getLists();
                $matched_list = array_filter($mailpoet_lists, function ($list) use ($course_title) {
                    return $list['name'] === $course_title;
                });

                if (!empty($matched_list)) {
                    $list_ids = array_column($matched_list, 'id');
                    $subscriber = $mailpoet_api->getSubscriber($email);

                    if ($subscriber) {
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
                }

            } catch (Exception $e) {
                $errors[] = "MailPoet error for {$email}: " . $e->getMessage();
            }
        }
    }

    fclose($file);

    // ✅ Admin Summary Output
    echo '<div class="notice notice-success"><p>';
    echo "Import complete:<br>";
    echo "• Users added: {$users_added}<br>";
    echo "• Users updated/skipped: {$users_skipped}<br>";
    echo "• Enrollments made: {$enrollments_made}<br>";
    if (!empty($errors)) {
        echo "• Errors:<ul>";
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo "</ul>";
    } else {
        echo "• No errors.";
    }
    echo '</p></div>';
}
