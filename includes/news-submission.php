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

if (!function_exists('shed_get_full_address')) {
    function shed_get_full_address() {
        return "Brundall Men’s Shed, Broom Boats, Riverside, Brundall NR13 5PX";
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
            "Keep the style consistent but allow small differences so posts feel human-written."
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

        $shed_address = "Brundall Men’s Shed, Broom Boats, Riverside, Brundall NR13 5PX";

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
- avoid clichés and exaggeration
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
        $uploaded_images  = isset($submission['gallery_uploads']) ? $submission['gallery_uploads'] : [];
        $cropped_featured = '';

        if (isset($submission['featured_crop_base64']) && is_string($submission['featured_crop_base64'])) {
            $cropped_featured = $submission['featured_crop_base64'];
        }

        error_log('SHED NEWS NORMALIZED: trace_id=' . $trace_id . ' cropped length=' . strlen($cropped_featured));

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

        $ai_service = new Shed_AI_Rewrite_Service();
        $ai_result = $ai_service->rewrite($raw_title, $raw_story, $contributor, $activity_date);

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

        $post_id = wp_insert_post([
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $post_title,
            'post_content' => $post_content,
        ], true);

        if (is_wp_error($post_id)) {
            error_log('SHED NEWS NORMALIZED: wp_insert_post failed trace_id=' . $trace_id . ' error=' . $post_id->get_error_message());
            return false;
        }

        update_post_meta($post_id, 'shed_contributor_name', $contributor);
        update_post_meta($post_id, 'shed_activity_date', $activity_date);
        update_post_meta($post_id, 'shed_original_submission_text', $raw_story);

        if ($trace_id !== '') {
            update_post_meta($post_id, 'shed_trace_id', $trace_id);
        }

        $image_service = new Shed_Image_Service();

        $featured_attachment_id = $image_service->save_cropped_featured_image($cropped_featured, $post_id);
        error_log('SHED NEWS NORMALIZED: featured attachment result trace_id=' . $trace_id . ' result=' . print_r($featured_attachment_id, true));

        $attachment_ids = $image_service->import_forminator_gallery_images($uploaded_images, $post_id);

        if (count($attachment_ids) > 0) {
            $gallery_html = $image_service->build_gallery_html($attachment_ids);

            if ($gallery_html !== '') {
                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $post_content . $gallery_html,
                ]);
            }
        }

        error_log('SHED NEWS NORMALIZED: completed trace_id=' . $trace_id . ' post_id=' . $post_id);

        return $post_id;
    }
}

if (!function_exists('shed_build_normalized_submission_from_forminator_fields')) {
    function shed_build_normalized_submission_from_forminator_fields($field_data_array, $form_id) {

        $fields = [];

        foreach ($field_data_array as $field) {
            if (isset($field['name'])) {
                $fields[$field['name']] = $field['value'] ?? '';
            }
        }

        $cropped_featured = '';
        if (isset($fields['textarea-2']) && is_string($fields['textarea-2'])) {
            $cropped_featured = $fields['textarea-2'];
        } elseif (isset($_POST['textarea-2']) && is_string($_POST['textarea-2'])) {
            $cropped_featured = wp_unslash($_POST['textarea-2']);
        }

        return [
            'trace_id' => 'legacy_' . wp_generate_password(8, false, false),
            'source' => 'forminator',
            'form_id' => (int) $form_id,
            'title' => $fields['text-1'] ?? '',
            'description' => $fields['textarea-1'] ?? '',
            'member_name' => $fields['text-2'] ?? '',
            'event_date' => $fields['date-1'] ?? '',
            'permission_tick' => $fields['checkbox-1'] ?? '',
            'gallery_uploads' => $fields['upload-1'] ?? '',
            'featured_crop_base64' => $cropped_featured,
            'raw_payload' => $field_data_array,
        ];
    }
}

if (!function_exists('shed_save_news_story_from_forminator')) {
    function shed_save_news_story_from_forminator($field_data_array, $form_id) {

        if ((int) $form_id !== 807) {
            return $field_data_array;
        }

        $submission = shed_build_normalized_submission_from_forminator_fields($field_data_array, $form_id);

        shed_process_news_submission_from_normalized($submission);

        return $field_data_array;
    }
}

add_action('forminator_custom_form_submit_field_data', 'shed_save_news_story_from_forminator', 10, 2);