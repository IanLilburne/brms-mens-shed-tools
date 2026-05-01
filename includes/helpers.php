<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple logger for debugging.
 */
function shed_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if ($data !== null) {
            error_log('SHED LOG: ' . $message . ' | ' . print_r($data, true));
        } else {
            error_log('SHED LOG: ' . $message);
        }
    }
}

if (!function_exists('shed_get_project_type')) {
    function shed_get_project_type($post_id) {
        $project_type = sanitize_key((string) get_post_meta($post_id, 'project_type', true));

        if (!in_array($project_type, ['project', 'idea', 'event', 'video'], true)) {
            return 'project';
        }

        return $project_type;
    }
}

if (!function_exists('shed_normalize_date_input')) {
    function shed_normalize_date_input($raw_value) {
        $raw_value = trim((string) $raw_value);

        if ($raw_value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_value)) {
            return $raw_value;
        }

        if (strpos($raw_value, '/') !== false) {
            $parts = explode('/', $raw_value);
            if (count($parts) === 3) {
                return sprintf(
                    '%04d-%02d-%02d',
                    intval($parts[2]),
                    intval($parts[1]),
                    intval($parts[0])
                );
            }
        }

        if (strpos($raw_value, '-') !== false) {
            $parts = explode('-', $raw_value);
            if (count($parts) === 3) {
                if (strlen((string) $parts[0]) === 4) {
                    return sprintf(
                        '%04d-%02d-%02d',
                        intval($parts[0]),
                        intval($parts[1]),
                        intval($parts[2])
                    );
                }

                return sprintf(
                    '%04d-%02d-%02d',
                    intval($parts[2]),
                    intval($parts[1]),
                    intval($parts[0])
                );
            }
        }

        return '';
    }
}

if (!function_exists('shed_render_cropper_assets')) {
    function shed_render_cropper_assets() {
        static $rendered = false;

        if ($rendered) {
            return;
        }

        $rendered = true;
        ?>
        <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css">
        <link rel="stylesheet" href="<?php echo esc_url(MENS_SHED_TOOLS_URL . 'assets/news-cropper.css'); ?>?ver=<?php echo esc_attr(file_exists(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.css') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.css') : '1.0'); ?>">
        <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>
        <script src="<?php echo esc_url(MENS_SHED_TOOLS_URL . 'assets/news-cropper.js'); ?>?ver=<?php echo esc_attr(file_exists(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.js') ? filemtime(MENS_SHED_TOOLS_PATH . 'assets/news-cropper.js') : '1.0'); ?>"></script>
        <?php
    }
}
