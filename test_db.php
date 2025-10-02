<?php
/**
 * Database Connection Test
 * DELETE THIS FILE AFTER TESTING!
 */

header('Content-Type: application/json');

try {
    require_once 'php/db_connect.php';
    
    if ($pdo) {
        // Test a simple query
        $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM Users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Database connected successfully',
            'user_count' => $result['user_count'],
            'db_info' => [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'database' => getenv('DB_NAME') ?: 'hr_integrated_db',
                'user' => getenv('DB_USER') ?: 'root'
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'PDO connection object not created'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>
