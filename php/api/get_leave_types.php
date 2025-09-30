<?php
    // --- Error Reporting & Headers ---
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Adjust for production

    // --- Database Connection ---
    $pdo = null;
    try {
        require_once '../db_connect.php';
         if (!isset($pdo) || !$pdo instanceof PDO) { throw new Exception('DB connection failed'); }
    } catch (Throwable $e) {
        error_log("PHP Error in get_leave_types.php (db_connect include): " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error.']);
        exit;
    }

    try {
        // Fetch relevant columns from LeaveTypes table
        $sql = "SELECT
                    LeaveTypeID,
                    TypeName,
                    Description,
                    RequiresApproval,
                    AccrualRate,
                    MaxCarryForwardDays
                FROM
                    LeaveTypes -- Ensure this table name matches your schema
                ORDER BY
                    TypeName";
        $stmt = $pdo->query($sql);
        $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Final JSON Output ---
         if (headers_sent()) { exit; } // Prevent output if headers already sent
         http_response_code(200);
         echo json_encode($leaveTypes); // Output the array (can be empty)

    } catch (\PDOException $e) {
        error_log("PHP PDOException in get_leave_types.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Database error retrieving leave types.']);
    } catch (Throwable $e) {
         error_log("PHP Throwable in get_leave_types.php: " . $e->getMessage());
         if (!headers_sent()) { http_response_code(500); }
         echo json_encode(['error' => 'Unexpected server error retrieving leave types.']);
    }
    exit;
    ?>
    