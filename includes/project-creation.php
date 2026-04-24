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

        if (shed_get_project_type($source_idea_id) !== 'idea') {
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

        if (!$idea_post || $idea_post->post_type !== 'project' || shed_get_project_type($source_idea_id) !== 'idea') {
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

if (!function_exists('shed_get_native_create_project_defaults')) {
    function shed_get_native_create_project_defaults() {
        $prefill = shed_get_source_idea_prefill_data(shed_get_source_idea_id_from_request());

        return [
            'project_type'            => $prefill['project_type'] ?? 'project',
            'project_name'            => $prefill['project_name'] ?? '',
            'description'             => $prefill['description'] ?? '',
            'project_contact'         => $prefill['project_contact'] ?? '',
            'hours_required'          => '',
            'completion_target_date'  => '',
            'event_date'              => '',
            'event_location'          => '',
            'event_status'            => 'open',
            'source_idea_id'          => $prefill['source_idea_id'] ?? 0,
            'project_featured_crop_base64' => '',
        ];
    }
}

if (!function_exists('shed_normalize_project_create_submission')) {
    function shed_normalize_project_create_submission($raw) {
        $project_type = sanitize_key((string) ($raw['project_type'] ?? 'project'));

        if (!in_array($project_type, ['project', 'idea', 'event'], true)) {
            $project_type = 'project';
        }

        $event_status = sanitize_key((string) ($raw['event_status'] ?? 'open'));
        if (!in_array($event_status, ['open', 'ended'], true)) {
            $event_status = 'open';
        }

        return [
            'project_type'            => $project_type,
            'project_name'            => sanitize_text_field((string) ($raw['project_name'] ?? '')),
            'description'             => sanitize_textarea_field((string) ($raw['description'] ?? '')),
            'project_contact'         => sanitize_text_field((string) ($raw['project_contact'] ?? '')),
            'hours_required'          => max(0, intval($raw['hours_required'] ?? 0)),
            'completion_target_date'  => shed_normalize_date_input($raw['completion_target_date'] ?? ''),
            'event_date'              => shed_normalize_date_input($raw['event_date'] ?? ''),
            'event_location'          => sanitize_text_field((string) ($raw['event_location'] ?? '')),
            'event_status'            => $event_status,
            'source_idea_id'          => absint($raw['source_idea_id'] ?? 0),
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

        if ($submission['project_contact'] === '') {
            return new WP_Error('missing_contact', 'Please enter the main contact.');
        }

        if ($project_type === 'project' && $submission['hours_required'] <= 0) {
            return new WP_Error('missing_hours', 'Please enter the hours required for this project.');
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
        update_post_meta($post_id, 'timestamp', current_time('mysql'));

        if ($project_type === 'project') {
            update_post_meta($post_id, 'project_ref', shed_get_next_project_ref());
            update_post_meta($post_id, 'hours_required', $submission['hours_required']);
            update_post_meta($post_id, 'hours_committed', 0);
            update_post_meta($post_id, 'volunteer_status', 'seeking_volunteers');
            update_post_meta($post_id, 'project_stage', 'quote');
            update_post_meta($post_id, 'completion_target_date', $submission['completion_target_date']);

            if ($source_idea_id) {
                update_post_meta($post_id, 'source_idea_id', $source_idea_id);
            }
        } elseif ($project_type === 'event') {
            update_post_meta($post_id, 'event_date', $submission['event_date']);
            update_post_meta($post_id, 'event_location', $submission['event_location']);
            update_post_meta($post_id, 'event_status', $submission['event_status']);
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
    function shed_enqueue_native_project_create_assets() {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $content = (string) $post->post_content;
        $has_native_shortcode = has_shortcode($content, 'shed_create_project_form');
        $has_legacy_form_shortcode = (
            strpos($content, '[forminator_form id="683"]') !== false ||
            strpos($content, "[forminator_form id='683']") !== false
        );

        if (!$has_native_shortcode && !$has_legacy_form_shortcode) {
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
                $message = 'Project created successfully.';
                $message_type = 'success';
                $values = shed_get_native_create_project_defaults();
            }
        }

        static $instance = 0;
        $instance++;
        $form_id = 'shed-native-create-project-' . $instance;

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
                    <label><strong>Project type</strong></label>
                    <div class="shed-radio-group">
                        <label><input type="radio" name="project_type" value="project" <?php checked($values['project_type'], 'project'); ?>> Project</label>
                        <label><input type="radio" name="project_type" value="idea" <?php checked($values['project_type'], 'idea'); ?>> Idea</label>
                        <label><input type="radio" name="project_type" value="event" <?php checked($values['project_type'], 'event'); ?>> Event</label>
                    </div>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-title"><strong>Project name</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-title" type="text" name="project_name" value="<?php echo esc_attr($values['project_name']); ?>" required>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-description"><strong>Description</strong></label>
                    <textarea id="<?php echo esc_attr($form_id); ?>-description" name="description" rows="6"><?php echo esc_textarea($values['description']); ?></textarea>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-contact"><strong>Main contact</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-contact" type="text" name="project_contact" value="<?php echo esc_attr($values['project_contact']); ?>" required>
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-hours"><strong>Hours required</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-hours" type="number" min="1" name="hours_required" value="<?php echo esc_attr((string) $values['hours_required']); ?>">
                </div>

                <div class="shed-form-field" data-project-type-group="project">
                    <label for="<?php echo esc_attr($form_id); ?>-target-date"><strong>Completion target date</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-target-date" type="date" name="completion_target_date" value="<?php echo esc_attr($values['completion_target_date']); ?>">
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

                <div class="shed-form-actions">
                    <button type="submit">Create project</button>
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
            .shed-native-create-project-form input[type="number"],
            .shed-native-create-project-form input[type="date"],
            .shed-native-create-project-form textarea,
            .shed-native-create-project-form select { width: 100%; padding: 10px 12px; box-sizing: border-box; }
            .shed-radio-group { display: flex; gap: 20px; flex-wrap: wrap; }
            .shed-radio-group label { display: inline-flex; align-items: center; gap: 8px; }
            .shed-form-actions button { padding: 12px 18px; border: 0; border-radius: 8px; background: #0a7f00; color: #fff; cursor: pointer; }
        </style>

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
            var cropper = null;
            var objectUrl = null;
            var cropConfirmed = false;

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

                form.querySelectorAll('[data-project-type-group="project"]').forEach(function (field) {
                    var visible = projectType === 'project';
                    field.style.display = visible ? '' : 'none';
                    field.querySelectorAll('input, select, textarea').forEach(function (input) {
                        input.disabled = !visible;
                    });
                });

                form.querySelectorAll('[data-project-type-group="event"]').forEach(function (field) {
                    var visible = projectType === 'event';
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
