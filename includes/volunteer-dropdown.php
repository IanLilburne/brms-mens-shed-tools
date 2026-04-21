<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_populate_volunteer_project_dropdown')) {
    function shed_populate_volunteer_project_dropdown($wrappers, $form_id) {
        if ((int) $form_id !== 718) {
            return $wrappers;
        }

        $target_select_id    = 'select-1';
        $selected_project_id = isset($_GET['project_id']) ? (string) absint($_GET['project_id']) : '';

        $projects = get_posts([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $new_options = [];

        foreach ($projects as $project) {
            $project_id    = (string) $project->ID;
            $project_stage = get_post_meta($project->ID, 'project_stage', true);

            if ($project_stage === 'complete') {
                continue;
            }

            $project_ref   = get_post_meta($project->ID, 'project_ref', true);
            $project_title = $project->post_title;

            $label = $project_title;
            if ($project_ref !== '') {
                $label = $project_ref . ' - ' . $project_title;
            }

            $new_options[] = [
                'label'    => $label,
                'value'    => $project_id,
                'limit'    => '',
                'key'      => function_exists('forminator_unique_key') ? forminator_unique_key() : uniqid(),
                'selected' => ($selected_project_id !== '' && $selected_project_id === $project_id) ? 'true' : '',
            ];
        }

        foreach ($wrappers as $wrapper_key => $wrapper) {
            if (!isset($wrapper['fields']) || !is_array($wrapper['fields'])) {
                continue;
            }

            foreach ($wrapper['fields'] as $field_key => $field) {
                if (
                    isset($field['element_id']) &&
                    $field['element_id'] === $target_select_id
                ) {
                    $wrappers[$wrapper_key]['fields'][$field_key]['options'] = $new_options;
                }
            }
        }

        return $wrappers;
    }
}

add_filter('forminator_cform_render_fields', 'shed_populate_volunteer_project_dropdown', 10, 2);