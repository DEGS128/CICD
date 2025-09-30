<?php
/**
 * Benefits Routes
 * Handles benefits management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Benefit.php';

class BenefitsController {
    private $pdo;
    private $authMiddleware;
    private $benefitModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->benefitModel = new Benefit();
    }

    /**
     * Handle benefits requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    if ($subResource === 'categories') {
                        $this->getBenefitCategories();
                    } else {
                        $this->getBenefits();
                    }
                } elseif ($subResource === 'employees') {
                    $this->getEmployeeBenefits($id);
                } else {
                    $this->getBenefit($id);
                }
                break;
            case 'POST':
                if ($subResource === 'categories') {
                    $this->createBenefitCategory();
                } elseif ($subResource === 'assign') {
                    $this->assignBenefitToEmployee();
                } else {
                    $this->createBenefit();
                }
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateBenefit($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteBenefit($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all benefits
     */
    private function getBenefits() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'benefit_type' => $request->getData('benefit_type'),
            'is_active' => $request->getData('is_active'),
            'search' => $request->getData('search')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $benefits = $this->benefitModel->getBenefits(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->benefitModel->countBenefits($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($benefits, $paginationData);

        } catch (Exception $e) {
            error_log("Get benefits error: " . $e->getMessage());
            Response::error('Failed to retrieve benefits', 500);
        }
    }

    /**
     * Get single benefit
     */
    private function getBenefit($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid benefit ID']);
        }

        try {
            $benefit = $this->benefitModel->getBenefitById($id);
            
            if (!$benefit) {
                Response::notFound('Benefit not found');
            }

            Response::success($benefit);

        } catch (Exception $e) {
            error_log("Get benefit error: " . $e->getMessage());
            Response::error('Failed to retrieve benefit', 500);
        }
    }

    /**
     * Get benefit categories
     */
    private function getBenefitCategories() {
        try {
            $categories = $this->benefitModel->getBenefitCategories();
            Response::success($categories);

        } catch (Exception $e) {
            error_log("Get benefit categories error: " . $e->getMessage());
            Response::error('Failed to retrieve benefit categories', 500);
        }
    }

    /**
     * Get employee benefits
     */
    private function getEmployeeBenefits($employeeId) {
        $request = new Request();
        if (!$request->validateInteger($employeeId)) {
            Response::validationError(['employee_id' => 'Invalid employee ID']);
        }

        try {
            $benefits = $this->benefitModel->getEmployeeBenefits($employeeId);
            Response::success($benefits);

        } catch (Exception $e) {
            error_log("Get employee benefits error: " . $e->getMessage());
            Response::error('Failed to retrieve employee benefits', 500);
        }
    }

    /**
     * Create new benefit
     */
    private function createBenefit() {
        // Check authorization - only admins and HR can create benefits
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create benefits');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['benefit_name', 'benefit_type', 'amount']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Additional validation
        $amount = $data['amount'];
        if (!is_numeric($amount) || $amount < 0) {
            $errors['amount'] = 'Amount must be a positive number';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $benefitData = [
                'benefit_name' => $request->sanitizeString($data['benefit_name']),
                'description' => isset($data['description']) ? $request->sanitizeString($data['description']) : null,
                'benefit_type' => (int)$data['benefit_type'],
                'amount' => $amount,
                'is_percentage' => isset($data['is_percentage']) ? (int)$data['is_percentage'] : 0,
                'is_active' => 1
            ];

            $benefitId = $this->benefitModel->createBenefit($benefitData);

            Response::created([
                'benefit_id' => $benefitId,
                'benefit_name' => $benefitData['benefit_name']
            ], 'Benefit created successfully');

        } catch (Exception $e) {
            error_log("Create benefit error: " . $e->getMessage());
            Response::error('Failed to create benefit', 500);
        }
    }

    /**
     * Create benefit category
     */
    private function createBenefitCategory() {
        // Check authorization - only admins can create categories
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create benefit categories');
        }

        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['category_name']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $categoryData = [
                'category_name' => $request->sanitizeString($data['category_name']),
                'category_description' => isset($data['category_description']) ? $request->sanitizeString($data['category_description']) : null,
                'is_active' => 1
            ];

            $categoryId = $this->benefitModel->createBenefitCategory($categoryData);

            Response::created([
                'category_id' => $categoryId,
                'category_name' => $categoryData['category_name']
            ], 'Benefit category created successfully');

        } catch (Exception $e) {
            error_log("Create benefit category error: " . $e->getMessage());
            Response::error('Failed to create benefit category', 500);
        }
    }

    /**
     * Assign benefit to employee
     */
    private function assignBenefitToEmployee() {
        // Check authorization - only admins and HR can assign benefits
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to assign benefits');
        }

        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['employee_id', 'benefit_id', 'benefit_amount']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $assignmentData = [
                'employee_id' => (int)$data['employee_id'],
                'benefit_id' => (int)$data['benefit_id'],
                'benefit_amount' => $data['benefit_amount'],
                'start_date' => isset($data['start_date']) ? $data['start_date'] : date('Y-m-d'),
                'end_date' => isset($data['end_date']) ? $data['end_date'] : null,
                'status' => isset($data['status']) ? $request->sanitizeString($data['status']) : 'Active',
                'notes' => isset($data['notes']) ? $request->sanitizeString($data['notes']) : null
            ];

            $assignmentId = $this->benefitModel->assignBenefitToEmployee($assignmentData);

            Response::created([
                'assignment_id' => $assignmentId,
                'employee_id' => $assignmentData['employee_id'],
                'benefit_id' => $assignmentData['benefit_id']
            ], 'Benefit assigned to employee successfully');

        } catch (Exception $e) {
            error_log("Assign benefit error: " . $e->getMessage());
            Response::error('Failed to assign benefit to employee', 500);
        }
    }

    /**
     * Update benefit
     */
    private function updateBenefit($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid benefit ID']);
        }

        // Check authorization - only admins and HR can update benefits
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update benefits');
        }

        $data = $request->getData();

        // Check if benefit exists
        $existingBenefit = $this->benefitModel->getBenefitById($id);
        if (!$existingBenefit) {
            Response::notFound('Benefit not found');
        }

        try {
            $updateData = [];

            // Only update provided fields
            $allowedFields = [
                'benefit_name', 'description', 'benefit_type', 'amount', 'is_percentage', 'is_active'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if (in_array($field, ['benefit_type', 'is_percentage', 'is_active'])) {
                        $updateData[$field] = (int)$data[$field];
                    } else {
                        $updateData[$field] = $request->sanitizeString($data[$field]);
                    }
                }
            }

            if (!empty($updateData)) {
                $this->benefitModel->updateBenefit($id, $updateData);
            }

            Response::success(null, 'Benefit updated successfully');

        } catch (Exception $e) {
            error_log("Update benefit error: " . $e->getMessage());
            Response::error('Failed to update benefit', 500);
        }
    }

    /**
     * Delete benefit (soft delete)
     */
    private function deleteBenefit($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid benefit ID']);
        }

        // Check authorization - only admins can delete benefits
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to delete benefits');
        }

        try {
            $benefit = $this->benefitModel->getBenefitById($id);
            
            if (!$benefit) {
                Response::notFound('Benefit not found');
            }

            $this->benefitModel->deleteBenefit($id);

            Response::success(null, 'Benefit deleted successfully');

        } catch (Exception $e) {
            error_log("Delete benefit error: " . $e->getMessage());
            Response::error('Failed to delete benefit', 500);
        }
    }
}
