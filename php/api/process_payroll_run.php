<?php
/**
 * API Endpoint: Process Payroll Run
 * Calculates payslips for all eligible employees for a given payroll run ID
 * and inserts them into the Payslips table.
 * v2.3 - Integrated approved claim reimbursements into payroll (Option B).
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); 

// --- Increase execution time limit for potentially long process ---
set_time_limit(300); // 5 minutes, adjust as needed

// --- Session Start (Required for Auth Checks) ---
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; 
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection object not created.');
    }
} catch (Throwable $e) {
    error_log("Process Payroll Error (DB Connection): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authentication & Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}
$allowed_roles = [1, 2]; // System Admin, HR Admin
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); 
     echo json_encode(['error' => 'Permission denied. You do not have rights to process payroll.']);
     exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

$payroll_id = isset($input_data['payroll_id']) ? filter_var($input_data['payroll_id'], FILTER_VALIDATE_INT) : null;

if (empty($payroll_id) || $payroll_id === false || $payroll_id <= 0) {
     http_response_code(400); 
     echo json_encode(['error' => 'Valid Payroll Run ID is required.']);
     exit;
}

// Placeholder Calculation Functions (Keep these from your original file)
function calculate_sss(float $gross_income): float {
    if ($gross_income <= 3250) return 135.00;
    if ($gross_income <= 24750) return 1125.00;
    return 1125.00; 
}
function calculate_philhealth(float $gross_income): float {
    $rate = 0.04;
    $min_contribution = 400;
    $max_contribution = 3200;
    $contribution = $gross_income * $rate;
    if ($contribution < $min_contribution) return $min_contribution / 2;
    if ($contribution > $max_contribution) return $max_contribution / 2;
    return $contribution / 2;
}
function calculate_pagibig(float $gross_income): float {
    if ($gross_income > 1500) {
        return 100.00;
    }
    return 0.00;
}
function calculate_withholding_tax(float $taxable_income, string $pay_frequency): float {
    $tax_rate = 0.10; 
    $tax = 0.00;
    if ($pay_frequency === 'Monthly' && $taxable_income > 20833) {
        $tax = ($taxable_income - 20833) * $tax_rate;
    } elseif ($pay_frequency === 'Bi-Weekly' && $taxable_income > 10417) {
         $tax = ($taxable_income - 10417) * $tax_rate;
    }
    return max(0, $tax); 
}
function calculate_overtime_pay(float $overtime_hours, float $hourly_rate, float $ot_multiplier = 1.25): float {
    if ($overtime_hours <= 0 || $hourly_rate <= 0) {
        return 0.00;
    }
    return $overtime_hours * $hourly_rate * $ot_multiplier;
}
function calculate_regular_pay(string $pay_frequency, float $base_salary, float $hourly_rate, float $hours_worked, string $period_start, string $period_end): float {
    if ($pay_frequency === 'Hourly') {
        return $hourly_rate * $hours_worked;
    } elseif ($pay_frequency === 'Monthly') {
        return $base_salary;
    } elseif ($pay_frequency === 'Bi-Weekly') {
        return $base_salary / 2;
    }
    error_log("Warning: Unhandled pay frequency '{$pay_frequency}' in calculate_regular_pay.");
    return 0.00; 
}


$processed_count = 0;
$error_count = 0;
$employee_errors = [];

try {
    $sql_run = "SELECT PayPeriodStartDate, PayPeriodEndDate, PaymentDate, Status FROM PayrollRuns WHERE PayrollID = :payroll_id";
    $stmt_run = $pdo->prepare($sql_run);
    $stmt_run->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
    $stmt_run->execute();
    $payroll_run = $stmt_run->fetch();

    if (!$payroll_run) {
        http_response_code(404);
        echo json_encode(['error' => "Payroll Run ID {$payroll_id} not found."]);
        exit;
    }
    if ($payroll_run['Status'] === 'Completed' || $payroll_run['Status'] === 'Processing') {
         http_response_code(409); 
         echo json_encode(['error' => "Payroll Run ID {$payroll_id} is already {$payroll_run['Status']}."]);
         exit;
    }
     if ($payroll_run['Status'] !== 'Pending') { 
         http_response_code(409); 
         echo json_encode(['error' => "Payroll Run ID {$payroll_id} has status '{$payroll_run['Status']}' and cannot be processed."]);
         exit;
    }

    $pay_period_start = $payroll_run['PayPeriodStartDate'];
    $pay_period_end = $payroll_run['PayPeriodEndDate'];
    $payment_date = $payroll_run['PaymentDate'];

    $sql_update_status = "UPDATE PayrollRuns SET Status = 'Processing' WHERE PayrollID = :payroll_id";
    $stmt_update_status = $pdo->prepare($sql_update_status);
    $stmt_update_status->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
    $stmt_update_status->execute();

    $sql_employees = "SELECT EmployeeID FROM Employees WHERE IsActive = TRUE";
    $stmt_employees = $pdo->query($sql_employees);

    while ($employee = $stmt_employees->fetch()) {
        $employee_id = $employee['EmployeeID'];
        error_log("Processing EmployeeID: {$employee_id} for PayrollRunID: {$payroll_id}");
        $pdo->beginTransaction(); 

        try {
            $sql_salary = "SELECT BaseSalary, PayFrequency, PayRate FROM EmployeeSalaries
                           WHERE EmployeeID = :employee_id AND IsCurrent = TRUE";
            $stmt_salary = $pdo->prepare($sql_salary);
            $stmt_salary->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt_salary->execute();
            $salary_info = $stmt_salary->fetch();

            if (!$salary_info) {
                throw new Exception("No current salary record found.");
            }
            $base_salary = (float)($salary_info['BaseSalary'] ?? 0);
            $pay_frequency = $salary_info['PayFrequency'] ?? 'Monthly'; 
            $hourly_rate = (float)($salary_info['PayRate'] ?? 0);

            $sql_timesheet = "SELECT TotalHoursWorked, OvertimeHours FROM Timesheets
                              WHERE EmployeeID = :employee_id
                                AND PeriodStartDate = :start_date
                                AND PeriodEndDate = :end_date
                                AND Status = 'Approved'";
            $stmt_timesheet = $pdo->prepare($sql_timesheet);
            $stmt_timesheet->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt_timesheet->bindParam(':start_date', $pay_period_start, PDO::PARAM_STR);
            $stmt_timesheet->bindParam(':end_date', $pay_period_end, PDO::PARAM_STR);
            $stmt_timesheet->execute();
            $timesheet_info = $stmt_timesheet->fetch();

            $hours_worked = 0.00;
            $overtime_hours = 0.00;
            if ($pay_frequency === 'Hourly' && $timesheet_info) {
                 $hours_worked = (float)($timesheet_info['TotalHoursWorked'] ?? 0);
                 $overtime_hours = (float)($timesheet_info['OvertimeHours'] ?? 0);
            } // Else: standard hours logic for salaried might be needed if not strictly timesheet based for them

            error_log("EmployeeID {$employee_id}: Using HoursWorked={$hours_worked}, OvertimeHours={$overtime_hours}");

            $sql_bonuses = "SELECT SUM(BonusAmount) as TotalBonus FROM Bonuses
                            WHERE EmployeeID = :employee_id
                              AND (PayrollID = :payroll_id OR (PayrollID IS NULL AND AwardDate BETWEEN :start_date AND :end_date))";
            $stmt_bonuses = $pdo->prepare($sql_bonuses);
            $stmt_bonuses->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt_bonuses->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT); 
            $stmt_bonuses->bindParam(':start_date', $pay_period_start, PDO::PARAM_STR); 
            $stmt_bonuses->bindParam(':end_date', $pay_period_end, PDO::PARAM_STR); 
            $stmt_bonuses->execute();
            $bonuses_total = (float)($stmt_bonuses->fetchColumn() ?: 0);
            error_log("EmployeeID {$employee_id}: Found BonusesTotal={$bonuses_total}");

            // **** START: Fetch Approved Claims for Reimbursement ****
            $sql_claims_to_reimburse = "SELECT SUM(Amount) as TotalClaimsAmount, GROUP_CONCAT(ClaimID) as ReimbursedClaimIDs
                                        FROM Claims
                                        WHERE EmployeeID = :employee_id
                                          AND Status = 'Approved'
                                          AND PayrollID IS NULL"; // Only pick up claims not yet linked to a payroll
            $stmt_claims_reimburse = $pdo->prepare($sql_claims_to_reimburse);
            $stmt_claims_reimburse->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt_claims_reimburse->execute();
            $claims_data = $stmt_claims_reimburse->fetch(PDO::FETCH_ASSOC);
            $reimbursable_claims_total = (float)($claims_data['TotalClaimsAmount'] ?? 0);
            $reimbursed_claim_ids_array = !empty($claims_data['ReimbursedClaimIDs']) ? explode(',', $claims_data['ReimbursedClaimIDs']) : [];
            error_log("EmployeeID {$employee_id}: Found ReimbursableClaimsTotal={$reimbursable_claims_total} for ClaimIDs: " . ($claims_data['ReimbursedClaimIDs'] ?? 'None'));
            // **** END: Fetch Approved Claims ****

            $sql_deductions = "SELECT SUM(DeductionAmount) as TotalOtherDeductions FROM Deductions
                               WHERE EmployeeID = :employee_id AND PayrollID = :payroll_id";
            $stmt_deductions = $pdo->prepare($sql_deductions);
            $stmt_deductions->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
            $stmt_deductions->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
            $stmt_deductions->execute();
            $other_deductions_total = (float)($stmt_deductions->fetchColumn() ?: 0);
            error_log("EmployeeID {$employee_id}: Found OtherDeductionsTotal={$other_deductions_total}");

            $regular_pay = calculate_regular_pay($pay_frequency, $base_salary, $hourly_rate, $hours_worked, $pay_period_start, $pay_period_end);
            $overtime_pay = calculate_overtime_pay($overtime_hours, $hourly_rate);
            $holiday_pay = 0.00; 
            $night_differential_pay = 0.00;

            // Use $reimbursable_claims_total for OtherEarnings
            $other_earnings_for_payslip = $reimbursable_claims_total;

            $gross_income = $regular_pay + $overtime_pay + $holiday_pay + $night_differential_pay + $bonuses_total + $other_earnings_for_payslip;

            $sss_contribution = calculate_sss($gross_income);
            $philhealth_contribution = calculate_philhealth($gross_income); 
            $pagibig_contribution = calculate_pagibig($gross_income);
            $taxable_income = $gross_income - ($sss_contribution + $philhealth_contribution + $pagibig_contribution);
            $taxable_income = max(0, $taxable_income); 
            $withholding_tax = calculate_withholding_tax($taxable_income, $pay_frequency);
            $total_deductions = $sss_contribution + $philhealth_contribution + $pagibig_contribution + $withholding_tax + $other_deductions_total;
            $net_income = $gross_income - $total_deductions;

            error_log("EmployeeID {$employee_id}: Calculated Gross={$gross_income}, SSS={$sss_contribution}, PhilH={$philhealth_contribution}, PagIBIG={$pagibig_contribution}, Tax={$withholding_tax}, OtherDed={$other_deductions_total}, TotalDed={$total_deductions}, Net={$net_income}, ReimbursementsIncluded={$other_earnings_for_payslip}");

            $sql_insert_payslip = "INSERT INTO Payslips (
                                    PayrollID, EmployeeID, PayPeriodStartDate, PayPeriodEndDate, PaymentDate,
                                    BasicSalary, HourlyRate, HoursWorked, OvertimeHours, RegularPay,
                                    OvertimePay, HolidayPay, NightDifferentialPay, BonusesTotal, OtherEarnings,
                                    GrossIncome, SSS_Contribution, PhilHealth_Contribution, PagIBIG_Contribution,
                                    WithholdingTax, OtherDeductionsTotal, TotalDeductions, NetIncome
                                ) VALUES (
                                    :payroll_id, :employee_id, :start_date, :end_date, :payment_date,
                                    :basic_salary, :hourly_rate, :hours_worked, :overtime_hours, :regular_pay,
                                    :overtime_pay, :holiday_pay, :night_diff_pay, :bonuses_total, :other_earnings,
                                    :gross_income, :sss, :philhealth, :pagibig,
                                    :tax, :other_deductions, :total_deductions, :net_income
                                )";
            $stmt_insert = $pdo->prepare($sql_insert_payslip);
            $stmt_insert->execute([
                ':payroll_id' => $payroll_id, ':employee_id' => $employee_id, ':start_date' => $pay_period_start, ':end_date' => $pay_period_end, ':payment_date' => $payment_date,
                ':basic_salary' => $base_salary, ':hourly_rate' => ($hourly_rate > 0 ? $hourly_rate : null), ':hours_worked' => $hours_worked, ':overtime_hours' => $overtime_hours, ':regular_pay' => $regular_pay,
                ':overtime_pay' => $overtime_pay, ':holiday_pay' => $holiday_pay, ':night_diff_pay' => $night_differential_pay, ':bonuses_total' => $bonuses_total,
                ':other_earnings' => $other_earnings_for_payslip, // Includes reimbursements
                ':gross_income' => $gross_income, ':sss' => $sss_contribution, ':philhealth' => $philhealth_contribution, ':pagibig' => $pagibig_contribution,
                ':tax' => $withholding_tax, ':other_deductions' => $other_deductions_total, ':total_deductions' => $total_deductions, ':net_income' => $net_income
            ]);

            // **** START: Mark Claims as Processed ****
            if (!empty($reimbursed_claim_ids_array)) {
                // Constructing the IN clause placeholders dynamically
                $placeholders = implode(',', array_fill(0, count($reimbursed_claim_ids_array), '?'));
                
                $sql_update_claims_paid = "UPDATE Claims
                                           SET Status = 'Paid', PayrollID = ?
                                           WHERE ClaimID IN ($placeholders)
                                             AND EmployeeID = ?"; // Added EmployeeID check for safety
                $stmt_update_claims_paid = $pdo->prepare($sql_update_claims_paid);
                
                // Bind parameters
                $params_for_update = array_merge([$payroll_id], $reimbursed_claim_ids_array, [$employee_id]);
                $stmt_update_claims_paid->execute($params_for_update);
                
                error_log("EmployeeID {$employee_id}: Marked ClaimIDs " . implode(',', $reimbursed_claim_ids_array) . " as Paid and linked to PayrollID {$payroll_id}");
            }
            // **** END: Mark Claims as Processed ****

            $sql_update_bonuses = "UPDATE Bonuses SET PayrollID = :payroll_id
                                   WHERE EmployeeID = :employee_id
                                     AND PayrollID IS NULL
                                     AND AwardDate BETWEEN :start_date AND :end_date";
            $stmt_update_bonuses = $pdo->prepare($sql_update_bonuses);
            $stmt_update_bonuses->execute([
                ':payroll_id' => $payroll_id,
                ':employee_id' => $employee_id,
                ':start_date' => $pay_period_start,
                ':end_date' => $pay_period_end
            ]);

            $pdo->commit(); 
            $processed_count++;
            error_log("Successfully processed EmployeeID: {$employee_id}");

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); } 
            $error_count++;
            $employee_errors[$employee_id] = $e->getMessage();
            error_log("Error processing payroll for EmployeeID {$employee_id} in PayrollRun {$payroll_id}: " . $e->getMessage());
        }
    } 

    $final_status = ($error_count === 0 && $processed_count > 0) ? 'Completed' : (($error_count > 0 && $processed_count > 0) ? 'Partial Failure' : 'Failed');
    if ($processed_count === 0 && $error_count === 0) {
        $final_status = 'Failed'; 
        $employee_errors['general'] = 'No eligible employees found or processed for this run.';
    } elseif ($processed_count === 0 && $error_count > 0) {
         $final_status = 'Failed';
    }

    $sql_final_update = "UPDATE PayrollRuns SET Status = :final_status, ProcessedDate = NOW() WHERE PayrollID = :payroll_id";
    $stmt_final_update = $pdo->prepare($sql_final_update);
    $stmt_final_update->bindParam(':final_status', $final_status, PDO::PARAM_STR);
    $stmt_final_update->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
    $stmt_final_update->execute();

    http_response_code(200);
    $response_message = "Payroll Run {$payroll_id} processing finished. Status: {$final_status}. {$processed_count} employees successful.";
    if ($error_count > 0) {
        $response_message .= " {$error_count} employees failed.";
    }
    echo json_encode([
        'message' => $response_message,
        'payroll_id' => $payroll_id,
        'status' => $final_status,
        'processed_count' => $processed_count,
        'error_count' => $error_count,
        'errors' => $employee_errors 
    ]);

} catch (\PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    try {
         $sql_fail_update = "UPDATE PayrollRuns SET Status = 'Failed' WHERE PayrollID = :payroll_id AND Status = 'Processing'";
         $stmt_fail = $pdo->prepare($sql_fail_update);
         $stmt_fail->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
         $stmt_fail->execute();
    } catch (Exception $inner_e) {
         error_log("Failed to reset payroll run status to Failed after error: " . $inner_e->getMessage());
    }
    error_log("Process Payroll Error (PDOException): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A database error occurred during payroll processing.']);
} catch (Throwable $e) {
     if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
     try {
         $sql_fail_update = "UPDATE PayrollRuns SET Status = 'Failed' WHERE PayrollID = :payroll_id AND Status = 'Processing'";
         $stmt_fail = $pdo->prepare($sql_fail_update);
         $stmt_fail->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
         $stmt_fail->execute();
     } catch (Exception $inner_e) {
          error_log("Failed to reset payroll run status to Failed after error: " . $inner_e->getMessage());
     }
     error_log("Process Payroll Error (Throwable): " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected error occurred during payroll processing.']);
}
exit;
?>
