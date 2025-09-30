<?php
/**
 * API Endpoint: Mark Notification(s) as Read
 * Updates the IsRead status of specified notifications for the logged-in user.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: 0, Development: 1
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Needed for authentication

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // IMPORTANT: For production, restrict this.
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Use POST for state changes
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
        throw new Exception('DB connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in mark_notification_read.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$loggedInUserId = $_SESSION['user_id'];
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
    echo json_encode(['error' => 'Invalid JSON payload received. Error: ' . json_last_error_msg()]);
    exit;
}

// --- Extract Notification ID(s) ---
$notification_id = isset($input_data['notification_id']) ? filter_var($input_data['notification_id'], FILTER_VALIDATE_INT) : null;
$notification_ids_array = isset($input_data['notification_ids']) && is_array($input_data['notification_ids']) ? $input_data['notification_ids'] : null;

if (empty($notification_id) && empty($notification_ids_array)) {
    http_response_code(400);
    echo json_encode(['error' => 'No notification ID(s) provided to mark as read.']);
    exit;
}

// --- Update Notifications ---
try {
    $pdo->beginTransaction();
    $updated_count = 0;

    if (!empty($notification_id)) { // Single notification ID
        $sql = "UPDATE Notifications SET IsRead = TRUE
                WHERE NotificationID = :notification_id AND UserID = :user_id AND IsRead = FALSE";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
        $stmt->execute();
        $updated_count += $stmt->rowCount();
    } elseif (!empty($notification_ids_array)) { // Array of notification IDs
        // Sanitize each ID in the array
        $sanitized_ids = [];
        foreach ($notification_ids_array as $id) {
            $filtered_id = filter_var($id, FILTER_VALIDATE_INT);
            if ($filtered_id && $filtered_id > 0) {
                $sanitized_ids[] = $filtered_id;
            }
        }

        if (!empty($sanitized_ids)) {
            // Create placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
            $sql = "UPDATE Notifications SET IsRead = TRUE
                    WHERE NotificationID IN ($placeholders) AND UserID = ? AND IsRead = FALSE";
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $param_index = 1;
            foreach ($sanitized_ids as $id_to_bind) {
                $stmt->bindValue($param_index++, $id_to_bind, PDO::PARAM_INT);
            }
            $stmt->bindValue($param_index, $loggedInUserId, PDO::PARAM_INT);

            $stmt->execute();
            $updated_count += $stmt->rowCount();
        }
    }

    $pdo->commit();

    if ($updated_count > 0) {
        http_response_code(200);
        echo json_encode(['message' => "{$updated_count} notification(s) marked as read."]);
    } else {
        http_response_code(200); // Still OK, just nothing was updated (maybe already read or invalid IDs for user)
        echo json_encode(['message' => 'No notifications were updated (they may have already been read or do not belong to this user).']);
    }

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("PHP PDOException in mark_notification_read.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error marking notification(s) as read.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("PHP Throwable in mark_notification_read.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error marking notification(s) as read.']);
}
exit;
?>
