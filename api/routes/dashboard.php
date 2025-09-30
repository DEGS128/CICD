<?php
/**
 * Dashboard Routes
 * Handles dashboard data and analytics
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class DashboardController {
    private $pdo;
    private $authMiddleware;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Handle dashboard requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                $this->getDashboardData();
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get dashboard summary data
     */
    private function getDashboardData() {
        try {
            $currentUser = $this->authMiddleware->getCurrentUser();
            $role = $currentUser['role_name'];

            $dashboardData = [];

            // Get basic counts
            $dashboardData['counts'] = $this->getBasicCounts();

            // Get role-specific data
            if (in_array($role, ['System Admin', 'HR Manager'])) {
                $dashboardData['recent_activities'] = $this->getRecentActivities();
                $dashboardData['department_stats'] = $this->getDepartmentStats();
                $dashboardData['payroll_summary'] = $this->getPayrollSummary();
            }

            // Get employee-specific data
            if ($role === 'Employee') {
                $dashboardData['my_attendance'] = $this->getMyAttendance($currentUser['employee_id']);
                $dashboardData['my_leave_balance'] = $this->getMyLeaveBalance($currentUser['employee_id']);
                $dashboardData['my_upcoming_events'] = $this->getMyUpcomingEvents($currentUser['employee_id']);
            }

            Response::success($dashboardData);

        } catch (Exception $e) {
            error_log("Dashboard error: " . $e->getMessage());
            Response::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get basic counts
     */
    private function getBasicCounts() {
        $counts = [];

        // Total employees
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM Employees WHERE IsActive = 1");
        $counts['total_employees'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total departments
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM OrganizationalStructure WHERE IsActive = 1");
        $counts['total_departments'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Active users
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM Users WHERE IsActive = 1");
        $counts['active_users'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pending leave requests
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM LeaveRequests WHERE Status = 'Pending'");
        $counts['pending_leave_requests'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $counts;
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities() {
        $activities = [];

        // Recent employee additions
        $stmt = $this->pdo->query("
            SELECT 'employee_added' as type, FirstName, LastName, HireDate as date
            FROM Employees 
            WHERE IsActive = 1 AND HireDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY HireDate DESC 
            LIMIT 5
        ");
        $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Recent leave requests
        $stmt = $this->pdo->query("
            SELECT 'leave_request' as type, lr.StartDate as date, e.FirstName, e.LastName
            FROM LeaveRequests lr
            JOIN Employees e ON lr.EmployeeID = e.EmployeeID
            WHERE lr.CreatedDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY lr.CreatedDate DESC 
            LIMIT 5
        ");
        $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Get department statistics
     */
    private function getDepartmentStats() {
        $stmt = $this->pdo->query("
            SELECT 
                d.DepartmentName,
                COUNT(e.EmployeeID) as employee_count,
                AVG(s.BaseSalary) as avg_salary
            FROM OrganizationalStructure d
            LEFT JOIN Employees e ON d.DepartmentID = e.DepartmentID AND e.IsActive = 1
            LEFT JOIN Salaries s ON e.EmployeeID = s.EmployeeID AND s.Status = 'Active'
            WHERE d.IsActive = 1
            GROUP BY d.DepartmentID, d.DepartmentName
            ORDER BY employee_count DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payroll summary
     */
    private function getPayrollSummary() {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_payroll_runs,
                SUM(TotalGrossPay) as total_gross_pay,
                SUM(TotalDeductions) as total_deductions,
                SUM(TotalNetPay) as total_net_pay
            FROM PayrollRuns 
            WHERE Status = 'Completed'
            AND PayPeriodEnd >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get my attendance
     */
    private function getMyAttendance($employeeId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                AttendanceDate,
                ClockInTime,
                ClockOutTime,
                Status
            FROM AttendanceRecords 
            WHERE EmployeeID = :employee_id 
            AND AttendanceDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY AttendanceDate DESC
            LIMIT 10
        ");
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get my leave balance
     */
    private function getMyLeaveBalance($employeeId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                lt.LeaveTypeName,
                lb.AvailableDays,
                lb.UsedDays,
                lb.TotalDays
            FROM LeaveBalances lb
            JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
            WHERE lb.EmployeeID = :employee_id
            AND lb.Year = YEAR(NOW())
        ");
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get my upcoming events
     */
    private function getMyUpcomingEvents($employeeId) {
        $events = [];

        // Upcoming leave requests
        $stmt = $this->pdo->prepare("
            SELECT 
                'leave' as type,
                StartDate as event_date,
                lt.LeaveTypeName as title,
                Status
            FROM LeaveRequests lr
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lr.EmployeeID = :employee_id
            AND lr.StartDate >= CURDATE()
            ORDER BY lr.StartDate
            LIMIT 5
        ");
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return $events;
    }
}

