<?php
class IWMS_Projects
{
    public function __construct()
    {
        add_action('wp_ajax_iwms_create_project', array($this, 'create_project'));
        add_action('wp_ajax_iwms_update_project', array($this, 'update_project'));
        add_action('wp_ajax_iwms_delete_project', array($this, 'delete_project'));
        add_action('wp_ajax_iwms_assign_users', array($this, 'assign_users'));
        add_action('wp_ajax_iwms_get_projects', array($this, 'get_projects'));
        add_action('wp_ajax_iwms_get_project_details', array($this, 'get_project_details'));
    }

    public function create_project()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_manage_projects')) {
            wp_die('Unauthorized');
        }

        $name = sanitize_text_field($_POST['name']);
        $manager_id = intval($_POST['manager_id']);
        $budget = floatval($_POST['budget']);
        $estimated_hours = floatval($_POST['estimated_hours']);
        $description = sanitize_textarea_field($_POST['description']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Validate required fields
        if (empty($name) || !$manager_id) {
            wp_send_json_error('Project name and manager are required');
            return;
        }

        // Validate manager role
        $manager = get_userdata($manager_id);
        if (!$manager || !in_array('iwms_project_manager', $manager->roles)) {
            wp_send_json_error('Selected user is not a project manager');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';

        $wpdb->insert($table, array(
            'name' => $name,
            'manager_id' => $manager_id,
            'budget' => $budget,
            'estimated_hours' => $estimated_hours,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date
        ));

        $project_id = $wpdb->insert_id;

        // Auto-assign manager to the project
        $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
        $wpdb->insert($assignment_table, array(
            'project_id' => $project_id,
            'user_id' => $manager_id
        ));

        wp_send_json_success(array(
            'message' => 'Project created successfully',
            'project_id' => $project_id
        ));
    }

    public function update_project()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_manage_projects')) {
            wp_die('Unauthorized');
        }

        $project_id = intval($_POST['project_id']);
        $name = sanitize_text_field($_POST['name']);
        $manager_id = intval($_POST['manager_id']);
        $budget = floatval($_POST['budget']);
        $estimated_hours = floatval($_POST['estimated_hours']);
        $description = sanitize_textarea_field($_POST['description']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $status = sanitize_text_field($_POST['status']);

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';

        $wpdb->update($table, array(
            'name' => $name,
            'manager_id' => $manager_id,
            'budget' => $budget,
            'estimated_hours' => $estimated_hours,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => $status
        ), array('id' => $project_id));

        wp_send_json_success('Project updated successfully');
    }

    public function delete_project()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_manage_projects')) {
            wp_die('Unauthorized');
        }

        $project_id = intval($_POST['project_id']);

        global $wpdb;

        // Delete project assignments
        $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
        $wpdb->delete($assignment_table, array('project_id' => $project_id));

        // Delete time logs associated with this project
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $wpdb->delete($timelogs_table, array('project_id' => $project_id));

        // Delete project
        $table = $wpdb->prefix . 'iwms_projects';
        $wpdb->delete($table, array('id' => $project_id));

        wp_send_json_success('Project deleted successfully');
    }

    public function assign_users()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_assign_projects')) {
            wp_die('Unauthorized');
        }

        $project_id = intval($_POST['project_id']);
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();

        // Validate that current user can manage this project
        if (!$this->can_manage_project($project_id)) {
            wp_send_json_error('You cannot manage this project');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_project_assignments';

        // Remove existing assignments
        $wpdb->delete($table, array('project_id' => $project_id));

        // Add new assignments
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user && in_array('iwms_employee', $user->roles)) {
                $wpdb->insert($table, array(
                    'project_id' => $project_id,
                    'user_id' => $user_id
                ));
            }
        }

        wp_send_json_success('Users assigned successfully');
    }

    public function get_projects()
    {
        $user_id = get_current_user_id();
        $role = IWMS_Roles::get_user_role($user_id);

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';

        if (in_array($role, array('administrator', 'iwms_hr_manager'))) {
            $projects = $wpdb->get_results(
                "SELECT p.*, u.display_name as manager_name,
                        COUNT(pa.user_id) as team_size
                 FROM $table p 
                 LEFT JOIN {$wpdb->users} u ON p.manager_id = u.ID
                 LEFT JOIN {$wpdb->prefix}iwms_project_assignments pa ON p.id = pa.project_id
                 GROUP BY p.id
                 ORDER BY p.created_at DESC"
            );
        } else {
            // Get projects where user is assigned or is manager
            $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
            $projects = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, u.display_name as manager_name,
                        COUNT(pa2.user_id) as team_size
                 FROM $table p 
                 LEFT JOIN {$wpdb->users} u ON p.manager_id = u.ID
                 LEFT JOIN $assignment_table pa ON p.id = pa.project_id 
                 LEFT JOIN $assignment_table pa2 ON p.id = pa2.project_id
                 WHERE p.manager_id = %d OR pa.user_id = %d 
                 GROUP BY p.id
                 ORDER BY p.created_at DESC",
                $user_id, $user_id
            ));
        }

        wp_send_json_success($projects);
    }

    public function get_project_details()
    {
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();

        if (!$this->can_access_project($project_id, $user_id)) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';

        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, u.display_name as manager_name
             FROM $table p 
             LEFT JOIN {$wpdb->users} u ON p.manager_id = u.ID
             WHERE p.id = %d",
            $project_id
        ));

        if (!$project) {
            wp_send_json_error('Project not found');
            return;
        }

        // Get assigned users
        $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
        $assigned_users = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name
             FROM $assignment_table pa
             LEFT JOIN {$wpdb->users} u ON pa.user_id = u.ID
             WHERE pa.project_id = %d",
            $project_id
        ));

        // Get time logs summary
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $time_summary = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(total_seconds)/3600 as total_hours,
                SUM(CASE WHEN billable = 1 THEN total_seconds ELSE 0 END)/3600 as billable_hours,
                COUNT(DISTINCT user_id) as active_members
             FROM $timelogs_table 
             WHERE project_id = %d",
            $project_id
        ));

        wp_send_json_success(array(
            'project' => $project,
            'assigned_users' => $assigned_users,
            'time_summary' => $time_summary
        ));
    }

    private function can_manage_project($project_id)
    {
        $user_id = get_current_user_id();
        $role = IWMS_Roles::get_user_role($user_id);

        if (in_array($role, array('administrator', 'iwms_hr_manager'))) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'iwms_projects';
        $manager_id = $wpdb->get_var($wpdb->prepare(
            "SELECT manager_id FROM $table WHERE id = %d",
            $project_id
        ));

        return $manager_id == $user_id;
    }

    private function can_access_project($project_id, $user_id)
    {
        $role = IWMS_Roles::get_user_role($user_id);

        if (in_array($role, array('administrator', 'iwms_hr_manager'))) {
            return true;
        }

        global $wpdb;
        $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
        $projects_table = $wpdb->prefix . 'iwms_projects';

        $access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table p
             LEFT JOIN $assignment_table pa ON p.id = pa.project_id
             WHERE p.id = %d AND (p.manager_id = %d OR pa.user_id = %d)",
            $project_id, $user_id, $user_id
        ));

        return $access > 0;
    }
}
