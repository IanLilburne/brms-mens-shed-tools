<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_head', 'shed_tv_dashboard_kiosk_css', 99);

if (!function_exists('shed_tv_dashboard_kiosk_css')) {
    function shed_tv_dashboard_kiosk_css() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'shed_tv_dashboard')) {
            return;
        }
        ?>
        <style>
            /* Hide common theme elements */
            body.shed-tv-kiosk #masthead,
            body.shed-tv-kiosk #site-navigation,
            body.shed-tv-kiosk .main-navigation,
            body.shed-tv-kiosk .site-header,
            body.shed-tv-kiosk .inside-header,
            body.shed-tv-kiosk .site-branding,
            body.shed-tv-kiosk .entry-header,
            body.shed-tv-kiosk .page-header,
            body.shed-tv-kiosk .site-footer,
            body.shed-tv-kiosk #colophon,
            body.shed-tv-kiosk .widget-area,
            body.shed-tv-kiosk .sidebar,
            body.shed-tv-kiosk .right-sidebar,
            body.shed-tv-kiosk .left-sidebar,
            body.shed-tv-kiosk .footer-widgets,
            body.shed-tv-kiosk .top-bar,
            body.shed-tv-kiosk .nav-below-header,
            body.shed-tv-kiosk .generate-back-to-top,
            body.shed-tv-kiosk .post-image,
            body.shed-tv-kiosk .featured-image,
            body.shed-tv-kiosk .comments-area,
            body.shed-tv-kiosk .inside-page-header,
            body.shed-tv-kiosk .page-title,
            body.shed-tv-kiosk .entry-title {
                display: none !important;
            }

            /* Remove spacing/container restrictions */
            body.shed-tv-kiosk,
            html.shed-tv-kiosk {
                margin: 0 !important;
                padding: 0 !important;
                background: #f7f7f7 !important;
            }

            body.shed-tv-kiosk #page,
            body.shed-tv-kiosk #content,
            body.shed-tv-kiosk .site,
            body.shed-tv-kiosk .site-content,
            body.shed-tv-kiosk .content-area,
            body.shed-tv-kiosk .site-main,
            body.shed-tv-kiosk .inside-site-info,
            body.shed-tv-kiosk .inside-article,
            body.shed-tv-kiosk article,
            body.shed-tv-kiosk .entry-content,
            body.shed-tv-kiosk .separate-containers .inside-article,
            body.shed-tv-kiosk .one-container .site-content,
            body.shed-tv-kiosk .container,
            body.shed-tv-kiosk .grid-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            /* Ensure dashboard owns the viewport */
            body.shed-tv-kiosk .shed-tv-wrapper {
                min-height: 100vh !important;
                margin: 0 !important;
            }
        </style>
        <?php
    }
}

add_filter('body_class', 'shed_tv_dashboard_kiosk_body_class');

if (!function_exists('shed_tv_dashboard_kiosk_body_class')) {
    function shed_tv_dashboard_kiosk_body_class($classes) {
        if (!is_singular()) {
            return $classes;
        }

        global $post;
        if (isset($post->post_content) && has_shortcode($post->post_content, 'shed_tv_dashboard')) {
            $classes[] = 'shed-tv-kiosk';
        }

        return $classes;
    }
}