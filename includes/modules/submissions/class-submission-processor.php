<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_Submission_Processor {

    private $logger;

    public function __construct(Shed_Logger $logger) {
        $this->logger = $logger;
    }

    public function process(array $submission) {
    $this->logger->info('Submission processor received submission', [
        'trace_id' => $submission['trace_id'] ?? '',
        'source' => $submission['source'] ?? '',
        'form_id' => $submission['form_id'] ?? 0,
        'title' => $submission['title'] ?? '',
        'member_name' => $submission['member_name'] ?? '',
        'has_featured_crop' => !empty($submission['featured_crop_base64']),
        'gallery_count' => count($submission['gallery_uploads'] ?? []),
    ]);

    if (function_exists('shed_process_news_submission_from_normalized')) {
        return shed_process_news_submission_from_normalized($submission);
    }

    $this->logger->warning('Normalized submission handler function not found', [
        'trace_id' => $submission['trace_id'] ?? '',
    ]);

    return false;
}
}