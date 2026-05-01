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
            if (shed_get_project_type($project_id) !== 'project') {
                continue;
            }

            $ref = get_post_meta($project_id, 'project_ref', true);

            if (preg_match('/^P(\d+)$/', (string) $ref, $matches)) {
                $max_num = max($max_num, intval($matches[1]));
            }
        }

        return 'P' . str_pad((string) ($max_num + 1), 3, '0', STR_PAD_LEFT);
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
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp_copy = wp_tempnam($file['name']);

        if (!$temp_copy || !@copy($file['tmp_name'], $temp_copy)) {
            return false;
        }

        $file_array = [
            'name'     => sanitize_file_name($file['name']),
            'tmp_name' => $temp_copy,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            if (file_exists($temp_copy)) {
                @unlink($temp_copy);
            }

            return false;
        }

        set_post_thumbnail($post_id, $attachment_id);

        return $attachment_id;
    }
}

if (!function_exists('shed_import_project_featured_image_from_field')) {
    function shed_import_project_featured_image_from_field($field_name, $post_id) {
        if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return false;
        }

        $uploaded_image = [
            'file' => $_FILES[$field_name],
        ];

        return shed_import_project_featured_image($uploaded_image, $post_id);
    }
}

if (!function_exists('shed_copy_featured_image_from_source_project')) {
    function shed_copy_featured_image_from_source_project($source_post_id, $post_id) {
        $thumbnail_id = get_post_thumbnail_id($source_post_id);

        if ($thumbnail_id) {
            set_post_thumbnail($post_id, $thumbnail_id);
            return $thumbnail_id;
        }

        return false;
    }
}

if (!function_exists('shed_get_project_pdf_attachment_id')) {
    function shed_get_project_pdf_attachment_id($post_id) {
        $attachment_id = absint(get_post_meta($post_id, 'project_pdf_attachment_id', true));

        if (!$attachment_id) {
            $attachment_id = absint(get_post_meta($post_id, 'idea_pdf_attachment_id', true));
        }

        return $attachment_id;
    }
}

if (!function_exists('shed_get_project_pdf_url')) {
    function shed_get_project_pdf_url($post_id) {
        $attachment_id = shed_get_project_pdf_attachment_id($post_id);

        if (!$attachment_id) {
            return '';
        }

        $url = wp_get_attachment_url($attachment_id);

        return $url ? $url : '';
    }
}

if (!function_exists('shed_get_project_pdf_filename')) {
    function shed_get_project_pdf_filename($post_id) {
        $attachment_id = shed_get_project_pdf_attachment_id($post_id);

        if (!$attachment_id) {
            return '';
        }

        $file = get_attached_file($attachment_id);

        if ($file) {
            return basename($file);
        }

        return get_the_title($attachment_id);
    }
}

if (!function_exists('shed_upload_project_pdf_attachment')) {
    function shed_upload_project_pdf_attachment($field_name, $post_id) {
        if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return false;
        }

        $file = $_FILES[$field_name];

        if (
            empty($file['tmp_name']) ||
            empty($file['name']) ||
            !isset($file['error']) ||
            (int) $file['error'] !== 0 ||
            !is_uploaded_file($file['tmp_name'])
        ) {
            return false;
        }

        $file_check = wp_check_filetype($file['name'], ['pdf' => 'application/pdf']);

        if (($file_check['ext'] ?? '') !== 'pdf') {
            return new WP_Error('invalid_idea_pdf', 'Please upload a PDF file.');
        }

        $handle = fopen($file['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 4) : '';

        if ($handle) {
            fclose($handle);
        }

        if ($header !== '%PDF') {
            return new WP_Error('invalid_idea_pdf', 'Please upload a PDF file.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = wp_handle_upload(
            $file,
            [
                'test_form' => false,
                'test_type' => false,
                'mimes'     => ['pdf' => 'application/pdf'],
            ]
        );

        if (isset($uploaded['error'])) {
            return new WP_Error('pdf_upload_failed', $uploaded['error']);
        }

        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => 'application/pdf',
                'post_title'     => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ],
            $uploaded['file'],
            $post_id
        );

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        update_post_meta($post_id, 'project_pdf_attachment_id', $attachment_id);
        delete_post_meta($post_id, 'idea_pdf_attachment_id');

        return $attachment_id;
    }
}

if (!function_exists('shed_has_uploaded_file')) {
    function shed_has_uploaded_file($field_name) {
        return (
            !empty($_FILES[$field_name]) &&
            is_array($_FILES[$field_name]) &&
            !empty($_FILES[$field_name]['tmp_name']) &&
            !empty($_FILES[$field_name]['name']) &&
            isset($_FILES[$field_name]['error']) &&
            (int) $_FILES[$field_name]['error'] === 0 &&
            is_uploaded_file($_FILES[$field_name]['tmp_name'])
        );
    }
}

if (!function_exists('shed_has_selected_upload')) {
    function shed_has_selected_upload($field_name) {
        return (
            !empty($_FILES[$field_name]) &&
            is_array($_FILES[$field_name]) &&
            !empty($_FILES[$field_name]['name']) &&
            (!isset($_FILES[$field_name]['error']) || (int) $_FILES[$field_name]['error'] !== UPLOAD_ERR_NO_FILE)
        );
    }
}

if (!function_exists('shed_get_upload_error_message')) {
    function shed_get_upload_error_message($error_code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The selected file is larger than the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The selected file is larger than the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'The selected file was only partly uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'Please choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'The upload was stopped by a server extension.',
        ];

        return $messages[$error_code] ?? 'The selected file could not be uploaded. Please try again.';
    }
}

if (!function_exists('shed_allow_project_upload_mimes')) {
    function shed_allow_project_upload_mimes($mimes) {
        foreach (shed_get_project_upload_mimes() as $extension => $mime_type) {
            $mimes[$extension] = $mime_type;
        }

        return $mimes;
    }
}

add_filter('upload_mimes', 'shed_allow_project_upload_mimes');

if (!function_exists('shed_get_project_upload_mimes')) {
    function shed_get_project_upload_mimes() {
        return [
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/x-m4v',
            'mov'  => 'video/quicktime',
            'webm' => 'video/webm',
            'ogv'  => 'video/ogg',
        ];
    }
}

if (!function_exists('shed_allow_project_filetype_check')) {
    function shed_allow_project_filetype_check($data, $file, $filename, $mimes, $real_mime = '') {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $allowed = shed_get_project_upload_mimes();

        if (!isset($allowed[$extension])) {
            return $data;
        }

        $data['ext'] = $extension;
        $data['type'] = $allowed[$extension];
        $data['proper_filename'] = false;

        return $data;
    }
}

add_filter('wp_check_filetype_and_ext', 'shed_allow_project_filetype_check', 10, 5);

if (!function_exists('shed_get_training_video_attachment_id')) {
    function shed_get_training_video_attachment_id($post_id) {
        return absint(get_post_meta($post_id, 'training_video_attachment_id', true));
    }
}

if (!function_exists('shed_upload_training_video_attachment')) {
    function shed_upload_training_video_attachment($field_name, $post_id) {
        if (!shed_has_selected_upload($field_name)) {
            return false;
        }

        $file = $_FILES[$field_name];

        if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('video_upload_failed', shed_get_upload_error_message((int) $file['error']));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('video_upload_failed', 'The selected video could not be uploaded. Please try again.');
        }

        $allowed_types = [
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/x-m4v',
            'mov'  => 'video/quicktime',
            'webm' => 'video/webm',
            'ogv'  => 'video/ogg',
        ];
        $file_check = wp_check_filetype($file['name'], $allowed_types);

        if (empty($file_check['ext']) || empty($allowed_types[$file_check['ext']])) {
            return new WP_Error('invalid_training_video', 'Please upload a video file.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = wp_handle_upload(
            $file,
            [
                'test_form' => false,
                'test_type' => false,
                'mimes'     => $allowed_types,
            ]
        );

        if (isset($uploaded['error'])) {
            return new WP_Error('video_upload_failed', $uploaded['error']);
        }

        $attachment_id = wp_insert_attachment(
            [
                'post_mime_type' => $allowed_types[$file_check['ext']],
                'post_title'     => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ],
            $uploaded['file'],
            $post_id
        );

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $video_url = wp_get_attachment_url($attachment_id);

        update_post_meta($post_id, 'training_video_attachment_id', $attachment_id);

        if ($video_url) {
            update_post_meta($post_id, 'training_video_url', esc_url_raw($video_url));
        }

        return $attachment_id;
    }
}

if (!function_exists('shed_copy_project_pdf_from_source_project')) {
    function shed_copy_project_pdf_from_source_project($source_post_id, $post_id) {
        $attachment_id = shed_get_project_pdf_attachment_id($source_post_id);

        if (!$attachment_id) {
            return false;
        }

        update_post_meta($post_id, 'project_pdf_attachment_id', $attachment_id);
        return $attachment_id;
    }
}

if (!function_exists('shed_get_idea_pdf_attachment_id')) {
    function shed_get_idea_pdf_attachment_id($post_id) {
        return shed_get_project_pdf_attachment_id($post_id);
    }
}

if (!function_exists('shed_get_idea_pdf_url')) {
    function shed_get_idea_pdf_url($post_id) {
        return shed_get_project_pdf_url($post_id);
    }
}

if (!function_exists('shed_get_idea_pdf_filename')) {
    function shed_get_idea_pdf_filename($post_id) {
        return shed_get_project_pdf_filename($post_id);
    }
}

if (!function_exists('shed_upload_idea_pdf_attachment')) {
    function shed_upload_idea_pdf_attachment($field_name, $post_id) {
        return shed_upload_project_pdf_attachment($field_name, $post_id);
    }
}

if (!function_exists('shed_is_source_project_idea')) {
    function shed_is_source_project_idea($project_id) {
        $project_type = shed_get_project_type($project_id);

        if ($project_type === 'idea') {
            return true;
        }

        return $project_type === 'project' && sanitize_key((string) get_post_meta($project_id, 'project_stage', true)) === 'awaiting_you';
    }
}

if (!function_exists('shed_get_source_idea_id_from_request')) {
    function shed_get_source_idea_id_from_request() {
        $source_idea_id = 0;

        if (isset($_POST['source_idea_id'])) {
            $source_idea_id = absint(wp_unslash($_POST['source_idea_id']));
        } elseif (isset($_GET['source_idea_id'])) {
            $source_idea_id = absint(wp_unslash($_GET['source_idea_id']));
        }

        if (!$source_idea_id) {
            return 0;
        }

        $source_post = get_post($source_idea_id);

        if (!$source_post || $source_post->post_type !== 'project') {
            return 0;
        }

        if (!shed_is_source_project_idea($source_idea_id)) {
            return 0;
        }

        return $source_idea_id;
    }
}

if (!function_exists('shed_get_source_idea_prefill_data')) {
    function shed_get_source_idea_prefill_data($source_idea_id) {
        if (!$source_idea_id) {
            return null;
        }

        $idea_post = get_post($source_idea_id);

        if (!$idea_post || $idea_post->post_type !== 'project' || !shed_is_source_project_idea($source_idea_id)) {
            return null;
        }

        return [
            'source_idea_id' => $source_idea_id,
            'project_type'   => 'project',
            'project_name'   => $idea_post->post_title,
            'description'    => $idea_post->post_content,
            'project_contact'=> shed_get_project_contact($source_idea_id),
        ];
    }
}

if (!function_exists('shed_get_source_idea_project_tasks')) {
    function shed_get_source_idea_project_tasks($source_idea_id) {
        $tasks = get_post_meta($source_idea_id, 'project_tasks', true);

        if (!is_array($tasks)) {
            return [];
        }

        $project_tasks = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_name = isset($task['task']) ? sanitize_text_field($task['task']) : '';
            $task_hours = isset($task['est_hours']) ? max(0, intval($task['est_hours'])) : 0;

            if ($task_name === '' && $task_hours <= 0) {
                continue;
            }

            $project_tasks[] = [
                'done'           => false,
                'task'           => $task_name,
                'est_hours'      => $task_hours,
                'volunteer_name' => '',
            ];
        }

        return $project_tasks;
    }
}

if (!function_exists('shed_sum_project_task_hours')) {
    function shed_sum_project_task_hours($tasks) {
        if (!is_array($tasks)) {
            return 0;
        }

        return array_sum(array_map(function ($task) {
            return is_array($task) && isset($task['est_hours']) ? intval($task['est_hours']) : 0;
        }, $tasks));
    }
}

if (!function_exists('shed_normalize_project_create_tasks')) {
    function shed_normalize_project_create_tasks($raw) {
        $task_names_raw = isset($raw['task_name']) ? wp_unslash($raw['task_name']) : [];
        $task_hours_raw = isset($raw['task_est_hours']) ? wp_unslash($raw['task_est_hours']) : [];
        $task_volunteers_raw = isset($raw['task_volunteer_name']) ? wp_unslash($raw['task_volunteer_name']) : [];
        $task_done_raw = isset($raw['task_done']) ? wp_unslash($raw['task_done']) : [];
        $project_tasks = [];

        if (!is_array($task_names_raw) || !is_array($task_hours_raw) || !is_array($task_volunteers_raw) || !is_array($task_done_raw)) {
            return $project_tasks;
        }

        $task_row_count = max(count($task_names_raw), count($task_hours_raw), count($task_volunteers_raw), count($task_done_raw));

        for ($i = 0; $i < $task_row_count; $i++) {
            $task_name = isset($task_names_raw[$i]) ? sanitize_text_field($task_names_raw[$i]) : '';
            $task_hours = isset($task_hours_raw[$i]) ? max(0, min(99, intval($task_hours_raw[$i]))) : 0;
            $task_volunteer = isset($task_volunteers_raw[$i]) ? substr(sanitize_text_field($task_volunteers_raw[$i]), 0, 15) : '';
            $task_done = isset($task_done_raw[$i]) && (string) $task_done_raw[$i] === '1';

            if ($task_name === '' && $task_hours <= 0 && $task_volunteer === '') {
                continue;
            }

            $project_tasks[] = [
                'done'           => $task_done,
                'task'           => $task_name,
                'est_hours'      => $task_hours,
                'volunteer_name' => $task_volunteer,
            ];
        }

        return $project_tasks;
    }
}

if (!function_exists('shed_normalize_project_create_costings')) {
    function shed_normalize_project_create_costings($raw) {
        $items_raw = isset($raw['costing_item']) ? wp_unslash($raw['costing_item']) : [];
        $qtys_raw = isset($raw['costing_qty']) ? wp_unslash($raw['costing_qty']) : [];
        $unit_prices_raw = isset($raw['costing_unit_price']) ? wp_unslash($raw['costing_unit_price']) : [];
        $project_costings = [];

        if (!is_array($items_raw) || !is_array($qtys_raw) || !is_array($unit_prices_raw)) {
            return $project_costings;
        }

        $row_count = max(count($items_raw), count($qtys_raw), count($unit_prices_raw));

        for ($i = 0; $i < $row_count; $i++) {
            $item = isset($items_raw[$i]) ? sanitize_text_field($items_raw[$i]) : '';
            $qty = isset($qtys_raw[$i]) ? max(0, (float) $qtys_raw[$i]) : 0;
            $unit_price = isset($unit_prices_raw[$i]) ? max(0, (float) $unit_prices_raw[$i]) : 0;

            if ($item === '' && $qty <= 0 && $unit_price <= 0) {
                continue;
            }

            $project_costings[] = [
                'item'       => $item,
                'qty'        => $qty,
                'unit_price' => $unit_price,
            ];
        }

        return $project_costings;
    }
}

if (!function_exists('shed_get_native_create_project_defaults')) {
    function shed_get_native_create_project_defaults() {
        $source_idea_id = shed_get_source_idea_id_from_request();
        $prefill = shed_get_source_idea_prefill_data($source_idea_id);
        $project_tasks = $source_idea_id ? shed_get_source_idea_project_tasks($source_idea_id) : [];

        if (empty($project_tasks)) {
            $project_tasks = [[
                'done'           => false,
                'task'           => '',
                'est_hours'      => 0,
                'volunteer_name' => '',
            ]];
        }

        return [
            'project_type'            => $prefill['project_type'] ?? 'project',
            'project_name'            => $prefill['project_name'] ?? '',
            'description'             => $prefill['description'] ?? '',
            'project_contact'         => $prefill['project_contact'] ?? '',
            'project_notes'           => '',
            'completion_target_date'  => '',
            'volunteer_status'        => 'seeking_volunteers',
            'project_stage'           => 'quote',
            'event_date'              => '',
            'event_location'          => '',
            'event_status'            => 'open',
            'idea_status'             => 'open',
            'training_video_url'      => '',
            'training_video_category' => '',
            'training_video_duration' => '',
            'training_video_status'   => 'active',
            'source_idea_id'          => $prefill['source_idea_id'] ?? 0,
            'project_tasks'           => $project_tasks,
            'project_costings'        => [[
                'item'       => '',
                'qty'        => '',
                'unit_price' => '',
            ]],
            'project_featured_crop_base64' => '',
        ];
    }
}

if (!function_exists('shed_normalize_project_create_submission')) {
    function shed_normalize_project_create_submission($raw) {
        $project_type = sanitize_key((string) ($raw['project_type'] ?? 'project'));

        if (!in_array($project_type, ['project', 'idea', 'event', 'video'], true)) {
            $project_type = 'project';
        }

        $event_status = sanitize_key((string) ($raw['event_status'] ?? 'open'));
        if (!in_array($event_status, ['open', 'ended'], true)) {
            $event_status = 'open';
        }

        $idea_status = sanitize_key((string) ($raw['idea_status'] ?? 'open'));
        if (!in_array($idea_status, ['open', 'ended'], true)) {
            $idea_status = 'open';
        }

        $video_status = sanitize_key((string) ($raw['training_video_status'] ?? 'active'));
        if (!in_array($video_status, ['active', 'archived'], true)) {
            $video_status = 'active';
        }

        $volunteer_status = sanitize_key((string) ($raw['volunteer_status'] ?? 'seeking_volunteers'));
        if (!in_array($volunteer_status, ['seeking_volunteers', 'volunteer_goal_achieved'], true)) {
            $volunteer_status = 'seeking_volunteers';
        }

        $project_stage = isset($raw['project_stage']) ? shed_normalize_project_stage($raw['project_stage']) : 'quote';

        return [
            'project_type'            => $project_type,
            'project_name'            => sanitize_text_field((string) ($raw['project_name'] ?? '')),
            'description'             => sanitize_textarea_field((string) ($raw['description'] ?? '')),
            'project_contact'         => sanitize_text_field((string) ($raw['project_contact'] ?? '')),
            'project_notes'           => sanitize_textarea_field((string) ($raw['project_notes'] ?? '')),
            'completion_target_date'  => shed_normalize_date_input($raw['completion_target_date'] ?? ''),
            'volunteer_status'        => $volunteer_status,
            'project_stage'           => $project_stage,
            'event_date'              => shed_normalize_date_input($raw['event_date'] ?? ''),
            'event_location'          => sanitize_text_field((string) ($raw['event_location'] ?? '')),
            'event_status'            => $event_status,
            'idea_status'             => $idea_status,
            'training_video_url'      => esc_url_raw((string) ($raw['training_video_url'] ?? '')),
            'training_video_category' => sanitize_text_field((string) ($raw['training_video_category'] ?? '')),
            'training_video_duration' => sanitize_text_field((string) ($raw['training_video_duration'] ?? '')),
            'training_video_status'   => $video_status,
            'source_idea_id'          => absint($raw['source_idea_id'] ?? 0),
            'project_tasks'           => shed_normalize_project_create_tasks($raw),
            'project_costings'        => shed_normalize_project_create_costings($raw),
            'project_featured_crop_base64' => isset($raw['project_featured_crop_base64']) ? wp_unslash((string) $raw['project_featured_crop_base64']) : '',
        ];
    }
}

if (!function_exists('shed_create_project_post_from_submission')) {
    function shed_create_project_post_from_submission($submission) {
        $source_idea_id = shed_get_source_idea_id_from_request();
        $project_type = $submission['project_type'];

        if ($source_idea_id) {
            $project_type = 'project';
        }

        if ($submission['project_name'] === '') {
            return new WP_Error('missing_title', 'Please enter a title.');
        }

        if ($project_type !== 'video' && $submission['project_contact'] === '') {
            return new WP_Error('missing_contact', 'Please enter the main contact.');
        }

        if ($project_type === 'video' && $submission['training_video_url'] === '' && !shed_has_selected_upload('training_video_file')) {
            return new WP_Error('missing_video_url', 'Please enter the video URL.');
        }

        $post_id = wp_insert_post([
            'post_type'    => 'project',
            'post_status'  => 'publish',
            'post_title'   => $submission['project_name'],
            'post_content' => $submission['description'],
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, 'project_type', $project_type);
        update_post_meta($post_id, 'project_contact', $submission['project_contact']);
        update_post_meta($post_id, 'project_notes', $submission['project_notes']);
        update_post_meta($post_id, 'timestamp', current_time('mysql'));

        if ($project_type === 'project') {
            $project_tasks = $submission['project_tasks'];

            update_post_meta($post_id, 'project_ref', shed_get_next_project_ref());
            update_post_meta($post_id, 'hours_required', shed_sum_project_task_hours($project_tasks));
            update_post_meta($post_id, 'hours_committed', 0);
            update_post_meta($post_id, 'volunteer_status', $submission['volunteer_status']);
            update_post_meta($post_id, 'project_stage', $submission['project_stage']);
            update_post_meta($post_id, 'completion_target_date', $submission['completion_target_date']);
            update_post_meta($post_id, 'project_tasks', $project_tasks);
            update_post_meta($post_id, 'project_costings', $submission['project_costings']);

            if ($source_idea_id) {
                update_post_meta($post_id, 'source_idea_id', $source_idea_id);
            }
        } elseif ($project_type === 'event') {
            update_post_meta($post_id, 'event_date', $submission['event_date']);
            update_post_meta($post_id, 'event_location', $submission['event_location']);
            update_post_meta($post_id, 'event_status', $submission['event_status']);
        } elseif ($project_type === 'idea') {
            update_post_meta($post_id, 'idea_status', $submission['idea_status']);
        } elseif ($project_type === 'video') {
            update_post_meta($post_id, 'training_video_url', $submission['training_video_url']);
            update_post_meta($post_id, 'training_video_category', $submission['training_video_category']);
            update_post_meta($post_id, 'training_video_duration', $submission['training_video_duration']);
            update_post_meta($post_id, 'training_video_status', $submission['training_video_status']);

            $video_attachment_id = shed_upload_training_video_attachment('training_video_file', $post_id);

            if (is_wp_error($video_attachment_id)) {
                return $video_attachment_id;
            }
        }

        $attachment_id = false;

        if ($submission['project_featured_crop_base64'] !== '' && function_exists('shed_save_cropped_featured_image')) {
            $attachment_id = shed_save_cropped_featured_image($submission['project_featured_crop_base64'], $post_id);
        }

        if (!$attachment_id) {
            $attachment_id = shed_import_project_featured_image_from_field('project_image', $post_id);
        }

        if (!$attachment_id && $source_idea_id) {
            shed_copy_featured_image_from_source_project($source_idea_id, $post_id);
        }

        if (in_array($project_type, ['project', 'idea'], true)) {
            $pdf_attachment_id = shed_upload_project_pdf_attachment('project_pdf', $post_id);

            if (!$pdf_attachment_id && $source_idea_id) {
                shed_copy_project_pdf_from_source_project($source_idea_id, $post_id);
            }
        }

        return $post_id;
    }
}

if (!function_exists('shed_handle_native_create_project_submission')) {
    function shed_handle_native_create_project_submission() {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_POST['shed_create_project_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_create_project_nonce']), 'shed_create_project')
        ) {
            return null;
        }

        $submission = shed_normalize_project_create_submission($_POST);
        $result = shed_create_project_post_from_submission($submission);

        return [
            'submission' => $submission,
            'result'     => $result,
        ];
    }
}

if (!function_exists('shed_enqueue_native_project_create_assets')) {
    function shed_enqueue_native_project_create_assets($force = false) {
        global $post;

        if (!$force && !is_a($post, 'WP_Post')) {
            return;
        }

        $content = is_a($post, 'WP_Post') ? (string) $post->post_content : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        $is_create_edit_page = (
            strpos($request_uri, '/home/members-area/create-project/') !== false ||
            strpos($request_uri, 'project_id=') !== false ||
            strpos($request_uri, 'mode=create') !== false ||
            strpos($request_uri, 'source_idea_id=') !== false
        );
        $has_legacy_create_form = (
            strpos($content, '[forminator_form id="683"]') !== false ||
            strpos($content, "[forminator_form id='683']") !== false ||
            (strpos($content, 'forminator_form') !== false && strpos($content, '683') !== false) ||
            (strpos($content, 'forminator/forms') !== false && strpos($content, '683') !== false)
        );

        if (
            !$force &&
            !has_shortcode($content, 'shed_create_project_form') &&
            !has_shortcode($content, 'shed_create_edit_project_form') &&
            !has_shortcode($content, 'shed_project_edit_selector') &&
            !has_shortcode($content, 'shed_edit_project_form') &&
            !$has_legacy_create_form &&
            !$is_create_edit_page
        ) {
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
    }
}

add_action('wp_enqueue_scripts', 'shed_enqueue_native_project_create_assets');

if (!function_exists('shed_render_native_create_project_form')) {
    function shed_render_native_create_project_form() {
        shed_enqueue_native_project_create_assets(true);

        $handled = shed_handle_native_create_project_submission();
        $values = shed_get_native_create_project_defaults();
        $message = '';
        $message_type = '';

        if ($handled) {
            $values = array_merge($values, $handled['submission']);

            if (is_wp_error($handled['result'])) {
                $message = $handled['result']->get_error_message();
                $message_type = 'error';
            } else {
                $message = 'Record created successfully.';
                $message_type = 'success';
                $values = shed_get_native_create_project_defaults();
            }
        }

        static $instance = 0;
        $instance++;
        $form_id = 'shed-native-create-project-' . $instance;
        $project_tasks = isset($values['project_tasks']) && is_array($values['project_tasks']) && !empty($values['project_tasks'])
            ? $values['project_tasks']
            : [[
                'done'           => false,
                'task'           => '',
                'est_hours'      => 0,
                'volunteer_name' => '',
            ]];
        $project_costings = isset($values['project_costings']) && is_array($values['project_costings']) && !empty($values['project_costings'])
            ? $values['project_costings']
            : [[
                'item'       => '',
                'qty'        => '',
                'unit_price' => '',
            ]];

        ob_start();
        ?>
        <div class="shed-create-project-wrap">
            <?php if ($message !== '') : ?>
                <div class="shed-create-project-message shed-create-project-message-<?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <form id="<?php echo esc_attr($form_id); ?>" class="shed-native-create-project-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('shed_create_project', 'shed_create_project_nonce'); ?>
                <input type="hidden" name="source_idea_id" value="<?php echo esc_attr((string) $values['source_idea_id']); ?>">
                <input type="hidden" name="project_featured_crop_base64" value="">

                <div class="shed-form-field">
                    <label><strong>Record type</strong></label>
                    <div class="shed-radio-group">
                        <label><input type="radio" name="project_type" value="project" <?php checked($values['project_type'], 'project'); ?>> Project</label>
                        <label><input type="radio" name="project_type" value="idea" <?php checked($values['project_type'], 'idea'); ?>> Idea</label>
                        <label><input type="radio" name="project_type" value="event" <?php checked($values['project_type'], 'event'); ?>> Event</label>
                        <label><input type="radio" name="project_type" value="video" <?php checked($values['project_type'], 'video'); ?>> Training video</label>
                    </div>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-title"><strong>Title</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-title" type="text" name="project_name" value="<?php echo esc_attr($values['project_name']); ?>" required>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-description"><strong>Description</strong></label>
                    <textarea id="<?php echo esc_attr($form_id); ?>-description" name="description" rows="6"><?php echo esc_textarea($values['description']); ?></textarea>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-contact"><strong>Main contact</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-contact" type="text" name="project_contact" value="<?php echo esc_attr($values['project_contact']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-target-date"><strong>Completion target date</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-target-date" type="date" name="completion_target_date" value="<?php echo esc_attr($values['completion_target_date']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-volunteer-status"><strong>Volunteer status</strong></label>
                    <select id="<?php echo esc_attr($form_id); ?>-volunteer-status" name="volunteer_status">
                        <option value="seeking_volunteers" <?php selected($values['volunteer_status'], 'seeking_volunteers'); ?>>Seeking volunteers</option>
                        <option value="volunteer_goal_achieved" <?php selected($values['volunteer_status'], 'volunteer_goal_achieved'); ?>>Volunteer goal achieved</option>
                    </select>
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-project-stage"><strong>Project lifecycle</strong></label>
                    <select id="<?php echo esc_attr($form_id); ?>-project-stage" name="project_stage">
                        <option value="quote" <?php selected($values['project_stage'], 'quote'); ?>>Quote</option>
                        <option value="making" <?php selected($values['project_stage'], 'making'); ?>>Making</option>
                        <option value="invoicing" <?php selected($values['project_stage'], 'invoicing'); ?>>Invoicing</option>
                        <option value="complete" <?php selected($values['project_stage'], 'complete'); ?>>Complete</option>
                    </select>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-image"><strong>Upload image</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-image" type="file" name="project_image" accept="image/*">
                </div>

                <div class="shed-form-field" data-project-type-group="event">
                    <label for="<?php echo esc_attr($form_id); ?>-event-date"><strong>Event date</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-event-date" type="date" name="event_date" value="<?php echo esc_attr($values['event_date']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="event">
                    <label for="<?php echo esc_attr($form_id); ?>-event-location"><strong>Event location</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-event-location" type="text" name="event_location" value="<?php echo esc_attr($values['event_location']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="event">
                    <label for="<?php echo esc_attr($form_id); ?>-event-status"><strong>Event status</strong></label>
                    <select id="<?php echo esc_attr($form_id); ?>-event-status" name="event_status">
                        <option value="open" <?php selected($values['event_status'], 'open'); ?>>Open</option>
                        <option value="ended" <?php selected($values['event_status'], 'ended'); ?>>Ended</option>
                    </select>
                </div>

                <div class="shed-form-field" data-project-type-group="idea">
                    <label for="<?php echo esc_attr($form_id); ?>-idea-status"><strong>Idea status</strong></label>
                    <select id="<?php echo esc_attr($form_id); ?>-idea-status" name="idea_status">
                        <option value="open" <?php selected($values['idea_status'], 'open'); ?>>Open</option>
                        <option value="ended" <?php selected($values['idea_status'], 'ended'); ?>>Ended</option>
                    </select>
                </div>

                <div class="shed-form-field" data-project-type-group="idea">
                    <label for="<?php echo esc_attr($form_id); ?>-idea-pdf"><strong>Supporting PDF</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-idea-pdf" type="file" name="project_pdf" accept="application/pdf,.pdf">
                </div>

                <div class="shed-form-field" data-project-type-group="video">
                    <label for="<?php echo esc_attr($form_id); ?>-video-url"><strong>Video URL</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-video-url" type="url" name="training_video_url" value="<?php echo esc_attr($values['training_video_url']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="video">
                    <label for="<?php echo esc_attr($form_id); ?>-video-file"><strong>Upload video</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-video-file" type="file" name="training_video_file" accept="video/*">
                </div>

                <div class="shed-form-field" data-project-type-group="video">
                    <label for="<?php echo esc_attr($form_id); ?>-video-category"><strong>Category</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-video-category" type="text" name="training_video_category" value="<?php echo esc_attr($values['training_video_category']); ?>" placeholder="Machine safety">
                </div>

                <div class="shed-form-field" data-project-type-group="video">
                    <label for="<?php echo esc_attr($form_id); ?>-video-duration"><strong>Duration</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-video-duration" type="text" name="training_video_duration" value="<?php echo esc_attr($values['training_video_duration']); ?>" placeholder="4 min">
                </div>

                <div class="shed-form-field" data-project-type-group="video">
                    <label for="<?php echo esc_attr($form_id); ?>-video-status"><strong>Video status</strong></label>
                    <select id="<?php echo esc_attr($form_id); ?>-video-status" name="training_video_status">
                        <option value="active" <?php selected($values['training_video_status'], 'active'); ?>>Active</option>
                        <option value="archived" <?php selected($values['training_video_status'], 'archived'); ?>>Archived</option>
                    </select>
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-project-pdf"><strong>Supporting PDF</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-project-pdf" type="file" name="project_pdf" accept="application/pdf,.pdf">
                </div>

                <div data-project-type-group="project">
                    <hr>

                    <h3>Tasks</h3>
                    <div style="overflow-x:auto;">
                        <table id="<?php echo esc_attr($form_id); ?>-tasks-table" class="shed-create-tasks-table" style="width:100%; border-collapse: collapse; margin-bottom: 14px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:70px;">Move</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:80px;">Done</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:45%;">Task</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:130px;">Est hours</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:220px;">Volunteer name</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:90px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="<?php echo esc_attr($form_id); ?>-tasks-body">
                                <?php foreach ($project_tasks as $row) : ?>
                                    <?php
                                    $task_done = !empty($row['done']);
                                    $task_name = isset($row['task']) ? $row['task'] : '';
                                    $task_est_hours = isset($row['est_hours']) ? $row['est_hours'] : 0;
                                    $task_volunteer_name = isset($row['volunteer_name']) ? $row['volunteer_name'] : '';
                                    $task_row_status_class = $task_done ? 'shed-task-row--done' : (trim((string) $task_volunteer_name) === '' ? 'shed-task-row--unassigned' : 'shed-task-row--assigned');
                                    ?>
                                    <tr class="shed-task-row <?php echo esc_attr($task_row_status_class); ?>" draggable="true">
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-task-drag-handle" aria-label="Move task">Move</button></td>
                                        <td class="shed-task-done-cell" style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="hidden" name="task_done[]" value="<?php echo $task_done ? '1' : '0'; ?>">
                                            <input type="checkbox" class="shed-task-done-checkbox" <?php checked($task_done); ?>>
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="task_name[]" value="<?php echo esc_attr($task_name); ?>"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" min="0" max="99" step="1" name="task_est_hours[]" value="<?php echo esc_attr((string) $task_est_hours); ?>"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="task_volunteer_name[]" maxlength="15" value="<?php echo esc_attr($task_volunteer_name); ?>"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-remove-task-row">Remove</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p><button type="button" id="<?php echo esc_attr($form_id); ?>-add-task-row">Add row</button></p>

                    <template id="<?php echo esc_attr($form_id); ?>-task-row-template">
                        <tr class="shed-task-row shed-task-row--unassigned" draggable="true">
                            <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-task-drag-handle" aria-label="Move task">Move</button></td>
                            <td class="shed-task-done-cell" style="padding:8px; border-bottom:1px solid #eee;"><input type="hidden" name="task_done[]" value="0"><input type="checkbox" class="shed-task-done-checkbox"></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="task_name[]" value=""></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" min="0" max="99" step="1" name="task_est_hours[]" value="0"></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="task_volunteer_name[]" maxlength="15" value=""></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-remove-task-row">Remove</button></td>
                        </tr>
                    </template>

                    <hr>

                    <h3>Project costing</h3>
                    <div style="overflow-x:auto;">
                        <table id="<?php echo esc_attr($form_id); ?>-costings-table" style="width:100%; border-collapse: collapse; margin-bottom: 14px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px;">Item</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:110px;">Qty</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:140px;">Unit price (&pound;)</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:140px;">Total (&pound;)</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:90px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="<?php echo esc_attr($form_id); ?>-costings-body">
                                <?php foreach ($project_costings as $row) : ?>
                                    <?php
                                    $item = isset($row['item']) ? $row['item'] : '';
                                    $qty = isset($row['qty']) ? $row['qty'] : '';
                                    $unit_price = isset($row['unit_price']) ? $row['unit_price'] : '';
                                    ?>
                                    <tr class="shed-costing-row">
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="costing_item[]" value="<?php echo esc_attr($item); ?>"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" step="0.01" min="0" name="costing_qty[]" value="<?php echo esc_attr((string) $qty); ?>" class="shed-costing-qty"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" step="0.01" min="0" name="costing_unit_price[]" value="<?php echo esc_attr((string) $unit_price); ?>" class="shed-costing-unit-price"></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" value="" class="shed-costing-line-total" readonly></td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-remove-costing-row">Remove</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="padding:12px 8px; text-align:right; font-weight:700;">Grand total (&pound;)</td>
                                    <td style="padding:12px 8px;"><input type="text" id="<?php echo esc_attr($form_id); ?>-costings-grand-total" readonly></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p><button type="button" id="<?php echo esc_attr($form_id); ?>-add-costing-row">Add row</button></p>

                    <template id="<?php echo esc_attr($form_id); ?>-costing-row-template">
                        <tr class="shed-costing-row">
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" name="costing_item[]" value=""></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" step="0.01" min="0" name="costing_qty[]" value="" class="shed-costing-qty"></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="number" step="0.01" min="0" name="costing_unit_price[]" value="" class="shed-costing-unit-price"></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><input type="text" value="" class="shed-costing-line-total" readonly></td>
                            <td style="padding:8px; border-bottom:1px solid #eee;"><button type="button" class="shed-remove-costing-row">Remove</button></td>
                        </tr>
                    </template>

                    <div class="shed-form-field" style="margin-top: 24px;">
                        <label for="<?php echo esc_attr($form_id); ?>-project-notes"><strong>Notes</strong></label>
                        <textarea id="<?php echo esc_attr($form_id); ?>-project-notes" name="project_notes" rows="6"><?php echo esc_textarea($values['project_notes']); ?></textarea>
                    </div>
                </div>

                <div class="shed-form-actions">
                    <button type="submit">Create record</button>
                </div>
            </form>
        </div>

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

        <style>
            .shed-create-project-wrap { max-width: 920px; }
            .shed-create-project-message { margin-bottom: 16px; padding: 12px 14px; border-radius: 8px; }
            .shed-create-project-message-success { background: #edf8ed; color: #136a13; border: 1px solid #9fce9f; }
            .shed-create-project-message-error { background: #fff1f1; color: #8a1f1f; border: 1px solid #e1a3a3; }
            .shed-native-create-project-form .shed-form-field { margin-bottom: 18px; }
            .shed-native-create-project-form label { display: block; margin-bottom: 6px; }
            .shed-native-create-project-form input[type="text"],
            .shed-native-create-project-form input[type="url"],
            .shed-native-create-project-form input[type="number"],
            .shed-native-create-project-form input[type="date"],
            .shed-native-create-project-form textarea,
            .shed-native-create-project-form select { width: 100%; padding: 10px 12px; box-sizing: border-box; }
            .shed-radio-group { display: flex; gap: 20px; flex-wrap: wrap; }
            .shed-radio-group label { display: inline-flex; align-items: center; gap: 8px; }
            .shed-form-actions button { padding: 12px 18px; border: 0; border-radius: 8px; background: #0a7f00; color: #fff; cursor: pointer; }
            .shed-create-tasks-table .shed-task-row--unassigned td { background-color: #fff2cc; }
            .shed-create-tasks-table .shed-task-row--assigned td { background-color: #f3f4f6; }
            .shed-create-tasks-table .shed-task-row--done td { background-color: #d9ead3; }
            .shed-create-tasks-table .shed-task-row.is-dragging { opacity: 0.55; }
            .shed-native-create-project-form .shed-task-done-cell { text-align: center; vertical-align: middle; }
            .shed-native-create-project-form .shed-task-done-checkbox { accent-color: #2e7d32; height: 24px; width: 24px; }
            .shed-native-create-project-form .shed-task-drag-handle { cursor: grab; padding: 8px 10px; }
            .shed-native-create-project-form .shed-task-row input,
            .shed-native-create-project-form .shed-costing-row input { width: 100%; padding: 8px; box-sizing: border-box; }
            .shed-native-create-project-form .shed-task-row input { background-color: transparent; }
            .shed-native-create-project-form .shed-costing-line-total,
            .shed-native-create-project-form [id$="-costings-grand-total"] { background: #f7f7f7; border: 1px solid #ddd; }
        </style>

        <?php if (function_exists('shed_render_cropper_assets')) { shed_render_cropper_assets(); } ?>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById(<?php echo wp_json_encode($form_id); ?>);
            if (!form) {
                return;
            }

            var imageInput = form.querySelector('input[name="project_image"]');
            var hiddenCrop = form.querySelector('input[name="project_featured_crop_base64"]');
            var typeInputs = form.querySelectorAll('input[name="project_type"]');
            var modal = document.getElementById('shed-cropper-modal');
            var cropperImage = document.getElementById('shed-cropper-image');
            var applyBtn = document.getElementById('shed-cropper-apply');
            var cancelBtn = document.getElementById('shed-cropper-cancel');
            var tasksBody = document.getElementById(<?php echo wp_json_encode($form_id . '-tasks-body'); ?>);
            var taskTemplate = document.getElementById(<?php echo wp_json_encode($form_id . '-task-row-template'); ?>);
            var addTaskBtn = document.getElementById(<?php echo wp_json_encode($form_id . '-add-task-row'); ?>);
            var costingsBody = document.getElementById(<?php echo wp_json_encode($form_id . '-costings-body'); ?>);
            var costingTemplate = document.getElementById(<?php echo wp_json_encode($form_id . '-costing-row-template'); ?>);
            var addCostingBtn = document.getElementById(<?php echo wp_json_encode($form_id . '-add-costing-row'); ?>);
            var costingsGrandTotal = document.getElementById(<?php echo wp_json_encode($form_id . '-costings-grand-total'); ?>);
            var cropper = null;
            var objectUrl = null;
            var cropConfirmed = false;
            var draggedTaskRow = null;

            function openModal() {
                if (!modal) return;
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                if (!modal) return;
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }

            function destroyCropper() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
            }

            function getProjectType() {
                var checked = form.querySelector('input[name="project_type"]:checked');
                return checked ? checked.value : 'project';
            }

            function updateTypeVisibility() {
                var projectType = getProjectType();

                form.querySelectorAll('[data-project-type-group]').forEach(function (field) {
                    var visible = field.getAttribute('data-project-type-group') === projectType;
                    field.style.display = visible ? '' : 'none';
                    field.querySelectorAll('input, select, textarea').forEach(function (input) {
                        input.disabled = !visible;
                    });
                });
            }

            updateTypeVisibility();
            typeInputs.forEach(function (input) {
                input.addEventListener('change', updateTypeVisibility);
            });

            function updateTaskRowStatus(row) {
                var doneCheckbox = row.querySelector('.shed-task-done-checkbox');
                var volunteerField = row.querySelector('input[name="task_volunteer_name[]"]');
                var isDone = doneCheckbox ? doneCheckbox.checked : false;
                var hasVolunteer = volunteerField ? volunteerField.value.trim() !== '' : false;

                row.classList.remove('shed-task-row--unassigned', 'shed-task-row--assigned', 'shed-task-row--done');

                if (isDone) {
                    row.classList.add('shed-task-row--done');
                } else if (hasVolunteer) {
                    row.classList.add('shed-task-row--assigned');
                } else {
                    row.classList.add('shed-task-row--unassigned');
                }
            }

            function bindTaskRow(row) {
                var doneCheckbox = row.querySelector('.shed-task-done-checkbox');
                var doneField = doneCheckbox ? doneCheckbox.previousElementSibling : null;
                var volunteerField = row.querySelector('input[name="task_volunteer_name[]"]');
                var removeBtn = row.querySelector('.shed-remove-task-row');
                row.draggable = true;

                row.addEventListener('dragstart', function (event) {
                    draggedTaskRow = row;
                    row.classList.add('is-dragging');
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', '');
                    }
                });

                row.addEventListener('dragend', function () {
                    row.classList.remove('is-dragging');
                    draggedTaskRow = null;
                });

                row.addEventListener('dragover', function (event) {
                    if (!draggedTaskRow || draggedTaskRow === row || !tasksBody) {
                        return;
                    }

                    event.preventDefault();

                    var rect = row.getBoundingClientRect();
                    var insertAfter = event.clientY > rect.top + (rect.height / 2);
                    tasksBody.insertBefore(draggedTaskRow, insertAfter ? row.nextSibling : row);
                });

                if (doneCheckbox && doneField) {
                    doneCheckbox.addEventListener('change', function () {
                        doneField.value = doneCheckbox.checked ? '1' : '0';
                        updateTaskRowStatus(row);
                    });
                }

                if (volunteerField) {
                    volunteerField.addEventListener('input', function () {
                        updateTaskRowStatus(row);
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        if (tasksBody && tasksBody.querySelectorAll('.shed-task-row').length > 1) {
                            row.remove();
                        } else {
                            row.querySelectorAll('input').forEach(function (input) {
                                if (input.type === 'checkbox') {
                                    input.checked = false;
                                } else if (input.type === 'hidden' && input.name === 'task_done[]') {
                                    input.value = '0';
                                } else {
                                    input.value = input.name === 'task_est_hours[]' ? '0' : '';
                                }
                            });
                            updateTaskRowStatus(row);
                        }
                    });
                }
            }

            function recalcCostings() {
                var grandTotal = 0;

                if (!costingsBody) {
                    return;
                }

                costingsBody.querySelectorAll('.shed-costing-row').forEach(function (row) {
                    var qty = parseFloat((row.querySelector('.shed-costing-qty') || {}).value || '0') || 0;
                    var unitPrice = parseFloat((row.querySelector('.shed-costing-unit-price') || {}).value || '0') || 0;
                    var lineTotal = qty * unitPrice;
                    var lineTotalField = row.querySelector('.shed-costing-line-total');

                    grandTotal += lineTotal;

                    if (lineTotalField) {
                        lineTotalField.value = lineTotal ? lineTotal.toFixed(2) : '';
                    }
                });

                if (costingsGrandTotal) {
                    costingsGrandTotal.value = grandTotal ? grandTotal.toFixed(2) : '';
                }
            }

            function bindCostingRow(row) {
                row.querySelectorAll('.shed-costing-qty, .shed-costing-unit-price').forEach(function (input) {
                    input.addEventListener('input', recalcCostings);
                });

                var removeBtn = row.querySelector('.shed-remove-costing-row');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        if (costingsBody && costingsBody.querySelectorAll('.shed-costing-row').length > 1) {
                            row.remove();
                        } else {
                            row.querySelectorAll('input').forEach(function (input) {
                                input.value = '';
                            });
                        }
                        recalcCostings();
                    });
                }
            }

            if (tasksBody) {
                tasksBody.querySelectorAll('.shed-task-row').forEach(bindTaskRow);
            }

            if (addTaskBtn && tasksBody && taskTemplate) {
                addTaskBtn.addEventListener('click', function () {
                    var clone = taskTemplate.content.cloneNode(true);
                    tasksBody.appendChild(clone);
                    var rows = tasksBody.querySelectorAll('.shed-task-row');
                    bindTaskRow(rows[rows.length - 1]);
                });
            }

            if (costingsBody) {
                costingsBody.querySelectorAll('.shed-costing-row').forEach(bindCostingRow);
                recalcCostings();
            }

            if (addCostingBtn && costingsBody && costingTemplate) {
                addCostingBtn.addEventListener('click', function () {
                    var clone = costingTemplate.content.cloneNode(true);
                    costingsBody.appendChild(clone);
                    var rows = costingsBody.querySelectorAll('.shed-costing-row');
                    bindCostingRow(rows[rows.length - 1]);
                    recalcCostings();
                });
            }

            if (!imageInput || !hiddenCrop || typeof Cropper === 'undefined') {
                return;
            }

            imageInput.addEventListener('change', function (e) {
                cropConfirmed = false;
                hiddenCrop.value = '';

                var file = e.target.files && e.target.files[0];
                if (!file) {
                    return;
                }

                if (!file.type.match(/^image\//)) {
                    alert('Please choose an image file.');
                    imageInput.value = '';
                    return;
                }

                destroyCropper();
                objectUrl = URL.createObjectURL(file);
                cropperImage.src = objectUrl;
                openModal();

                cropperImage.onload = function () {
                    if (cropper) {
                        cropper.destroy();
                    }

                    cropper = new Cropper(cropperImage, {
                        aspectRatio: 16 / 9,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        responsive: true,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: false,
                        toggleDragModeOnDblclick: false,
                        background: false
                    });
                };
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    if (!cropper) {
                        return;
                    }

                    var canvas = cropper.getCroppedCanvas({
                        width: 1600,
                        height: 900,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });

                    if (!canvas) {
                        alert('Could not crop the image.');
                        return;
                    }

                    hiddenCrop.value = canvas.toDataURL('image/jpeg', 0.9);
                    cropConfirmed = true;
                    closeModal();
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    cropConfirmed = false;
                    hiddenCrop.value = '';
                    imageInput.value = '';
                    destroyCropper();
                    closeModal();
                });
            }

            form.addEventListener('submit', function (e) {
                if (imageInput.files.length > 0 && !cropConfirmed) {
                    e.preventDefault();
                    alert('Please crop the featured image before submitting.');
                    openModal();
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('shed_replace_legacy_project_form_shortcode')) {
    function shed_replace_legacy_project_form_shortcode($content) {
        if (is_admin() || strpos($content, 'forminator_form') === false) {
            return $content;
        }

        return preg_replace(
            '/\[forminator_form\s+id=(["\'])683\1[^\]]*\]/i',
            '[shed_create_project_form]',
            $content
        );
    }
}

add_filter('the_content', 'shed_replace_legacy_project_form_shortcode', 5);

add_shortcode('shed_create_project_form', 'shed_render_native_create_project_form');
