<?php

class IWMS_Roles
{
    public function __construct()
    {
        add_action('init', array($this, 'add_custom_roles'));
        add_action('admin_init', array($this, 'add_capabilities'));
    }

    public function add_custom_roles()
    {
        $roles_to_create = array(
            'iwms_hr_manager' => 'HR Manager',
            'iwms_project_manager' => 'Project Manager',
            'iwms_team_lead' => 'Team Lead',
            'iwms_employee' => 'Employee',
            'iwms_finance_manager' => 'Finance Manager'
        );

        foreach ($roles_to_create as $role_slug => $role_name) {
            if (!get_role($role_slug)) {
                add_role($role_slug, $role_name, array(
                    'read' => true,
                ));
            }
        }

        // Clean up any potential duplicates (one-time cleanup)
        $this->cleanup_duplicate_roles();
    }

    private function cleanup_duplicate_roles()
    {
        global $wpdb;

        // Check if roles table has duplicates (this is a one-time cleanup)
        $roles_option = get_option($wpdb->prefix . 'user_roles');
        if ($roles_option) {
            $clean_roles = array();
            foreach ($roles_option as $role_key => $role_data) {
                // Only keep IWMS roles once
                if (strpos($role_key, 'iwms_') === 0) {
                    if (!isset($clean_roles[$role_key])) {
                        $clean_roles[$role_key] = $role_data;
                    }
                } else {
                    $clean_roles[$role_key] = $role_data;
                }
            }
            update_option($wpdb->prefix . 'user_roles', $clean_roles);
        }
    }

    public function add_capabilities()
    {
        $roles = array('administrator', 'iwms_hr_manager', 'iwms_project_manager', 'iwms_team_lead', 'iwms_employee', 'iwms_finance_manager');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                // Basic capabilities
                $role->add_cap('iwms_access_dashboard');

                // Attendance capabilities
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_employee'))) {
                    $role->add_cap('iwms_check_in');
                    $role->add_cap('iwms_check_out');
                }

                // Time tracking capabilities
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_project_manager', 'iwms_team_lead', 'iwms_employee'))) {
                    $role->add_cap('iwms_log_time');
                    $role->add_cap('iwms_start_timer');
                }

                // Project management
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_project_manager'))) {
                    $role->add_cap('iwms_manage_projects');
                    $role->add_cap('iwms_assign_projects');
                }

                // Leave management
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_employee'))) {
                    $role->add_cap('iwms_request_leave');
                }
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_project_manager'))) {
                    $role->add_cap('iwms_approve_leave');
                }

                // Payroll
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_finance_manager'))) {
                    $role->add_cap('iwms_manage_payroll');
                }

                // Reports
                if (in_array($role_name, array('administrator', 'iwms_hr_manager', 'iwms_project_manager', 'iwms_finance_manager'))) {
                    $role->add_cap('iwms_view_reports');
                }
            }
        }
    }

    public static function get_user_role($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user ? $user->roles[0] : false;
    }

    public static function has_capability($capability, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user && $user->has_cap($capability);
    }
}