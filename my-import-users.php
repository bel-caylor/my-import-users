<?php
/**
 * Plugin Name: CSV User Importer with LearnDash & MailPoet
 * Description: Upload a CSV to create users, assign LearnDash courses, and add to MailPoet list.
 * Version: 1.5
 */

// Load includes
require_once plugin_dir_path(__FILE__) . 'includes/download-template.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/validator.php';
require_once plugin_dir_path(__FILE__) . 'includes/processor.php';

