<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_compensation_plans.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Fetch Logic ---
try {
    $sql = "SELECT
                PlanID,
                PlanName,
                Description,
                EffectiveDate,
                EndDate,
                PlanType
            FROM
                CompensationPlans -- Ensure table name matches schema
            ORDER BY
                EffectiveDate DESC, PlanName";

    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates (optional)
    foreach ($plans as &$plan) {
        if (!empty($plan['EffectiveDate'])) {
            $plan['EffectiveDateFormatted'] = date('M d, Y', strtotime($plan['EffectiveDate']));
        }
        if (!empty($plan['EndDate'])) {
            $plan['EndDateFormatted'] = date('M d, Y', strtotime($plan['EndDate']));
        } else {
            $plan['EndDateFormatted'] = 'Ongoing';
        }
    }
    unset($plan);

    // --- Final JSON Output ---
    if (headers_sent()) { exit; }
    http_response_code(200);
    echo json_encode($plans);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_compensation_plans.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Database error retrieving compensation plans.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_compensation_plans.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving compensation plans.']);
}
exit;
?>
