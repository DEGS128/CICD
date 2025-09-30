<?php
/**
 * Departments Routes
 * Handles department management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Department.php';

class DepartmentsController {
    private $pdo;
    private $authMiddleware;
    private $departmentModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->departmentModel = new Department();
    }

    /**
     * Handle department requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    $this->getDepartments();
                } elseif ($subResource === 'employees') {
                    $this->getDepartmentEmployees($id);
                } else {
                    $this->getDepartment($id);
                }
                break;
            case 'POST':
                $this->createDepartment();
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateDepartment($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteDepartment($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all departments
     */
    private function getDepartments() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'is_active' => $request->getData('is_active'),
            'search' => $request->getData('search')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $departments = $this->departmentModel->getDepartments(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->departmentModel->countDepartments($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($departments, $paginationData);

        } catch (Exception $e) {
            error_log("Get departments error: " . $e->getMessage());
            Response::error('Failed to retrieve departments', 500);
        }
    }

    /**
     * Get single department
     */
    private function getDepartment($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid department ID']);
        }

        try {
            $department = $this->departmentModel->getDepartmentById($id);
            
            if (!$department) {
                Response::notFound('Department not found');
            }

            Response::success($department);

        } catch (Exception $e) {
            error_log("Get department error: " . $e->getMessage());
            Response::error('Failed to retrieve department', 500);
        }
    }

    /**
     * Get department employees
     */
    private function getDepartmentEmployees($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid department ID']);
        }

        try {
            $employees = $this->departmentModel->getDepartmentEmployees($id);
            Response::success($employees);

        } catch (Exception $e) {
            error_log("Get department employees error: " . $e->getMessage());
            Response::error('Failed to retrieve department employees', 500);
        }
    }

    /**
     * Create new department
     */
    private function createDepartment() {
        // Check authorization - only admins can create departments
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create departments');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['department_name']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $departmentName = $request->sanitizeString($data['department_name']);

        // Check if department name already exists
        if ($this->departmentModel->departmentNameExists($departmentName)) {
            $errors['department_name'] = 'Department name already exists';
            Response::validationError($errors);
        }

        try {
            $departmentData = [
                'department_name' => $departmentName,
                'description' => isset($data['description']) ? $request->sanitizeString($data['description']) : null,
                'manager_id' => isset($data['manager_id']) ? (int)$data['manager_id'] : null,
                'budget' => isset($data['budget']) ? $request->sanitizeString($data['budget']) : null,
                'location' => isset($data['location']) ? $request->sanitizeString($data['location']) : null,
                'is_active' => 1
            ];

            $departmentId = $this->departmentModel->createDepartment($departmentData);

            Response::created([
                'department_id' => $departmentId,
                'department_name' => $departmentName
            ], 'Department created successfully');

        } catch (Exception $e) {
            error_log("Create department error: " . $e->getMessage());
            Response::error('Failed to create department', 500);
        }
    }

    /**
     * Update department
     */
    private function updateDepartment($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid department ID']);
        }

        // Check authorization - only admins can update departments
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update departments');
        }

        $data = $request->getData();

        // Check if department exists
        $existingDepartment = $this->departmentModel->getDepartmentById($id);
        if (!$existingDepartment) {
            Response::notFound('Department not found');
        }

        $errors = [];

        // Validate department name if provided
        if (isset($data['department_name'])) {
            $departmentName = $request->sanitizeString($data['department_name']);
            if ($this->departmentModel->departmentNameExists($departmentName, $id)) {
                $errors['department_name'] = 'Department name already exists';
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $updateData = [];

            // Only update provided fields
            $allowedFields = [
                'department_name', 'description', 'manager_id', 'budget', 'location', 'is_active'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['manager_id', 'is_active'])) {
                        $updateData[$field] = (int)$data[$field];
                    } else {
                        $updateData[$field] = $request->sanitizeString($data[$field]);
                    }
                }
            }

            if (!empty($updateData)) {
                $this->departmentModel->updateDepartment($id, $updateData);
            }

            Response::success(null, 'Department updated successfully');

        } catch (Exception $e) {
            error_log("Update department error: " . $e->getMessage());
            Response::error('Failed to update department', 500);
        }
    }

    /**
     * Delete department (soft delete)
     */
    private function deleteDepartment($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid department ID']);
        }

        // Check authorization - only admins can delete departments
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to delete departments');
        }

        try {
            $department = $this->departmentModel->getDepartmentById($id);
            
            if (!$department) {
                Response::notFound('Department not found');
            }

            $this->departmentModel->deleteDepartment($id);

            Response::success(null, 'Department deleted successfully');

        } catch (Exception $e) {
            error_log("Delete department error: " . $e->getMessage());
            Response::error('Failed to delete department', 500);
        }
    }
}

