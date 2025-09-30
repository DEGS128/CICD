<?php
/**
 * Benefit Model
 * Handles benefit-related database operations
 */

class Benefit {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get benefit by ID
     */
    public function getBenefitById($benefitId) {
        $sql = "SELECT
                    b.BenefitID, b.BenefitName, b.Description, b.BenefitType,
                    b.Amount, b.IsPercentage, b.IsActive, b.CreatedDate,
                    bc.CategoryName, bc.CategoryDescription
                FROM Benefits b
                LEFT JOIN BenefitsCategories bc ON b.BenefitType = bc.CategoryID
                WHERE b.BenefitID = :benefit_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':benefit_id', $benefitId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all benefits with pagination
     */
    public function getBenefits($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    b.BenefitID, b.BenefitName, b.Description, b.BenefitType,
                    b.Amount, b.IsPercentage, b.IsActive, b.CreatedDate,
                    bc.CategoryName
                FROM Benefits b
                LEFT JOIN BenefitsCategories bc ON b.BenefitType = bc.CategoryID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['benefit_type'])) {
            $sql .= " AND b.BenefitType = :benefit_type";
            $params[':benefit_type'] = $filters['benefit_type'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND b.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (b.BenefitName LIKE :search OR b.Description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY b.BenefitName LIMIT :limit OFFSET :offset";

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
     * Count total benefits
     */
    public function countBenefits($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM Benefits b WHERE 1=1";
        $params = [];

        // Apply same filters as getBenefits
        if (!empty($filters['benefit_type'])) {
            $sql .= " AND b.BenefitType = :benefit_type";
            $params[':benefit_type'] = $filters['benefit_type'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND b.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (b.BenefitName LIKE :search OR b.Description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
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
     * Create new benefit
     */
    public function createBenefit($data) {
        $sql = "INSERT INTO Benefits (
                    BenefitName, Description, BenefitType, Amount, IsPercentage, IsActive
                ) VALUES (
                    :benefit_name, :description, :benefit_type, :amount, :is_percentage, :is_active
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':benefit_name', $data['benefit_name'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindParam(':benefit_type', $data['benefit_type'], PDO::PARAM_INT);
        $stmt->bindParam(':amount', $data['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':is_percentage', $data['is_percentage'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update benefit
     */
    public function updateBenefit($benefitId, $data) {
        $sql = "UPDATE Benefits SET 
                    BenefitName = :benefit_name,
                    Description = :description,
                    BenefitType = :benefit_type,
                    Amount = :amount,
                    IsPercentage = :is_percentage,
                    IsActive = :is_active
                WHERE BenefitID = :benefit_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':benefit_name', $data['benefit_name'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindParam(':benefit_type', $data['benefit_type'], PDO::PARAM_INT);
        $stmt->bindParam(':amount', $data['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':is_percentage', $data['is_percentage'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':benefit_id', $benefitId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete benefit (soft delete)
     */
    public function deleteBenefit($benefitId) {
        $sql = "UPDATE Benefits SET IsActive = 0 WHERE BenefitID = :benefit_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':benefit_id', $benefitId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get benefit categories
     */
    public function getBenefitCategories() {
        $sql = "SELECT CategoryID, CategoryName, CategoryDescription, IsActive
                FROM BenefitsCategories
                WHERE IsActive = 1
                ORDER BY CategoryName";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create benefit category
     */
    public function createBenefitCategory($data) {
        $sql = "INSERT INTO BenefitsCategories (CategoryName, CategoryDescription, IsActive)
                VALUES (:category_name, :category_description, :is_active)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':category_name', $data['category_name'], PDO::PARAM_STR);
        $stmt->bindParam(':category_description', $data['category_description'], PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Get employee benefits
     */
    public function getEmployeeBenefits($employeeId) {
        $sql = "SELECT
                    eb.BenefitID, eb.EmployeeID, eb.BenefitAmount, eb.StartDate, eb.EndDate,
                    eb.Status, eb.Notes, eb.CreatedDate,
                    b.BenefitName, b.Description, b.BenefitType,
                    bc.CategoryName
                FROM EmployeeBenefits eb
                JOIN Benefits b ON eb.BenefitID = b.BenefitID
                LEFT JOIN BenefitsCategories bc ON b.BenefitType = bc.CategoryID
                WHERE eb.EmployeeID = :employee_id
                ORDER BY eb.StartDate DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign benefit to employee
     */
    public function assignBenefitToEmployee($data) {
        $sql = "INSERT INTO EmployeeBenefits (
                    EmployeeID, BenefitID, BenefitAmount, StartDate, EndDate, Status, Notes
                ) VALUES (
                    :employee_id, :benefit_id, :benefit_amount, :start_date, :end_date, :status, :notes
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':benefit_id', $data['benefit_id'], PDO::PARAM_INT);
        $stmt->bindParam(':benefit_amount', $data['benefit_amount'], PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }
}

