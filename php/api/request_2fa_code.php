<?php
/**
 * API Endpoint: Request 2FA Code
 * Generates and sends a 2FA code to the logged-in user's email for a specific context (e.g., password change).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../../php-error.log'); // Ensure this path is writable

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

// --- Composer Autoloader for PHPMailer ---
$pathToVendor = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($pathToVendor)) {
    require $pathToVendor;
} else {
    error_log("Request 2FA Code API Error: PHPMailer vendor/autoload.php not found at " . $pathToVendor);
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Email library components missing.']);
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
    error_log("Request 2FA Code API Error (DB Connection): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error. Could not connect to database.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$loggedInUserId = $_SESSION['user_id'];

// --- Get Input Data ---
$input_data = json_decode(file_get_contents('php://input'), true);
$context = isset($input_data['context']) ? trim(htmlspecialchars($input_data['context'])) : null;

if ($context !== 'password_change') { // Only support this context for now
    http_response_code(400);
    echo json_encode(['error' => 'Invalid context for 2FA code request.']);
    exit;
}

// --- Process Request ---
try {
    // Fetch user details: IsTwoFactorEnabled, EmployeeEmail
    $sql_user = "SELECT u.IsTwoFactorEnabled, e.Email AS EmployeeEmail, e.FirstName
                 FROM Users u
                 JOIN Employees e ON u.EmployeeID = e.EmployeeID
                 WHERE u.UserID = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit;
    }

    if (!$user_info['IsTwoFactorEnabled']) {
        http_response_code(400);
        echo json_encode(['error' => '2FA is not enabled for this account. No code sent.']);
        exit;
    }

    if (empty($user_info['EmployeeEmail'])) {
        error_log("Request 2FA Code Error: UserID {$loggedInUserId} has 2FA enabled but no email address in Employees table.");
        http_response_code(500);
        echo json_encode(['error' => 'Cannot send 2FA code. Your email address is missing. Please contact support.']);
        exit;
    }

    // Generate 2FA code
    $two_factor_code = sprintf("%06d", random_int(100000, 999999));
    $expiry_time = new DateTime('+10 minutes'); // Code expires in 10 minutes
    $expiry_timestamp = $expiry_time->format('Y-m-d H:i:s');

    // Store code and expiry in the database
    $sql_update_2fa = "UPDATE Users
                       SET TwoFactorEmailCode = :code, TwoFactorCodeExpiry = :expiry
                       WHERE UserID = :user_id";
    $stmt_update_2fa = $pdo->prepare($sql_update_2fa);
    $stmt_update_2fa->bindParam(':code', $two_factor_code, PDO::PARAM_STR);
    $stmt_update_2fa->bindParam(':expiry', $expiry_timestamp, PDO::PARAM_STR);
    $stmt_update_2fa->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);

    if (!$stmt_update_2fa->execute()) {
        error_log("Request 2FA Code DB Error: Failed to store 2FA code for UserID {$loggedInUserId}.");
        throw new Exception('Failed to update 2FA code in database.');
    }

    // --- Send Email with PHPMailer ---
    $mail = new PHPMailer(true);
    $emailSent = false;
    try {
        // Gmail SMTP Configuration - GET FROM ENVIRONMENT VARIABLES
        $gmailUser = getenv('GMAIL_USER');
        $gmailAppPassword = getenv('GMAIL_APP_PASSWORD');

        if (empty($gmailUser) || empty($gmailAppPassword)) {
            error_log("PHPMailer GMAIL_USER or GMAIL_APP_PASSWORD environment variables not set.");
            throw new Exception('Email server configuration error.');
        }

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $gmailAppPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom($gmailUser, 'Avalon HR System');
        $mail->addAddress($user_info['EmployeeEmail'], $user_info['FirstName']);

        $mail->isHTML(true);
        $mail->Subject = 'Your Avalon HR Password Change Verification Code';
        $mail->Body    = "Hello {$user_info['FirstName']},<br><br>" .
                         "Your verification code to change your password is: <b>{$two_factor_code}</b><br><br>" .
                         "This code will expire in 10 minutes.<br><br>" .
                         "If you did not request this, please secure your account or contact support immediately.";
        $mail->AltBody = "Hello {$user_info['FirstName']},\n\nYour verification code to change your password is: {$two_factor_code}\n\nThis code will expire in 10 minutes.\n\nIf you did not request this, please secure your account or contact support immediately.";

        $mail->send();
        $emailSent = true;
    } catch (Exception $e) {
        error_log("PHPMailer Error sending 2FA code for password change to {$user_info['EmployeeEmail']} (UserID: {$loggedInUserId}): {$mail->ErrorInfo}. Exception: {$e->getMessage()}");
        // Don't throw to client, but log it. The main response will indicate failure.
    }
    // --- End Send Email ---

    if ($emailSent) {
        http_response_code(200);
        echo json_encode(['message' => 'A 2FA code has been sent to your email address.']);
    } else {
        // If email sending failed, we should ideally roll back the DB update of the code,
        // or at least inform the user that the code was generated but not sent.
        // For simplicity here, we'll just return a generic error.
        throw new Exception('Failed to send 2FA code email. Please try again or contact support.');
    }

} catch (Exception $e) { // Catches custom exceptions and PHPMailer exceptions
    error_log("Request 2FA Code API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) { // Catches generic Throwables
    error_log("Request 2FA Code API Throwable: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected server error occurred while requesting 2FA code.']);
}
exit;
?>
