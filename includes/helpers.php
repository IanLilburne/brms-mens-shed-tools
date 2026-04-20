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