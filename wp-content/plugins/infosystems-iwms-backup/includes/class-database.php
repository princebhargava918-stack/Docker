<?php

class IWMS_Database
{
    public function __construct()
    {
        // No activation hooks here - moved to main plugin file
    }

    public function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Include the dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Attendance table
        $table_attendance = $wpdb->prefix . 'iwms_attendance';
        $sql_attendance = "CREATE TABLE $table_attendance (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            date date NOT NULL,
            check_in datetime DEFAULT NULL,
            check_out datetime DEFAULT NULL,
            total_hours decimal(5,2) DEFAULT 0,
            status varchar(20) DEFAULT 'present',
            PRIMARY KEY (id),
            UNIQUE KEY user_date (user_id, date)
        ) $charset_collate;";

        // Time logs table
        $table_timelogs = $wpdb->prefix . 'iwms_timelogs';
        $sql_timelogs = "CREATE TABLE $table_timelogs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            project_id mediumint(9) DEFAULT NULL,
            task_name varchar(255) NOT NULL,
            description text,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            total_seconds int(11) DEFAULT 0,
            billable tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'completed',
            date date NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_user_date (user_id, date)
        ) $charset_collate;";

        // Projects table
        $table_projects = $wpdb->prefix . 'iwms_projects';
        $sql_projects = "CREATE TABLE $table_projects (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            manager_id bigint(20) NOT NULL,
            budget decimal(10,2) DEFAULT 0,
            estimated_hours decimal(6,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Project assignments table
        $table_assignments = $wpdb->prefix . 'iwms_project_assignments';
        $sql_assignments = "CREATE TABLE $table_assignments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id, user_id)
        ) $charset_collate;";

        // Leaves table
        $table_leaves = $wpdb->prefix . 'iwms_leaves';
        $sql_leaves = "CREATE TABLE $table_leaves (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            leave_type varchar(50) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            reason text,
            status varchar(20) DEFAULT 'pending',
            approved_by bigint(20) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Holidays table
        $table_holidays = $wpdb->prefix . 'iwms_holidays';
        $sql_holidays = "CREATE TABLE $table_holidays (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            name varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'holiday',
            PRIMARY KEY (id),
            UNIQUE KEY holiday_date (date)
        ) $charset_collate;";

        // Payroll table
        $table_payroll = $wpdb->prefix . 'iwms_payroll';
        $sql_payroll = "CREATE TABLE $table_payroll (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            month int(2) NOT NULL,
            year int(4) NOT NULL,
            base_salary decimal(10,2) DEFAULT 0,
            billable_hours decimal(6,2) DEFAULT 0,
            hourly_rate decimal(8,2) DEFAULT 0,
            unpaid_leaves int(3) DEFAULT 0,
            overtime decimal(6,2) DEFAULT 0,
            total_salary decimal(10,2) DEFAULT 0,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_month_year (user_id, month, year)
        ) $charset_collate;";

        dbDelta($sql_attendance);
        dbDelta($sql_timelogs);
        dbDelta($sql_projects);
        dbDelta($sql_assignments);
        dbDelta($sql_leaves);
        dbDelta($sql_holidays);
        dbDelta($sql_payroll);

        // Set default roles and capabilities
        $this->set_default_roles();
    }

    public function deactivate()
    {
        // Optional: drop tables on deactivate, but usually not recommended
    }

    private function set_default_roles()
    {
        // This will be handled by IWMS_Roles class
    }
}