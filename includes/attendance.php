<?php

if (!defined('ABSPATH')) {
    exit;
}

define('SHED_ATTENDANCE_DB_VERSION', '1.4.1');

add_action('admin_init', 'shed_attendance_maybe_install');
add_action('rest_api_init', 'shed_attendance_maybe_install', 1);
add_action('admin_menu', 'shed_attendance_admin_menu');
add_action('rest_api_init', 'shed_attendance_register_routes');
add_action('admin_post_shed_attendance_save_member', 'shed_attendance_handle_save_member');
add_action('admin_post_shed_attendance_save_duty_settings', 'shed_attendance_handle_save_duty_settings');
add_action('admin_post_shed_attendance_save_duty_rota', 'shed_attendance_handle_save_duty_rota');
add_action('admin_post_shed_attendance_export_events', 'shed_attendance_handle_export_events');
add_action('admin_post_shed_attendance_export_duration_report', 'shed_attendance_handle_export_duration_report');
add_action('shed_attendance_daily_closeout', 'shed_attendance_run_daily_closeout');
add_shortcode('shed_attendance_today', 'shed_attendance_today_shortcode');
add_shortcode('shed_attendance_now', 'shed_attendance_now_shortcode');
add_shortcode('shed_attendance_duration_report', 'shed_attendance_duration_report_shortcode');
add_shortcode('shed_duty_rota', 'shed_duty_rota_shortcode');
add_shortcode('shed_attendance_events', 'shed_attendance_events_shortcode');

if (!function_exists('shed_attendance_activate')) {
    function shed_attendance_activate() {
        shed_attendance_install_tables();
        shed_attendance_schedule_daily_closeout();
    }
}

if (!function_exists('shed_attendance_deactivate')) {
    function shed_attendance_deactivate() {
        wp_clear_scheduled_hook('shed_attendance_daily_closeout');
    }
}

if (!function_exists('shed_attendance_maybe_install')) {
    function shed_attendance_maybe_install() {
        if (get_option('shed_attendance_db_version') !== SHED_ATTENDANCE_DB_VERSION) {
            shed_attendance_install_tables();
        }

        shed_attendance_schedule_daily_closeout();
    }
}

if (!function_exists('shed_attendance_install_tables')) {
    function shed_attendance_install_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $members_table = shed_attendance_members_table();
        $events_table = shed_attendance_events_table();
        $duty_rota_table = shed_attendance_duty_rota_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(
            "CREATE TABLE {$members_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                card_id varchar(64) NOT NULL,
                member_name varchar(190) NOT NULL,
                handle varchar(100) NOT NULL DEFAULT '',
                is_active tinyint(1) NOT NULL DEFAULT 1,
                is_pending tinyint(1) NOT NULL DEFAULT 0,
                first_seen_at datetime NULL,
                last_seen_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY card_id (card_id),
                KEY member_name (member_name),
                KEY is_pending (is_pending)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$events_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                client_event_id varchar(100) DEFAULT NULL,
                card_id varchar(64) NOT NULL,
                member_id bigint(20) unsigned DEFAULT NULL,
                event_time datetime NOT NULL,
                direction varchar(10) NOT NULL DEFAULT '',
                card_state tinyint(1) NOT NULL DEFAULT 0,
                is_deemed tinyint(1) NOT NULL DEFAULT 0,
                source varchar(50) NOT NULL DEFAULT 'esp32',
                note varchar(190) NOT NULL DEFAULT '',
                raw_payload longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY client_event_id (client_event_id),
                KEY card_id (card_id),
                KEY member_id (member_id),
                KEY event_time (event_time),
                KEY is_deemed (is_deemed)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$duty_rota_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                rota_date date NOT NULL,
                availability varchar(30) NOT NULL DEFAULT 'closed',
                duty_manager_handle varchar(100) NOT NULL DEFAULT '',
                note varchar(190) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY rota_date (rota_date),
                KEY availability (availability),
                KEY duty_manager_handle (duty_manager_handle)
            ) {$charset_collate};"
        );

        update_option('shed_attendance_db_version', SHED_ATTENDANCE_DB_VERSION);
    }
}

if (!function_exists('shed_attendance_members_table')) {
    function shed_attendance_members_table() {
        global $wpdb;
        return $wpdb->prefix . 'shed_attendance_members';
    }
}

if (!function_exists('shed_attendance_events_table')) {
    function shed_attendance_events_table() {
        global $wpdb;
        return $wpdb->prefix . 'shed_attendance_events';
    }
}

if (!function_exists('shed_attendance_duty_rota_table')) {
    function shed_attendance_duty_rota_table() {
        global $wpdb;
        return $wpdb->prefix . 'shed_duty_rota';
    }
}

if (!function_exists('shed_attendance_schedule_daily_closeout')) {
    function shed_attendance_schedule_daily_closeout() {
        if (wp_next_scheduled('shed_attendance_daily_closeout')) {
            return;
        }

        $run_time = new DateTimeImmutable('today 18:00:00', wp_timezone());

        if ($run_time->getTimestamp() <= time()) {
            $run_time = $run_time->modify('+1 day');
        }

        wp_schedule_event($run_time->getTimestamp(), 'daily', 'shed_attendance_daily_closeout');
    }
}

if (!function_exists('shed_attendance_register_routes')) {
    function shed_attendance_register_routes() {
        register_rest_route('shed/v1', '/prox-card', [
            'methods'             => [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE],
            'callback'            => 'shed_attendance_handle_prox_card_request',
            'permission_callback' => 'shed_attendance_rest_permission',
        ]);
    }
}

if (!function_exists('shed_attendance_rest_permission')) {
    function shed_attendance_rest_permission(WP_REST_Request $request) {
        $expected_key = trim((string) get_option('shed_attendance_api_key', ''));

        if ($expected_key === '') {
            return true;
        }

        $provided_key = trim((string) $request->get_header('x-shed-prox-key'));

        if ($provided_key === '') {
            $provided_key = trim((string) $request->get_param('api_key'));
        }

        return hash_equals($expected_key, $provided_key);
    }
}

if (!function_exists('shed_attendance_handle_prox_card_request')) {
    function shed_attendance_handle_prox_card_request(WP_REST_Request $request) {
        if ($request->get_method() === 'GET') {
            if ($request->get_param('sync') === 'cards') {
                return [
                    'ok'    => true,
                    'cards' => shed_attendance_get_card_cache_payload(),
                    'rota'  => shed_attendance_get_duty_rota_payload(current_time('Y-m-d')),
                ];
            }

            if ($request->get_param('sync') === 'rota') {
                return [
                    'ok'   => true,
                    'rota' => shed_attendance_get_duty_rota_payload(current_time('Y-m-d')),
                ];
            }

            return [
                'ok'      => true,
                'service' => 'shed-attendance',
                'rota'    => shed_attendance_get_duty_rota_payload(current_time('Y-m-d')),
                'message' => 'Send lookupValue to look up a card, or CardID and timestamp to log an attendance event.',
            ];
        }

        $params = $request->get_json_params();

        if (!is_array($params)) {
            $params = [];
        }

        if (isset($params['lookupValue'])) {
            return shed_attendance_lookup_card((string) $params['lookupValue']);
        }

        if (isset($params['CardID'])) {
            return shed_attendance_log_card_event(
                (string) $params['CardID'],
                isset($params['timestamp']) ? (string) $params['timestamp'] : '',
                $params
            );
        }

        return new WP_Error(
            'shed_attendance_bad_request',
            'Send lookupValue for a member lookup, or CardID and timestamp to log an event.',
            ['status' => 400]
        );
    }
}

if (!function_exists('shed_attendance_lookup_card')) {
    function shed_attendance_lookup_card($card_id) {
        $card_id = shed_attendance_normalize_card_id($card_id);

        if ($card_id === '') {
            return new WP_Error('shed_attendance_missing_card', 'Card ID is required.', ['status' => 400]);
        }

        $member = shed_attendance_get_member_by_card($card_id);
        $last_event = shed_attendance_get_last_event_for_day($card_id, current_time('Y-m-d'));
        $card_state = $last_event ? intval($last_event->card_state) : 0;

        if (!$member) {
            shed_attendance_record_unknown_card($card_id);
            $member = shed_attendance_get_member_by_card($card_id);
        }

        if (!$member || intval($member->is_active) !== 1 || intval($member->is_pending) === 1) {
            return [
                'handle'    => 'Unknown card',
                'cardState' => 0,
                'eventType' => 0,
                'known'     => false,
                'rota'      => shed_attendance_get_duty_rota_payload(current_time('Y-m-d')),
                'message'   => 'Card not recognised',
            ];
        }

        $handle = trim((string) $member->handle);

        if ($handle === '') {
            $handle = $member->member_name;
        }

        return [
            'handle'    => $handle,
            'cardState' => $card_state,
            'eventType' => $card_state,
            'known'     => true,
            'rota'      => shed_attendance_get_duty_rota_payload(current_time('Y-m-d')),
            'message'   => $card_state ? 'Goodbye ' . $handle : 'Welcome ' . $handle,
        ];
    }
}

if (!function_exists('shed_attendance_log_card_event')) {
    function shed_attendance_log_card_event($card_id, $timestamp, array $payload) {
        global $wpdb;

        $card_id = shed_attendance_normalize_card_id($card_id);

        if ($card_id === '') {
            return new WP_Error('shed_attendance_missing_card', 'Card ID is required.', ['status' => 400]);
        }

        $member = shed_attendance_get_member_by_card($card_id);
        $event_time = shed_attendance_normalize_timestamp($timestamp);
        $event_date = substr($event_time, 0, 10);
        $client_event_id = isset($payload['eventId']) ? sanitize_text_field((string) $payload['eventId']) : '';

        if (!$member) {
            shed_attendance_record_unknown_card($card_id);
            $member = shed_attendance_get_member_by_card($card_id);
        }

        $last_event = shed_attendance_get_last_event_for_day($card_id, $event_date);
        $new_state = ($last_event && intval($last_event->card_state) === 1) ? 0 : 1;

        if (isset($payload['eventType'])) {
            $new_state = intval($payload['eventType']) === 1 ? 1 : 0;
        } elseif (isset($payload['cardState'])) {
            $new_state = intval($payload['cardState']) === 1 ? 1 : 0;
        }

        $direction = $new_state === 1 ? 'enter' : 'leave';

        if ($client_event_id !== '') {
            $existing = shed_attendance_get_event_by_client_id($client_event_id);

            if ($existing) {
                return [
                    'ok'        => true,
                    'duplicate' => true,
                    'known'     => (bool) $member,
                    'handle'    => $member ? shed_attendance_member_handle($member) : 'Unknown card',
                    'cardState' => intval($existing->card_state),
                    'eventType' => intval($existing->card_state),
                    'direction' => $existing->direction,
                    'eventTime' => $existing->event_time,
                ];
            }
        }

        $event_id = shed_attendance_insert_event(
            $card_id,
            $member,
            $event_time,
            $new_state,
            'esp32',
            false,
            '',
            $payload,
            $client_event_id
        );

        if (!$event_id) {
            return new WP_Error(
                'shed_attendance_log_failed',
                'Unable to save attendance event.',
                [
                    'status'   => 500,
                    'db_error' => defined('WP_DEBUG') && WP_DEBUG ? $wpdb->last_error : '',
                ]
            );
        }

        return [
            'ok'        => true,
            'known'     => (bool) $member,
            'handle'    => $member ? shed_attendance_member_handle($member) : 'Unknown card',
            'cardState' => $new_state,
            'eventType' => $new_state,
            'direction' => $direction,
            'eventTime' => $event_time,
        ];
    }
}

if (!function_exists('shed_attendance_normalize_card_id')) {
    function shed_attendance_normalize_card_id($card_id) {
        $card_id = strtoupper(trim((string) $card_id));
        return preg_replace('/[^A-F0-9]/', '', $card_id);
    }
}

if (!function_exists('shed_attendance_normalize_timestamp')) {
    function shed_attendance_normalize_timestamp($timestamp) {
        $timestamp = trim((string) $timestamp);

        if ($timestamp !== '') {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp, wp_timezone());

            if ($date instanceof DateTime) {
                return $date->format('Y-m-d H:i:s');
            }

            $date = DateTime::createFromFormat('d/m/Y H:i:s', $timestamp, wp_timezone());

            if ($date instanceof DateTime) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return current_time('mysql');
    }
}

if (!function_exists('shed_attendance_normalize_rota_date')) {
    function shed_attendance_normalize_rota_date($date) {
        $date = trim((string) $date);

        if ($date === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $parsed = DateTime::createFromFormat('d/m/Y', $date, wp_timezone());

        if ($parsed instanceof DateTime) {
            return $parsed->format('Y-m-d');
        }

        return '';
    }
}

if (!function_exists('shed_attendance_normalize_availability')) {
    function shed_attendance_normalize_availability($availability) {
        $availability = sanitize_key((string) $availability);
        $allowed = ['open', 'closed', 'unavailable'];

        return in_array($availability, $allowed, true) ? $availability : 'open';
    }
}

if (!function_exists('shed_attendance_get_availability_label')) {
    function shed_attendance_get_availability_label($availability) {
        $labels = [
            'open'        => 'Open',
            'closed'      => 'Closed',
            'unavailable' => 'Unavailable',
        ];

        return $labels[$availability] ?? 'Open';
    }
}

if (!function_exists('shed_attendance_day_bounds')) {
    function shed_attendance_day_bounds($date) {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) ? $date : current_time('Y-m-d');
        $start = $date . ' 00:00:00';
        $end = (new DateTimeImmutable($start, wp_timezone()))->modify('+1 day')->format('Y-m-d H:i:s');

        return [$start, $end];
    }
}

if (!function_exists('shed_attendance_get_member_by_card')) {
    function shed_attendance_get_member_by_card($card_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_members_table() . ' WHERE card_id = %s LIMIT 1',
                shed_attendance_normalize_card_id($card_id)
            )
        );
    }
}

if (!function_exists('shed_attendance_get_card_cache_payload')) {
    function shed_attendance_get_card_cache_payload() {
        $members = shed_attendance_get_members();
        $cards = [];

        foreach ($members as $member) {
            if (intval($member->is_active) !== 1 || intval($member->is_pending) === 1) {
                continue;
            }

            $cards[] = [
                'cardID' => (string) $member->card_id,
                'handle' => shed_attendance_member_handle($member),
            ];
        }

        return $cards;
    }
}

if (!function_exists('shed_attendance_record_unknown_card')) {
    function shed_attendance_record_unknown_card($card_id) {
        global $wpdb;

        $card_id = shed_attendance_normalize_card_id($card_id);

        if ($card_id === '') {
            return 0;
        }

        $existing = shed_attendance_get_member_by_card($card_id);
        $now = current_time('mysql');

        if ($existing) {
            $wpdb->update(
                shed_attendance_members_table(),
                [
                    'last_seen_at' => $now,
                    'updated_at'   => $now,
                ],
                ['id' => intval($existing->id)],
                ['%s', '%s'],
                ['%d']
            );

            return intval($existing->id);
        }

        $inserted = $wpdb->insert(
            shed_attendance_members_table(),
            [
                'card_id'       => $card_id,
                'member_name'   => 'Unknown member',
                'handle'        => '',
                'is_active'     => 0,
                'is_pending'    => 1,
                'first_seen_at' => $now,
                'last_seen_at'  => $now,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $inserted ? intval($wpdb->insert_id) : 0;
    }
}

if (!function_exists('shed_attendance_get_last_event')) {
    function shed_attendance_get_last_event($card_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_events_table() . ' WHERE card_id = %s ORDER BY event_time DESC, id DESC LIMIT 1',
                shed_attendance_normalize_card_id($card_id)
            )
        );
    }
}

if (!function_exists('shed_attendance_get_last_event_for_day')) {
    function shed_attendance_get_last_event_for_day($card_id, $date) {
        global $wpdb;

        list($start, $end) = shed_attendance_day_bounds($date);

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_events_table() . ' WHERE card_id = %s AND event_time >= %s AND event_time < %s ORDER BY event_time DESC, id DESC LIMIT 1',
                shed_attendance_normalize_card_id($card_id),
                $start,
                $end
            )
        );
    }
}

if (!function_exists('shed_attendance_get_event_by_client_id')) {
    function shed_attendance_get_event_by_client_id($client_event_id) {
        global $wpdb;

        $client_event_id = sanitize_text_field((string) $client_event_id);

        if ($client_event_id === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_events_table() . ' WHERE client_event_id = %s LIMIT 1',
                $client_event_id
            )
        );
    }
}

if (!function_exists('shed_attendance_get_events_for_day')) {
    function shed_attendance_get_events_for_day($card_id, $date) {
        global $wpdb;

        list($start, $end) = shed_attendance_day_bounds($date);

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_events_table() . ' WHERE card_id = %s AND event_time >= %s AND event_time < %s ORDER BY event_time ASC, id ASC',
                shed_attendance_normalize_card_id($card_id),
                $start,
                $end
            )
        );
    }
}

if (!function_exists('shed_attendance_insert_event')) {
    function shed_attendance_insert_event($card_id, $member, $event_time, $event_type, $source, $is_deemed = false, $note = '', array $payload = [], $client_event_id = '') {
        global $wpdb;

        $event_type = intval($event_type) === 1 ? 1 : 0;
        $direction = $event_type === 1 ? 'enter' : 'leave';
        $member_id = ($member && intval($member->is_pending) !== 1 && intval($member->is_active) === 1) ? intval($member->id) : null;
        $inserted = $wpdb->insert(
            shed_attendance_events_table(),
            [
                'client_event_id' => $client_event_id !== '' ? sanitize_text_field((string) $client_event_id) : null,
                'card_id'     => shed_attendance_normalize_card_id($card_id),
                'member_id'   => $member_id,
                'event_time'  => $event_time,
                'direction'   => $direction,
                'card_state'  => $event_type,
                'is_deemed'   => $is_deemed ? 1 : 0,
                'source'      => sanitize_key($source),
                'note'        => sanitize_text_field($note),
                'raw_payload' => !empty($payload) ? wp_json_encode($payload) : null,
                'created_at'  => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $inserted ? intval($wpdb->insert_id) : 0;
    }
}

if (!function_exists('shed_attendance_deemed_event_exists')) {
    function shed_attendance_deemed_event_exists($card_id, $event_time, $event_type) {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . shed_attendance_events_table() . ' WHERE card_id = %s AND event_time = %s AND card_state = %d AND is_deemed = 1',
                shed_attendance_normalize_card_id($card_id),
                $event_time,
                intval($event_type)
            )
        );
    }
}

if (!function_exists('shed_attendance_member_handle')) {
    function shed_attendance_member_handle($member) {
        $handle = trim((string) $member->handle);
        return $handle !== '' ? $handle : (string) $member->member_name;
    }
}

if (!function_exists('shed_attendance_run_daily_closeout')) {
    function shed_attendance_run_daily_closeout($date = '') {
        global $wpdb;

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) ? $date : current_time('Y-m-d');
        $members = shed_attendance_get_members();
        $noon = $date . ' 12:00:00';
        $deemed_entry_time = $date . ' 10:00:00';
        $deemed_exit_time = $date . ' 15:00:00';
        $changes = [
            'deemed_entries' => 0,
            'deemed_exits'   => 0,
            'adjusted_exits' => 0,
        ];

        foreach ($members as $member) {
            if (intval($member->is_active) !== 1) {
                continue;
            }

            $events = shed_attendance_get_events_for_day($member->card_id, $date);

            if (empty($events)) {
                continue;
            }

            if (count($events) === 1 && intval($events[0]->card_state) === 1 && $events[0]->event_time > $noon) {
                $wpdb->update(
                    shed_attendance_events_table(),
                    [
                        'direction'  => 'leave',
                        'card_state' => 0,
                        'note'       => 'Adjusted to exit by daily close-out: single afternoon tap.',
                    ],
                    ['id' => intval($events[0]->id)],
                    ['%s', '%d', '%s'],
                    ['%d']
                );
                $changes['adjusted_exits']++;

                if (!shed_attendance_deemed_event_exists($member->card_id, $deemed_entry_time, 1)) {
                    shed_attendance_insert_event(
                        $member->card_id,
                        $member,
                        $deemed_entry_time,
                        1,
                        'daily-closeout',
                        true,
                        'Deemed entry at 10:00: single afternoon tap without morning entry.'
                    );
                    $changes['deemed_entries']++;
                }

                continue;
            }

            $last_event = end($events);

            if ($last_event && intval($last_event->card_state) === 1 && $last_event->event_time <= $noon) {
                if (!shed_attendance_deemed_event_exists($member->card_id, $deemed_exit_time, 0)) {
                    shed_attendance_insert_event(
                        $member->card_id,
                        $member,
                        $deemed_exit_time,
                        0,
                        'daily-closeout',
                        true,
                        'Deemed exit at 15:00: member still marked in after a morning entry.'
                    );
                    $changes['deemed_exits']++;
                }
            }
        }

        update_option('shed_attendance_last_closeout', [
            'date'    => $date,
            'run_at'  => current_time('mysql'),
            'changes' => $changes,
        ]);

        return $changes;
    }
}

if (!function_exists('shed_attendance_admin_menu')) {
    function shed_attendance_admin_menu() {
        add_menu_page(
            'Shed Attendance',
            'Shed Attendance',
            'manage_options',
            'shed-attendance',
            'shed_attendance_render_admin_page',
            'dashicons-id',
            31
        );

        add_submenu_page(
            'shed-attendance',
            'Shed Attendance Reports',
            'Reports',
            'manage_options',
            'shed-attendance-reports',
            'shed_attendance_render_reports_page'
        );
    }
}

if (!function_exists('shed_attendance_handle_save_member')) {
    function shed_attendance_handle_save_member() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage attendance cards.');
        }

        check_admin_referer('shed_attendance_save_member');

        global $wpdb;

        $card_id = isset($_POST['card_id']) ? shed_attendance_normalize_card_id(wp_unslash($_POST['card_id'])) : '';
        $member_name = isset($_POST['member_name']) ? sanitize_text_field(wp_unslash($_POST['member_name'])) : '';
        $handle = isset($_POST['handle']) ? sanitize_text_field(wp_unslash($_POST['handle'])) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_pending = ($member_name === 'Unknown member' || $handle === '') ? 1 : 0;
        $now = current_time('mysql');

        if ($card_id === '' || $member_name === '') {
            wp_safe_redirect(add_query_arg('shed_attendance_error', 'missing_member_fields', shed_attendance_admin_url()));
            exit;
        }

        $existing = shed_attendance_get_member_by_card($card_id);

        if ($existing) {
            $wpdb->update(
                shed_attendance_members_table(),
                [
                    'member_name' => $member_name,
                    'handle'      => $handle,
                    'is_active'   => $is_active,
                    'is_pending'  => $is_pending,
                    'updated_at'  => $now,
                ],
                ['id' => intval($existing->id)],
                ['%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                shed_attendance_members_table(),
                [
                    'card_id'     => $card_id,
                    'member_name' => $member_name,
                    'handle'      => $handle,
                    'is_active'   => $is_active,
                    'is_pending'  => $is_pending,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
        }

        if (isset($_POST['api_key'])) {
            update_option('shed_attendance_api_key', sanitize_text_field(wp_unslash($_POST['api_key'])));
        }

        wp_safe_redirect(add_query_arg('shed_attendance_saved', '1', shed_attendance_admin_url()));
        exit;
    }
}

if (!function_exists('shed_attendance_admin_url')) {
    function shed_attendance_admin_url() {
        return admin_url('admin.php?page=shed-attendance');
    }
}

if (!function_exists('shed_attendance_handle_save_duty_settings')) {
    function shed_attendance_handle_save_duty_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage duty managers.');
        }

        check_admin_referer('shed_attendance_save_duty_settings');

        $raw_handles = isset($_POST['duty_manager_handles']) ? wp_unslash($_POST['duty_manager_handles']) : '';
        $handles = preg_split('/\r\n|\r|\n/', (string) $raw_handles);
        $handles = array_map('sanitize_text_field', is_array($handles) ? $handles : []);
        $handles = array_filter(array_map('trim', $handles));

        update_option('shed_attendance_duty_manager_handles', implode("\n", array_values(array_unique($handles))));

        wp_safe_redirect(add_query_arg('shed_attendance_saved', '1', shed_attendance_admin_url()));
        exit;
    }
}

if (!function_exists('shed_attendance_handle_save_duty_rota')) {
    function shed_attendance_handle_save_duty_rota() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage the duty rota.');
        }

        check_admin_referer('shed_attendance_save_duty_rota');

        global $wpdb;

        $rota_date = isset($_POST['rota_date']) ? shed_attendance_normalize_rota_date(wp_unslash($_POST['rota_date'])) : '';
        $availability = isset($_POST['availability']) ? shed_attendance_normalize_availability(wp_unslash($_POST['availability'])) : 'open';
        $duty_manager_handle = isset($_POST['duty_manager_handle']) ? sanitize_text_field(wp_unslash($_POST['duty_manager_handle'])) : '';
        $note = isset($_POST['rota_note']) ? sanitize_text_field(wp_unslash($_POST['rota_note'])) : '';
        $now = current_time('mysql');

        if ($rota_date === '') {
            wp_safe_redirect(add_query_arg('shed_attendance_error', 'missing_rota_date', shed_attendance_admin_url()));
            exit;
        }

        $existing = shed_attendance_get_duty_rota_row($rota_date);

        if ($existing) {
            $wpdb->update(
                shed_attendance_duty_rota_table(),
                [
                    'availability'        => $availability,
                    'duty_manager_handle' => $duty_manager_handle,
                    'note'                => $note,
                    'updated_at'          => $now,
                ],
                ['id' => intval($existing->id)],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                shed_attendance_duty_rota_table(),
                [
                    'rota_date'           => $rota_date,
                    'availability'        => $availability,
                    'duty_manager_handle' => $duty_manager_handle,
                    'note'                => $note,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        wp_safe_redirect(add_query_arg('shed_attendance_saved', '1', shed_attendance_admin_url()));
        exit;
    }
}

if (!function_exists('shed_attendance_handle_export_events')) {
    function shed_attendance_handle_export_events() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export attendance records.');
        }

        check_admin_referer('shed_attendance_export_events');

        $rows = shed_attendance_get_event_rows(10000);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shed-attendance-events.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Card ID', 'Event Type', 'Timestamp', 'Direction', 'Member', 'Handle', 'Deemed', 'Note']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row->card_id,
                $row->card_state,
                $row->event_time,
                $row->direction,
                $row->member_name,
                $row->handle,
                intval($row->is_deemed) === 1 ? 'Yes' : 'No',
                $row->note,
            ]);
        }

        fclose($output);
        exit;
    }
}

if (!function_exists('shed_attendance_handle_export_duration_report')) {
    function shed_attendance_handle_export_duration_report() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export attendance reports.');
        }

        check_admin_referer('shed_attendance_export_duration_report');

        $period = isset($_POST['attendance_report_period']) ? sanitize_key(wp_unslash($_POST['attendance_report_period'])) : 'day';
        $date = isset($_POST['attendance_report_date']) ? sanitize_text_field(wp_unslash($_POST['attendance_report_date'])) : current_time('Y-m-d');
        $bounds = shed_attendance_report_period_bounds($period, $date);
        $sessions = shed_attendance_get_attendance_sessions($bounds['start'], $bounds['end']);
        $filename = 'shed-attendance-duration-' . sanitize_file_name($bounds['period'] . '-' . substr($bounds['start'], 0, 10)) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Period', 'Member', 'Card ID', 'Entry time', 'Exit time', 'Duration', 'Duration minutes', 'Still open', 'Includes deemed event']);

        foreach ($sessions as $session) {
            fputcsv($output, [
                $bounds['label'],
                $session['handle'] ?: $session['member_name'] ?: 'Unknown card',
                $session['card_id'],
                $session['entry_time'],
                $session['exit_time'],
                shed_attendance_format_duration($session['duration']),
                round(intval($session['duration']) / MINUTE_IN_SECONDS, 2),
                !empty($session['is_open']) ? 'Yes' : 'No',
                !empty($session['has_deemed']) ? 'Yes' : 'No',
            ]);
        }

        fclose($output);
        exit;
    }
}

if (!function_exists('shed_attendance_get_members')) {
    function shed_attendance_get_members() {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT * FROM ' . shed_attendance_members_table() . ' ORDER BY member_name ASC, card_id ASC'
        );
    }
}

if (!function_exists('shed_attendance_get_pending_members')) {
    function shed_attendance_get_pending_members() {
        global $wpdb;

        return $wpdb->get_results(
            'SELECT * FROM ' . shed_attendance_members_table() . ' WHERE is_pending = 1 ORDER BY last_seen_at DESC, created_at DESC'
        );
    }
}

if (!function_exists('shed_attendance_get_event_rows')) {
    function shed_attendance_get_event_rows($limit = 100) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT e.*, m.member_name, m.handle
                FROM ' . shed_attendance_events_table() . ' e
                LEFT JOIN ' . shed_attendance_members_table() . ' m ON e.member_id = m.id
                ORDER BY e.event_time DESC, e.id DESC
                LIMIT %d',
                max(1, intval($limit))
            )
        );
    }
}

if (!function_exists('shed_attendance_get_current_attendees')) {
    function shed_attendance_get_current_attendees($date = '') {
        global $wpdb;

        $date = shed_attendance_normalize_rota_date($date);
        list($start, $end) = shed_attendance_day_bounds($date !== '' ? $date : current_time('Y-m-d'));
        $events_table = shed_attendance_events_table();
        $members_table = shed_attendance_members_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, m.member_name, m.handle, m.is_pending, m.is_active
                FROM {$events_table} e
                LEFT JOIN {$members_table} m ON m.id = e.member_id OR (e.member_id IS NULL AND m.card_id = e.card_id)
                WHERE e.event_time >= %s
                    AND e.event_time < %s
                    AND e.card_state = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$events_table} later
                        WHERE later.card_id = e.card_id
                            AND later.event_time >= %s
                            AND later.event_time < %s
                            AND (
                                later.event_time > e.event_time
                                OR (later.event_time = e.event_time AND later.id > e.id)
                            )
                    )
                ORDER BY COALESCE(NULLIF(m.handle, ''), NULLIF(m.member_name, ''), e.card_id) ASC",
                $start,
                $end,
                $start,
                $end
            )
        );
    }
}

if (!function_exists('shed_attendance_datetime_to_timestamp')) {
    function shed_attendance_datetime_to_timestamp($datetime) {
        try {
            return (new DateTimeImmutable((string) $datetime, wp_timezone()))->getTimestamp();
        } catch (Exception $exception) {
            return 0;
        }
    }
}

if (!function_exists('shed_attendance_format_duration')) {
    function shed_attendance_format_duration($seconds) {
        $seconds = max(0, intval($seconds));
        $hours = intdiv($seconds, HOUR_IN_SECONDS);
        $minutes = intdiv($seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS);

        return sprintf('%d:%02d', $hours, $minutes);
    }
}

if (!function_exists('shed_attendance_report_period_bounds')) {
    function shed_attendance_report_period_bounds($period = 'day', $date = '') {
        $period = sanitize_key((string) $period);

        if (!in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $date = shed_attendance_normalize_rota_date($date);

        if ($date === '') {
            $date = current_time('Y-m-d');
        }

        $selected = new DateTimeImmutable($date . ' 12:00:00', wp_timezone());

        if ($period === 'week') {
            $start_date = $selected->modify('monday this week')->format('Y-m-d');
            $end_date = (new DateTimeImmutable($start_date . ' 00:00:00', wp_timezone()))->modify('+1 week')->format('Y-m-d');
            $label = 'Week commencing ' . wp_date('d/m/Y', strtotime($start_date . ' 12:00:00'));
        } elseif ($period === 'month') {
            $start_date = $selected->modify('first day of this month')->format('Y-m-d');
            $end_date = (new DateTimeImmutable($start_date . ' 00:00:00', wp_timezone()))->modify('+1 month')->format('Y-m-d');
            $label = wp_date('F Y', strtotime($start_date . ' 12:00:00'));
        } else {
            $start_date = $selected->format('Y-m-d');
            $end_date = (new DateTimeImmutable($start_date . ' 00:00:00', wp_timezone()))->modify('+1 day')->format('Y-m-d');
            $label = wp_date('d/m/Y', strtotime($start_date . ' 12:00:00'));
        }

        return [
            'period' => $period,
            'date'   => $date,
            'start'  => $start_date . ' 00:00:00',
            'end'    => $end_date . ' 00:00:00',
            'label'  => $label,
        ];
    }
}

if (!function_exists('shed_attendance_get_events_for_period')) {
    function shed_attendance_get_events_for_period($start, $end) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT e.*, m.member_name, m.handle
                FROM ' . shed_attendance_events_table() . ' e
                LEFT JOIN ' . shed_attendance_members_table() . ' m ON m.card_id = e.card_id
                WHERE e.event_time >= %s AND e.event_time < %s
                ORDER BY e.card_id ASC, e.event_time ASC, e.id ASC',
                $start,
                $end
            )
        );
    }
}

if (!function_exists('shed_attendance_get_attendance_sessions')) {
    function shed_attendance_get_attendance_sessions($start, $end) {
        $events = shed_attendance_get_events_for_period($start, $end);
        $open_events = [];
        $sessions = [];

        foreach ($events as $event) {
            $card_id = shed_attendance_normalize_card_id($event->card_id);
            $event_state = intval($event->card_state);

            if ($event_state === 1) {
                if (empty($open_events[$card_id])) {
                    $open_events[$card_id] = $event;
                }

                continue;
            }

            if (empty($open_events[$card_id])) {
                continue;
            }

            $entry = $open_events[$card_id];
            unset($open_events[$card_id]);
            $entry_time = shed_attendance_datetime_to_timestamp($entry->event_time);
            $exit_time = shed_attendance_datetime_to_timestamp($event->event_time);

            if ($entry_time <= 0 || $exit_time <= $entry_time) {
                continue;
            }

            $sessions[] = [
                'card_id'       => $card_id,
                'member_name'   => $entry->member_name ?: $event->member_name,
                'handle'        => $entry->handle ?: $event->handle,
                'entry_time'    => $entry->event_time,
                'exit_time'     => $event->event_time,
                'duration'      => $exit_time - $entry_time,
                'is_open'       => false,
                'has_deemed'    => intval($entry->is_deemed) === 1 || intval($event->is_deemed) === 1,
            ];
        }

        $now = current_time('mysql');
        $now_ts = shed_attendance_datetime_to_timestamp($now);
        $period_start_ts = shed_attendance_datetime_to_timestamp($start);
        $period_end_ts = shed_attendance_datetime_to_timestamp($end);

        if ($now_ts >= $period_start_ts && $now_ts < $period_end_ts) {
            foreach ($open_events as $card_id => $entry) {
                $entry_time = shed_attendance_datetime_to_timestamp($entry->event_time);

                if ($entry_time <= 0 || $now_ts <= $entry_time) {
                    continue;
                }

                $sessions[] = [
                    'card_id'       => $card_id,
                    'member_name'   => $entry->member_name,
                    'handle'        => $entry->handle,
                    'entry_time'    => $entry->event_time,
                    'exit_time'     => $now,
                    'duration'      => $now_ts - $entry_time,
                    'is_open'       => true,
                    'has_deemed'    => intval($entry->is_deemed) === 1,
                ];
            }
        }

        return $sessions;
    }
}

if (!function_exists('shed_attendance_get_duration_totals')) {
    function shed_attendance_get_duration_totals($sessions, $group_by_member = true) {
        $totals = [];

        foreach ($sessions as $session) {
            $key = $group_by_member ? $session['card_id'] : '_all';

            if (!isset($totals[$key])) {
                $totals[$key] = [
                    'member'       => $group_by_member ? ($session['handle'] ?: $session['member_name'] ?: 'Unknown card') : 'All members',
                    'card_id'      => $group_by_member ? $session['card_id'] : '',
                    'sessions'     => 0,
                    'seconds'      => 0,
                    'open_count'   => 0,
                    'deemed_count' => 0,
                ];
            }

            $totals[$key]['sessions']++;
            $totals[$key]['seconds'] += intval($session['duration']);

            if (!empty($session['is_open'])) {
                $totals[$key]['open_count']++;
            }

            if (!empty($session['has_deemed'])) {
                $totals[$key]['deemed_count']++;
            }
        }

        usort($totals, function ($a, $b) {
            return strcasecmp($a['member'], $b['member']);
        });

        return $totals;
    }
}

if (!function_exists('shed_attendance_get_qualified_duty_managers')) {
    function shed_attendance_get_qualified_duty_managers() {
        $raw = (string) get_option('shed_attendance_duty_manager_handles', '');
        $handles = preg_split('/\r\n|\r|\n/', $raw);
        $handles = array_map('trim', is_array($handles) ? $handles : []);
        $handles = array_filter($handles, function ($handle) {
            return $handle !== '';
        });

        return array_values(array_unique($handles));
    }
}

if (!function_exists('shed_attendance_get_duty_rota_row')) {
    function shed_attendance_get_duty_rota_row($date) {
        global $wpdb;

        $date = shed_attendance_normalize_rota_date($date);

        if ($date === '') {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_duty_rota_table() . ' WHERE rota_date = %s LIMIT 1',
                $date
            )
        );
    }
}

if (!function_exists('shed_attendance_get_duty_rota_payload')) {
    function shed_attendance_get_duty_rota_payload($date) {
        $date = shed_attendance_normalize_rota_date($date);
        $row = shed_attendance_get_duty_rota_row($date);
        $availability = $row ? shed_attendance_normalize_availability($row->availability) : 'closed';
        $handle = $row ? (string) $row->duty_manager_handle : '';

        return [
            'date'              => $date,
            'day'               => $date !== '' ? wp_date('l', strtotime($date . ' 12:00:00')) : '',
            'availability'      => $availability,
            'availabilityLabel' => shed_attendance_get_availability_label($availability),
            'dutyManager'       => $handle,
            'isOpen'            => $availability === 'open' || $handle !== '',
        ];
    }
}

if (!function_exists('shed_attendance_get_duty_rota_rows')) {
    function shed_attendance_get_duty_rota_rows($limit = 120) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_duty_rota_table() . ' ORDER BY rota_date DESC LIMIT %d',
                max(1, intval($limit))
            )
        );
    }
}

if (!function_exists('shed_attendance_get_upcoming_duty_rota_rows')) {
    function shed_attendance_get_upcoming_duty_rota_rows($days = 30) {
        global $wpdb;

        $start = current_time('Y-m-d');
        $end = (new DateTimeImmutable($start, wp_timezone()))->modify('+' . max(1, intval($days)) . ' days')->format('Y-m-d');

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . shed_attendance_duty_rota_table() . ' WHERE rota_date >= %s AND rota_date <= %s ORDER BY rota_date ASC',
                $start,
                $end
            )
        );
    }
}

if (!function_exists('shed_attendance_today_shortcode')) {
    function shed_attendance_today_shortcode($atts = []) {
        $atts = shortcode_atts(['date' => current_time('Y-m-d')], $atts, 'shed_attendance_today');
        $rota = shed_attendance_get_duty_rota_payload($atts['date']);

        ob_start();
        ?>
        <div class="shed-attendance-today">
            <p><strong><?php echo esc_html($rota['day']); ?> <?php echo esc_html(wp_date('d/m/Y', strtotime($rota['date'] . ' 12:00:00'))); ?></strong></p>
            <p>Status: <?php echo esc_html($rota['availabilityLabel']); ?></p>
            <?php if ($rota['dutyManager'] !== '') : ?>
                <p>Duty Manager: <?php echo esc_html($rota['dutyManager']); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('shed_attendance_render_current_attendees_table')) {
    function shed_attendance_render_current_attendees_table($rows) {
        ob_start();
        ?>
        <div class="shed-attendance-now">
            <p><strong>Members in attendance now: <?php echo esc_html((string) count($rows)); ?></strong></p>
            <p>Report time: <?php echo esc_html(current_time('d/m/Y H:i:s')); ?></p>
            <table class="shed-attendance-now-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Card ID</th>
                        <th>Tap-in time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)) : ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php $member_label = $row->handle ?: $row->member_name ?: 'Unknown card'; ?>
                            <tr>
                                <td><?php echo esc_html($member_label); ?></td>
                                <td><code><?php echo esc_html($row->card_id); ?></code></td>
                                <td><?php echo esc_html($row->event_time); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3">No members are currently shown as in attendance.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('shed_attendance_render_duration_report')) {
    function shed_attendance_render_duration_report($period = 'day', $date = '', $mode = 'member', $context = 'admin', $show_export = true) {
        $bounds = shed_attendance_report_period_bounds($period, $date);
        $mode = $mode === 'total' ? 'total' : 'member';
        $sessions = shed_attendance_get_attendance_sessions($bounds['start'], $bounds['end']);
        $totals = shed_attendance_get_duration_totals($sessions, $mode === 'member');
        $selected_date = substr($bounds['date'], 0, 10);
        $context = $context === 'frontend' ? 'frontend' : 'admin';
        $show_export = $show_export && $context === 'admin';
        $form_action = $context === 'admin' ? admin_url('admin.php') : remove_query_arg(['attendance_report_period', 'attendance_report_date', 'attendance_report_mode']);
        $total_seconds = array_sum(array_map(function ($row) {
            return intval($row['seconds']);
        }, $totals));

        ob_start();
        ?>
        <div class="shed-attendance-duration-report">
            <form method="get" action="<?php echo esc_url($form_action); ?>">
                <?php if ($context === 'admin') : ?>
                    <input type="hidden" name="page" value="shed-attendance-reports">
                <?php endif; ?>
                <table class="<?php echo $context === 'admin' ? 'form-table' : 'shed-attendance-report-controls'; ?>" role="presentation">
                    <tr>
                        <th scope="row"><label for="attendance_report_period">Period</label></th>
                        <td>
                            <select name="attendance_report_period" id="attendance_report_period">
                                <option value="day" <?php selected($bounds['period'], 'day'); ?>>Calendar day</option>
                                <option value="week" <?php selected($bounds['period'], 'week'); ?>>Calendar week</option>
                                <option value="month" <?php selected($bounds['period'], 'month'); ?>>Calendar month</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="attendance_report_date">Date</label></th>
                        <td>
                            <input name="attendance_report_date" id="attendance_report_date" type="date" value="<?php echo esc_attr($selected_date); ?>">
                            <p class="description">For week and month reports, choose any date inside the required period.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">View</th>
                        <td>
                            <label><input name="attendance_report_mode" type="radio" value="member" <?php checked($mode, 'member'); ?>> List by member</label>
                            <br>
                            <label><input name="attendance_report_mode" type="radio" value="total" <?php checked($mode, 'total'); ?>> Show overall total only</label>
                        </td>
                    </tr>
                </table>
                <?php if ($context === 'admin') : ?>
                    <?php submit_button('Run attendance report', 'secondary'); ?>
                <?php else : ?>
                    <p><button type="submit">Run attendance report</button></p>
                <?php endif; ?>
            </form>

            <?php if ($show_export) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('shed_attendance_export_duration_report'); ?>
                    <input type="hidden" name="action" value="shed_attendance_export_duration_report">
                    <input type="hidden" name="attendance_report_period" value="<?php echo esc_attr($bounds['period']); ?>">
                    <input type="hidden" name="attendance_report_date" value="<?php echo esc_attr($selected_date); ?>">
                    <?php submit_button('Export duration CSV', 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>

            <p><strong><?php echo esc_html($bounds['label']); ?></strong></p>
            <p>Total attendance: <?php echo esc_html(shed_attendance_format_duration($total_seconds)); ?> from <?php echo esc_html((string) count($sessions)); ?> completed or current attendance period<?php echo count($sessions) === 1 ? '' : 's'; ?>.</p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <?php if ($mode === 'member') : ?>
                            <th>Member</th>
                            <th>Card ID</th>
                        <?php else : ?>
                            <th>Scope</th>
                        <?php endif; ?>
                        <th>Attendance periods</th>
                        <th>Total time</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($totals)) : ?>
                        <?php foreach ($totals as $row) : ?>
                            <?php
                            $notes = [];

                            if ($row['open_count'] > 0) {
                                $notes[] = $row['open_count'] . ' still open';
                            }

                            if ($row['deemed_count'] > 0) {
                                $notes[] = $row['deemed_count'] . ' deemed';
                            }
                            ?>
                            <tr>
                                <?php if ($mode === 'member') : ?>
                                    <td><?php echo esc_html($row['member']); ?></td>
                                    <td><code><?php echo esc_html($row['card_id']); ?></code></td>
                                <?php else : ?>
                                    <td><?php echo esc_html($row['member']); ?></td>
                                <?php endif; ?>
                                <td><?php echo esc_html((string) $row['sessions']); ?></td>
                                <td><?php echo esc_html(shed_attendance_format_duration($row['seconds'])); ?></td>
                                <td><?php echo esc_html(implode(', ', $notes)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="<?php echo $mode === 'member' ? '5' : '4'; ?>">No attendance periods found for this selection.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('shed_attendance_now_shortcode')) {
    function shed_attendance_now_shortcode($atts = []) {
        $atts = shortcode_atts(['public' => 'no'], $atts, 'shed_attendance_now');

        if ($atts['public'] !== 'yes' && !current_user_can('read')) {
            return '<p>You must be logged in to view current attendance.</p>';
        }

        return shed_attendance_render_current_attendees_table(shed_attendance_get_current_attendees());
    }
}

if (!function_exists('shed_attendance_duration_report_shortcode')) {
    function shed_attendance_duration_report_shortcode($atts = []) {
        $atts = shortcode_atts(
            [
                'period' => 'day',
                'date'   => current_time('Y-m-d'),
                'mode'   => 'member',
                'public' => 'no',
            ],
            $atts,
            'shed_attendance_duration_report'
        );

        if ($atts['public'] !== 'yes' && !current_user_can('read')) {
            return '<p>You must be logged in to view attendance reports.</p>';
        }

        $period = isset($_GET['attendance_report_period']) ? sanitize_key(wp_unslash($_GET['attendance_report_period'])) : $atts['period'];
        $date = isset($_GET['attendance_report_date']) ? sanitize_text_field(wp_unslash($_GET['attendance_report_date'])) : $atts['date'];
        $mode = isset($_GET['attendance_report_mode']) ? sanitize_key(wp_unslash($_GET['attendance_report_mode'])) : $atts['mode'];

        return shed_attendance_render_duration_report($period, $date, $mode, 'frontend', false);
    }
}

if (!function_exists('shed_duty_rota_shortcode')) {
    function shed_duty_rota_shortcode($atts = []) {
        $atts = shortcode_atts(['days' => 30], $atts, 'shed_duty_rota');
        $rows = shed_attendance_get_upcoming_duty_rota_rows($atts['days']);

        ob_start();
        ?>
        <div class="shed-duty-rota">
            <table class="shed-duty-rota-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Duty Manager</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)) : ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php $availability = shed_attendance_normalize_availability($row->availability); ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y', strtotime($row->rota_date . ' 12:00:00'))); ?></td>
                                <td><?php echo esc_html(wp_date('l', strtotime($row->rota_date . ' 12:00:00'))); ?></td>
                                <td><?php echo esc_html(shed_attendance_get_availability_label($availability)); ?></td>
                                <td><?php echo esc_html($row->duty_manager_handle); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4">No duty rota entries found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('shed_attendance_events_shortcode')) {
    function shed_attendance_events_shortcode($atts = []) {
        $atts = shortcode_atts(['limit' => 50, 'public' => 'no'], $atts, 'shed_attendance_events');

        if ($atts['public'] !== 'yes' && !current_user_can('manage_options')) {
            return '<p>You do not have permission to view attendance events.</p>';
        }

        $rows = shed_attendance_get_event_rows(max(1, min(500, intval($atts['limit']))));

        ob_start();
        ?>
        <div class="shed-attendance-events">
            <table class="shed-attendance-events-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Member</th>
                        <th>Event</th>
                        <th>Deemed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)) : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->event_time); ?></td>
                                <td><?php echo esc_html($row->handle ?: $row->member_name ?: 'Unknown card'); ?></td>
                                <td><?php echo esc_html(intval($row->card_state) === 1 ? 'In' : 'Out'); ?></td>
                                <td><?php echo intval($row->is_deemed) === 1 ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4">No attendance events found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('shed_attendance_render_admin_page')) {
    function shed_attendance_render_admin_page() {
        $pending_members = shed_attendance_get_pending_members();
        $duty_managers = shed_attendance_get_qualified_duty_managers();
        $today_rota = shed_attendance_get_duty_rota_payload(current_time('Y-m-d'));
        $endpoint = rest_url('shed/v1/prox-card');
        $api_key = (string) get_option('shed_attendance_api_key', '');
        $duty_manager_handles = (string) get_option('shed_attendance_duty_manager_handles', '');
        ?>
        <div class="wrap">
            <h1>Shed Attendance</h1>

            <?php if (isset($_GET['shed_attendance_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Attendance settings saved.</p></div>
            <?php endif; ?>

            <?php if (isset($_GET['shed_attendance_error'])) : ?>
                <div class="notice notice-error is-dismissible"><p>Please check the required fields and try again.</p></div>
            <?php endif; ?>

            <h2>ESP32 endpoint</h2>
            <p><code><?php echo esc_html($endpoint); ?></code></p>
            <p>Today: <?php echo esc_html($today_rota['availabilityLabel']); ?><?php echo $today_rota['dutyManager'] !== '' ? ' - Duty manager: ' . esc_html($today_rota['dutyManager']) : ''; ?></p>
            <?php $last_closeout = get_option('shed_attendance_last_closeout'); ?>
            <?php if (is_array($last_closeout) && !empty($last_closeout['run_at'])) : ?>
                <p>Last daily close-out: <?php echo esc_html($last_closeout['run_at']); ?> for <?php echo esc_html($last_closeout['date'] ?? ''); ?>.</p>
            <?php endif; ?>

            <h2>Add or update member card</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('shed_attendance_save_member'); ?>
                <input type="hidden" name="action" value="shed_attendance_save_member">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="card_id">Card ID</label></th>
                        <td><input name="card_id" id="card_id" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="member_name">Member name</label></th>
                        <td><input name="member_name" id="member_name" type="text" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="handle">Display handle</label></th>
                        <td><input name="handle" id="handle" type="text" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td><label><input name="is_active" type="checkbox" value="1" checked> Active card</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api_key">Optional ESP32 API key</label></th>
                        <td>
                            <input name="api_key" id="api_key" type="text" class="regular-text" value="<?php echo esc_attr($api_key); ?>">
                            <p class="description">Leave blank while testing. If set, send it as the <code>X-Shed-Prox-Key</code> header or <code>api_key</code> query parameter.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save member card'); ?>
            </form>

            <h2>Pending card assignments</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Card ID</th>
                        <th>First seen</th>
                        <th>Last seen</th>
                        <th>Next step</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pending_members)) : ?>
                        <?php foreach ($pending_members as $pending_member) : ?>
                            <tr>
                                <td><code><?php echo esc_html($pending_member->card_id); ?></code></td>
                                <td><?php echo esc_html($pending_member->first_seen_at); ?></td>
                                <td><?php echo esc_html($pending_member->last_seen_at); ?></td>
                                <td>Copy this card ID into the form above, add the member name and handle, keep Active card ticked, then save.</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4">No unknown cards are waiting for assignment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>Qualified duty managers</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('shed_attendance_save_duty_settings'); ?>
                <input type="hidden" name="action" value="shed_attendance_save_duty_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="duty_manager_handles">Handles</label></th>
                        <td>
                            <textarea name="duty_manager_handles" id="duty_manager_handles" class="large-text" rows="6"><?php echo esc_textarea($duty_manager_handles); ?></textarea>
                            <p class="description">One qualified duty manager handle per line, for example Clive, Ray, Paul A.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save qualified duty managers'); ?>
            </form>

            <h2>Add or update duty rota day</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('shed_attendance_save_duty_rota'); ?>
                <input type="hidden" name="action" value="shed_attendance_save_duty_rota">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rota_date">Date</label></th>
                        <td><input name="rota_date" id="rota_date" type="date" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="availability">Availability</label></th>
                        <td>
                            <select name="availability" id="availability">
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="duty_manager_handle">Duty manager</label></th>
                        <td>
                            <input name="duty_manager_handle" id="duty_manager_handle" type="text" class="regular-text" list="shed-duty-manager-list">
                            <datalist id="shed-duty-manager-list">
                                <?php foreach ($duty_managers as $handle) : ?>
                                    <option value="<?php echo esc_attr($handle); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rota_note">Note</label></th>
                        <td><input name="rota_note" id="rota_note" type="text" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button('Save duty rota day'); ?>
            </form>
        </div>
        <?php
    }
}

if (!function_exists('shed_attendance_render_reports_page')) {
    function shed_attendance_render_reports_page() {
        $members = shed_attendance_get_members();
        $current_attendees = shed_attendance_get_current_attendees();
        $events = shed_attendance_get_event_rows(100);
        $duty_rota_rows = shed_attendance_get_duty_rota_rows(120);
        $attendance_report_period = isset($_GET['attendance_report_period']) ? sanitize_key(wp_unslash($_GET['attendance_report_period'])) : 'day';
        $attendance_report_date = isset($_GET['attendance_report_date']) ? sanitize_text_field(wp_unslash($_GET['attendance_report_date'])) : current_time('Y-m-d');
        $attendance_report_mode = isset($_GET['attendance_report_mode']) ? sanitize_key(wp_unslash($_GET['attendance_report_mode'])) : 'member';
        ?>
        <div class="wrap">
            <h1>Shed Attendance Reports</h1>

            <h2>Emergency attendance now</h2>
            <?php echo shed_attendance_render_current_attendees_table($current_attendees); ?>

            <h2>Attendance duration report</h2>
            <?php echo shed_attendance_render_duration_report($attendance_report_period, $attendance_report_date, $attendance_report_mode); ?>

            <h2>Member cards</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Card ID</th>
                        <th>Member</th>
                        <th>Handle</th>
                        <th>Status</th>
                        <th>Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)) : ?>
                        <?php foreach ($members as $member) : ?>
                            <tr>
                                <td><code><?php echo esc_html($member->card_id); ?></code></td>
                                <td><?php echo esc_html($member->member_name); ?></td>
                                <td><?php echo esc_html($member->handle); ?></td>
                                <td><?php echo intval($member->is_pending) === 1 ? 'Pending' : (intval($member->is_active) === 1 ? 'Active' : 'Inactive'); ?></td>
                                <td><?php echo esc_html($member->last_seen_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">No member cards have been added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>Duty rota</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Availability</th>
                        <th>Duty Manager</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($duty_rota_rows)) : ?>
                        <?php foreach ($duty_rota_rows as $rota_row) : ?>
                            <?php $availability = shed_attendance_normalize_availability($rota_row->availability); ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y', strtotime($rota_row->rota_date . ' 12:00:00'))); ?></td>
                                <td><?php echo esc_html(wp_date('l', strtotime($rota_row->rota_date . ' 12:00:00'))); ?></td>
                                <td><?php echo esc_html(shed_attendance_get_availability_label($availability)); ?></td>
                                <td><?php echo esc_html($rota_row->duty_manager_handle); ?></td>
                                <td><?php echo esc_html($rota_row->note); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">No duty rota days have been added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>Recent attendance events</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('shed_attendance_export_events'); ?>
                <input type="hidden" name="action" value="shed_attendance_export_events">
                <?php submit_button('Export CSV', 'secondary', 'submit', false); ?>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event Type</th>
                        <th>Direction</th>
                        <th>Card ID</th>
                        <th>Member</th>
                        <th>Handle</th>
                        <th>Deemed</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)) : ?>
                        <?php foreach ($events as $event) : ?>
                            <tr>
                                <td><?php echo esc_html($event->event_time); ?></td>
                                <td><?php echo esc_html((string) intval($event->card_state)); ?></td>
                                <td><?php echo esc_html(ucfirst($event->direction)); ?></td>
                                <td><code><?php echo esc_html($event->card_id); ?></code></td>
                                <td><?php echo esc_html($event->member_name ?: 'Unknown card'); ?></td>
                                <td><?php echo esc_html($event->handle); ?></td>
                                <td><?php echo intval($event->is_deemed) === 1 ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html($event->note); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="8">No attendance events have been logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
