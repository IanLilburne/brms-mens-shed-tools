<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_handle_tv_task_volunteer_submission')) {
    function shed_handle_tv_task_volunteer_submission() {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            !isset($_POST['shed_tv_task_action']) ||
            !isset($_POST['shed_tv_task_nonce'])
        ) {
            return;
        }

        $project_id = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
        $task_index = isset($_POST['task_index']) ? absint(wp_unslash($_POST['task_index'])) : 0;
        $nonce      = sanitize_text_field(wp_unslash($_POST['shed_tv_task_nonce']));

        if (!$project_id || !wp_verify_nonce($nonce, 'shed_tv_task_' . $project_id . '_' . $task_index)) {
            return;
        }

        $project_post = get_post($project_id);
        if (!$project_post || $project_post->post_type !== 'project') {
            return;
        }

        if (!in_array(shed_get_project_type($project_id), ['project', 'idea'], true)) {
            return;
        }

        $tasks = get_post_meta($project_id, 'project_tasks', true);
        if (!is_array($tasks) || !isset($tasks[$task_index]) || !is_array($tasks[$task_index])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['shed_tv_task_action']));

        if ($action === 'remove') {
            $tasks[$task_index]['volunteer_name'] = '';
        } else {
            $volunteer_name = isset($_POST['volunteer_name']) ? sanitize_text_field(wp_unslash($_POST['volunteer_name'])) : '';
            $tasks[$task_index]['volunteer_name'] = substr($volunteer_name, 0, 15);
        }

        update_post_meta($project_id, 'project_tasks', $tasks);

        $redirect_url = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $redirect_url = wp_validate_redirect($redirect_url, '');

        if (!$redirect_url) {
            $redirect_url = wp_get_referer();
        }

        if (!$redirect_url) {
            $redirect_url = home_url('/');
        }

        wp_safe_redirect(add_query_arg([
            'shed_task_updated' => '1',
            'shed_task_project' => $project_id,
        ], $redirect_url));
        exit;
    }
}

add_action('template_redirect', 'shed_handle_tv_task_volunteer_submission');

if (!function_exists('shed_tv_dashboard_get_task_class')) {
    function shed_tv_dashboard_get_task_class($task) {
        if (!empty($task['done'])) {
            return 'shed-tv-task--done';
        }

        if (!empty($task['volunteer_name'])) {
            return 'shed-tv-task--assigned';
        }

        return 'shed-tv-task--unassigned';
    }
}

if (!function_exists('shed_tv_dashboard_render_tasks_panel')) {
    function shed_tv_dashboard_render_tasks_panel($project_data) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
        $redirect_to = remove_query_arg(['shed_task_updated', 'shed_task_project'], home_url($request_uri));
        ?>
        <div class="shed-tv-volunteers shed-tv-tasks-panel">
            <div class="shed-tv-volunteers-title">Tasks</div>

            <?php if (!empty($project_data['project_tasks'])): ?>
                <ul class="shed-tv-tasks-list">
                    <?php foreach ($project_data['project_tasks'] as $task): ?>
                        <?php
                        $task_class = shed_tv_dashboard_get_task_class($task);
                        $task_index = isset($task['task_index']) ? absint($task['task_index']) : 0;
                        $volunteer_name = isset($task['volunteer_name']) ? (string) $task['volunteer_name'] : '';
                        ?>
                        <li class="shed-tv-task <?php echo esc_attr($task_class); ?>">
                            <div class="shed-tv-task-main">
                                <div class="shed-tv-task-title">
                                    <span class="shed-tv-task-name"><?php echo esc_html($task['task']); ?></span>
                                    <?php if (!empty($task['est_hours'])): ?>
                                        <span class="shed-tv-task-hours"><?php echo esc_html($task['est_hours']); ?>h</span>
                                    <?php endif; ?>
                                    <span class="shed-tv-task-volunteer">
                                    <?php if (!empty($task['done'])): ?>
                                        Done<?php echo $volunteer_name !== '' ? ' by ' . esc_html($volunteer_name) : ''; ?>
                                    <?php elseif ($volunteer_name !== ''): ?>
                                        <?php echo esc_html($volunteer_name); ?> is helping
                                    <?php else: ?>
                                        No volunteer yet
                                    <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (empty($task['done'])): ?>
                                <form class="shed-tv-task-form" method="post">
                                    <input type="hidden" name="shed_tv_task_nonce" value="<?php echo esc_attr(wp_create_nonce('shed_tv_task_' . $project_data['project_id'] . '_' . $task_index)); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $project_data['project_id']); ?>">
                                    <input type="hidden" name="task_index" value="<?php echo esc_attr((string) $task_index); ?>">
                                    <input type="hidden" name="shed_tv_task_action" value="save">
                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
                                    <input type="text" name="volunteer_name" maxlength="15" value="<?php echo esc_attr($volunteer_name); ?>" placeholder="Your name">
                                    <button type="submit"><?php echo $volunteer_name !== '' ? 'Update' : 'Volunteer'; ?></button>
                                    <?php if ($volunteer_name !== ''): ?>
                                        <button type="submit" name="shed_tv_task_action" value="remove" class="shed-tv-task-remove">Remove</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div>No tasks yet</div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('shed_tv_dashboard_render_project_slide')) {
    function shed_tv_dashboard_render_project_slide($project, $project_data, $stage_labels) {
        $print_payload = [
            'project_ref'        => (string) ($project_data['project_ref'] ?? ''),
            'title'              => (string) ($project_data['title'] ?? ''),
            'target'             => (string) ($project_data['target'] ?? ''),
            'description'        => (string) ($project_data['description'] ?? ''),
            'project_notes'      => (string) ($project_data['project_notes'] ?? ''),
            'project_contact'    => (string) ($project_data['project_contact'] ?? ''),
            'project_lifecycle'  => (string) ($project_data['project_lifecycle_label'] ?? ''),
            'image_url'          => (string) ($project_data['image_url'] ?? ''),
            'tasks'              => array_map(static function ($task) {
                return [
                    'done'           => !empty($task['done']) ? 'Yes' : 'No',
                    'task'           => (string) ($task['task'] ?? ''),
                    'est_hours'      => (string) ($task['est_hours'] ?? ''),
                    'volunteer_name' => (string) ($task['volunteer_name'] ?? ''),
                ];
            }, $project_data['project_tasks'] ?? []),
            'costings'           => array_map(static function ($row) {
                return [
                    'item'       => (string) ($row['item'] ?? ''),
                    'qty'        => (string) ($row['qty_display'] ?? ''),
                    'unit_price' => (string) ($row['unit_price_display'] ?? ''),
                    'line_total' => (string) ($row['line_total_display'] ?? ''),
                ];
            }, $project_data['project_costings'] ?? []),
            'costings_grand_total' => (string) ($project_data['project_costings_grand_total_display'] ?? '0.00'),
        ];
        ?>
        <li class="splide__slide">
            <div class="shed-tv-slide" data-project-id="<?php echo esc_attr((string) $project_data['project_id']); ?>" data-project-type="project">
                <script type="application/json" class="shed-tv-print-data"><?php echo wp_json_encode($print_payload); ?></script>
                <div class="shed-tv-left">
                    <div class="shed-tv-top">
                        <div class="shed-tv-title"><?php echo esc_html($project->post_title); ?></div>

                        <?php if ($project_data['project_ref']): ?>
                            <div class="shed-tv-project-ref">Project <?php echo esc_html($project_data['project_ref']); ?></div>
                        <?php endif; ?>

                        <?php if ($project_data['project_stage']): ?>
                            <div class="shed-tv-stage">
                                <?php echo esc_html($stage_labels[$project_data['project_stage']] ?? $project_data['project_stage']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="shed-tv-description-wrap">
                            <div class="shed-tv-description" data-collapsed-lines="4">
                                <?php echo esc_html($project_data['description']); ?>
                            </div>
                            <button type="button" class="shed-tv-description-toggle" hidden>More</button>
                        </div>
                    </div>

                    <div class="shed-tv-image-wrap">
                        <?php if ($project_data['image_html']): ?>
                            <?php echo $project_data['image_html']; ?>
                        <?php else: ?>
                            <div class="shed-tv-no-image">No project image yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="shed-tv-bottom">
                        <div class="shed-tv-meta">
                            <div class="shed-tv-meta-row">
                                <span class="shed-tv-label">Completion target date:</span>
                                <?php echo $project_data['target'] ? esc_html($project_data['target']) : 'Not set'; ?>
                            </div>
                            <div class="shed-tv-meta-row">
                                <span class="shed-tv-label">Main contact:</span>
                                <?php echo !empty($project_data['project_contact']) ? esc_html($project_data['project_contact']) : 'Not set'; ?>
                            </div>
                        </div>

                        <div class="shed-tv-hours">
                            <?php echo esc_html($project_data['committed']); ?> of <?php echo esc_html($project_data['required']); ?> hours
                        </div>

                        <div class="shed-tv-progress">
                            <div class="shed-tv-progress-bar" style="width: <?php echo esc_attr($project_data['percent']); ?>%; background: <?php echo esc_attr($project_data['bar_color']); ?>;"></div>
                            <div class="shed-tv-progress-percent"><?php echo esc_html($project_data['percent']); ?>%</div>
                        </div>
                    </div>
                </div>

                <div class="shed-tv-right">
                    <div class="shed-tv-status <?php echo esc_attr($project_data['status_cls']); ?>">
                        <?php echo esc_html($project_data['status']); ?>
                    </div>

                    <?php if (!empty($project_data['project_pdf_url'])): ?>
                        <div class="shed-tv-volunteers">
                            <a class="shed-tv-description-toggle" style="display:inline-block;" href="<?php echo esc_url($project_data['project_pdf_url']); ?>" target="_blank" rel="noopener">
                                Open PDF
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php shed_tv_dashboard_render_tasks_panel($project_data); ?>
                </div>
            </div>
        </li>
        <?php
    }
}

if (!function_exists('shed_tv_dashboard_render_idea_slide')) {
    function shed_tv_dashboard_render_idea_slide($project_data) {
        $status_class = $project_data['idea_status'] === 'ended' ? 'shed-status-full' : 'shed-status-idea';
        $status_label = $project_data['idea_status'] === 'ended' ? 'Ended idea' : 'Project idea';
        ?>
        <li class="splide__slide">
            <div class="shed-tv-slide" data-project-id="<?php echo esc_attr((string) $project_data['project_id']); ?>" data-project-type="idea">
                <div class="shed-tv-left">
                    <div class="shed-tv-top">
                        <div class="shed-tv-title"><?php echo esc_html($project_data['title']); ?></div>
                        <div class="shed-tv-project-ref">Idea / <?php echo esc_html($project_data['idea_label']); ?></div>

                        <div class="shed-tv-description-wrap">
                            <div class="shed-tv-description" data-collapsed-lines="4">
                                <?php echo esc_html($project_data['description']); ?>
                            </div>
                            <button type="button" class="shed-tv-description-toggle" hidden>More</button>
                        </div>
                    </div>

                    <div class="shed-tv-image-wrap">
                        <?php if ($project_data['image_html']): ?>
                            <?php echo $project_data['image_html']; ?>
                        <?php else: ?>
                            <div class="shed-tv-no-image">No idea image yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="shed-tv-meta shed-tv-meta-under-media">
                        <div class="shed-tv-meta-row">
                            <span class="shed-tv-label">Main contact:</span>
                            <?php echo !empty($project_data['project_contact']) ? esc_html($project_data['project_contact']) : 'Not set'; ?>
                        </div>
                    </div>
                </div>

                <div class="shed-tv-right">
                    <div class="shed-tv-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></div>

                    <div class="shed-tv-volunteers">
                        <div class="shed-tv-volunteers-title">Next step</div>
                        <div>Like this idea? Turn it into a live project when you are ready.</div>
                    </div>

                    <div class="shed-tv-volunteers">
                        <a class="shed-tv-description-toggle" style="display:inline-block;" href="<?php echo esc_url($project_data['create_from_idea_url']); ?>">
                            Clone to new project
                        </a>
                    </div>

                    <?php if (!empty($project_data['project_pdf_url'])): ?>
                        <div class="shed-tv-volunteers">
                            <a class="shed-tv-description-toggle" style="display:inline-block;" href="<?php echo esc_url($project_data['project_pdf_url']); ?>" target="_blank" rel="noopener">
                                Open PDF
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php shed_tv_dashboard_render_tasks_panel($project_data); ?>
                </div>
            </div>
        </li>
        <?php
    }
}

if (!function_exists('shed_tv_dashboard_render_event_slide')) {
    function shed_tv_dashboard_render_event_slide($project_data) {
        $status_class = $project_data['event_status'] === 'ended' ? 'shed-status-full' : 'shed-status-open';
        ?>
        <li class="splide__slide">
            <div class="shed-tv-slide" data-project-id="<?php echo esc_attr((string) $project_data['project_id']); ?>" data-project-type="event">
                <div class="shed-tv-left">
                    <div class="shed-tv-top">
                        <div class="shed-tv-title"><?php echo esc_html($project_data['title']); ?></div>
                        <div class="shed-tv-project-ref">Event</div>

                        <div class="shed-tv-description-wrap">
                            <div class="shed-tv-description" data-collapsed-lines="4">
                                <?php echo esc_html($project_data['description']); ?>
                            </div>
                            <button type="button" class="shed-tv-description-toggle" hidden>More</button>
                        </div>
                    </div>

                    <div class="shed-tv-image-wrap">
                        <?php if ($project_data['image_html']): ?>
                            <?php echo $project_data['image_html']; ?>
                        <?php else: ?>
                            <div class="shed-tv-no-image">No event image yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="shed-tv-meta shed-tv-meta-under-media">
                        <div class="shed-tv-meta-row">
                            <span class="shed-tv-label">Main contact:</span>
                            <?php echo !empty($project_data['project_contact']) ? esc_html($project_data['project_contact']) : 'Not set'; ?>
                        </div>
                    </div>
                </div>

                <div class="shed-tv-right">
                    <div class="shed-tv-status <?php echo esc_attr($status_class); ?>">
                        <?php echo esc_html($project_data['event_status_label']); ?>
                    </div>

                    <div class="shed-tv-volunteers">
                        <div class="shed-tv-volunteers-title">Event date</div>
                        <div><?php echo $project_data['event_date'] ? esc_html($project_data['event_date']) : 'Not set'; ?></div>
                    </div>

                    <div class="shed-tv-volunteers">
                        <div class="shed-tv-volunteers-title">Location</div>
                        <div><?php echo $project_data['event_location'] ? esc_html($project_data['event_location']) : 'Not set'; ?></div>
                    </div>

                    <div class="shed-tv-volunteers">
                        <div class="shed-tv-volunteers-title">Status</div>
                        <div><?php echo esc_html($project_data['event_status_label']); ?></div>
                    </div>
                </div>
            </div>
        </li>
        <?php
    }
}

if (!function_exists('shed_tv_dashboard_render_video_list_panel')) {
    function shed_tv_dashboard_render_video_list_panel($videos, $current_video_id) {
        ?>
        <div class="shed-tv-volunteers shed-tv-video-list-panel">
            <div class="shed-tv-volunteers-title">Training videos</div>

            <?php if (!empty($videos)) : ?>
                <ul class="shed-tv-video-list">
                    <?php foreach ($videos as $video) : ?>
                        <?php
                        $is_current = (int) $video['project_id'] === (int) $current_video_id;
                        $video_url = isset($video['video_url']) ? (string) $video['video_url'] : '';
                        ?>
                        <li class="<?php echo $is_current ? 'is-current' : ''; ?>">
                            <div>
                                <span class="shed-tv-video-list-title"><?php echo esc_html($video['title']); ?></span>
                                <?php if (!empty($video['video_category'])) : ?>
                                    <span class="shed-tv-video-list-meta"><?php echo esc_html($video['video_category']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($video_url !== '') : ?>
                                <button type="button" class="shed-tv-video-open" data-video-url="<?php echo esc_url($video_url); ?>">
                                    Play
                                </button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <div>No training videos yet</div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('shed_tv_dashboard_render_video_slide')) {
    function shed_tv_dashboard_render_video_slide($video_data, $videos) {
        $video_url = isset($video_data['video_url']) ? (string) $video_data['video_url'] : '';
        ?>
        <li class="splide__slide">
            <div class="shed-tv-slide shed-tv-video-slide" data-project-id="<?php echo esc_attr((string) $video_data['project_id']); ?>" data-project-type="video" data-video-url="<?php echo esc_url($video_url); ?>">
                <div class="shed-tv-left">
                    <div class="shed-tv-top">
                        <div class="shed-tv-title"><?php echo esc_html($video_data['title']); ?></div>
                        <div class="shed-tv-project-ref">Training video</div>

                        <div class="shed-tv-description-wrap">
                            <div class="shed-tv-description" data-collapsed-lines="4">
                                <?php echo esc_html($video_data['description']); ?>
                            </div>
                            <button type="button" class="shed-tv-description-toggle" hidden>More</button>
                        </div>
                    </div>

                    <div class="shed-tv-image-wrap shed-tv-video-image-wrap">
                        <?php if ($video_data['image_html']): ?>
                            <?php echo $video_data['image_html']; ?>
                        <?php else: ?>
                            <div class="shed-tv-no-image">No video image yet</div>
                        <?php endif; ?>

                        <?php if ($video_url !== '') : ?>
                            <button type="button" class="shed-tv-video-play-overlay" data-video-url="<?php echo esc_url($video_url); ?>">
                                Play video
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="shed-tv-meta shed-tv-meta-under-media">
                        <div class="shed-tv-meta-row">
                            <span class="shed-tv-label">Main contact:</span>
                            <?php echo !empty($video_data['project_contact']) ? esc_html($video_data['project_contact']) : 'Not set'; ?>
                        </div>
                    </div>
                </div>

                <div class="shed-tv-right">
                    <div class="shed-tv-status <?php echo $video_data['video_status'] === 'archived' ? 'shed-status-full' : 'shed-status-open'; ?>">
                        <?php echo esc_html($video_data['video_status_label']); ?>
                    </div>

                    <?php if (!empty($video_data['video_category']) || !empty($video_data['video_duration'])) : ?>
                        <div class="shed-tv-volunteers">
                            <div class="shed-tv-volunteers-title">Details</div>
                            <?php if (!empty($video_data['video_category'])) : ?>
                                <div><strong>Category:</strong> <?php echo esc_html($video_data['video_category']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($video_data['video_duration'])) : ?>
                                <div><strong>Duration:</strong> <?php echo esc_html($video_data['video_duration']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($video_url !== '') : ?>
                        <button type="button" class="shed-tv-video-primary-button shed-tv-video-open" data-video-url="<?php echo esc_url($video_url); ?>">
                            Play current video
                        </button>
                    <?php endif; ?>

                    <?php shed_tv_dashboard_render_video_list_panel($videos, $video_data['project_id']); ?>
                </div>
            </div>
        </li>
        <?php
    }
}

/**
 * TV dashboard shortcode.
 */
if (!function_exists('shed_tv_dashboard_render')) {
    function shed_tv_dashboard_render($atts = []) {
        $atts         = shortcode_atts(['type' => 'project'], $atts, 'shed_tv_dashboard');
        $default_type = shed_get_dashboard_type_filter($atts['type']);
        if ($default_type === 'all') {
            $default_type = 'project';
        }
        $type_filter  = shed_get_tv_type_filter($default_type);
        $status_filter = shed_get_tv_status_filter($type_filter);
        $projects     = shed_get_tv_dashboard_projects($type_filter, $status_filter);
        $videos       = $type_filter === 'video' ? shed_get_tv_dashboard_video_items($status_filter) : [];
        $stage_labels = shed_get_stage_labels();

        ob_start();
        ?>

        <div class="shed-tv-wrapper">
            <div class="shed-tv-fullscreen-controls">
                <button type="button" id="shed-tv-menu-toggle" class="shed-tv-menu-toggle" aria-expanded="false" aria-controls="shed-tv-menu-panel">Menu</button>

                <div id="shed-tv-menu-panel" class="shed-tv-menu-panel">
                    <button type="button" id="shed-enter-fullscreen">Full screen</button>
                    <button type="button" id="shed-exit-fullscreen">Exit</button>
                    <button type="button" id="shed-exit-dashboard">Back to site</button>
                    <button type="button" id="shed-tv-prev-slide" aria-label="Previous slide">Previous</button>
                    <button type="button" id="shed-tv-next-slide" aria-label="Next slide">Next</button>
                    <button type="button" id="shed-tv-toggle-autoplay" aria-pressed="false">Pause</button>
                    <button type="button" id="shed-edit-dashboard-item" data-edit-url="<?php echo esc_url(site_url('/home/members-area/create-project/')); ?>">Edit</button>
                    <button type="button" id="shed-print-dashboard-item">Print</button>

                    <label class="shed-tv-filter-control">
                        <span>Type</span>
                        <select id="shed-tv-type-filter">
                            <option value="project" <?php selected($type_filter, 'project'); ?>>Project</option>
                            <option value="idea" <?php selected($type_filter, 'idea'); ?>>Idea</option>
                            <option value="event" <?php selected($type_filter, 'event'); ?>>Event</option>
                            <option value="video" <?php selected($type_filter, 'video'); ?>>Videos</option>
                        </select>
                    </label>

                    <label class="shed-tv-filter-control">
                        <span>Status</span>
                        <select id="shed-tv-status-filter" data-current-status="<?php echo esc_attr($status_filter); ?>"></select>
                    </label>
                </div>
            </div>

            <?php if (isset($_GET['shed_task_updated'])) : ?>
                <div class="shed-tv-task-updated">Task updated</div>
            <?php endif; ?>

            <div id="shedSplide" class="splide">
                <div class="splide__track">
                    <ul class="splide__list">

                    <?php if (!$projects): ?>
                        <li class="splide__slide">
                            <div class="shed-tv-slide">
                                <div class="shed-tv-left">
                                    <div class="shed-tv-title">No items match this filter</div>
                                    <div class="shed-tv-description">Choose another type or status from the controls above.</div>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>

                    <?php if ($type_filter === 'video') : ?>
                        <?php foreach ($videos as $video_data) : ?>
                            <?php shed_tv_dashboard_render_video_slide($video_data, $videos); ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $project_type = shed_get_tv_dashboard_project_type($project->ID);

                            if ($project_type === 'idea') {
                                $project_data = shed_get_tv_dashboard_idea_data($project);
                                if ($project_data) {
                                    shed_tv_dashboard_render_idea_slide($project_data);
                                }
                                continue;
                            }

                            if ($project_type === 'event') {
                                $project_data = shed_get_tv_dashboard_event_data($project);
                                if ($project_data) {
                                    shed_tv_dashboard_render_event_slide($project_data);
                                }
                                continue;
                            }

                            if ($project_type === 'video') {
                                $project_data = shed_get_tv_dashboard_video_data($project);
                                if ($project_data) {
                                    shed_tv_dashboard_render_video_slide($project_data, $videos);
                                }
                                continue;
                            }

                            $project_data = shed_get_tv_dashboard_project_data($project);

                            if ($project_data) {
                                shed_tv_dashboard_render_project_slide($project, $project_data, $stage_labels);
                            }
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    </ul>
                </div>
            </div>
        </div>

        <?php

        return ob_get_clean();
    }
}

add_shortcode('shed_tv_dashboard', 'shed_tv_dashboard_render');
