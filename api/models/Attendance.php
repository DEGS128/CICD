<?php
/**
 * Attendance Model
 * Handles attendance-related database operations
 */

class Attendance {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get attendance record by ID
     */
    public function getAttendanceRecordById($recordId) {
        $sql = "SELECT
                    ar.RecordID, ar.EmployeeID, ar.AttendanceDate, ar.ClockInTime,
                    ar.ClockOutTime, ar.Status, ar.Notes, ar.CreatedDate,
                    e.FirstName, e.LastName, e.JobTitle,
                    d.DepartmentName
                FROM AttendanceRecords ar
                JOIN Employees e ON ar.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                WHERE ar.RecordID = :record_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':record_id', $recordId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all attendance records with pagination
     */
    public function getAttendanceRecords($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    ar.RecordID, ar.EmployeeID, ar.AttendanceDate, ar.ClockInTime,
                    ar.ClockOutTime, ar.Status, ar.Notes, ar.CreatedDate,
                    e.FirstName, e.LastName, e.JobTitle,
                    d.DepartmentName
                FROM AttendanceRecords ar
                JOIN Employees e ON ar.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['employee_id'])) {
            $sql .= " AND ar.EmployeeID = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['attendance_date'])) {
            $sql .= " AND ar.AttendanceDate = :attendance_date";
            $params[':attendance_date'] = $filters['attendance_date'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ar.AttendanceDate >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ar.AttendanceDate <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ar.Status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        $sql .= " ORDER BY ar.AttendanceDate DESC, e.LastName, e.FirstName LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total attendance records
     */
    public function countAttendanceRecords($filters = []) {
        $sql = "SELECT COUNT(*) as total 
                FROM AttendanceRecords ar
                JOIN Employees e ON ar.EmployeeID = e.EmployeeID
                WHERE 1=1";
        $params = [];

        // Apply same filters as getAttendanceRecords
        if (!empty($filters['employee_id'])) {
            $sql .= " AND ar.EmployeeID = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['attendance_date'])) {
            $sql .= " AND ar.AttendanceDate = :attendance_date";
            $params[':attendance_date'] = $filters['attendance_date'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ar.AttendanceDate >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ar.AttendanceDate <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ar.Status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }

    /**
     * Create attendance record
     */
    public function createAttendanceRecord($data) {
        $sql = "INSERT INTO AttendanceRecords (
                    EmployeeID, AttendanceDate, ClockInTime, ClockOutTime, Status, Notes
                ) VALUES (
                    :employee_id, :attendance_date, :clock_in_time, :clock_out_time, :status, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':attendance_date', $data['attendance_date'], PDO::PARAM_STR);
        $stmt->bindParam(':clock_in_time', $data['clock_in_time'], PDO::PARAM_STR);
        $stmt->bindParam(':clock_out_time', $data['clock_out_time'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update attendance record
     */
    public function updateAttendanceRecord($recordId, $data) {
        $sql = "UPDATE AttendanceRecords SET 
                    AttendanceDate = :attendance_date,
                    ClockInTime = :clock_in_time,
                    ClockOutTime = :clock_out_time,
                    Status = :status,
                    Notes = :notes
                WHERE RecordID = :record_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':attendance_date', $data['attendance_date'], PDO::PARAM_STR);
        $stmt->bindParam(':clock_in_time', $data['clock_in_time'], PDO::PARAM_STR);
        $stmt->bindParam(':clock_out_time', $data['clock_out_time'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        $stmt->bindParam(':record_id', $recordId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete attendance record
     */
    public function deleteAttendanceRecord($recordId) {
        $sql = "DELETE FROM AttendanceRecords WHERE RecordID = :record_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':record_id', $recordId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Clock in employee
     */
    public function clockIn($employeeId, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Check if already clocked in today
        $checkSql = "SELECT RecordID FROM AttendanceRecords 
                     WHERE EmployeeID = :employee_id AND AttendanceDate = :date AND ClockOutTime IS NULL";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $checkStmt->bindParam(':date', $date, PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            throw new Exception('Employee has already clocked in today');
        }

        $data = [
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'clock_in_time' => date('H:i:s'),
            'clock_out_time' => null,
            'status' => 'Present',
            'notes' => null
        ];

        return $this->createAttendanceRecord($data);
    }

    /**
     * Clock out employee
     */
    public function clockOut($employeeId, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // Find today's attendance record
        $findSql = "SELECT RecordID FROM AttendanceRecords 
                    WHERE EmployeeID = :employee_id AND AttendanceDate = :date AND ClockOutTime IS NULL";
        $findStmt = $this->pdo->prepare($findSql);
        $findStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $findStmt->bindParam(':date', $date, PDO::PARAM_STR);
        $findStmt->execute();
        $record = $findStmt->fetch();

        if (!$record) {
            throw new Exception('No clock-in record found for today');
        }

        $updateData = [
            'attendance_date' => $date,
            'clock_in_time' => null, // Will be preserved
            'clock_out_time' => date('H:i:s'),
            'status' => 'Present',
            'notes' => null
        ];

        return $this->updateAttendanceRecord($record['RecordID'], $updateData);
    }

    /**
     * Get employee attendance summary
     */
    public function getEmployeeAttendanceSummary($employeeId, $month = null, $year = null) {
        if ($month === null) $month = date('n');
        if ($year === null) $year = date('Y');

        $sql = "SELECT
                    COUNT(*) as total_days,
                    SUM(CASE WHEN Status = 'Present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN Status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN Status = 'Late' THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN Status = 'Half Day' THEN 1 ELSE 0 END) as half_days
                FROM AttendanceRecords
                WHERE EmployeeID = :employee_id
                AND MONTH(AttendanceDate) = :month
                AND YEAR(AttendanceDate) = :year";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance statistics
     */
    public function getAttendanceStatistics($filters = []) {
        $sql = "SELECT
                    COUNT(*) as total_records,
                    SUM(CASE WHEN Status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN Status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN Status = 'Late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN Status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count
                FROM AttendanceRecords ar
                JOIN Employees e ON ar.EmployeeID = e.EmployeeID
                WHERE 1=1";

        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= " AND ar.AttendanceDate >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ar.AttendanceDate <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

