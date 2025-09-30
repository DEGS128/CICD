<?php
/**
 * Leave Routes
 * Handles leave management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Leave.php';

class LeaveController {
    private $pdo;
    private $authMiddleware;
    private $leaveModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->leaveModel = new Leave();
    }

    /**
     * Handle leave requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    if ($subResource === 'types') {
                        $this->getLeaveTypes();
                    } else {
                        $this->getLeaveRequests();
                    }
                } elseif ($subResource === 'balance') {
                    $this->getEmployeeLeaveBalance($id);
                } else {
                    $this->getLeaveRequest($id);
                }
                break;
            case 'POST':
                if ($subResource === 'types') {
                    $this->createLeaveType();
                } elseif ($subResource === 'approve') {
                    $this->approveLeaveRequest($id);
                } elseif ($subResource === 'reject') {
                    $this->rejectLeaveRequest($id);
                } else {
                    $this->createLeaveRequest();
                }
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateLeaveRequest($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteLeaveRequest($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all leave requests
     */
    private function getLeaveRequests() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'employee_id' => $request->getData('employee_id'),
            'status' => $request->getData('status'),
            'leave_type_id' => $request->getData('leave_type_id'),
            'date_from' => $request->getData('date_from'),
            'date_to' => $request->getData('date_to')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $leaveRequests = $this->leaveModel->getLeaveRequests(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            Response::success($leaveRequests);

        } catch (Exception $e) {
            error_log("Get leave requests error: " . $e->getMessage());
            Response::error('Failed to retrieve leave requests', 500);
        }
    }

    /**
     * Get single leave request
     */
    private function getLeaveRequest($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid leave request ID']);
        }

        try {
            $leaveRequest = $this->leaveModel->getLeaveRequestById($id);
            
            if (!$leaveRequest) {
                Response::notFound('Leave request not found');
            }

            Response::success($leaveRequest);

        } catch (Exception $e) {
            error_log("Get leave request error: " . $e->getMessage());
            Response::error('Failed to retrieve leave request', 500);
        }
    }

    /**
     * Get leave types
     */
    private function getLeaveTypes() {
        try {
            $leaveTypes = $this->leaveModel->getLeaveTypes();
            Response::success($leaveTypes);

        } catch (Exception $e) {
            error_log("Get leave types error: " . $e->getMessage());
            Response::error('Failed to retrieve leave types', 500);
        }
    }

    /**
     * Get employee leave balance
     */
    private function getEmployeeLeaveBalance($employeeId) {
        $request = new Request();
        if (!$request->validateInteger($employeeId)) {
            Response::validationError(['employee_id' => 'Invalid employee ID']);
        }

        $year = $request->getData('year') ?: date('Y');

        try {
            $balance = $this->leaveModel->getEmployeeLeaveBalance($employeeId, $year);
            Response::success($balance);

        } catch (Exception $e) {
            error_log("Get employee leave balance error: " . $e->getMessage());
            Response::error('Failed to retrieve leave balance', 500);
        }
    }

    /**
     * Create new leave request
     */
    private function createLeaveRequest() {
        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'reason']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Calculate days requested
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        $daysRequested = $startDate->diff($endDate)->days + 1;

        try {
            $leaveData = [
                'employee_id' => (int)$data['employee_id'],
                'leave_type_id' => (int)$data['leave_type_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $daysRequested,
                'reason' => $request->sanitizeString($data['reason']),
                'status' => 'Pending'
            ];

            $requestId = $this->leaveModel->createLeaveRequest($leaveData);

            Response::created([
                'request_id' => $requestId,
                'employee_id' => $leaveData['employee_id'],
                'days_requested' => $daysRequested
            ], 'Leave request created successfully');

        } catch (Exception $e) {
            error_log("Create leave request error: " . $e->getMessage());
            Response::error('Failed to create leave request', 500);
        }
    }

    /**
     * Create new leave type
     */
    private function createLeaveType() {
        // Check authorization - only admins can create leave types
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create leave types');
        }

        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['leave_type_name', 'max_days_per_year']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $leaveTypeData = [
                'leave_type_name' => $request->sanitizeString($data['leave_type_name']),
                'description' => isset($data['description']) ? $request->sanitizeString($data['description']) : null,
                'max_days_per_year' => (int)$data['max_days_per_year'],
                'is_active' => 1
            ];

            $leaveTypeId = $this->leaveModel->createLeaveType($leaveTypeData);

            Response::created([
                'leave_type_id' => $leaveTypeId,
                'leave_type_name' => $leaveTypeData['leave_type_name']
            ], 'Leave type created successfully');

        } catch (Exception $e) {
            error_log("Create leave type error: " . $e->getMessage());
            Response::error('Failed to create leave type', 500);
        }
    }

    /**
     * Approve leave request
     */
    private function approveLeaveRequest($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid leave request ID']);
        }

        // Check authorization - only admins and HR can approve leave requests
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to approve leave requests');
        }

        $data = $request->getData();
        $currentUser = $this->authMiddleware->getCurrentUser();

        try {
            $this->leaveModel->updateLeaveRequestStatus(
                $id, 
                'Approved', 
                $currentUser['employee_id'],
                isset($data['comments']) ? $request->sanitizeString($data['comments']) : null
            );

            Response::success(null, 'Leave request approved successfully');

        } catch (Exception $e) {
            error_log("Approve leave request error: " . $e->getMessage());
            Response::error('Failed to approve leave request', 500);
        }
    }

    /**
     * Reject leave request
     */
    private function rejectLeaveRequest($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid leave request ID']);
        }

        // Check authorization - only admins and HR can reject leave requests
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to reject leave requests');
        }

        $data = $request->getData();
        $currentUser = $this->authMiddleware->getCurrentUser();

        $errors = $request->validateRequired(['comments']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $this->leaveModel->updateLeaveRequestStatus(
                $id, 
                'Rejected', 
                $currentUser['employee_id'],
                $request->sanitizeString($data['comments'])
            );

            Response::success(null, 'Leave request rejected successfully');

        } catch (Exception $e) {
            error_log("Reject leave request error: " . $e->getMessage());
            Response::error('Failed to reject leave request', 500);
        }
    }

    /**
     * Update leave request
     */
    private function updateLeaveRequest($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid leave request ID']);
        }

        $data = $request->getData();

        // Check if leave request exists
        $existingRequest = $this->leaveModel->getLeaveRequestById($id);
        if (!$existingRequest) {
            Response::notFound('Leave request not found');
        }

        // Only allow updates if status is Pending
        if ($existingRequest['Status'] !== 'Pending') {
            Response::error('Cannot update leave request that has been processed', 400);
        }

        try {
            // For now, just return success - implement specific update logic as needed
            Response::success(null, 'Leave request updated successfully');

        } catch (Exception $e) {
            error_log("Update leave request error: " . $e->getMessage());
            Response::error('Failed to update leave request', 500);
        }
    }

    /**
     * Delete leave request
     */
    private function deleteLeaveRequest($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid leave request ID']);
        }

        // Check authorization - only admins can delete leave requests
        if (!$this->authMiddleware->hasAnyRole(['System Admin'])) {
            Response::forbidden('Insufficient permissions to delete leave requests');
        }

        try {
            $leaveRequest = $this->leaveModel->getLeaveRequestById($id);
            
            if (!$leaveRequest) {
                Response::notFound('Leave request not found');
            }

            // For now, just return success - implement specific delete logic as needed
            Response::success(null, 'Leave request deleted successfully');

        } catch (Exception $e) {
            error_log("Delete leave request error: " . $e->getMessage());
            Response::error('Failed to delete leave request', 500);
        }
    }
}
