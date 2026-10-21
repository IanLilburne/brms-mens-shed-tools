<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Android-friendly attendance kiosk.
 * Requires a dedicated logged-in WordPress account with edit_posts capability.
 * Attendance records are stored in the existing attendance tables.
 */
add_shortcode('shed_attendance_kiosk', 'shed_kiosk_shortcode');
add_action('rest_api_init', 'shed_kiosk_register_routes');
add_action('admin_menu', 'shed_kiosk_admin_menu');
add_action('admin_post_shed_kiosk_save_news', 'shed_kiosk_save_news');

function shed_kiosk_allowed() {
    return is_user_logged_in() && current_user_can('edit_posts');
}

function shed_kiosk_shortcode() {
    if (!shed_kiosk_allowed()) {
        return '<p>Sign in with the Shed kiosk account to use attendance check-in.</p>';
    }
    wp_enqueue_style('shed-kiosk', MENS_SHED_TOOLS_URL . 'assets/attendance-kiosk.css', [], '0.2.0');
    wp_enqueue_script('shed-kiosk', MENS_SHED_TOOLS_URL . 'assets/attendance-kiosk.js', [], '0.2.0', true);
    wp_localize_script('shed-kiosk', 'shedKioskConfig', [
        'base' => esc_url_raw(rest_url('shed/v1/kiosk/')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
    return '<main id="shed-kiosk" class="shed-kiosk" aria-label="Shed attendance kiosk">
        <header class="shed-kiosk-header"><div><h1>Brundall Men\'s Shed</h1><p>Welcome to the Shed</p></div><div class="shed-kiosk-clock" id="shed-kiosk-clock"></div></header>
        <div class="shed-kiosk-news" id="shed-kiosk-news" aria-label="Latest announcements"><span>Loading announcements…</span></div>
        <section class="shed-kiosk-main"><h2>Sign in or out</h2><p>Tap your RFID card or find your name below.</p>
          <div class="shed-kiosk-feedback" id="shed-kiosk-feedback" role="status" aria-live="polite">Ready for your card or name</div>
          <label for="shed-kiosk-search">Find your name</label>
          <input type="search" id="shed-kiosk-search" autocomplete="off" placeholder="Start typing your name…" />
          <div id="shed-kiosk-results" class="shed-kiosk-results" role="listbox" aria-label="Matching members"></div>
        </section>
        <footer class="shed-kiosk-footer"><div><strong id="shed-kiosk-present">–</strong><span>Currently in Shed</span></div><div><strong id="shed-kiosk-visits">–</strong><span>Visits today</span></div></footer>
        <div id="shed-kiosk-connection" class="shed-kiosk-connection" hidden>Connection lost. Reconnecting…</div>
    </main>';
}

function shed_kiosk_register_routes() {
    $routes = [
        '/kiosk/status' => ['GET', 'shed_kiosk_status'],
        '/kiosk/search' => ['GET', 'shed_kiosk_search'],
        '/kiosk/check' => ['POST', 'shed_kiosk_check'],
    ];
    foreach ($routes as $route => $entry) {
        register_rest_route('shed/v1', $route, [
            'methods' => $entry[0],
            'callback' => $entry[1],
            'permission_callback' => 'shed_kiosk_allowed',
        ]);
    }
}

function shed_kiosk_status(WP_REST_Request $request) {
    global $wpdb;
    $today = current_time('Y-m-d');
    list($start, $end) = shed_attendance_day_bounds($today);
    $present = shed_attendance_get_current_attendees($today);
    $events_table = shed_attendance_events_table();
    $members_table = shed_attendance_members_table();
    $visits = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE event_time >= %s AND event_time < %s AND card_state = 1 AND is_deemed = 0",
        $start, $end
    ));
    $after = max(0, (int) $request->get_param('after'));
    $recent = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.card_state, e.event_time, e.source, COALESCE(NULLIF(m.handle, ''), m.member_name, 'Unknown card') AS name
        FROM {$events_table} e LEFT JOIN {$members_table} m ON m.card_id = e.card_id
        WHERE e.id > %d AND e.created_at >= %s AND e.is_deemed = 0 ORDER BY e.id ASC LIMIT 15",
        $after, wp_date('Y-m-d H:i:s', time() - 240)
    ));
    $events = [];
    foreach ($recent as $event) {
        $events[] = [
            'id' => (int) $event->id,
            'name' => (string) $event->name,
            'direction' => (int) $event->card_state === 1 ? 'in' : 'out',
            'source' => (string) $event->source,
            'time' => (string) $event->event_time,
        ];
    }
    $rota = shed_attendance_get_duty_rota_payload($today);
    $news = array_values(array_filter((array) get_option('shed_kiosk_news', []), function ($item) use ($today) {
        return !empty($item['message']) && (empty($item['start']) || $item['start'] <= $today)
            && (empty($item['end']) || $item['end'] >= $today);
    }));
    if (!empty($rota['dutyManager'])) {
        array_unshift($news, ['message' => 'Today\'s duty manager is ' . $rota['dutyManager']]);
    }
    return rest_ensure_response([
        'present' => count($present),
        'visits' => $visits,
        'latest_id' => (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) FROM {$events_table}"),
        'events' => $events,
        'news' => $news,
    ]);
}

function shed_kiosk_search(WP_REST_Request $request) {
    global $wpdb;
    $query = sanitize_text_field((string) $request->get_param('q'));
    if (mb_strlen($query) < 2) { return ['members' => []]; }
    $table = shed_attendance_members_table();
    $like = '%' . $wpdb->esc_like($query) . '%';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, card_id, member_name, handle FROM {$table}
        WHERE is_active = 1 AND is_pending = 0 AND (member_name LIKE %s OR handle LIKE %s)
        ORDER BY member_name ASC LIMIT 10", $like, $like
    ));
    $members = [];
    foreach ($rows as $row) {
        $last = shed_attendance_get_last_event_for_day($row->card_id, current_time('Y-m-d'));
        $members[] = [
            'id' => (int) $row->id,
            'name' => (string) $row->member_name,
            'handle' => (string) $row->handle,
            'in' => $last && (int) $last->card_state === 1,
        ];
    }
    return ['members' => $members];
}

function shed_kiosk_check(WP_REST_Request $request) {
    global $wpdb;
    $id = (int) $request->get_param('member_id');
    $action = (string) $request->get_param('action');
    $token = sanitize_text_field((string) $request->get_param('token'));
    if (!$id || !in_array($action, ['in', 'out'], true) || !preg_match('/^[a-zA-Z0-9-]{12,100}$/', $token)) {
        return new WP_Error('invalid_check', 'Invalid check-in request.', ['status' => 400]);
    }
    $table = shed_attendance_members_table();
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND is_active = 1 AND is_pending = 0", $id
    ));
    if (!$member) { return new WP_Error('missing_member', 'Member not available.', ['status' => 404]); }

    // Serialise competing card/touch events for this member across requests.
    $lock_name = 'shed_kiosk_' . md5($member->card_id);
    $got_lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lock_name));
    if ($got_lock !== 1) { return new WP_Error('busy', 'Please try again.', ['status' => 409]); }
    try {
        $existing = shed_attendance_get_event_by_client_id('kiosk-' . $token);
        if ($existing) {
            return ['ok' => true, 'duplicate' => true, 'name' => shed_attendance_member_handle($member), 'direction' => $existing->direction];
        }
        $last = shed_attendance_get_last_event_for_day($member->card_id, current_time('Y-m-d'));
        $already_in = $last && (int) $last->card_state === 1;
        if (($action === 'in') === $already_in) {
            return new WP_Error('stale_state', 'Attendance status changed. Please search again.', ['status' => 409]);
        }
        if ($last && (time() - shed_attendance_datetime_to_timestamp($last->event_time)) < 8) {
            return new WP_Error('too_fast', 'Please wait a few seconds before changing status again.', ['status' => 409]);
        }
        $state = $action === 'in' ? 1 : 0;
        $event_id = shed_attendance_insert_event($member->card_id, $member, current_time('mysql'), $state, 'touchscreen', false, '', [], 'kiosk-' . $token);
        if (!$event_id) { return new WP_Error('save_failed', 'Could not save attendance.', ['status' => 500]); }
        return ['ok' => true, 'id' => $event_id, 'name' => shed_attendance_member_handle($member), 'direction' => $action];
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    }
}

function shed_kiosk_admin_menu() {
    add_submenu_page('shed-attendance', 'Kiosk announcements', 'Kiosk announcements', 'manage_options', 'shed-kiosk-news', 'shed_kiosk_news_page');
}
function shed_kiosk_news_page() {
    if (!current_user_can('manage_options')) { return; }
    $items = (array) get_option('shed_kiosk_news', []);
    echo '<div class="wrap"><h1>Kiosk announcements</h1><p>One announcement per line. Optional date ranges can be added in a later release.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="shed_kiosk_save_news">';
    wp_nonce_field('shed_kiosk_news');
    $lines = implode("\n", array_map(function ($item) { return isset($item['message']) ? $item['message'] : ''; }, $items));
    echo '<textarea name="messages" rows="12" cols="90">' . esc_textarea($lines) . '</textarea>';
    submit_button('Save announcements');
    echo '</form></div>';
}
function shed_kiosk_save_news() {
    if (!current_user_can('manage_options')) { wp_die('Not authorised.'); }
    check_admin_referer('shed_kiosk_news');
    $raw = isset($_POST['messages']) ? (string) wp_unslash($_POST['messages']) : '';
    $items = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $message = sanitize_text_field($line);
        if ($message !== '') { $items[] = ['message' => mb_substr($message, 0, 250)]; }
        if (count($items) >= 20) { break; }
    }
    update_option('shed_kiosk_news', $items);
    wp_safe_redirect(admin_url('admin.php?page=shed-kiosk-news&saved=1'));
    exit;
}
