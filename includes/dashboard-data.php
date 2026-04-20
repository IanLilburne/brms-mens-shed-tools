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