<?php
/**
 * Script: Post HR Payroll Data to Financial Systems (More Concrete Version)
 *
 * Takes aggregated payroll data from hr_integrated_db and creates corresponding
 * entries in fin_general_ledger, fin_accounts_payable, and initiates
 * disbursement requests in fin_disbursement.
 *
 * !! WARNING !!
 * THIS SCRIPT REQUIRES CAREFUL CONFIGURATION OF DATABASE CONNECTIONS,
 * ACCOUNT IDS, AND BUDGET ALLOCATION LOGIC TO MATCH YOUR SPECIFIC SETUP.
 * THOROUGH TESTING IN A DEVELOPMENT ENVIRONMENT IS ESSENTIAL.
 * PRODUCTION USE REQUIRES FURTHER HARDENING (SECURITY, ERROR HANDLING, ETC.).
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // SET TO 0 FOR PRODUCTION
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/financial_integration_errors.log'); // Ensure 'logs' directory exists and is writable

header('Content-Type: application/json');

// --- Configuration: Database Connection Details ---
// !! IMPORTANT !! REPLACE WITH YOUR ACTUAL DATABASE CREDENTIALS AND HOSTS
$db_configs = [
    'hr' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=hr_integrated_db;charset=utf8mb4',
        'username' => '3206_CENTRALIZED_DATABASE', // REPLACE
        'password' => '4562526'       // REPLACE
    ],
    'gl' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=fin_general_ledger;charset=utf8mb4',
        'username' => '3206_CENTRALIZED_DATABASE', // REPLACE
        'password' => '4562526'       // REPLACE
    ],
    'ap' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=fin_accounts_payable;charset=utf8mb4',
        'username' => '3206_CENTRALIZED_DATABASE', // REPLACE
        'password' => '4562526'       // REPLACE
    ],
    'disbursement' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=fin_disbursement;charset=utf8mb4',
        'username' => '3206_CENTRALIZED_DATABASE', // REPLACE
        'password' => '4562526'       // REPLACE
    ],
    'budget' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=fin_budget_management;charset=utf8mb4',
        'username' => '3206_CENTRALIZED_DATABASE', // REPLACE
        'password' => '4562526'       // REPLACE
    ]
];

// --- Database Connection Function ---
function getDbConnection(string $db_key, array $configs): PDO {
    if (!isset($configs[$db_key])) {
        throw new Exception("Database configuration for '{$db_key}' not found.");
    }
    $config = $configs[$db_key];
    try {
        $pdo = new PDO($config['dsn'], $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false // Recommended for security
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB Connection Error ({$db_key}): " . $e->getMessage());
        throw new Exception("Database connection failed for '{$db_key}'. Please check server logs.");
    }
}

// --- Configuration: Chart of Account IDs (from fin_general_ledger.accounts) ---
// !! IMPORTANT !! VERIFY AND UPDATE THESE WITH YOUR ACTUAL ACCOUNT IDs
define('ACC_CASH_IN_BANK', 1); // Example: Assuming AccountID 1 in your fin_general_ledger for 'Cash in Bank'
define('ACC_SALARIES_PAYABLE', 201); // Example
define('ACC_SSS_PAYABLE', 202);
define('ACC_PHILHEALTH_PAYABLE', 203);
define('ACC_PAGIBIG_PAYABLE', 204);
define('ACC_WHT_PAYABLE', 205);
// define('ACC_OTHER_DEDUCTIONS_PAYABLE', 206); // If you have a general one

define('ACC_SALARIES_EXPENSE', 501); // Example: Ensure this account has AccountType 'Expense'
define('ACC_BONUSES_EXPENSE', 502);
define('ACC_REIMBURSEMENTS_EXPENSE', 503);
define('ACC_SSS_EMPLOYER_EXPENSE', 504);
define('ACC_PHILHEALTH_EMPLOYER_EXPENSE', 505);
define('ACC_PAGIBIG_EMPLOYER_EXPENSE', 506);

// --- Configuration: Other ---
define('FINANCE_USER_ID', 1); // UserID from fin_disbursement.employees making disbursement requests. Ensure this user exists.
define('DEFAULT_BUDGET_ALLOCATION_ID', 1); // Placeholder: Replace with logic to get actual AllocationID

// --- Input Processing ---
$input_data = json_decode(file_get_contents('php://input'), true);
$payroll_id_to_process = null;
if (isset($input_data['payroll_id'])) {
    $payroll_id_to_process = filter_var($input_data['payroll_id'], FILTER_VALIDATE_INT);
}

if (empty($payroll_id_to_process) || $payroll_id_to_process <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid PayrollID is required.']);
    exit;
}

// --- Result Variables ---
$gl_transaction_id = null;
$disbursement_request_id_net_payroll = null;
$ap_invoice_ids = [];
$log_messages = [];

try {
    $pdo_hr = getDbConnection('hr', $db_configs);
    $pdo_gl = getDbConnection('gl', $db_configs);
    $pdo_ap = getDbConnection('ap', $db_configs);
    $pdo_disbursement = getDbConnection('disbursement', $db_configs);
    // $pdo_budget = getDbConnection('budget', $db_configs); // Uncomment if direct budget queries are needed

    $log_messages[] = "Successfully connected to all databases.";

    // --- 1. Fetch and Verify Payroll Run from HR ---
    $stmt_hr_run = $pdo_hr->prepare("SELECT PayrollID, PayPeriodStartDate, PayPeriodEndDate, PaymentDate, Status, FinancialPostingStatus FROM PayrollRuns WHERE PayrollID = :payroll_id");
    $stmt_hr_run->bindParam(':payroll_id', $payroll_id_to_process, PDO::PARAM_INT);
    $stmt_hr_run->execute();
    $payroll_run_details = $stmt_hr_run->fetch();

    if (!$payroll_run_details) {
        throw new Exception("Payroll Run ID {$payroll_id_to_process} not found in HR system.");
    }
    if ($payroll_run_details['Status'] !== 'Completed') {
        throw new Exception("HR Payroll Run ID {$payroll_id_to_process} is not 'Completed'. Current status: " . $payroll_run_details['Status']);
    }
    if (isset($payroll_run_details['FinancialPostingStatus']) && $payroll_run_details['FinancialPostingStatus'] === 'Posted') {
        throw new Exception("HR Payroll Run ID {$payroll_id_to_process} has already been marked as 'PostedToFinance'.");
    }
    $log_messages[] = "HR Payroll Run ID {$payroll_id_to_process} verified (Status: Completed).";


    // --- 2. Aggregate Payslip Data from HR ---
    $sql_payslip_agg = "SELECT
                            COALESCE(SUM(RegularPay), 0) as total_regular_pay,
                            COALESCE(SUM(OvertimePay), 0) as total_overtime_pay,
                            COALESCE(SUM(HolidayPay), 0) as total_holiday_pay,
                            COALESCE(SUM(NightDifferentialPay), 0) as total_night_diff_pay,
                            COALESCE(SUM(BonusesTotal), 0) as total_bonuses,
                            COALESCE(SUM(OtherEarnings), 0) as total_reimbursements,
                            COALESCE(SUM(GrossIncome), 0) as total_gross_income,
                            COALESCE(SUM(SSS_Contribution), 0) as total_sss_employee,
                            COALESCE(SUM(PhilHealth_Contribution), 0) as total_philhealth_employee,
                            COALESCE(SUM(PagIBIG_Contribution), 0) as total_pagibig_employee,
                            COALESCE(SUM(WithholdingTax), 0) as total_withholding_tax,
                            COALESCE(SUM(OtherDeductionsTotal), 0) as total_other_deductions,
                            COALESCE(SUM(NetIncome), 0) as total_net_income,
                            COUNT(DISTINCT EmployeeID) as employee_count
                        FROM Payslips
                        WHERE PayrollID = :payroll_id";
    $stmt_payslip_agg = $pdo_hr->prepare($sql_payslip_agg);
    $stmt_payslip_agg->bindParam(':payroll_id', $payroll_id_to_process, PDO::PARAM_INT);
    $stmt_payslip_agg->execute();
    $aggregated_payroll = $stmt_payslip_agg->fetch();

    if (!$aggregated_payroll || $aggregated_payroll['employee_count'] == 0) {
        throw new Exception("No payslip data found or no employees processed for Payroll Run ID {$payroll_id_to_process}.");
    }
    $log_messages[] = "Aggregated payslip data fetched for {$aggregated_payroll['employee_count']} employees.";

    // --- Calculate Employer Contributions (Example Logic) ---
    // !! IMPORTANT !! Replace with your country/company specific calculation logic.
    $total_sss_employer = round($aggregated_payroll['total_gross_income'] * 0.089, 2); // Example
    $total_philhealth_employer = round($aggregated_payroll['total_gross_income'] * 0.02, 2); // Example
    $total_pagibig_employer = round($aggregated_payroll['employee_count'] * 100.00, 2); // Example
    $log_messages[] = "Calculated Employer Contributions: SSS Emp'r={$total_sss_employer}, PhilHealth Emp'r={$total_philhealth_employer}, PagIBIG Emp'r={$total_pagibig_employer}.";


    // --- 3. General Ledger Posting (fin_general_ledger) ---
    $pdo_gl->beginTransaction();
    try {
        // TODO: Implement logic to fetch the correct AllocationID, BudgetName, Department from fin_budget_management
        // For now, using placeholders. This is a CRITICAL part to get right for budget tracking.
        $gl_allocation_id = DEFAULT_BUDGET_ALLOCATION_ID; // Placeholder
        $gl_budget_name = 'Payroll Run ' . $payroll_id_to_process; // Placeholder
        $gl_allocated_department = 'Company Wide Payroll'; // Placeholder

        $stmt_gl_trans = $pdo_gl->prepare(
            "INSERT INTO transactions (TransactionFrom, TransactionDate, AllocationID, BudgetName, Allocated_Department, TotalAmount, EntryID, PaymentID, AdjustmentID, PayablePaymentID) 
             VALUES (:from, :date, :alloc_id, :budget_name, :dept, :total_amount, 0, 0, 0, 0)" // Assuming 0 or NULL for non-applicable IDs
        );
        $total_payroll_cost = $aggregated_payroll['total_gross_income'] + $total_sss_employer + $total_philhealth_employer + $total_pagibig_employer;
        $stmt_gl_trans->execute([
            ':from' => 'Payroll', // Ensure 'Payroll' is an enum value
            ':date' => $payroll_run_details['PaymentDate'],
            ':alloc_id' => $gl_allocation_id,
            ':budget_name' => $gl_budget_name,
            ':dept' => $gl_allocated_department,
            ':total_amount' => round($total_payroll_cost, 2)
        ]);
        $gl_transaction_id = $pdo_gl->lastInsertId();
        $log_messages[] = "Created GL Master Transaction ID: {$gl_transaction_id}.";

        $journal_entries = [
            ['AccountID' => ACC_SALARIES_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $aggregated_payroll['total_regular_pay'] + $aggregated_payroll['total_overtime_pay'] + $aggregated_payroll['total_holiday_pay'] + $aggregated_payroll['total_night_diff_pay'], 'Description' => 'Salaries & Wages Expense'],
            ['AccountID' => ACC_BONUSES_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $aggregated_payroll['total_bonuses'], 'Description' => 'Bonuses Expense'],
            ['AccountID' => ACC_REIMBURSEMENTS_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $aggregated_payroll['total_reimbursements'], 'Description' => 'Employee Reimbursements Expense'],
            ['AccountID' => ACC_SSS_EMPLOYER_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $total_sss_employer, 'Description' => 'SSS Employer Contribution Expense'],
            ['AccountID' => ACC_PHILHEALTH_EMPLOYER_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $total_philhealth_employer, 'Description' => 'PhilHealth Employer Contribution Expense'],
            ['AccountID' => ACC_PAGIBIG_EMPLOYER_EXPENSE, 'EntryType' => 'Debit',  'Amount' => $total_pagibig_employer, 'Description' => 'Pag-IBIG Employer Contribution Expense'],
            ['AccountID' => ACC_SSS_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_sss_employee'] + $total_sss_employer, 'Description' => 'SSS Payable'],
            ['AccountID' => ACC_PHILHEALTH_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_philhealth_employee'] + $total_philhealth_employer, 'Description' => 'PhilHealth Payable'],
            ['AccountID' => ACC_PAGIBIG_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_pagibig_employee'] + $total_pagibig_employer, 'Description' => 'Pag-IBIG Payable'],
            ['AccountID' => ACC_WHT_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_withholding_tax'], 'Description' => 'Withholding Tax Payable'],
            // ['AccountID' => ACC_OTHER_DEDUCTIONS_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_other_deductions'], 'Description' => 'Other Deductions Payable'],
            ['AccountID' => ACC_SALARIES_PAYABLE, 'EntryType' => 'Credit', 'Amount' => $aggregated_payroll['total_net_income'], 'Description' => 'Salaries Payable (Net Payroll)']
        ];

        $stmt_journal_entry = $pdo_gl->prepare(
            "INSERT INTO journalentries (AccountID, TransactionID, EntryType, Amount, EntryDate, Description)
             VALUES (:acc_id, :trans_id, :type, :amount, :date, :desc)"
        );
        foreach ($journal_entries as $entry) {
            if (abs($entry['Amount']) > 0.001) { // Check for non-negligible amounts
                $stmt_journal_entry->execute([
                    ':acc_id' => $entry['AccountID'],
                    ':trans_id' => $gl_transaction_id,
                    ':type' => $entry['EntryType'],
                    ':amount' => round($entry['Amount'], 2),
                    ':date' => $payroll_run_details['PaymentDate'],
                    ':desc' => $entry['Description'] . " - HR Payroll ID " . $payroll_id_to_process
                ]);
            }
        }
        $pdo_gl->commit();
        $log_messages[] = "GL Journal Entries posted successfully for GL Transaction ID: {$gl_transaction_id}.";
    } catch (Exception $e) {
        if ($pdo_gl->inTransaction()) $pdo_gl->rollBack();
        throw new Exception("GL Posting Failed for HR Payroll ID {$payroll_id_to_process}: " . $e->getMessage());
    }

    // --- 4. Accounts Payable Creation (fin_accounts_payable) ---
    $pdo_ap->beginTransaction();
    try {
        $payables_to_create = [
            ['PayableName' => 'SSS Contributions', 'Amount' => $aggregated_payroll['total_sss_employee'] + $total_sss_employer, 'GLAccountID' => ACC_SSS_PAYABLE, 'VendorType' => 'Statutory Agency SSS'],
            ['PayableName' => 'PhilHealth Contributions', 'Amount' => $aggregated_payroll['total_philhealth_employee'] + $total_philhealth_employer, 'GLAccountID' => ACC_PHILHEALTH_PAYABLE, 'VendorType' => 'Statutory Agency PhilHealth'],
            ['PayableName' => 'Pag-IBIG Contributions', 'Amount' => $aggregated_payroll['total_pagibig_employee'] + $total_pagibig_employer, 'GLAccountID' => ACC_PAGIBIG_PAYABLE, 'VendorType' => 'Statutory Agency Pag-IBIG'],
            ['PayableName' => 'Withholding Tax', 'Amount' => $aggregated_payroll['total_withholding_tax'], 'GLAccountID' => ACC_WHT_PAYABLE, 'VendorType' => 'Statutory Agency BIR'],
        ];
        // Add other deductions if they are payables to third parties

        $stmt_ap_invoice = $pdo_ap->prepare(
            "INSERT INTO payableinvoices (AllocationID, AccountID, BudgetName, Department, Types, Amount, StartDate, Status)
             VALUES (:alloc_id, :acc_id, :budget_name, :dept, :types, :amount, :start_date, :status)"
        );

        foreach ($payables_to_create as $payable) {
            if (abs($payable['Amount']) > 0.001) {
                // TODO: Determine actual AllocationID from fin_budget_management for these payables
                $ap_allocation_id = DEFAULT_BUDGET_ALLOCATION_ID; // Placeholder
                $ap_budget_name = 'Statutory - Payroll ' . $payroll_id_to_process; // Placeholder
                $ap_department = 'Finance/Compliance'; // Placeholder

                $stmt_ap_invoice->execute([
                    ':alloc_id' => $ap_allocation_id,
                    ':acc_id' => $payable['GLAccountID'], // This is the GL Liability AccountID
                    ':budget_name' => $ap_budget_name,
                    ':dept' => $ap_department,
                    ':types' => $payable['VendorType'], // Ensure this matches enum/varchar in your table
                    ':amount' => round($payable['Amount'], 2),
                    ':start_date' => $payroll_run_details['PaymentDate'],
                    ':status' => 'Pending'
                ]);
                $ap_invoice_id = $pdo_ap->lastInsertId();
                $ap_invoice_ids[$payable['PayableName']] = $ap_invoice_id;
                $log_messages[] = "Created AP Invoice for {$payable['PayableName']}, AP ID: {$ap_invoice_id}.";
            }
        }
        $pdo_ap->commit();
    } catch (Exception $e) {
        if ($pdo_ap->inTransaction()) $pdo_ap->rollBack();
        throw new Exception("AP Invoice Creation Failed for HR Payroll ID {$payroll_id_to_process}: " . $e->getMessage());
    }

    // --- 5. Disbursement Request for Net Payroll (fin_disbursement) ---
    $pdo_disbursement->beginTransaction();
    try {
        if (abs($aggregated_payroll['total_net_income']) > 0.001) {
            // TODO: Determine AllocationID for Net Payroll from fin_budget_management
            $net_payroll_allocation_id = DEFAULT_BUDGET_ALLOCATION_ID; // Placeholder

            $stmt_disb_req = $pdo_disbursement->prepare(
                "INSERT INTO disbursementrequests (EmployeeID, AllocationID, Amount, DateOfRequest, Status)
                 VALUES (:emp_id, :alloc_id, :amount, NOW(), :status)"
            );
            $stmt_disb_req->execute([
                ':emp_id' => FINANCE_USER_ID, // User ID from fin_disbursement.employees
                ':alloc_id' => $net_payroll_allocation_id,
                ':amount' => round($aggregated_payroll['total_net_income'], 2),
                ':status' => 'Pending'
            ]);
            $disbursement_request_id_net_payroll = $pdo_disbursement->lastInsertId();
            $log_messages[] = "Created Disbursement Request for Net Payroll. Disb. Req. ID: {$disbursement_request_id_net_payroll}.";
        }
        $pdo_disbursement->commit();
    } catch (Exception $e) {
        if ($pdo_disbursement->inTransaction()) $pdo_disbursement->rollBack();
        throw new Exception("Net Payroll Disbursement Request Failed for HR Payroll ID {$payroll_id_to_process}: " . $e->getMessage());
    }
    
    // --- 6. Update HR PayrollRun Status ---
    // Add a column like 'FinancialPostingStatus' (VARCHAR or ENUM) to PayrollRuns table in hr_integrated_db
    // ALTER TABLE PayrollRuns ADD FinancialPostingStatus VARCHAR(20) DEFAULT NULL;
    $pdo_hr->beginTransaction();
    try {
        $stmt_update_hr_status = $pdo_hr->prepare("UPDATE PayrollRuns SET FinancialPostingStatus = 'Posted', FinancialPostingDate = NOW() WHERE PayrollID = :payroll_id");
        $stmt_update_hr_status->bindParam(':payroll_id', $payroll_id_to_process, PDO::PARAM_INT);
        $stmt_update_hr_status->execute();
        $pdo_hr->commit();
        $log_messages[] = "HR PayrollRun ID {$payroll_id_to_process} status updated to 'PostedToFinance'.";
    } catch (Exception $e) {
        if ($pdo_hr->inTransaction()) $pdo_hr->rollBack();
        // Log this error but don't necessarily halt the whole process if other parts succeeded.
        error_log("Failed to update HR PayrollRun status for ID {$payroll_id_to_process}: " . $e->getMessage());
        $log_messages[] = "Warning: Failed to update HR PayrollRun status for ID {$payroll_id_to_process}: " . $e->getMessage();
    }


    http_response_code(200);
    echo json_encode([
        'message' => "Financial posting successfully completed for HR Payroll ID {$payroll_id_to_process}.",
        'hr_payroll_id' => $payroll_id_to_process,
        'gl_transaction_id' => $gl_transaction_id,
        'ap_invoice_ids_created' => $ap_invoice_ids,
        'net_payroll_disbursement_request_id' => $disbursement_request_id_net_payroll,
        'logs' => $log_messages
    ]);

} catch (Exception $e) {
    error_log("CRITICAL FINANCIAL INTEGRATION ERROR for HR Payroll ID {$payroll_id_to_process}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Financial integration process failed.', 
        'details' => $e->getMessage(),
        'hr_payroll_id' => $payroll_id_to_process,
        'logs' => $log_messages // Include logs even on failure for debugging
    ]);
}
?>
