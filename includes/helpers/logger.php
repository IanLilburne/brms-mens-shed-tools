<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Logger {

    public function info($message, array $context = []) {
        $this->write('INFO', $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->write('WARNING', $message, $context);
    }

    public function error($message, array $context = []) {
        $this->write('ERROR', $message, $context);
    }

    private function write($level, $message, array $context = []) {
        $line = '[MENS-SHED-TOOLS][' . $level . '] ' . $message;

        if (!empty($context)) {
            $json = wp_json_encode($context);
            if ($json !== false) {
                $line .= ' ' . $json;
            }
        }

        error_log($line);
    }
}