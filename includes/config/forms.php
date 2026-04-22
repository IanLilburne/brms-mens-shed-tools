<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'news_submission' => [
        'form_id' => 807,
        'fields'  => [
            'title' => 'text-1',
            'description' => 'textarea-1',
            'member_name' => 'text-2',
            'event_date' => 'date-1',
            'permission_tick' => 'checkbox-1',
            'gallery_uploads' => 'upload-1',
            'featured_crop_base64' => 'textarea-2',
        ],
    ],
];