<?php
class IWMS_Attendance
{
    public function __construct()
    {
        // Initialize attendance hooks and functionality
        add_action('wp_ajax_iwms_check_in', array($this, 'check_in'));
        add_action('wp_ajax_iwms_check_out', array($this, 'check_out'));
        add_action('wp_ajax_iwms_get_attendance', array($this, 'get_attendance'));
    }

    public function check_in()
    {
        // Check nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_check_in')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $today = current_time('Y-m-d');
        $now = current_time('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        // Check if already checked in today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND date = %s",
            $user_id, $today
        ));

        if ($existing && $existing->check_in) {
            wp_send_json_error('Already checked in today');
            return;
        }

        if ($existing) {
            $wpdb->update($table, array('check_in' => $now), array('id' => $existing->id));
        } else {
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'date' => $today,
                'check_in' => $now
            ));
        }

        wp_send_json_success('Checked in successfully');
    }

    public function check_out()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_check_out')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $today = current_time('Y-m-d');
        $now = current_time('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND date = %s",
            $user_id, $today
        ));

        if (!$record || !$record->check_in) {
            wp_send_json_error('Not checked in today');
            return;
        }

        $check_in_time = strtotime($record->check_in);
        $check_out_time = strtotime($now);
        $total_seconds = $check_out_time - $check_in_time;
        $total_hours = round($total_seconds / 3600, 2);

        $wpdb->update($table, array(
            'check_out' => $now,
            'total_hours' => $total_hours
        ), array('id' => $record->id));

        wp_send_json_success(array('total_hours' => $total_hours));
    }

    public function get_attendance()
    {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        if (!IWMS_Roles::has_capability('iwms_view_reports') && $user_id != get_current_user_id()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d ORDER BY date DESC",
            $user_id, $month, $year
        ));

        wp_send_json_success($results);
    }

    public static function get_today_status($user_id = null)
    {
        if (!$user_id) $user_id = get_current_user_id();
        $today = current_time('Y-m-d');

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND date = %s",
            $user_id, $today
        ));

        return $record ? $record : false;
    }

    public static function auto_logout_check()
    {
        // This will be called by cron to auto logout after 9 hours
        $now = current_time('Y-m-d H:i:s');
        $nine_hours_ago = date('Y-m-d H:i:s', strtotime('-9 hours'));

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE check_in < %s AND check_out IS NULL",
            $nine_hours_ago
        ));

        foreach ($records as $record) {
            $check_out_time = date('Y-m-d H:i:s', strtotime($record->check_in . ' +9 hours'));
            $total_hours = 9.00;

            $wpdb->update($table, array(
                'check_out' => $check_out_time,
                'total_hours' => $total_hours,
                'status' => 'auto_logout'
            ), array('id' => $record->id));
        }
    }
}