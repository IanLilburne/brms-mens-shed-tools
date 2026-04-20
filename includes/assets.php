<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue Splide assets only when the TV dashboard shortcode is present.
 */
add_action('wp_enqueue_scripts', 'shed_enqueue_splide_assets');

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

    shed_log('Splide assets enqueued for shed_tv_dashboard');
}