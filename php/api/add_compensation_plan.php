<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in add_compensation_plan.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

// --- Get Data from POST Request (expecting JSON) ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

// --- Extract and sanitize data ---
$plan_name = isset($input_data['plan_name']) ? trim(htmlspecialchars($input_data['plan_name'])) : null;
$description = isset($input_data['description']) ? trim(htmlspecialchars($input_data['description'])) : null;
$effective_date = isset($input_data['effective_date']) ? $input_data['effective_date'] : null; // Expect YYYY-MM-DD
$end_date = isset($input_data['end_date']) ? $input_data['end_date'] : null;             // Expect YYYY-MM-DD, optional
$plan_type = isset($input_data['plan_type']) ? trim(htmlspecialchars($input_data['plan_type'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($plan_name)) {
    $errors['plan_name'] = 'Plan Name is required.';
}
if (empty($effective_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date)) {
    $errors['effective_date'] = 'Valid Effective Date (YYYY-MM-DD) is required.';
}
// Validate end_date only if provided
if (!empty($end_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
     $errors['end_date'] = 'End Date must be in YYYY-MM-DD format if provided.';
} elseif (!empty($end_date) && $end_date < $effective_date) {
     $errors['end_date'] = 'End Date cannot be before Effective Date.';
}
if (empty($description)) $description = null; // Allow empty
if (empty($plan_type)) $plan_type = null;     // Allow empty
if (empty($end_date)) $end_date = null;         // Store as NULL if empty

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Insert into Database ---
try {
    $sql = "INSERT INTO CompensationPlans (PlanName, Description, EffectiveDate, EndDate, PlanType)
            VALUES (:plan_name, :description, :effective_date, :end_date, :plan_type)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':plan_name', $plan_name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':effective_date', $effective_date, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $end_date, $end_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':plan_type', $plan_type, PDO::PARAM_STR);

    $stmt->execute();
    $new_plan_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Compensation plan added successfully.',
        'plan_id' => $new_plan_id
    ]);

} catch (\PDOException $e) {
    error_log("PHP PDOException in add_compensation_plan.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., duplicate PlanName if unique)
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Failed to add plan. A plan with this name might already exist.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error adding compensation plan.']);
    }
} catch (Throwable $e) {
    error_log("PHP Throwable in add_compensation_plan.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error adding compensation plan.']);
}
exit;
?>
