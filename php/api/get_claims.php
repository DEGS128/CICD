<?php
/**
 * API Endpoint: Get Claims
 * Retrieves claim records, optionally filtering by employee ID and/or status.
 * v2.0 - Restored filtering logic.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
$pdo = null; // Initialize
try {
    require_once '../db_connect.php';
     if (!isset($pdo) || !$pdo instanceof PDO) {
         throw new Exception('$pdo object not created by db_connect.php');
    }
} catch (Throwable $e) {
    error_log("CRITICAL PHP Error: Failed to include or connect via db_connect.php in get_claims.php: " . $e->getMessage());
     if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
     }
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}

// --- Optional Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$status_filter = isset($_GET['status']) ? trim(htmlspecialchars($_GET['status'])) : null;

// --- Debugging: Log received parameters ---
error_log("[get_claims.php] Received Params - employee_id: " . ($employee_id_filter ?? 'NULL') . ", status: " . ($status_filter ?? 'NULL'));
// ---

try {
    // Base SQL query
    $sql = "SELECT
                c.ClaimID, c.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                c.ClaimTypeID, ct.TypeName AS ClaimTypeName,
                c.SubmissionDate, c.ClaimDate, c.Amount, c.Currency,
                c.Description, c.ReceiptPath, c.Status, c.PayrollID
            FROM
                Claims c
            JOIN
                Employees e ON c.EmployeeID = e.EmployeeID
            JOIN
                ClaimTypes ct ON c.ClaimTypeID = ct.ClaimTypeID";

    $conditions = [];
    $params = [];

    // --- FILTERING LOGIC ---
    // Add filter conditions based on provided parameters
    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $conditions[] = "c.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }

    if (!empty($status_filter)) {
        // Use BINARY comparison for case-sensitivity (ensure 'Submitted' matches DB exactly)
        $conditions[] = "BINARY c.Status = :status";
        $params[':status'] = $status_filter;
    }
    // --- END FILTERING LOGIC ---

     // Append WHERE clause if conditions exist
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Default ordering
    $sql .= " ORDER BY c.SubmissionDate DESC";

    error_log("[get_claims.php] Executing SQL: " . $sql);
    error_log("[get_claims.php] With Params: " . print_r($params, true));

    // Prepare and execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch results
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $claimCount = count($claims);
    error_log("[get_claims.php] Raw claims data fetched (Count: " . $claimCount . ")");

    // Format data (only if claims were found)
    if (!empty($claims)) {
        foreach ($claims as &$claim) {
            // Format Amount
            if (isset($claim['Amount']) && is_numeric($claim['Amount'])) {
                 $claim['AmountFormatted'] = number_format((float)$claim['Amount'], 2);
            } else { $claim['AmountFormatted'] = '-'; }
            // Format Claim Date
             if (!empty($claim['ClaimDate'])) {
                 $claim['ClaimDateFormatted'] = date('M d, Y', strtotime($claim['ClaimDate']));
             } else { $claim['ClaimDateFormatted'] = 'N/A'; }
            // Format Submission Timestamp
             if (!empty($claim['SubmissionDate'])) {
                 $claim['SubmissionDateFormatted'] = date('M d, Y H:i', strtotime($claim['SubmissionDate']));
             } else { $claim['SubmissionDateFormatted'] = 'N/A'; }
             $claim['ReceiptPath'] = $claim['ReceiptPath'] ?? null;
             $claim['ClaimID'] = $claim['ClaimID'] ?? null;
             $claim['EmployeeName'] = $claim['EmployeeName'] ?? 'N/A';
             $claim['ClaimTypeName'] = $claim['ClaimTypeName'] ?? 'N/A';
             $claim['Status'] = $claim['Status'] ?? 'N/A';
        }
        unset($claim);
    }

    error_log("[get_claims.php] Final claims data sending (Count: " . count($claims) . ")");

    // Output the results as JSON
    if (!headers_sent()) {
         http_response_code(200);
         echo json_encode($claims);
    } else {
        error_log("[get_claims.php] Error: Headers already sent before final echo.");
    }


} catch (\PDOException $e) {
    error_log("[get_claims.php] PDOException: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . print_r($params, true));
     if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
     }
    echo json_encode(['error' => 'Database error while retrieving claims data.']);
} catch (Throwable $e) {
     error_log("[get_claims.php] Throwable: " . $e->getMessage());
      if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
     }
     echo json_encode(['error' => 'An unexpected server error occurred.']);
}

exit; // Explicitly exit
?>
