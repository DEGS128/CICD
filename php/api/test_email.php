<?php
// test_email.php

// --- PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Error Reporting ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // Display errors for this test script
ini_set('log_errors', 1);
// --- End Error Reporting ---

// --- Composer Autoloader ---
// Adjust the path based on your project structure.
// This assumes test_email.php is in php/api/ and vendor/ is in the project root (hr34/).
$pathToVendor = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($pathToVendor)) {
    require $pathToVendor;
} else {
    die("ERROR: PHPMailer vendor/autoload.php not found at '{$pathToVendor}'. Please run 'composer install'.");
}
// --- End Composer Autoloader ---

echo "<h1>PHPMailer Gmail SMTP Test</h1>";

// --- Get Credentials from Environment Variables ---
// IMPORTANT: Ensure these environment variables are set on your server.
$gmailUser = getenv('GMAIL_USER');
$gmailAppPassword = getenv('GMAIL_APP_PASSWORD');

if (empty($gmailUser) || empty($gmailAppPassword)) {
    die("<p style='color:red;'>ERROR: GMAIL_USER or GMAIL_APP_PASSWORD environment variables are not set. Cannot send email.</p>");
}
echo "<p>Attempting to use Gmail User: " . htmlspecialchars($gmailUser) . "</p>";
// --- End Get Credentials ---

// --- Recipient Email for Testing ---
// Updated to the user's provided email address
$recipientTestEmail = "taba.136541091035@depedqc.ph";
// --- End Recipient ---


$mail = new PHPMailer(true); // Passing `true` enables exceptions

try {
    // Server settings
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for detailed error messages
    $mail->SMTPDebug = SMTP::DEBUG_OFF;    // Disable debug output for cleaner test
    $mail->isSMTP();                       // Send using SMTP
    $mail->Host       = 'smtp.gmail.com';  // Set the SMTP server to send through
    $mail->SMTPAuth   = true;              // Enable SMTP authentication
    $mail->Username   = $gmailUser;        // SMTP username (your Gmail address)
    $mail->Password   = $gmailAppPassword; // SMTP password (your App Password)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
    $mail->Port       = 465;               // TCP port to connect to for SMTPS

    // Recipients
    $mail->setFrom($gmailUser, 'Avalon HR Test Email'); // Sender Email and Name
    $mail->addAddress($recipientTestEmail, 'Test Recipient');     // Add a recipient

    // Content
    $mail->isHTML(true); // Set email format to HTML
    $mail->Subject = 'PHPMailer Test Email from Avalon HR System';
    $testCode = rand(100000, 999999);
    $mail->Body    = "This is a test email sent from the Avalon HR System using PHPMailer.<br>" .
                     "If you received this, your email configuration is working!<br><br>" .
                     "Test Code: <b>{$testCode}</b>";
    $mail->AltBody = "This is a test email sent from the Avalon HR System using PHPMailer. If you received this, your email configuration is working! Test Code: {$testCode}";

    echo "<p>Attempting to send email to: " . htmlspecialchars($recipientTestEmail) . "...</p>";
    $mail->send();
    echo "<p style='color:green;'>SUCCESS: Test email has been sent successfully to " . htmlspecialchars($recipientTestEmail) . "!</p>";
    echo "<p>Please check your inbox (and spam folder).</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>ERROR: Message could not be sent. Mailer Error: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    echo "<p>Exception details: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><b>Troubleshooting Tips:</b><br>" .
         "1. Double-check your GMAIL_USER and GMAIL_APP_PASSWORD environment variables.<br>" .
         "2. Ensure you are using an App Password if 2-Step Verification is enabled on your Gmail account.<br>" .
         "3. Verify that 'Less secure app access' is NOT required if you are NOT using an App Password (though App Passwords are safer).<br>" .
         "4. Check your server's firewall or security group settings to ensure outbound connections on port 465 (or 587 for STARTTLS) are allowed.<br>" .
         "5. If using `SMTP::DEBUG_SERVER`, review the detailed SMTP conversation log for clues.</p>";
}

?>
