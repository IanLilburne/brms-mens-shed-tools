<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Submission_Normalizer {

    private $form_config;
    private $logger;

    public function __construct(array $form_config, Shed_Logger $logger) {
        $this->form_config = $form_config;
        $this->logger = $logger;
    }

    public function normalize($form_id, array $field_data_array) {
        $config = $this->get_form_config($form_id);

        if (!$config) {
            $this->logger->warning('No form config found for form', [
                'form_id' => $form_id,
            ]);
            return null;
        }

        $mapped = [];
        $field_map = $config['fields'];

        foreach ($field_map as $internal_key => $forminator_key) {
            $mapped[$internal_key] = $this->extract_field_value($field_data_array, $forminator_key);
        }

       $submission = [
    'trace_id' => $this->generate_trace_id(),
    'source' => 'forminator',
    'form_id' => (int) $form_id,
    'title' => (string) ($mapped['title'] ?? ''),
    'description' => (string) ($mapped['description'] ?? ''),
    'member_name' => (string) ($mapped['member_name'] ?? ''),
    'event_date' => !empty($mapped['event_date']) ? (string) $mapped['event_date'] : null,
    'permission_tick' => $mapped['permission_tick'] ?? '',
    'featured_crop_base64' => !empty($mapped['featured_crop_base64']) ? (string) $mapped['featured_crop_base64'] : null,
    'gallery_uploads' => $this->normalize_uploads($mapped['gallery_uploads'] ?? []),
    'raw_payload' => $field_data_array,
];

$this->logger->info('Submission normalized', [
    'trace_id' => $submission['trace_id'] ?? '',
    'form_id' => $submission['form_id'] ?? 0,
    'title' => $submission['title'] ?? '',
    'permission_tick' => $submission['permission_tick'] ?? '',
    'has_featured_crop' => !empty($submission['featured_crop_base64']),
    'gallery_type' => gettype($submission['gallery_uploads'] ?? null),
]);

        return $submission;
    }

    private function get_form_config($form_id) {
        foreach ($this->form_config as $form_key => $config) {
            if ((int) $config['form_id'] === (int) $form_id) {
                return $config;
            }
        }

        return null;
    }

    private function extract_field_value(array $field_data_array, $target_name) {
        foreach ($field_data_array as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? '';
            if ($name !== $target_name) {
                continue;
            }

            return $field['value'] ?? null;
        }

        return null;
    }

    private function normalize_uploads($value) {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [$value];
        }

        return [];
    }

    private function generate_trace_id() {
        return 'shed_' . wp_generate_password(8, false, false);
    }
}