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

    /**
     * Keep the ACF Author Name field aligned with the contributor name.
     * Use the ACF field key on initial save so ACF creates its reference metadata.
     */
    public static function set_author_name($post_id, $contributor)
    {
        $contributor = sanitize_text_field($contributor);
        if ($contributor === '') {
            return;
        }

        if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields') && function_exists('update_field')) {
            foreach ((array) acf_get_field_groups(['post_id' => $post_id]) as $group) {
                foreach ((array) acf_get_fields($group) as $field) {
                    if (($field['name'] ?? '') === 'author_name' && !empty($field['key'])) {
                        update_field($field['key'], $contributor, $post_id);
                        return;
                    }
                }
            }
        }

        // Store the value even if ACF is temporarily inactive or not assigned to this post.
        update_post_meta($post_id, 'author_name', $contributor);
    }

    public function update_meta($post_id, $contributor, $activity_date, $raw_story, $trace_id = '')
    {
        update_post_meta($post_id, 'shed_contributor_name', $contributor);
        self::set_author_name($post_id, $contributor);
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

/**
 * Repair missing ACF author names on existing pending/draft submissions when
 * an editor opens the post. Never replace a name the reviewer has entered.
 */
add_action('load-post.php', function () {
    if (!current_user_can('edit_posts') || empty($_GET['post'])) {
        return;
    }

    $post_id = absint($_GET['post']);
    if (!$post_id || get_post_type($post_id) !== 'post' || !current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!in_array(get_post_status($post_id), ['pending', 'draft'], true)) {
        return;
    }

    $existing = trim((string) get_post_meta($post_id, 'author_name', true));
    $contributor = trim((string) get_post_meta($post_id, 'shed_contributor_name', true));

    if ($existing === '' && $contributor !== '') {
        Shed_Post_Service::set_author_name($post_id, $contributor);
    }
});
