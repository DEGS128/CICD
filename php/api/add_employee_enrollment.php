<?php
/**
 * API Endpoint: Add Employee HMO Enrollment
 * Enrolls an employee in an HMO plan
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    // Get form data
    $employeeId = filter_var($_POST['employeeId'] ?? 0, FILTER_VALIDATE_INT);
    $planId = filter_var($_POST['planId'] ?? 0, FILTER_VALIDATE_INT);
    $enrollmentDate = $_POST['enrollmentDate'] ?? date('Y-m-d');
    $effectiveDate = $_POST['effectiveDate'] ?? date('Y-m-d');
    $monthlyContribution = filter_var($_POST['monthlyContribution'] ?? 0, FILTER_VALIDATE_FLOAT);
    $companyContribution = filter_var($_POST['companyContribution'] ?? 0, FILTER_VALIDATE_FLOAT);
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if ($employeeId <= 0 || $planId <= 0) {
        echo json_encode(['error' => 'Employee and HMO plan are required.']);
        exit;
    }

    // Check if employee already has an active enrollment
    $checkSql = "SELECT EnrollmentID FROM EmployeeHMOEnrollments WHERE EmployeeID = :employeeId AND Status = 'Active'";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':employeeId', $employeeId);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Employee already has an active HMO enrollment.']);
        exit;
    }

    // Insert new enrollment
    $sql = "INSERT INTO EmployeeHMOEnrollments (EmployeeID, PlanID, EnrollmentDate, EffectiveDate, MonthlyContribution, CompanyContribution, Notes) 
            VALUES (:employeeId, :planId, :enrollmentDate, :effectiveDate, :monthlyContribution, :companyContribution, :notes)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':employeeId', $employeeId);
    $stmt->bindParam(':planId', $planId);
    $stmt->bindParam(':enrollmentDate', $enrollmentDate);
    $stmt->bindParam(':effectiveDate', $effectiveDate);
    $stmt->bindParam(':monthlyContribution', $monthlyContribution);
    $stmt->bindParam(':companyContribution', $companyContribution);
    $stmt->bindParam(':notes', $notes);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee enrolled successfully.',
            'enrollmentId' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['error' => 'Failed to enroll employee.']);
    }

} catch (PDOException $e) {
    error_log("Add Employee Enrollment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while enrolling employee.']);
} catch (Exception $e) {
    error_log("Add Employee Enrollment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>
