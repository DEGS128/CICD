<?php
/**
 * API Endpoint: Add Salary Adjustment
 * Adds a new salary adjustment record for an employee.
 * V2.2 - Removed 'Notes' column from SalaryAdjustments insert to match schema.
 * V2.1 - Corrected SQL column name from EffectiveDate to AdjustmentDate.
 * V2: If reason is performance-related, attempts to fetch latest performance
 * rating from HR 1-2 system and appends it to the notes/reason.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../../php-error.log');

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection (HR 3-4 DB) ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('HR 3-4 Database connection object ($pdo) not properly created.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in add_salary_adjustment.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the HR 3-4 database.']);
    exit;
}

// --- Authentication & Authorization Check ---
// Assuming only HR Admins or SysAdmins can add salary adjustments
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) { // 1 for SysAdmin, 2 for HR Admin
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Permission denied to add salary adjustments.']);
    exit;
}
$loggedInUserId = $_SESSION['user_id']; // This is the UserID of the admin performing the action
$approverEmployeeId = $_SESSION['employee_id']; // EmployeeID of the admin, to be stored as ApprovedBy
// --- End Authentication & Authorization Check ---

// --- Function to call HR 1-2 API for latest performance rating ---
/**
 * Fetches latest performance rating from the HR 1-2 system.
 * @param int $hr34_employee_id The EmployeeID from the HR 3-4 system.
 * @return array|null Performance rating data or null on error/not found.
 */
function getLatestPerformanceRatingFromHR12($hr34_employee_id) {
    $hr12_api_base_url = 'http://localhost/hr12_system/api/'; // ADJUST IF YOUR HR12 PATH IS DIFFERENT
    $hr12_rating_endpoint = $hr12_api_base_url . 'hr12_get_latest_performance_rating.php';
    $api_key_for_hr12 = '1111'; // Must match HR 1-2 API's expectation

    $url_with_param = $hr12_rating_endpoint . '?hr34_employee_id=' . urlencode($hr34_employee_id);
    error_log("[add_salary_adjustment.php] Calling HR 1-2 API for latest rating: " . $url_with_param);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_with_param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Shorter timeout for this
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'X-API-KEY: ' . $api_key_for_hr12
    ]);

    $response_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("cURL Error calling HR 1-2 latest rating API: " . $curl_error);
        return null;
    }

    if ($http_code == 200) {
        $data = json_decode($response_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['overall_rating'])) {
            return $data; // Expecting ['overall_rating' => x.x, 'last_review_date' => 'YYYY-MM-DD']
        }
        error_log("HR 1-2 latest rating API response not valid JSON or missing rating. HTTP: {$http_code}. Resp: " . substr($response_json, 0, 100));
    } else {
        error_log("HR 1-2 latest rating API returned HTTP status: {$http_code}. Resp: " . substr($response_json, 0, 100));
    }
    return null;
}
// --- End HR 1-2 API Call Function ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload. Error: ' . json_last_error_msg()]);
    exit;
}

// --- Extract and sanitize data ---
$employee_id = isset($input_data['employee_id']) ? filter_var($input_data['employee_id'], FILTER_VALIDATE_INT) : null;
$new_base_salary = isset($input_data['new_base_salary']) ? filter_var($input_data['new_base_salary'], FILTER_VALIDATE_FLOAT) : null;
$new_pay_frequency = isset($input_data['new_pay_frequency']) ? trim(htmlspecialchars($input_data['new_pay_frequency'])) : null;
$new_pay_rate = isset($input_data['new_pay_rate']) ? filter_var($input_data['new_pay_rate'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
$effective_date = isset($input_data['effective_date']) ? $input_data['effective_date'] : null; // YYYY-MM-DD
$reason = isset($input_data['reason']) ? trim(htmlspecialchars($input_data['reason'])) : 'General Adjustment';
$notes_from_js = isset($input_data['notes']) ? trim(htmlspecialchars($input_data['notes'])) : ''; // Notes from JS form

// This $notes variable will accumulate performance data if applicable, and then JS notes.
$notes_for_processing = $notes_from_js;


// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id <= 0) $errors['employee_id'] = 'Valid Employee ID is required.';
if ($new_base_salary === null || $new_base_salary < 0) $errors['new_base_salary'] = 'Valid New Base Salary is required.';
if (empty($new_pay_frequency)) $errors['new_pay_frequency'] = 'New Pay Frequency is required.';

if (empty($effective_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date)) {
    $errors['effective_date'] = 'Valid Effective Date (YYYY-MM-DD) is required.';
}
if (empty($reason)) $errors['reason'] = 'Reason for adjustment is required.';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Fetch performance rating if reason is performance-related ---
$performance_related_reasons = ['merit increase', 'performance-based adjustment', 'performance bonus adjustment', 'annual increment', 'promotion']; // Case-insensitive check
if (in_array(strtolower($reason), $performance_related_reasons)) {
    $ratingInfo = getLatestPerformanceRatingFromHR12($employee_id);
    if ($ratingInfo && isset($ratingInfo['overall_rating']) && $ratingInfo['overall_rating'] !== null) {
        $performance_note = "Basis: Latest Performance Rating from HR1-2: {$ratingInfo['overall_rating']}";
        if(isset($ratingInfo['last_review_date'])) {
            $performance_note .= " (Review Date: {$ratingInfo['last_review_date']}).";
        }
        // Prepend performance note to existing notes
        $notes_for_processing = $performance_note . (empty($notes_for_processing) ? '' : "\n" . $notes_for_processing);
    } else {
        $no_rating_note = "Note: Could not retrieve latest performance rating from HR 1-2 system for this adjustment.";
        // Prepend no rating note
        $notes_for_processing = $no_rating_note . (empty($notes_for_processing) ? '' : "\n" . $notes_for_processing);
        error_log("Could not retrieve performance rating for EmployeeID {$employee_id} for performance-based adjustment.");
    }
}
// --- End Fetch Performance Rating ---

// --- Database Transaction ---
try {
    $pdo->beginTransaction();

    // 1. Get current salary ID and end-date it
    $previous_salary_id = null;
    $stmt_current_salary = $pdo->prepare("SELECT SalaryID FROM EmployeeSalaries WHERE EmployeeID = :employee_id AND IsCurrent = TRUE");
    $stmt_current_salary->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt_current_salary->execute();
    $current_salary_row = $stmt_current_salary->fetch(PDO::FETCH_ASSOC);

    if ($current_salary_row) {
        $previous_salary_id = $current_salary_row['SalaryID'];
        // Calculate day before new effective date
        $previous_end_date = date('Y-m-d', strtotime($effective_date . ' -1 day'));

        $stmt_update_old = $pdo->prepare("UPDATE EmployeeSalaries SET IsCurrent = FALSE, EndDate = :end_date WHERE SalaryID = :salary_id");
        $stmt_update_old->bindParam(':end_date', $previous_end_date, PDO::PARAM_STR);
        $stmt_update_old->bindParam(':salary_id', $previous_salary_id, PDO::PARAM_INT);
        $stmt_update_old->execute();
    }

    // 2. Insert new salary record
    $sql_new_salary = "INSERT INTO EmployeeSalaries (EmployeeID, BaseSalary, PayFrequency, PayRate, EffectiveDate, IsCurrent)
                       VALUES (:employee_id, :base_salary, :pay_frequency, :pay_rate, :effective_date, TRUE)";
    $stmt_new_salary = $pdo->prepare($sql_new_salary);
    $stmt_new_salary->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt_new_salary->bindParam(':base_salary', $new_base_salary);
    $stmt_new_salary->bindParam(':pay_frequency', $new_pay_frequency, PDO::PARAM_STR);
    $stmt_new_salary->bindValue(':pay_rate', $new_pay_rate, $new_pay_rate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_new_salary->bindParam(':effective_date', $effective_date, PDO::PARAM_STR);
    $stmt_new_salary->execute();
    $new_salary_id = $pdo->lastInsertId();

    if (!$new_salary_id) {
        throw new Exception("Failed to insert new salary record.");
    }

    // 3. Insert into SalaryAdjustments table
    // Removed 'Notes' column from the INSERT statement as it's not in the SalaryAdjustments table schema.
    // The $notes_for_processing variable can be logged or used if the schema changes later.
    $sql_adjustment = "INSERT INTO SalaryAdjustments (EmployeeID, PreviousSalaryID, NewSalaryID, AdjustmentDate, Reason, ApprovedBy, ApprovalDate, PercentageIncrease)
                       VALUES (:employee_id, :previous_salary_id, :new_salary_id, :effective_date, :reason, :approved_by, NOW(), NULL)"; // PercentageIncrease set to NULL
    $stmt_adjustment = $pdo->prepare($sql_adjustment);
    $stmt_adjustment->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt_adjustment->bindValue(':previous_salary_id', $previous_salary_id, $previous_salary_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt_adjustment->bindParam(':new_salary_id', $new_salary_id, PDO::PARAM_INT);
    $stmt_adjustment->bindParam(':effective_date', $effective_date, PDO::PARAM_STR);
    $stmt_adjustment->bindParam(':reason', $reason, PDO::PARAM_STR);
    // $stmt_adjustment->bindParam(':notes', $notes_for_processing, PDO::PARAM_STR); // Notes column removed
    $stmt_adjustment->bindParam(':approved_by', $approverEmployeeId, PDO::PARAM_INT);
    $stmt_adjustment->execute();
    
    // Log the notes if any were generated/provided, even if not stored in SalaryAdjustments
    if (!empty($notes_for_processing)) {
        error_log("Salary Adjustment Notes for EmployeeID {$employee_id}, NewSalaryID {$new_salary_id}: " . $notes_for_processing);
    }


    $pdo->commit();

    http_response_code(201); // Created
    echo json_encode(['message' => 'Salary adjustment added successfully.', 'new_salary_id' => $new_salary_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP PDOException in add_salary_adjustment.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error processing salary adjustment.', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP Throwable in add_salary_adjustment.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error processing salary adjustment.']);
}
exit;
?>
