<?php
/**
 * HMO Routes
 * Handles HMO management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/HMO.php';

class HMOController {
    private $pdo;
    private $authMiddleware;
    private $hmoModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->hmoModel = new HMO();
    }

    /**
     * Handle HMO requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    if ($subResource === 'providers') {
                        $this->getHMOProviders();
                    } elseif ($subResource === 'enrollments') {
                        $this->getHMOEnrollments();
                    } else {
                        $this->getHMOPlans();
                    }
                } else {
                    $this->getHMOPlan($id);
                }
                break;
            case 'POST':
                if ($subResource === 'enrollments') {
                    $this->createHMOEnrollment();
                } else {
                    $this->createHMOPlan();
                }
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateHMOPlan($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteHMOPlan($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all HMO plans
     */
    private function getHMOPlans() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'provider_id' => $request->getData('provider_id'),
            'is_active' => $request->getData('is_active')
        ];

        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $hmoPlans = $this->hmoModel->getHMOPlans(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            Response::success($hmoPlans);

        } catch (Exception $e) {
            error_log("Get HMO plans error: " . $e->getMessage());
            Response::error('Failed to retrieve HMO plans', 500);
        }
    }

    /**
     * Get single HMO plan
     */
    private function getHMOPlan($id) {
        Response::success([], 'HMO plan details endpoint - implementation pending');
    }

    /**
     * Get HMO providers
     */
    private function getHMOProviders() {
        try {
            $providers = $this->hmoModel->getHMOProviders();
            Response::success($providers);

        } catch (Exception $e) {
            error_log("Get HMO providers error: " . $e->getMessage());
            Response::error('Failed to retrieve HMO providers', 500);
        }
    }

    /**
     * Get HMO enrollments
     */
    private function getHMOEnrollments() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'employee_id' => $request->getData('employee_id'),
            'status' => $request->getData('status')
        ];

        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $enrollments = $this->hmoModel->getHMOEnrollments(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            Response::success($enrollments);

        } catch (Exception $e) {
            error_log("Get HMO enrollments error: " . $e->getMessage());
            Response::error('Failed to retrieve HMO enrollments', 500);
        }
    }

    /**
     * Create new HMO plan
     */
    private function createHMOPlan() {
        Response::success([], 'Create HMO plan endpoint - implementation pending');
    }

    /**
     * Create new HMO enrollment
     */
    private function createHMOEnrollment() {
        // Check authorization - only admins and HR can create HMO enrollments
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create HMO enrollments');
        }

        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['employee_id', 'plan_id', 'monthly_deduction']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $enrollmentData = [
                'employee_id' => (int)$data['employee_id'],
                'plan_id' => (int)$data['plan_id'],
                'status' => 'Active',
                'monthly_deduction' => $data['monthly_deduction'],
                'enrollment_date' => isset($data['enrollment_date']) ? $data['enrollment_date'] : date('Y-m-d'),
                'effective_date' => isset($data['effective_date']) ? $data['effective_date'] : date('Y-m-d')
            ];

            $enrollmentId = $this->hmoModel->createHMOEnrollment($enrollmentData);

            Response::created([
                'enrollment_id' => $enrollmentId,
                'employee_id' => $enrollmentData['employee_id'],
                'plan_id' => $enrollmentData['plan_id']
            ], 'HMO enrollment created successfully');

        } catch (Exception $e) {
            error_log("Create HMO enrollment error: " . $e->getMessage());
            Response::error('Failed to create HMO enrollment', 500);
        }
    }

    /**
     * Update HMO plan
     */
    private function updateHMOPlan($id) {
        Response::success([], 'Update HMO plan endpoint - implementation pending');
    }

    /**
     * Delete HMO plan
     */
    private function deleteHMOPlan($id) {
        Response::success([], 'Delete HMO plan endpoint - implementation pending');
    }
}
