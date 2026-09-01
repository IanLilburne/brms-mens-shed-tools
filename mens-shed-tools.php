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
require_once MENS_SHED_TOOLS_PATH . 'includes/project-creation.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/project-edit.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-signup.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/volunteer-commitments-report.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/admin-cleanup.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/attendance-report-fix.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/attendance.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/ai/class-ai-rewrite-service.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/images/class-image-service.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/posts/class-post-service.php';

register_activation_hook(__FILE__, 'shed_attendance_activate');
register_deactivation_hook(__FILE__, 'shed_attendance_deactivate');

$news_submission_file = MENS_SHED_TOOLS_PATH . 'includes/news-submission.php';
if (file_exists($news_submission_file)) {
    require_once $news_submission_file;
}

$news_cropper_file = MENS_SHED_TOOLS_PATH . 'includes/news-cropper.php';
if (file_exists($news_cropper_file)) {
    require_once $news_cropper_file;
}

