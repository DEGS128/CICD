<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_payslip_details.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Get Payslip ID from Query Parameter ---
$payslip_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (empty($payslip_id) || $payslip_id === false || $payslip_id <= 0) {
    if (!headers_sent()) { http_response_code(400); }
    echo json_encode(['error' => 'Valid Payslip ID is required as a query parameter (e.g., ?id=1).']);
    exit;
}
// --- End Get Payslip ID ---

// --- Authorization Check (Placeholder/Example) ---
// In a real system, you'd check if the logged-in user is allowed to see this payslip
// (e.g., is it their own payslip, or are they an HR Admin?)
session_start();
$is_authorized = false;
if (isset($_SESSION['user_id'])) {
    // Example: Allow if HR Admin/SysAdmin OR if it's the employee's own payslip
    if (in_array($_SESSION['role_id'], [1, 2])) { // Assuming 1=SysAdmin, 2=HR Admin
        $is_authorized = true;
    } else {
        // Check if the payslip belongs to the logged-in employee
        try {
            $checkOwnerSql = "SELECT EmployeeID FROM Payslips WHERE PayslipID = :payslip_id";
            $checkOwnerStmt = $pdo->prepare($checkOwnerSql);
            $checkOwnerStmt->bindParam(':payslip_id', $payslip_id, PDO::PARAM_INT);
            $checkOwnerStmt->execute();
            $payslipOwner = $checkOwnerStmt->fetchColumn();
            if ($payslipOwner && isset($_SESSION['employee_id']) && $payslipOwner == $_SESSION['employee_id']) {
                $is_authorized = true;
            }
        } catch (\PDOException $e) {
             error_log("Auth check failed for payslip details: " . $e->getMessage());
             // Default to unauthorized if check fails
        }
    }
}

if (!$is_authorized) {
    if (!headers_sent()) { http_response_code(403); } // Forbidden
    echo json_encode(['error' => 'You do not have permission to view this payslip.']);
    exit;
}
// --- End Authorization Check ---


// --- Fetch Logic ---
$sql = '';
try {
    // Select all payslip details and join with Employees for name/job title
    $sql = "SELECT
                p.*, -- Select all columns from Payslips
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                e.JobTitle AS EmployeeJobTitle
            FROM
                Payslips p
            JOIN
                Employees e ON p.EmployeeID = e.EmployeeID
            WHERE
                p.PayslipID = :payslip_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':payslip_id', $payslip_id, PDO::PARAM_INT);
    $stmt->execute();
    $payslip_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payslip_details) {
        if (!headers_sent()) { http_response_code(404); }
        echo json_encode(['error' => 'Payslip not found with the specified ID.']);
        exit;
    }

    // --- Format data for display ---
    $formatted_details = $payslip_details; // Start with all fetched data

    // Format Dates
    $date_fields = ['PayPeriodStartDate', 'PayPeriodEndDate', 'PaymentDate', 'CreatedAt'];
    foreach ($date_fields as $field) {
        if (!empty($formatted_details[$field])) {
            // Use 'M d, Y' for dates, 'M d, Y H:i:s' for timestamps if needed
            $format = ($field === 'CreatedAt') ? 'M d, Y H:i:s' : 'M d, Y';
            $formatted_details[$field . 'Formatted'] = date($format, strtotime($formatted_details[$field]));
        } else {
             $formatted_details[$field . 'Formatted'] = 'N/A';
        }
    }

    // Format Monetary Values (using number_format for 2 decimal places)
    $money_fields = [
        'BasicSalary', 'HourlyRate', 'HoursWorked', 'OvertimeHours', 'RegularPay',
        'OvertimePay', 'HolidayPay', 'NightDifferentialPay', 'BonusesTotal', 'OtherEarnings',
        'GrossIncome', 'SSS_Contribution', 'PhilHealth_Contribution', 'PagIBIG_Contribution',
        'WithholdingTax', 'OtherDeductionsTotal', 'TotalDeductions', 'NetIncome'
    ];
    foreach ($money_fields as $field) {
        if (isset($formatted_details[$field]) && is_numeric($formatted_details[$field])) {
            $formatted_details[$field . 'Formatted'] = number_format((float)$formatted_details[$field], 2);
        } else {
            // Handle null or non-numeric - display as 0.00 or '-'?
             $formatted_details[$field . 'Formatted'] = '0.00'; // Or '-'
        }
    }
     // Special case for hours - maybe don't format as currency
     $formatted_details['HoursWorkedFormatted'] = isset($formatted_details['HoursWorked']) ? number_format((float)$formatted_details['HoursWorked'], 2) : '0.00';
     $formatted_details['OvertimeHoursFormatted'] = isset($formatted_details['OvertimeHours']) ? number_format((float)$formatted_details['OvertimeHours'], 2) : '0.00';


    // --- Final JSON Output ---
    if (headers_sent()) { exit; }
    http_response_code(200);
    echo json_encode($formatted_details); // Output the detailed, formatted data

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_payslip_details.php: " . $e->getMessage() . " | SQL: " . $sql);
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Database error retrieving payslip details.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_payslip_details.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving payslip details.']);
}
exit;
?>
