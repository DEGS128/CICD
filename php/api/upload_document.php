<?php
// --- Error Reporting for Debugging ---
// Log all errors, but don't display them to the user (prevents HTML output)
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 = Off for production, 1 = On for debugging *if needed*
ini_set('log_errors', 1);
// Optional: Specify a custom log file
// ini_set('error_log', '/path/to/your/php-error.log');
// --- End Error Reporting ---

// --- Set Headers EARLY ---
// Set the header to indicate JSON content type FIRST
header('Content-Type: application/json');
// Allow requests from any origin (for development - restrict in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Allow POST and OPTIONS (for preflight)
header('Access-Control-Allow-Headers: Content-Type'); // Allow Content-Type header

// Handle preflight OPTIONS request (sent by browsers for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
// Include the database connection script *after* setting initial headers
// Use require to cause a fatal error if the file is missing, which helps debugging
try {
    require_once '../db_connect.php';
} catch (Throwable $e) { // Catch potential parse errors or other issues in db_connect
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    // Ensure JSON output even for this critical failure
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}
// --- End Database Connection ---


// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required.']);
    exit;
}


// --- Configuration ---
// Define the target directory for uploads (relative to this script's location)
$target_dir_relative = "../../uploads/documents/"; // Go up two levels from php/api/, then into uploads/documents/
$target_dir_absolute = realpath(__DIR__ . '/' . $target_dir_relative); // Use __DIR__ for reliability

// Check if the absolute path was resolved and the directory exists
if ($target_dir_absolute === false || !is_dir($target_dir_absolute)) {
     $resolved_path_debug = realpath(__DIR__ . '/' . $target_dir_relative); // Get path resolution attempt
     error_log("Upload directory check failed. Relative path: '{$target_dir_relative}'. Attempted absolute path: '{$resolved_path_debug}'. Directory does not exist or path is invalid.");
     http_response_code(500);
     echo json_encode(['error' => 'Server configuration error: Upload directory not found.']);
     exit;
}
// Check if the directory is writable by the web server user
if (!is_writable($target_dir_absolute)) {
    error_log("Upload directory is not writable: " . $target_dir_absolute);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Upload directory not writable. Please check permissions.']);
    exit;
}
// Add DIRECTORY_SEPARATOR at the end for consistency
$target_dir_absolute .= DIRECTORY_SEPARATOR;


// Allowed file extensions and max size (e.g., 5MB)
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$max_file_size = 5 * 1024 * 1024; // 5 MB in bytes
// --- End Configuration ---


// --- Get Data from POST Request ---
$employee_id = isset($_POST['employee_id']) ? filter_var($_POST['employee_id'], FILTER_VALIDATE_INT) : null;
$document_type = isset($_POST['document_type']) ? trim(htmlspecialchars($_POST['document_type'])) : null;

// Validate input
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Valid Employee ID is required.']);
    exit;
}
if (empty($document_type)) {
    http_response_code(400);
    echo json_encode(['error' => 'Document Type is required.']);
    exit;
}
if (!isset($_FILES['document_file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file field named "document_file" received.']);
    exit;
}
if ($_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    // Handle different upload errors
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive specified in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server configuration error: Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];
    $error_code = $_FILES['document_file']['error'];
    $error_message = $upload_errors[$error_code] ?? 'Unknown upload error.';
    error_log("File Upload Error Code: " . $error_code . " - " . $error_message); // Log the specific error

    http_response_code(400);
    echo json_encode(['error' => $error_message]);
    exit;
}
// --- End Get Data ---


// --- File Validation ---
$file = $_FILES['document_file'];
$original_filename = basename($file["name"]);
$file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
$file_size = $file["size"];

// Check file extension
if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)]);
    exit;
}

// Check file size
if ($file_size > $max_file_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds the limit of ' . ($max_file_size / 1024 / 1024) . ' MB.']);
    exit;
}
// --- End File Validation ---


// --- Generate Unique Filename & Move File ---
// Sanitize original filename (optional but recommended)
$safe_original_filename = preg_replace("/[^a-zA-Z0-9\.\-\_]/", "_", $original_filename);
// Create a unique name to prevent overwriting and add employee ID for organization
$unique_filename = $employee_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$target_file_absolute = $target_dir_absolute . $unique_filename;
// Store the relative path *from the web root* for the database (adjust if needed)
// Assuming 'uploads' is directly under the web root accessible via URL
$db_file_path = "uploads/documents/" . $unique_filename;


if (!move_uploaded_file($file["tmp_name"], $target_file_absolute)) {
    error_log("Failed to move uploaded file from temporary location: " . $file["tmp_name"] . " to target: " . $target_file_absolute . ". Check permissions and paths.");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file on the server.']);
    exit;
}
// --- End Move File ---


// --- Insert into Database ---
try {
    $sql = "INSERT INTO EmployeeDocuments (EmployeeID, DocumentType, DocumentName, FilePath)
            VALUES (:employee_id, :document_type, :document_name, :file_path)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':document_type', $document_type, PDO::PARAM_STR);
    $stmt->bindParam(':document_name', $safe_original_filename, PDO::PARAM_STR); // Store the original (sanitized) name
    $stmt->bindParam(':file_path', $db_file_path, PDO::PARAM_STR); // Store the relative web path

    $stmt->execute();
    $new_document_id = $pdo->lastInsertId(); // Get the ID of the newly inserted document

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Document uploaded successfully.',
        'document_id' => $new_document_id,
        'file_path' => $db_file_path,
        'original_name' => $safe_original_filename
    ]);

} catch (\PDOException $e) {
    error_log("API Error (upload_document - DB Insert): " . $e->getMessage() . " SQL: " . $sql); // Log SQL too
    // Attempt to delete the uploaded file if DB insert fails to prevent orphans
    if (file_exists($target_file_absolute)) {
        unlink($target_file_absolute);
        error_log("Deleted orphaned file after DB error: " . $target_file_absolute);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save document details to database.']);
}
// --- End Insert into Database ---
?>
