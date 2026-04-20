<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_tv_filter')) {
    function shed_get_tv_filter() {
        return isset($_GET['tv_filter']) ? sanitize_key($_GET['tv_filter']) : 'all';
    }
}

if (!function_exists('shed_get_stage_labels')) {
    function shed_get_stage_labels() {
        return [
            'quote'        => 'Quote',
            'awaiting_you' => 'Awaiting you!',
            'in_progress'  => 'In progress',
            'invoicing'    => 'Invoicing',
            'complete'     => 'Complete',
        ];
    }
}

if (!function_exists('shed_should_include_project_in_tv_dashboard')) {
    function shed_should_include_project_in_tv_dashboard($project_id, $tv_filter = 'all') {
        $volunteer_status = get_post_meta($project_id, 'volunteer_status', true);
        $project_stage    = get_post_meta($project_id, 'project_stage', true);

        if ($volunteer_status === '') {
            $volunteer_status = 'seeking_volunteers';
        }

        if ($project_stage === '') {
            $project_stage = 'quote';
        }

        switch ($tv_filter) {
            case 'awaiting_you':
                return ($project_stage === 'awaiting_you');

            case 'seeking_volunteers':
                return ($project_stage !== 'awaiting_you' && $volunteer_status === 'seeking_volunteers');

            case 'volunteer_goal_achieved':
                return ($project_stage !== 'awaiting_you' && $volunteer_status === 'volunteer_goal_achieved');

            case 'all':
            default:
                return true;
        }
    }
}

if (!function_exists('shed_get_tv_dashboard_projects')) {
    function shed_get_tv_dashboard_projects($tv_filter = 'all') {
        $all_projects = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $projects = [];

        foreach ($all_projects as $project) {
            if (shed_should_include_project_in_tv_dashboard($project->ID, $tv_filter)) {
                $projects[] = $project;
            }
        }

        return $projects;
    }
}

if (!function_exists('shed_get_project_dashboard_status')) {
    function shed_get_project_dashboard_status($project_id) {
        $volunteer_status = get_post_meta($project_id, 'volunteer_status', true);
        $project_stage    = get_post_meta($project_id, 'project_stage', true);

        if ($volunteer_status === '') {
            $volunteer_status = 'seeking_volunteers';
        }

        if ($project_stage === '') {
            $project_stage = 'quote';
        }

        if ($project_stage === 'awaiting_you') {
            return [
                'label'     => 'How about this?',
                'bar_color' => '#d4a017',
                'class'     => 'shed-status-idea',
            ];
        }

        if ($volunteer_status === 'volunteer_goal_achieved') {
            return [
                'label'     => 'Goal achieved',
                'bar_color' => '#b30000',
                'class'     => 'shed-status-full',
            ];
        }

        return [
            'label'     => 'Seeking volunteers',
            'bar_color' => '#0a7f00',
            'class'     => 'shed-status-open',
        ];
    }
}

if (!function_exists('shed_get_project_recent_volunteers')) {
    function shed_get_project_recent_volunteers($project_id, $limit = 4) {
        return get_posts([
            'post_type'      => 'volunteer_signup',
            'posts_per_page' => intval($limit),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => 'project_id',
                    'value' => $project_id,
                ]
            ]
        ]);
    }
}

if (!function_exists('shed_get_volunteer_signup_summary')) {
    function shed_get_volunteer_signup_summary($signup_post_id) {
        return [
            'name'  => get_post_meta($signup_post_id, 'volunteer_name', true),
            'hours' => get_post_meta($signup_post_id, 'volunteer_hours', true),
        ];
    }
}

if (!function_exists('shed_get_tv_dashboard_project_data')) {
    function shed_get_tv_dashboard_project_data($project) {
        $project_id = is_object($project) ? $project->ID : intval($project);
        $project_post = is_object($project) ? $project : get_post($project_id);

        if (!$project_post) {
            return null;
        }

        $project_ref   = get_post_meta($project_id, 'project_ref', true);
        $required      = intval(get_post_meta($project_id, 'hours_required', true));
        $committed     = intval(get_post_meta($project_id, 'hours_committed', true));
        $target        = get_post_meta($project_id, 'completion_target_date', true);
        $project_stage = get_post_meta($project_id, 'project_stage', true);

        if ($project_stage === '') {
            $project_stage = 'quote';
        }

        $percent = $required > 0 ? min(100, round(($committed / $required) * 100)) : 0;

        $status_data = shed_get_project_dashboard_status($project_id);

        $description = wp_trim_words(wp_strip_all_tags($project_post->post_content), 24);

        $volunteer_url = add_query_arg(
            'project_id',
            $project_id,
            site_url('/home/members-area/projects-volunteer-signup/')
        );

        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($volunteer_url);

        $image_html = '';
        if (has_post_thumbnail($project_id)) {
            $image_html = get_the_post_thumbnail($project_id, 'large');
        }

        return [
            'project_id'     => $project_id,
            'project_ref'    => $project_ref,
            'required'       => $required,
            'committed'      => $committed,
            'target'         => $target,
            'project_stage'  => $project_stage,
            'percent'        => $percent,
            'status'         => $status_data['label'],
            'bar_color'      => $status_data['bar_color'],
            'status_cls'     => $status_data['class'],
            'description'    => $description,
            'volunteer_url'  => $volunteer_url,
            'qr_url'         => $qr_url,
            'image_html'     => $image_html,
        ];
    }
}