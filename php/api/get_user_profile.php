<?php
/**
 * API Endpoint: Get User Profile
 * Retrieves detailed information for the currently logged-in user.
 * V5: Fetches more employee details (personal, address, emergency, photo, direct manager)
 * and formats DateOfBirth.
 * V4: Corrected API key to match HR 1-2 API expectation.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../../php-error.log');

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection (HR 3-4 DB) ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('HR 3-4 Database connection object ($pdo) not properly created.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_user_profile.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the HR 3-4 database.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id'])) {
    http_response_code(401); // Changed from 403 to 401 for unauthenticated
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$loggedInEmployeeId = $_SESSION['employee_id'];
// --- End Authentication Check ---

/**
 * Fetches performance summary from the HR 1-2 system for a given employee
 * by making an actual API call.
 * @param int $hr34_employee_id The EmployeeID from the HR 3-4 system.
 * @return array An array containing performance data or a default "not available" structure.
 */
function getPerformanceSummaryFromHR12_Actual($hr34_employee_id) {
    // IMPORTANT: Configure these values
    // Assuming HR 1-2 is in a folder named 'hr12_system' in your XAMPP htdocs
    $hr12_api_base_url = 'http://localhost/hr12_system/api/'; // ADJUST IF YOUR HR12 PATH IS DIFFERENT
    $hr12_performance_endpoint = $hr12_api_base_url . 'hr12_get_performance_summary.php';
    
    $api_key_for_hr12 = '1111'; // This now matches the $expectedApiKey in hr12_get_performance_summary.php

    $url_with_param = $hr12_performance_endpoint . '?hr34_employee_id=' . urlencode($hr34_employee_id);

    error_log("[get_user_profile.php] Calling HR 1-2 API for performance summary: " . $url_with_param);

    $default_summary = [
        'last_review_date' => 'N/A',
        'last_review_period' => 'N/A',
        'overall_rating' => 'N/A',
        'summary_comment' => 'Could not retrieve performance summary from HR 1-2 system.'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_with_param);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'X-API-KEY: ' . $api_key_for_hr12
    ]);

    $response_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("cURL Error calling HR 1-2 API: " . curl_error($ch));
        curl_close($ch);
        return $default_summary;
    }
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return array_merge([ 
                'last_review_date' => 'N/A',
                'last_review_period' => 'N/A',
                'overall_rating' => 'N/A',
                'summary_comment' => 'Data incomplete from HR 1-2.'
            ], $data);
        } else {
            error_log("HR 1-2 API response was not valid JSON or not an array. HTTP Code: {$http_code}. Response: " . substr($response_json, 0, 200));
            return $default_summary;
        }
    } else {
        error_log("HR 1-2 API returned HTTP status: {$http_code}. Response: " . substr($response_json, 0, 200));
        $api_error_message = "Error fetching performance data from HR 1-2 (Status: {$http_code}).";
        if ($http_code == 401) { 
            $api_error_message = "Unauthorized: API Key mismatch or issue with HR 1-2 API authorization.";
        }
        $default_summary['summary_comment'] = $api_error_message;
        return $default_summary;
    }
}


// --- Fetch User Profile Data from HR 3-4 DB ---
try {
    $sql = "SELECT
                e.EmployeeID,
                e.FirstName,
                e.MiddleName,
                e.LastName,
                e.Suffix,
                e.Email AS EmployeeEmail,
                e.PersonalEmail,
                e.PhoneNumber,
                e.DateOfBirth,
                e.Gender,
                e.MaritalStatus,
                e.Nationality,
                e.AddressLine1,
                e.AddressLine2,
                e.City,
                e.StateProvince,
                e.PostalCode,
                e.Country,
                e.EmergencyContactName,
                e.EmergencyContactRelationship,
                e.EmergencyContactPhone,
                e.HireDate,
                e.JobTitle,
                e.IsActive AS EmployeeIsActive,
                e.EmployeePhotoPath,
                d.DepartmentID,
                d.DepartmentName,
                CONCAT(dept_mgr.FirstName, ' ', dept_mgr.LastName) AS DepartmentManagerName,
                CONCAT(direct_mgr.FirstName, ' ', direct_mgr.LastName) AS DirectManagerName,
                u.Username,
                u.IsTwoFactorEnabled,
                r.RoleName,
                es.BaseSalary,
                es.PayFrequency,
                es.PayRate,
                es.EffectiveDate AS SalaryEffectiveDate
            FROM
                Employees e
            LEFT JOIN
                OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN 
                Employees dept_mgr ON d.ManagerID = dept_mgr.EmployeeID /* Manager of the department */
            LEFT JOIN
                Employees direct_mgr ON e.ManagerID = direct_mgr.EmployeeID /* Employee's direct manager */
            LEFT JOIN
                Users u ON e.EmployeeID = u.EmployeeID
            LEFT JOIN
                Roles r ON u.RoleID = r.RoleID
            LEFT JOIN
                EmployeeSalaries es ON e.EmployeeID = es.EmployeeID AND es.IsCurrent = TRUE
            WHERE
                e.EmployeeID = :employee_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':employee_id', $loggedInEmployeeId, PDO::PARAM_INT);
    $stmt->execute();
    $profileData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profileData) {
        http_response_code(404);
        echo json_encode(['error' => 'User profile data not found in HR 3-4 database.']);
        exit;
    }

    // Format dates and monetary values
    if (!empty($profileData['HireDate'])) {
        $profileData['HireDateFormatted'] = date('M d, Y', strtotime($profileData['HireDate']));
    }
    if (!empty($profileData['SalaryEffectiveDate'])) {
        $profileData['SalaryEffectiveDateFormatted'] = date('M d, Y', strtotime($profileData['SalaryEffectiveDate']));
    }
    if (!empty($profileData['DateOfBirth'])) {
        $profileData['DateOfBirthFormatted'] = date('M d, Y', strtotime($profileData['DateOfBirth']));
    }
    if (isset($profileData['BaseSalary'])) {
        $profileData['BaseSalaryFormatted'] = number_format((float)$profileData['BaseSalary'], 2);
    }
    if (isset($profileData['PayRate'])) {
        $profileData['PayRateFormatted'] = $profileData['PayRate'] ? number_format((float)$profileData['PayRate'], 2) : '-';
    }

    // --- Fetch Performance Summary from HR 1-2 via actual API call ---
    $performanceSummaryHR12 = getPerformanceSummaryFromHR12_Actual($loggedInEmployeeId);
    $profileData['performance_summary_hr12'] = $performanceSummaryHR12;
    // --- End Fetch Performance Summary ---

    http_response_code(200);
    echo json_encode($profileData);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_user_profile.php (HR 3-4 DB Query): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error retrieving user profile from HR 3-4.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_user_profile.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving user profile.']);
}
exit;
?>
