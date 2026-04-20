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
            return '<p>No projects found.</p>';
        }

        ob_start();
        ?>
        <style>
            #shedSplide {
                width: 100%;
                height: 100%;
            }

            #shedSplide .splide__track,
            #shedSplide .splide__list,
            #shedSplide .splide__slide {
                height: 100%;
            }

            .shed-tv-wrapper {
                width: 100%;
                min-height: 100vh;
                background: #f7f7f7;
                color: #111;
                font-family: Arial, sans-serif;
                box-sizing: border-box;
            }

            .shed-tv-slide {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 36px;
                align-items: stretch;
                min-height: 100vh;
                padding: 36px 44px;
                box-sizing: border-box;
            }

            .shed-tv-left,
            .shed-tv-right {
                background: #fff;
                border-radius: 20px;
                padding: 28px;
                box-sizing: border-box;
            }

            .shed-tv-left {
                display: flex;
                flex-direction: column;
                height: 100%;
                min-height: 0;
            }

            .shed-tv-right {
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                align-items: stretch;
                gap: 28px;
            }

            .shed-tv-top {
                flex: 0 1 auto;
            }

            .shed-tv-title {
                font-size: 3.2rem;
                line-height: 1.05;
                font-weight: 700;
                margin-bottom: 10px;
            }

            .shed-tv-project-ref {
                font-size: 1.05rem;
                line-height: 1.2;
                font-weight: 700;
                color: #666;
                margin-bottom: 10px;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .shed-tv-stage {
                font-size: 0.98rem;
                color: #888;
                margin-bottom: 14px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .shed-tv-description {
                font-size: 1.3rem;
                line-height: 1.32;
                margin-bottom: 18px;
                color: #222;
                display: -webkit-box;
                -webkit-line-clamp: 4;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .shed-tv-image-wrap {
                position: relative;
                width: 100%;
                aspect-ratio: 16 / 9;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #f3f3f3;
                border-radius: 16px;
                overflow: hidden;
                margin-bottom: 22px;
                flex: 0 0 auto;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            }

            .shed-tv-image-wrap img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .shed-tv-no-image {
                font-size: 1.2rem;
                color: #777;
                padding: 20px;
                text-align: center;
            }

            .shed-tv-bottom {
                margin-top: auto;
            }

            .shed-tv-meta {
                font-size: 1.25rem;
                line-height: 1.4;
                margin-bottom: 14px;
            }

            .shed-tv-meta-row {
                margin-bottom: 10px;
            }

            .shed-tv-label {
                font-weight: 700;
            }

            .shed-tv-hours {
                font-size: 1.55rem;
                font-weight: 700;
                margin: 16px 0 14px 0;
                white-space: nowrap;
            }

            .shed-tv-progress {
                width: 100%;
                height: 38px;
                background: #e6e6e6;
                border-radius: 22px;
                overflow: hidden;
                margin-bottom: 8px;
                position: relative;
            }

            .shed-tv-progress-bar {
                height: 100%;
                transition: width 0.5s ease;
            }

            .shed-tv-progress-percent {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 1rem;
                font-weight: 700;
                color: #222;
                z-index: 2;
            }

            .shed-tv-status {
                display: inline-block;
                align-self: flex-start;
                font-size: 2.2rem;
                font-weight: 700;
                padding: 14px 24px;
                border-radius: 999px;
                margin-bottom: 6px;
            }

            .shed-status-open {
                background: #e6f6e6;
                color: #0a7f00;
            }

            .shed-status-full {
                background: #fde8e8;
                color: #b30000;
            }

            .shed-status-idea {
                background: #fff6d6;
                color: #a36b00;
            }

            .shed-tv-qr-block {
                text-align: center;
                background: #fafafa;
                border-radius: 16px;
                padding: 20px;
                animation: shedPulse 3s infinite;
            }

            @keyframes shedPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }

            .shed-tv-qr-title {
                font-size: 1.45rem;
                font-weight: 700;
                margin-bottom: 12px;
            }

            .shed-tv-qr-block img {
                width: 180px;
                height: 180px;
                display: block;
                margin: 0 auto 10px auto;
            }

            .shed-tv-qr-caption {
                font-size: 1.05rem;
                color: #444;
                line-height: 1.3;
            }

            .shed-tv-volunteers {
                background: #fafafa;
                border-radius: 16px;
                padding: 20px;
            }

            .shed-tv-volunteers-title {
                font-size: 1.35rem;
                font-weight: 700;
                margin-bottom: 12px;
            }

            .shed-tv-volunteers-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .shed-tv-volunteers-list li {
                font-size: 1.2rem;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .shed-tv-fullscreen-controls {
                position: fixed;
                top: 14px;
                right: 14px;
                z-index: 9999;
                display: flex;
                gap: 10px;
            }

            .shed-tv-fullscreen-controls button {
                font-size: 1rem;
                padding: 10px 14px;
                border: none;
                border-radius: 10px;
                background: rgba(0, 0, 0, 0.75);
                color: #fff;
                cursor: pointer;
            }

            .shed-filter-btn {
                font-size: 1rem;
                padding: 10px 14px;
                border: none;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.9);
                color: #111;
                cursor: pointer;
            }

            .shed-filter-btn.active {
                background: #0a7f00;
                color: #fff;
            }

            @media (max-width: 1100px) {
                .shed-tv-slide {
                    grid-template-columns: 1fr;
                    min-height: auto;
                }

                .shed-tv-wrapper {
                    min-height: auto;
                }

                .shed-tv-hours {
                    white-space: normal;
                }
            }
        </style>

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
                            $project_ref   = get_post_meta($project->ID, 'project_ref', true);
                            $required      = intval(get_post_meta($project->ID, 'hours_required', true));
                            $committed     = intval(get_post_meta($project->ID, 'hours_committed', true));
                            $target        = get_post_meta($project->ID, 'completion_target_date', true);
                            $project_stage = get_post_meta($project->ID, 'project_stage', true);

                            $percent = $required > 0 ? min(100, round(($committed / $required) * 100)) : 0;

                            if ($project_stage === '') {
                                $project_stage = 'quote';
                            }

                            $status_data = shed_get_project_dashboard_status($project->ID);
                            $status      = $status_data['label'];
                            $bar_color   = $status_data['bar_color'];
                            $status_cls  = $status_data['class'];

                            $description = wp_trim_words(wp_strip_all_tags($project->post_content), 24);

                            $volunteer_url = add_query_arg('project_id', $project->ID, site_url('/home/members-area/projects-volunteer-signup/'));
                            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($volunteer_url);

                            $volunteers = get_posts([
                                'post_type'      => 'volunteer_signup',
                                'posts_per_page' => 4,
                                'orderby'        => 'date',
                                'order'          => 'DESC',
                                'meta_query'     => [
                                    [
                                        'key'   => 'project_id',
                                        'value' => $project->ID,
                                    ]
                                ]
                            ]);

                            $image_html = '';
                            if (has_post_thumbnail($project->ID)) {
                                $image_html = get_the_post_thumbnail($project->ID, 'large');
                            }
                            ?>

                            <li class="splide__slide">
                                <div class="shed-tv-slide">
                                    <div class="shed-tv-left">
                                        <div class="shed-tv-top">
                                            <div class="shed-tv-title"><?php echo esc_html($project->post_title); ?></div>

                                            <?php if ($project_ref): ?>
                                                <div class="shed-tv-project-ref">Project <?php echo esc_html($project_ref); ?></div>
                                            <?php endif; ?>

                                            <?php if ($project_stage): ?>
                                                <div class="shed-tv-stage">
                                                    <?php echo esc_html($stage_labels[$project_stage] ?? $project_stage); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="shed-tv-description">
                                                <?php echo esc_html($description); ?>
                                            </div>
                                        </div>

                                        <div class="shed-tv-image-wrap">
                                            <?php if ($image_html): ?>
                                                <?php echo $image_html; ?>
                                            <?php else: ?>
                                                <div class="shed-tv-no-image">No project image yet</div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="shed-tv-bottom">
                                            <div class="shed-tv-meta">
                                                <div class="shed-tv-meta-row">
                                                    <span class="shed-tv-label">Completion target date:</span>
                                                    <?php echo $target ? esc_html($target) : 'Not set'; ?>
                                                </div>
                                            </div>

                                            <div class="shed-tv-hours">
                                                <?php echo esc_html($committed); ?> of <?php echo esc_html($required); ?> hours
                                            </div>

                                            <div class="shed-tv-progress">
                                                <div class="shed-tv-progress-bar" style="width: <?php echo esc_attr($percent); ?>%; background: <?php echo esc_attr($bar_color); ?>;"></div>
                                                <div class="shed-tv-progress-percent"><?php echo esc_html($percent); ?>%</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="shed-tv-right">
                                        <div class="shed-tv-status <?php echo esc_attr($status_cls); ?>">
                                            <?php echo esc_html($status); ?>
                                        </div>

                                        <div class="shed-tv-qr-block">
                                            <div class="shed-tv-qr-title">Scan to join this project</div>
                                            <img src="<?php echo esc_url($qr_url); ?>" alt="QR code for volunteering">
                                            <div class="shed-tv-qr-caption">Opens this project’s volunteer form</div>
                                        </div>

                                        <div class="shed-tv-volunteers">
                                            <div class="shed-tv-volunteers-title">Recent volunteers</div>

                                            <?php if ($volunteers): ?>
                                                <ul class="shed-tv-volunteers-list">
                                                    <?php foreach ($volunteers as $v): ?>
                                                        <?php
                                                        $name  = get_post_meta($v->ID, 'volunteer_name', true);
                                                        $hours = get_post_meta($v->ID, 'volunteer_hours', true);
                                                        ?>
                                                        <li>
                                                            <?php echo esc_html($name); ?>, <?php echo esc_html($hours); ?>h
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

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var splideEl = document.getElementById('shedSplide');
            if (splideEl) {
                new Splide(splideEl, {
                    type: 'loop',
                    autoplay: true,
                    interval: 8000,
                    pauseOnHover: false,
                    pauseOnFocus: false,
                    arrows: false,
                    pagination: false,
                    speed: 800,
                }).mount();
            }

            var enterBtn = document.getElementById('shed-enter-fullscreen');
            var exitBtn = document.getElementById('shed-exit-fullscreen');
            var exitDashboardBtn = document.getElementById('shed-exit-dashboard');

            if (enterBtn) {
                enterBtn.addEventListener('click', function () {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    }
                });
            }

            if (exitBtn) {
                exitBtn.addEventListener('click', function () {
                    if (document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                });
            }

            if (exitDashboardBtn) {
                exitDashboardBtn.addEventListener('click', function () {
                    if (document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen();
                    }

                    window.location.href = '/';
                });
            }

            var filterButtons = document.querySelectorAll('.shed-filter-btn');
            var currentUrl = new URL(window.location.href);
            var currentFilter = currentUrl.searchParams.get('tv_filter') || 'all';

            function setActiveFilterButton(filter) {
                filterButtons.forEach(function (btn) {
                    btn.classList.toggle('active', btn.getAttribute('data-filter') === filter);
                });
            }

            setActiveFilterButton(currentFilter);

            if (!currentUrl.searchParams.get('tv_filter')) {
                var savedFilter = localStorage.getItem('shed_tv_filter');
                if (savedFilter && savedFilter !== 'all') {
                    currentUrl.searchParams.set('tv_filter', savedFilter);
                    window.location.replace(currentUrl.toString());
                    return;
                }
            }

            filterButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var filter = btn.getAttribute('data-filter') || 'all';
                    localStorage.setItem('shed_tv_filter', filter);

                    var url = new URL(window.location.href);

                    if (filter === 'all') {
                        url.searchParams.delete('tv_filter');
                    } else {
                        url.searchParams.set('tv_filter', filter);
                    }

                    window.location.href = url.toString();
                });
            });

            setTimeout(function () {
                location.reload();
            }, 300000);
        });
        </script>
        <?php

        return ob_get_clean();
    }
}