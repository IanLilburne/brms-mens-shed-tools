<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Forminator_Adapter {

    private $normalizer;
    private $processor;
    private $logger;

    public function __construct(
        Shed_Submission_Normalizer $normalizer,
        Shed_Submission_Processor $processor,
        Shed_Logger $logger
    ) {
        $this->normalizer = $normalizer;
        $this->processor = $processor;
        $this->logger = $logger;
    }

    public function register() {
        add_action(
            'forminator_custom_form_submit_field_data',
            [$this, 'handle_submission'],
            10,
            2
        );
    }

    public function handle_submission($field_data_array, $form_id) {
        $this->logger->info('Forminator adapter hook fired', [
            'form_id' => $form_id,
            'field_count' => is_array($field_data_array) ? count($field_data_array) : 0,
        ]);

        if (!is_array($field_data_array)) {
            $this->logger->warning('Forminator submission payload was not an array', [
                'form_id' => $form_id,
            ]);
            return $field_data_array;
        }

        $submission = $this->normalizer->normalize($form_id, $field_data_array);

        if (!$submission) {
            return $field_data_array;
        }

        $this->processor->process($submission);

        return $field_data_array;
    }
}