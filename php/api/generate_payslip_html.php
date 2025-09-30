<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user in production
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log file

session_start(); // Required for authentication

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; // Adjust path as necessary
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in generate_payslip_html.php (db_connect include): " . $e->getMessage());
    // Output a user-friendly error page if DB connection fails
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Server Configuration Error</h1><p>Could not connect to the database. Please contact support.</p></body></html>";
    exit;
}

// --- Get Payslip ID from Query Parameter ---
$payslip_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (empty($payslip_id) || $payslip_id === false || $payslip_id <= 0) {
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Invalid Request</h1><p>A valid Payslip ID is required.</p></body></html>";
    exit;
}

// --- Authentication & Authorization Check (Essential) ---
$is_authorized = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['employee_id'])) {
    // System Admins and HR Admins can view any payslip
    if (in_array((int)$_SESSION['role_id'], [1, 2])) {
        $is_authorized = true;
    } else {
        // Employees can only view their own payslips
        try {
            $checkOwnerSql = "SELECT EmployeeID FROM Payslips WHERE PayslipID = :payslip_id";
            $checkOwnerStmt = $pdo->prepare($checkOwnerSql);
            $checkOwnerStmt->bindParam(':payslip_id', $payslip_id, PDO::PARAM_INT);
            $checkOwnerStmt->execute();
            $payslipOwnerEmployeeId = $checkOwnerStmt->fetchColumn();

            if ($payslipOwnerEmployeeId && $payslipOwnerEmployeeId == $_SESSION['employee_id']) {
                $is_authorized = true;
            }
        } catch (\PDOException $e) {
             error_log("Auth check failed for payslip PDF generation: " . $e->getMessage());
        }
    }
}

if (!$is_authorized) {
    echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>Access Denied</h1><p>You do not have permission to view this payslip.</p><p><a href='../../index.php'>Go to Login</a></p></body></html>";
    exit;
}
// --- End Auth Check ---


// --- Fetch Payslip Details ---
$payslip_details = null;
try {
    $sql = "SELECT
                p.*, -- Select all columns from Payslips
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                e.JobTitle AS EmployeeJobTitle,
                e.EmployeeID AS EmployeeNumber, -- Assuming EmployeeID can serve as EmployeeNumber
                d.DepartmentName
            FROM
                Payslips p
            JOIN
                Employees e ON p.EmployeeID = e.EmployeeID
            LEFT JOIN
                OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
            WHERE
                p.PayslipID = :payslip_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':payslip_id', $payslip_id, PDO::PARAM_INT);
    $stmt->execute();
    $payslip_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payslip_details) {
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Payslip Not Found</h1><p>The requested payslip could not be found.</p></body></html>";
        exit;
    }

    // Helper function for formatting monetary values
    function format_money($amount) {
        return isset($amount) && is_numeric($amount) ? number_format((float)$amount, 2) : '0.00';
    }
    // Helper function for formatting dates
    function format_date_payslip($date_string) {
        return !empty($date_string) ? date('M d, Y', strtotime($date_string)) : 'N/A';
    }


} catch (\PDOException $e) {
    error_log("PHP PDOException in generate_payslip_html.php: " . $e->getMessage());
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Database Error</h1><p>Could not retrieve payslip details.</p></body></html>";
    exit;
} catch (Throwable $e) {
    error_log("PHP Throwable in generate_payslip_html.php: " . $e->getMessage());
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Unexpected Server Error</h1><p>An error occurred while generating the payslip.</p></body></html>";
    exit;
}

// --- Start HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($payslip_details['EmployeeName'] ?? 'Employee'); ?> - <?php echo htmlspecialchars(format_date_payslip($payslip_details['PayPeriodStartDate']) . ' to ' . format_date_payslip($payslip_details['PayPeriodEndDate'])); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 0;
            background-color: #fff; /* White background for printing */
            color: #333;
        }
        .payslip-container {
            width: 100%;
            max-width: 800px; /* Standard A4-ish width */
            margin: 20px auto;
            padding: 25px;
            border: 1px solid #ddd;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .payslip-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #4A5568; /* gray-700 */
            padding-bottom: 15px;
        }
        .payslip-header h1 {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            color: #2D3748; /* gray-800 */
            margin: 0 0 5px 0;
        }
        .payslip-header p {
            font-size: 14px;
            color: #4A5568; /* gray-700 */
            margin: 0;
        }
        .company-logo {
            max-height: 60px; /* Adjust as needed */
            margin-bottom: 10px;
        }
        .employee-details, .payroll-details {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc;
        }
        .employee-details h2, .payroll-details h2, .earnings-deductions h2, .summary h2 {
            font-family: 'Cinzel', serif;
            font-size: 18px;
            color: #4E3B2A; /* Theme color */
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .grid-cols-payslip {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr)); /* Two columns */
            gap: 1rem;
        }
        .payslip-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }
        .payslip-item strong {
            color: #4A5568; /* gray-700 */
            margin-right: 8px;
            min-width: 150px; /* Align labels */
            display: inline-block;
        }
        .payslip-item span {
            text-align: right;
            color: #2D3748; /* gray-800 */
        }
        .earnings-deductions {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns */
            gap: 2rem;
            margin-bottom: 20px;
        }
        .section-title {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 600;
            color: #4E3B2A;
            margin-bottom: 8px;
        }
        .summary .payslip-item strong {
            font-size: 15px;
        }
        .summary .payslip-item span {
            font-size: 15px;
            font-weight: bold;
        }
        .net-pay strong, .net-pay span {
            font-size: 18px !important;
            color: #2C5282 !important; /* blue-700 */
        }
        .payslip-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #718096; /* gray-600 */
        }
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                -webkit-print-color-adjust: exact; /* Chrome, Safari */
                color-adjust: exact; /* Firefox */
            }
            .payslip-container {
                margin: 0;
                border: none;
                box-shadow: none;
                width: 100%;
                max-width: 100%;
                padding: 5mm; /* Minimal padding for print */
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="payslip-header">
            <h1>Avalon HR Management System</h1>
            <p>Payslip</p>
        </div>

        <div class="employee-details">
            <h2>Employee Information</h2>
            <div class="grid-cols-payslip">
                <div class="payslip-item"><strong>Employee Name:</strong> <span><?php echo htmlspecialchars($payslip_details['EmployeeName'] ?? 'N/A'); ?></span></div>
                <div class="payslip-item"><strong>Employee ID:</strong> <span><?php echo htmlspecialchars($payslip_details['EmployeeNumber'] ?? 'N/A'); ?></span></div>
                <div class="payslip-item"><strong>Job Title:</strong> <span><?php echo htmlspecialchars($payslip_details['EmployeeJobTitle'] ?? 'N/A'); ?></span></div>
                <div class="payslip-item"><strong>Department:</strong> <span><?php echo htmlspecialchars($payslip_details['DepartmentName'] ?? 'N/A'); ?></span></div>
            </div>
        </div>

        <div class="payroll-details">
            <h2>Payroll Information</h2>
            <div class="grid-cols-payslip">
                <div class="payslip-item"><strong>Pay Period:</strong> <span><?php echo htmlspecialchars(format_date_payslip($payslip_details['PayPeriodStartDate']) . ' - ' . format_date_payslip($payslip_details['PayPeriodEndDate'])); ?></span></div>
                <div class="payslip-item"><strong>Payment Date:</strong> <span><?php echo htmlspecialchars(format_date_payslip($payslip_details['PaymentDate'])); ?></span></div>
                <div class="payslip-item"><strong>Payroll Run ID:</strong> <span><?php echo htmlspecialchars($payslip_details['PayrollID'] ?? 'N/A'); ?></span></div>
                <div class="payslip-item"><strong>Payslip ID:</strong> <span><?php echo htmlspecialchars($payslip_details['PayslipID'] ?? 'N/A'); ?></span></div>
            </div>
        </div>

        <div class="earnings-deductions">
            <div>
                <h2 class="section-title">Earnings</h2>
                <div class="payslip-item"><strong>Basic Salary Component:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['BasicSalary'])); ?></span></div>
                <?php if (isset($payslip_details['HourlyRate']) && $payslip_details['HourlyRate'] > 0): ?>
                <div class="payslip-item"><strong>Hourly Rate:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['HourlyRate'])); ?></span></div>
                <div class="payslip-item"><strong>Hours Worked:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['HoursWorked'])); ?></span></div>
                <?php endif; ?>
                <div class="payslip-item"><strong>Regular Pay:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['RegularPay'])); ?></span></div>
                <div class="payslip-item"><strong>Overtime Pay:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['OvertimePay'])); ?></span></div>
                <div class="payslip-item"><strong>Holiday Pay:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['HolidayPay'])); ?></span></div>
                <div class="payslip-item"><strong>Night Differential:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['NightDifferentialPay'])); ?></span></div>
                <div class="payslip-item"><strong>Bonuses:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['BonusesTotal'])); ?></span></div>
                <div class="payslip-item"><strong>Other Earnings:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['OtherEarnings'])); ?></span></div>
                <hr class="my-2">
                <div class="payslip-item"><strong>GROSS INCOME:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['GrossIncome'])); ?></span></div>
            </div>
            <div>
                <h2 class="section-title">Deductions</h2>
                <div class="payslip-item"><strong>SSS Contribution:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['SSS_Contribution'])); ?></span></div>
                <div class="payslip-item"><strong>PhilHealth Contribution:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['PhilHealth_Contribution'])); ?></span></div>
                <div class="payslip-item"><strong>Pag-IBIG Contribution:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['PagIBIG_Contribution'])); ?></span></div>
                <div class="payslip-item"><strong>Withholding Tax:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['WithholdingTax'])); ?></span></div>
                <div class="payslip-item"><strong>Other Deductions:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['OtherDeductionsTotal'])); ?></span></div>
                <hr class="my-2">
                <div class="payslip-item"><strong>TOTAL DEDUCTIONS:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['TotalDeductions'])); ?></span></div>
            </div>
        </div>

        <div class="summary mt-6 pt-4 border-t-2 border-gray-400">
            <div class="payslip-item net-pay"><strong>NET PAY:</strong> <span><?php echo htmlspecialchars(format_money($payslip_details['NetIncome'])); ?></span></div>
        </div>

        <div class="payslip-footer">
            <p>This is a system-generated payslip. If you have any questions, please contact HR.</p>
            <p>Generated on: <?php echo date('M d, Y H:i:s'); ?></p>
        </div>
    </div>
    <div class="text-center my-4 no-print">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Print Payslip
        </button>
    </div>
</body>
</html>
