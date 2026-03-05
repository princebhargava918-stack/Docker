<?php
class IWMS_Leaves
{
    public function __construct()
    {
        add_action('wp_ajax_iwms_request_leave', array($this, 'request_leave'));
        add_action('wp_ajax_iwms_approve_leave', array($this, 'approve_leave'));
        add_action('wp_ajax_iwms_reject_leave', array($this, 'reject_leave'));
        add_action('wp_ajax_iwms_get_leaves', array($this, 'get_leaves'));
        add_action('wp_ajax_iwms_get_pending_leaves', array($this, 'get_pending_leaves'));
        add_action('iwms_leave_requested', array($this, 'send_leave_notification'));
    }

    public function request_leave()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_request_leave')) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $leave_type = sanitize_text_field($_POST['leave_type']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $reason = sanitize_textarea_field($_POST['reason']);

        // Validate dates
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $today = strtotime(date('Y-m-d'));

        if ($start > $end) {
            wp_send_json_error('End date must be after start date');
            return;
        }

        if ($start < $today) {
            wp_send_json_error('Cannot request leave for past dates');
            return;
        }

        // Check for overlapping leaves
        if ($this->has_overlapping_leave($user_id, $start_date, $end_date)) {
            wp_send_json_error('You already have leave during these dates');
            return;
        }

        // Check leave balance (simplified - assume unlimited for now)
        $days_requested = $this->calculate_leave_days($start_date, $end_date);
        if ($days_requested > 30) { // Basic validation
            wp_send_json_error('Cannot request more than 30 days at once');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'leave_type' => $leave_type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'reason' => $reason
        ));

        // Send notification
        do_action('iwms_leave_requested', $wpdb->insert_id);

        wp_send_json_success('Leave request submitted successfully');
    }

    public function approve_leave()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_approve_leave')) {
            wp_die('Unauthorized');
        }

        $leave_id = intval($_POST['leave_id']);
        $approver_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        $leave = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $leave_id));

        if (!$leave) {
            wp_send_json_error('Leave request not found');
            return;
        }

        if ($leave->status !== 'pending') {
            wp_send_json_error('Leave request already processed');
            return;
        }

        $wpdb->update($table, array(
            'status' => 'approved',
            'approved_by' => $approver_id,
            'approved_at' => current_time('mysql')
        ), array('id' => $leave_id));

        // Send approval notification
        $this->send_leave_decision_notification($leave_id, 'approved');

        wp_send_json_success('Leave approved successfully');
    }

    public function reject_leave()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_approve_leave')) {
            wp_die('Unauthorized');
        }

        $leave_id = intval($_POST['leave_id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        $approver_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        $leave = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $leave_id));

        if (!$leave) {
            wp_send_json_error('Leave request not found');
            return;
        }

        if ($leave->status !== 'pending') {
            wp_send_json_error('Leave request already processed');
            return;
        }

        $wpdb->update($table, array(
            'status' => 'rejected',
            'approved_by' => $approver_id,
            'approved_at' => current_time('mysql'),
            'reason' => $wpdb->get_var("SELECT reason FROM $table WHERE id = $leave_id") . "\n\nRejection Reason: " . $reason
        ), array('id' => $leave_id));

        // Send rejection notification
        $this->send_leave_decision_notification($leave_id, 'rejected');

        wp_send_json_success('Leave rejected');
    }

    public function get_leaves()
    {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();

        if (!IWMS_Roles::has_capability('iwms_approve_leave') && $user_id != get_current_user_id()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name, 
                    approver.display_name as approver_name
             FROM $table l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             LEFT JOIN {$wpdb->users} approver ON l.approved_by = approver.ID
             WHERE l.user_id = %d 
             ORDER BY l.created_at DESC",
            $user_id
        ));

        wp_send_json_success($results);
    }

    public function get_pending_leaves()
    {
        if (!IWMS_Roles::has_capability('iwms_approve_leave')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        $results = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM $table l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.status = 'pending'
             ORDER BY l.created_at DESC"
        );

        wp_send_json_success($results);
    }

    public function send_leave_notification($leave_id)
    {
        $leave = $this->get_leave_details($leave_id);
        if (!$leave) return;

        $user = get_userdata($leave->user_id);
        $hr_managers = get_users(array('role' => 'iwms_hr_manager'));
        $project_managers = get_users(array('role' => 'iwms_project_manager'));

        $recipients = array_merge($hr_managers, $project_managers);

        $subject = 'New Leave Request from ' . $user->display_name;
        $message = "A new leave request has been submitted:\n\n";
        $message .= "Employee: {$user->display_name}\n";
        $message .= "Type: {$leave->leave_type}\n";
        $message .= "From: {$leave->start_date} To: {$leave->end_date}\n";
        $message .= "Reason: {$leave->reason}\n\n";
        $message .= "Please review and approve/reject via the IWMS dashboard.";

        foreach ($recipients as $recipient) {
            wp_mail($recipient->user_email, $subject, $message);
        }
    }

    private function send_leave_decision_notification($leave_id, $decision)
    {
        $leave = $this->get_leave_details($leave_id);
        if (!$leave) return;

        $user = get_userdata($leave->user_id);
        $approver = get_userdata($leave->approved_by);

        $subject = 'Leave Request ' . ucfirst($decision);
        $message = "Your leave request has been {$decision}:\n\n";
        $message .= "Type: {$leave->leave_type}\n";
        $message .= "From: {$leave->start_date} To: {$leave->end_date}\n";
        if ($decision === 'rejected') {
            $message .= "Reason: {$leave->reason}\n";
        }
        $message .= "\nApproved by: {$approver->display_name}";

        wp_mail($user->user_email, $subject, $message);
    }

    private function get_leave_details($leave_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $leave_id));
    }

    private function has_overlapping_leave($user_id, $start_date, $end_date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_leaves';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d AND status IN ('pending', 'approved') 
             AND ((start_date BETWEEN %s AND %s) OR (end_date BETWEEN %s AND %s) 
             OR (%s BETWEEN start_date AND end_date))",
            $user_id, $start_date, $end_date, $start_date, $end_date, $start_date
        )) > 0;
    }

    private function calculate_leave_days($start_date, $end_date)
    {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $days = 0;

        for ($i = $start; $i <= $end; $i += 86400) {
            $day_of_week = date('N', $i); // 1=Monday, 7=Sunday
            if ($day_of_week < 6) { // Exclude weekends
                $days++;
            }
        }

        return $days;
    }
}
