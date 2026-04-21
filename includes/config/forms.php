<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    'news_submission' => [
        'form_id' => 123, // CHANGE THIS to your real Forminator form ID
        'fields'  => [
            'title'              => 'text-1',
            'description'        => 'textarea-1',
            'member_name'        => 'name-1',
            'event_date'         => 'date-1',
            'gallery_uploads'    => 'upload-1',
            'featured_crop_base64' => 'textarea-featured-crop',
        ],
    ],
];