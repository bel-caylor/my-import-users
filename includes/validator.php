<?php

function csv_user_importer_validate_csv($csv_path) {
    $required_headers = ['first_name', 'last_name', 'email', 'course_title'];
    $file = fopen($csv_path, 'r');
    $headers = fgetcsv($file);
    fclose($file);

    if (!$headers || array_diff($required_headers, $headers)) {
        return 'CSV file must include the following columns: ' . implode(', ', $required_headers);
    }

    return true;
}