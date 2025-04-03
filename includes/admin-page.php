<?php

// Add admin menu item
add_action('admin_menu', function () {
    add_users_page (
        'CSV User Importer',
        'User Importer',
        'manage_options',
        'csv-user-importer',
        'csv_user_importer_admin_page',
        'dashicons-upload',
        100
    );
});

function csv_user_importer_admin_page() {
    ?>
        <div class="wrap">
            <h1><b>CSV User Importer</b></h1>

            <style>
                .csv-import-box {
                    background: #fff;
                    padding: 20px;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    margin-bottom: 30px;
                    width: min-content;
                }
            </style>

            <div class="csv-import-box">
                <h2>Import Users</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="user_csv" accept=".csv" required>
                    <?php submit_button('Upload and Validate'); ?>
                </form>
            </div>

            <h2>Download CSV Template</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=csv-user-importer&download_csv_template=1')); ?>" class="button">
                    Download CSV Template
                </a>
            </p>
        </div>

    <?php

    if (!empty($_FILES['user_csv']['tmp_name'])) {
        $file = $_FILES['user_csv']['tmp_name'];
        $validation_result = csv_user_importer_validate_csv($file);

        if ($validation_result === true) {
            csv_user_importer_process_csv($file);
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($validation_result) . '</p></div>';
        }
    }
}