<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_save_cropped_featured_image')) {
    function shed_save_cropped_featured_image($base64_image, $post_id) {
        if (empty($base64_image)) {
            error_log('SHED NEWS: cropped featured image is empty');
            return false;
        }

        $base64_image = wp_unslash($base64_image);
        $base64_image = trim($base64_image);
        $base64_image = html_entity_decode($base64_image, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (strpos($base64_image, 'data%3Aimage%2F') === 0 || strpos($base64_image, 'data:image/') !== 0) {
            $decoded = rawurldecode($base64_image);
            if (!empty($decoded)) {
                $base64_image = $decoded;
            }
        }

        if (!preg_match('/^data:image\/([a-zA-Z0-9]+);base64,/', $base64_image, $matches)) {
            error_log('SHED NEWS: cropped featured image prefix sample=' . substr($base64_image, 0, 120));
            error_log('SHED NEWS: cropped featured image is not valid base64 image data');
            return false;
        }

        $image_type = strtolower($matches[1]);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($image_type, $allowed, true)) {
            error_log('SHED NEWS: cropped featured image type not allowed: ' . $image_type);
            return false;
        }

        $base64_data = substr($base64_image, strpos($base64_image, ',') + 1);
        $base64_data = preg_replace('/\s+/', '', $base64_data);

        $binary = base64_decode($base64_data);

        if ($binary === false) {
            error_log('SHED NEWS: cropped featured image base64 decode failed');
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $extension = ($image_type === 'jpeg') ? 'jpg' : $image_type;
        $filename = 'shed-featured-' . time() . '.' . $extension;
        $temp_file = wp_tempnam($filename);

        if (!$temp_file) {
            error_log('SHED NEWS: could not create temp file for cropped featured image');
            return false;
        }

        if (file_put_contents($temp_file, $binary) === false) {
            error_log('SHED NEWS: failed writing cropped featured image temp file');
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
            return false;
        }

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $temp_file,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('SHED NEWS: media_handle_sideload failed for cropped featured image: ' . $attachment_id->get_error_message());

            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }

            return false;
        }

        set_post_thumbnail($post_id, $attachment_id);
        error_log('SHED NEWS: cropped featured image saved as attachment ID ' . $attachment_id);

        return $attachment_id;
    }
}

if (!function_exists('shed_import_native_news_featured_image')) {
    function shed_import_native_news_featured_image($field_name, $post_id) {
        if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return false;
        }

        $file = $_FILES[$field_name];

        if (
            empty($file['tmp_name']) ||
            empty($file['name']) ||
            !isset($file['error']) ||
            (int) $file['error'] !== UPLOAD_ERR_OK ||
            !file_exists($file['tmp_name'])
        ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp_copy = wp_tempnam($file['name']);

        if (!$temp_copy || !@copy($file['tmp_name'], $temp_copy)) {
            if ($temp_copy && file_exists($temp_copy)) {
                @unlink($temp_copy);
            }
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

if (!function_exists('shed_import_native_news_video')) {
    function shed_import_native_news_video($field_name, $post_id) {
        if (!function_exists('shed_has_selected_upload') || !shed_has_selected_upload($field_name)) {
            return false;
        }

        $file = $_FILES[$field_name];

        if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $message = function_exists('shed_get_upload_error_message')
                ? shed_get_upload_error_message((int) $file['error'])
                : 'The selected video could not be uploaded. Please try again.';

            return new WP_Error('news_video_upload_failed', $message);
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('news_video_upload_failed', 'The selected video could not be uploaded. Please try again.');
        }

        $allowed_types = function_exists('shed_get_project_upload_mimes')
            ? array_intersect_key(shed_get_project_upload_mimes(), array_flip(['mp4', 'm4v', 'mov', 'webm', 'ogv']))
            : [
                'mp4'  => 'video/mp4',
                'm4v'  => 'video/x-m4v',
                'mov'  => 'video/quicktime',
                'webm' => 'video/webm',
                'ogv'  => 'video/ogg',
            ];

        $file_check = wp_check_filetype($file['name'], $allowed_types);

        if (empty($file_check['ext']) || empty($allowed_types[$file_check['ext']])) {
            return new WP_Error('invalid_news_video', 'Please upload a video file.');
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
            return new WP_Error('news_video_upload_failed', $uploaded['error']);
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

        update_post_meta($post_id, 'shed_news_video_attachment_id', $attachment_id);

        $video_url = wp_get_attachment_url($attachment_id);

        if ($video_url) {
            update_post_meta($post_id, 'shed_news_video_url', esc_url_raw($video_url));
        }

        return $attachment_id;
    }
}

if (!function_exists('shed_get_full_address')) {
    function shed_get_full_address() {
        return "Brundall Menâ€™s Shed, Broom Boats, Riverside, Brundall NR13 5PX";
    }
}

if (!function_exists('shed_is_shed_event')) {
    function shed_is_shed_event($raw_title, $raw_story) {
        $text = strtolower($raw_title . ' ' . $raw_story);

        if (strpos($text, 'brooms boats') !== false || strpos($text, 'broom boats') !== false) {
            return false;
        }

        if (strpos($text, 'shed') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('shed_parse_uk_date')) {
    function shed_parse_uk_date($date_str) {
        if (!$date_str) {
            return false;
        }

        $date_str = trim($date_str);
        $formats = ['d/m/Y', 'd/m/y', 'm/d/Y', 'm/d/y'];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $date_str);
            if ($dt instanceof DateTime) {
                $errors = DateTime::getLastErrors();
                if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
                    continue;
                }

                $dt->setTime(0, 0, 0);
                return $dt;
            }
        }

        return false;
    }
}

if (!function_exists('shed_get_date_phrase_instruction')) {
    function shed_get_date_phrase_instruction($date_str) {
        $event = shed_parse_uk_date($date_str);

        if (!$event) {
            return "If no reliable date is available, use neutral natural phrasing and avoid time-specific claims.";
        }

        $today = new DateTime('today');
        $diff_days = (int) $today->diff($event)->format('%r%a');

        if ($diff_days === 0) {
            return "This event is today. You MUST use present-day phrasing such as 'Today at the Shed'. Do not describe it as if it has already happened in the distant past.";
        }

        if ($diff_days === -1) {
            return "This event was yesterday. Use past tense. Prefer phrasing like 'Yesterday at the Shed'.";
        }

        if ($diff_days > -7 && $diff_days < 0) {
            return "This event happened within the last week. You MUST use past tense. Prefer phrasing like 'Earlier this week' or 'On Tuesday'.";
        }

        if ($diff_days <= -7 && $diff_days >= -21) {
            return "This event happened within the last three weeks. You MUST use past tense. Prefer phrasing like 'Last week' or 'Earlier this month'.";
        }

        if ($diff_days < -21) {
            return "This event is in the past. You MUST use past tense. Prefer natural phrasing like 'Recently at the Shed' or 'Earlier this year'.";
        }

        if ($diff_days === 1) {
            return "This event is tomorrow. You MUST use future tense throughout, such as 'Tomorrow at the Shed' or 'We will be...'. Do NOT describe it as if it has already happened.";
        }

        if ($diff_days > 1 && $diff_days < 7) {
            return "This event is within the next week. You MUST use future tense throughout, such as 'Later this week', 'This Friday', or 'We will be...'. Do NOT describe it as if it has already happened.";
        }

        if ($diff_days >= 7 && $diff_days <= 21) {
            return "This event is within the next three weeks. You MUST use future tense throughout, such as 'Next week', 'Later this month', or 'We will be...'. Do NOT describe it as if it has already happened.";
        }

        return "This event is in the future. You MUST use future tense throughout, such as 'In the coming weeks' or 'We will be...'. Do NOT describe it as if it has already happened.";
    }
}

if (!function_exists('shed_get_style_variation_instruction')) {
    function shed_get_style_variation_instruction() {
        $options = [
            "Vary the opening sentence slightly so posts do not all begin the same way.",
            "Sometimes begin with the activity, sometimes the turnout, sometimes the outcome.",
            "Use natural variation in sentence rhythm while keeping a consistent tone.",
            "Keep the style consistent but allow small differences so posts feel human-written.",
        ];

        return $options[array_rand($options)];
    }
}

if (!function_exists('shed_ai_rewrite_story')) {
    function shed_ai_rewrite_story($raw_title, $raw_story, $contributor = '', $activity_date = '') {
        if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
            error_log('SHED NEWS AI: OPENAI_API_KEY is not defined');
            return false;
        }

        $date_instruction = shed_get_date_phrase_instruction($activity_date);
        $variation_instruction = shed_get_style_variation_instruction();

        $text = strtolower($raw_title . ' ' . $raw_story);

        $is_external_venue = (
            strpos($text, 'brooms boats') !== false ||
            strpos($text, 'broom boats') !== false
        );

        $is_shed_event = (!$is_external_venue && strpos($text, 'shed') !== false);

        $shed_address = "Brundall Menâ€™s Shed, Broom Boats, Riverside, Brundall NR13 5PX";

        $location_block = "\n\nLocation guidance:\n";

        if ($is_shed_event) {
            $location_block .= "- This event takes place at the Shed.\n";
            $location_block .= "- Include the full address once in a natural way: " . $shed_address . "\n";
            $location_block .= "- Do not repeat the address more than once.\n";
        } else {
            $location_block .= "- This event may take place at a venue other than the Shed.\n";
            $location_block .= "- Use the venue mentioned in the submission if present.\n";
            $location_block .= "- Do not insert the Shed address unless clearly appropriate.\n";
        }

        $system_prompt = "You are writing short website posts for Brundall Men's Shed.

House style:
- plain English
- friendly, local, community tone
- warm and natural, not formal
- lightly narrative
- understated but with a sense of activity and purpose
- avoid hype, but do not sound dry
- no corporate language
- no invented details
- keep it concise
- usually 80 to 140 words
- output a short title and two short paragraphs

Writing style:
- describe actions and outcomes rather than merely listing facts
- it is acceptable to slightly enhance phrasing for readability, but never invent facts, atmosphere, or details that were not provided
- do not add scene-setting such as weather, crowd mood, or emotional reactions unless explicitly mentioned
- prefer practical wording over expressive or promotional language
- avoid clichÃ©s and exaggeration
- do not use quotation marks around the title
- mention thanks only if clearly supported by the submission

Date guidance:
" . $date_instruction . "

Variation guidance:
" . $variation_instruction . "

Rules:
- Use only the facts provided
- If details are unclear, keep the wording general rather than inventing specifics
- If the date indicates a future event, do not write in past tense under any circumstances"
        . $location_block . "

Return valid JSON only with keys: title, paragraph1, paragraph2";

        $user_prompt = "Rewrite this member submission into house style.

Make the writing flow naturally as a short account of what happened or what will happen, depending on the date.

Submission title: " . $raw_title . "
Submission text: " . $raw_story . "
Contributor: " . $contributor . "
Date of event: " . $activity_date;

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'temperature' => 0.45,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $system_prompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $user_prompt,
                    ],
                ],
            ]),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            error_log('SHED NEWS AI: request failed: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            error_log('SHED NEWS AI: bad response code ' . $code . ' body=' . $body);
            return false;
        }

        $json = json_decode($body, true);

        if (!is_array($json) || empty($json['choices'][0]['message']['content'])) {
            error_log('SHED NEWS AI: unexpected response body=' . $body);
            return false;
        }

        $content = $json['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            error_log('SHED NEWS AI: could not parse JSON content=' . $content);
            return false;
        }

        return [
            'title'      => isset($parsed['title']) ? sanitize_text_field($parsed['title']) : '',
            'paragraph1' => isset($parsed['paragraph1']) ? sanitize_text_field($parsed['paragraph1']) : '',
            'paragraph2' => isset($parsed['paragraph2']) ? sanitize_text_field($parsed['paragraph2']) : '',
        ];
    }
}

if (!function_exists('shed_process_news_submission_from_normalized')) {
    function shed_process_news_submission_from_normalized(array $submission) {
        $trace_id = isset($submission['trace_id']) ? sanitize_text_field($submission['trace_id']) : '';

        $raw_title        = isset($submission['title']) ? sanitize_text_field($submission['title']) : '';
        $raw_story        = isset($submission['description']) ? sanitize_textarea_field($submission['description']) : '';
        $contributor      = isset($submission['member_name']) ? sanitize_text_field($submission['member_name']) : '';
        $activity_date    = isset($submission['event_date']) ? sanitize_text_field($submission['event_date']) : '';
        $permission_tick  = isset($submission['permission_tick']) ? $submission['permission_tick'] : '';
        $native_gallery_files = $submission['gallery_files'] ?? null;
        $skip_ai_rewrite = !empty($submission['skip_ai_rewrite']);
        $cropped_featured = '';

        if (isset($submission['featured_crop_base64']) && is_string($submission['featured_crop_base64'])) {
            $cropped_featured = $submission['featured_crop_base64'];
        }

        if ($raw_story === '') {
            error_log('SHED NEWS NORMALIZED: missing story text, aborting trace_id=' . $trace_id);
            return false;
        }

        if (empty($permission_tick)) {
            error_log('SHED NEWS NORMALIZED: permission checkbox not ticked, aborting trace_id=' . $trace_id);
            return false;
        }

        $post_title = ($raw_title !== '') ? $raw_title : 'Shed update';
        $post_content = '';

        $ai_result = false;

        if (!$skip_ai_rewrite) {
            $ai_service = new Shed_AI_Rewrite_Service();
            $ai_result = $ai_service->rewrite($raw_title, $raw_story, $contributor, $activity_date);
        }

        if ($ai_result && (!empty($ai_result['title']) || !empty($ai_result['paragraph1']))) {
            if (!empty($ai_result['title'])) {
                $post_title = $ai_result['title'];
            }

            if (!empty($ai_result['paragraph1'])) {
                $post_content .= '<p>' . esc_html($ai_result['paragraph1']) . '</p>';
            }

            if (!empty($ai_result['paragraph2'])) {
                $post_content .= '<p>' . esc_html($ai_result['paragraph2']) . '</p>';
            }
        } else {
            $story_paragraphs = preg_split('/\r\n|\r|\n/', $raw_story);

            foreach ($story_paragraphs as $para) {
                $para = trim($para);
                if ($para !== '') {
                    $post_content .= '<p>' . esc_html($para) . '</p>';
                }
            }
        }

        if ($contributor !== '') {
            $post_content .= '<p><em>Submitted by ' . esc_html($contributor) . '</em></p>';
        }

        $post_service = new Shed_Post_Service();
        $post_id = $post_service->create_draft($post_title, $post_content);

        if (is_wp_error($post_id)) {
            error_log('SHED NEWS NORMALIZED: wp_insert_post failed trace_id=' . $trace_id . ' error=' . $post_id->get_error_message());
            return false;
        }

        $post_service->update_meta($post_id, $contributor, $activity_date, $raw_story, $trace_id);

        if ($trace_id !== '') {
            update_post_meta($post_id, 'shed_trace_id', $trace_id);
        }

        $image_service = new Shed_Image_Service();

        $featured_attachment_id = $image_service->save_cropped_featured_image($cropped_featured, $post_id);

        if (!$featured_attachment_id) {
            $featured_attachment_id = shed_import_native_news_featured_image('featured_image', $post_id);
        }

        $attachment_ids = $image_service->import_native_gallery_images($native_gallery_files, $post_id);
        $video_attachment_id = shed_import_native_news_video('post_video', $post_id);

        if (is_wp_error($video_attachment_id)) {
            error_log('SHED NEWS NORMALIZED: video upload failed trace_id=' . $trace_id . ' error=' . $video_attachment_id->get_error_message());
            return $video_attachment_id;
        }

        $video_html = '';

        if ($video_attachment_id) {
            $video_url = wp_get_attachment_url($video_attachment_id);

            if ($video_url) {
                $video_html = '<figure class="wp-block-video"><video controls src="' . esc_url($video_url) . '"></video></figure>';
            }
        }

        if (count($attachment_ids) > 0 || $video_html !== '') {
            $gallery_html = $image_service->build_gallery_html($attachment_ids);
            $post_service->append_content($post_id, $post_content . $gallery_html . $video_html);
        }

        error_log('SHED NEWS NORMALIZED: completed trace_id=' . $trace_id . ' post_id=' . $post_id);

        return $post_id;
    }
}

if (!function_exists('shed_get_native_news_submission_defaults')) {
    function shed_get_native_news_submission_defaults() {
        return [
            'title'                => '',
            'description'          => '',
            'member_name'          => '',
            'event_date'           => '',
            'permission_tick'      => '',
            'featured_crop_base64' => '',
        ];
    }
}

if (!function_exists('shed_normalize_native_news_submission')) {
    function shed_normalize_native_news_submission($raw) {
        return [
            'trace_id'             => 'native_' . wp_generate_password(8, false, false),
            'source'               => 'native',
            'title'                => sanitize_text_field(isset($raw['title']) ? wp_unslash((string) $raw['title']) : ''),
            'description'          => sanitize_textarea_field(isset($raw['description']) ? wp_unslash((string) $raw['description']) : ''),
            'member_name'          => sanitize_text_field(isset($raw['member_name']) ? wp_unslash((string) $raw['member_name']) : ''),
            'event_date'           => sanitize_text_field(isset($raw['event_date']) ? wp_unslash((string) $raw['event_date']) : ''),
            'permission_tick'      => !empty($raw['permission_tick']) ? 'yes' : '',
            'featured_crop_base64' => isset($raw['featured_crop_base64']) ? wp_unslash((string) $raw['featured_crop_base64']) : '',
            'gallery_files'        => $_FILES['gallery_images'] ?? null,
        ];
    }
}

if (!function_exists('shed_handle_native_news_submission')) {
    function shed_handle_native_news_submission() {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            wp_doing_ajax() ||
            !isset($_POST['shed_news_submission_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_news_submission_nonce']), 'shed_news_submission')
        ) {
            return null;
        }

        $submission = shed_normalize_native_news_submission($_POST);
        $result = shed_process_news_submission_from_normalized($submission);

        return [
            'submission' => $submission,
            'result'     => $result,
        ];
    }
}

if (!function_exists('shed_ajax_submit_native_news_submission')) {
    function shed_ajax_submit_native_news_submission() {
        if (
            !isset($_POST['shed_news_submission_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_news_submission_nonce']), 'shed_news_submission')
        ) {
            wp_send_json_error(['message' => 'The submission security check failed. Please refresh the page and try again.'], 403);
        }

        $submission = shed_normalize_native_news_submission($_POST);
        $submission['gallery_files'] = null;
        $submission['featured_crop_base64'] = '';
        $submission['skip_ai_rewrite'] = true;

        $saved_files = $_FILES;
        $_FILES = [];
        $result = shed_process_news_submission_from_normalized($submission);
        $_FILES = $saved_files;

        if (!$result || is_wp_error($result)) {
            $message = is_wp_error($result)
                ? $result->get_error_message()
                : 'The submission could not be saved. Please check the required fields and try again.';

            wp_send_json_error(['message' => $message], 500);
        }

        wp_send_json_success([
            'post_id' => (int) $result,
            'message' => 'Your post submission has been received and saved as a draft for review.',
        ]);
    }
}

add_action('wp_ajax_shed_submit_news_post', 'shed_ajax_submit_native_news_submission');
add_action('wp_ajax_nopriv_shed_submit_news_post', 'shed_ajax_submit_native_news_submission');

if (!function_exists('shed_ajax_upload_native_news_featured_image')) {
    function shed_ajax_upload_native_news_featured_image() {
        if (
            !isset($_POST['shed_news_submission_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_news_submission_nonce']), 'shed_news_submission')
        ) {
            wp_send_json_error(['message' => 'The featured image upload security check failed. Please refresh the page and try again.'], 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'post') {
            wp_send_json_error(['message' => 'The draft post could not be found for this featured image upload.'], 404);
        }

        if (empty($_FILES['featured_image']) || !is_array($_FILES['featured_image'])) {
            wp_send_json_error(['message' => 'No featured image was received.'], 400);
        }

        $attachment_id = shed_import_native_news_featured_image('featured_image', $post_id);

        if (!$attachment_id || is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'The featured image could not be uploaded.'], 500);
        }

        wp_send_json_success(['attachment_id' => (int) $attachment_id]);
    }
}

add_action('wp_ajax_shed_upload_news_featured_image', 'shed_ajax_upload_native_news_featured_image');
add_action('wp_ajax_nopriv_shed_upload_news_featured_image', 'shed_ajax_upload_native_news_featured_image');

if (!function_exists('shed_ajax_upload_native_news_video')) {
    function shed_ajax_upload_native_news_video() {
        if (
            !isset($_POST['shed_news_submission_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_news_submission_nonce']), 'shed_news_submission')
        ) {
            wp_send_json_error(['message' => 'The video upload security check failed. Please refresh the page and try again.'], 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'post') {
            wp_send_json_error(['message' => 'The draft post could not be found for this video upload.'], 404);
        }

        if (empty($_FILES['post_video']) || !is_array($_FILES['post_video'])) {
            wp_send_json_error(['message' => 'No video was received.'], 400);
        }

        $attachment_id = shed_import_native_news_video('post_video', $post_id);

        if (!$attachment_id || is_wp_error($attachment_id)) {
            $message = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'The selected video could not be uploaded.';
            wp_send_json_error(['message' => $message], 500);
        }

        $video_url = wp_get_attachment_url($attachment_id);

        if ($video_url) {
            $post_content = (string) get_post_field('post_content', $post_id);
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $post_content . '<figure class="wp-block-video"><video controls src="' . esc_url($video_url) . '"></video></figure>',
            ]);
        }

        wp_send_json_success(['attachment_id' => (int) $attachment_id]);
    }
}

add_action('wp_ajax_shed_upload_news_video', 'shed_ajax_upload_native_news_video');
add_action('wp_ajax_nopriv_shed_upload_news_video', 'shed_ajax_upload_native_news_video');

if (!function_exists('shed_ajax_upload_native_news_gallery_image')) {
    function shed_ajax_upload_native_news_gallery_image() {
        if (
            !isset($_POST['shed_news_submission_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['shed_news_submission_nonce']), 'shed_news_submission')
        ) {
            wp_send_json_error(['message' => 'The image upload security check failed. Please refresh the page and try again.'], 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id) !== 'post') {
            wp_send_json_error(['message' => 'The draft post could not be found for this image upload.'], 404);
        }

        $post_status = get_post_status($post_id);

        if (!in_array($post_status, ['draft', 'pending'], true)) {
            wp_send_json_error(['message' => 'Images can only be added to a draft submission.'], 403);
        }

        if (empty($_FILES['gallery_image']) || !is_array($_FILES['gallery_image'])) {
            wp_send_json_error(['message' => 'No image was received.'], 400);
        }

        $image_service = new Shed_Image_Service();
        $attachment_ids = $image_service->import_native_gallery_images($_FILES['gallery_image'], $post_id);

        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => 'The selected image could not be uploaded.'], 500);
        }

        $gallery_html = $image_service->build_gallery_html($attachment_ids);
        $post_content = (string) get_post_field('post_content', $post_id);

        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $post_content . $gallery_html,
        ]);

        wp_send_json_success([
            'attachment_id' => (int) $attachment_ids[0],
        ]);
    }
}

add_action('wp_ajax_shed_upload_news_gallery_image', 'shed_ajax_upload_native_news_gallery_image');
add_action('wp_ajax_nopriv_shed_upload_news_gallery_image', 'shed_ajax_upload_native_news_gallery_image');

if (!function_exists('shed_enqueue_native_news_submission_assets')) {
    function shed_enqueue_native_news_submission_assets($force = false) {
        global $post;

        if (!$force && !is_a($post, 'WP_Post')) {
            return;
        }

        $content = is_a($post, 'WP_Post') ? (string) $post->post_content : '';
        if (!$force && !has_shortcode($content, 'shed_news_submission_form')) {
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

add_action('wp_enqueue_scripts', 'shed_enqueue_native_news_submission_assets');

if (!function_exists('shed_render_native_news_submission_form')) {
    function shed_render_native_news_submission_form() {
        shed_enqueue_native_news_submission_assets(true);

        $handled = shed_handle_native_news_submission();
        $values = shed_get_native_news_submission_defaults();
        $message = '';
        $message_type = '';

        if ($handled) {
            $values = array_merge($values, $handled['submission']);

            if (!$handled['result'] || is_wp_error($handled['result'])) {
                $message = is_wp_error($handled['result'])
                    ? $handled['result']->get_error_message()
                    : 'The submission could not be saved. Please check the required fields and try again.';
                $message_type = 'error';
            } else {
                $message = 'Your post submission has been received and saved as a draft for review.';
                $message_type = 'success';
                $values = shed_get_native_news_submission_defaults();
            }
        }

        static $instance = 0;
        $instance++;
        $form_id = 'shed-native-news-submission-' . $instance;

        ob_start();
        ?>
        <div class="shed-news-submission-wrap">
            <?php if ($message !== '') : ?>
                <div class="shed-news-submission-message shed-news-submission-message-<?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>

            <form id="<?php echo esc_attr($form_id); ?>" class="shed-native-news-submission-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('shed_news_submission', 'shed_news_submission_nonce'); ?>
                <input type="hidden" name="featured_crop_base64" value="">

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-title"><strong>Title</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-title" type="text" name="title" value="<?php echo esc_attr($values['title']); ?>">
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-description"><strong>What happened?</strong></label>
                    <textarea id="<?php echo esc_attr($form_id); ?>-description" name="description" rows="8" required><?php echo esc_textarea($values['description']); ?></textarea>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-member-name"><strong>Your name</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-member-name" type="text" name="member_name" value="<?php echo esc_attr($values['member_name']); ?>">
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-event-date"><strong>Date of event</strong></label>
                    <input id="<?php echo esc_attr($form_id); ?>-event-date" type="text" name="event_date" value="<?php echo esc_attr($values['event_date']); ?>" placeholder="dd/mm/yyyy">
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-featured-image"><strong>Featured image</strong> (optional)</label>
                    <input id="<?php echo esc_attr($form_id); ?>-featured-image" type="file" name="featured_image" accept="image/*" data-shed-cropper="featured" data-shed-cropper-hidden="input[name='featured_crop_base64']">
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-gallery-images"><strong>Additional images</strong> (optional)</label>
                    <div class="shed-gallery-image-inputs">
                        <input id="<?php echo esc_attr($form_id); ?>-gallery-images" type="file" name="gallery_images[]" accept="image/*" data-shed-gallery-image>
                    </div>
                    <button type="button" class="shed-add-gallery-image">Add another image</button>
                </div>

                <div class="shed-form-field">
                    <label for="<?php echo esc_attr($form_id); ?>-post-video"><strong>Video</strong> (optional)</label>
                    <input id="<?php echo esc_attr($form_id); ?>-post-video" type="file" name="post_video" accept="video/*">
                </div>

                <div class="shed-form-field">
                    <label class="shed-checkbox-label">
                        <input type="checkbox" name="permission_tick" value="yes" <?php checked($values['permission_tick'], 'yes'); ?> required>
                        I confirm these details and images can be used on the website.
                    </label>
                </div>

                <div class="shed-form-actions">
                    <button type="submit">Submit post</button>
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
            .shed-news-submission-wrap { max-width: 920px; }
            .shed-news-submission-message { margin-bottom: 16px; padding: 12px 14px; border-radius: 8px; }
            .shed-news-submission-message-success { background: #edf8ed; color: #136a13; border: 1px solid #9fce9f; }
            .shed-news-submission-message-error { background: #fff1f1; color: #8a1f1f; border: 1px solid #e1a3a3; }
            .shed-native-news-submission-form .shed-form-field { margin-bottom: 18px; }
            .shed-native-news-submission-form label { display: block; margin-bottom: 6px; }
            .shed-native-news-submission-form input[type="text"],
            .shed-native-news-submission-form textarea,
            .shed-native-news-submission-form input[type="file"] { width: 100%; padding: 10px 12px; box-sizing: border-box; }
            .shed-native-news-submission-form input[type="file"] { padding: 8px 0; }
            .shed-gallery-image-inputs { display: grid; gap: 8px; }
            .shed-add-gallery-image { margin-top: 8px; padding: 8px 12px; border: 1px solid #bbb; border-radius: 6px; background: #fff; cursor: pointer; }
            .shed-checkbox-label { display: flex; gap: 10px; align-items: flex-start; }
            .shed-checkbox-label input[type="checkbox"] { width: auto; margin-top: 3px; }
            .shed-form-actions button { padding: 12px 18px; border: 0; border-radius: 8px; background: #0a7f00; color: #fff; cursor: pointer; }
        </style>

        <?php if (function_exists('shed_render_cropper_assets')) { shed_render_cropper_assets(); } ?>

        <script>
        (function () {
        function initShedNewsCropper() {
            var form = document.getElementById(<?php echo wp_json_encode($form_id); ?>);
            if (!form) {
                return;
            }

            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var featuredInput = form.querySelector('input[name="featured_image"]');
            var galleryInputsWrap = form.querySelector('.shed-gallery-image-inputs');
            var addGalleryButton = form.querySelector('.shed-add-gallery-image');
            var videoInput = form.querySelector('input[name="post_video"]');
            var hiddenInput = form.querySelector('input[name="featured_crop_base64"]');
            var modal = document.getElementById('shed-cropper-modal');
            var cropperImage = document.getElementById('shed-cropper-image');
            var applyBtn = document.getElementById('shed-cropper-apply');
            var cancelBtn = document.getElementById('shed-cropper-cancel');
            var submitButton = form.querySelector('button[type="submit"]');
            var cropper = null;
            var objectUrl = null;
            var cropConfirmed = false;
            var pendingFile = null;
            var cropperLoading = false;
            var galleryPrepared = false;
            var maxGalleryImages = 8;
            var maxGalleryEdge = 1200;
            var galleryJpegQuality = 0.76;
            var ajaxSubmitting = false;

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

            function loadCropper(callback) {
                if (typeof Cropper !== 'undefined') {
                    callback();
                    return;
                }

                if (cropperLoading) {
                    window.setTimeout(function () {
                        loadCropper(callback);
                    }, 100);
                    return;
                }

                cropperLoading = true;
                var script = document.createElement('script');
                script.src = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js';
                script.onload = function () {
                    cropperLoading = false;
                    callback();
                };
                script.onerror = function () {
                    cropperLoading = false;
                    alert('The image cropper could not be loaded. Please check the browser console or network connection.');
                };
                document.head.appendChild(script);
            }

            function startCropper(file) {
                if (!file || !cropperImage) {
                    return;
                }

                destroyCropper();
                objectUrl = URL.createObjectURL(file);
                cropperImage.onload = function () {
                    loadCropper(function () {
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
                    });
                };
                cropperImage.src = objectUrl;
                openModal();
            }

            function canPrepareGalleryImages() {
                return !!(
                    window.File &&
                    window.FileReader &&
                    window.Image &&
                    document.createElement('canvas').getContext
                );
            }

            function getGalleryInputs() {
                if (!galleryInputsWrap) {
                    return [];
                }

                return Array.prototype.slice.call(galleryInputsWrap.querySelectorAll('input[data-shed-gallery-image]'));
            }

            function getSelectedGalleryFiles() {
                var files = [];

                getGalleryInputs().forEach(function (input) {
                    if (input.files && input.files[0]) {
                        files.push(input.files[0]);
                    }
                });

                return files;
            }

            function resizeGalleryFile(file) {
                return new Promise(function (resolve) {
                    if (!file || !file.type || !file.type.match(/^image\//)) {
                        resolve(file);
                        return;
                    }

                    var reader = new FileReader();

                    reader.onload = function () {
                        var image = new Image();

                        image.onload = function () {
                            var width = image.naturalWidth || image.width;
                            var height = image.naturalHeight || image.height;
                            var largestEdge = Math.max(width, height);

                            if (!width || !height || (largestEdge <= maxGalleryEdge && file.size <= 750000)) {
                                resolve(file);
                                return;
                            }

                            var scale = Math.min(1, maxGalleryEdge / largestEdge);
                            var canvas = document.createElement('canvas');
                            canvas.width = Math.max(1, Math.round(width * scale));
                            canvas.height = Math.max(1, Math.round(height * scale));

                            var context = canvas.getContext('2d');
                            context.drawImage(image, 0, 0, canvas.width, canvas.height);

                            canvas.toBlob(function (blob) {
                                if (!blob) {
                                    resolve(file);
                                    return;
                                }

                                var originalName = file.name || 'additional-image';
                                var resizedName = originalName.replace(/\.[^.]+$/, '') + '.jpg';
                                resolve(new File([blob], resizedName, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                }));
                            }, 'image/jpeg', galleryJpegQuality);
                        };

                        image.onerror = function () {
                            resolve(file);
                        };

                        image.src = reader.result;
                    };

                    reader.onerror = function () {
                        resolve(file);
                    };

                    reader.readAsDataURL(file);
                });
            }

            function prepareGalleryImages() {
                var selectedGalleryFiles = getSelectedGalleryFiles();

                if (selectedGalleryFiles.length === 0) {
                    return Promise.resolve([]);
                }

                var files = selectedGalleryFiles.slice();

                if (!canPrepareGalleryImages()) {
                    return Promise.resolve(files);
                }

                return Promise.all(files.map(resizeGalleryFile));
            }

            function setSubmitState(disabled, text) {
                if (!submitButton) {
                    return;
                }

                submitButton.disabled = disabled;

                if (text) {
                    submitButton.textContent = text;
                }
            }

            function showSubmissionMessage(type, message) {
                var existingMessage = form.parentNode.querySelector('.shed-news-submission-message');
                var messageEl = existingMessage || document.createElement('div');

                messageEl.className = 'shed-news-submission-message shed-news-submission-message-' + type;
                messageEl.textContent = message;

                if (!existingMessage) {
                    form.parentNode.insertBefore(messageEl, form);
                }

                messageEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function fetchJson(url, options, stepName) {
                return fetch(url, options).then(function (response) {
                    return response.text().then(function (text) {
                        var payload = null;

                        try {
                            payload = text ? JSON.parse(text) : null;
                        } catch (error) {
                            var message = 'The server returned an HTML error while ' + stepName + '.';

                            if (response.status) {
                                message += ' HTTP status: ' + response.status + '.';
                            }

                            throw new Error(message);
                        }

                        if (!response.ok) {
                            throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'The server could not complete the request while ' + stepName + '.');
                        }

                        return payload;
                    });
                });
            }

            function dataUrlToFile(dataUrl, filename) {
                var parts = dataUrl.split(',');
                var match = parts[0].match(/data:([^;]+);base64/);
                var mimeType = match ? match[1] : 'image/jpeg';
                var binary = atob(parts[1] || '');
                var length = binary.length;
                var bytes = new Uint8Array(length);

                for (var index = 0; index < length; index++) {
                    bytes[index] = binary.charCodeAt(index);
                }

                return new File([bytes], filename, {
                    type: mimeType,
                    lastModified: Date.now()
                });
            }

            function getFeaturedUploadFile() {
                if (hiddenInput && hiddenInput.value && hiddenInput.value.indexOf('data:image/') === 0 && window.File && window.Uint8Array) {
                    return dataUrlToFile(hiddenInput.value, 'featured-image.jpg');
                }

                if (featuredInput && featuredInput.files && featuredInput.files[0]) {
                    return featuredInput.files[0];
                }

                return null;
            }

            function createDraftWithoutGallery() {
                var formData = new FormData(form);

                formData.delete('gallery_images[]');
                formData.delete('gallery_images');
                formData.delete('featured_image');
                formData.delete('featured_crop_base64');
                formData.delete('post_video');

                formData.append('action', 'shed_submit_news_post');

                return fetchJson(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }, 'saving the post').then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.post_id) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'The submission could not be saved.');
                    }

                    return payload.data.post_id;
                });
            }

            function uploadFeaturedImage(postId) {
                var file = getFeaturedUploadFile();
                var nonce = form.querySelector('input[name="shed_news_submission_nonce"]');
                var formData = new FormData();

                if (!file) {
                    return Promise.resolve();
                }

                formData.append('action', 'shed_upload_news_featured_image');
                formData.append('post_id', postId);

                if (nonce) {
                    formData.append('shed_news_submission_nonce', nonce.value);
                }

                formData.append('featured_image', file);
                setSubmitState(true, 'Uploading featured image...');

                return fetchJson(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }, 'uploading the featured image').then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'The featured image could not be uploaded.');
                    }
                });
            }

            function uploadVideo(postId) {
                var file = videoInput && videoInput.files ? videoInput.files[0] : null;
                var nonce = form.querySelector('input[name="shed_news_submission_nonce"]');
                var formData = new FormData();

                if (!file) {
                    return Promise.resolve();
                }

                formData.append('action', 'shed_upload_news_video');
                formData.append('post_id', postId);

                if (nonce) {
                    formData.append('shed_news_submission_nonce', nonce.value);
                }

                formData.append('post_video', file);
                setSubmitState(true, 'Uploading video...');

                return fetchJson(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }, 'uploading the video').then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'The video could not be uploaded.');
                    }
                });
            }

            function uploadGalleryImage(postId, file, index, total) {
                var formData = new FormData();
                var nonce = form.querySelector('input[name="shed_news_submission_nonce"]');

                formData.append('action', 'shed_upload_news_gallery_image');
                formData.append('post_id', postId);

                if (nonce) {
                    formData.append('shed_news_submission_nonce', nonce.value);
                }

                formData.append('gallery_image', file);
                setSubmitState(true, 'Uploading image ' + (index + 1) + ' of ' + total + '...');

                return fetchJson(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }, 'uploading image ' + (index + 1) + ' of ' + total).then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'One of the images could not be uploaded.');
                    }
                });
            }

            function uploadGalleryImagesSequentially(postId, files) {
                var sequence = Promise.resolve();

                files.forEach(function (file, index) {
                    sequence = sequence.then(function () {
                        return uploadGalleryImage(postId, file, index, files.length);
                    });
                });

                return sequence;
            }

            function submitWithSequentialGalleryUploads() {
                ajaxSubmitting = true;
                setSubmitState(true, 'Preparing images...');

                return prepareGalleryImages()
                    .then(function (preparedFiles) {
                        setSubmitState(true, 'Saving post...');
                        return createDraftWithoutGallery().then(function (postId) {
                            return uploadFeaturedImage(postId)
                                .then(function () {
                                    return uploadVideo(postId);
                                })
                                .then(function () {
                                    return uploadGalleryImagesSequentially(postId, preparedFiles);
                                });
                        });
                    })
                    .then(function () {
                        form.reset();
                        hiddenInput.value = '';
                        cropConfirmed = false;
                        galleryPrepared = false;
                        getGalleryInputs().forEach(function (input, index) {
                            if (index === 0) {
                                input.value = '';
                            } else {
                                input.remove();
                            }
                        });
                        ajaxSubmitting = false;
                        setSubmitState(false, 'Submit post');
                        showSubmissionMessage('success', 'Your post submission has been received and saved as a draft for review.');
                    })
                    .catch(function (error) {
                        ajaxSubmitting = false;
                        setSubmitState(false, 'Submit post');
                        showSubmissionMessage('error', error && error.message ? error.message : 'The submission could not be saved. Please try again.');
                    });
            }

            if (!featuredInput || !hiddenInput || !modal || !cropperImage) {
                return;
            }

            if (galleryInputsWrap) {
                galleryInputsWrap.addEventListener('change', function (event) {
                    if (!event.target || !event.target.matches('input[data-shed-gallery-image]')) {
                        return;
                    }

                    galleryPrepared = false;

                    if (getSelectedGalleryFiles().length > maxGalleryImages) {
                        alert('Please choose no more than ' + maxGalleryImages + ' additional images.');
                        event.target.value = '';
                    }
                });
            }

            if (addGalleryButton && galleryInputsWrap) {
                addGalleryButton.addEventListener('click', function () {
                    if (getGalleryInputs().length >= maxGalleryImages) {
                        alert('Please choose no more than ' + maxGalleryImages + ' additional images.');
                        return;
                    }

                    var input = document.createElement('input');
                    input.type = 'file';
                    input.name = 'gallery_images[]';
                    input.accept = 'image/*';
                    input.setAttribute('data-shed-gallery-image', '');
                    galleryInputsWrap.appendChild(input);
                    input.click();
                });
            }

            featuredInput.addEventListener('change', function (e) {
                cropConfirmed = false;
                hiddenInput.value = '';

                var file = e.target.files && e.target.files[0];
                if (!file) {
                    return;
                }

                if (!file.type.match(/^image\//)) {
                    alert('Please choose an image file.');
                    featuredInput.value = '';
                    return;
                }

                pendingFile = file;
                startCropper(file);
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

                    hiddenInput.value = canvas.toDataURL('image/jpeg', 0.9);
                    cropConfirmed = true;
                    closeModal();
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    cropConfirmed = false;
                    hiddenInput.value = '';
                    featuredInput.value = '';
                    destroyCropper();
                    closeModal();
                });
            }

            form.addEventListener('submit', function (e) {
                if (ajaxSubmitting) {
                    e.preventDefault();
                    return;
                }

                if (featuredInput.files.length > 0 && !cropConfirmed) {
                    e.preventDefault();
                    alert('Please crop the featured image before submitting.');
                    if (pendingFile) {
                        startCropper(pendingFile);
                    } else {
                        openModal();
                    }
                    return;
                }

                var selectedGalleryFiles = getSelectedGalleryFiles();

                if (!galleryPrepared && selectedGalleryFiles.length > 0) {
                    e.preventDefault();

                    if (selectedGalleryFiles.length > maxGalleryImages) {
                        alert('Please choose no more than ' + maxGalleryImages + ' additional images.');
                        return;
                    }

                    galleryPrepared = true;
                    submitWithSequentialGalleryUploads();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initShedNewsCropper);
        } else {
            initShedNewsCropper();
        }
        }());
        </script>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('shed_replace_legacy_news_submission_shortcode')) {
    function shed_replace_legacy_news_submission_shortcode($content) {
        if (is_admin() || strpos($content, 'forminator_form') === false) {
            return $content;
        }

        return preg_replace(
            '/\[forminator_form\s+id=(["\'])807\1[^\]]*\]/i',
            '[shed_news_submission_form]',
            $content
        );
    }
}

add_filter('the_content', 'shed_replace_legacy_news_submission_shortcode', 5);

add_shortcode('shed_news_submission_form', 'shed_render_native_news_submission_form');
