<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Report on active registered members who have visited, but whose latest actual
 * check-in was more than 30 calendar days ago.
 */
function shed_attendance_absent_members_report() {
    global $wpdb;
    $members = shed_attendance_members_table();
    $events = shed_attendance_events_table();
    $today = new DateTimeImmutable(current_time('Y-m-d'), wp_timezone());
    $rows = $wpdb->get_results(
        "SELECT m.id, m.member_name, m.handle, MAX(e.event_time) AS last_checkin
         FROM {$members} m
         INNER JOIN {$events} e ON e.card_id = m.card_id
         WHERE m.is_active = 1 AND m.is_pending = 0
           AND e.card_state = 1 AND e.is_deemed = 0
         GROUP BY m.id, m.member_name, m.handle
         ORDER BY last_checkin ASC"
    );
    $absent = [];
    foreach ($rows as $row) {
        $last_day = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $row->last_checkin, 0, 10), wp_timezone());
        if (!$last_day) { continue; }
        $days = (int) $last_day->diff($today)->format('%r%a');
        if ($days > 30) {
            $absent[] = ['name' => $row->member_name, 'handle' => $row->handle,
                         'date' => $last_day->format('d/m/Y'), 'days' => $days];
        }
    }
    ob_start();
    echo '<p>Active registered members whose latest recorded genuine check-in was more than 30 calendar days ago. Automatic (deemed) entries are excluded. Members who have never checked in are not shown.</p>';
    echo '<p><strong>' . esc_html((string) count($absent)) . ' members</strong></p>';
    echo '<table class="widefat striped"><thead><tr><th>Member</th><th>Handle</th><th>Last check-in</th><th>Days since last check-in</th></tr></thead><tbody>';
    if (!$absent) {
        echo '<tr><td colspan="4">No active members with a recorded check-in more than 30 days ago.</td></tr>';
    }
    foreach ($absent as $row) {
        echo '<tr><td>' . esc_html($row['name']) . '</td><td>' . esc_html($row['handle']) . '</td><td>' . esc_html($row['date']) . '</td><td><strong>' . esc_html((string) $row['days']) . '</strong></td></tr>';
    }
    echo '</tbody></table>';
    return ob_get_clean();
}
