<?php
/**
 * Payroll Routes
 * Handles payroll management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Payroll.php';

class PayrollController {
    private $pdo;
    private $authMiddleware;
    private $payrollModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->payrollModel = new Payroll();
    }

    /**
     * Handle payroll requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    $this->getPayrollRuns();
                } elseif ($subResource === 'payslips') {
                    $this->getPayslips($id);
                } elseif ($subResource === 'process') {
                    $this->processPayrollRun($id);
                } else {
                    $this->getPayrollRun($id);
                }
                break;
            case 'POST':
                if ($subResource === 'process') {
                    $this->processPayrollRun($id);
                } else {
                    $this->createPayrollRun();
                }
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updatePayrollRun($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deletePayrollRun($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all payroll runs
     */
    private function getPayrollRuns() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'status' => $request->getData('status'),
            'pay_period_start' => $request->getData('pay_period_start'),
            'pay_period_end' => $request->getData('pay_period_end')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $payrollRuns = $this->payrollModel->getPayrollRuns(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->payrollModel->countPayrollRuns($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($payrollRuns, $paginationData);

        } catch (Exception $e) {
            error_log("Get payroll runs error: " . $e->getMessage());
            Response::error('Failed to retrieve payroll runs', 500);
        }
    }

    /**
     * Get single payroll run
     */
    private function getPayrollRun($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid payroll run ID']);
        }

        try {
            $payrollRun = $this->payrollModel->getPayrollRunById($id);
            
            if (!$payrollRun) {
                Response::notFound('Payroll run not found');
            }

            Response::success($payrollRun);

        } catch (Exception $e) {
            error_log("Get payroll run error: " . $e->getMessage());
            Response::error('Failed to retrieve payroll run', 500);
        }
    }

    /**
     * Get payslips for a payroll run
     */
    private function getPayslips($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid payroll run ID']);
        }

        try {
            $payslips = $this->payrollModel->getPayslipsByPayrollRun($id);
            Response::success($payslips);

        } catch (Exception $e) {
            error_log("Get payslips error: " . $e->getMessage());
            Response::error('Failed to retrieve payslips', 500);
        }
    }

    /**
     * Process payroll run
     */
    private function processPayrollRun($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid payroll run ID']);
        }

        // Check authorization - only admins and HR can process payroll
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to process payroll');
        }

        try {
            $payrollRun = $this->payrollModel->getPayrollRunById($id);
            
            if (!$payrollRun) {
                Response::notFound('Payroll run not found');
            }

            if ($payrollRun['Status'] === 'Completed') {
                Response::error('Payroll run has already been processed', 400);
            }

            $this->payrollModel->processPayrollRun($id);

            Response::success(null, 'Payroll run processed successfully');

        } catch (Exception $e) {
            error_log("Process payroll run error: " . $e->getMessage());
            Response::error('Failed to process payroll run', 500);
        }
    }

    /**
     * Create new payroll run
     */
    private function createPayrollRun() {
        // Check authorization - only admins and HR can create payroll runs
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create payroll runs');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['pay_period_start', 'pay_period_end', 'pay_date']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['pay_period_start'])) {
            $errors['pay_period_start'] = 'Pay period start must be in YYYY-MM-DD format';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['pay_period_end'])) {
            $errors['pay_period_end'] = 'Pay period end must be in YYYY-MM-DD format';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['pay_date'])) {
            $errors['pay_date'] = 'Pay date must be in YYYY-MM-DD format';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $payrollData = [
                'pay_period_start' => $data['pay_period_start'],
                'pay_period_end' => $data['pay_period_end'],
                'pay_date' => $data['pay_date'],
                'status' => 'Draft',
                'notes' => isset($data['notes']) ? $request->sanitizeString($data['notes']) : null
            ];

            $payrollId = $this->payrollModel->createPayrollRun($payrollData);

            Response::created([
                'payroll_id' => $payrollId,
                'pay_period_start' => $payrollData['pay_period_start'],
                'pay_period_end' => $payrollData['pay_period_end']
            ], 'Payroll run created successfully');

        } catch (Exception $e) {
            error_log("Create payroll run error: " . $e->getMessage());
            Response::error('Failed to create payroll run', 500);
        }
    }

    /**
     * Update payroll run
     */
    private function updatePayrollRun($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid payroll run ID']);
        }

        // Check authorization - only admins and HR can update payroll runs
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update payroll runs');
        }

        $data = $request->getData();

        // Check if payroll run exists
        $existingPayrollRun = $this->payrollModel->getPayrollRunById($id);
        if (!$existingPayrollRun) {
            Response::notFound('Payroll run not found');
        }

        try {
            $updateData = [];

            // Only update provided fields
            $allowedFields = [
                'pay_period_start', 'pay_period_end', 'pay_date', 'status', 'notes'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $request->sanitizeString($data[$field]);
                }
            }

            if (!empty($updateData)) {
                $this->payrollModel->updatePayrollRun($id, $updateData);
            }

            Response::success(null, 'Payroll run updated successfully');

        } catch (Exception $e) {
            error_log("Update payroll run error: " . $e->getMessage());
            Response::error('Failed to update payroll run', 500);
        }
    }

    /**
     * Delete payroll run
     */
    private function deletePayrollRun($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid payroll run ID']);
        }

        // Check authorization - only admins can delete payroll runs
        if (!$this->authMiddleware->hasAnyRole(['System Admin'])) {
            Response::forbidden('Insufficient permissions to delete payroll runs');
        }

        try {
            $payrollRun = $this->payrollModel->getPayrollRunById($id);
            
            if (!$payrollRun) {
                Response::notFound('Payroll run not found');
            }

            if ($payrollRun['Status'] === 'Completed') {
                Response::error('Cannot delete completed payroll run', 400);
            }

            $this->payrollModel->deletePayrollRun($id);

            Response::success(null, 'Payroll run deleted successfully');

        } catch (Exception $e) {
            error_log("Delete payroll run error: " . $e->getMessage());
            Response::error('Failed to delete payroll run', 500);
        }
    }
}
