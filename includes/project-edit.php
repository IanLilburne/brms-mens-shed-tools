<?php

if (!defined('ABSPATH')) {
    exit;
}

error_log('SHED PROJECT EDIT: file loaded');

if (!function_exists('shed_project_edit_selector_shortcode')) {
    function shed_project_edit_selector_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to edit projects.</p>';
        }

        $projects = get_posts(array(
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'project_stage',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'project_stage',
                    'value'   => 'complete',
                    'compare' => '!=',
                ),
            ),
        ));

        if (empty($projects)) {
            return '<p>No active projects available to edit.</p>';
        }

        $edit_form_page_url = site_url('/home/members-area/edit-a-project/');

        ob_start();
        ?>
        <form id="shed-project-edit-selector-form" style="max-width: 560px;">
            <p>
                <label for="shed_project_id"><strong>Select project to edit</strong></label><br>
                <select id="shed_project_id" name="project_id" style="width:100%; padding:8px;">
                    <option value="">Select a project...</option>
                    <?php foreach ($projects as $project) : ?>
                        <?php
                        $project_ref = get_post_meta($project->ID, 'project_ref', true);

                        $stage = get_post_meta($project->ID, 'project_stage', true);
                        if ($stage === '') {
                            $stage = 'quote';
                        }

                        $stage_labels = array(
                            'quote'       => 'Quote',
                            'in_progress' => 'In progress',
                            'invoicing'   => 'Invoicing',
                            'complete'    => 'Complete',
                        );

                        $stage_label = isset($stage_labels[$stage])
                            ? $stage_labels[$stage]
                            : ucfirst(str_replace('_', ' ', $stage));

                        $project_label = $project->post_title;
                        if ($project_ref !== '') {
                            $project_label = $project_ref . ' - ' . $project->post_title;
                        }
                        ?>
                        <option value="<?php echo esc_attr($project->ID); ?>">
                            <?php echo esc_html($project_label . ' (' . $stage_label . ')'); ?>
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
                var url = baseUrl + separator + 'project_id=' + encodeURIComponent(select.value);

                window.location.href = url;
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_project_edit_selector', 'shed_project_edit_selector_shortcode');

if (!function_exists('shed_edit_project_form_shortcode')) {
    function shed_edit_project_form_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to edit projects.</p>';
        }

        $project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;

        if (!$project_id) {
            return '<p>No project selected.</p>';
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
            wp_verify_nonce($_POST['shed_edit_project_nonce'], 'shed_edit_project_' . $project_id)
        ) {
            $project_name      = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
            $description       = isset($_POST['project_description']) ? sanitize_textarea_field(wp_unslash($_POST['project_description'])) : '';
            $hours_required    = isset($_POST['hours_required']) ? intval($_POST['hours_required']) : 0;
            $target_date       = isset($_POST['completion_target_date']) ? sanitize_text_field(wp_unslash($_POST['completion_target_date'])) : '';
            $volunteer_status  = isset($_POST['volunteer_status']) ? sanitize_text_field(wp_unslash($_POST['volunteer_status'])) : 'seeking_volunteers';
            $project_stage     = isset($_POST['project_stage']) ? sanitize_text_field(wp_unslash($_POST['project_stage'])) : 'quote';

            if (!empty($target_date)) {
                $parts = explode('-', $target_date);
                if (count($parts) === 3) {
                    $year  = trim($parts[0]);
                    $month = str_pad(trim($parts[1]), 2, '0', STR_PAD_LEFT);
                    $day   = str_pad(trim($parts[2]), 2, '0', STR_PAD_LEFT);
                    $target_date = $year . '-' . $month . '-' . $day;
                } else {
                    $target_date = '';
                }
            }

            $allowed_volunteer_statuses = array(
                'seeking_volunteers',
                'volunteer_goal_achieved',
            );

            $allowed_stages = array(
                'quote',
                'awaiting_you',
                'in_progress',
                'invoicing',
                'complete',
            );

            if ($project_name === '' || $hours_required <= 0) {
                $message = '<p style="color:red;"><strong>Please complete the required fields.</strong></p>';
            } else {
                if (!in_array($volunteer_status, $allowed_volunteer_statuses, true)) {
                    $volunteer_status = 'seeking_volunteers';
                }

                if (!in_array($project_stage, $allowed_stages, true)) {
                    $project_stage = 'quote';
                }

                $result = wp_update_post(array(
                    'ID'           => $project_id,
                    'post_title'   => $project_name,
                    'post_content' => $description,
                ), true);

                if (is_wp_error($result)) {
                    $message = '<p style="color:red;"><strong>Update failed:</strong> ' . esc_html($result->get_error_message()) . '</p>';
                } else {
                    update_post_meta($project_id, 'hours_required', $hours_required);
                    update_post_meta($project_id, 'volunteer_status', $volunteer_status);
                    update_post_meta($project_id, 'project_stage', $project_stage);
                    update_post_meta($project_id, 'completion_target_date', $target_date);

                    $costing_items_raw       = isset($_POST['costing_item']) ? wp_unslash($_POST['costing_item']) : array();
                    $costing_qtys_raw        = isset($_POST['costing_qty']) ? wp_unslash($_POST['costing_qty']) : array();
                    $costing_unit_prices_raw = isset($_POST['costing_unit_price']) ? wp_unslash($_POST['costing_unit_price']) : array();

                    $project_costings = array();

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

                            $project_costings[] = array(
                                'item'       => $item,
                                'qty'        => $qty,
                                'unit_price' => $unit_price,
                            );
                        }
                    }

                    update_post_meta($project_id, 'project_costings', $project_costings);

                    $task_names_raw = isset($_POST['task_name']) ? wp_unslash($_POST['task_name']) : array();
                    $task_hours_raw = isset($_POST['task_est_hours']) ? wp_unslash($_POST['task_est_hours']) : array();
                    $task_volunteers_raw = isset($_POST['task_volunteer_name']) ? wp_unslash($_POST['task_volunteer_name']) : array();

                    $project_tasks = array();

                    if (is_array($task_names_raw) && is_array($task_hours_raw) && is_array($task_volunteers_raw)) {
                        $task_row_count = max(count($task_names_raw), count($task_hours_raw), count($task_volunteers_raw));

                        for ($i = 0; $i < $task_row_count; $i++) {
                            $task_name = isset($task_names_raw[$i]) ? sanitize_text_field($task_names_raw[$i]) : '';
                            $task_hours = isset($task_hours_raw[$i]) ? intval($task_hours_raw[$i]) : 0;
                            $task_volunteer = isset($task_volunteers_raw[$i]) ? sanitize_text_field($task_volunteers_raw[$i]) : '';

                            $task_hours = max(0, min(99, $task_hours));
                            $task_volunteer = substr($task_volunteer, 0, 15);

                            if ($task_name === '' && $task_hours <= 0 && $task_volunteer === '') {
                                continue;
                            }

                            $project_tasks[] = array(
                                'task'           => $task_name,
                                'est_hours'      => $task_hours,
                                'volunteer_name' => $task_volunteer,
                            );
                        }
                    }

                    update_post_meta($project_id, 'project_tasks', $project_tasks);

                    if (
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

                    $message = '<p style="color:green;"><strong>Project updated successfully.</strong></p>';

                    $post = get_post($project_id);
                }
            }
        }

        $project_ref    = get_post_meta($project_id, 'project_ref', true);
        $project_name   = $post->post_title;
        $description    = $post->post_content;
        $hours_required = get_post_meta($project_id, 'hours_required', true);

        $target_date_raw = get_post_meta($project_id, 'completion_target_date', true);
        $target_date = '';

        if (!empty($target_date_raw)) {
            $raw = trim($target_date_raw);

            if (strpos($raw, '/') !== false) {
                $parts = explode('/', $raw);
                if (count($parts) === 3) {
                    $day   = str_pad(trim($parts[0]), 2, '0', STR_PAD_LEFT);
                    $month = str_pad(trim($parts[1]), 2, '0', STR_PAD_LEFT);
                    $year  = trim($parts[2]);
                    $target_date = $year . '-' . $month . '-' . $day;
                }
            } else {
                $parts = explode('-', $raw);
                if (count($parts) === 3) {
                    $a = trim($parts[0]);
                    $b = trim($parts[1]);
                    $c = trim($parts[2]);

                    if (strlen($a) === 4) {
                        $target_date = $a . '-' . str_pad($b, 2, '0', STR_PAD_LEFT) . '-' . str_pad($c, 2, '0', STR_PAD_LEFT);
                    } else {
                        $target_date = $c . '-' . str_pad($b, 2, '0', STR_PAD_LEFT) . '-' . str_pad($a, 2, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        $volunteer_status = get_post_meta($project_id, 'volunteer_status', true);
        $project_stage    = get_post_meta($project_id, 'project_stage', true);

        if ($volunteer_status === '') {
            $volunteer_status = 'seeking_volunteers';
        }

        if ($project_stage === '') {
            $project_stage = 'quote';
        }

        $project_costings = get_post_meta($project_id, 'project_costings', true);
        if (!is_array($project_costings)) {
            $project_costings = array();
        }

        $project_tasks = get_post_meta($project_id, 'project_tasks', true);
        if (!is_array($project_tasks)) {
            $project_tasks = array();
        }

        if (empty($project_costings)) {
            $project_costings[] = array(
                'item'       => '',
                'qty'        => '',
                'unit_price' => '',
            );
        }

        if (empty($project_tasks)) {
            $project_tasks[] = array(
                'task'           => '',
                'est_hours'      => 0,
                'volunteer_name' => '',
            );
        }

        ob_start();
        ?>
        <div class="shed-edit-project-form-wrap" style="max-width: 1100px;">
            <?php echo $message; ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('shed_edit_project_' . $project_id, 'shed_edit_project_nonce'); ?>

                <?php if ($project_ref !== '') : ?>
                    <p>
                        <label><strong>Project reference</strong></label><br>
                        <input type="text" value="<?php echo esc_attr($project_ref); ?>" readonly style="width:100%; padding:8px; background:#f7f7f7;">
                    </p>
                <?php endif; ?>

                <p>
                    <label for="project_name"><strong>Project name</strong></label><br>
                    <input type="text" id="project_name" name="project_name" value="<?php echo esc_attr($project_name); ?>" required style="width:100%; padding:8px;">
                </p>

                <p>
                    <label for="project_description"><strong>Description</strong></label><br>
                    <textarea id="project_description" name="project_description" rows="6" style="width:100%; padding:8px;"><?php echo esc_textarea($description); ?></textarea>
                </p>

                <p>
                    <label for="hours_required"><strong>Hours required</strong></label><br>
                    <input type="number" id="hours_required" name="hours_required" min="1" value="<?php echo esc_attr($hours_required); ?>" required style="width:100%; padding:8px;">
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
                    <select id="project_stage" name="project_stage">
                        <option value="quote" <?php selected($project_stage, 'quote'); ?>>Quote</option>
                        <option value="awaiting_you" <?php selected($project_stage, 'awaiting_you'); ?>>Awaiting you!</option>
                        <option value="in_progress" <?php selected($project_stage, 'in_progress'); ?>>In progress</option>
                        <option value="invoicing" <?php selected($project_stage, 'invoicing'); ?>>Invoicing</option>
                        <option value="complete" <?php selected($project_stage, 'complete'); ?>>Complete</option>
                    </select>
                </p>

                <p>
                    <label for="project_image"><strong>Replace image</strong> (optional)</label><br>
                    <input type="file" id="project_image" name="project_image" accept="image/*">
                </p>

            <hr style="margin: 32px 0;">

            <h3 style="margin-bottom: 12px;">Tasks test</h3>
            <p style="margin-top: 0; color: #555;">Add tasks with estimated hours for each. Add the volunteers name. (This may be left blank)</p>

            <div style="overflow-x:auto;">
                <table id="shed-tasks-table" style="width:100%; border-collapse: collapse; margin-bottom: 14px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:50%;">Task</th>
                            <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:130px;">Est hours</th>
                            <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:220px;">Volunteer name</th>
                            <th style="text-align:left; border-bottom:1px solid #ddd; padding:10px; width:90px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody id="shed-tasks-body">
                        <?php foreach ($project_tasks as $row) : ?>
                            <?php
                            $task_name = isset($row['task']) ? $row['task'] : '';
                            $task_est_hours = isset($row['est_hours']) ? $row['est_hours'] : 0;
                            $task_volunteer_name = isset($row['volunteer_name']) ? $row['volunteer_name'] : '';
                            ?>
                            <tr class="shed-task-row">
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
                <tr class="shed-task-row">
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

            <p style="margin-top: 28px;">
                <button type="submit">Save Project</button>
            </p>
            </form>

            <p>
                <a href="<?php echo esc_url(site_url('/home/members-area/edit-project/')); ?>">&larr; Back to project selector</a>
            </p>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
            var tableBody = document.getElementById('shed-costings-body');
            var addBtn = document.getElementById('shed-add-costing-row');
            var template = document.getElementById('shed-costing-row-template');
            var grandTotalField = document.getElementById('shed-costings-grand-total');
            var tasksBody = document.getElementById('shed-tasks-body');
            var addTaskBtn = document.getElementById('shed-add-task-row');
            var taskTemplate = document.getElementById('shed-task-row-template');

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

            function bindTaskRow(row) {
                if (!row) {
                    return;
                }

                var removeBtn = row.querySelector('.shed-remove-task-row');

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
                            });
                        }
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

            recalcCostings();
        });
    </script>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_edit_project_form', 'shed_edit_project_form_shortcode');
