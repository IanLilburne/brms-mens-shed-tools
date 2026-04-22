<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_next_project_ref')) {
    function shed_get_next_project_ref() {
        $projects = get_posts([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => 'project_ref',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);

        $max_num = 0;

        foreach ($projects as $project_id) {
            $ref = get_post_meta($project_id, 'project_ref', true);

            if (preg_match('/^P(\d+)$/', (string) $ref, $matches)) {
                $num = intval($matches[1]);
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
        }

        $next_num = $max_num + 1;

        return 'P' . str_pad((string) $next_num, 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('shed_import_project_featured_image')) {
    function shed_import_project_featured_image($uploaded_image, $post_id) {
        if (
            empty($uploaded_image) ||
            !is_array($uploaded_image) ||
            !isset($uploaded_image['file']) ||
            !is_array($uploaded_image['file'])
        ) {
            error_log('SAVE NEW PROJECT: no usable upload image found');
            return false;
        }

        $file = $uploaded_image['file'];

        if (
            empty($file['tmp_name']) ||
            empty($file['name']) ||
            !isset($file['error']) ||
            (int) $file['error'] !== 0 ||
            !file_exists($file['tmp_name'])
        ) {
            error_log('SAVE NEW PROJECT: upload file array incomplete or tmp file missing');
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp_copy = wp_tempnam($file['name']);

        if (!$temp_copy || !@copy($file['tmp_name'], $temp_copy)) {
            error_log('SAVE NEW PROJECT: could not create temp copy of uploaded image');
            return false;
        }

        $file_array = [
            'name'     => sanitize_file_name($file['name']),
            'tmp_name' => $temp_copy,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('SAVE NEW PROJECT: image import failed: ' . $attachment_id->get_error_message());

            if (file_exists($temp_copy)) {
                @unlink($temp_copy);
            }

            return false;
        }

        set_post_thumbnail($post_id, $attachment_id);
        error_log('SAVE NEW PROJECT: featured image set, attachment ID ' . $attachment_id);

        return $attachment_id;
    }
}

if (!function_exists('shed_save_new_project_from_forminator')) {
    function shed_save_new_project_from_forminator($field_data_array, $form_id) {
        if ((int) $form_id !== 683) {
            return $field_data_array;
        }

        $fields = [];

        foreach ($field_data_array as $field) {
            if (isset($field['name'])) {
                $fields[$field['name']] = $field['value'] ?? '';
            }
        }

        $project_name    = isset($fields['text-1']) ? sanitize_text_field($fields['text-1']) : '';
        $project_contact = isset($fields['text-2']) ? sanitize_text_field($fields['text-2']) : '';
        $description     = isset($fields['textarea-2']) ? sanitize_textarea_field($fields['textarea-2']) : '';
        $hours_required  = isset($fields['number-1']) ? intval($fields['number-1']) : 0;
        $target_date     = isset($fields['date-1']) ? sanitize_text_field($fields['date-1']) : '';
        $uploaded_image  = $fields['upload-1'] ?? '';
        $timestamp       = current_time('mysql');

        if ($project_name === '' || $hours_required <= 0 || $project_contact === '') {
            error_log('SAVE NEW PROJECT: missing required fields, aborting');
            return $field_data_array;
        }

        $project_ref = shed_get_next_project_ref();

        $post_id = wp_insert_post([
            'post_type'    => 'project',
            'post_status'  => 'publish',
            'post_title'   => $project_name,
            'post_content' => $description,
        ], true);

        if (is_wp_error($post_id)) {
            error_log('SAVE NEW PROJECT: wp_insert_post failed: ' . $post_id->get_error_message());
            return $field_data_array;
        }

        update_post_meta($post_id, 'project_ref', $project_ref);
        update_post_meta($post_id, 'project_contact', $project_contact);
        update_post_meta($post_id, 'hours_required', $hours_required);
        update_post_meta($post_id, 'hours_committed', 0);
        update_post_meta($post_id, 'volunteer_status', 'seeking_volunteers');
        update_post_meta($post_id, 'project_stage', 'quote');
        update_post_meta($post_id, 'completion_target_date', $target_date);
        update_post_meta($post_id, 'timestamp', $timestamp);

        shed_import_project_featured_image($uploaded_image, $post_id);

        error_log('SAVE NEW PROJECT: created project ' . $project_ref . ' - ' . $project_name . ' (post ID ' . $post_id . ')');

        return $field_data_array;
    }
}

add_action('forminator_custom_form_submit_field_data', 'shed_save_new_project_from_forminator', 10, 2);
