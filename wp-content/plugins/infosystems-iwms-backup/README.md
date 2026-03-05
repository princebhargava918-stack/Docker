# InfoSystems Workforce Management Suite (IWMS)

A comprehensive enterprise HRMS + Project & Time Tracking system built as a WordPress plugin, comparable to Zoho People/Time Tracker.

## 🚀 Features

### ✅ Core Features Implemented
- **Role-Based Access Control**: 5 custom roles with granular permissions
- **Attendance System**: Check-in/check-out with auto calculations
- **Time Tracking**: Live timer + manual logging with 9-hour validations
- **Project Management**: Create projects, assign teams, track progress
- **Leave Management**: Request/approve workflow with email notifications
- **Payroll Engine**: Automatic salary calculations with billable hours
- **Reporting**: Daily/monthly reports with CSV export
- **REST API**: Mobile app integration ready

### 🎯 Key Features
- **9-Hour Workday Enforcement**: Auto-stop timers, prevent over-logging
- **Holiday Integration**: Prevent logging on holidays/leaves
- **Email Notifications**: Leave requests and approvals
- **Cron Jobs**: Automated timer stopping every 15 minutes
- **Modern UI**: Bootstrap-based responsive dashboard
- **Security**: Nonces, capability checks, input sanitization

## 🏗️ Installation

1. Upload the `infosystems-iwms` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Use shortcode `[iwms_dashboard]` on any page
4. Assign appropriate roles to users

## 👥 User Roles

- **Administrator**: Full access
- **HR Manager**: Employee management, leave approvals
- **Project Manager**: Project creation, team assignments
- **Team Lead**: Time tracking, basic approvals
- **Employee**: Basic attendance and time logging
- **Finance Manager**: Payroll and financial reports

## 📊 Database Tables

- `wp_iwms_attendance`: Daily attendance records
- `wp_iwms_timelogs`: Time tracking entries
- `wp_iwms_projects`: Project information
- `wp_iwms_project_assignments`: Team assignments
- `wp_iwms_leaves`: Leave requests and approvals
- `wp_iwms_holidays`: Holiday calendar
- `wp_iwms_payroll`: Monthly payroll data

## 🔧 API Endpoints

- `GET /wp-json/iwms/v1/attendance` - Attendance data
- `GET /wp-json/iwms/v1/timelogs` - Time logs
- `GET /wp-json/iwms/v1/projects` - Project list
- `GET /wp-json/iwms/v1/reports/daily` - Daily reports
- `GET /wp-json/iwms/v1/reports/monthly` - Monthly reports
- `GET /wp-json/iwms/v1/payroll` - Payroll data

## 💰 Payroll Formula

```
Total Salary = Base Salary + (Billable Hours × Hourly Rate) - Unpaid Leave Deductions + Overtime Bonus
```

- Overtime: Hours > 160 per month at 1.5x rate
- Unpaid Leave: Deducted at daily base salary rate

## 🔒 Security Features

- WordPress nonces on all AJAX requests
- User capability checks
- Input sanitization and validation
- SQL prepared statements
- XSS protection

## 📈 Performance

- Indexed database columns
- Optimized queries
- Background cron jobs
- AJAX-powered interface
- No heavy meta queries

## 🎨 Customization

The system is built with modular classes:
- `IWMS_Database`: Table creation and management
- `IWMS_Roles`: User roles and capabilities
- `IWMS_Attendance`: Check-in/check-out functionality
- `IWMS_TimeTracker`: Timer and manual logging
- `IWMS_Projects`: Project management
- `IWMS_Leaves`: Leave workflow
- `IWMS_Payroll`: Salary calculations
- `IWMS_Reports`: Reporting engine
- `IWMS_API`: REST endpoints
- `IWMS_Dashboard`: UI rendering

## 🔮 Future Enhancements

- Mobile app integration
- Advanced reporting dashboards
- Integration with calendar systems
- Multi-company support
- Advanced leave policies
- Expense tracking
- Performance reviews

## 📝 License

This plugin is provided as-is for educational and internal use.

---

**Built for enterprise workforce management with scalability and security in mind.**
