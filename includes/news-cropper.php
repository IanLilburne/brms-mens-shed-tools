<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_news_cropper_should_load')) {
    function shed_news_cropper_should_load() {
        if (is_page('post-submissions')) {
            return true;
        }

        global $post;

        return is_a($post, 'WP_Post') && has_shortcode((string) $post->post_content, 'shed_news_submission_form');
    }
}

if (!function_exists('shed_enqueue_news_cropper_assets')) {
    function shed_enqueue_news_cropper_assets() {
        if (!shed_news_cropper_should_load()) {
            return;
        }

        wp_enqueue_style(
            'cropperjs',
            'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css',
            [],
            '1.6.2'
        );

        wp_enqueue_script(
            'cropperjs',
            'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js',
            [],
            '1.6.2',
            true
        );

        wp_enqueue_style(
            'shed-news-cropper-css',
            MENS_SHED_TOOLS_URL . 'assets/news-cropper.css',
            ['cropperjs'],
            file_exists(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.css') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.css') : '1.0'
        );

        wp_enqueue_script(
            'shed-news-cropper-js',
            MENS_SHED_TOOLS_URL . 'assets/news-cropper.js',
            ['cropperjs'],
            file_exists(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.js') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.js') : '1.0',
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'shed_enqueue_news_cropper_assets');

if (!function_exists('shed_render_news_cropper_modal')) {
    function shed_render_news_cropper_modal() {
        if (!shed_news_cropper_should_load()) {
            return;
        }
        ?>
        <div id="shed-cropper-modal" style="display:none;">
            <div id="shed-cropper-backdrop"></div>
            <div id="shed-cropper-panel">
                <div class="shed-cropper-wrap">
                    <img id="shed-cropper-image" src="" alt="Crop image">
                </div>
                <div class="shed-cropper-buttons">
                    <button type="button" id="shed-cropper-cancel">Cancel</button>
                    <button type="button" id="shed-cropper-apply">Apply crop</button>
                </div>
            </div>
        </div>
        <?php
    }
}

add_action('wp_footer', 'shed_render_news_cropper_modal', 100);
