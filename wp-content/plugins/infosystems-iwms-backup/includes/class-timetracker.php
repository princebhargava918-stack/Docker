<?php
class IWMS_TimeTracker
{
    public function __construct()
    {
        // Initialize time tracking hooks
        add_action('wp_ajax_iwms_start_timer', array($this, 'start_timer'));
        add_action('wp_ajax_iwms_stop_timer', array($this, 'stop_timer'));
        add_action('wp_ajax_iwms_log_manual_time', array($this, 'log_manual_time'));
        add_action('wp_ajax_iwms_get_time_logs', array($this, 'get_time_logs'));

        // Add cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));
        if (!wp_next_scheduled('iwms_auto_stop_timers')) {
            wp_schedule_event(time(), 'every_fifteen_minutes', 'iwms_auto_stop_timers');
        }
        add_action('iwms_auto_stop_timers', array($this, 'auto_stop_timers'));
    }

    public function add_cron_schedule($schedules)
    {
        $schedules['every_fifteen_minutes'] = array(
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 minutes')
        );
        return $schedules;
    }

    public function auto_stop_timers()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $timers = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'running' AND TIMESTAMPDIFF(SECOND, start_time, NOW()) > 32400"
        );

        foreach ($timers as $timer) {
            $this->force_stop_timer($timer->id, $timer->user_id);
        }
    }

    private function force_stop_timer($log_id, $user_id)
    {
        $end_time = current_time('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND status = 'running'",
            $log_id, $user_id
        ));

        if (!$log) return;

        $start_time = strtotime($log->start_time);
        $end_timestamp = strtotime($end_time);
        $total_seconds = min($end_timestamp - $start_time, 32400); // Cap at 9 hours

        $end_time = date('Y-m-d H:i:s', $start_time + $total_seconds);

        $wpdb->update($table, array(
            'end_time' => $end_time,
            'total_seconds' => $total_seconds,
            'status' => 'completed'
        ), array('id' => $log_id));
    }

    public function start_timer()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_start_timer')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $project_id = intval($_POST['project_id']);
        $task_name = sanitize_text_field($_POST['task_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $billable = intval($_POST['billable']);
        $date = current_time('Y-m-d');
        $start_time = current_time('Y-m-d H:i:s');

        // Check if user already has a running timer
        if ($this->get_running_timer($user_id)) {
            wp_send_json_error('Timer already running');
            return;
        }

        // Validate project assignment if project_id provided
        if ($project_id && !$this->is_user_assigned_to_project($user_id, $project_id)) {
            wp_send_json_error('Not assigned to this project');
            return;
        }

        // Prevent logging on holidays or leaves
        if ($this->is_holiday($date) || $this->has_leave($user_id, $date)) {
            wp_send_json_error('Cannot log time on holidays or leave days');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'task_name' => $task_name,
            'description' => $description,
            'start_time' => $start_time,
            'billable' => $billable,
            'status' => 'running',
            'date' => $date
        ));

        wp_send_json_success(array('log_id' => $wpdb->insert_id));
    }

    public function stop_timer()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_start_timer')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $log_id = intval($_POST['log_id']);
        $end_time = current_time('Y-m-d H:i:s');

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND status = 'running'",
            $log_id, $user_id
        ));

        if (!$log) {
            wp_send_json_error('Invalid timer session');
            return;
        }

        $start_time = strtotime($log->start_time);
        $end_timestamp = strtotime($end_time);
        $total_seconds = $end_timestamp - $start_time;

        // Prevent logging more than 9 hours
        if ($total_seconds > 32400) { // 9 hours in seconds
            $total_seconds = 32400;
            $end_time = date('Y-m-d H:i:s', $start_time + 32400);
        }

        $wpdb->update($table, array(
            'end_time' => $end_time,
            'total_seconds' => $total_seconds,
            'status' => 'completed'
        ), array('id' => $log_id));

        wp_send_json_success(array('total_seconds' => $total_seconds));
    }

    public function log_manual_time()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_log_time')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $project_id = intval($_POST['project_id']);
        $task_name = sanitize_text_field($_POST['task_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $billable = intval($_POST['billable']);
        $date = sanitize_text_field($_POST['date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);

        // Validate inputs
        if (empty($task_name) || empty($start_time) || empty($end_time)) {
            wp_send_json_error('Missing required fields');
            return;
        }

        $start_datetime = $date . ' ' . $start_time . ':00';
        $end_datetime = $date . ' ' . $end_time . ':00';

        $start_timestamp = strtotime($start_datetime);
        $end_timestamp = strtotime($end_datetime);

        if ($start_timestamp >= $end_timestamp) {
            wp_send_json_error('End time must be after start time');
            return;
        }

        $total_seconds = $end_timestamp - $start_timestamp;

        // Prevent logging more than 9 hours
        if ($total_seconds > 32400) {
            wp_send_json_error('Cannot log more than 9 hours per day');
            return;
        }

        // Check total logged hours for the day
        $existing_logs = $this->get_daily_logs($user_id, $date);
        $total_logged_today = array_sum(array_column($existing_logs, 'total_seconds'));

        if ($total_logged_today + $total_seconds > 32400) {
            wp_send_json_error('Total logged hours cannot exceed 9 hours per day');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'task_name' => $task_name,
            'description' => $description,
            'start_time' => $start_datetime,
            'end_time' => $end_datetime,
            'total_seconds' => $total_seconds,
            'billable' => $billable,
            'status' => 'completed',
            'date' => $date
        ));

        wp_send_json_success('Time logged successfully');
    }

    public function get_time_logs()
    {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        if (!IWMS_Roles::has_capability('iwms_view_reports') && $user_id != get_current_user_id()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d ORDER BY date DESC, start_time DESC",
            $user_id, $month, $year
        ));

        wp_send_json_success($results);
    }

    private function get_running_timer($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND status = 'running'",
            $user_id
        ));
    }

    private function is_user_assigned_to_project($user_id, $project_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_project_assignments';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE project_id = %d AND user_id = %d",
            $project_id, $user_id
        )) > 0;
    }

    private function is_holiday($date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_holidays';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE date = %s",
            $date
        )) > 0;
    }

    private function has_leave($user_id, $date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND %s BETWEEN start_date AND end_date AND status = 'approved'",
            $user_id, $date
        )) > 0;
    }

    private function get_daily_logs($user_id, $date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND date = %s AND status = 'completed'",
            $user_id, $date
        ));
    }
}
