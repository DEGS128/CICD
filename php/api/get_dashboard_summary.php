<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production, 1 for development
ini_set('log_errors', 1);
// Ensure this path is writable by the web server:
// ini_set('error_log', __DIR__ . '/../../php-error.log'); 

session_start();

header('Content-Type: application/json');

require_once '../db_connect.php'; // Adjust path as needed

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_name'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. User not logged in.']);
    exit;
}

$role = isset($_GET['role']) ? $_GET['role'] : $_SESSION['role_name'];
$loggedInUserId = $_SESSION['user_id']; 
$loggedInEmployeeId = $_SESSION['employee_id'] ?? null; 

$summaryData = [
    'charts' => [] 
];

try {
    if ($role === 'System Admin' || $role === 'HR Admin') {
        // Total Employees
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Employees");
        $summaryData['total_employees'] = $stmt->fetchColumn();

        // Active Employees
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Employees WHERE IsActive = 1");
        $summaryData['active_employees'] = $stmt->fetchColumn();
        $inactive_employees = $summaryData['total_employees'] - $summaryData['active_employees'];
        $summaryData['charts']['employee_status_distribution'] = [
            'labels' => ['Active', 'Inactive'],
            'data' => [(int)$summaryData['active_employees'], (int)$inactive_employees]
        ];

        // Pending Leave Requests (System-wide)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM LeaveRequests WHERE Status = 'Pending'");
        $summaryData['pending_leave_requests'] = $stmt->fetchColumn();

        // Total Departments
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM organizationalstructure"); 
        $summaryData['total_departments'] = $stmt->fetchColumn();
        
        // Recent Hires (Last 30 days)
        $stmt_recent_hires = $pdo->query("SELECT COUNT(*) as count FROM Employees WHERE HireDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $summaryData['recent_hires_last_30_days'] = $stmt_recent_hires->fetchColumn();

        // Leave Requests by Type (Last 30 Days, System-wide)
        $stmt_leave_types = $pdo->query("
            SELECT lt.TypeName, COUNT(lr.RequestID) as count
            FROM LeaveRequests lr
            JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            WHERE lr.RequestDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY lt.TypeName
            ORDER BY count DESC
            LIMIT 5
        ");
        $leave_type_labels = [];
        $leave_type_data = [];
        while ($row = $stmt_leave_types->fetch(PDO::FETCH_ASSOC)) {
            $leave_type_labels[] = $row['TypeName'];
            $leave_type_data[] = (int)$row['count'];
        }
        $summaryData['charts']['leave_requests_by_type'] = [
            'labels' => $leave_type_labels,
            'data' => $leave_type_data
        ];

        // Employee Distribution by Department
        $stmt_dept_dist = $pdo->query("
            SELECT os.DepartmentName, COUNT(e.EmployeeID) as count
            FROM Employees e
            JOIN organizationalstructure os ON e.DepartmentID = os.DepartmentID
            WHERE e.IsActive = 1
            GROUP BY os.DepartmentName
            ORDER BY count DESC
        ");
        $dept_dist_labels = [];
        $dept_dist_data = [];
        while ($row = $stmt_dept_dist->fetch(PDO::FETCH_ASSOC)) {
            $dept_dist_labels[] = $row['DepartmentName'];
            $dept_dist_data[] = (int)$row['count'];
        }
        $summaryData['charts']['employee_distribution_by_department'] = [
            'labels' => $dept_dist_labels,
            'data' => $dept_dist_data
        ];


    } elseif ($role === 'Manager') {
        if (!$loggedInEmployeeId) {
            throw new Exception("Employee ID not found for manager.");
        }
        // Team Members
        $stmt_team_members = $pdo->prepare("SELECT COUNT(*) as count FROM Employees WHERE ManagerID = :manager_id");
        $stmt_team_members->bindParam(':manager_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_team_members->execute();
        $summaryData['team_members'] = $stmt_team_members->fetchColumn();

        // Pending Team Leave
        $stmt_pending_leave = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM LeaveRequests lr
            JOIN Employees e ON lr.EmployeeID = e.EmployeeID
            WHERE e.ManagerID = :manager_id AND lr.Status = 'Pending'
        ");
        $stmt_pending_leave->bindParam(':manager_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_pending_leave->execute();
        $pending_team_leave_count = $stmt_pending_leave->fetchColumn();
        $summaryData['pending_team_leave'] = $pending_team_leave_count;

        // Pending Timesheets for Team
        $stmt_pending_timesheets = $pdo->prepare("
            SELECT COUNT(t.TimesheetID) as count
            FROM Timesheets t
            JOIN Employees e ON t.EmployeeID = e.EmployeeID
            WHERE e.ManagerID = :manager_id AND t.Status = 'Pending' 
        ");
        $stmt_pending_timesheets->bindParam(':manager_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_pending_timesheets->execute();
        $pending_timesheets_count = $stmt_pending_timesheets->fetchColumn();
        $summaryData['pending_timesheets'] = $pending_timesheets_count;
        
        // Pending Claims for Team
        $stmt_pending_claims_team = $pdo->prepare("
            SELECT COUNT(c.ClaimID) as count
            FROM Claims c
            JOIN Employees e ON c.EmployeeID = e.EmployeeID
            WHERE e.ManagerID = :manager_id AND c.Status = 'Submitted' 
        ");
        $stmt_pending_claims_team->bindParam(':manager_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_pending_claims_team->execute();
        $pending_claims_team_count = $stmt_pending_claims_team->fetchColumn();

        // Open Tasks (sum of pending approvals for the manager's team)
        $summaryData['open_tasks'] = (int)$pending_team_leave_count + (int)$pending_timesheets_count + (int)$pending_claims_team_count;

    } elseif ($role === 'Employee') {
        if (!$loggedInEmployeeId) {
            throw new Exception("Employee ID not found for employee.");
        }
        $currentYear = date('Y');
        // Available Leave Days
        $stmt_leave_balance = $pdo->prepare("
            SELECT SUM(lb.AvailableDays) as total_available 
            FROM LeaveBalances lb 
            WHERE lb.EmployeeID = :employee_id AND lb.BalanceYear = :year
        ");
        $stmt_leave_balance->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_leave_balance->bindParam(':year', $currentYear, PDO::PARAM_INT);
        $stmt_leave_balance->execute();
        $available_leave = $stmt_leave_balance->fetchColumn();
        $summaryData['available_leave_days'] = $available_leave !== null ? floatval($available_leave) : 0;

        // My Pending Claims
        $stmt_pending_claims = $pdo->prepare("SELECT COUNT(*) as count FROM Claims WHERE EmployeeID = :employee_id AND Status = 'Submitted'");
        $stmt_pending_claims->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_pending_claims->execute();
        $summaryData['pending_claims'] = $stmt_pending_claims->fetchColumn();

        // Upcoming Payslip Date
        $stmt_payslip_date = $pdo->prepare("
            SELECT MIN(PaymentDate) as next_payment_date 
            FROM PayrollRuns 
            WHERE PaymentDate >= CURDATE() AND Status NOT IN ('Completed', 'Failed')
        ");
        $stmt_payslip_date->execute();
        $next_payslip_date_row = $stmt_payslip_date->fetch(PDO::FETCH_ASSOC);
        if ($next_payslip_date_row && $next_payslip_date_row['next_payment_date']) {
            $summaryData['upcoming_payslip_date'] = date("M d, Y", strtotime($next_payslip_date_row['next_payment_date']));
        } else {
            $summaryData['upcoming_payslip_date'] = 'N/A';
        }

        // My Documents Count
        $stmt_docs_count = $pdo->prepare("SELECT COUNT(*) as count FROM employeedocuments WHERE EmployeeID = :employee_id");
        $stmt_docs_count->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_docs_count->execute();
        $summaryData['my_documents_count'] = $stmt_docs_count->fetchColumn();

        // Chart: My Leave Summary (Available, Used, Pending for current year)
        $stmt_used_leave = $pdo->prepare("
            SELECT SUM(lr.NumberOfDays) as total_used
            FROM LeaveRequests lr
            WHERE lr.EmployeeID = :employee_id AND lr.Status = 'Approved' AND YEAR(lr.StartDate) = :year
        ");
        $stmt_used_leave->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_used_leave->bindParam(':year', $currentYear, PDO::PARAM_INT);
        $stmt_used_leave->execute();
        $used_days = $stmt_used_leave->fetchColumn() ?: 0;

        $stmt_pending_leave_emp = $pdo->prepare("
            SELECT SUM(lr.NumberOfDays) as total_pending
            FROM LeaveRequests lr
            WHERE lr.EmployeeID = :employee_id AND lr.Status = 'Pending' AND YEAR(lr.StartDate) = :year
        ");
        $stmt_pending_leave_emp->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $stmt_pending_leave_emp->bindParam(':year', $currentYear, PDO::PARAM_INT);
        $stmt_pending_leave_emp->execute();
        $pending_days = $stmt_pending_leave_emp->fetchColumn() ?: 0;
        
        $summaryData['charts']['my_leave_summary'] = [
            'labels' => ['Available', 'Used This Year', 'Pending This Year'],
            'data' => [
                floatval($summaryData['available_leave_days']), 
                floatval($used_days), 
                floatval($pending_days)
            ]
        ];

    } else {
        $summaryData['message'] = "No specific dashboard summary for role: " . htmlspecialchars($role);
    }

    echo json_encode($summaryData);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in get_dashboard_summary.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error. ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error in get_dashboard_summary.php: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
}
?>
