<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');
// --- End Error Reporting ---

// --- Set Headers EARLY ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
// --- End Headers ---

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}
// --- End Database Connection ---

// --- Optional Filters (Example: by employee_id, status, date range) ---
$employee_id = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$status_filter = isset($_GET['status']) ? trim(htmlspecialchars($_GET['status'])) : null;
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : null; // Period Start Date >=
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : null;     // Period End Date <=
// --- End Filters ---

try {
    // Base SQL query
    $sql = "SELECT
                t.TimesheetID,
                t.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                t.ScheduleID, -- Optional: Join with Schedules if needed later
                t.PeriodStartDate,
                t.PeriodEndDate,
                t.TotalHoursWorked,
                t.OvertimeHours,
                t.Status,
                t.SubmittedDate,
                t.ApprovalDate,
                CONCAT(a.FirstName, ' ', a.LastName) AS ApproverName
            FROM
                Timesheets t
            JOIN
                Employees e ON t.EmployeeID = e.EmployeeID
            LEFT JOIN -- Join to get approver name if available
                Employees a ON t.ApprovedBy = a.EmployeeID";

    $conditions = [];
    $params = [];

    // Add conditions based on filters
    if ($employee_id !== null && $employee_id > 0) {
        $conditions[] = "t.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id;
    }
    if (!empty($status_filter)) {
        $conditions[] = "t.Status = :status";
        $params[':status'] = $status_filter;
    }
    if (!empty($start_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_filter)) {
         $conditions[] = "t.PeriodStartDate >= :start_date"; // Or t.PeriodEndDate >= :start_date depending on logic
         $params[':start_date'] = $start_date_filter;
    }
     if (!empty($end_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_filter)) {
         $conditions[] = "t.PeriodEndDate <= :end_date"; // Or t.PeriodStartDate <= :end_date
         $params[':end_date'] = $end_date_filter;
    }

    // Append WHERE clause if conditions exist
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY t.PeriodEndDate DESC, e.LastName, e.FirstName"; // Order by period end, then name

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // Pass parameters for binding

    // Fetch all results
    $timesheets = $stmt->fetchAll();

    // Format date fields (optional)
    foreach ($timesheets as &$ts) {
        if (!empty($ts['PeriodStartDate'])) {
             $ts['PeriodStartDateFormatted'] = date('M d, Y', strtotime($ts['PeriodStartDate']));
        }
        if (!empty($ts['PeriodEndDate'])) {
             $ts['PeriodEndDateFormatted'] = date('M d, Y', strtotime($ts['PeriodEndDate']));
        }
        if (!empty($ts['SubmittedDate'])) {
             $ts['SubmittedDateFormatted'] = date('M d, Y H:i', strtotime($ts['SubmittedDate']));
        }
         if (!empty($ts['ApprovalDate'])) {
             $ts['ApprovalDateFormatted'] = date('M d, Y H:i', strtotime($ts['ApprovalDate']));
        }
        // Format hours (handle NULL)
        $ts['TotalHoursWorked'] = $ts['TotalHoursWorked'] !== null ? number_format($ts['TotalHoursWorked'], 2) : '-';
        $ts['OvertimeHours'] = $ts['OvertimeHours'] !== null ? number_format($ts['OvertimeHours'], 2) : '-';

    }
    unset($ts); // Unset reference

    // Output the results as JSON
    echo json_encode($timesheets);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_timesheets): " . $e->getMessage());

    // Send an error response back to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve timesheet data.']);
}
?>
