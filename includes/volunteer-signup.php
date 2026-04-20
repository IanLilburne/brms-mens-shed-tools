<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_forminator_fields_by_name')) {
    function shed_get_forminator_fields_by_name($field_data_array) {
        $fields = [];

        foreach ($field_data_array as $field) {
            if (isset($field['name'])) {
                $fields[$field['name']] = $field['value'] ?? '';
            }
        }

        return $fields;
    }
}

if (!function_exists('shed_get_project_total_volunteer_hours')) {
    function shed_get_project_total_volunteer_hours($project_id) {
        $all_signups = get_posts([
            'post_type'      => 'volunteer_signup',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'project_id',
                    'value' => $project_id,
                ]
            ]
        ]);

        $total_hours = 0;

        foreach ($all_signups as $signup) {
            $total_hours += intval(get_post_meta($signup->ID, 'volunteer_hours', true));
        }

        return $total_hours;
    }
}

if (!function_exists('shed_update_project_volunteer_totals')) {
    function shed_update_project_volunteer_totals($project_id) {
        $hours_required  = intval(get_post_meta($project_id, 'hours_required', true));
        $new_total_hours = shed_get_project_total_volunteer_hours($project_id);

        update_post_meta($project_id, 'hours_committed', $new_total_hours);

        if ($hours_required > 0 && $new_total_hours >= $hours_required) {
            update_post_meta($project_id, 'volunteer_status', 'volunteer_goal_achieved');
        } else {
            update_post_meta($project_id, 'volunteer_status', 'seeking_volunteers');
        }

        return $new_total_hours;
    }
}

if (!function_exists('shed_save_volunteer_signup_from_forminator')) {
    function shed_save_volunteer_signup_from_forminator($field_data_array, $form_id) {
        if ((int) $form_id !== 718) {
            return $field_data_array;
        }

        $fields = shed_get_forminator_fields_by_name($field_data_array);

        $project_id      = isset($fields['select-1']) ? intval($fields['select-1']) : 0;
        $volunteer_name  = isset($fields['text-1']) ? sanitize_text_field($fields['text-1']) : '';
        $requested_hours = isset($fields['number-1']) ? intval($fields['number-1']) : 0;
        $notes           = isset($fields['textarea-1']) ? sanitize_textarea_field($fields['textarea-1']) : '';
        $timestamp       = current_time('mysql');

        if ($project_id <= 0 || $volunteer_name === '' || $requested_hours <= 0) {
            shed_log('VOLUNTEER SIGNUP: missing required fields, aborting', [
                'project_id'      => $project_id,
                'volunteer_name'  => $volunteer_name,
                'requested_hours' => $requested_hours,
            ]);
            return $field_data_array;
        }

        $project_post = get_post($project_id);

        if (!$project_post) {
            shed_log('VOLUNTEER SIGNUP: project not found at all', ['project_id' => $project_id]);
            return $field_data_array;
        }

        if ($project_post->post_type !== 'project') {
            shed_log('VOLUNTEER SIGNUP: linked project not a project post', [
                'project_id' => $project_id,
                'post_type'  => $project_post->post_type,
            ]);
            return $field_data_array;
        }

        $project_ref     = get_post_meta($project_id, 'project_ref', true);
        $project_title   = get_the_title($project_id);
        $hours_required  = intval(get_post_meta($project_id, 'hours_required', true));
        $hours_committed = intval(get_post_meta($project_id, 'hours_committed', true));

        $remaining_hours = max(0, $hours_required - $hours_committed);
        $final_hours     = min($requested_hours, $remaining_hours);

        if ($final_hours <= 0) {
            shed_log('VOLUNTEER SIGNUP: no remaining hours available', [
                'project_id'      => $project_id,
                'hours_required'  => $hours_required,
                'hours_committed' => $hours_committed,
                'requested_hours' => $requested_hours,
            ]);
            return $field_data_array;
        }

        $signup_title = $volunteer_name . ' – ';
        if ($project_ref !== '') {
            $signup_title .= $project_ref . ' - ';
        }
        $signup_title .= $project_title;

        $signup_post_id = wp_insert_post([
            'post_type'   => 'volunteer_signup',
            'post_title'  => $signup_title,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($signup_post_id) || !$signup_post_id) {
            shed_log(
                'VOLUNTEER SIGNUP: wp_insert_post failed',
                is_wp_error($signup_post_id) ? $signup_post_id->get_error_message() : 'unknown'
            );
            return $field_data_array;
        }

        update_post_meta($signup_post_id, 'volunteer_name', $volunteer_name);
        update_post_meta($signup_post_id, 'volunteer_hours', $final_hours);
        update_post_meta($signup_post_id, 'project_id', $project_id);
        update_post_meta($signup_post_id, 'notes', $notes);
        update_post_meta($signup_post_id, 'timestamp', $timestamp);

        $new_total_hours = shed_update_project_volunteer_totals($project_id);

        shed_log('VOLUNTEER SIGNUP: saved successfully', [
            'signup_post_id'   => $signup_post_id,
            'project_id'       => $project_id,
            'volunteer_name'   => $volunteer_name,
            'final_hours'      => $final_hours,
            'new_total_hours'  => $new_total_hours,
        ]);

        return $field_data_array;
    }
}

add_action('forminator_custom_form_submit_field_data', 'shed_save_volunteer_signup_from_forminator', 10, 2);