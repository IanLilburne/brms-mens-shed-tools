<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Image_Service
{
    const MAX_NATIVE_GALLERY_IMAGES = 8;
    const MAX_NATIVE_GALLERY_IMAGE_BYTES = 4194304;

    public function save_cropped_featured_image($base64_image, $post_id)
    {
        return shed_save_cropped_featured_image($base64_image, $post_id);
    }

    public function import_native_gallery_images($uploaded_files, $post_id)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_ids = [];
        $upload_items = $this->normalize_native_uploads($uploaded_files);

        if (empty($upload_items)) {
            return $attachment_ids;
        }

        foreach ($upload_items as $file) {
            $file_name = isset($file['name']) ? (string) $file['name'] : '';
            $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
            $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            $size = isset($file['size']) ? (int) $file['size'] : 0;

            if (
                $file_name === '' ||
                $error === UPLOAD_ERR_NO_FILE ||
                $error !== UPLOAD_ERR_OK ||
                $size > self::MAX_NATIVE_GALLERY_IMAGE_BYTES ||
                $tmp_name === '' ||
                !is_uploaded_file($tmp_name)
            ) {
                continue;
            }

            $temp_copy = wp_tempnam($file_name);

            if (!$temp_copy || !@copy($tmp_name, $temp_copy)) {
                if ($temp_copy && file_exists($temp_copy)) {
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
                if (file_exists($temp_copy)) {
                    @unlink($temp_copy);
                }
                continue;
            }

            $attachment_ids[] = $attachment_id;
        }

        return $attachment_ids;
    }

    private function normalize_native_uploads($uploaded_files)
    {
        if (!is_array($uploaded_files) || empty($uploaded_files['name'])) {
            return [];
        }

        if (!is_array($uploaded_files['name'])) {
            return [$uploaded_files];
        }

        $files = [];
        $file_count = min(count($uploaded_files['name']), self::MAX_NATIVE_GALLERY_IMAGES);

        for ($index = 0; $index < $file_count; $index++) {
            $files[] = [
                'name'     => $uploaded_files['name'][$index] ?? '',
                'type'     => $uploaded_files['type'][$index] ?? '',
                'tmp_name' => $uploaded_files['tmp_name'][$index] ?? '',
                'error'    => $uploaded_files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $uploaded_files['size'][$index] ?? 0,
            ];
        }

        return $files;
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
