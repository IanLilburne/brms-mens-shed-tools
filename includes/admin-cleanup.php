<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'shed_hide_old_volunteer_signup_menu', 999);
add_filter('manage_project_posts_columns', 'shed_project_admin_columns');
add_action('manage_project_posts_custom_column', 'shed_project_admin_column_content', 10, 2);
add_action('restrict_manage_posts', 'shed_project_admin_filters');
add_action('pre_get_posts', 'shed_project_admin_filter_query');
add_filter('get_edit_post_link', 'shed_project_admin_frontend_edit_link', 10, 3);

if (!function_exists('shed_hide_old_volunteer_signup_menu')) {
    function shed_hide_old_volunteer_signup_menu() {
        remove_menu_page('edit.php?post_type=volunteer-signup');
    }
}

if (!function_exists('shed_project_admin_columns')) {
    function shed_project_admin_columns($columns) {
        return [
            'cb'                     => $columns['cb'] ?? '<input type="checkbox">',
            'shed_project_ref'       => 'Project ID',
            'title'                  => 'Project name',
            'shed_project_type'      => 'Type',
            'shed_project_lifecycle' => 'Lifecycle',
            'shed_completion_date'   => 'Completion date',
            'shed_project_contact'   => 'Project contact',
            'shed_task_count'        => 'Tasks',
            'shed_costing_total'     => 'Costing total',
            'date'                   => $columns['date'] ?? 'Date',
        ];
    }
}

if (!function_exists('shed_project_admin_column_content')) {
    function shed_project_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'shed_project_ref':
                $project_ref = get_post_meta($post_id, 'project_ref', true);
                echo $project_ref !== '' ? esc_html($project_ref) : '&mdash;';
                break;

            case 'shed_project_type':
                echo esc_html(shed_get_project_type_label(shed_get_tv_dashboard_project_type($post_id)));
                break;

            case 'shed_project_lifecycle':
                echo esc_html(shed_project_admin_get_lifecycle_label($post_id));
                break;

            case 'shed_completion_date':
                $date = shed_normalize_date_input(get_post_meta($post_id, 'completion_target_date', true));
                echo $date !== '' ? esc_html($date) : '&mdash;';
                break;

            case 'shed_project_contact':
                $contact = shed_get_project_contact($post_id);
                echo $contact !== '' ? esc_html($contact) : '&mdash;';
                break;

            case 'shed_task_count':
                echo esc_html((string) shed_project_admin_count_tasks($post_id));
                break;

            case 'shed_costing_total':
                echo esc_html(shed_project_admin_format_costing_total($post_id));
                break;
        }
    }
}

if (!function_exists('shed_project_admin_filters')) {
    function shed_project_admin_filters($post_type) {
        if ($post_type !== 'project') {
            return;
        }

        $selected_type = isset($_GET['shed_project_type']) ? sanitize_key(wp_unslash($_GET['shed_project_type'])) : '';
        $selected_lifecycle = isset($_GET['shed_project_lifecycle']) ? sanitize_key(wp_unslash($_GET['shed_project_lifecycle'])) : '';
        ?>
        <select name="shed_project_type">
            <option value="">All project types</option>
            <option value="project" <?php selected($selected_type, 'project'); ?>>Project</option>
            <option value="idea" <?php selected($selected_type, 'idea'); ?>>Idea</option>
            <option value="event" <?php selected($selected_type, 'event'); ?>>Event</option>
            <option value="video" <?php selected($selected_type, 'video'); ?>>Training video</option>
        </select>

        <select name="shed_project_lifecycle">
            <option value="">All lifecycles</option>
            <option value="quote" <?php selected($selected_lifecycle, 'quote'); ?>>Quote</option>
            <option value="making" <?php selected($selected_lifecycle, 'making'); ?>>Making</option>
            <option value="invoicing" <?php selected($selected_lifecycle, 'invoicing'); ?>>Invoicing</option>
            <option value="complete" <?php selected($selected_lifecycle, 'complete'); ?>>Complete</option>
        </select>
        <?php
    }
}

if (!function_exists('shed_project_admin_filter_query')) {
    function shed_project_admin_filter_query($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'project') {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $type = isset($_GET['shed_project_type']) ? sanitize_key(wp_unslash($_GET['shed_project_type'])) : '';
        $lifecycle = isset($_GET['shed_project_lifecycle']) ? sanitize_key(wp_unslash($_GET['shed_project_lifecycle'])) : '';

        if (in_array($type, ['project', 'idea', 'event', 'video'], true)) {
            $meta_query[] = [
                'key'   => 'project_type',
                'value' => $type,
            ];
        }

        if (in_array($lifecycle, ['quote', 'making', 'invoicing', 'complete'], true)) {
            $meta_query[] = [
                'key'   => 'project_stage',
                'value' => $lifecycle,
            ];
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }
}

if (!function_exists('shed_project_admin_frontend_edit_link')) {
    function shed_project_admin_frontend_edit_link($link, $post_id, $context) {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'project') {
            return $link;
        }

        return add_query_arg('project_id', $post_id, site_url('/home/members-area/create-project/'));
    }
}

if (!function_exists('shed_project_admin_get_lifecycle_label')) {
    function shed_project_admin_get_lifecycle_label($post_id) {
        $project_type = shed_get_tv_dashboard_project_type($post_id);

        if ($project_type === 'idea') {
            return ucfirst(shed_get_idea_status($post_id));
        }

        if ($project_type === 'event') {
            $event_status = sanitize_key((string) get_post_meta($post_id, 'event_status', true));
            return $event_status === 'ended' ? 'Ended' : 'Open';
        }

        if ($project_type === 'video') {
            return shed_get_training_video_status_label(shed_get_training_video_status($post_id));
        }

        $stage_labels = shed_get_stage_labels();
        $stage = shed_normalize_project_stage(get_post_meta($post_id, 'project_stage', true));

        return $stage_labels[$stage] ?? ucfirst($stage);
    }
}

if (!function_exists('shed_project_admin_count_tasks')) {
    function shed_project_admin_count_tasks($post_id) {
        return count(shed_get_project_tasks($post_id));
    }
}

if (!function_exists('shed_project_admin_get_costing_total')) {
    function shed_project_admin_get_costing_total($post_id) {
        $costings = get_post_meta($post_id, 'project_costings', true);

        if (!is_array($costings)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($costings as $row) {
            if (!is_array($row)) {
                continue;
            }

            $qty = isset($row['qty']) ? (float) $row['qty'] : 0.0;
            $unit_price = isset($row['unit_price']) ? (float) $row['unit_price'] : 0.0;
            $total += max(0, $qty) * max(0, $unit_price);
        }

        return $total;
    }
}

if (!function_exists('shed_project_admin_format_costing_total')) {
    function shed_project_admin_format_costing_total($post_id) {
        $total = shed_project_admin_get_costing_total($post_id);
        $currency_symbol = html_entity_decode('&pound;', ENT_QUOTES, 'UTF-8');

        if ($total <= 0) {
            return $currency_symbol . '0.00';
        }

        return $currency_symbol . number_format($total, 2);
    }
}
