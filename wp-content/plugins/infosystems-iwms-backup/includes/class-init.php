<?php
class IWMS_Init
{

    public function __construct()
    {
        $this->load_dependencies();
        $this->init_modules();
    }

    private function load_dependencies()
    {
        require_once IWMS_PATH . 'includes/class-roles.php';
        require_once IWMS_PATH . 'includes/class-dashboard.php';
        require_once IWMS_PATH . 'includes/class-attendance.php';
        require_once IWMS_PATH . 'includes/class-timetracker.php';
        require_once IWMS_PATH . 'includes/class-projects.php';
        require_once IWMS_PATH . 'includes/class-leaves.php';
        require_once IWMS_PATH . 'includes/class-payroll.php';
        require_once IWMS_PATH . 'includes/class-reports.php';
        require_once IWMS_PATH . 'includes/class-api.php';
    }

    private function init_modules()
    {
        new IWMS_Roles();
        new IWMS_Dashboard();
        new IWMS_Attendance();
        new IWMS_TimeTracker();
        new IWMS_Projects();
        new IWMS_Leaves();
        new IWMS_Payroll();
        new IWMS_Reports();
        new IWMS_API();
    }
}

new IWMS_Init();