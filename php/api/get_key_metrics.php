<?php
// hr34/php/api/get_key_metrics.php

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

session_start(); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); 

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; 
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Database connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in " . __FILE__ . " (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authorization Check ---
$allowed_roles = [1, 2]; // System Admin, HR Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array((int)$_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); 
     echo json_encode(['error' => 'Permission denied. You do not have rights to view key metrics.']);
     exit;
}

// --- Get Parameters ---
$metric_name = isset($_GET['metric_name']) ? trim(htmlspecialchars($_GET['metric_name'])) : null;
$metric_period = isset($_GET['metric_period']) ? trim(htmlspecialchars($_GET['metric_period'])) : 'current'; 

$response_data = [
    'metricNameDisplay' => '', // User-friendly name
    'metricKey' => $metric_name,
    'metricPeriod' => $metric_period,
    'value' => null,        
    'dataPoints' => [],     
    'labels' => [],         
    'unit' => '',
    'notes' => '',
    'error' => null
];

if (empty($metric_name)) {
    $response_data['error'] = "Metric name is required.";
    http_response_code(400);
    echo json_encode($response_data);
    exit;
}

try {
    $current_date = date('Y-m-d');
    $year_start = date('Y-01-01');
    $year_end = date('Y-12-31');

    // Define period start and end dates for trend data (example: last 12 months)
    $trend_period_end = $current_date;
    // Default period for trends if not 'current'
    if ($metric_period === 'monthly') {
        $trend_period_start = date('Y-m-01', strtotime('-11 months')); // Last 12 months
    } elseif ($metric_period === 'quarterly') {
        // For quarterly, you might adjust to start of the quarter, 4 quarters back
        $current_quarter_month = ((ceil(date('n') / 3) -1) * 3) + 1;
        $trend_period_start = date('Y-m-01', strtotime(date('Y') . '-' . $current_quarter_month . '-01 -1 year +1 day'));
    } elseif ($metric_period === 'annual') {
        $trend_period_start = date('Y-01-01', strtotime('-2 years')); // Last 3 years including current
    } else { // 'current' or other
        $trend_period_start = $year_start; // Default to current year for non-trend snapshots
    }


    switch ($metric_name) {
        case 'headcount_by_department':
            $response_data['metricNameDisplay'] = 'Headcount by Department';
            $response_data['unit'] = 'Employees';
            $response_data['notes'] = 'Number of active employees in each department as of today.';
            
            $sql_headcount = "SELECT os.DepartmentName, COUNT(e.EmployeeID) as Headcount
                              FROM Employees e
                              JOIN OrganizationalStructure os ON e.DepartmentID = os.DepartmentID
                              WHERE e.IsActive = TRUE
                              GROUP BY os.DepartmentID, os.DepartmentName
                              ORDER BY Headcount DESC";
            $stmt_headcount = $pdo->query($sql_headcount);
            $headcount_data = $stmt_headcount->fetchAll(PDO::FETCH_ASSOC);
            
            if ($headcount_data) {
                foreach($headcount_data as $dept) {
                    $response_data['labels'][] = $dept['DepartmentName'];
                    $response_data['dataPoints'][] = (int)$dept['Headcount'];
                }
            } else {
                 $response_data['notes'] = 'No department headcount data available.';
            }
            break;

        case 'turnover_rate':
            $response_data['metricNameDisplay'] = 'Employee Turnover Rate';
            $response_data['unit'] = "%";
            $response_data['notes'] = "Annualized turnover rate. Assumes 'TerminationDate' column exists in 'Employees' table.";

            // For simplicity, let's calculate for the current year so far
            // More complex logic would be needed for rolling periods or specific 'monthly', 'quarterly' views
            
            // Count of employees who left during the period (current year)
            // IMPORTANT: This query assumes you have a TerminationDate column.
            // If not, this will fail or return incorrect results.
            $sql_terminations = "SELECT COUNT(*) FROM Employees 
                                 WHERE TerminationDate BETWEEN :year_start AND :current_date";
            $stmt_terminations = $pdo->prepare($sql_terminations);
            $stmt_terminations->bindParam(':year_start', $year_start);
            $stmt_terminations->bindParam(':current_date', $current_date);
            $stmt_terminations->execute();
            $terminations_count = (int)$stmt_terminations->fetchColumn();

            // Average number of employees during the period
            // Simplification: (Employees at start + Employees at end) / 2
            $sql_emp_start_year = "SELECT COUNT(*) FROM Employees WHERE HireDate <= :year_start AND (TerminationDate IS NULL OR TerminationDate > :year_start)";
            $stmt_emp_start_year = $pdo->prepare($sql_emp_start_year);
            $stmt_emp_start_year->bindParam(':year_start', $year_start);
            $stmt_emp_start_year->execute();
            $employees_at_start_of_year = (int)$stmt_emp_start_year->fetchColumn();

            $sql_emp_current = "SELECT COUNT(*) FROM Employees WHERE IsActive = TRUE";
            $stmt_emp_current = $pdo->query($sql_emp_current);
            $employees_current = (int)$stmt_emp_current->fetchColumn();
            
            $average_employees = ($employees_at_start_of_year + $employees_current) / 2;

            if ($average_employees > 0) {
                // Annualize the rate if the period is less than a full year
                $days_in_year = (new DateTime($year_end))->diff(new DateTime($year_start))->days +1;
                $days_passed_this_year = (new DateTime($current_date))->diff(new DateTime($year_start))->days +1;
                
                if ($days_passed_this_year > 0 && $days_passed_this_year < $days_in_year) {
                    $annualization_factor = $days_in_year / $days_passed_this_year;
                    $turnover_rate = ($terminations_count / $average_employees) * $annualization_factor * 100;
                } else if ($days_passed_this_year >= $days_in_year) {
                     $turnover_rate = ($terminations_count / $average_employees) * 100;
                } else {
                    $turnover_rate = 0; // Avoid division by zero if no time has passed
                }
                $response_data['value'] = round($turnover_rate, 2);
            } else {
                $response_data['value'] = "0.00";
                $response_data['notes'] .= " (Average employee count is zero).";
            }
            // TODO: Implement trendData for turnover rate if $metric_period is 'monthly', 'quarterly', etc.
            // This would involve calculating the rate for each sub-period.
            break;

        case 'avg_time_to_hire':
            $response_data['metricNameDisplay'] = 'Average Time to Hire';
            $response_data['value'] = "N/A"; 
            $response_data['unit'] = "days";
            $response_data['notes'] = "Requires Recruitment module data (JobPostings, Applicants tables with relevant dates). Not yet implemented.";
            break;

        case 'training_completion_rate':
            $response_data['metricNameDisplay'] = 'Training Completion Rate';
            $response_data['value'] = "N/A"; 
            $response_data['unit'] = "%";
            $response_data['notes'] = "Requires Learning Management module data (TrainingCourses, EmployeeTraining tables). Not yet implemented.";
            break;
        
        default:
            $response_data['error'] = "Metric '{$metric_name}' is not recognized or implemented yet.";
            http_response_code(404);
            echo json_encode($response_data);
            exit;
    }

    http_response_code(200);
    echo json_encode($response_data);

} catch (\PDOException $e) {
    error_log("PHP PDOException in " . __FILE__ . " for metric '{$metric_name}': " . $e->getMessage());
    $response_data['error'] = "Database error retrieving metric: {$metric_name}.";
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($response_data);
} catch (Throwable $e) { 
    error_log("PHP Throwable in " . __FILE__ . " for metric '{$metric_name}': " . $e->getMessage());
    $response_data['error'] = "Unexpected server error retrieving metric: {$metric_name}.";
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($response_data);
}
exit; 
?>
