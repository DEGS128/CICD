<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Required to get SenderUserID if logged in

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    error_log("PHP Error: Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

// --- Configuration for File Uploads ---
$target_dir_relative = "../../uploads/receipts/";
$target_dir_absolute = realpath(__DIR__ . '/' . $target_dir_relative);
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_file_size = 2 * 1024 * 1024; // 2 MB
$db_path_prefix = "uploads/receipts/"; // Relative to web root for DB storage

// --- Directory Checks ---
$target_dir_absolute = __DIR__ . '/' . $target_dir_relative;
if (!is_dir($target_dir_absolute)) {
    if (!mkdir($target_dir_absolute, 0775, true)) { // Attempt to create if not exists
        error_log("Upload directory check failed and could not be created: '{$target_dir_absolute}'.");
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: Upload directory issue.']);
        exit;
    }
     error_log("Created upload directory: " . $target_dir_absolute);
}
$target_dir_absolute = realpath($target_dir_absolute);
if ($target_dir_absolute === false) {
    error_log("Failed to resolve real path for upload directory: " . __DIR__ . '/' . $target_dir_relative);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Upload directory path resolution failed.']);
    exit;
}
if (!is_writable($target_dir_absolute)) {
    error_log("Upload directory is not writable: " . $target_dir_absolute);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Upload directory not writable.']);
    exit;
}
$target_dir_absolute .= DIRECTORY_SEPARATOR;
// --- End Directory Checks ---


// --- Get Data from POST Request (Form Data) ---
$employee_id = isset($_POST['employee_id']) ? filter_var($_POST['employee_id'], FILTER_VALIDATE_INT) : null;
$claim_type_id = isset($_POST['claim_type_id']) ? filter_var($_POST['claim_type_id'], FILTER_VALIDATE_INT) : null;
$claim_date = isset($_POST['claim_date']) ? $_POST['claim_date'] : null;
$amount = isset($_POST['amount']) ? filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT) : null;
$description = isset($_POST['description']) ? trim(htmlspecialchars($_POST['description'])) : null;
$currency = 'PHP';
$initial_status = 'Submitted';
// --- End Get Data ---


// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id <= 0) $errors['employee_id'] = 'Valid Employee ID is required.';
if (empty($claim_type_id) || $claim_type_id <= 0) $errors['claim_type_id'] = 'Valid Claim Type is required.';
if (empty($claim_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $claim_date)) $errors['claim_date'] = 'Valid Claim Date (YYYY-MM-DD) is required.';
if ($amount === null || $amount <= 0) $errors['amount'] = 'Valid Claim Amount (positive number) is required.';
if (empty($description)) $description = null;
// --- End Validation ---


// --- File Handling ---
$receipt_file_path_db = null;
$target_file_absolute_path = null;

if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] == UPLOAD_ERR_OK) {
    $file = $_FILES['receipt_file'];
    $original_filename = basename($file["name"]);
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $file_size = $file["size"];

    if (!in_array($file_extension, $allowed_extensions)) {
        $errors['receipt_file'] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions);
    } elseif ($file_size > $max_file_size) {
        $errors['receipt_file'] = 'File size exceeds the limit of ' . ($max_file_size / 1024 / 1024) . ' MB.';
    } else {
        $unique_filename = $employee_id . '_claim_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_file_absolute_path = $target_dir_absolute . $unique_filename;
        $receipt_file_path_db = $db_path_prefix . $unique_filename;

        if (!move_uploaded_file($file["tmp_name"], $target_file_absolute_path)) {
             error_log("Failed to move uploaded receipt file: " . $file["tmp_name"] . " to " . $target_file_absolute_path);
             $errors['receipt_file'] = 'Failed to save uploaded receipt on the server.';
             $receipt_file_path_db = null;
             $target_file_absolute_path = null;
        }
    }
} elseif (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $error_code = $_FILES['receipt_file']['error'];
    $errors['receipt_file'] = 'File upload error occurred (Code: ' . $error_code . ').';
    error_log("Receipt Upload Error Code: " . $error_code);
}
// --- End File Handling ---


// --- Final Check for Errors Before DB Insert ---
if (!empty($errors)) {
    if ($target_file_absolute_path && file_exists($target_file_absolute_path)) {
         unlink($target_file_absolute_path);
         error_log("Deleted orphaned receipt file due to validation errors: " . $target_file_absolute_path);
    }
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Final Check ---


// --- Insert into Database ---
try {
    $pdo->beginTransaction(); // Start transaction

    $sql = "INSERT INTO Claims (EmployeeID, ClaimTypeID, SubmissionDate, ClaimDate, Amount, Currency, Description, ReceiptPath, Status)
            VALUES (:employee_id, :claim_type_id, NOW(), :claim_date, :amount, :currency, :description, :receipt_path, :status)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':claim_type_id', $claim_type_id, PDO::PARAM_INT);
    $stmt->bindParam(':claim_date', $claim_date, PDO::PARAM_STR);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':currency', $currency, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':receipt_path', $receipt_file_path_db, PDO::PARAM_STR);
    $stmt->bindParam(':status', $initial_status, PDO::PARAM_STR);

    $stmt->execute();
    $new_claim_id = $pdo->lastInsertId();

    // --- Create Notification for System Admins ---
    if ($new_claim_id) {
        $adminUserIds = [];
        $stmt_admins = $pdo->query("SELECT UserID FROM Users WHERE RoleID = 1 AND IsActive = TRUE"); // RoleID 1 for System Admin
        while ($admin_row = $stmt_admins->fetch(PDO::FETCH_ASSOC)) {
            $adminUserIds[] = $admin_row['UserID'];
        }

        if (!empty($adminUserIds)) {
            // Get employee name for the notification message
            $employeeName = "Employee ID " . $employee_id; // Fallback
            $stmt_emp_name = $pdo->prepare("SELECT CONCAT(FirstName, ' ', LastName) AS FullName FROM Employees WHERE EmployeeID = :emp_id");
            $stmt_emp_name->bindParam(':emp_id', $employee_id, PDO::PARAM_INT);
            $stmt_emp_name->execute();
            $emp_name_row = $stmt_emp_name->fetch(PDO::FETCH_ASSOC);
            if ($emp_name_row) {
                $employeeName = $emp_name_row['FullName'];
            }

            $notificationMessage = "New claim (#{$new_claim_id}) submitted by {$employeeName} for " . htmlspecialchars($description ?: 'Claim') . " (Amount: {$amount} {$currency}).";
            $notificationLink = "#claims-approval"; // Link to the approval section
            $notificationType = "NEW_CLAIM_SUBMITTED";
            $senderUserId = null; // System action or originating employee's UserID if available

            // Get Sender UserID based on EmployeeID who submitted
            $stmt_sender_user = $pdo->prepare("SELECT UserID FROM Users WHERE EmployeeID = :employee_id_sender");
            $stmt_sender_user->bindParam(':employee_id_sender', $employee_id, PDO::PARAM_INT);
            $stmt_sender_user->execute();
            $sender_user_row = $stmt_sender_user->fetch(PDO::FETCH_ASSOC);
            if ($sender_user_row) {
                $senderUserId = $sender_user_row['UserID'];
            }


            $sql_notify = "INSERT INTO Notifications (UserID, SenderUserID, NotificationType, Message, Link, IsRead, CreatedAt)
                           VALUES (:recipient_user_id, :sender_user_id, :type, :message, :link, FALSE, NOW())";
            $stmt_notify = $pdo->prepare($sql_notify);

            foreach ($adminUserIds as $adminUserId) {
                $stmt_notify->execute([
                    ':recipient_user_id' => $adminUserId,
                    ':sender_user_id' => $senderUserId,
                    ':type' => $notificationType,
                    ':message' => $notificationMessage,
                    ':link' => $notificationLink
                ]);
            }
        }
    }
    // --- End Notification Creation ---

    $pdo->commit(); // Commit transaction

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Claim submitted successfully with status: ' . $initial_status,
        'claim_id' => $new_claim_id
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("API Error (submit_claim - DB Insert/Notify): " . $e->getMessage());
    if ($target_file_absolute_path && file_exists($target_file_absolute_path)) {
        unlink($target_file_absolute_path);
        error_log("Deleted orphaned receipt file after DB error: " . $target_file_absolute_path);
    }
    if ($e->getCode() == '23000') {
         http_response_code(400);
         echo json_encode(['error' => 'Failed to submit claim. Ensure Employee and Claim Type IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save claim details to database.']);
    }
} catch (Throwable $e) { // Catch any other general errors
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("API Error (submit_claim - General): " . $e->getMessage());
    if ($target_file_absolute_path && file_exists($target_file_absolute_path)) {
        unlink($target_file_absolute_path);
    }
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected server error occurred.']);
}
// --- End Insert into Database ---
?>
