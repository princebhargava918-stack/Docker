<?php
class IWMS_Reports
{
    public function __construct()
    {
        add_action('wp_ajax_iwms_get_daily_report', array($this, 'get_daily_report'));
        add_action('wp_ajax_iwms_get_monthly_report', array($this, 'get_monthly_report'));
        add_action('wp_ajax_iwms_export_report', array($this, 'export_report'));
    }

    public function get_daily_report()
    {
        if (!IWMS_Roles::has_capability('iwms_view_reports')) {
            wp_die('Unauthorized');
        }

        $date = sanitize_text_field($_POST['date']);

        global $wpdb;
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $attendance_table = $wpdb->prefix . 'iwms_attendance';

        // Daily time logs
        $time_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT tl.*, u.display_name, p.name as project_name 
             FROM $timelogs_table tl 
             LEFT JOIN {$wpdb->users} u ON tl.user_id = u.ID 
             LEFT JOIN {$wpdb->prefix}iwms_projects p ON tl.project_id = p.id 
             WHERE tl.date = %s 
             ORDER BY tl.start_time ASC",
            $date
        ));

        // Daily attendance
        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name 
             FROM $attendance_table a 
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
             WHERE a.date = %s 
             ORDER BY a.check_in ASC",
            $date
        ));

        wp_send_json_success(array(
            'time_logs' => $time_logs,
            'attendance' => $attendance
        ));
    }

    public function get_monthly_report()
    {
        if (!IWMS_Roles::has_capability('iwms_view_reports')) {
            wp_die('Unauthorized');
        }

        $month = intval($_POST['month']);
        $year = intval($_POST['year']);

        global $wpdb;
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $attendance_table = $wpdb->prefix . 'iwms_attendance';

        // Monthly summary by user
        $user_reports = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.ID, u.display_name,
                SUM(CASE WHEN tl.billable = 1 THEN tl.total_seconds ELSE 0 END)/3600 as billable_hours,
                SUM(CASE WHEN tl.billable = 0 THEN tl.total_seconds ELSE 0 END)/3600 as non_billable_hours,
                COUNT(DISTINCT tl.date) as working_days,
                AVG(a.total_hours) as avg_daily_hours
             FROM {$wpdb->users} u 
             LEFT JOIN $timelogs_table tl ON u.ID = tl.user_id AND MONTH(tl.date) = %d AND YEAR(tl.date) = %d
             LEFT JOIN $attendance_table a ON u.ID = a.user_id AND MONTH(a.date) = %d AND YEAR(a.date) = %d
             WHERE u.ID IN (SELECT user_id FROM $timelogs_table WHERE MONTH(date) = %d AND YEAR(date) = %d)
             GROUP BY u.ID, u.display_name
             ORDER BY u.display_name",
            $month, $year, $month, $year, $month, $year
        ));

        wp_send_json_success($user_reports);
    }

    public function export_report()
    {
        if (!IWMS_Roles::has_capability('iwms_view_reports')) {
            wp_die('Unauthorized');
        }

        $type = sanitize_text_field($_POST['type']); // daily or monthly
        $data = json_decode(stripslashes($_POST['data']), true);

        // Generate CSV
        $filename = 'iwms_report_' . $type . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        if ($type === 'daily') {
            fputcsv($output, array('User', 'Project', 'Task', 'Start Time', 'End Time', 'Billable', 'Hours'));
            foreach ($data['time_logs'] as $log) {
                fputcsv($output, array(
                    $log['display_name'],
                    $log['project_name'],
                    $log['task_name'],
                    $log['start_time'],
                    $log['end_time'],
                    $log['billable'] ? 'Yes' : 'No',
                    number_format($log['total_seconds'] / 3600, 2)
                ));
            }
        } elseif ($type === 'monthly') {
            fputcsv($output, array('User', 'Billable Hours', 'Non-Billable Hours', 'Working Days', 'Avg Daily Hours'));
            foreach ($data as $report) {
                fputcsv($output, array(
                    $report['display_name'],
                    number_format($report['billable_hours'], 2),
                    number_format($report['non_billable_hours'], 2),
                    $report['working_days'],
                    number_format($report['avg_daily_hours'], 2)
                ));
            }
        }

        fclose($output);
        exit;
    }
}
