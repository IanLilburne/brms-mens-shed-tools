<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_enqueue_splide_assets')) {
    function shed_enqueue_splide_assets() {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if (!has_shortcode($post->post_content, 'shed_tv_dashboard')) {
            return;
        }

        wp_enqueue_style(
            'splide-css',
            'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css',
            array(),
            '4.1.4'
        );

        wp_enqueue_script(
            'splide-js',
            'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js',
            array(),
            '4.1.4',
            true
        );

        wp_enqueue_style(
            'shed-dashboard-css',
            MENS_SHED_TOOLS_URL . 'assets/dashboard.css',
            array('splide-css'),
            file_exists(MENS_SHED_TOOLS_PATH . 'assets/dashboard.css') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/dashboard.css') : '1.0'
        );

        wp_enqueue_script(
            'shed-dashboard-js',
            MENS_SHED_TOOLS_URL . 'assets/dashboard.js',
            array('splide-js'),
            file_exists(MENS_SHED_TOOLS_PATH . 'assets/dashboard.js') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/dashboard.js') : '1.0',
            true
        );

        shed_log('Dashboard assets enqueued');
    }
}

add_action('wp_enqueue_scripts', 'shed_enqueue_splide_assets');
