jQuery(document).ready(function($) {
    // Attendance functionality
    $('#check-in-btn').on('click', function() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_check_in',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#attendance-status').html('<div class="alert alert-success">Checked in successfully!</div>');
                    $('#check-in-btn').prop('disabled', true);
                    loadAttendanceData();
                } else {
                    $('#attendance-status').html('<div class="alert alert-danger">' + response.data + '</div>');
                }
            }
        });
    });

    $('#check-out-btn').on('click', function() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_check_out',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#attendance-status').html('<div class="alert alert-success">Checked out successfully! Total hours: ' + response.data.total_hours + '</div>');
                    $('#check-out-btn').prop('disabled', true);
                    loadAttendanceData();
                } else {
                    $('#attendance-status').html('<div class="alert alert-danger">' + response.data + '</div>');
                }
            }
        });
    });

    // Timer functionality
    let timerInterval;
    let startTime;
    let runningLogId;

    $('#start-timer-btn').on('click', function() {
        const projectId = $('#project-select').val();
        const taskName = $('#task-name').val();
        const description = $('#task-description').val();
        const billable = $('#billable-check').is(':checked') ? 1 : 0;

        if (!taskName) {
            alert('Please enter a task name');
            return;
        }

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_start_timer',
                nonce: iwms_ajax.nonce,
                project_id: projectId,
                task_name: taskName,
                description: description,
                billable: billable
            },
            success: function(response) {
                if (response.success) {
                    runningLogId = response.data.log_id;
                    startTime = new Date();
                    $('#start-timer-btn').hide();
                    $('#stop-timer-btn').show();
                    timerInterval = setInterval(updateTimer, 1000);
                } else {
                    alert(response.data);
                }
            }
        });
    });

    $('#stop-timer-btn').on('click', function() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_stop_timer',
                nonce: iwms_ajax.nonce,
                log_id: runningLogId
            },
            success: function(response) {
                if (response.success) {
                    clearInterval(timerInterval);
                    $('#timer-display').text('00:00:00');
                    $('#start-timer-btn').show();
                    $('#stop-timer-btn').hide();
                    loadTimeLogs();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    $('#manual-time-form').on('submit', function(e) {
        e.preventDefault();

        const date = $('#manual-date').val();
        const startTimeInput = $('#manual-start').val();
        const endTimeInput = $('#manual-end').val();
        const taskName = $('#manual-task').val();

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_log_manual_time',
                nonce: iwms_ajax.nonce,
                date: date,
                start_time: startTimeInput,
                end_time: endTimeInput,
                task_name: taskName,
                project_id: $('#project-select').val(),
                description: $('#task-description').val(),
                billable: $('#billable-check').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $('#manual-time-form')[0].reset();
                    loadTimeLogs();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Leave functionality
    $('#leave-request-form').on('submit', function(e) {
        e.preventDefault();

        const leaveType = $('#leave-type').val();
        const startDate = $('#start-date').val();
        const endDate = $('#end-date').val();
        const reason = $('#leave-reason').val();

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_request_leave',
                nonce: iwms_ajax.nonce,
                leave_type: leaveType,
                start_date: startDate,
                end_date: endDate,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert('Leave request submitted successfully!');
                    $('#leave-request-form')[0].reset();
                    loadMyLeaves();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Project management
    $('#create-project-form').on('submit', function(e) {
        e.preventDefault();

        const projectData = {
            action: 'iwms_create_project',
            nonce: iwms_ajax.nonce,
            name: $('#project-name').val(),
            manager_id: $('#project-manager').val(),
            budget: $('#project-budget').val(),
            estimated_hours: $('#project-hours').val(),
            description: $('#project-description').val(),
            start_date: $('#project-start').val(),
            end_date: $('#project-end').val()
        };

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: projectData,
            success: function(response) {
                if (response.success) {
                    alert('Project created successfully!');
                    $('#create-project-form')[0].reset();
                    loadProjects();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Load data functions
    function loadAttendanceData() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_get_attendance',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(record) {
                        html += '<tr>';
                        html += '<td>' + record.date + '</td>';
                        html += '<td>' + (record.check_in ? record.check_in.split(' ')[1] : '-') + '</td>';
                        html += '<td>' + (record.check_out ? record.check_out.split(' ')[1] : '-') + '</td>';
                        html += '<td>' + record.total_hours + '</td>';
                        html += '<td>' + record.status + '</td>';
                        html += '</tr>';
                    });
                    $('#attendance-table tbody').html(html);
                }
            }
        });
    }

    function loadTimeLogs() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_get_time_logs',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(log) {
                        const duration = new Date(log.total_seconds * 1000).toISOString().substr(11, 8);
                        html += '<tr>';
                        html += '<td>' + log.task_name + '</td>';
                        html += '<td>' + (log.project_name || '-') + '</td>';
                        html += '<td>' + log.start_time.split(' ')[1] + '</td>';
                        html += '<td>' + (log.end_time ? log.end_time.split(' ')[1] : '-') + '</td>';
                        html += '<td>' + duration + '</td>';
                        html += '<td>' + (log.billable ? 'Yes' : 'No') + '</td>';
                        html += '</tr>';
                    });
                    $('#timelogs-table tbody').html(html);
                }
            }
        });
    }

    function updateTimer() {
        const now = new Date();
        const diff = now - startTime;
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);

        $('#timer-display').text(
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0')
        );
    }

    function loadProjects() {
        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_get_projects',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">Select Project (Optional)</option>';
                    response.data.forEach(function(project) {
                        options += '<option value="' + project.id + '">' + project.name + '</option>';
                    });
                    $('#project-select').html(options);
                }
            }
        });

        // Also load project list if on projects page
        if ($('#projects-list').length) {
            $.ajax({
                url: iwms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwms_get_projects',
                    nonce: iwms_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<div class="row">';
                        response.data.forEach(function(project) {
                            html += '<div class="col-md-6 mb-3">';
                            html += '<div class="card">';
                            html += '<div class="card-body">';
                            html += '<h5 class="card-title">' + project.name + '</h5>';
                            html += '<p class="card-text">' + (project.description || 'No description') + '</p>';
                            html += '<p class="mb-1"><strong>Manager:</strong> ' + (project.manager_name || 'N/A') + '</p>';
                            html += '<p class="mb-1"><strong>Team Size:</strong> ' + (project.team_size || 0) + ' members</p>';
                            html += '<p class="mb-1"><strong>Budget:</strong> $' + (project.budget || 0) + '</p>';
                            html += '<p class="mb-1"><strong>Hours:</strong> ' + (project.estimated_hours || 0) + '</p>';
                            html += '<p class="mb-1"><strong>Status:</strong> <span class="badge bg-primary">' + (project.status || 'Active') + '</span></p>';
                            html += '</div></div></div>';
                        });
                        html += '</div>';
                        $('#projects-list').html(html);
                    } else {
                        $('#projects-list').html('<p>No projects found.</p>');
                    }
                }
            });
        }
    }

    function loadPendingLeaves() {
        if (!$('#pending-leaves-list').length) return;

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_get_pending_leaves',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<div class="list-group">';
                    response.data.forEach(function(leave) {
                        html += '<div class="list-group-item">';
                        html += '<h6>' + leave.display_name + '</h6>';
                        html += '<p>' + leave.leave_type + ' leave from ' + leave.start_date + ' to ' + leave.end_date + '</p>';
                        html += '<p><small>' + leave.reason + '</small></p>';
                        html += '<button class="btn btn-success btn-sm me-2 approve-leave" data-id="' + leave.id + '">Approve</button>';
                        html += '<button class="btn btn-danger btn-sm reject-leave" data-id="' + leave.id + '">Reject</button>';
                        html += '</div>';
                    });
                    html += '</div>';
                    $('#pending-leaves-list').html(html);
                } else {
                    $('#pending-leaves-list').html('<p>No pending leave requests.</p>');
                }
            }
        });
    }

    function loadMyLeaves() {
        if (!$('#my-leaves-list').length) return;

        $.ajax({
            url: iwms_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwms_get_leaves',
                nonce: iwms_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-striped">';
                    html += '<thead><tr><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Approved By</th></tr></thead><tbody>';
                    response.data.forEach(function(leave) {
                        let statusClass = leave.status === 'approved' ? 'success' : (leave.status === 'rejected' ? 'danger' : 'warning');
                        html += '<tr>';
                        html += '<td>' + leave.leave_type + '</td>';
                        html += '<td>' + leave.start_date + '</td>';
                        html += '<td>' + leave.end_date + '</td>';
                        html += '<td><span class="badge bg-' + statusClass + '">' + leave.status + '</span></td>';
                        html += '<td>' + (leave.approver_name || '-') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    $('#my-leaves-list').html(html);
                } else {
                    $('#my-leaves-list').html('<p>No leave history found.</p>');
                }
            }
        });
    }

    // Event handlers for dynamic elements
    $(document).on('click', '.approve-leave', function() {
        const leaveId = $(this).data('id');
        if (confirm('Are you sure you want to approve this leave request?')) {
            $.ajax({
                url: iwms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwms_approve_leave',
                    nonce: iwms_ajax.nonce,
                    leave_id: leaveId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Leave approved successfully!');
                        loadPendingLeaves();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }
    });

    $(document).on('click', '.reject-leave', function() {
        const leaveId = $(this).data('id');
        const reason = prompt('Reason for rejection:');
        if (reason !== null) {
            $.ajax({
                url: iwms_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwms_reject_leave',
                    nonce: iwms_ajax.nonce,
                    leave_id: leaveId,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        alert('Leave rejected!');
                        loadPendingLeaves();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }
    });

    // Initialize
    loadAttendanceData();
    loadTimeLogs();
    loadProjects();

    // Check for running timer on page load
    $.ajax({
        url: iwms_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'iwms_get_time_logs',
            nonce: iwms_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                const runningLog = response.data.find(log => log.status === 'running');
                if (runningLog) {
                    runningLogId = runningLog.id;
                    startTime = new Date(runningLog.start_time);
                    $('#start-timer-btn').hide();
                    $('#stop-timer-btn').show();
                    timerInterval = setInterval(updateTimer, 1000);
                }
            }
        }
    });

    // Load leave and project data when sections are active
    if ($('#pending-leaves-list').length) loadPendingLeaves();
    if ($('#my-leaves-list').length) loadMyLeaves();
});
