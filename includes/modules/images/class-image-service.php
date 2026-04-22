<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Image_Service
{
    public function save_cropped_featured_image($base64_image, $post_id)
    {
        return shed_save_cropped_featured_image($base64_image, $post_id);
    }

    public function import_forminator_gallery_images($uploaded_images, $post_id)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_ids = [];
        $forminator_temp_dir = WP_CONTENT_DIR . '/uploads/forminator/temp/';
        $upload_items = [];

        if (
            is_array($uploaded_images) &&
            isset($uploaded_images['file']) &&
            is_array($uploaded_images['file'])
        ) {
            $upload_items = $uploaded_images['file'];
        }

        foreach ($upload_items as $index => $item) {
            if (empty($item['success']) || empty($item['file_name'])) {
                error_log('SHED IMAGE SERVICE: skipping upload item at index ' . $index . ' because success/file_name missing');
                continue;
            }

            $file_name = basename($item['file_name']);
            $source_path = $forminator_temp_dir . $file_name;

            if (!file_exists($source_path)) {
                error_log('SHED IMAGE SERVICE: source file not found: ' . $source_path);
                continue;
            }

            $temp_copy = wp_tempnam($file_name);

            if (!$temp_copy) {
                error_log('SHED IMAGE SERVICE: could not create temp file for ' . $file_name);
                continue;
            }

            if (!@copy($source_path, $temp_copy)) {
                error_log('SHED IMAGE SERVICE: could not copy source file to temp for ' . $file_name);

                if (file_exists($temp_copy)) {
                    @unlink($temp_copy);
                }
                continue;
            }

            $file_array = [
                'name'     => sanitize_file_name($file_name),
                'tmp_name' => $temp_copy,
            ];

            $attachment_id = media_handle_sideload($file_array, $post_id);

            if (is_wp_error($attachment_id)) {
                error_log('SHED IMAGE SERVICE: media_handle_sideload failed for ' . $file_name . ': ' . $attachment_id->get_error_message());

                if (file_exists($temp_copy)) {
                    @unlink($temp_copy);
                }
                continue;
            }

            $attachment_ids[] = $attachment_id;
        }

        return $attachment_ids;
    }

    public function build_gallery_html(array $attachment_ids)
    {
        $gallery_html = '';

        foreach ($attachment_ids as $attachment_id) {
            $img_html = wp_get_attachment_image($attachment_id, 'large');
            if ($img_html) {
                $gallery_html .= '<p>' . $img_html . '</p>';
            }
        }

        return $gallery_html;
    }
}