<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_project_type_label')) {
    function shed_get_project_type_label($project_type) {
        $labels = [
            'project' => 'Project',
            'idea'    => 'Idea',
            'event'   => 'Event',
            'video'   => 'Training video',
        ];

        return $labels[$project_type] ?? 'Project';
    }
}

if (!function_exists('shed_get_editable_projects')) {
    function shed_get_editable_projects() {
        $projects = get_posts([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $editable = [];

        foreach ($projects as $project) {
            $editable[] = $project;
        }

        return $editable;
    }
}

if (!function_exists('shed_project_edit_selector_shortcode')) {
    function shed_project_edit_selector_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to edit projects.</p>';
        }

        $projects = shed_get_editable_projects();

        if (empty($projects)) {
            return '<p>No active projects available to edit.</p>';
        }

        $edit_form_page_url = site_url('/home/members-area/create-project/');
        $stage_labels = shed_get_stage_labels();

        ob_start();
        ?>
        <form id="shed-project-edit-selector-form" style="max-width: 560px;">
            <p>
                <label for="shed_project_id"><strong>Select project to edit</strong></label><br>
                <select id="shed_project_id" name="project_id" style="width:100%; padding:8px;">
                    <option value="">Select a project...</option>
                    <?php foreach ($projects as $project) : ?>
                        <?php
                        $project_type  = shed_get_project_type($project->ID);
                        $project_ref   = get_post_meta($project->ID, 'project_ref', true);
                        $project_stage = get_post_meta($project->ID, 'project_stage', true);
                        $project_label = $project->post_title;

                        if ($project_type === 'project' && $project_ref !== '') {
                            $project_label = $project_ref . ' - ' . $project_label;
                        }

                        if ($project_type === 'project') {
                            if ($project_stage === '') {
                                $project_stage = 'quote';
                            }

                            $project_label .= ' (' . ($stage_labels[$project_stage] ?? ucfirst(str_replace('_', ' ', $project_stage))) . ')';
                        } else {
                            $project_label .= ' (' . shed_get_project_type_label($project_type) . ')';
                        }
                        ?>
                        <option value="<?php echo esc_attr($project->ID); ?>">
                            <?php echo esc_html($project_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <button type="submit">Edit Project</button>
            </p>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('shed-project-edit-selector-form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var select = document.getElementById('shed_project_id');
                if (!select || !select.value) {
                    alert('Please select a project.');
                    return;
                }

                var baseUrl = <?php echo wp_json_encode($edit_form_page_url); ?>;
                var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
                window.location.href = baseUrl + separator + 'project_id=' + encodeURIComponent(select.value);
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_project_edit_selector', 'shed_project_edit_selector_shortcode');

if (!function_exists('shed_get_create_edit_project_page_url')) {
    function shed_get_create_edit_project_page_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
        return remove_query_arg(['mode', 'project_id', 'source_idea_id'], home_url($request_uri));
    }
}

if (!function_exists('shed_create_edit_project_form_shortcode')) {
    function shed_create_edit_project_form_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to create or edit projects.</p>';
        }

        $project_id = isset($_GET['project_id']) ? absint(wp_unslash($_GET['project_id'])) : 0;
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';
        $source_idea_id = isset($_GET['source_idea_id']) ? absint(wp_unslash($_GET['source_idea_id'])) : 0;
        $is_create_submission = isset($_POST['shed_create_project_nonce']);

        if ($project_id) {
            return '<h2>Create / edit content</h2>' . shed_edit_project_form_shortcode();
        }

        if ($mode === 'create' || $source_idea_id || $is_create_submission) {
            return '<h2>Create / edit content</h2>' . shed_render_native_create_project_form();
        }

        $projects = shed_get_editable_projects();
        $stage_labels = shed_get_stage_labels();
        $base_url = shed_get_create_edit_project_page_url();
        $create_url = add_query_arg('mode', 'create', $base_url);

        ob_start();
        ?>
        <div class="shed-create-edit-project-wrap" style="max-width: 720px;">
            <h2>Create / edit content</h2>

            <p>
                <a href="<?php echo esc_url($create_url); ?>" class="button" style="display:inline-block; padding:10px 16px; text-decoration:none;">
                    Create new record
                </a>
            </p>

            <?php if (!empty($projects)) : ?>
                <form id="shed-create-edit-project-selector-form">
                    <p>
                        <label for="shed_create_edit_project_id"><strong>Edit an existing project, idea, event, or video</strong></label><br>
                        <select id="shed_create_edit_project_id" name="project_id" style="width:100%; padding:8px;">
                            <option value="">Select a record...</option>
                            <?php foreach ($projects as $project) : ?>
                                <?php
                                $project_type = shed_get_project_type($project->ID);
                                $project_ref = get_post_meta($project->ID, 'project_ref', true);
                                $project_stage = get_post_meta($project->ID, 'project_stage', true);
                                $project_label = $project->post_title;

                                if ($project_type === 'project' && $project_ref !== '') {
                                    $project_label = $project_ref . ' - ' . $project_label;
                                }

                                if ($project_type === 'project') {
                                    if ($project_stage === '') {
                                        $project_stage = 'quote';
                                    }

                                    $project_label .= ' (' . ($stage_labels[$project_stage] ?? ucfirst(str_replace('_', ' ', $project_stage))) . ')';
                                } else {
                                    $project_label .= ' (' . shed_get_project_type_label($project_type) . ')';
                                }
                                ?>
                                <option value="<?php echo esc_attr((string) $project->ID); ?>">
                                    <?php echo esc_html($project_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <button type="submit">Edit selected record</button>
                    </p>
                </form>
            <?php else : ?>
                <p>No existing records available to edit.</p>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('shed-create-edit-project-selector-form');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var select = document.getElementById('shed_create_edit_project_id');
                if (!select || !select.value) {
                    alert('Please select a record.');
                    return;
                }

                var url = new URL(<?php echo wp_json_encode($base_url); ?>, window.location.origin);
                url.searchParams.set('project_id', select.value);
                window.location.href = url.toString();
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_create_edit_project_form', 'shed_create_edit_project_form_shortcode');
add_shortcode('shed_create_project_form', 'shed_create_edit_project_form_shortcode');
add_shortcode('shed_project_edit_selector', 'shed_create_edit_project_form_shortcode');

if (!function_exists('shed_edit_project_form_shortcode')) {
    function shed_edit_project_form_shortcode() {
        if (function_exists('shed_enqueue_native_project_create_assets')) {
            shed_enqueue_native_project_create_assets(true);
        }

        if (!is_user_logged_in()) {
            return '<p>Please log in to edit projects.</p>';
        }

        $project_id = isset($_GET['project_id']) ? absint(wp_unslash($_GET['project_id'])) : 0;

        if (!$project_id) {
            return shed_create_edit_project_form_shortcode();
        }

        $post = get_post($project_id);

        if (!$post || $post->post_type !== 'project') {
            return '<p>Project not found.</p>';
        }

        if (!current_user_can('edit_post', $project_id)) {
            return '<p>You do not have permission to edit this project.</p>';
        }

        $message = '';

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['shed_edit_project_nonce']) &&
            wp_verify_nonce(wp_unslash($_POST['shed_edit_project_nonce']), 'shed_edit_project_' . $project_id)
        ) {
            $project_name      = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
            $description       = isset($_POST['project_description']) ? sanitize_textarea_field(wp_unslash($_POST['project_description'])) : '';
            $project_type      = isset($_POST['project_type']) ? sanitize_key(wp_unslash($_POST['project_type'])) : 'project';
            $project_contact   = isset($_POST['project_contact']) ? sanitize_text_field(wp_unslash($_POST['project_contact'])) : '';
            $project_notes     = isset($_POST['project_notes']) ? sanitize_textarea_field(wp_unslash($_POST['project_notes'])) : '';
            $target_date       = isset($_POST['completion_target_date']) ? shed_normalize_date_input(wp_unslash($_POST['completion_target_date'])) : '';
            $volunteer_status  = isset($_POST['volunteer_status']) ? sanitize_text_field(wp_unslash($_POST['volunteer_status'])) : 'seeking_volunteers';
            $project_stage     = isset($_POST['project_stage']) ? shed_normalize_project_stage(wp_unslash($_POST['project_stage'])) : 'quote';
            $idea_status       = isset($_POST['idea_status']) ? sanitize_key(wp_unslash($_POST['idea_status'])) : 'open';
            $event_date        = isset($_POST['event_date']) ? shed_normalize_date_input(wp_unslash($_POST['event_date'])) : '';
            $event_location    = isset($_POST['event_location']) ? sanitize_text_field(wp_unslash($_POST['event_location'])) : '';
            $event_status      = isset($_POST['event_status']) ? sanitize_key(wp_unslash($_POST['event_status'])) : 'open';
            $video_url         = isset($_POST['training_video_url']) ? esc_url_raw(wp_unslash($_POST['training_video_url'])) : '';
            $video_category    = isset($_POST['training_video_category']) ? sanitize_text_field(wp_unslash($_POST['training_video_category'])) : '';
            $video_duration    = isset($_POST['training_video_duration']) ? sanitize_text_field(wp_unslash($_POST['training_video_duration'])) : '';
            $video_status      = isset($_POST['training_video_status']) ? sanitize_key(wp_unslash($_POST['training_video_status'])) : 'active';

            if (!in_array($project_type, ['project', 'idea', 'event', 'video'], true)) {
                $project_type = 'project';
            }

            $allowed_volunteer_statuses = [
                'seeking_volunteers',
                'volunteer_goal_achieved',
            ];

            $allowed_stages = [
                'quote',
                'making',
                'invoicing',
                'complete',
            ];

            if (!in_array($volunteer_status, $allowed_volunteer_statuses, true)) {
                $volunteer_status = 'seeking_volunteers';
            }

            if (!in_array($project_stage, $allowed_stages, true)) {
                $project_stage = 'quote';
            }

            if (!in_array($idea_status, ['open', 'ended'], true)) {
                $idea_status = 'open';
            }

            if (!in_array($event_status, ['open', 'ended'], true)) {
                $event_status = 'open';
            }

            if (!in_array($video_status, ['active', 'archived'], true)) {
                $video_status = 'active';
            }

            if ($project_name === '') {
                $message = '<p style="color:red;"><strong>Please complete the required fields.</strong></p>';
            } elseif ($project_type === 'video' && $video_url === '' && !shed_has_selected_upload('training_video_file')) {
                $message = '<p style="color:red;"><strong>Please add a video URL or upload a video file.</strong></p>';
            } else {
                $result = wp_update_post([
                    'ID'           => $project_id,
                    'post_title'   => $project_name,
                    'post_content' => $description,
                ], true);

                if (is_wp_error($result)) {
                    $message = '<p style="color:red;"><strong>Update failed:</strong> ' . esc_html($result->get_error_message()) . '</p>';
                } else {
                    update_post_meta($project_id, 'project_type', $project_type);
                    update_post_meta($project_id, 'project_contact', $project_contact);
                    update_post_meta($project_id, 'project_notes', $project_notes);

                    if ($project_type === 'project') {
                        update_post_meta($project_id, 'volunteer_status', $volunteer_status);
                        update_post_meta($project_id, 'project_stage', $project_stage);
                        update_post_meta($project_id, 'completion_target_date', $target_date);

                        $costing_items_raw       = isset($_POST['costing_item']) ? wp_unslash($_POST['costing_item']) : [];
                        $costing_qtys_raw        = isset($_POST['costing_qty']) ? wp_unslash($_POST['costing_qty']) : [];
                        $costing_unit_prices_raw = isset($_POST['costing_unit_price']) ? wp_unslash($_POST['costing_unit_price']) : [];

                        $project_costings = [];

                        if (is_array($costing_items_raw) && is_array($costing_qtys_raw) && is_array($costing_unit_prices_raw)) {
                            $row_count = max(count($costing_items_raw), count($costing_qtys_raw), count($costing_unit_prices_raw));

                            for ($i = 0; $i < $row_count; $i++) {
                                $item = isset($costing_items_raw[$i]) ? sanitize_text_field($costing_items_raw[$i]) : '';
                                $qty_raw = isset($costing_qtys_raw[$i]) ? trim((string) $costing_qtys_raw[$i]) : '';
                                $unit_price_raw = isset($costing_unit_prices_raw[$i]) ? trim((string) $costing_unit_prices_raw[$i]) : '';

                                $qty_raw = str_replace(',', '.', $qty_raw);
                                $unit_price_raw = str_replace(',', '.', $unit_price_raw);

                                $qty = is_numeric($qty_raw) ? (float) $qty_raw : 0;
                                $unit_price = is_numeric($unit_price_raw) ? (float) $unit_price_raw : 0;

                                if ($item === '' && $qty <= 0 && $unit_price <= 0) {
                                    continue;
                                }

                                $project_costings[] = [
                                    'item'       => $item,
                                    'qty'        => $qty,
                                    'unit_price' => $unit_price,
                                ];
                            }
                        }

                        update_post_meta($project_id, 'project_costings', $project_costings);

                        $task_names_raw      = isset($_POST['task_name']) ? wp_unslash($_POST['task_name']) : [];
                        $task_hours_raw      = isset($_POST['task_est_hours']) ? wp_unslash($_POST['task_est_hours']) : [];
                        $task_volunteers_raw = isset($_POST['task_volunteer_name']) ? wp_unslash($_POST['task_volunteer_name']) : [];
                        $task_done_raw       = isset($_POST['task_done']) ? wp_unslash($_POST['task_done']) : [];

                        $project_tasks = [];

                        if (is_array($task_names_raw) && is_array($task_hours_raw) && is_array($task_volunteers_raw) && is_array($task_done_raw)) {
                            $task_row_count = max(count($task_names_raw), count($task_hours_raw), count($task_volunteers_raw), count($task_done_raw));

                            for ($i = 0; $i < $task_row_count; $i++) {
                                $task_name = isset($task_names_raw[$i]) ? sanitize_text_field($task_names_raw[$i]) : '';
                                $task_hours = isset($task_hours_raw[$i]) ? intval($task_hours_raw[$i]) : 0;
                                $task_volunteer = isset($task_volunteers_raw[$i]) ? sanitize_text_field($task_volunteers_raw[$i]) : '';
                                $task_done = isset($task_done_raw[$i]) && (string) $task_done_raw[$i] === '1';

                                $task_hours = max(0, min(99, $task_hours));
                                $task_volunteer = substr($task_volunteer, 0, 15);

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
                        }

                        update_post_meta($project_id, 'project_tasks', $project_tasks);
                        update_post_meta($project_id, 'hours_required', array_sum(array_map(function ($task) {
                            return isset($task['est_hours']) ? intval($task['est_hours']) : 0;
                        }, $project_tasks)));
                    } elseif ($project_type === 'event') {
                        update_post_meta($project_id, 'event_date', $event_date);
                        update_post_meta($project_id, 'event_location', $event_location);
                        update_post_meta($project_id, 'event_status', $event_status);
                    } elseif ($project_type === 'idea') {
                        update_post_meta($project_id, 'idea_status', $idea_status);
                    } elseif ($project_type === 'video') {
                        update_post_meta($project_id, 'training_video_url', $video_url);
                        update_post_meta($project_id, 'training_video_category', $video_category);
                        update_post_meta($project_id, 'training_video_duration', $video_duration);
                        update_post_meta($project_id, 'training_video_status', $video_status);

                        $video_attachment_id = shed_upload_training_video_attachment('training_video_file', $project_id);

                        if (is_wp_error($video_attachment_id)) {
                            $message .= '<p style="color:orange;"><strong>Project updated, but video upload failed:</strong> ' . esc_html($video_attachment_id->get_error_message()) . '</p>';
                        }
                    }

                    if (in_array($project_type, ['project', 'idea'], true)) {
                        if (!empty($_POST['remove_project_pdf'])) {
                            delete_post_meta($project_id, 'project_pdf_attachment_id');
                            delete_post_meta($project_id, 'idea_pdf_attachment_id');
                        }

                        $pdf_attachment_id = shed_upload_project_pdf_attachment('project_pdf', $project_id);

                        if (is_wp_error($pdf_attachment_id)) {
                            $message .= '<p style="color:orange;"><strong>Project updated, but PDF upload failed:</strong> ' . esc_html($pdf_attachment_id->get_error_message()) . '</p>';
                        }
                    }

                    $cropped_image = isset($_POST['project_featured_crop_base64'])
                        ? wp_unslash((string) $_POST['project_featured_crop_base64'])
                        : '';

                    if ($cropped_image !== '' && function_exists('shed_save_cropped_featured_image')) {
                        $attachment_id = shed_save_cropped_featured_image($cropped_image, $project_id);

                        if (is_wp_error($attachment_id)) {
                            $message .= '<p style="color:orange;"><strong>Project updated, but image crop failed:</strong> ' . esc_html($attachment_id->get_error_message()) . '</p>';
                        } elseif ($attachment_id) {
                            set_post_thumbnail($project_id, $attachment_id);
                        }
                    } elseif (
                        !empty($_FILES['project_image']['name']) &&
                        isset($_FILES['project_image']['tmp_name']) &&
                        is_uploaded_file($_FILES['project_image']['tmp_name'])
                    ) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $attachment_id = media_handle_upload('project_image', $project_id);

                        if (is_wp_error($attachment_id)) {
                            $message .= '<p style="color:orange;"><strong>Project updated, but image upload failed:</strong> ' . esc_html($attachment_id->get_error_message()) . '</p>';
                        } else {
                            set_post_thumbnail($project_id, $attachment_id);
                        }
                    }

                    $message = '<p style="color:green;"><strong>Project updated successfully.</strong></p>' . $message;
                    $post = get_post($project_id);
                }
            }
        }

        $project_type     = shed_get_project_type($project_id);
        $project_ref      = get_post_meta($project_id, 'project_ref', true);
        $project_name     = $post->post_title;
        $description      = $post->post_content;
        $project_contact  = shed_get_project_contact($project_id);
        $target_date      = shed_normalize_date_input(get_post_meta($project_id, 'completion_target_date', true));
        $volunteer_status = get_post_meta($project_id, 'volunteer_status', true);
        $project_stage    = shed_normalize_project_stage(get_post_meta($project_id, 'project_stage', true));
        $idea_status      = get_post_meta($project_id, 'idea_status', true);
        $event_date       = shed_normalize_date_input(get_post_meta($project_id, 'event_date', true));
        $event_location   = get_post_meta($project_id, 'event_location', true);
        $event_status     = get_post_meta($project_id, 'event_status', true);
        $video_url        = get_post_meta($project_id, 'training_video_url', true);
        $video_category   = get_post_meta($project_id, 'training_video_category', true);
        $video_duration   = get_post_meta($project_id, 'training_video_duration', true);
        $video_status     = get_post_meta($project_id, 'training_video_status', true);
        $video_attachment_id = shed_get_training_video_attachment_id($project_id);
        $video_attachment_name = $video_attachment_id ? get_the_title($video_attachment_id) : '';
        $project_pdf_url  = shed_get_project_pdf_url($project_id);
        $project_pdf_name = shed_get_project_pdf_filename($project_id);

        if ($volunteer_status === '') {
            $volunteer_status = 'seeking_volunteers';
        }

        if (!in_array($idea_status, ['open', 'ended'], true)) {
            $idea_status = 'open';
        }

        if (!in_array($event_status, ['open', 'ended'], true)) {
            $event_status = 'open';
        }

        if (!in_array($video_status, ['active', 'archived'], true)) {
            $video_status = 'active';
        }

        $project_costings = get_post_meta($project_id, 'project_costings', true);
        if (!is_array($project_costings) || empty($project_costings)) {
            $project_costings = [[
                'item'       => '',
                'qty'        => '',
                'unit_price' => '',
            ]];
        }

        $project_tasks = get_post_meta($project_id, 'project_tasks', true);
        if (!is_array($project_tasks) || empty($project_tasks)) {
            $project_tasks = [[
                'done'           => false,
                'task'           => '',
                'est_hours'      => 0,
                'volunteer_name' => '',
            ]];
        }

        $project_notes = (string) get_post_meta($project_id, 'project_notes', true);

        $is_project_type = $project_type === 'project';
        $is_idea_type    = $project_type === 'idea';
        $is_event_type   = $project_type === 'event';
        $is_video_type   = $project_type === 'video';
        $create_from_idea_url = add_query_arg('source_idea_id', $project_id, site_url('/home/members-area/create-project/'));

        ob_start();
        ?>
        <div class="shed-edit-project-form-wrap" style="max-width: 1100px;">
            <?php echo $message; ?>

            <form method="post" enctype="multipart/form-data" id="shed-edit-project-form">
                <?php wp_nonce_field('shed_edit_project_' . $project_id, 'shed_edit_project_nonce'); ?>

                <?php if ($project_ref !== '') : ?>
                    <p>
                        <label><strong>Project reference</strong></label><br>
                        <input type="text" value="<?php echo esc_attr($project_ref); ?>" readonly style="width:100%; padding:8px; background:#f7f7f7;">
                    </p>
                <?php endif; ?>

                <p>
                    <label for="project_type"><strong>Type</strong></label><br>
                    <select id="project_type" name="project_type" style="width:100%; padding:8px;">
                        <option value="project" <?php selected($project_type, 'project'); ?>>Project</option>
                        <option value="idea" <?php selected($project_type, 'idea'); ?>>Idea</option>
                        <option value="event" <?php selected($project_type, 'event'); ?>>Event</option>
                        <option value="video" <?php selected($project_type, 'video'); ?>>Training video</option>
                    </select>
                </p>

                <p>
                    <label for="project_name"><strong>Title</strong></label><br>
                    <input type="text" id="project_name" name="project_name" value="<?php echo esc_attr($project_name); ?>" required style="width:100%; padding:8px;">
                </p>

                <p>
                    <label for="project_description"><strong>Description</strong></label><br>
                    <textarea id="project_description" name="project_description" rows="6" style="width:100%; padding:8px;"><?php echo esc_textarea($description); ?></textarea>
                </p>

                <div data-type-group="project" <?php echo $is_project_type ? '' : 'hidden'; ?>>
                    <p>
                        <label for="project_contact"><strong>Project contact</strong></label><br>
                        <input type="text" id="project_contact" name="project_contact" value="<?php echo esc_attr($project_contact); ?>" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="completion_target_date"><strong>Target date</strong></label><br>
                        <input type="date" id="completion_target_date" name="completion_target_date" value="<?php echo esc_attr($target_date); ?>" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="volunteer_status"><strong>Volunteer status</strong></label><br>
                        <select id="volunteer_status" name="volunteer_status" style="width:100%; padding:8px;">
                            <option value="seeking_volunteers" <?php selected($volunteer_status, 'seeking_volunteers'); ?>>Seeking volunteers</option>
                            <option value="volunteer_goal_achieved" <?php selected($volunteer_status, 'volunteer_goal_achieved'); ?>>Volunteer goal achieved</option>
                        </select>
                    </p>

                    <p>
                        <label for="project_stage"><strong>Project lifecycle</strong></label><br>
                        <select id="project_stage" name="project_stage" style="width:100%; padding:8px;">
                            <option value="quote" <?php selected($project_stage, 'quote'); ?>>Quote</option>
                            <option value="making" <?php selected($project_stage, 'making'); ?>>Making</option>
                            <option value="invoicing" <?php selected($project_stage, 'invoicing'); ?>>Invoicing</option>
                            <option value="complete" <?php selected($project_stage, 'complete'); ?>>Complete</option>
                        </select>
                    </p>
                </div>

                <div data-type-group="event" <?php echo $is_event_type ? '' : 'hidden'; ?>>
                    <p>
                        <label for="event_date"><strong>Event date</strong></label><br>
                        <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr($event_date); ?>" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="event_location"><strong>Event location</strong></label><br>
                        <input type="text" id="event_location" name="event_location" value="<?php echo esc_attr($event_location); ?>" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="event_status"><strong>Event status</strong></label><br>
                        <select id="event_status" name="event_status" style="width:100%; padding:8px;">
                            <option value="open" <?php selected($event_status, 'open'); ?>>Open</option>
                            <option value="ended" <?php selected($event_status, 'ended'); ?>>Ended</option>
                        </select>
                    </p>
                </div>

                <div data-type-group="idea" <?php echo $is_idea_type ? '' : 'hidden'; ?>>
                    <p>
                        <label for="idea_status"><strong>Idea status</strong></label><br>
                        <select id="idea_status" name="idea_status" style="width:100%; padding:8px;">
                            <option value="open" <?php selected($idea_status, 'open'); ?>>Open</option>
                            <option value="ended" <?php selected($idea_status, 'ended'); ?>>Ended</option>
                        </select>
                    </p>

                    <p>
                        <label for="idea_pdf"><strong>Supporting PDF</strong></label><br>
                        <?php if ($project_pdf_url !== '') : ?>
                            <span>Current PDF: <a href="<?php echo esc_url($project_pdf_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($project_pdf_name !== '' ? $project_pdf_name : 'Open PDF'); ?></a></span><br>
                            <label style="display:inline-flex; align-items:center; gap:8px; margin-top:8px;">
                                <input type="checkbox" name="remove_project_pdf" value="1">
                                Remove PDF
                            </label><br>
                        <?php endif; ?>
                        <input type="file" id="idea_pdf" name="project_pdf" accept="application/pdf,.pdf" style="margin-top:8px;">
                    </p>

                    <p>
                        <a href="<?php echo esc_url($create_from_idea_url); ?>" style="display:inline-block; padding:10px 16px; background:#0a7f00; color:#fff; text-decoration:none; border-radius:6px;">
                            Create project from this idea
                        </a>
                    </p>
                </div>

                <div data-type-group="video" <?php echo $is_video_type ? '' : 'hidden'; ?>>
                    <p>
                        <label for="training_video_url"><strong>Video URL</strong></label><br>
                        <input type="url" id="training_video_url" name="training_video_url" value="<?php echo esc_attr($video_url); ?>" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="training_video_file"><strong>Replace video</strong> (optional)</label><br>
                        <?php if ($video_url !== '') : ?>
                            <span>Current video: <a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($video_attachment_name !== '' ? $video_attachment_name : 'Open video'); ?></a></span><br>
                        <?php endif; ?>
                        <input type="file" id="training_video_file" name="training_video_file" accept="video/*" style="margin-top:8px;">
                    </p>

                    <p>
                        <label for="training_video_category"><strong>Category</strong></label><br>
                        <input type="text" id="training_video_category" name="training_video_category" value="<?php echo esc_attr($video_category); ?>" placeholder="Machine safety" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="training_video_duration"><strong>Duration</strong></label><br>
                        <input type="text" id="training_video_duration" name="training_video_duration" value="<?php echo esc_attr($video_duration); ?>" placeholder="4 min" style="width:100%; padding:8px;">
                    </p>

                    <p>
                        <label for="training_video_status"><strong>Video status</strong></label><br>
                        <select id="training_video_status" name="training_video_status" style="width:100%; padding:8px;">
                            <option value="active" <?php selected($video_status, 'active'); ?>>Active</option>
                            <option value="archived" <?php selected($video_status, 'archived'); ?>>Archived</option>
                        </select>
                    </p>
                </div>

                <p>
                    <label for="project_image"><strong>Replace image</strong> (optional)</label><br>
                    <input type="file" id="project_image" name="project_image" accept="image/*">
                    <input type="hidden" name="project_featured_crop_base64" value="">
                </p>

                <div data-type-group="project" <?php echo $is_project_type ? '' : 'hidden'; ?>>
                    <p>
                        <label for="project_pdf"><strong>Supporting PDF</strong></label><br>
                        <?php if ($project_pdf_url !== '') : ?>
                            <span>Current PDF: <a href="<?php echo esc_url($project_pdf_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($project_pdf_name !== '' ? $project_pdf_name : 'Open PDF'); ?></a></span><br>
                            <label style="display:inline-flex; align-items:center; gap:8px; margin-top:8px;">
                                <input type="checkbox" name="remove_project_pdf" value="1">
                                Remove PDF
                            </label><br>
                        <?php endif; ?>
                        <input type="file" id="project_pdf" name="project_pdf" accept="application/pdf,.pdf" style="margin-top:8px;">
                    </p>

                    <hr style="margin: 32px 0;">

                    <h3 style="margin-bottom: 12px;">Tasks</h3>
                    <p style="margin-top: 0; color: #555;">Add tasks with estimated hours for each. Add the volunteers name. (This may be left blank)</p>
                    <style>
                        .shed-task-row--unassigned td {
                            background-color: #fff2cc;
                        }

                        .shed-task-row--assigned td {
                            background-color: #f3f4f6;
                        }

                        .shed-task-row--done td {
                            background-color: #d9ead3;
                        }

                        .shed-task-done-cell {
                            text-align: center;
                            vertical-align: middle;
                        }

                        .shed-task-done-checkbox {
                            accent-color: #2e7d32;
                            height: 24px;
                            width: 24px;
                        }

                        .shed-task-row input[name="task_name[]"],
                        .shed-task-row input[name="task_est_hours[]"],
                        .shed-task-row input[name="task_volunteer_name[]"] {
                            background-color: transparent;
                        }

                        .shed-task-row.is-dragging {
                            opacity: 0.55;
                        }

                        .shed-task-drag-handle {
                            cursor: grab;
                            padding: 8px 10px;
                        }
                    </style>

                    <div style="overflow-x:auto;">
                        <table id="shed-tasks-table" style="width:100%; border-collapse: collapse; margin-bottom: 14px;">
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
                            <tbody id="shed-tasks-body">
                                <?php foreach ($project_tasks as $row) : ?>
                                    <?php
                                    $task_done = !empty($row['done']);
                                    $task_name = isset($row['task']) ? $row['task'] : '';
                                    $task_est_hours = isset($row['est_hours']) ? $row['est_hours'] : 0;
                                    $task_volunteer_name = isset($row['volunteer_name']) ? $row['volunteer_name'] : '';
                                    $task_row_status_class = $task_done
                                        ? 'shed-task-row--done'
                                        : (trim((string) $task_volunteer_name) === '' ? 'shed-task-row--unassigned' : 'shed-task-row--assigned');
                                    ?>
                                    <tr class="shed-task-row <?php echo esc_attr($task_row_status_class); ?>" draggable="true">
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <button type="button" class="shed-task-drag-handle" aria-label="Move task">Move</button>
                                        </td>
                                        <td class="shed-task-done-cell" style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="hidden" name="task_done[]" value="<?php echo $task_done ? '1' : '0'; ?>">
                                            <input type="checkbox" class="shed-task-done-checkbox" <?php checked($task_done); ?>>
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="text" name="task_name[]" value="<?php echo esc_attr($task_name); ?>" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="number" min="0" max="99" step="1" name="task_est_hours[]" value="<?php echo esc_attr($task_est_hours); ?>" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="text" name="task_volunteer_name[]" maxlength="15" value="<?php echo esc_attr($task_volunteer_name); ?>" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <button type="button" class="shed-remove-task-row" style="padding:8px 10px;">Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p>
                        <button type="button" id="shed-add-task-row">Add row</button>
                    </p>

                    <template id="shed-task-row-template">
                        <tr class="shed-task-row shed-task-row--unassigned" draggable="true">
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <button type="button" class="shed-task-drag-handle" aria-label="Move task">Move</button>
                            </td>
                            <td class="shed-task-done-cell" style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="hidden" name="task_done[]" value="0">
                                <input type="checkbox" class="shed-task-done-checkbox">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="text" name="task_name[]" value="" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="number" min="0" max="99" step="1" name="task_est_hours[]" value="0" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="text" name="task_volunteer_name[]" maxlength="15" value="" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <button type="button" class="shed-remove-task-row" style="padding:8px 10px;">Remove</button>
                            </td>
                        </tr>
                    </template>

                    <hr style="margin: 32px 0;">

                    <h3 style="margin-bottom: 12px;">Project costing</h3>
                    <p style="margin-top: 0; color: #555;">Add materials or other quoted items below.</p>

                    <div style="overflow-x:auto;">
                        <table id="shed-costings-table" style="width:100%; border-collapse: collapse; margin-bottom: 14px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px;">Item</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:110px;">Qty</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:140px;">Unit price (&pound;)</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:140px;">Total (&pound;)</th>
                                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:90px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody id="shed-costings-body">
                                <?php foreach ($project_costings as $row) : ?>
                                    <?php
                                    $item = isset($row['item']) ? $row['item'] : '';
                                    $qty = isset($row['qty']) ? $row['qty'] : '';
                                    $unit_price = isset($row['unit_price']) ? $row['unit_price'] : '';
                                    ?>
                                    <tr class="shed-costing-row">
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="text" name="costing_item[]" value="<?php echo esc_attr($item); ?>" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="number" step="0.01" min="0" name="costing_qty[]" value="<?php echo esc_attr($qty); ?>" class="shed-costing-qty" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="number" step="0.01" min="0" name="costing_unit_price[]" value="<?php echo esc_attr($unit_price); ?>" class="shed-costing-unit-price" style="width:100%; padding:8px;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <input type="text" value="" class="shed-costing-line-total" readonly style="width:100%; padding:8px; background:#f7f7f7; border:1px solid #ddd;">
                                        </td>
                                        <td style="padding:8px; border-bottom:1px solid #eee;">
                                            <button type="button" class="shed-remove-costing-row" style="padding:8px 10px;">Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="padding:12px 8px; text-align:right; font-weight:700;">Grand total (&pound;)</td>
                                    <td style="padding:12px 8px;">
                                        <input type="text" id="shed-costings-grand-total" readonly style="width:100%; padding:8px; background:#f0f0f0; border:1px solid #bbb; font-weight:700;">
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p>
                        <button type="button" id="shed-add-costing-row">Add row</button>
                    </p>

                    <template id="shed-costing-row-template">
                        <tr class="shed-costing-row">
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="text" name="costing_item[]" value="" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="number" step="0.01" min="0" name="costing_qty[]" value="" class="shed-costing-qty" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="number" step="0.01" min="0" name="costing_unit_price[]" value="" class="shed-costing-unit-price" style="width:100%; padding:8px;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <input type="text" value="" class="shed-costing-line-total" readonly style="width:100%; padding:8px; background:#f7f7f7; border:1px solid #ddd;">
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">
                                <button type="button" class="shed-remove-costing-row" style="padding:8px 10px;">Remove</button>
                            </td>
                        </tr>
                    </template>

                    <p style="margin-top: 24px;">
                        <label for="project_notes"><strong>Notes</strong></label><br>
                        <textarea id="project_notes" name="project_notes" rows="6" style="width:100%; padding:8px;"><?php echo esc_textarea($project_notes); ?></textarea>
                    </p>
                </div>

                <p style="margin-top: 28px;">
                    <button type="submit">Save Project</button>
                </p>
            </form>

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

            <p>
                <a href="<?php echo esc_url(site_url('/home/members-area/create-project/')); ?>">&larr; Back to Create / edit content</a>
            </p>
        </div>

        <?php if (function_exists('shed_render_cropper_assets')) { shed_render_cropper_assets(); } ?>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('shed-edit-project-form');
            var typeField = document.getElementById('project_type');
            var tableBody = document.getElementById('shed-costings-body');
            var addBtn = document.getElementById('shed-add-costing-row');
            var template = document.getElementById('shed-costing-row-template');
            var grandTotalField = document.getElementById('shed-costings-grand-total');
            var tasksBody = document.getElementById('shed-tasks-body');
            var addTaskBtn = document.getElementById('shed-add-task-row');
            var taskTemplate = document.getElementById('shed-task-row-template');
            var imageInput = form ? form.querySelector('input[name="project_image"]') : null;
            var hiddenCrop = form ? form.querySelector('input[name="project_featured_crop_base64"]') : null;
            var modal = document.getElementById('shed-cropper-modal');
            var cropperImage = document.getElementById('shed-cropper-image');
            var applyCropBtn = document.getElementById('shed-cropper-apply');
            var cancelCropBtn = document.getElementById('shed-cropper-cancel');
            var cropperAvailable = typeof Cropper !== 'undefined';
            var cropper = null;
            var objectUrl = null;
            var cropConfirmed = false;
            var draggedTaskRow = null;

            function openCropperModal() {
                if (!modal) {
                    return;
                }

                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            function closeCropperModal() {
                if (!modal) {
                    return;
                }

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

            function toNumber(value) {
                if (value === null || value === undefined || value === '') {
                    return 0;
                }
                value = String(value).replace(',', '.');
                var n = parseFloat(value);
                return isNaN(n) ? 0 : n;
            }

            function money(value) {
                return value.toFixed(2);
            }

            function recalcCostings() {
                if (!tableBody) {
                    return;
                }

                var rows = tableBody.querySelectorAll('.shed-costing-row');
                var grandTotal = 0;

                rows.forEach(function (row) {
                    var qtyField = row.querySelector('.shed-costing-qty');
                    var unitPriceField = row.querySelector('.shed-costing-unit-price');
                    var lineTotalField = row.querySelector('.shed-costing-line-total');
                    var qty = toNumber(qtyField ? qtyField.value : 0);
                    var unitPrice = toNumber(unitPriceField ? unitPriceField.value : 0);
                    var lineTotal = qty * unitPrice;

                    if (lineTotalField) {
                        lineTotalField.value = money(lineTotal);
                    }

                    grandTotal += lineTotal;
                });

                if (grandTotalField) {
                    grandTotalField.value = money(grandTotal);
                }
            }

            function bindRow(row) {
                if (!row) {
                    return;
                }

                var qtyField = row.querySelector('.shed-costing-qty');
                var unitPriceField = row.querySelector('.shed-costing-unit-price');
                var removeBtn = row.querySelector('.shed-remove-costing-row');

                if (qtyField) {
                    qtyField.addEventListener('input', recalcCostings);
                }

                if (unitPriceField) {
                    unitPriceField.addEventListener('input', recalcCostings);
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        var rows = tableBody.querySelectorAll('.shed-costing-row');
                        if (rows.length > 1) {
                            row.remove();
                        } else {
                            row.querySelectorAll('input').forEach(function (input) {
                                if (input.type === 'text' || input.type === 'number') {
                                    input.value = '';
                                }
                            });
                        }
                        recalcCostings();
                    });
                }
            }

            function bindTaskRow(row) {
                if (!row) {
                    return;
                }

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

                function updateTaskRowStatus() {
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

                if (doneCheckbox && doneField) {
                    doneCheckbox.addEventListener('change', function () {
                        doneField.value = doneCheckbox.checked ? '1' : '0';
                        updateTaskRowStatus();
                    });
                }

                if (volunteerField) {
                    volunteerField.addEventListener('input', updateTaskRowStatus);
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        var rows = tasksBody.querySelectorAll('.shed-task-row');
                        if (rows.length > 1) {
                            row.remove();
                        } else {
                            row.querySelectorAll('input').forEach(function (input) {
                                if (input.type === 'text') {
                                    input.value = '';
                                }
                                if (input.type === 'number') {
                                    input.value = '0';
                                }
                                if (input.type === 'checkbox') {
                                    input.checked = false;
                                }
                                if (input.type === 'hidden' && input.name === 'task_done[]') {
                                    input.value = '0';
                                }
                            });
                            updateTaskRowStatus();
                        }
                    });
                }

                updateTaskRowStatus();
            }

            function toggleTypeGroups() {
                if (!form || !typeField) {
                    return;
                }

                var currentType = typeField.value || 'project';

                form.querySelectorAll('[data-type-group]').forEach(function (group) {
                    var isVisible = group.getAttribute('data-type-group') === currentType;
                    group.hidden = !isVisible;

                    group.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                        if (field.type === 'submit' || field.type === 'button') {
                            field.disabled = !isVisible;
                            return;
                        }

                        field.disabled = !isVisible;
                    });
                });
            }

            if (tableBody) {
                tableBody.querySelectorAll('.shed-costing-row').forEach(bindRow);
            }

            if (addBtn && tableBody && template) {
                addBtn.addEventListener('click', function () {
                    var clone = template.content.cloneNode(true);
                    tableBody.appendChild(clone);
                    var rows = tableBody.querySelectorAll('.shed-costing-row');
                    bindRow(rows[rows.length - 1]);
                    recalcCostings();
                });
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

            if (typeField) {
                typeField.addEventListener('change', toggleTypeGroups);
            }

            if (imageInput && hiddenCrop && modal && cropperImage && cropperAvailable) {
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
                    openCropperModal();

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
            }

            if (applyCropBtn && hiddenCrop) {
                applyCropBtn.addEventListener('click', function () {
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
                    closeCropperModal();
                });
            }

            if (cancelCropBtn && imageInput && hiddenCrop) {
                cancelCropBtn.addEventListener('click', function () {
                    cropConfirmed = false;
                    hiddenCrop.value = '';
                    imageInput.value = '';
                    destroyCropper();
                    closeCropperModal();
                });
            }

            if (form && imageInput && cropperAvailable) {
                form.addEventListener('submit', function (e) {
                    if (imageInput.files.length > 0 && !cropConfirmed) {
                        e.preventDefault();
                        alert('Please crop the featured image before submitting.');
                        openCropperModal();
                    }
                });
            }

            toggleTypeGroups();
            recalcCostings();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_edit_project_form', 'shed_edit_project_form_shortcode');
