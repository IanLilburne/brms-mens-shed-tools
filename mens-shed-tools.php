<?php
/**
 * Plugin Name: Men's Shed Tools
 * Plugin URI: https://brms.org.uk/
 * Description: Custom tools for Brundall Men's Shed workflow, dashboards, and forms.
 * Version: 0.1.0
 * Author: Brundall Men's Shed
 * License: GPL2+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MENS_SHED_TOOLS_PATH', plugin_dir_path(__FILE__));
define('MENS_SHED_TOOLS_URL', plugin_dir_url(__FILE__));

require_once MENS_SHED_TOOLS_PATH . 'includes/helpers.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/assets.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/dashboard-data.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/dashboard.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/dashboard-kiosk.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-signup.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-edit.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-preselect.php';
//require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-dropdown.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/admin-cleanup.php';
//require_once MENS_SHED_TOOLS_PATH . 'includes/news-submission.php';
//require_once MENS_SHED_TOOLS_PATH . 'includes/news-cropper.php';

$news_submission_file = MENS_SHED_TOOLS_PATH . 'includes/news-submission.php';
if (file_exists($news_submission_file)) {
    require_once $news_submission_file;
}

$news_cropper_file = MENS_SHED_TOOLS_PATH . 'includes/news-cropper.php';
if (file_exists($news_cropper_file)) {
    require_once $news_cropper_file;
}

$volunteer_dropdown_file = MENS_SHED_TOOLS_PATH . 'includes/volunteer-dropdown.php';
if (file_exists($volunteer_dropdown_file)) {
    require_once $volunteer_dropdown_file;
}

$bootstrap_file = MENS_SHED_TOOLS_PATH . 'includes/bootstrap.php';
if (file_exists($bootstrap_file)) {
    require_once $bootstrap_file;
}