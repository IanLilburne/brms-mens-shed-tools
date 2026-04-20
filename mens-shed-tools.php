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
require_once MENS_SHED_TOOLS_PATH . 'includes/dashboard.php';