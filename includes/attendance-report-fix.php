<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build attendance sessions without allowing an entry on one calendar day to
 * be paired with a leave event on another day.
 *
 * This function is loaded before includes/attendance.php. That file wraps its
 * own implementation in function_exists(), so this safer implementation takes
 * precedence without changing the attendance event data itself.
 */
if (!function_exists('shed_attendance_get_attendance_sessions')) {
    function shed_attendance_get_attendance_sessions($start, $end) {
        $events = shed_attendance_get_events_for_period($start, $end);
        $events_by_card_and_day = [];
        $sessions = [];
        $maximum_session_seconds = 12 * HOUR_IN_SECONDS;

        foreach ($events as $event) {
            $card_id = shed_attendance_normalize_card_id($event->card_id);

            if ($card_id === '') {
                continue;
            }

            $event_day = substr((string) $event->event_time, 0, 10);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_day)) {
                continue;
            }

            if (!isset($events_by_card_and_day[$card_id])) {
                $events_by_card_and_day[$card_id] = [];
            }

            if (!isset($events_by_card_and_day[$card_id][$event_day])) {
                $events_by_card_and_day[$card_id][$event_day] = [];
            }

            $events_by_card_and_day[$card_id][$event_day][] = $event;
        }

        $now = current_time('mysql');
        $now_day = substr($now, 0, 10);
        $now_ts = shed_attendance_datetime_to_timestamp($now);
        $period_start_ts = shed_attendance_datetime_to_timestamp($start);
        $period_end_ts = shed_attendance_datetime_to_timestamp($end);
        $report_includes_now = $now_ts >= $period_start_ts && $now_ts < $period_end_ts;

        foreach ($events_by_card_and_day as $card_id => $days) {
            foreach ($days as $event_day => $day_events) {
                $open_event = null;

                foreach ($day_events as $event) {
                    $event_state = intval($event->card_state);

                    if ($event_state === 1) {
                        // Keep the most recent unmatched entry on this day. A
                        // second IN before an OUT is treated as a correction or
                        // duplicate rather than extending a session backwards.
                        $open_event = $event;
                        continue;
                    }

                    if (!$open_event) {
                        // A leave without an earlier entry on the same day is
                        // not a reliable duration boundary.
                        continue;
                    }

                    $entry_time = shed_attendance_datetime_to_timestamp($open_event->event_time);
                    $exit_time = shed_attendance_datetime_to_timestamp($event->event_time);
                    $duration = $exit_time - $entry_time;

                    if ($entry_time > 0 && $exit_time > $entry_time && $duration <= $maximum_session_seconds) {
                        $sessions[] = [
                            'card_id'       => $card_id,
                            'member_name'   => $open_event->member_name ?: $event->member_name,
                            'handle'        => $open_event->handle ?: $event->handle,
                            'entry_time'    => $open_event->event_time,
                            'exit_time'     => $event->event_time,
                            'duration'      => $duration,
                            'is_open'       => false,
                            'has_deemed'    => intval($open_event->is_deemed) === 1 || intval($event->is_deemed) === 1,
                        ];
                    }

                    // Whether valid or suspicious, this leave consumes the
                    // current same-day entry so it cannot leak into another
                    // pairing.
                    $open_event = null;
                }

                // Only a genuinely open session for today may run up to now.
                // Historical unmatched entries are deliberately ignored.
                if ($open_event && $report_includes_now && $event_day === $now_day) {
                    $entry_time = shed_attendance_datetime_to_timestamp($open_event->event_time);
                    $duration = $now_ts - $entry_time;

                    if ($entry_time > 0 && $now_ts > $entry_time && $duration <= $maximum_session_seconds) {
                        $sessions[] = [
                            'card_id'       => $card_id,
                            'member_name'   => $open_event->member_name,
                            'handle'        => $open_event->handle,
                            'entry_time'    => $open_event->event_time,
                            'exit_time'     => $now,
                            'duration'      => $duration,
                            'is_open'       => true,
                            'has_deemed'    => intval($open_event->is_deemed) === 1,
                        ];
                    }
                }
            }
        }

        return $sessions;
    }
}
