<?php
/**
 * API Endpoint: Save HMO Enrollment
 * Creates or updates an HMO enrollment for an employee
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db_connect.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    $enrollmentId = isset($_POST['enrollmentId']) ? (int)$_POST['enrollmentId'] : null;
    $employeeId = (int)$_POST['employeeId'];
    $planId = (int)$_POST['planId'];
    $enrollmentDate = $_POST['enrollmentDate'];
    $effectiveDate = $_POST['effectiveDate'];
    $endDate = !empty($_POST['endDate']) ? $_POST['endDate'] : null;
    $status = $_POST['status'] ?? 'Active';
    $monthlyDeduction = (float)$_POST['monthlyDeduction'];

    // Validation
    if ($employeeId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid employee is required.']);
        exit;
    }

    if ($planId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid plan is required.']);
        exit;
    }

    if (empty($enrollmentDate) || empty($effectiveDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Enrollment date and effective date are required.']);
        exit;
    }

    if ($monthlyDeduction < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Monthly deduction cannot be negative.']);
        exit;
    }

    // Check if employee already has an active enrollment
    if (!$enrollmentId) {
        $sql = "SELECT COUNT(*) FROM EmployeeHMOEnrollments
                WHERE EmployeeID = :employeeId AND Status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employeeId', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Employee already has an active HMO enrollment.']);
            exit;
        }
    }

    if ($enrollmentId) {
        // Update existing enrollment
        $sql = "UPDATE EmployeeHMOEnrollments SET
                    EmployeeID = :employeeId,
                    PlanID = :planId,
                    EnrollmentDate = :enrollmentDate,
                    EffectiveDate = :effectiveDate,
                    EndDate = :endDate,
                    Status = :status,
                    MonthlyDeduction = :monthlyDeduction,
                    UpdatedAt = CURRENT_TIMESTAMP
                WHERE EnrollmentID = :enrollmentId";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':enrollmentId', $enrollmentId, PDO::PARAM_INT);
    } else {
        // Insert new enrollment
        $sql = "INSERT INTO EmployeeHMOEnrollments
                (EmployeeID, PlanID, EnrollmentDate, EffectiveDate, EndDate, Status, MonthlyDeduction, CreatedAt)
                VALUES (:employeeId, :planId, :enrollmentDate, :effectiveDate, :endDate, :status, :monthlyDeduction, CURRENT_TIMESTAMP)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':employeeId', $employeeId, PDO::PARAM_INT);
    $stmt->bindParam(':planId', $planId, PDO::PARAM_INT);
    $stmt->bindParam(':enrollmentDate', $enrollmentDate);
    $stmt->bindParam(':effectiveDate', $effectiveDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':monthlyDeduction', $monthlyDeduction, PDO::PARAM_STR);

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $enrollmentId ? 'Enrollment updated successfully.' : 'Enrollment created successfully.',
        'enrollmentId' => $enrollmentId ?: $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Save HMO Enrollment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while saving enrollment.']);
} catch (Exception $e) {
    error_log("Save HMO Enrollment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>
