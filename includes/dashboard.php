<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * TV dashboard shortcode.
 */
if (!function_exists('shed_tv_dashboard_render')) {
    add_shortcode('shed_tv_dashboard', 'shed_tv_dashboard_render');

    function shed_tv_dashboard_render() {
        $tv_filter    = shed_get_tv_filter();
        $projects     = shed_get_tv_dashboard_projects($tv_filter);
        $stage_labels = shed_get_stage_labels();

        if (!$projects) {
            return '<p>No projects in this category. Try selecting "All".</p>';
        }

        ob_start();
        ?>

        <div class="shed-tv-wrapper">
            <div class="shed-tv-fullscreen-controls">
                <button type="button" id="shed-enter-fullscreen">Full screen</button>
                <button type="button" id="shed-exit-fullscreen">Exit</button>
                <button type="button" id="shed-exit-dashboard">Back to site</button>

                <button type="button" class="shed-filter-btn" data-filter="all">All</button>
                <button type="button" class="shed-filter-btn" data-filter="awaiting_you">Awaiting you!</button>
                <button type="button" class="shed-filter-btn" data-filter="seeking_volunteers">Seeking volunteers</button>
                <button type="button" class="shed-filter-btn" data-filter="volunteer_goal_achieved">Goal achieved</button>
            </div>

            <div id="shedSplide" class="splide">
                <div class="splide__track">
                    <ul class="splide__list">

<?php foreach ($projects as $project): ?>
    <?php
    $project_data = shed_get_tv_dashboard_project_data($project);
    $volunteers   = shed_get_project_recent_volunteers($project->ID, 4);

    if (!$project_data) {
        continue;
    }
    ?>

    <li class="splide__slide">
        <div class="shed-tv-slide">
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

                    <div class="shed-tv-description">
                        <?php echo esc_html($project_data['description']); ?>
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

                <div class="shed-tv-qr-block">
                    <div class="shed-tv-qr-title">Scan to join this project</div>
                    <img src="<?php echo esc_url($project_data['qr_url']); ?>" alt="QR code for volunteering">
                    <div class="shed-tv-qr-caption">Opens this project’s volunteer form</div>
                </div>

                <div class="shed-tv-volunteers">
                    <div class="shed-tv-volunteers-title">Recent volunteers</div>

                    <?php if ($volunteers): ?>
                        <ul class="shed-tv-volunteers-list">
                            <?php foreach ($volunteers as $v): ?>
                                <?php $volunteer = shed_get_volunteer_signup_summary($v->ID); ?>
                                <li>
                                    <?php echo esc_html($volunteer['name']); ?>, <?php echo esc_html($volunteer['hours']); ?>h
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div>No volunteers yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </li>
<?php endforeach; ?>

                    </ul>
                </div>
            </div>
        </div>

        
        <?php

        return ob_get_clean();
    }
}