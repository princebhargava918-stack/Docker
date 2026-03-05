<?php
class IWMS_Payroll
{
    public function __construct()
    {
        add_action('wp_ajax_iwms_generate_payroll', array($this, 'generate_payroll'));
        add_action('wp_ajax_iwms_get_payroll', array($this, 'get_payroll'));
    }

    public function generate_payroll()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_manage_payroll')) {
            wp_die('Unauthorized');
        }

        $month = intval($_POST['month']);
        $year = intval($_POST['year']);

        $users = get_users(array('role__in' => array('iwms_employee', 'iwms_team_lead', 'iwms_project_manager', 'iwms_hr_manager', 'iwms_finance_manager')));

        global $wpdb;
        $payroll_table = $wpdb->prefix . 'iwms_payroll';
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $leaves_table = $wpdb->prefix . 'iwms_leaves';

        foreach ($users as $user) {
            $user_id = $user->ID;

            // Get base salary and hourly rate from user meta
            $base_salary = get_user_meta($user_id, 'iwms_base_salary', true) ?: 0;
            $hourly_rate = get_user_meta($user_id, 'iwms_hourly_rate', true) ?: 0;

            // Calculate billable hours for the month
            $billable_hours = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_seconds)/3600 FROM $timelogs_table 
                 WHERE user_id = %d AND billable = 1 AND MONTH(date) = %d AND YEAR(date) = %d",
                $user_id, $month, $year
            )) ?: 0;

            // Calculate unpaid leaves
            $unpaid_leaves = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $leaves_table 
                 WHERE user_id = %d AND leave_type = 'unpaid' AND status = 'approved' 
                 AND ((MONTH(start_date) = %d AND YEAR(start_date) = %d) OR (MONTH(end_date) = %d AND YEAR(end_date) = %d))",
                $user_id, $month, $year, $month, $year
            )) ?: 0;

            // Calculate overtime (hours over 160 per month)
            $total_hours = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_seconds)/3600 FROM $timelogs_table 
                 WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d",
                $user_id, $month, $year
            )) ?: 0;

            $overtime = max(0, $total_hours - 160);

            // Calculate total salary
            $billable_earnings = $billable_hours * $hourly_rate;
            $unpaid_deduction = $unpaid_leaves * ($base_salary / 30); // Assuming 30 days per month
            $overtime_bonus = $overtime * ($hourly_rate * 1.5); // 1.5x for overtime

            $total_salary = $base_salary + $billable_earnings - $unpaid_deduction + $overtime_bonus;

            // Insert or update payroll record
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $payroll_table WHERE user_id = %d AND month = %d AND year = %d",
                $user_id, $month, $year
            ));

            $data = array(
                'user_id' => $user_id,
                'month' => $month,
                'year' => $year,
                'base_salary' => $base_salary,
                'billable_hours' => $billable_hours,
                'hourly_rate' => $hourly_rate,
                'unpaid_leaves' => $unpaid_leaves,
                'overtime' => $overtime,
                'total_salary' => $total_salary
            );

            if ($existing) {
                $wpdb->update($payroll_table, $data, array('id' => $existing));
            } else {
                $wpdb->insert($payroll_table, $data);
            }
        }

        wp_send_json_success('Payroll generated for ' . count($users) . ' employees');
    }

    public function get_payroll()
    {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        if (!IWMS_Roles::has_capability('iwms_manage_payroll') && $user_id != get_current_user_id()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_payroll';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND month = %d AND year = %d",
            $user_id, $month, $year
        ));

        wp_send_json_success($results);
    }
}
