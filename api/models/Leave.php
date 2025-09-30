<?php
/**
 * Leave Model
 * Handles leave-related database operations
 */

class Leave {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get leave request by ID
     */
    public function getLeaveRequestById($requestId) {
        $sql = "SELECT
                    lr.LeaveRequestID, lr.EmployeeID, lr.LeaveTypeID, lr.StartDate, lr.EndDate,
                    lr.DaysRequested, lr.Reason, lr.Status, lr.ApprovedBy, lr.ApprovedDate,
                    lr.Comments, lr.CreatedDate,
                    e.FirstName, e.LastName, e.Email, e.JobTitle,
                    d.DepartmentName,
                    lt.LeaveTypeName, lt.MaxDaysPerYear,
                    CONCAT(approver.FirstName, ' ', approver.LastName) AS ApproverName
                FROM LeaveRequests lr
                JOIN Employees e ON lr.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                LEFT JOIN Employees approver ON lr.ApprovedBy = approver.EmployeeID
                WHERE lr.LeaveRequestID = :request_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all leave requests with pagination
     */
    public function getLeaveRequests($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    lr.LeaveRequestID, lr.EmployeeID, lr.LeaveTypeID, lr.StartDate, lr.EndDate,
                    lr.DaysRequested, lr.Reason, lr.Status, lr.ApprovedBy, lr.ApprovedDate,
                    lr.CreatedDate,
                    e.FirstName, e.LastName, e.Email, e.JobTitle,
                    d.DepartmentName,
                    lt.LeaveTypeName
                FROM LeaveRequests lr
                JOIN Employees e ON lr.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['employee_id'])) {
            $sql .= " AND lr.EmployeeID = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND lr.Status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['leave_type_id'])) {
            $sql .= " AND lr.LeaveTypeID = :leave_type_id";
            $params[':leave_type_id'] = $filters['leave_type_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND lr.StartDate >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND lr.EndDate <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY lr.CreatedDate DESC LIMIT :limit OFFSET :offset";

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
     * Create leave request
     */
    public function createLeaveRequest($data) {
        $sql = "INSERT INTO LeaveRequests (
                    EmployeeID, LeaveTypeID, StartDate, EndDate, DaysRequested, Reason, Status
                ) VALUES (
                    :employee_id, :leave_type_id, :start_date, :end_date, :days_requested, :reason, :status
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':leave_type_id', $data['leave_type_id'], PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
        $stmt->bindParam(':days_requested', $data['days_requested'], PDO::PARAM_INT);
        $stmt->bindParam(':reason', $data['reason'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update leave request status
     */
    public function updateLeaveRequestStatus($requestId, $status, $approvedBy = null, $comments = null) {
        $sql = "UPDATE LeaveRequests SET 
                    Status = :status,
                    ApprovedBy = :approved_by,
                    ApprovedDate = :approved_date,
                    Comments = :comments
                WHERE LeaveRequestID = :request_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':approved_by', $approvedBy, PDO::PARAM_INT);
        $stmt->bindParam(':approved_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
        $stmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get leave types
     */
    public function getLeaveTypes() {
        $sql = "SELECT LeaveTypeID, LeaveTypeName, Description, MaxDaysPerYear, IsActive
                FROM LeaveTypes
                WHERE IsActive = 1
                ORDER BY LeaveTypeName";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create leave type
     */
    public function createLeaveType($data) {
        $sql = "INSERT INTO LeaveTypes (LeaveTypeName, Description, MaxDaysPerYear, IsActive)
                VALUES (:leave_type_name, :description, :max_days_per_year, :is_active)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':leave_type_name', $data['leave_type_name'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindParam(':max_days_per_year', $data['max_days_per_year'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Get employee leave balance
     */
    public function getEmployeeLeaveBalance($employeeId, $year = null) {
        if ($year === null) $year = date('Y');

        $sql = "SELECT
                    lb.LeaveTypeID, lb.AvailableDays, lb.UsedDays, lb.TotalDays,
                    lt.LeaveTypeName, lt.MaxDaysPerYear
                FROM LeaveBalances lb
                JOIN LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
                WHERE lb.EmployeeID = :employee_id AND lb.Year = :year";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

