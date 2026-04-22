<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Post_Service
{
    public function create_draft($title, $content)
    {
        $post_id = wp_insert_post([
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return $post_id;
    }

    public function update_meta($post_id, $contributor, $activity_date, $raw_story, $trace_id = '')
    {
        update_post_meta($post_id, 'shed_contributor_name', $contributor);
        update_post_meta($post_id, 'shed_activity_date', $activity_date);
        update_post_meta($post_id, 'shed_original_submission_text', $raw_story);

        if ($trace_id !== '') {
            update_post_meta($post_id, 'shed_trace_id', $trace_id);
        }
    }

    public function append_content($post_id, $content)
    {
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $content,
        ]);
    }
}