<?php

// Add download template action
add_action('admin_init', function () {
    if (isset($_GET['download_csv_template']) && current_user_can('manage_options')) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="user-import-template.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['first_name', 'last_name', 'email', 'course_title']);
        fputcsv($output, ['Jane', 'Doe', 'jane@example.com', 'Cultivating Character in Kids']);
        fclose($output);
        exit;
    }
});