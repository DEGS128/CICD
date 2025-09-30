<?php
/**
 * Authentication Routes
 * Handles login, logout, 2FA, and password reset
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController {
    private $pdo;
    private $authMiddleware;
    private $userModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->userModel = new User();
    }

    /**
     * User login
     */
    public function login() {
        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['username', 'password']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $username = $request->sanitizeString($data['username']);
        $password = $data['password'];

        try {
            // Get user details
            $user = $this->userModel->getUserByUsername($username);
            
            if (!$user || !$user['IsActive']) {
                Response::unauthorized('Invalid username or password');
            }

            // Verify password
            if (!password_verify($password, $user['PasswordHash'])) {
                Response::unauthorized('Invalid username or password');
            }

            // Check if 2FA is enabled
            if ($user['IsTwoFactorEnabled']) {
                $this->handle2FA($user);
                return;
            }

            // Generate JWT token
            $token = $this->authMiddleware->generateToken(
                $user['UserID'],
                $user['EmployeeID'],
                $user['Username'],
                $user['RoleID'],
                $user['RoleName']
            );

            // Update last login
            $this->userModel->updateLastLogin($user['UserID']);

            Response::success([
                'token' => $token,
                'user' => [
                    'id' => $user['UserID'],
                    'employee_id' => $user['EmployeeID'],
                    'username' => $user['Username'],
                    'role' => $user['RoleName'],
                    'full_name' => $user['FirstName'] . ' ' . $user['LastName']
                ]
            ], 'Login successful');

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            Response::error('Login failed', 500);
        }
    }

    /**
     * Handle 2FA process
     */
    private function handle2FA($user) {
        if (empty($user['Email'])) {
            Response::error('2FA enabled but no email address found', 500);
        }

        // Generate 2FA code
        $code = sprintf("%06d", random_int(100000, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        try {
            // Store code in database
            $this->userModel->store2FACode($user['UserID'], $code, $expiry);

            // Send email
            if ($this->send2FAEmail($user['Email'], $code, $user['Username'])) {
                Response::success([
                    'two_factor_required' => true,
                    'user_id' => $user['UserID'],
                    'email' => $user['Email']
                ], '2FA code sent to your email');
            } else {
                Response::error('Failed to send 2FA code', 500);
            }

        } catch (Exception $e) {
            error_log("2FA error: " . $e->getMessage());
            Response::error('2FA process failed', 500);
        }
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA() {
        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['user_id', 'code']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $userId = (int)$data['user_id'];
        $code = $data['code'];

        try {
            $user = $this->userModel->verify2FACode($userId, $code);
            
            if (!$user) {
                Response::unauthorized('Invalid or expired 2FA code');
            }

            // Generate JWT token
            $token = $this->authMiddleware->generateToken(
                $user['UserID'],
                $user['EmployeeID'],
                $user['Username'],
                $user['RoleID'],
                $user['RoleName']
            );

            // Update last login
            $this->userModel->updateLastLogin($user['UserID']);

            Response::success([
                'token' => $token,
                'user' => [
                    'id' => $user['UserID'],
                    'employee_id' => $user['EmployeeID'],
                    'username' => $user['Username'],
                    'role' => $user['RoleName'],
                    'full_name' => $user['FirstName'] . ' ' . $user['LastName']
                ]
            ], '2FA verification successful');

        } catch (Exception $e) {
            error_log("2FA verification error: " . $e->getMessage());
            Response::error('2FA verification failed', 500);
        }
    }

    /**
     * User logout
     */
    public function logout() {
        // In a stateless JWT system, logout is handled client-side
        // by removing the token. We can optionally maintain a blacklist
        // of tokens, but for simplicity, we'll just return success.
        Response::success(null, 'Logout successful');
    }

    /**
     * Reset password
     */
    public function resetPassword() {
        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['email']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $email = $data['email'];
        if (!$request->validateEmail($email)) {
            Response::validationError(['email' => 'Invalid email format']);
        }

        try {
            $user = $this->userModel->getUserByEmail($email);
            
            if (!$user) {
                // Don't reveal if email exists or not
                Response::success(null, 'If the email exists, a reset link has been sent');
            }

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token
            $this->userModel->storeResetToken($user['UserID'], $resetToken, $expiry);

            // Send reset email
            if ($this->sendResetEmail($email, $resetToken, $user['Username'])) {
                Response::success(null, 'Password reset link sent to your email');
            } else {
                Response::error('Failed to send reset email', 500);
            }

        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            Response::error('Password reset failed', 500);
        }
    }

    /**
     * Send 2FA email
     */
    private function send2FAEmail($email, $code, $username) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer not available");
            return false;
        }

        $gmailUser = getenv('GMAIL_USER');
        $gmailPass = getenv('GMAIL_APP_PASSWORD');

        if (empty($gmailUser) || empty($gmailPass)) {
            error_log("Gmail credentials not configured");
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailUser;
            $mail->Password = $gmailPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom($gmailUser, 'Hospital HR System');
            $mail->addAddress($email);
            $mail->isHTML(false);
            $mail->Subject = 'Your 2FA Code';
            $mail->Body = "Hello $username,\n\nYour 2FA code is: $code\n\nThis code expires in 10 minutes.";

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email
     */
    private function sendResetEmail($email, $token, $username) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return false;
        }

        $gmailUser = getenv('GMAIL_USER');
        $gmailPass = getenv('GMAIL_APP_PASSWORD');

        if (empty($gmailUser) || empty($gmailPass)) {
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailUser;
            $mail->Password = $gmailPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $resetUrl = getenv('APP_URL') . '/reset-password?token=' . $token;

            $mail->setFrom($gmailUser, 'Hospital HR System');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "
                <h2>Password Reset Request</h2>
                <p>Hello $username,</p>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <p><a href='$resetUrl'>Reset Password</a></p>
                <p>This link expires in 1 hour.</p>
            ";

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Reset email error: " . $e->getMessage());
            return false;
        }
    }
}

