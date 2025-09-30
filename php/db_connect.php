<?php
/**
 * Database Connection Script
 *
 * Establishes a PDO connection to the database.
 * Reads credentials from environment variables for improved security.
 * Ensure the following environment variables are set on your server:
 * DB_HOST: Database host (e.g., 'localhost' or '127.0.0.1')
 * DB_NAME: Database name (e.g., 'hr_integrated_db')
 * DB_USER: Database username (e.g., 'root')
 * DB_PASS: Database password
 */

// --- Error Reporting (Keep logging enabled, but don't display errors) ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Should be 0 in production
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Set a specific log file path

// --- Database Configuration from Environment Variables ---
// Provide default values ONLY for local development if necessary,
// but ideally, these should always be set in the environment.
$db_host = getenv('DB_HOST') ?: 'localhost';      // Default to 'localhost' if env var not set
$db_name = getenv('DB_NAME') ?: 'hr_integrated_db'; // Default to 'hr_integrated_db' if env var not set
$db_user = getenv('DB_USER') ?: 'root';           // Default to 'root' if env var not set
$db_pass = getenv('DB_PASS') ?: '';               // Default to empty password if env var not set
$charset = 'utf8mb4';

// --- Validate that essential variables were loaded ---
// Check if critical variables are still using defaults or are empty, which might indicate
// environment variables are not set correctly in production.
if ($db_host === 'localhost' && getenv('DB_HOST') === false) {
    error_log("Warning: DB_HOST environment variable not set. Using default 'localhost'.");
}
if ($db_name === 'hr_integrated_db' && getenv('DB_NAME') === false) {
    error_log("Warning: DB_NAME environment variable not set. Using default 'hr_integrated_db'.");
}
if ($db_user === 'root' && getenv('DB_USER') === false) {
     error_log("Warning: DB_USER environment variable not set. Using default 'root'.");
}
// No warning for password, as it might legitimately be empty.

// --- Data Source Name (DSN) ---
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$charset}";

// --- PDO Options ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements for security
];

// --- Establish Connection ---
$pdo = null; // Initialize $pdo to null
try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
     // Optional: Log successful connection for debugging if needed
     // error_log("Database connection successful to {$db_name}@{$db_host}");

} catch (\PDOException $e) {
     // Log the detailed error message securely on the server
     error_log("Database Connection Error: " . $e->getMessage() . " (DSN: " . $dsn . ", User: " . $db_user . ")");

     // Send a generic error response to the client (important for APIs)
     // Check if headers have already been sent before trying to set them
     if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
     }
     // Use die() or exit() to ensure no further code execution and prevent potential HTML output
     die(json_encode(['error' => 'Database connection failed. Please contact the administrator.']));
}

/**
 * Function to check database schema integrity
 * Verifies that key tables and relationships exist
 * @param PDO $pdo The database connection object
 * @return bool True if schema is intact, false otherwise
 */
function checkSchemaIntegrity($pdo) {
    try {
        // Check if key tables exist
        $tables = ['Users', 'Roles', 'Employees', 'Departments', 'JobRoles'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                error_log("Schema check failed: Table '$table' does not exist.");
                return false;
            }
        }

        // Check foreign key relationships
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Employees' AND COLUMN_NAME = 'DepartmentID'");
        if ($stmt->rowCount() == 0) {
            error_log("Schema check failed: Foreign key relationship missing for Employees.DepartmentID.");
            return false;
        }

        error_log("Schema integrity check passed.");
        return true;
    } catch (PDOException $e) {
        error_log("Schema check error: " . $e->getMessage());
        return false;
    }
}

// Run schema check after connection
if ($pdo) {
    checkSchemaIntegrity($pdo);
}

// The $pdo variable is now available for use in the script that includes this file.
// No closing PHP tag needed if this is the end of the file.
