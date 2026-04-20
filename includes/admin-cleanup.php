<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'shed_hide_old_volunteer_signup_menu', 999);

if (!function_exists('shed_hide_old_volunteer_signup_menu')) {
    function shed_hide_old_volunteer_signup_menu() {
        remove_menu_page('edit.php?post_type=volunteer-signup');
    }
}