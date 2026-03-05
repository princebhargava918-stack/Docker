<?php
class IWMS_Dashboard
{
    public function __construct()
    {
        add_shortcode('iwms_dashboard', array($this, 'render_dashboard'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_iwms_load_section', array($this, 'load_section'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('iwms-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css');
        wp_enqueue_script('iwms-bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);
        wp_enqueue_script('iwms-dashboard', plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/dashboard.js', array('jquery'), '1.0', true);
        wp_localize_script('iwms-dashboard', 'iwms_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iwms_nonce')
        ));
    }

    public function render_dashboard()
    {
        if (!is_user_logged_in()) {
            return '<div class="alert alert-warning">Please log in to access the dashboard.</div>';
        }

        if (!IWMS_Roles::has_capability('iwms_access_dashboard')) {
            return '<div class="alert alert-danger">You do not have permission to access this dashboard.</div>';
        }

        ob_start();
        ?>
        <div class="iwms-dashboard container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 px-0 bg-light sidebar">
                    <div class="d-flex flex-column p-3">
                        <h5 class="mb-3">IWMS Dashboard</h5>
                        <ul class="nav nav-pills flex-column mb-auto">
                            <li class="nav-item">
                                <a href="#" class="nav-link active" data-section="overview">Overview</a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="attendance">Attendance</a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="timer">Time Tracker</a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="projects">Projects</a>
                            </li>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="leaves">Leave Requests</a>
                            </li>
                            <?php if (IWMS_Roles::has_capability('iwms_view_reports')): ?>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="reports">Reports</a>
                            </li>
                            <?php endif; ?>
                            <?php if (IWMS_Roles::has_capability('iwms_manage_payroll')): ?>
                            <li class="nav-item">
                                <a href="#" class="nav-link" data-section="payroll">Payroll</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-md-9 col-lg-10 px-4 py-3">
                    <div id="dashboard-content">
                        <?php echo $this->render_overview(); ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.sidebar .nav-link').on('click', function(e) {
                e.preventDefault();
                $('.sidebar .nav-link').removeClass('active');
                $(this).addClass('active');

                var section = $(this).data('section');

                // Show loading
                $('#dashboard-content').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

                // Load content via AJAX
                $.ajax({
                    url: iwms_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'iwms_load_section',
                        section: section,
                        nonce: iwms_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#dashboard-content').html(response.data);
                        } else {
                            $('#dashboard-content').html('<div class="alert alert-danger">Error loading section.</div>');
                        }
                    },
                    error: function() {
                        $('#dashboard-content').html('<div class="alert alert-danger">Error loading section.</div>');
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();

    public function load_section()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'iwms_nonce') || !IWMS_Roles::has_capability('iwms_access_dashboard')) {
            wp_die('Unauthorized');
        }

        $section = sanitize_text_field($_POST['section']);

        switch($section) {
            case 'overview':
                $content = $this->render_overview();
                break;
            case 'attendance':
                $content = $this->render_attendance();
                break;
            case 'timer':
                $content = $this->render_timer();
                break;
            case 'projects':
                $content = $this->render_projects();
                break;
            case 'leaves':
                $content = $this->render_leaves();
                break;
            case 'reports':
                if (!IWMS_Roles::has_capability('iwms_view_reports')) {
                    $content = '<div class="alert alert-danger">You do not have permission to view reports.</div>';
                } else {
                    $content = $this->render_reports();
                }
                break;
            case 'payroll':
                if (!IWMS_Roles::has_capability('iwms_manage_payroll')) {
                    $content = '<div class="alert alert-danger">You do not have permission to manage payroll.</div>';
                } else {
                    $content = $this->render_payroll();
                }
                break;
            default:
                $content = '<div class="alert alert-warning">Section not found.</div>';
        }

        wp_send_json_success($content);
    }

    private function render_overview()
    {
        $user_id = get_current_user_id();
        $today = current_time('Y-m-d');

        // Get today's attendance
        $attendance_today = IWMS_Attendance::get_today_status($user_id);

        // Get this month's hours
        global $wpdb;
        $timelogs_table = $wpdb->prefix . 'iwms_timelogs';
        $month_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_seconds)/3600 FROM $timelogs_table WHERE user_id = %d AND MONTH(date) = %d AND YEAR(date) = %d",
            $user_id, date('m'), date('Y')
        )) ?: 0;

        // Get pending leaves
        $leaves_table = $wpdb->prefix . 'iwms_leaves';
        $pending_leaves = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $leaves_table WHERE user_id = %d AND status = 'pending'",
            $user_id
        )) ?: 0;

        ob_start();
        ?>
        <h2>Dashboard Overview</h2>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Today's Status</h5>
                        <p class="card-text">
                            <?php echo $attendance_today ? 'Checked In: ' . date('H:i', strtotime($attendance_today->check_in)) : 'Not Checked In'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Hours</h5>
                        <p class="card-text"><?php echo number_format($month_hours, 1); ?> hrs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending Leaves</h5>
                        <p class="card-text"><?php echo $pending_leaves; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Active Projects</h5>
                        <p class="card-text"><?php echo $this->get_active_projects_count($user_id); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h4>Recent Time Logs</h4>
                <?php echo $this->render_recent_logs(); ?>
            </div>
            <div class="col-md-6">
                <h4>Upcoming Tasks</h4>
                <p>No upcoming tasks.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_attendance()
    {
        ob_start();
        ?>
        <h2>Attendance Management</h2>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Today's Attendance</h5>
                <button id="check-in-btn" class="btn btn-success me-2">Check In</button>
                <button id="check-out-btn" class="btn btn-danger">Check Out</button>
                <p id="attendance-status" class="mt-2"></p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Monthly Attendance</div>
            <div class="card-body">
                <table class="table table-striped" id="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_timer()
    {
        ob_start();
        ?>
        <h2>Time Tracker</h2>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Live Timer</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select id="project-select" class="form-select">
                            <option value="">Select Project (Optional)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" id="task-name" class="form-control" placeholder="Task Name">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <textarea id="task-description" class="form-control" placeholder="Description (Optional)"></textarea>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="billable-check">
                            <label class="form-check-label" for="billable-check">Billable</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button id="start-timer-btn" class="btn btn-primary">Start Timer</button>
                        <button id="stop-timer-btn" class="btn btn-danger" style="display:none;">Stop Timer</button>
                    </div>
                </div>
                <div id="timer-display" class="h3 text-primary">00:00:00</div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Manual Time Entry</div>
            <div class="card-body">
                <form id="manual-time-form">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="date" id="manual-date" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <input type="time" id="manual-start" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <input type="time" id="manual-end" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="manual-task" class="form-control" placeholder="Task Name" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success">Log Time</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Today's Time Logs</div>
            <div class="card-body">
                <table class="table table-striped" id="timelogs-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Project</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration</th>
                            <th>Billable</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_projects()
    {
        $user_id = get_current_user_id();
        $role = IWMS_Roles::get_user_role($user_id);
        $can_manage = IWMS_Roles::has_capability('iwms_manage_projects');

        ob_start();
        ?>
        <h2>Project Management</h2>

        <?php if ($can_manage): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Create New Project</h5>
            </div>
            <div class="card-body">
                <form id="create-project-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-name" class="form-label">Project Name *</label>
                                <input type="text" class="form-control" id="project-name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-manager" class="form-label">Project Manager *</label>
                                <select class="form-select" id="project-manager" required>
                                    <option value="">Select Manager</option>
                                    <?php
                                    $managers = get_users(array('role' => 'iwms_project_manager'));
                                    foreach ($managers as $manager) {
                                        echo '<option value="' . $manager->ID . '">' . $manager->display_name . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-budget" class="form-label">Budget</label>
                                <input type="number" class="form-control" id="project-budget" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-hours" class="form-label">Estimated Hours</label>
                                <input type="number" class="form-control" id="project-hours" step="0.01">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-start" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="project-start">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="project-end" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="project-end">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="project-description" class="form-label">Description</label>
                        <textarea class="form-control" id="project-description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>My Projects</h5>
            </div>
            <div class="card-body">
                <div id="projects-list">
                    <p>Loading projects...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_leaves()
    {
        $user_id = get_current_user_id();
        $role = IWMS_Roles::get_user_role($user_id);
        $can_approve = IWMS_Roles::has_capability('iwms_approve_leave');

        ob_start();
        ?>
        <h2>Leave Management</h2>

        <?php if ($can_approve): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Pending Leave Requests</h5>
            </div>
            <div class="card-body">
                <div id="pending-leaves-list">
                    <p>Loading pending requests...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Request Leave</h5>
                    </div>
                    <div class="card-body">
                        <form id="leave-request-form">
                            <div class="mb-3">
                                <label for="leave-type" class="form-label">Leave Type</label>
                                <select class="form-select" id="leave-type" required>
                                    <option value="">Select Type</option>
                                    <option value="paid">Paid Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="casual">Casual Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start-date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start-date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end-date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end-date" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="leave-reason" class="form-label">Reason</label>
                                <textarea class="form-control" id="leave-reason" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>My Leave History</h5>
                    </div>
                    <div class="card-body">
                        <div id="my-leaves-list">
                            <p>Loading your leave history...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_reports()
    {
        ob_start();
        ?>
        <h2>Reports</h2>
        <p>Reports interface will be implemented here.</p>
        <?php
        return ob_get_clean();
    }

    private function render_payroll()
    {
        ob_start();
        ?>
        <h2>Payroll Management</h2>
        <p>Payroll interface will be implemented here.</p>
        <?php
        return ob_get_clean();
    }

    private function get_active_projects_count($user_id)
    {
        global $wpdb;
        $assignment_table = $wpdb->prefix . 'iwms_project_assignments';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $assignment_table WHERE user_id = %d",
            $user_id
        )) ?: 0;
    }

    private function render_recent_logs()
    {
        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'iwms_timelogs';
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY start_time DESC LIMIT 5",
            $user_id
        ));
        
        if (empty($logs)) {
            return '<p>No recent time logs.</p>';
        }
        
        $output = '<ul class="list-group">';
        foreach ($logs as $log) {
            $duration = gmdate('H:i:s', $log->total_seconds);
            $output .= '<li class="list-group-item">' . esc_html($log->task_name) . ' - ' . $duration . '</li>';
        }
        $output .= '</ul>';
        
        return $output;
    }
}
