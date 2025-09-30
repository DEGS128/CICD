<?php
/**
 * Attendance Routes
 * Handles attendance management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/Attendance.php';

class AttendanceController {
    private $pdo;
    private $authMiddleware;
    private $attendanceModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->attendanceModel = new Attendance();
    }

    /**
     * Handle attendance requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    if ($subResource === 'statistics') {
                        $this->getAttendanceStatistics();
                    } else {
                        $this->getAttendanceRecords();
                    }
                } elseif ($subResource === 'summary') {
                    $this->getEmployeeAttendanceSummary($id);
                } else {
                    $this->getAttendanceRecord($id);
                }
                break;
            case 'POST':
                if ($subResource === 'clock-in') {
                    $this->clockIn();
                } elseif ($subResource === 'clock-out') {
                    $this->clockOut();
                } else {
                    $this->createAttendanceRecord();
                }
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateAttendanceRecord($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteAttendanceRecord($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all attendance records
     */
    private function getAttendanceRecords() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'employee_id' => $request->getData('employee_id'),
            'attendance_date' => $request->getData('attendance_date'),
            'date_from' => $request->getData('date_from'),
            'date_to' => $request->getData('date_to'),
            'status' => $request->getData('status'),
            'department_id' => $request->getData('department_id')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $attendanceRecords = $this->attendanceModel->getAttendanceRecords(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->attendanceModel->countAttendanceRecords($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($attendanceRecords, $paginationData);

        } catch (Exception $e) {
            error_log("Get attendance records error: " . $e->getMessage());
            Response::error('Failed to retrieve attendance records', 500);
        }
    }

    /**
     * Get single attendance record
     */
    private function getAttendanceRecord($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid attendance record ID']);
        }

        try {
            $attendanceRecord = $this->attendanceModel->getAttendanceRecordById($id);
            
            if (!$attendanceRecord) {
                Response::notFound('Attendance record not found');
            }

            Response::success($attendanceRecord);

        } catch (Exception $e) {
            error_log("Get attendance record error: " . $e->getMessage());
            Response::error('Failed to retrieve attendance record', 500);
        }
    }

    /**
     * Get attendance statistics
     */
    private function getAttendanceStatistics() {
        $request = new Request();
        
        $filters = [
            'date_from' => $request->getData('date_from'),
            'date_to' => $request->getData('date_to'),
            'department_id' => $request->getData('department_id')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $statistics = $this->attendanceModel->getAttendanceStatistics($filters);
            Response::success($statistics);

        } catch (Exception $e) {
            error_log("Get attendance statistics error: " . $e->getMessage());
            Response::error('Failed to retrieve attendance statistics', 500);
        }
    }

    /**
     * Get employee attendance summary
     */
    private function getEmployeeAttendanceSummary($employeeId) {
        $request = new Request();
        if (!$request->validateInteger($employeeId)) {
            Response::validationError(['employee_id' => 'Invalid employee ID']);
        }

        $month = $request->getData('month') ?: date('n');
        $year = $request->getData('year') ?: date('Y');

        try {
            $summary = $this->attendanceModel->getEmployeeAttendanceSummary($employeeId, $month, $year);
            Response::success($summary);

        } catch (Exception $e) {
            error_log("Get employee attendance summary error: " . $e->getMessage());
            Response::error('Failed to retrieve employee attendance summary', 500);
        }
    }

    /**
     * Clock in employee
     */
    private function clockIn() {
        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['employee_id']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $employeeId = (int)$data['employee_id'];
        $date = isset($data['date']) ? $data['date'] : null;

        try {
            $recordId = $this->attendanceModel->clockIn($employeeId, $date);

            Response::created([
                'record_id' => $recordId,
                'employee_id' => $employeeId,
                'clock_in_time' => date('H:i:s')
            ], 'Employee clocked in successfully');

        } catch (Exception $e) {
            error_log("Clock in error: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Clock out employee
     */
    private function clockOut() {
        $request = new Request();
        $data = $request->getData();

        $errors = $request->validateRequired(['employee_id']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $employeeId = (int)$data['employee_id'];
        $date = isset($data['date']) ? $data['date'] : null;

        try {
            $this->attendanceModel->clockOut($employeeId, $date);

            Response::success([
                'employee_id' => $employeeId,
                'clock_out_time' => date('H:i:s')
            ], 'Employee clocked out successfully');

        } catch (Exception $e) {
            error_log("Clock out error: " . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Create new attendance record
     */
    private function createAttendanceRecord() {
        // Check authorization - only admins and HR can create attendance records
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create attendance records');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired(['employee_id', 'attendance_date']);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['attendance_date'])) {
            $errors['attendance_date'] = 'Attendance date must be in YYYY-MM-DD format';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $attendanceData = [
                'employee_id' => (int)$data['employee_id'],
                'attendance_date' => $data['attendance_date'],
                'clock_in_time' => isset($data['clock_in_time']) ? $data['clock_in_time'] : null,
                'clock_out_time' => isset($data['clock_out_time']) ? $data['clock_out_time'] : null,
                'status' => isset($data['status']) ? $request->sanitizeString($data['status']) : 'Present',
                'notes' => isset($data['notes']) ? $request->sanitizeString($data['notes']) : null
            ];

            $recordId = $this->attendanceModel->createAttendanceRecord($attendanceData);

            Response::created([
                'record_id' => $recordId,
                'employee_id' => $attendanceData['employee_id'],
                'attendance_date' => $attendanceData['attendance_date']
            ], 'Attendance record created successfully');

        } catch (Exception $e) {
            error_log("Create attendance record error: " . $e->getMessage());
            Response::error('Failed to create attendance record', 500);
        }
    }

    /**
     * Update attendance record
     */
    private function updateAttendanceRecord($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid attendance record ID']);
        }

        // Check authorization - only admins and HR can update attendance records
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update attendance records');
        }

        $data = $request->getData();

        // Check if attendance record exists
        $existingRecord = $this->attendanceModel->getAttendanceRecordById($id);
        if (!$existingRecord) {
            Response::notFound('Attendance record not found');
        }

        try {
            $updateData = [];

            // Only update provided fields
            $allowedFields = [
                'attendance_date', 'clock_in_time', 'clock_out_time', 'status', 'notes'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $request->sanitizeString($data[$field]);
                }
            }

            if (!empty($updateData)) {
                $this->attendanceModel->updateAttendanceRecord($id, $updateData);
            }

            Response::success(null, 'Attendance record updated successfully');

        } catch (Exception $e) {
            error_log("Update attendance record error: " . $e->getMessage());
            Response::error('Failed to update attendance record', 500);
        }
    }

    /**
     * Delete attendance record
     */
    private function deleteAttendanceRecord($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid attendance record ID']);
        }

        // Check authorization - only admins can delete attendance records
        if (!$this->authMiddleware->hasAnyRole(['System Admin'])) {
            Response::forbidden('Insufficient permissions to delete attendance records');
        }

        try {
            $attendanceRecord = $this->attendanceModel->getAttendanceRecordById($id);
            
            if (!$attendanceRecord) {
                Response::notFound('Attendance record not found');
            }

            $this->attendanceModel->deleteAttendanceRecord($id);

            Response::success(null, 'Attendance record deleted successfully');

        } catch (Exception $e) {
            error_log("Delete attendance record error: " . $e->getMessage());
            Response::error('Failed to delete attendance record', 500);
        }
    }
}
