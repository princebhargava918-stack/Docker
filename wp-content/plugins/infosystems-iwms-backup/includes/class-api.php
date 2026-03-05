<?php
class IWMS_API
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        // Attendance endpoints
        register_rest_route('iwms/v1', '/attendance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_attendance'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'user_id' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'month' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'year' => array('required' => false, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // Time logs endpoints
        register_rest_route('iwms/v1', '/timelogs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_timelogs'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'user_id' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'month' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'year' => array('required' => false, 'validate_callback' => 'is_numeric'),
            ),
        ));

        register_rest_route('iwms/v1', '/timelogs', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_timelog'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'project_id' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'task_name' => array('required' => true),
                'start_time' => array('required' => true),
                'end_time' => array('required' => true),
                'billable' => array('required' => false, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // Projects endpoints
        register_rest_route('iwms/v1', '/projects', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_projects'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Reports endpoints
        register_rest_route('iwms/v1', '/reports/daily', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_daily_report'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'date' => array('required' => true),
            ),
        ));

        register_rest_route('iwms/v1', '/reports/monthly', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_monthly_report'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'month' => array('required' => true, 'validate_callback' => 'is_numeric'),
                'year' => array('required' => true, 'validate_callback' => 'is_numeric'),
            ),
        ));

        // Payroll endpoints
        register_rest_route('iwms/v1', '/payroll', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_payroll'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'user_id' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'month' => array('required' => false, 'validate_callback' => 'is_numeric'),
                'year' => array('required' => false, 'validate_callback' => 'is_numeric'),
            ),
        ));
    }

    public function check_permission($request)
    {
        return is_user_logged_in() && IWMS_Roles::has_capability('iwms_access_dashboard');
    }

    public function get_attendance($request)
    {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $month = $request->get_param('month') ?: date('m');
        $year = $request->get_param('year') ?: date('Y');

        if (!IWMS_Roles::has_capability('iwms_view_reports') && $user_id != get_current_user_id()) {
            return new WP_Error('unauthorized', 'You cannot view this data', array('status' => 403));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_attendance';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d ORDER BY date DESC",
            $user_id, $month, $year
        ));

        return new WP_REST_Response($results, 200);
    }

    public function get_timelogs($request)
    {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $month = $request->get_param('month') ?: date('m');
        $year = $request->get_param('year') ?: date('Y');

        if (!IWMS_Roles::has_capability('iwms_view_reports') && $user_id != get_current_user_id()) {
            return new WP_Error('unauthorized', 'You cannot view this data', array('status' => 403));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d ORDER BY date DESC, start_time DESC",
            $user_id, $month, $year
        ));

        return new WP_REST_Response($results, 200);
    }

    public function create_timelog($request)
    {
        $user_id = get_current_user_id();
        $project_id = $request->get_param('project_id');
        $task_name = sanitize_text_field($request->get_param('task_name'));
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $end_time = sanitize_text_field($request->get_param('end_time'));
        $billable = intval($request->get_param('billable'));

        $start_timestamp = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        $total_seconds = $end_timestamp - $start_timestamp;

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';

        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'project_id' => $project_id,
            'task_name' => $task_name,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'total_seconds' => $total_seconds,
            'billable' => $billable,
            'date' => date('Y-m-d', $start_timestamp)
        ));

        return new WP_REST_Response(array('message' => 'Time log created', 'id' => $wpdb->insert_id), 201);
    }

    public function get_projects($request)
    {
        $user_id = get_current_user_id();
        $role = IWMS_Roles::get_user_role($user_id);

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';

        if (in_array($role, array('administrator', 'iwms_hr_manager'))) {
            $projects = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        } else {
            $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
            $projects = $wpdb->get_results($wpdb->prepare(
                "SELECT p.* FROM $table p 
                 LEFT JOIN $assignment_table pa ON p.id = pa.project_id 
                 WHERE p.manager_id = %d OR pa.user_id = %d 
                 GROUP BY p.id ORDER BY p.created_at DESC",
                $user_id, $user_id
            ));
        }

        return new WP_REST_Response($projects, 200);
    }

    public function get_daily_report($request)
    {
        $date = $request->get_param('date');

        global $wpdb;
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $attendance_table = $wpdb->prefix . 'iwms_attendance';

        $time_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT tl.*, u.display_name FROM $timelogs_table tl 
             LEFT JOIN {$wpdb->users} u ON tl.user_id = u.ID 
             WHERE tl.date = %s ORDER BY tl.start_time ASC",
            $date
        ));

        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name FROM $attendance_table a 
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
             WHERE a.date = %s ORDER BY a.check_in ASC",
            $date
        ));

        return new WP_REST_Response(array(
            'time_logs' => $time_logs,
            'attendance' => $attendance
        ), 200);
    }

    public function get_monthly_report($request)
    {
        $month = $request->get_param('month');
        $year = $request->get_param('year');

        global $wpdb;
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';

        $reports = $wpdb->get_results($wpdb->prepare(
            "SELECT u.display_name, 
                    SUM(CASE WHEN tl.billable = 1 THEN tl.total_seconds ELSE 0 END)/3600 as billable_hours,
                    SUM(CASE WHEN tl.billable = 0 THEN tl.total_seconds ELSE 0 END)/3600 as non_billable_hours
             FROM {$wpdb->users} u 
             LEFT JOIN $timelogs_table tl ON u.ID = tl.user_id AND MONTH(tl.date) = %d AND YEAR(tl.date) = %d
             GROUP BY u.ID, u.display_name ORDER BY u.display_name",
            $month, $year
        ));

        return new WP_REST_Response($reports, 200);
    }

    public function get_payroll($request)
    {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $month = $request->get_param('month') ?: date('m');
        $year = $request->get_param('year') ?: date('Y');

        if (!IWMS_Roles::has_capability('iwms_manage_payroll') && $user_id != get_current_user_id()) {
            return new WP_Error('unauthorized', 'You cannot view this data', array('status' => 403));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_payroll';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND month = %d AND year = %d",
            $user_id, $month, $year
        ));

        return new WP_REST_Response($results, 200);
    }
}
