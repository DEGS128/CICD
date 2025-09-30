<?php
/**
 * API Endpoint: Login
 * Handles user authentication.
 * Incorporates step 1 of Email 2FA using PHPMailer via Gmail SMTP.
 * v1.4 - Integrated PHPMailer.
 */

// --- PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Start session early

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Composer Autoloader ---
// Adjust the path based on your project structure and vendor directory location
$pathToVendor = __DIR__ . '/../../vendor/autoload.php'; // Assumes vendor is two levels up from api folder
if (file_exists($pathToVendor)) {
    require $pathToVendor;
} else {
    error_log("Login API Notice: PHPMailer vendor/autoload.php not found at " . $pathToVendor . ". Email functionality will be disabled.");
    // Don't exit - allow login to continue without email functionality
}
// --- End Composer Autoloader ---


// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection object not created.');
    }
} catch (Throwable $e) {
    error_log("Login API Error (DB Connection): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Email Sending Function (using PHPMailer) ---
/**
 * Sends the 2FA code using PHPMailer and Gmail SMTP.
 * Reads credentials from environment variables.
 *
 * @param string $recipientEmail The recipient's email address.
 * @param string $code The 2FA code to send.
 * @param string $username The user's username (for email content).
 * @return bool True on success, false on failure.
 */
function send_2fa_email_code_phpmailer(string $recipientEmail, string $code, string $username): bool {
    // Check if PHPMailer classes are available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("send_2fa_email_code_phpmailer: PHPMailer classes not available. Email functionality disabled.");
        return false; // Cannot send email without PHPMailer
    }
    
    // --- Get Credentials from Environment Variables ---
    $gmailUser = getenv('GMAIL_USER');
    $gmailAppPassword = getenv('GMAIL_APP_PASSWORD');

    if (empty($gmailUser) || empty($gmailAppPassword)) {
        error_log("send_2fa_email_code_phpmailer: GMAIL_USER or GMAIL_APP_PASSWORD environment variables not set.");
        return false; // Cannot send email without credentials
    }
    // --- End Get Credentials ---


    $mail = new PHPMailer(true); // Passing `true` enables exceptions

    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // Disable verbose debug output (set to 2 for testing)
        $mail->isSMTP();                           // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';      // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = $gmailUser;            // SMTP username (your Gmail address)
        $mail->Password   = $gmailAppPassword;     // SMTP password (your App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
        $mail->Port       = 465;                   // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        // Recipients
        $mail->setFrom($gmailUser, 'hospital HR System'); // Sender Email and Name
        $mail->addAddress($recipientEmail, 'johnpaulaustria@gmailc.com');          // Add a recipient

        // Content
        $mail->isHTML(false); // Set email format to plain text
        $mail->Subject = 'Your hospital HR System Login Code';
        $mail->Body    = "Hello " . htmlspecialchars($username) . ",\n\n" .
                         "Your two-factor authentication code is: " . $code . "\n\n" .
                         "This code will expire in 10 minutes.\n\n" .
                         "If you did not request this code, please ignore this email or contact support.";
        $mail->AltBody = $mail->Body; // Simple plain text alternative body

        $mail->send();
        error_log("2FA Email Sent successfully to: " . $recipientEmail);
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: Message could not be sent. Mailer Error: {$mail->ErrorInfo}. Exception: {$e->getMessage()}");
        return false;
    }
}
// --- End Email Sending Function ---

/**
 * Function to validate user account against database schema
 * Ensures user has valid employee and role associations
 * @param PDO $pdo The database connection object
 * @param array $user User data from database
 * @return bool True if user is valid, false otherwise
 */
function validateUserSchema($pdo, $user) {
    try {
        // Check if user has associated employee data
        if (empty($user['EmployeeID']) || $user['EmployeeID'] <= 0) {
            error_log("Schema validation failed: User {$user['UserID']} has invalid EmployeeID.");
            return false;
        }

        // Verify employee exists and is active
        $stmt = $pdo->prepare("SELECT Status FROM Employees WHERE EmployeeID = :employee_id");
        $stmt->bindParam(':employee_id', $user['EmployeeID'], PDO::PARAM_INT);
        $stmt->execute();
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee || $employee['Status'] !== 'Active') {
            error_log("Schema validation failed: Employee {$user['EmployeeID']} is not active or does not exist.");
            return false;
        }

        // Check role association
        if (empty($user['RoleID']) || $user['RoleID'] <= 0) {
            error_log("Schema validation failed: User {$user['UserID']} has invalid RoleID.");
            return false;
        }

        error_log("User schema validation passed for UserID {$user['UserID']}.");
        return true;
    } catch (PDOException $e) {
        error_log("Schema validation error: " . $e->getMessage());
        return false;
    }
}

// --- Login Logic ---
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

$username = isset($input_data['username']) ? trim($input_data['username']) : null;
$password = isset($input_data['password']) ? $input_data['password'] : null;

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

try {
    // Fetch user details including 2FA status and employee email
    $sql = "SELECT
                u.UserID, u.EmployeeID, u.Username, u.PasswordHash, u.RoleID, u.IsActive,
                u.IsTwoFactorEnabled, -- Added 2FA flag
                r.RoleName,
                e.FirstName, e.LastName, e.Email AS EmployeeEmail -- Added Employee Email
            FROM Users u
            JOIN Roles r ON u.RoleID = r.RoleID
            JOIN Employees e ON u.EmployeeID = e.EmployeeID
            WHERE u.Username = :username";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['IsActive']) {
        // User not found or inactive - return error
        error_log("Login failed: User '{$username}' not found or inactive.");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password.']);
        exit;
    }

    // Verify password for real users
    $trimmedHash = trim($user['PasswordHash']);
    if (!password_verify($password, $trimmedHash)) {
        error_log("Login failed: Password verification failed for '{$username}'.");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password.']);
        exit;
    }

    // --- 2FA Check ---
    if ($user['IsTwoFactorEnabled']) {
        // 2FA is enabled for this user
        if (empty($user['EmployeeEmail'])) {
            error_log("2FA Error: UserID {$user['UserID']} has 2FA enabled but no email address in Employees table.");
            http_response_code(500);
            echo json_encode(['error' => 'Two-factor authentication cannot proceed. Please contact support (email missing).']);
            exit;
        }

        // Generate 2FA code
        $two_factor_code = sprintf("%06d", random_int(100000, 999999)); // 6-digit code
        $expiry_time = new DateTime('+10 minutes'); // Code expires in 10 minutes
        $expiry_timestamp = $expiry_time->format('Y-m-d H:i:s');

        // Store code and expiry in the database
        $sql_update_2fa = "UPDATE Users
                           SET TwoFactorEmailCode = :code,
                               TwoFactorCodeExpiry = :expiry
                           WHERE UserID = :user_id";
        $stmt_update_2fa = $pdo->prepare($sql_update_2fa);
        $stmt_update_2fa->bindParam(':code', $two_factor_code, PDO::PARAM_STR);
        $stmt_update_2fa->bindParam(':expiry', $expiry_timestamp, PDO::PARAM_STR);
        $stmt_update_2fa->bindParam(':user_id', $user['UserID'], PDO::PARAM_INT);

        if (!$stmt_update_2fa->execute()) {
            error_log("2FA DB Error: Failed to store 2FA code for UserID {$user['UserID']}.");
            http_response_code(500);
            echo json_encode(['error' => 'Failed to initiate two-factor authentication process.']);
            exit;
        }

        // Send the code via email using PHPMailer function
        if (!send_2fa_email_code_phpmailer($user['EmployeeEmail'], $two_factor_code, $user['Username'])) {
            error_log("2FA Email Error: Failed to send 2FA code email to {$user['EmployeeEmail']} for UserID {$user['UserID']} using PHPMailer.");
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send two-factor authentication code via email.']);
            exit;
        }

        // Return response indicating 2FA is required
        http_response_code(200); // OK, but login is not complete yet
        echo json_encode([
            'two_factor_required' => true,
            'message' => 'Two-factor authentication required. Please check your email (' . htmlspecialchars($user['EmployeeEmail']) . ') for the code.',
            'user_id_temp' => $user['UserID'] // Send UserID temporarily
        ]);
        exit;

    } else {
        // 2FA is NOT enabled - Proceed with normal login
        session_regenerate_id(true); // Regenerate session ID for security

        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['employee_id'] = $user['EmployeeID'];
        $_SESSION['username'] = $user['Username'];
        $_SESSION['role_id'] = $user['RoleID'];
        $_SESSION['role_name'] = $user['RoleName'];
        $_SESSION['full_name'] = $user['FirstName'] . ' ' . $user['LastName'];

        // --- Fetch and store HMO enrollment data in session ---
        try {
            $hmoSql = "SELECT
                        eh.EnrollmentID,
                        eh.PlanID,
                        eh.Status as EnrollmentStatus,
                        eh.MonthlyDeduction,
                        eh.EnrollmentDate,
                        eh.EffectiveDate,
                        hp.PlanName,
                        hpr.ProviderName
                       FROM EmployeeHMOEnrollments eh
                       LEFT JOIN HMOPlans hp ON eh.PlanID = hp.PlanID
                       LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
                       WHERE eh.EmployeeID = :employee_id AND eh.Status = 'Active'
                       ORDER BY eh.EffectiveDate DESC LIMIT 1";

            $hmoStmt = $pdo->prepare($hmoSql);
            $hmoStmt->bindParam(':employee_id', $user['EmployeeID'], PDO::PARAM_INT);
            $hmoStmt->execute();
            $hmoEnrollment = $hmoStmt->fetch(PDO::FETCH_ASSOC);

            if ($hmoEnrollment) {
                $_SESSION['hmo_enrollment'] = [
                    'enrollment_id' => $hmoEnrollment['EnrollmentID'],
                    'plan_id' => $hmoEnrollment['PlanID'],
                    'plan_name' => $hmoEnrollment['PlanName'],
                    'provider_name' => $hmoEnrollment['ProviderName'],
                    'status' => $hmoEnrollment['EnrollmentStatus'],
                    'monthly_deduction' => $hmoEnrollment['MonthlyDeduction'],
                    'enrollment_date' => $hmoEnrollment['EnrollmentDate'],
                    'effective_date' => $hmoEnrollment['EffectiveDate']
                ];
            } else {
                $_SESSION['hmo_enrollment'] = null; // No active HMO enrollment
            }
        } catch (PDOException $e) {
            error_log("HMO Session Data Error: " . $e->getMessage());
            $_SESSION['hmo_enrollment'] = null; // Set to null on error
        }
        // --- End HMO session data ---

        // Determine redirect URL based on role
        $redirect_url = 'index.php'; // Default redirect
        if ($user['RoleName'] === 'System Admin') {
            $redirect_url = 'admin_landing.php';
        } elseif ($user['RoleName'] === 'Employee') {
            $redirect_url = 'employee_landing.php';
        }

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful.',
            'two_factor_required' => false, // Indicate 2FA was not needed
            'redirect_url' => $redirect_url,
            'user' => [ // Send user details for UI update
                'user_id' => $user['UserID'],
                'employee_id' => $user['EmployeeID'],
                'username' => $user['Username'],
                'full_name' => $_SESSION['full_name'],
                'role_name' => $user['RoleName']
            ]
        ]);
        exit;
    }
    // --- End 2FA Check ---

} catch (\PDOException $e) {
    error_log("Login API Error (DB Query/Verify): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred during login. Please try again.']);
    exit;
} catch (\Exception $e) { // Catch exceptions from random_int or DateTime
     error_log("Login API Error (Code Generation/Date/PHPMailer): " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An internal error occurred during the login process.']);
     exit;
} catch (Throwable $e) {
    error_log("Login API Error (General): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected server error occurred.']);
    exit;
}
?>