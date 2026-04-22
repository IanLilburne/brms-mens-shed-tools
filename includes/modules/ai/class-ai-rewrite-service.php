<?php
if (!defined('ABSPATH')) {
    exit;
}

class Shed_AI_Rewrite_Service {

    public function rewrite($raw_title, $raw_story, $contributor = '', $activity_date = '') {
        return shed_ai_rewrite_story($raw_title, $raw_story, $contributor, $activity_date);
    }
}