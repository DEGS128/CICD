<?php
/**
 * RESTful API Entry Point
 * Hospital HR Management System
 * 
 * This file serves as the main entry point for all API requests.
 * It handles routing, middleware, and response formatting.
 */

// Error reporting and headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers for CORS and JSON responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../php/db_connect.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Request.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/middlewares/ErrorHandler.php';

// Initialize error handler
$errorHandler = new ErrorHandler();

// Initialize request handler
$request = new Request();

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $request->getPath();

// Remove query string from path
$path = strtok($path, '?');

// Remove leading slash and split into segments
$path = ltrim($path, '/');
$segments = explode('/', $path);

// Remove 'api' from the beginning if present
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

// Route the request
try {
    // Authentication routes (no auth required)
    if ($segments[0] === 'auth') {
        switch ($segments[1]) {
            case 'login':
                if ($method === 'POST') {
                    require_once __DIR__ . '/routes/auth.php';
                    $authController = new AuthController();
                    $authController->login();
                } else {
                    Response::methodNotAllowed();
                }
                break;
            case 'logout':
                if ($method === 'POST') {
                    require_once __DIR__ . '/routes/auth.php';
                    $authController = new AuthController();
                    $authController->logout();
                } else {
                    Response::methodNotAllowed();
                }
                break;
            case 'verify-2fa':
                if ($method === 'POST') {
                    require_once __DIR__ . '/routes/auth.php';
                    $authController = new AuthController();
                    $authController->verify2FA();
                } else {
                    Response::methodNotAllowed();
                }
                break;
            case 'reset-password':
                if ($method === 'POST') {
                    require_once __DIR__ . '/routes/auth.php';
                    $authController = new AuthController();
                    $authController->resetPassword();
                } else {
                    Response::methodNotAllowed();
                }
                break;
            default:
                Response::notFound();
        }
    }
    // Protected routes (require authentication)
    else {
        // Check authentication
        $authMiddleware = new AuthMiddleware();
        if (!$authMiddleware->authenticate()) {
            Response::unauthorized('Authentication required');
        }

        // Route to appropriate controller
        $resource = $segments[0] ?? '';
        $id = $segments[1] ?? null;
        $subResource = $segments[2] ?? null;

        switch ($resource) {
            case 'users':
                require_once __DIR__ . '/routes/users.php';
                $controller = new UsersController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'employees':
                require_once __DIR__ . '/routes/employees.php';
                $controller = new EmployeesController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'departments':
                require_once __DIR__ . '/routes/departments.php';
                $controller = new DepartmentsController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'benefits':
                require_once __DIR__ . '/routes/benefits.php';
                $controller = new BenefitsController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'payroll':
                require_once __DIR__ . '/routes/payroll.php';
                $controller = new PayrollController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'attendance':
                require_once __DIR__ . '/routes/attendance.php';
                $controller = new AttendanceController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'leave':
                require_once __DIR__ . '/routes/leave.php';
                $controller = new LeaveController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'hmo':
                require_once __DIR__ . '/routes/hmo.php';
                $controller = new HMOController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'reports':
                require_once __DIR__ . '/routes/reports.php';
                $controller = new ReportsController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            case 'dashboard':
                require_once __DIR__ . '/routes/dashboard.php';
                $controller = new DashboardController();
                $controller->handleRequest($method, $id, $subResource);
                break;
            default:
                Response::notFound();
        }
    }
} catch (Exception $e) {
    $errorHandler->handle($e);
}
