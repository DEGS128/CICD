<?php
/**
 * HMO Model
 * Handles HMO-related database operations
 */

class HMO {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get HMO plans
     */
    public function getHMOPlans($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    hp.PlanID, hp.PlanName, hp.Description, hp.MonthlyPremium,
                    hp.CoverageLimit, hp.IsActive, hp.CreatedDate,
                    hpr.ProviderName, hpr.ProviderContact
                FROM HMOPlans hp
                JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
                WHERE 1=1";

        $params = [];

        if (!empty($filters['provider_id'])) {
            $sql .= " AND hp.ProviderID = :provider_id";
            $params[':provider_id'] = $filters['provider_id'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND hp.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        $sql .= " ORDER BY hp.PlanName LIMIT :limit OFFSET :offset";

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
     * Get HMO providers
     */
    public function getHMOProviders() {
        $sql = "SELECT ProviderID, ProviderName, ProviderContact, Address, IsActive
                FROM HMOProviders
                WHERE IsActive = 1
                ORDER BY ProviderName";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get HMO enrollments
     */
    public function getHMOEnrollments($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    eh.EnrollmentID, eh.EmployeeID, eh.PlanID, eh.Status,
                    eh.MonthlyDeduction, eh.EnrollmentDate, eh.EffectiveDate,
                    e.FirstName, e.LastName, e.Email,
                    hp.PlanName, hpr.ProviderName
                FROM EmployeeHMOEnrollments eh
                JOIN Employees e ON eh.EmployeeID = e.EmployeeID
                JOIN HMOPlans hp ON eh.PlanID = hp.PlanID
                JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
                WHERE 1=1";

        $params = [];

        if (!empty($filters['employee_id'])) {
            $sql .= " AND eh.EmployeeID = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND eh.Status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY eh.EnrollmentDate DESC LIMIT :limit OFFSET :offset";

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
     * Create HMO enrollment
     */
    public function createHMOEnrollment($data) {
        $sql = "INSERT INTO EmployeeHMOEnrollments (
                    EmployeeID, PlanID, Status, MonthlyDeduction, EnrollmentDate, EffectiveDate
                ) VALUES (
                    :employee_id, :plan_id, :status, :monthly_deduction, :enrollment_date, :effective_date
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':plan_id', $data['plan_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':monthly_deduction', $data['monthly_deduction'], PDO::PARAM_STR);
        $stmt->bindParam(':enrollment_date', $data['enrollment_date'], PDO::PARAM_STR);
        $stmt->bindParam(':effective_date', $data['effective_date'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }
}

