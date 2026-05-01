<?php

if (!defined('ABSPATH')) {
    exit;
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
                ],
            ],
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

if (!function_exists('shed_get_available_volunteer_projects')) {
    function shed_get_available_volunteer_projects() {
        $projects = get_posts([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $items = [];

        foreach ($projects as $project) {
            if (shed_get_project_type($project->ID) !== 'project') {
                continue;
            }

            if (get_post_meta($project->ID, 'project_stage', true) === 'complete') {
                continue;
            }

            $project_ref = get_post_meta($project->ID, 'project_ref', true);
            $label = $project->post_title;

            if ($project_ref !== '') {
                $label = $project_ref . ' - ' . $label;
            }

            $items[] = [
                'id'    => $project->ID,
                'label' => $label,
            ];
        }

        return $items;
    }
}

if (!function_exists('shed_get_native_volunteer_signup_defaults')) {
    function shed_get_native_volunteer_signup_defaults() {
        return [
            'project_id'      => isset($_GET['project_id']) ? absint(wp_unslash($_GET['project_id'])) : 0,
            'volunteer_name'  => '',
            'requested_hours' => '',
            'notes'           => '',
        ];
    }
}

if (!function_exists('shed_normalize_native_volunteer_signup_submission')) {
    function shed_normalize_native_volunteer_signup_submission($raw) {
        return [
            'project_id'      => absint(isset($raw['project_id']) ? wp_unslash($raw['project_id']) : 0),
            'volunteer_name'  => sanitize_text_field(isset($raw['volunteer_name']) ? wp_unslash((string) $raw['volunteer_name']) : ''),
            'requested_hours' => max(0, intval(isset($raw['requested_hours']) ? wp_unslash($raw['requested_hours']) : 0)),
            'notes'           => sanitize_textarea_field(isset($raw['notes']) ? wp_unslash((string) $raw['notes']) : ''),
        ];
    }
}

if (!function_exists('shed_create_volunteer_signup_from_submission')) {
    function shed_create_volunteer_signup_from_submission($submission) {
        $project_id      = $submission['project_id'];
        $volunteer_name  = $submission['volunteer_name'];
        $requested_hours = $submission['requested_hours'];
        $notes           = $submission['notes'];
        $timestamp       = current_time('mysql');

        if ($project_id <= 0 || $volunteer_name === '' || $requested_hours <= 0) {
            return new WP_Error('missing_fields', 'Please choose a project, add your name, and enter the number of hours you can help with.');
        }

        $project_post = get_post($project_id);

        if (!$project_post || $project_post->post_type !== 'project') {
            return new WP_Error('invalid_project', 'That project could not be found.');
        }

        if (shed_get_project_type($project_id) !== 'project') {
            return new WP_Error('invalid_project_type', 'Only live projects can accept volunteer signups.');
        }

        if (get_post_meta($project_id, 'project_stage', true) === 'complete') {
            return new WP_Error('project_complete', 'That project is already complete.');
        }

        $project_ref     = get_post_meta($project_id, 'project_ref', true);
        $project_title   = get_the_title($project_id);
        $hours_required  = intval(get_post_meta($project_id, 'hours_required', true));
        $hours_committed = intval(get_post_meta($project_id, 'hours_committed', true));
        $remaining_hours = max(0, $hours_required - $hours_committed);
        $final_hours     = min($requested_hours, $remaining_hours);

        if ($final_hours <= 0) {
            return new WP_Error('no_hours_left', 'This project does not need any more volunteer hours right now.');
        }

        $signup_title = $volunteer_name . ' - ';

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
            return new WP_Error('signup_failed', 'The volunteer signup could not be saved.');
        }

        update_post_meta($signup_post_id, 'volunteer_name', $volunteer_name);
        update_post_meta($signup_post_id, 'volunteer_hours', $final_hours);
        update_post_meta($signup_post_id, 'project_id', $project_id);
        update_post_meta($signup_post_id, 'notes', $notes);
        update_post_meta($signup_post_id, 'timestamp', $timestamp);

        $new_total_hours = shed_update_project_volunteer_totals($project_id);

        shed_log('VOLUNTEER SIGNUP: saved successfully', [
            'signup_post_id'  => $signup_post_id,
            'project_id'      => $project_id,
            'volunteer_name'  => $volunteer_name,
            'final_hours'     => $final_hours,
            'new_total_hours' => $new_total_hours,
        ]);

        return $signup_post_id;
    }
}

if (!function_exists('shed_handle_native_volunteer_signup_submission')) {
    function shed_handle_native_volunteer_signup_submission() {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_POST['shed_volunteer_signup_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_volunteer_signup_nonce']), 'shed_volunteer_signup')
        ) {
            return null;
        }

        $submission = shed_normalize_native_volunteer_signup_submission($_POST);
        $result = shed_create_volunteer_signup_from_submission($submission);

        return [
            'submission' => $submission,
            'result'     => $result,
        ];
    }
}

if (!function_exists('shed_render_native_volunteer_signup_form')) {
    function shed_render_native_volunteer_signup_form() {
        $handled = shed_handle_native_volunteer_signup_submission();
        $values = shed_get_native_volunteer_signup_defaults();
        $message = '';
        $message_type = '';

        if ($handled) {
            $values = array_merge($values, $handled['submission']);

            if (is_wp_error($handled['result'])) {
                $message = $handled['result']->get_error_message();
                $message_type = 'error';
            } else {
                $message = 'Volunteer signup saved successfully.';
                $message_type = 'success';
                $values = shed_get_native_volunteer_signup_defaults();
            }
        }

        $projects = shed_get_available_volunteer_projects();

        if (empty($projects)) {
            return '<p>No active projects are currently available for volunteers.</p>';
        }

        $selected_project = $values['project_id'] ? get_post($values['project_id']) : null;
        $remaining_hours = 0;

        if ($selected_project && $selected_project->post_type === 'project' && shed_get_project_type($selected_project->ID) === 'project') {
            $hours_required = intval(get_post_meta($selected_project->ID, 'hours_required', true));
            $hours_committed = intval(get_post_meta($selected_project->ID, 'hours_committed', true));
            $remaining_hours = max(0, $hours_required - $hours_committed);
        }

        ob_start();
        ?>
        <div class="shed-volunteer-signup-wrap">
            <?php if ($message !== '') : ?>
                <div class="shed-volunteer-signup-message shed-volunteer-signup-message-<?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <form class="shed-native-volunteer-signup-form" method="post">
                <?php wp_nonce_field('shed_volunteer_signup', 'shed_volunteer_signup_nonce'); ?>

                <div class="shed-form-field">
                    <label for="shed-volunteer-project"><strong>Project</strong></label>
                    <select id="shed-volunteer-project" name="project_id" required>
                        <option value="">Select a project</option>
                        <?php foreach ($projects as $project_item) : ?>
                            <option value="<?php echo esc_attr((string) $project_item['id']); ?>" <?php selected($values['project_id'], $project_item['id']); ?>>
                                <?php echo esc_html($project_item['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="shed-form-field">
                    <label for="shed-volunteer-name"><strong>Your name</strong></label>
                    <input id="shed-volunteer-name" type="text" name="volunteer_name" value="<?php echo esc_attr($values['volunteer_name']); ?>" required>
                </div>

                <div class="shed-form-field">
                    <label for="shed-volunteer-hours"><strong>Hours you can help with</strong></label>
                    <input id="shed-volunteer-hours" type="number" min="1" step="1" name="requested_hours" value="<?php echo esc_attr((string) $values['requested_hours']); ?>" required>
                    <?php if ($remaining_hours > 0) : ?>
                        <div class="shed-volunteer-signup-help"><?php echo esc_html($remaining_hours); ?> hours currently still needed on this project.</div>
                    <?php endif; ?>
                </div>

                <div class="shed-form-field">
                    <label for="shed-volunteer-notes"><strong>Notes</strong> (optional)</label>
                    <textarea id="shed-volunteer-notes" name="notes" rows="4"><?php echo esc_textarea($values['notes']); ?></textarea>
                </div>

                <div class="shed-form-actions">
                    <button type="submit">Submit volunteer signup</button>
                </div>
            </form>
        </div>

        <style>
            .shed-volunteer-signup-wrap { max-width: 760px; }
            .shed-volunteer-signup-message { margin-bottom: 16px; padding: 12px 14px; border-radius: 8px; }
            .shed-volunteer-signup-message-success { background: #edf8ed; color: #136a13; border: 1px solid #9fce9f; }
            .shed-volunteer-signup-message-error { background: #fff1f1; color: #8a1f1f; border: 1px solid #e1a3a3; }
            .shed-native-volunteer-signup-form .shed-form-field { margin-bottom: 18px; }
            .shed-native-volunteer-signup-form label { display: block; margin-bottom: 6px; }
            .shed-native-volunteer-signup-form input,
            .shed-native-volunteer-signup-form select,
            .shed-native-volunteer-signup-form textarea { width: 100%; padding: 10px 12px; box-sizing: border-box; }
            .shed-volunteer-signup-help { margin-top: 6px; color: #555; font-size: 0.95rem; }
            .shed-form-actions button { padding: 12px 18px; border: 0; border-radius: 8px; background: #0a7f00; color: #fff; cursor: pointer; }
        </style>
        <?php

        return ob_get_clean();
    }
}

add_shortcode('shed_volunteer_signup_form', 'shed_render_native_volunteer_signup_form');
