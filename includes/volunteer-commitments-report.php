<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('shed_get_volunteer_commitment_rows')) {
    function shed_get_volunteer_commitment_rows() {
        $projects = get_posts([
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $rows = [];

        foreach ($projects as $project) {
            $project_id = $project->ID;

            if (shed_get_tv_dashboard_project_type($project_id) !== 'project') {
                continue;
            }

            if (shed_normalize_project_stage(get_post_meta($project_id, 'project_stage', true)) === 'complete') {
                continue;
            }

            foreach (shed_get_project_tasks($project_id) as $task) {
                $volunteer_name = trim((string) ($task['volunteer_name'] ?? ''));

                if ($volunteer_name === '' || !empty($task['done'])) {
                    continue;
                }

                $rows[] = [
                    'volunteer' => $volunteer_name,
                    'project_id' => $project_id,
                    'project'   => get_the_title($project_id),
                    'task'      => trim((string) ($task['task'] ?? '')),
                    'hours'     => max(0, intval($task['est_hours'] ?? 0)),
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $volunteer_compare = strcasecmp($a['volunteer'], $b['volunteer']);

            if ($volunteer_compare !== 0) {
                return $volunteer_compare;
            }

            $project_compare = strcasecmp($a['project'], $b['project']);

            if ($project_compare !== 0) {
                return $project_compare;
            }

            return strcasecmp($a['task'], $b['task']);
        });

        return $rows;
    }
}

if (!function_exists('shed_render_volunteer_commitments_report')) {
    function shed_render_volunteer_commitments_report() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view volunteer commitments.</p>';
        }

        $rows = shed_get_volunteer_commitment_rows();

        ob_start();
        ?>
        <div class="shed-volunteer-commitments-report">
            <style>
                .shed-volunteer-commitments-report {
                    max-width: 1000px;
                    margin: 0 auto;
                    color: #111;
                    font-family: Arial, sans-serif;
                }

                .shed-volunteer-commitments-report h2 {
                    margin-bottom: 18px;
                }

                .shed-volunteer-commitments-actions {
                    margin-bottom: 18px;
                }

                .shed-volunteer-commitments-actions button {
                    padding: 10px 16px;
                    border: 0;
                    border-radius: 8px;
                    background: #111;
                    color: #fff;
                    font-weight: 700;
                    cursor: pointer;
                }

                .shed-volunteer-commitments-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #fff;
                }

                .shed-volunteer-commitments-table th,
                .shed-volunteer-commitments-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #ddd;
                    text-align: left;
                    vertical-align: top;
                }

                .shed-volunteer-commitments-table th {
                    background: #f3f4f6;
                    font-weight: 700;
                }

                .shed-volunteer-commitments-table .shed-hours {
                    text-align: right;
                    white-space: nowrap;
                }

                @media print {
                    body * {
                        visibility: hidden;
                    }

                    .shed-volunteer-commitments-report,
                    .shed-volunteer-commitments-report * {
                        visibility: visible;
                    }

                    .shed-volunteer-commitments-report {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        max-width: none;
                    }

                    .shed-volunteer-commitments-actions {
                        display: none;
                    }
                }
            </style>

            <h2>Volunteer commitments</h2>

            <div class="shed-volunteer-commitments-actions">
                <button type="button" onclick="window.print()">Print report</button>
            </div>

            <?php if (!empty($rows)) : ?>
                <table class="shed-volunteer-commitments-table">
                    <thead>
                        <tr>
                            <th>Volunteer</th>
                            <th>Project</th>
                            <th>Task</th>
                            <th class="shed-hours">Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['volunteer']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg('project_id', $row['project_id'], site_url('/home/members-area/create-project/'))); ?>">
                                        <?php echo esc_html($row['project']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($row['task']); ?></td>
                                <td class="shed-hours"><?php echo esc_html((string) $row['hours']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No current volunteer commitments found.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('shed_volunteer_commitments_report', 'shed_render_volunteer_commitments_report');
