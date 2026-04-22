<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once MENS_SHED_TOOLS_PATH . 'includes/helpers/logger.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/submissions/class-submission-processor.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/forms/class-submission-normalizer.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/forms/class-forminator-adapter.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/ai/class-ai-rewrite-service.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/images/class-image-service.php';
require_once MENS_SHED_TOOLS_PATH . 'includes/modules/posts/class-post-service.php';

function shed_tools_bootstrap() {
    $form_config = require MENS_SHED_TOOLS_PATH . 'includes/config/forms.php';

    $logger = new Shed_Logger();
    $processor = new Shed_Submission_Processor($logger);
    $normalizer = new Shed_Submission_Normalizer($form_config, $logger);
    $adapter = new Shed_Forminator_Adapter($normalizer, $processor, $logger);

    //$adapter->register();

    $logger->info('Mens Shed Tools bootstrap complete');
}

add_action('plugins_loaded', 'shed_tools_bootstrap');