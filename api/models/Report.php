<?php
/**
 * Report Model
 * Handles report generation and analytics
 */

class Report {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get employee summary report
     */
    public function getEmployeeSummaryReport($filters = []) {
        $sql = "SELECT
                    COUNT(*) as total_employees,
                    SUM(CASE WHEN e.IsActive = 1 THEN 1 ELSE 0 END) as active_employees,
                    SUM(CASE WHEN e.IsActive = 0 THEN 1 ELSE 0 END) as inactive_employees,
                    AVG(s.BaseSalary) as average_salary,
                    MAX(s.BaseSalary) as highest_salary,
                    MIN(s.BaseSalary) as lowest_salary
                FROM Employees e
                LEFT JOIN Salaries s ON e.EmployeeID = s.EmployeeID AND s.Status = 'Active'";

        $params = [];

        if (!empty($filters['department_id'])) {
            $sql .= " WHERE e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary report
     */
    public function getAttendanceSummaryReport($filters = []) {
        $sql = "SELECT
                    COUNT(*) as total_records,
                    SUM(CASE WHEN ar.Status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ar.Status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ar.Status = 'Late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN ar.Status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count,
                    ROUND((SUM(CASE WHEN ar.Status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate
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

    /**
     * Get payroll summary report
     */
    public function getPayrollSummaryReport($filters = []) {
        $sql = "SELECT
                    COUNT(DISTINCT pr.PayrollID) as total_payroll_runs,
                    SUM(pr.TotalGrossPay) as total_gross_pay,
                    SUM(pr.TotalDeductions) as total_deductions,
                    SUM(pr.TotalNetPay) as total_net_pay,
                    AVG(pr.TotalNetPay) as average_net_pay
                FROM PayrollRuns pr
                WHERE 1=1";

        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= " AND pr.PayPeriodStart >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND pr.PayPeriodEnd <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get leave summary report
     */
    public function getLeaveSummaryReport($filters = []) {
        $sql = "SELECT
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN lr.Status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN lr.Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
                    SUM(CASE WHEN lr.Status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(lr.DaysRequested) as total_days_requested,
                    AVG(lr.DaysRequested) as average_days_per_request
                FROM LeaveRequests lr
                JOIN Employees e ON lr.EmployeeID = e.EmployeeID
                WHERE 1=1";

        $params = [];

        if (!empty($filters['date_from'])) {
            $sql .= " AND lr.StartDate >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND lr.EndDate <= :date_to";
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

    /**
     * Get department-wise employee count
     */
    public function getDepartmentWiseEmployeeCount() {
        $sql = "SELECT
                    d.DepartmentName,
                    COUNT(e.EmployeeID) as employee_count,
                    SUM(CASE WHEN e.IsActive = 1 THEN 1 ELSE 0 END) as active_count
                FROM OrganizationalStructure d
                LEFT JOIN Employees e ON d.DepartmentID = e.DepartmentID
                GROUP BY d.DepartmentID, d.DepartmentName
                ORDER BY employee_count DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly attendance trend
     */
    public function getMonthlyAttendanceTrend($year = null) {
        if ($year === null) $year = date('Y');

        $sql = "SELECT
                    MONTH(ar.AttendanceDate) as month,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN ar.Status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    ROUND((SUM(CASE WHEN ar.Status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate
                FROM AttendanceRecords ar
                WHERE YEAR(ar.AttendanceDate) = :year
                GROUP BY MONTH(ar.AttendanceDate)
                ORDER BY month";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

