<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_tv_type_filter')) {
    function shed_get_tv_type_filter($fallback = 'project') {
        $type_filter = isset($_GET['tv_type']) ? sanitize_key((string) wp_unslash($_GET['tv_type'])) : $fallback;

        return shed_get_dashboard_type_filter($type_filter);
    }
}

if (!function_exists('shed_get_tv_status_filter')) {
    function shed_get_tv_status_filter($type_filter) {
        $status_filter = isset($_GET['tv_status']) ? sanitize_key((string) wp_unslash($_GET['tv_status'])) : '';

        if ($type_filter === 'event' || $type_filter === 'idea') {
            return in_array($status_filter, ['open', 'ended', 'all'], true) ? $status_filter : 'open';
        }

        if ($type_filter === 'video') {
            return in_array($status_filter, ['active', 'archived', 'all'], true) ? $status_filter : 'active';
        }

        if ($type_filter === 'project') {
            return in_array($status_filter, ['active', 'quote', 'making', 'invoicing', 'complete', 'all'], true) ? $status_filter : 'active';
        }

        return 'all';
    }
}

if (!function_exists('shed_get_dashboard_type_filter')) {
    function shed_get_dashboard_type_filter($type_filter) {
        $type_filter = sanitize_key((string) $type_filter);

        if ($type_filter === 'videos') {
            $type_filter = 'video';
        }

        if (!in_array($type_filter, ['all', 'project', 'idea', 'event', 'video'], true)) {
            return 'all';
        }

        return $type_filter;
    }
}

if (!function_exists('shed_get_stage_labels')) {
    function shed_get_stage_labels() {
        return [
            'quote'        => 'Quote',
            'making'       => 'Making',
            'in_progress'  => 'Making',
            'invoicing'    => 'Invoicing',
            'complete'     => 'Complete',
        ];
    }
}

if (!function_exists('shed_get_event_status_label')) {
    function shed_get_event_status_label($status) {
        return $status === 'ended' ? 'Ended' : 'Open';
    }
}

if (!function_exists('shed_get_training_video_status')) {
    function shed_get_training_video_status($project_id) {
        $video_status = sanitize_key((string) get_post_meta($project_id, 'training_video_status', true));

        return in_array($video_status, ['active', 'archived'], true) ? $video_status : 'active';
    }
}

if (!function_exists('shed_get_training_video_status_label')) {
    function shed_get_training_video_status_label($status) {
        return $status === 'archived' ? 'Archived' : 'Active';
    }
}

if (!function_exists('shed_normalize_project_stage')) {
    function shed_normalize_project_stage($project_stage) {
        $project_stage = sanitize_key((string) $project_stage);

        if ($project_stage === '' || $project_stage === 'awaiting_you') {
            return 'quote';
        }

        if ($project_stage === 'in_progress') {
            return 'making';
        }

        if (!in_array($project_stage, ['quote', 'making', 'invoicing', 'complete'], true)) {
            return 'quote';
        }

        return $project_stage;
    }
}

if (!function_exists('shed_get_idea_status')) {
    function shed_get_idea_status($project_id) {
        $idea_status = sanitize_key((string) get_post_meta($project_id, 'idea_status', true));

        return in_array($idea_status, ['open', 'ended'], true) ? $idea_status : 'open';
    }
}

if (!function_exists('shed_get_tv_dashboard_project_type')) {
    function shed_get_tv_dashboard_project_type($project_id) {
        $project_type = shed_get_project_type($project_id);
        $project_stage = sanitize_key((string) get_post_meta($project_id, 'project_stage', true));

        if ($project_type === 'project' && $project_stage === 'awaiting_you') {
            return 'idea';
        }

        return $project_type;
    }
}

if (!function_exists('shed_should_include_project_in_tv_dashboard')) {
    function shed_should_include_project_in_tv_dashboard($project_id, $type_filter = 'project', $status_filter = '') {
        $project_type = shed_get_tv_dashboard_project_type($project_id);
        $type_filter  = shed_get_dashboard_type_filter($type_filter);
        $status_filter = shed_get_tv_status_filter($type_filter);

        if ($type_filter !== 'all' && $project_type !== $type_filter) {
            return false;
        }

        if ($project_type === 'event') {
            $event_status = sanitize_key((string) get_post_meta($project_id, 'event_status', true));
            if (!in_array($event_status, ['open', 'ended'], true)) {
                $event_status = 'open';
            }

            return $status_filter === 'all' || $event_status === $status_filter;
        }

        if ($project_type === 'idea') {
            $idea_status = shed_get_idea_status($project_id);

            return $status_filter === 'all' || $idea_status === $status_filter;
        }

        if ($project_type === 'video') {
            $video_status = shed_get_training_video_status($project_id);

            return $status_filter === 'all' || $video_status === $status_filter;
        }

        if ($project_type !== 'project') {
            return true;
        }

        $project_stage = shed_normalize_project_stage(get_post_meta($project_id, 'project_stage', true));

        if ($status_filter === 'active') {
            return $project_stage !== 'complete';
        }

        return $status_filter === 'all' || $project_stage === $status_filter;
    }
}

if (!function_exists('shed_get_tv_dashboard_projects')) {
    function shed_get_tv_dashboard_projects($type_filter = 'project', $status_filter = '') {
        $all_projects = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $projects = [];

        foreach ($all_projects as $project) {
            if (shed_should_include_project_in_tv_dashboard($project->ID, $type_filter, $status_filter)) {
                $projects[] = $project;
            }
        }

        return $projects;
    }
}

if (!function_exists('shed_get_tv_dashboard_video_items')) {
    function shed_get_tv_dashboard_video_items($status_filter = 'active') {
        $videos = [];

        foreach (shed_get_tv_dashboard_projects('video', $status_filter) as $project) {
            $video_data = shed_get_tv_dashboard_video_data($project);

            if ($video_data) {
                $videos[] = $video_data;
            }
        }

        usort($videos, static function ($left, $right) {
            $left_title  = isset($left['title']) ? (string) $left['title'] : '';
            $right_title = isset($right['title']) ? (string) $right['title'] : '';

            return strnatcasecmp($left_title, $right_title);
        });

        return $videos;
    }
}

if (!function_exists('shed_get_project_dashboard_status')) {
    function shed_get_project_dashboard_status($project_id) {
        $project_stage = shed_normalize_project_stage(get_post_meta($project_id, 'project_stage', true));

        if ($project_stage === 'complete') {
            return [
                'label'     => 'Complete',
                'bar_color' => '#b30000',
                'class'     => 'shed-status-full',
            ];
        }

        $stage_labels = shed_get_stage_labels();

        return [
            'label'     => $stage_labels[$project_stage] ?? 'Quote',
            'bar_color' => '#0a7f00',
            'class'     => 'shed-status-open',
        ];
    }
}

if (!function_exists('shed_get_project_tasks')) {
    function shed_get_project_tasks($project_id) {
        $tasks = get_post_meta($project_id, 'project_tasks', true);

        if (!is_array($tasks)) {
            return [];
        }

        $normalized = [];

        foreach ($tasks as $task_index => $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_name      = trim((string) ($task['task'] ?? ''));
            $est_hours      = intval($task['est_hours'] ?? 0);
            $volunteer_name = trim((string) ($task['volunteer_name'] ?? ''));
            $done           = !empty($task['done']);

            if ($task_name === '' && $est_hours <= 0 && $volunteer_name === '') {
                continue;
            }

            $normalized[] = [
                'task_index'     => $task_index,
                'done'           => $done,
                'task'           => $task_name,
                'est_hours'      => max(0, min(99, $est_hours)),
                'volunteer_name' => $volunteer_name,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('shed_get_project_costings')) {
    function shed_get_project_costings($project_id) {
        $costings = get_post_meta($project_id, 'project_costings', true);

        if (!is_array($costings)) {
            return [];
        }

        $normalized = [];

        foreach ($costings as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = trim((string) ($row['item'] ?? ''));
            $qty = is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : 0;
            $unit_price = is_numeric($row['unit_price'] ?? null) ? (float) $row['unit_price'] : 0;
            $line_total = $qty * $unit_price;

            if ($item === '' && $qty <= 0 && $unit_price <= 0) {
                continue;
            }

            $normalized[] = [
                'item'              => $item,
                'qty'               => $qty,
                'unit_price'        => $unit_price,
                'line_total'        => $line_total,
                'qty_display'       => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.'),
                'unit_price_display'=> number_format($unit_price, 2, '.', ''),
                'line_total_display'=> number_format($line_total, 2, '.', ''),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('shed_get_project_costings_grand_total')) {
    function shed_get_project_costings_grand_total($costings) {
        $grand_total = 0.0;

        foreach ($costings as $row) {
            $grand_total += (float) ($row['line_total'] ?? 0);
        }

        return $grand_total;
    }
}

if (!function_exists('shed_get_project_task_hour_totals')) {
    function shed_get_project_task_hour_totals($project_id) {
        $tasks = shed_get_project_tasks($project_id);
        $required = 0;
        $committed = 0;

        foreach ($tasks as $task) {
            $hours = intval($task['est_hours'] ?? 0);
            $required += $hours;

            if (!empty($task['volunteer_name'])) {
                $committed += $hours;
            }
        }

        return [
            'required'  => $required,
            'committed' => $committed,
        ];
    }
}

if (!function_exists('shed_get_project_contact')) {
    function shed_get_project_contact($project_id) {
        $candidate_keys = [
            'project_contact',
            'project_lead',
            'project_lead_name',
            'lead_name',
        ];

        foreach ($candidate_keys as $meta_key) {
            $value = trim((string) get_post_meta($project_id, $meta_key, true));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('shed_get_tv_dashboard_common_data')) {
    function shed_get_tv_dashboard_common_data($project) {
        $project_id   = is_object($project) ? $project->ID : intval($project);
        $project_post = is_object($project) ? $project : get_post($project_id);

        if (!$project_post) {
            return null;
        }

        $image_html = '';
        $image_url = '';
        if (has_post_thumbnail($project_id)) {
            $image_html = get_the_post_thumbnail($project_id, 'large');
            $image_url = (string) get_the_post_thumbnail_url($project_id, 'large');
        }

        return [
            'project_id'    => $project_id,
            'project_type'  => shed_get_project_type($project_id),
            'title'         => $project_post->post_title,
            'description'   => trim(wp_strip_all_tags($project_post->post_content)),
            'project_notes' => trim((string) get_post_meta($project_id, 'project_notes', true)),
            'image_html'    => $image_html,
            'image_url'     => $image_url,
            'project_contact' => shed_get_project_contact($project_id),
            'create_from_idea_url' => add_query_arg(
                'source_idea_id',
                $project_id,
                site_url('/home/members-area/create-project/')
            ),
        ];
    }
}

if (!function_exists('shed_get_tv_dashboard_project_data')) {
    function shed_get_tv_dashboard_project_data($project) {
        $data = shed_get_tv_dashboard_common_data($project);

        if (!$data) {
            return null;
        }

        $project_id = $data['project_id'];
        $project_ref = get_post_meta($project_id, 'project_ref', true);
        $task_hour_totals = shed_get_project_task_hour_totals($project_id);
        $required = $task_hour_totals['required'];
        $committed = $task_hour_totals['committed'];
        $target = get_post_meta($project_id, 'completion_target_date', true);
        $project_stage = shed_normalize_project_stage(get_post_meta($project_id, 'project_stage', true));
        $project_costings = shed_get_project_costings($project_id);
        $project_costings_grand_total = shed_get_project_costings_grand_total($project_costings);
        $stage_labels = shed_get_stage_labels();

        $status_data = shed_get_project_dashboard_status($project_id);

        return array_merge($data, [
            'project_ref'     => $project_ref,
            'required'        => $required,
            'committed'       => $committed,
            'target'          => $target,
            'project_stage'   => $project_stage,
            'project_contact' => shed_get_project_contact($project_id),
            'project_tasks'   => shed_get_project_tasks($project_id),
            'project_costings' => $project_costings,
            'project_costings_grand_total' => $project_costings_grand_total,
            'project_costings_grand_total_display' => number_format($project_costings_grand_total, 2, '.', ''),
            'project_pdf_url' => shed_get_project_pdf_url($project_id),
            'project_pdf_name' => shed_get_project_pdf_filename($project_id),
            'project_lifecycle_label' => $stage_labels[$project_stage] ?? 'Quote',
            'percent'         => $required > 0 ? min(100, round(($committed / $required) * 100)) : 0,
            'status'          => $status_data['label'],
            'bar_color'       => $status_data['bar_color'],
            'status_cls'      => $status_data['class'],
        ]);
    }
}

if (!function_exists('shed_get_tv_dashboard_idea_data')) {
    function shed_get_tv_dashboard_idea_data($project) {
        $data = shed_get_tv_dashboard_common_data($project);

        if (!$data) {
            return null;
        }

        return array_merge($data, [
            'idea_label' => 'PROJECT IDEA',
            'idea_status' => shed_get_idea_status($data['project_id']),
            'project_pdf_url' => shed_get_project_pdf_url($data['project_id']),
            'project_pdf_name' => shed_get_project_pdf_filename($data['project_id']),
            'idea_pdf_url' => shed_get_project_pdf_url($data['project_id']),
            'idea_pdf_name' => shed_get_project_pdf_filename($data['project_id']),
            'project_tasks' => shed_get_project_tasks($data['project_id']),
        ]);
    }
}

if (!function_exists('shed_get_tv_dashboard_event_data')) {
    function shed_get_tv_dashboard_event_data($project) {
        $data = shed_get_tv_dashboard_common_data($project);

        if (!$data) {
            return null;
        }

        $project_id = $data['project_id'];
        $event_status = sanitize_key((string) get_post_meta($project_id, 'event_status', true));

        if (!in_array($event_status, ['open', 'ended'], true)) {
            $event_status = 'open';
        }

        return array_merge($data, [
            'event_date'         => get_post_meta($project_id, 'event_date', true),
            'event_location'     => get_post_meta($project_id, 'event_location', true),
            'event_status'       => $event_status,
            'event_status_label' => shed_get_event_status_label($event_status),
        ]);
    }
}

if (!function_exists('shed_get_tv_dashboard_video_data')) {
    function shed_get_tv_dashboard_video_data($project) {
        $data = shed_get_tv_dashboard_common_data($project);

        if (!$data) {
            return null;
        }

        $project_id = $data['project_id'];
        $video_status = shed_get_training_video_status($project_id);

        return array_merge($data, [
            'video_url'          => esc_url_raw((string) get_post_meta($project_id, 'training_video_url', true)),
            'video_category'     => trim((string) get_post_meta($project_id, 'training_video_category', true)),
            'video_duration'     => trim((string) get_post_meta($project_id, 'training_video_duration', true)),
            'video_status'       => $video_status,
            'video_status_label' => shed_get_training_video_status_label($video_status),
        ]);
    }
}
