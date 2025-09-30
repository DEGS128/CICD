<?php
/**
 * API Endpoint: Update User Profile
 * Allows the logged-in user to update their own profile information.
 * V1.2 - Added more fields to be updatable: Address, Emergency Contact.
 * V1.1 - Added PersonalEmail to updatable fields.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Needed for authentication

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Use POST for updates
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

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
    error_log("PHP Error in update_user_profile.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$loggedInEmployeeId = $_SESSION['employee_id'];
// --- End Authentication Check ---

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
$email = isset($input_data['email']) ? filter_var(trim($input_data['email']), FILTER_VALIDATE_EMAIL) : null;
$personal_email = isset($input_data['personal_email']) ? filter_var(trim($input_data['personal_email']), FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE) : null;
$phone_number = isset($input_data['phone_number']) ? trim(htmlspecialchars($input_data['phone_number'])) : null;

$address_line1 = isset($input_data['address_line1']) ? trim(htmlspecialchars($input_data['address_line1'])) : null;
$address_line2 = isset($input_data['address_line2']) ? trim(htmlspecialchars($input_data['address_line2'])) : null;
$city = isset($input_data['city']) ? trim(htmlspecialchars($input_data['city'])) : null;
$state_province = isset($input_data['state_province']) ? trim(htmlspecialchars($input_data['state_province'])) : null;
$postal_code = isset($input_data['postal_code']) ? trim(htmlspecialchars($input_data['postal_code'])) : null;
$country = isset($input_data['country']) ? trim(htmlspecialchars($input_data['country'])) : null;

$emergency_contact_name = isset($input_data['emergency_contact_name']) ? trim(htmlspecialchars($input_data['emergency_contact_name'])) : null;
$emergency_contact_relationship = isset($input_data['emergency_contact_relationship']) ? trim(htmlspecialchars($input_data['emergency_contact_relationship'])) : null;
$emergency_contact_phone = isset($input_data['emergency_contact_phone']) ? trim(htmlspecialchars($input_data['emergency_contact_phone'])) : null;


// --- Validate Input ---
$errors = [];
if (empty($email)) { 
    $errors['email'] = 'A valid Work Email is required.';
}

if (isset($input_data['personal_email']) && !empty($input_data['personal_email']) && !$personal_email) {
    $errors['personal_email'] = 'Personal Email is not a valid email format.';
} elseif (empty($input_data['personal_email'])) {
    $personal_email = null; 
}

if (!empty($phone_number) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $phone_number)) { 
    $errors['phone_number'] = 'Invalid phone number format.';
}
if (empty($phone_number)) $phone_number = null; 

if (!empty($emergency_contact_phone) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $emergency_contact_phone)) { 
    $errors['emergency_contact_phone'] = 'Invalid emergency contact phone format.';
}
if (empty($emergency_contact_phone)) $emergency_contact_phone = null;


// Check for duplicate work email
if (empty($errors) && $email) {
    try {
        $checkEmailSql = "SELECT EmployeeID FROM Employees WHERE Email = :email AND EmployeeID != :current_employee_id";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $checkEmailStmt->bindParam(':current_employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $checkEmailStmt->execute();
        if ($checkEmailStmt->fetch()) {
             $errors['email_duplicate'] = 'This work email address is already in use by another account.';
        }
    } catch (\PDOException $e) {
         error_log("API Error (update_user_profile - Work Email Check): " . $e->getMessage());
         $errors['database_check_work_email'] = 'Error checking work email uniqueness.';
    }
}
// Check for duplicate personal email
if (empty($errors) && $personal_email && $personal_email !== $email) {
    try {
        $checkPersonalEmailSql = "SELECT EmployeeID FROM Employees WHERE PersonalEmail = :personal_email AND EmployeeID != :current_employee_id";
        $checkPersonalEmailStmt = $pdo->prepare($checkPersonalEmailSql);
        $checkPersonalEmailStmt->bindParam(':personal_email', $personal_email, PDO::PARAM_STR);
        $checkPersonalEmailStmt->bindParam(':current_employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $checkPersonalEmailStmt->execute();
        if ($checkPersonalEmailStmt->fetch()) {
             $errors['personal_email_duplicate'] = 'This personal email address is already in use by another account.';
        }
    } catch (\PDOException $e) {
         error_log("API Error (update_user_profile - Personal Email Check): " . $e->getMessage());
         $errors['database_check_personal_email'] = 'Error checking personal email uniqueness.';
    }
}


if (!empty($errors)) {
    http_response_code(400); 
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Update Database ---
try {
    $update_fields = [];
    $params = [':employee_id' => $loggedInEmployeeId];

    // Contact Info
    if (array_key_exists('email', $input_data)) { $update_fields[] = "Email = :email"; $params[':email'] = $email; }
    if (array_key_exists('personal_email', $input_data)) { $update_fields[] = "PersonalEmail = :personal_email"; $params[':personal_email'] = $personal_email; }
    if (array_key_exists('phone_number', $input_data)) { $update_fields[] = "PhoneNumber = :phone_number"; $params[':phone_number'] = $phone_number; }

    // Address
    if (array_key_exists('address_line1', $input_data)) { $update_fields[] = "AddressLine1 = :address_line1"; $params[':address_line1'] = $address_line1 ?: null; }
    if (array_key_exists('address_line2', $input_data)) { $update_fields[] = "AddressLine2 = :address_line2"; $params[':address_line2'] = $address_line2 ?: null; }
    if (array_key_exists('city', $input_data)) { $update_fields[] = "City = :city"; $params[':city'] = $city ?: null; }
    if (array_key_exists('state_province', $input_data)) { $update_fields[] = "StateProvince = :state_province"; $params[':state_province'] = $state_province ?: null; }
    if (array_key_exists('postal_code', $input_data)) { $update_fields[] = "PostalCode = :postal_code"; $params[':postal_code'] = $postal_code ?: null; }
    if (array_key_exists('country', $input_data)) { $update_fields[] = "Country = :country"; $params[':country'] = $country ?: null; }
    
    // Emergency Contact
    if (array_key_exists('emergency_contact_name', $input_data)) { $update_fields[] = "EmergencyContactName = :emergency_contact_name"; $params[':emergency_contact_name'] = $emergency_contact_name ?: null; }
    if (array_key_exists('emergency_contact_relationship', $input_data)) { $update_fields[] = "EmergencyContactRelationship = :emergency_contact_relationship"; $params[':emergency_contact_relationship'] = $emergency_contact_relationship ?: null; }
    if (array_key_exists('emergency_contact_phone', $input_data)) { $update_fields[] = "EmergencyContactPhone = :emergency_contact_phone"; $params[':emergency_contact_phone'] = $emergency_contact_phone ?: null; }


    if (empty($update_fields)) {
        http_response_code(200); 
        echo json_encode(['message' => 'No profile information was provided to update.']);
        exit;
    }

    $sql = "UPDATE Employees SET " . implode(", ", $update_fields) . " WHERE EmployeeID = :employee_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'message' => 'Profile updated successfully.',
            'employee_id' => $loggedInEmployeeId
        ]);
    } else {
        // Check if the record exists to differentiate between "no change" and "not found"
        $checkStmt = $pdo->prepare("SELECT EmployeeID FROM Employees WHERE EmployeeID = :employee_id");
        $checkStmt->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
            http_response_code(200); 
            echo json_encode(['message' => 'Profile information submitted, no changes detected.']);
        } else {
            http_response_code(404); // Should not happen if session is valid
            echo json_encode(['error' => 'Employee record not found.']);
        }
    }

} catch (\PDOException $e) {
    error_log("PHP PDOException in update_user_profile.php: " . $e->getMessage());
    if ($e->getCode() == '23000' && (strpos($e->getMessage(), 'Email') !== false || strpos($e->getMessage(), 'PersonalEmail') !== false)) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'This email address is already in use.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error updating profile.']);
    }
} catch (Throwable $e) {
    error_log("PHP Throwable in update_user_profile.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error updating profile.']);
}
exit;
?>
