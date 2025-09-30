<?php
/**
 * Payroll Model
 * Handles payroll-related database operations
 */

class Payroll {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get payroll run by ID
     */
    public function getPayrollRunById($payrollId) {
        $sql = "SELECT
                    pr.PayrollID, pr.PayPeriodStart, pr.PayPeriodEnd, pr.PayDate,
                    pr.Status, pr.TotalGrossPay, pr.TotalDeductions, pr.TotalNetPay,
                    pr.CreatedDate, pr.ProcessedDate, pr.Notes,
                    COUNT(ps.PayslipID) as PayslipCount
                FROM PayrollRuns pr
                LEFT JOIN Payslips ps ON pr.PayrollID = ps.PayrollID
                WHERE pr.PayrollID = :payroll_id
                GROUP BY pr.PayrollID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all payroll runs with pagination
     */
    public function getPayrollRuns($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    pr.PayrollID, pr.PayPeriodStart, pr.PayPeriodEnd, pr.PayDate,
                    pr.Status, pr.TotalGrossPay, pr.TotalDeductions, pr.TotalNetPay,
                    pr.CreatedDate, pr.ProcessedDate,
                    COUNT(ps.PayslipID) as PayslipCount
                FROM PayrollRuns pr
                LEFT JOIN Payslips ps ON pr.PayrollID = ps.PayrollID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND pr.Status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['pay_period_start'])) {
            $sql .= " AND pr.PayPeriodStart >= :pay_period_start";
            $params[':pay_period_start'] = $filters['pay_period_start'];
        }

        if (!empty($filters['pay_period_end'])) {
            $sql .= " AND pr.PayPeriodEnd <= :pay_period_end";
            $params[':pay_period_end'] = $filters['pay_period_end'];
        }

        $sql .= " GROUP BY pr.PayrollID ORDER BY pr.PayPeriodEnd DESC LIMIT :limit OFFSET :offset";

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
     * Count total payroll runs
     */
    public function countPayrollRuns($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM PayrollRuns pr WHERE 1=1";
        $params = [];

        // Apply same filters as getPayrollRuns
        if (!empty($filters['status'])) {
            $sql .= " AND pr.Status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['pay_period_start'])) {
            $sql .= " AND pr.PayPeriodStart >= :pay_period_start";
            $params[':pay_period_start'] = $filters['pay_period_start'];
        }

        if (!empty($filters['pay_period_end'])) {
            $sql .= " AND pr.PayPeriodEnd <= :pay_period_end";
            $params[':pay_period_end'] = $filters['pay_period_end'];
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
     * Create new payroll run
     */
    public function createPayrollRun($data) {
        $sql = "INSERT INTO PayrollRuns (
                    PayPeriodStart, PayPeriodEnd, PayDate, Status, Notes
                ) VALUES (
                    :pay_period_start, :pay_period_end, :pay_date, :status, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':pay_period_start', $data['pay_period_start'], PDO::PARAM_STR);
        $stmt->bindParam(':pay_period_end', $data['pay_period_end'], PDO::PARAM_STR);
        $stmt->bindParam(':pay_date', $data['pay_date'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update payroll run
     */
    public function updatePayrollRun($payrollId, $data) {
        $sql = "UPDATE PayrollRuns SET 
                    PayPeriodStart = :pay_period_start,
                    PayPeriodEnd = :pay_period_end,
                    PayDate = :pay_date,
                    Status = :status,
                    TotalGrossPay = :total_gross_pay,
                    TotalDeductions = :total_deductions,
                    TotalNetPay = :total_net_pay,
                    ProcessedDate = :processed_date,
                    Notes = :notes
                WHERE PayrollID = :payroll_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':pay_period_start', $data['pay_period_start'], PDO::PARAM_STR);
        $stmt->bindParam(':pay_period_end', $data['pay_period_end'], PDO::PARAM_STR);
        $stmt->bindParam(':pay_date', $data['pay_date'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':total_gross_pay', $data['total_gross_pay'], PDO::PARAM_STR);
        $stmt->bindParam(':total_deductions', $data['total_deductions'], PDO::PARAM_STR);
        $stmt->bindParam(':total_net_pay', $data['total_net_pay'], PDO::PARAM_STR);
        $stmt->bindParam(':processed_date', $data['processed_date'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        $stmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete payroll run
     */
    public function deletePayrollRun($payrollId) {
        $sql = "DELETE FROM PayrollRuns WHERE PayrollID = :payroll_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get payslips for a payroll run
     */
    public function getPayslipsByPayrollRun($payrollId) {
        $sql = "SELECT
                    ps.PayslipID, ps.EmployeeID, ps.PayrollID, ps.GrossPay, ps.Deductions,
                    ps.NetPay, ps.Status, ps.GeneratedDate, ps.Notes,
                    e.FirstName, e.LastName, e.Email, e.JobTitle,
                    d.DepartmentName
                FROM Payslips ps
                JOIN Employees e ON ps.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                WHERE ps.PayrollID = :payroll_id
                ORDER BY e.LastName, e.FirstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payslip by ID
     */
    public function getPayslipById($payslipId) {
        $sql = "SELECT
                    ps.PayslipID, ps.EmployeeID, ps.PayrollID, ps.GrossPay, ps.Deductions,
                    ps.NetPay, ps.Status, ps.GeneratedDate, ps.Notes,
                    e.FirstName, e.LastName, e.Email, e.JobTitle,
                    d.DepartmentName,
                    pr.PayPeriodStart, pr.PayPeriodEnd, pr.PayDate
                FROM Payslips ps
                JOIN Employees e ON ps.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                JOIN PayrollRuns pr ON ps.PayrollID = pr.PayrollID
                WHERE ps.PayslipID = :payslip_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':payslip_id', $payslipId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create payslip
     */
    public function createPayslip($data) {
        $sql = "INSERT INTO Payslips (
                    EmployeeID, PayrollID, GrossPay, Deductions, NetPay, Status, Notes
                ) VALUES (
                    :employee_id, :payroll_id, :gross_pay, :deductions, :net_pay, :status, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':payroll_id', $data['payroll_id'], PDO::PARAM_INT);
        $stmt->bindParam(':gross_pay', $data['gross_pay'], PDO::PARAM_STR);
        $stmt->bindParam(':deductions', $data['deductions'], PDO::PARAM_STR);
        $stmt->bindParam(':net_pay', $data['net_pay'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Process payroll run
     */
    public function processPayrollRun($payrollId) {
        try {
            $this->pdo->beginTransaction();

            // Get all active employees
            $employeesSql = "SELECT EmployeeID FROM Employees WHERE IsActive = 1";
            $employeesStmt = $this->pdo->query($employeesSql);
            $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

            $totalGrossPay = 0;
            $totalDeductions = 0;
            $totalNetPay = 0;

            foreach ($employees as $employee) {
                // Calculate gross pay (basic salary + bonuses)
                $grossPay = $this->calculateGrossPay($employee['EmployeeID'], $payrollId);
                
                // Calculate deductions
                $deductions = $this->calculateDeductions($employee['EmployeeID'], $payrollId);
                
                // Calculate net pay
                $netPay = $grossPay - $deductions;

                // Create payslip
                $payslipData = [
                    'employee_id' => $employee['EmployeeID'],
                    'payroll_id' => $payrollId,
                    'gross_pay' => $grossPay,
                    'deductions' => $deductions,
                    'net_pay' => $netPay,
                    'status' => 'Generated',
                    'notes' => null
                ];

                $this->createPayslip($payslipData);

                $totalGrossPay += $grossPay;
                $totalDeductions += $deductions;
                $totalNetPay += $netPay;
            }

            // Update payroll run totals
            $updateData = [
                'pay_period_start' => null, // Will be set by existing data
                'pay_period_end' => null,
                'pay_date' => null,
                'status' => 'Completed',
                'total_gross_pay' => $totalGrossPay,
                'total_deductions' => $totalDeductions,
                'total_net_pay' => $totalNetPay,
                'processed_date' => date('Y-m-d H:i:s'),
                'notes' => 'Processed automatically'
            ];

            $this->updatePayrollRun($payrollId, $updateData);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Calculate gross pay for employee
     */
    private function calculateGrossPay($employeeId, $payrollId) {
        // Get base salary
        $salarySql = "SELECT BaseSalary FROM Salaries WHERE EmployeeID = :employee_id AND Status = 'Active' ORDER BY EffectiveDate DESC LIMIT 1";
        $salaryStmt = $this->pdo->prepare($salarySql);
        $salaryStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $salaryStmt->execute();
        $salary = $salaryStmt->fetch(PDO::FETCH_ASSOC);
        
        $baseSalary = $salary ? $salary['BaseSalary'] : 0;

        // Get bonuses for this payroll period
        $bonusSql = "SELECT SUM(BonusAmount) as TotalBonus FROM Bonuses WHERE EmployeeID = :employee_id AND PayrollID = :payroll_id";
        $bonusStmt = $this->pdo->prepare($bonusSql);
        $bonusStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $bonusStmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        $bonusStmt->execute();
        $bonus = $bonusStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalBonus = $bonus ? $bonus['TotalBonus'] : 0;

        return $baseSalary + $totalBonus;
    }

    /**
     * Calculate deductions for employee
     */
    private function calculateDeductions($employeeId, $payrollId) {
        // Get salary deductions
        $deductionSql = "SELECT SUM(DeductionAmount) as TotalDeduction FROM Deductions WHERE EmployeeID = :employee_id AND PayrollID = :payroll_id";
        $deductionStmt = $this->pdo->prepare($deductionSql);
        $deductionStmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $deductionStmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        $deductionStmt->execute();
        $deduction = $deductionStmt->fetch(PDO::FETCH_ASSOC);
        
        return $deduction ? $deduction['TotalDeduction'] : 0;
    }
}

