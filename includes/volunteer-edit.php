<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_render_edit_volunteer_commitment_form')) {
    function shed_render_edit_volunteer_commitment_form() {
        $message = '';
        $message_type = 'success';
        $selected_signup_id = 0;

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['shed_edit_volunteer_nonce']) &&
            wp_verify_nonce($_POST['shed_edit_volunteer_nonce'], 'shed_edit_volunteer_commitment')
        ) {
            $signup_id          = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
            $selected_signup_id = $signup_id;
            $new_name_raw       = isset($_POST['volunteer_name']) ? sanitize_text_field($_POST['volunteer_name']) : '';
            $requested_hours    = isset($_POST['volunteer_hours']) ? max(0, intval($_POST['volunteer_hours'])) : 0;
            $notes              = isset($_POST['volunteer_notes']) ? sanitize_textarea_field($_POST['volunteer_notes']) : '';

            $signup_post = get_post($signup_id);

            if (!$signup_post || $signup_post->post_type !== 'volunteer_signup') {
                $message = 'That volunteer commitment could not be found.';
                $message_type = 'error';
            } else {
                $project_id   = intval(get_post_meta($signup_id, 'project_id', true));
                $current_name = get_post_meta($signup_id, 'volunteer_name', true);

                if (!$project_id || get_post_type($project_id) !== 'project') {
                    $message = 'The linked project could not be found.';
                    $message_type = 'error';
                } else {
                    $final_name = $new_name_raw !== '' ? $new_name_raw : $current_name;

                    $other_signups = get_posts([
                        'post_type'      => 'volunteer_signup',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                        'exclude'        => [$signup_id],
                        'meta_query'     => [
                            [
                                'key'   => 'project_id',
                                'value' => $project_id,
                            ]
                        ]
                    ]);

                    $other_hours_total = 0;

                    foreach ($other_signups as $other_signup) {
                        $other_hours_total += intval(get_post_meta($other_signup->ID, 'volunteer_hours', true));
                    }

                    $hours_required = intval(get_post_meta($project_id, 'hours_required', true));

                    $max_allowed_for_this_signup = max(0, $hours_required - $other_hours_total);
                    $final_hours = min($requested_hours, $max_allowed_for_this_signup);

                    update_post_meta($signup_id, 'volunteer_name', $final_name);
                    update_post_meta($signup_id, 'volunteer_hours', $final_hours);
                    update_post_meta($signup_id, 'notes', $notes);
                    update_post_meta($signup_id, 'edited_at', current_time('mysql'));

                    if (function_exists('shed_update_project_volunteer_totals')) {
                        shed_update_project_volunteer_totals($project_id);
                    } else {
                        $updated_signups = get_posts([
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

                        $new_total_hours = 0;

                        foreach ($updated_signups as $updated_signup) {
                            $new_total_hours += intval(get_post_meta($updated_signup->ID, 'volunteer_hours', true));
                        }

                        update_post_meta($project_id, 'hours_committed', $new_total_hours);

                        if ($hours_required > 0 && $new_total_hours >= $hours_required) {
                            update_post_meta($project_id, 'volunteer_status', 'volunteer_goal_achieved');
                        } else {
                            update_post_meta($project_id, 'volunteer_status', 'seeking_volunteers');
                        }
                    }

                    if ($final_hours < $requested_hours) {
                        $message = 'Volunteer commitment updated. Hours were capped to ' . $final_hours . ' so the project does not exceed its required total.';
                    } else {
                        $message = 'Volunteer commitment updated successfully.';
                    }
                }
            }
        }

        $all_signup_posts = get_posts([
            'post_type'      => 'volunteer_signup',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $grouped_signups = [];

        foreach ($all_signup_posts as $signup) {
            $signup_id  = $signup->ID;
            $project_id = intval(get_post_meta($signup_id, 'project_id', true));

            if (!$project_id || get_post_type($project_id) !== 'project') {
                continue;
            }

            $project_stage = get_post_meta($project_id, 'status', true);

            if ($project_stage === 'complete') {
                continue;
            }

            $project_ref   = get_post_meta($project_id, 'project_ref', true);
            $project_title = get_the_title($project_id);

            if ($project_title === '') {
                $project_title = 'Untitled project';
            }

            $project_label = $project_title;
            if ($project_ref !== '') {
                $project_label = $project_ref . ' - ' . $project_title;
            }

            $volunteer_name  = trim((string) get_post_meta($signup_id, 'volunteer_name', true));
            $volunteer_hours = intval(get_post_meta($signup_id, 'volunteer_hours', true));
            $volunteer_notes = get_post_meta($signup_id, 'notes', true);

            $grouped_signups[$project_label][] = [
                'signup_id'       => $signup_id,
                'project_id'      => $project_id,
                'project_ref'     => $project_ref,
                'project_title'   => $project_title,
                'project_label'   => $project_label,
                'volunteer_name'  => $volunteer_name,
                'volunteer_hours' => $volunteer_hours,
                'volunteer_notes' => $volunteer_notes,
            ];
        }

        if (!empty($grouped_signups)) {
            ksort($grouped_signups, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($grouped_signups as $project_title => &$signups) {
                usort($signups, function ($a, $b) {
                    return strcasecmp($a['volunteer_name'], $b['volunteer_name']);
                });
            }
            unset($signups);
        }

        ob_start();
        ?>

        <style>
            .shed-edit-volunteer-wrap {
                max-width: 760px;
                margin: 0 auto;
                padding: 24px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 12px;
            }

            .shed-edit-volunteer-wrap h2 {
                margin-top: 0;
                margin-bottom: 20px;
            }

            .shed-edit-volunteer-wrap .shed-field {
                margin-bottom: 18px;
            }

            .shed-edit-volunteer-wrap label {
                display: block;
                font-weight: 700;
                margin-bottom: 6px;
            }

            .shed-edit-volunteer-wrap input,
            .shed-edit-volunteer-wrap select,
            .shed-edit-volunteer-wrap textarea {
                width: 100%;
                padding: 10px 12px;
                box-sizing: border-box;
                border: 1px solid #ccc;
                border-radius: 8px;
            }

            .shed-edit-volunteer-wrap textarea {
                min-height: 120px;
                resize: vertical;
            }

            .shed-edit-volunteer-wrap button {
                background: #0a7f00;
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 12px 18px;
                cursor: pointer;
                font-size: 1rem;
            }

            .shed-edit-volunteer-message {
                margin-bottom: 18px;
                padding: 12px 14px;
                border-radius: 8px;
            }

            .shed-edit-volunteer-message.success {
                background: #e8f6e8;
                color: #0a7f00;
            }

            .shed-edit-volunteer-message.error {
                background: #fde8e8;
                color: #b30000;
            }

            .shed-edit-volunteer-help {
                font-size: 0.95rem;
                color: #555;
                margin-top: 6px;
            }
        </style>

        <div class="shed-edit-volunteer-wrap">
            <h2>Edit Volunteer Commitment</h2>

            <?php if ($message !== ''): ?>
                <div class="shed-edit-volunteer-message <?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($grouped_signups)): ?>
                <p>No volunteer commitments found for projects that are not complete.</p>
            <?php else: ?>
                <form method="post">
                    <?php wp_nonce_field('shed_edit_volunteer_commitment', 'shed_edit_volunteer_nonce'); ?>

                    <div class="shed-field">
                        <label for="shed-signup-id">Volunteer commitment</label>
                        <select id="shed-signup-id" name="signup_id" required>
                            <option value="">Select a volunteer commitment</option>

                            <?php foreach ($grouped_signups as $project_label => $signups): ?>
                                <optgroup label="<?php echo esc_attr($project_label); ?>">
                                    <?php foreach ($signups as $signup_item): ?>
                                        <?php
                                        $option_label = trim($signup_item['volunteer_name']);
                                        if ($option_label === '') {
                                            $option_label = 'Unnamed volunteer';
                                        }
                                        $option_label .= ' - ' . intval($signup_item['volunteer_hours']) . 'h';
                                        ?>
                                        <option
                                            value="<?php echo esc_attr($signup_item['signup_id']); ?>"
                                            data-volunteer-name="<?php echo esc_attr($signup_item['volunteer_name']); ?>"
                                            data-volunteer-hours="<?php echo esc_attr($signup_item['volunteer_hours']); ?>"
                                            data-volunteer-notes="<?php echo esc_attr($signup_item['volunteer_notes']); ?>"
                                            <?php selected($selected_signup_id, $signup_item['signup_id']); ?>
                                        >
                                            <?php echo esc_html($option_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="shed-field">
                        <label for="shed-volunteer-name">Volunteer name</label>
                        <input type="text" id="shed-volunteer-name" name="volunteer_name" value="">
                        <div class="shed-edit-volunteer-help">You can change the name here if needed.</div>
                    </div>

                    <div class="shed-field">
                        <label for="shed-volunteer-hours">Committed hours</label>
                        <input type="number" id="shed-volunteer-hours" name="volunteer_hours" value="" min="0" step="1" required>
                        <div class="shed-edit-volunteer-help">Set to 0 if the volunteer can no longer help.</div>
                    </div>

                    <div class="shed-field">
                        <label for="shed-volunteer-notes">Notes / reason for change</label>
                        <textarea id="shed-volunteer-notes" name="volunteer_notes"></textarea>
                    </div>

                    <button type="submit">Save volunteer changes</button>
                </form>
            <?php endif; ?>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var select = document.getElementById('shed-signup-id');
                var nameField = document.getElementById('shed-volunteer-name');
                var hoursField = document.getElementById('shed-volunteer-hours');
                var notesField = document.getElementById('shed-volunteer-notes');

                if (!select) return;

                function populateFields() {
                    var selected = select.options[select.selectedIndex];

                    if (!selected || !selected.value) {
                        nameField.value = '';
                        hoursField.value = '';
                        notesField.value = '';
                        return;
                    }

                    nameField.value = selected.getAttribute('data-volunteer-name') || '';
                    hoursField.value = selected.getAttribute('data-volunteer-hours') || '';
                    notesField.value = selected.getAttribute('data-volunteer-notes') || '';
                }

                select.addEventListener('change', populateFields);
                populateFields();
            });
        </script>

        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_edit_volunteer_commitment', 'shed_render_edit_volunteer_commitment_form');