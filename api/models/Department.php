<?php
/**
 * Department Model
 * Handles department-related database operations
 */

class Department {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get department by ID
     */
    public function getDepartmentById($departmentId) {
        $sql = "SELECT
                    d.DepartmentID, d.DepartmentName, d.Description, d.ManagerID,
                    d.Budget, d.Location, d.IsActive, d.CreatedDate,
                    CONCAT(m.FirstName, ' ', m.LastName) AS ManagerName,
                    COUNT(e.EmployeeID) AS EmployeeCount
                FROM OrganizationalStructure d
                LEFT JOIN Employees m ON d.ManagerID = m.EmployeeID
                LEFT JOIN Employees e ON d.DepartmentID = e.DepartmentID AND e.IsActive = 1
                WHERE d.DepartmentID = :department_id
                GROUP BY d.DepartmentID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all departments
     */
    public function getDepartments($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    d.DepartmentID, d.DepartmentName, d.Description, d.ManagerID,
                    d.Budget, d.Location, d.IsActive, d.CreatedDate,
                    CONCAT(m.FirstName, ' ', m.LastName) AS ManagerName,
                    COUNT(e.EmployeeID) AS EmployeeCount
                FROM OrganizationalStructure d
                LEFT JOIN Employees m ON d.ManagerID = m.EmployeeID
                LEFT JOIN Employees e ON d.DepartmentID = e.DepartmentID AND e.IsActive = 1
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['is_active'])) {
            $sql .= " AND d.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (d.DepartmentName LIKE :search OR d.Description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " GROUP BY d.DepartmentID ORDER BY d.DepartmentName LIMIT :limit OFFSET :offset";

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
     * Count total departments
     */
    public function countDepartments($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM OrganizationalStructure d WHERE 1=1";
        $params = [];

        // Apply same filters as getDepartments
        if (!empty($filters['is_active'])) {
            $sql .= " AND d.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (d.DepartmentName LIKE :search OR d.Description LIKE :search)";
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
     * Create new department
     */
    public function createDepartment($data) {
        $sql = "INSERT INTO OrganizationalStructure (
                    DepartmentName, Description, ManagerID, Budget, Location, IsActive
                ) VALUES (
                    :department_name, :description, :manager_id, :budget, :location, :is_active
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_name', $data['department_name'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindParam(':manager_id', $data['manager_id'], PDO::PARAM_INT);
        $stmt->bindParam(':budget', $data['budget'], PDO::PARAM_STR);
        $stmt->bindParam(':location', $data['location'], PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update department
     */
    public function updateDepartment($departmentId, $data) {
        $sql = "UPDATE OrganizationalStructure SET 
                    DepartmentName = :department_name,
                    Description = :description,
                    ManagerID = :manager_id,
                    Budget = :budget,
                    Location = :location,
                    IsActive = :is_active
                WHERE DepartmentID = :department_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_name', $data['department_name'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $data['description'], PDO::PARAM_STR);
        $stmt->bindParam(':manager_id', $data['manager_id'], PDO::PARAM_INT);
        $stmt->bindParam(':budget', $data['budget'], PDO::PARAM_STR);
        $stmt->bindParam(':location', $data['location'], PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete department (soft delete)
     */
    public function deleteDepartment($departmentId) {
        $sql = "UPDATE OrganizationalStructure SET IsActive = 0 WHERE DepartmentID = :department_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Check if department name exists
     */
    public function departmentNameExists($departmentName, $excludeDepartmentId = null) {
        $sql = "SELECT DepartmentID FROM OrganizationalStructure WHERE DepartmentName = :department_name";
        
        if ($excludeDepartmentId) {
            $sql .= " AND DepartmentID != :exclude_department_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_name', $departmentName, PDO::PARAM_STR);
        
        if ($excludeDepartmentId) {
            $stmt->bindParam(':exclude_department_id', $excludeDepartmentId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    /**
     * Get department employees
     */
    public function getDepartmentEmployees($departmentId) {
        $sql = "SELECT
                    e.EmployeeID, e.FirstName, e.LastName, e.Email, e.JobTitle,
                    e.HireDate, e.IsActive,
                    u.Username, r.RoleName
                FROM Employees e
                LEFT JOIN Users u ON e.EmployeeID = u.EmployeeID
                LEFT JOIN Roles r ON u.RoleID = r.RoleID
                WHERE e.DepartmentID = :department_id
                ORDER BY e.LastName, e.FirstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

