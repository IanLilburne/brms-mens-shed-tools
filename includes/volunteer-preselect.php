<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_preselect_volunteer_project_js')) {
    function shed_preselect_volunteer_project_js() {

        if (!is_page('projects-volunteer-signup')) {
            return;
        }

        $project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;

        if (!$project_id) {
            return;
        }

        $project_post = get_post($project_id);

        if (!$project_post || $project_post->post_type !== 'project') {
            return;
        }

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var projectId = <?php echo json_encode((string) $project_id); ?>;

            function setVolunteerProject() {
                var select =
                    document.querySelector('select[name="select-1"]') ||
                    document.getElementById('select-1');

                if (!select) {
                    return false;
                }

                select.value = projectId;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }

            if (setVolunteerProject()) {
                return;
            }

            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                if (setVolunteerProject() || tries > 20) {
                    clearInterval(timer);
                }
            }, 300);
        });
        </script>
        <?php
    }
}

add_action('wp_footer', 'shed_preselect_volunteer_project_js', 100);