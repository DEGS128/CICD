<?php
    // Set headers for JSON output and CORS
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Adjust for production

    // Include database connection
    require_once '../db_connect.php';

    // Check if an employee ID is provided for filtering
    $employee_id = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;

    try {
        // Base SQL query - Join with Employees to get names
        $sql = "SELECT
                    d.DocumentID,
                    d.EmployeeID,
                    CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                    d.DocumentType,
                    d.DocumentName,
                    d.FilePath,
                    d.UploadedAt
                FROM
                    EmployeeDocuments d
                JOIN
                    Employees e ON d.EmployeeID = e.EmployeeID";

        // Append WHERE clause if employee_id is provided and valid
        if ($employee_id !== null && $employee_id !== false && $employee_id > 0) {
            $sql .= " WHERE d.EmployeeID = :employee_id";
        }

        $sql .= " ORDER BY e.LastName, e.FirstName, d.UploadedAt DESC"; // Order results

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);

        // Bind the employee_id parameter if it exists
        if ($employee_id !== null && $employee_id !== false && $employee_id > 0) {
            $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        }

        $stmt->execute();

        // Fetch all results
        $documents = $stmt->fetchAll();

        // Output the results as JSON
        echo json_encode($documents);

    } catch (\PDOException $e) {
        error_log("API Error (get_documents): " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve document data.']);
    }
    ?>
    