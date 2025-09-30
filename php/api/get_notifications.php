<?php
/**
 * API Endpoint: Get Notifications
 * Retrieves notifications for the currently logged-in user.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: 0, Development: 1
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Set a specific log file path if needed

session_start(); // Needed for authentication

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // IMPORTANT: For production, restrict this.
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
        throw new Exception('Database connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_notifications.php (db_connect include): " . $e->getMessage());
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

// --- Fetch Notifications ---
try {
    // Fetch a limited number of notifications, prioritizing unread ones.
    // You can adjust the LIMIT as needed.
    $limit = 15; // Number of notifications to fetch

    $sql = "SELECT
                NotificationID,
                UserID,
                SenderUserID,
                (SELECT CONCAT(s_e.FirstName, ' ', s_e.LastName) FROM Users s_u JOIN Employees s_e ON s_u.EmployeeID = s_e.EmployeeID WHERE s_u.UserID = n.SenderUserID) AS SenderName,
                NotificationType,
                Message,
                Link,
                IsRead,
                CreatedAt
            FROM
                Notifications n
            WHERE
                n.UserID = :user_id
            ORDER BY
                n.IsRead ASC, n.CreatedAt DESC -- Show unread first, then by newest
            LIMIT :limit_val";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
    $stmt->bindParam(':limit_val', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optionally, get the total count of unread notifications for the badge
    $sql_unread_count = "SELECT COUNT(*) FROM Notifications WHERE UserID = :user_id AND IsRead = FALSE";
    $stmt_unread_count = $pdo->prepare($sql_unread_count);
    $stmt_unread_count->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
    $stmt_unread_count->execute();
    $unreadCount = (int) $stmt_unread_count->fetchColumn();

    // Format dates for better display
    foreach ($notifications as &$notification) {
        if (!empty($notification['CreatedAt'])) {
            // Create a DateTime object for the notification creation time
            $createdAt = new DateTime($notification['CreatedAt'], new DateTimeZone('UTC')); // Assuming CreatedAt is UTC
            // Convert to local timezone (e.g., Asia/Manila for Philippines)
            $createdAt->setTimezone(new DateTimeZone('Asia/Manila')); // Adjust to your server/user timezone

            // Calculate time ago
            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $interval = $now->diff($createdAt);

            if ($interval->y > 0) {
                $notification['TimeAgo'] = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
            } elseif ($interval->m > 0) {
                $notification['TimeAgo'] = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
            } elseif ($interval->d > 0) {
                $notification['TimeAgo'] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
            } elseif ($interval->h > 0) {
                $notification['TimeAgo'] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
            } elseif ($interval->i > 0) {
                $notification['TimeAgo'] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
            } else {
                $notification['TimeAgo'] = 'Just now';
            }
            $notification['CreatedAtFormatted'] = $createdAt->format('M d, Y h:i A');
        }
    }
    unset($notification);


    http_response_code(200);
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_notifications.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error retrieving notifications.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_notifications.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving notifications.']);
}
exit;
?>
