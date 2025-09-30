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

// --- Get Timesheet ID from Query Parameter ---
$timesheet_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (empty($timesheet_id) || $timesheet_id === false || $timesheet_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Valid Timesheet ID is required as a query parameter (e.g., ?id=1).']);
    exit;
}
// --- End Get Timesheet ID ---


try {
    // --- Fetch Main Timesheet Data ---
    $sql_main = "SELECT
                    t.TimesheetID,
                    t.EmployeeID,
                    CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                    e.JobTitle AS EmployeeJobTitle,
                    t.ScheduleID,
                    t.PeriodStartDate,
                    t.PeriodEndDate,
                    t.TotalHoursWorked,
                    t.OvertimeHours,
                    t.Status,
                    t.SubmittedDate,
                    t.ApprovalDate,
                    t.ApprovedBy,
                    CONCAT(a.FirstName, ' ', a.LastName) AS ApproverName
                FROM
                    Timesheets t
                JOIN
                    Employees e ON t.EmployeeID = e.EmployeeID
                LEFT JOIN
                    Employees a ON t.ApprovedBy = a.EmployeeID
                WHERE t.TimesheetID = :timesheet_id";

    $stmt_main = $pdo->prepare($sql_main);
    $stmt_main->bindParam(':timesheet_id', $timesheet_id, PDO::PARAM_INT);
    $stmt_main->execute();
    $timesheet_details = $stmt_main->fetch();

    if (!$timesheet_details) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Timesheet not found with the specified ID.']);
        exit;
    }
    // --- End Fetch Main Timesheet Data ---


    // --- Fetch Associated Attendance Records (Optional but useful) ---
    $sql_attendance = "SELECT
                            RecordID,
                            AttendanceDate,
                            ClockInTime,
                            ClockOutTime,
                            Status,
                            Notes,
                            TIMESTAMPDIFF(MINUTE, ClockInTime, ClockOutTime) / 60.0 AS HoursWorkedCalc -- Calculate hours difference
                       FROM
                            AttendanceRecords
                       WHERE
                            EmployeeID = :employee_id
                            AND AttendanceDate BETWEEN :start_date AND :end_date
                       ORDER BY
                            AttendanceDate, ClockInTime";

    $stmt_attendance = $pdo->prepare($sql_attendance);
    $stmt_attendance->bindParam(':employee_id', $timesheet_details['EmployeeID'], PDO::PARAM_INT);
    $stmt_attendance->bindParam(':start_date', $timesheet_details['PeriodStartDate'], PDO::PARAM_STR);
    $stmt_attendance->bindParam(':end_date', $timesheet_details['PeriodEndDate'], PDO::PARAM_STR);
    $stmt_attendance->execute();
    $attendance_records = $stmt_attendance->fetchAll();

    // Add attendance records to the main details array
    $timesheet_details['attendance_entries'] = $attendance_records;
    // --- End Fetch Attendance Records ---


    // --- Format data before sending (optional) ---
    if (!empty($timesheet_details['PeriodStartDate'])) {
        $timesheet_details['PeriodStartDateFormatted'] = date('M d, Y', strtotime($timesheet_details['PeriodStartDate']));
    }
    if (!empty($timesheet_details['PeriodEndDate'])) {
        $timesheet_details['PeriodEndDateFormatted'] = date('M d, Y', strtotime($timesheet_details['PeriodEndDate']));
    }
     if (!empty($timesheet_details['SubmittedDate'])) {
        $timesheet_details['SubmittedDateFormatted'] = date('M d, Y H:i', strtotime($timesheet_details['SubmittedDate']));
    }
    if (!empty($timesheet_details['ApprovalDate'])) {
        $timesheet_details['ApprovalDateFormatted'] = date('M d, Y H:i', strtotime($timesheet_details['ApprovalDate']));
    }
     // Format hours (handle NULL)
    $timesheet_details['TotalHoursWorkedFormatted'] = $timesheet_details['TotalHoursWorked'] !== null ? number_format($timesheet_details['TotalHoursWorked'], 2) : '-';
    $timesheet_details['OvertimeHoursFormatted'] = $timesheet_details['OvertimeHours'] !== null ? number_format($timesheet_details['OvertimeHours'], 2) : '-';

    // Format attendance entries
    foreach ($timesheet_details['attendance_entries'] as &$att_rec) {
         if (!empty($att_rec['AttendanceDate'])) {
             $att_rec['AttendanceDateFormatted'] = date('M d, Y (D)', strtotime($att_rec['AttendanceDate']));
        }
        if (!empty($att_rec['ClockInTime'])) {
            $att_rec['ClockInTimeFormatted'] = date('h:i A', strtotime($att_rec['ClockInTime']));
        } else { $att_rec['ClockInTimeFormatted'] = '-'; }
        if (!empty($att_rec['ClockOutTime'])) {
            $att_rec['ClockOutTimeFormatted'] = date('h:i A', strtotime($att_rec['ClockOutTime']));
        } else { $att_rec['ClockOutTimeFormatted'] = '-'; }
        // Format calculated hours
        $att_rec['HoursWorkedCalcFormatted'] = $att_rec['HoursWorkedCalc'] !== null ? number_format($att_rec['HoursWorkedCalc'], 2) : '-';
    }
    unset($att_rec);
    // --- End Formatting ---


    // Output the combined results as JSON
    echo json_encode($timesheet_details);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_timesheet_details): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve timesheet details.']);
} catch (Throwable $e) { // Catch other potential errors
    error_log("General Error (get_timesheet_details): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>
