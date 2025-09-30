<?php
/**
 * Employees Routes
 * Handles employee management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Employee.php';

class EmployeesController {
    private $pdo;
    private $authMiddleware;
    private $employeeModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->employeeModel = new Employee();
    }

    /**
     * Handle employee requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    $this->getEmployees();
                } elseif ($subResource === 'benefits') {
                    $this->getEmployeeBenefits($id);
                } elseif ($subResource === 'salary') {
                    $this->getEmployeeSalary($id);
                } else {
                    $this->getEmployee($id);
                }
                break;
            case 'POST':
                $this->createEmployee();
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateEmployee($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteEmployee($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all employees
     */
    private function getEmployees() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'department_id' => $request->getData('department_id'),
            'is_active' => $request->getData('is_active'),
            'manager_id' => $request->getData('manager_id'),
            'search' => $request->getData('search')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $employees = $this->employeeModel->getEmployees(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->employeeModel->countEmployees($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($employees, $paginationData);

        } catch (Exception $e) {
            error_log("Get employees error: " . $e->getMessage());
            Response::error('Failed to retrieve employees', 500);
        }
    }

    /**
     * Get single employee
     */
    private function getEmployee($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid employee ID']);
        }

        try {
            $employee = $this->employeeModel->getEmployeeById($id);
            
            if (!$employee) {
                Response::notFound('Employee not found');
            }

            Response::success($employee);

        } catch (Exception $e) {
            error_log("Get employee error: " . $e->getMessage());
            Response::error('Failed to retrieve employee', 500);
        }
    }

    /**
     * Get employee benefits
     */
    private function getEmployeeBenefits($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid employee ID']);
        }

        try {
            $benefits = $this->employeeModel->getEmployeeBenefits($id);
            Response::success($benefits);

        } catch (Exception $e) {
            error_log("Get employee benefits error: " . $e->getMessage());
            Response::error('Failed to retrieve employee benefits', 500);
        }
    }

    /**
     * Get employee salary
     */
    private function getEmployeeSalary($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid employee ID']);
        }

        try {
            $salary = $this->employeeModel->getEmployeeSalary($id);
            Response::success($salary);

        } catch (Exception $e) {
            error_log("Get employee salary error: " . $e->getMessage());
            Response::error('Failed to retrieve employee salary', 500);
        }
    }

    /**
     * Create new employee
     */
    private function createEmployee() {
        // Check authorization - only admins and HR can create employees
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create employees');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired([
            'first_name', 'last_name', 'email', 'job_title', 'department_id'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Additional validation
        $email = $data['email'];
        if (!$request->validateEmail($email)) {
            $errors['email'] = 'Invalid email format';
        }

        // Check if email already exists
        if ($this->employeeModel->emailExists($email)) {
            $errors['email'] = 'Email already exists';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $employeeData = [
                'first_name' => $request->sanitizeString($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? $request->sanitizeString($data['middle_name']) : null,
                'last_name' => $request->sanitizeString($data['last_name']),
                'suffix' => isset($data['suffix']) ? $request->sanitizeString($data['suffix']) : null,
                'email' => $email,
                'personal_email' => isset($data['personal_email']) ? $data['personal_email'] : null,
                'phone_number' => isset($data['phone_number']) ? $request->sanitizeString($data['phone_number']) : null,
                'date_of_birth' => isset($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'gender' => isset($data['gender']) ? $request->sanitizeString($data['gender']) : null,
                'marital_status' => isset($data['marital_status']) ? $request->sanitizeString($data['marital_status']) : null,
                'nationality' => isset($data['nationality']) ? $request->sanitizeString($data['nationality']) : null,
                'address_line1' => isset($data['address_line1']) ? $request->sanitizeString($data['address_line1']) : null,
                'address_line2' => isset($data['address_line2']) ? $request->sanitizeString($data['address_line2']) : null,
                'city' => isset($data['city']) ? $request->sanitizeString($data['city']) : null,
                'state_province' => isset($data['state_province']) ? $request->sanitizeString($data['state_province']) : null,
                'postal_code' => isset($data['postal_code']) ? $request->sanitizeString($data['postal_code']) : null,
                'country' => isset($data['country']) ? $request->sanitizeString($data['country']) : null,
                'emergency_contact_name' => isset($data['emergency_contact_name']) ? $request->sanitizeString($data['emergency_contact_name']) : null,
                'emergency_contact_relationship' => isset($data['emergency_contact_relationship']) ? $request->sanitizeString($data['emergency_contact_relationship']) : null,
                'emergency_contact_phone' => isset($data['emergency_contact_phone']) ? $request->sanitizeString($data['emergency_contact_phone']) : null,
                'hire_date' => isset($data['hire_date']) ? $data['hire_date'] : date('Y-m-d'),
                'job_title' => $request->sanitizeString($data['job_title']),
                'department_id' => (int)$data['department_id'],
                'manager_id' => isset($data['manager_id']) ? (int)$data['manager_id'] : null,
                'is_active' => 1
            ];

            $employeeId = $this->employeeModel->createEmployee($employeeData);

            Response::created([
                'employee_id' => $employeeId,
                'name' => $employeeData['first_name'] . ' ' . $employeeData['last_name']
            ], 'Employee created successfully');

        } catch (Exception $e) {
            error_log("Create employee error: " . $e->getMessage());
            Response::error('Failed to create employee', 500);
        }
    }

    /**
     * Update employee
     */
    private function updateEmployee($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid employee ID']);
        }

        // Check authorization - users can update their own profile, admins can update any
        $currentUser = $this->authMiddleware->getCurrentUser();
        if ($currentUser['employee_id'] != $id && 
            !$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update this employee');
        }

        $data = $request->getData();

        // Check if employee exists
        $existingEmployee = $this->employeeModel->getEmployeeById($id);
        if (!$existingEmployee) {
            Response::notFound('Employee not found');
        }

        $errors = [];

        // Validate email if provided
        if (isset($data['email'])) {
            if (!$request->validateEmail($data['email'])) {
                $errors['email'] = 'Invalid email format';
            } elseif ($this->employeeModel->emailExists($data['email'], $id)) {
                $errors['email'] = 'Email already exists';
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $updateData = [];

            // Only update provided fields
            $allowedFields = [
                'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'personal_email',
                'phone_number', 'date_of_birth', 'gender', 'marital_status', 'nationality',
                'address_line1', 'address_line2', 'city', 'state_province', 'postal_code', 'country',
                'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
                'job_title', 'department_id', 'manager_id', 'is_active'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['department_id', 'manager_id', 'is_active'])) {
                        $updateData[$field] = (int)$data[$field];
                    } else {
                        $updateData[$field] = $request->sanitizeString($data[$field]);
                    }
                }
            }

            if (!empty($updateData)) {
                $this->employeeModel->updateEmployee($id, $updateData);
            }

            Response::success(null, 'Employee updated successfully');

        } catch (Exception $e) {
            error_log("Update employee error: " . $e->getMessage());
            Response::error('Failed to update employee', 500);
        }
    }

    /**
     * Delete employee (soft delete)
     */
    private function deleteEmployee($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid employee ID']);
        }

        // Check authorization - only admins can delete employees
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to delete employees');
        }

        try {
            $employee = $this->employeeModel->getEmployeeById($id);
            
            if (!$employee) {
                Response::notFound('Employee not found');
            }

            $this->employeeModel->deleteEmployee($id);

            Response::success(null, 'Employee deleted successfully');

        } catch (Exception $e) {
            error_log("Delete employee error: " . $e->getMessage());
            Response::error('Failed to delete employee', 500);
        }
    }
}

