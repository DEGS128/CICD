<?php
/**
 * Employee Model
 * Handles employee-related database operations
 */

class Employee {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById($employeeId) {
        $sql = "SELECT
                    e.EmployeeID, e.FirstName, e.MiddleName, e.LastName, e.Suffix,
                    e.Email, e.PersonalEmail, e.PhoneNumber, e.DateOfBirth, e.Gender,
                    e.MaritalStatus, e.Nationality, e.AddressLine1, e.AddressLine2,
                    e.City, e.StateProvince, e.PostalCode, e.Country,
                    e.EmergencyContactName, e.EmergencyContactRelationship, e.EmergencyContactPhone,
                    e.HireDate, e.JobTitle, e.DepartmentID, e.ManagerID, e.IsActive,
                    e.TerminationDate, e.TerminationReason, e.EmployeePhotoPath,
                    d.DepartmentName,
                    CONCAT(m.FirstName, ' ', m.LastName) AS ManagerName,
                    u.UserID, u.Username, u.RoleID, r.RoleName
                FROM Employees e
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                LEFT JOIN Employees m ON e.ManagerID = m.EmployeeID
                LEFT JOIN Users u ON e.EmployeeID = u.EmployeeID
                LEFT JOIN Roles r ON u.RoleID = r.RoleID
                WHERE e.EmployeeID = :employee_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all employees with pagination
     */
    public function getEmployees($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    e.EmployeeID, e.FirstName, e.MiddleName, e.LastName, e.Suffix,
                    e.Email, e.PhoneNumber, e.DateOfBirth, e.Gender, e.JobTitle,
                    e.HireDate, e.IsActive, e.TerminationDate,
                    d.DepartmentName,
                    CONCAT(m.FirstName, ' ', m.LastName) AS ManagerName,
                    u.UserID, u.Username, r.RoleName
                FROM Employees e
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                LEFT JOIN Employees m ON e.ManagerID = m.EmployeeID
                LEFT JOIN Users u ON e.EmployeeID = u.EmployeeID
                LEFT JOIN Roles r ON u.RoleID = r.RoleID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['department_id'])) {
            $sql .= " AND e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND e.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['manager_id'])) {
            $sql .= " AND e.ManagerID = :manager_id";
            $params[':manager_id'] = $filters['manager_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.FirstName LIKE :search OR e.LastName LIKE :search OR e.Email LIKE :search OR e.JobTitle LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY e.LastName, e.FirstName LIMIT :limit OFFSET :offset";

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
     * Count total employees
     */
    public function countEmployees($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM Employees e WHERE 1=1";
        $params = [];

        // Apply same filters as getEmployees
        if (!empty($filters['department_id'])) {
            $sql .= " AND e.DepartmentID = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND e.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['manager_id'])) {
            $sql .= " AND e.ManagerID = :manager_id";
            $params[':manager_id'] = $filters['manager_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.FirstName LIKE :search OR e.LastName LIKE :search OR e.Email LIKE :search OR e.JobTitle LIKE :search)";
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
     * Create new employee
     */
    public function createEmployee($data) {
        $sql = "INSERT INTO Employees (
                    FirstName, MiddleName, LastName, Suffix, Email, PersonalEmail,
                    PhoneNumber, DateOfBirth, Gender, MaritalStatus, Nationality,
                    AddressLine1, AddressLine2, City, StateProvince, PostalCode, Country,
                    EmergencyContactName, EmergencyContactRelationship, EmergencyContactPhone,
                    HireDate, JobTitle, DepartmentID, ManagerID, IsActive
                ) VALUES (
                    :first_name, :middle_name, :last_name, :suffix, :email, :personal_email,
                    :phone_number, :date_of_birth, :gender, :marital_status, :nationality,
                    :address_line1, :address_line2, :city, :state_province, :postal_code, :country,
                    :emergency_contact_name, :emergency_contact_relationship, :emergency_contact_phone,
                    :hire_date, :job_title, :department_id, :manager_id, :is_active
                )";

        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
        $stmt->bindParam(':middle_name', $data['middle_name'], PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
        $stmt->bindParam(':suffix', $data['suffix'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindParam(':personal_email', $data['personal_email'], PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $data['phone_number'], PDO::PARAM_STR);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth'], PDO::PARAM_STR);
        $stmt->bindParam(':gender', $data['gender'], PDO::PARAM_STR);
        $stmt->bindParam(':marital_status', $data['marital_status'], PDO::PARAM_STR);
        $stmt->bindParam(':nationality', $data['nationality'], PDO::PARAM_STR);
        $stmt->bindParam(':address_line1', $data['address_line1'], PDO::PARAM_STR);
        $stmt->bindParam(':address_line2', $data['address_line2'], PDO::PARAM_STR);
        $stmt->bindParam(':city', $data['city'], PDO::PARAM_STR);
        $stmt->bindParam(':state_province', $data['state_province'], PDO::PARAM_STR);
        $stmt->bindParam(':postal_code', $data['postal_code'], PDO::PARAM_STR);
        $stmt->bindParam(':country', $data['country'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_name', $data['emergency_contact_name'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_relationship', $data['emergency_contact_relationship'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_phone', $data['emergency_contact_phone'], PDO::PARAM_STR);
        $stmt->bindParam(':hire_date', $data['hire_date'], PDO::PARAM_STR);
        $stmt->bindParam(':job_title', $data['job_title'], PDO::PARAM_STR);
        $stmt->bindParam(':department_id', $data['department_id'], PDO::PARAM_INT);
        $stmt->bindParam(':manager_id', $data['manager_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update employee
     */
    public function updateEmployee($employeeId, $data) {
        $sql = "UPDATE Employees SET 
                    FirstName = :first_name,
                    MiddleName = :middle_name,
                    LastName = :last_name,
                    Suffix = :suffix,
                    Email = :email,
                    PersonalEmail = :personal_email,
                    PhoneNumber = :phone_number,
                    DateOfBirth = :date_of_birth,
                    Gender = :gender,
                    MaritalStatus = :marital_status,
                    Nationality = :nationality,
                    AddressLine1 = :address_line1,
                    AddressLine2 = :address_line2,
                    City = :city,
                    StateProvince = :state_province,
                    PostalCode = :postal_code,
                    Country = :country,
                    EmergencyContactName = :emergency_contact_name,
                    EmergencyContactRelationship = :emergency_contact_relationship,
                    EmergencyContactPhone = :emergency_contact_phone,
                    JobTitle = :job_title,
                    DepartmentID = :department_id,
                    ManagerID = :manager_id,
                    IsActive = :is_active
                WHERE EmployeeID = :employee_id";

        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
        $stmt->bindParam(':middle_name', $data['middle_name'], PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
        $stmt->bindParam(':suffix', $data['suffix'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindParam(':personal_email', $data['personal_email'], PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $data['phone_number'], PDO::PARAM_STR);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth'], PDO::PARAM_STR);
        $stmt->bindParam(':gender', $data['gender'], PDO::PARAM_STR);
        $stmt->bindParam(':marital_status', $data['marital_status'], PDO::PARAM_STR);
        $stmt->bindParam(':nationality', $data['nationality'], PDO::PARAM_STR);
        $stmt->bindParam(':address_line1', $data['address_line1'], PDO::PARAM_STR);
        $stmt->bindParam(':address_line2', $data['address_line2'], PDO::PARAM_STR);
        $stmt->bindParam(':city', $data['city'], PDO::PARAM_STR);
        $stmt->bindParam(':state_province', $data['state_province'], PDO::PARAM_STR);
        $stmt->bindParam(':postal_code', $data['postal_code'], PDO::PARAM_STR);
        $stmt->bindParam(':country', $data['country'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_name', $data['emergency_contact_name'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_relationship', $data['emergency_contact_relationship'], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_phone', $data['emergency_contact_phone'], PDO::PARAM_STR);
        $stmt->bindParam(':job_title', $data['job_title'], PDO::PARAM_STR);
        $stmt->bindParam(':department_id', $data['department_id'], PDO::PARAM_INT);
        $stmt->bindParam(':manager_id', $data['manager_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete employee (soft delete)
     */
    public function deleteEmployee($employeeId) {
        $sql = "UPDATE Employees SET IsActive = 0 WHERE EmployeeID = :employee_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeEmployeeId = null) {
        $sql = "SELECT EmployeeID FROM Employees WHERE Email = :email";
        
        if ($excludeEmployeeId) {
            $sql .= " AND EmployeeID != :exclude_employee_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        
        if ($excludeEmployeeId) {
            $stmt->bindParam(':exclude_employee_id', $excludeEmployeeId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    /**
     * Get employee benefits
     */
    public function getEmployeeBenefits($employeeId) {
        $sql = "SELECT
                    eb.BenefitID, eb.EmployeeID, eb.BenefitType, eb.BenefitAmount,
                    eb.StartDate, eb.EndDate, eb.Status, eb.Notes,
                    bc.CategoryName
                FROM EmployeeBenefits eb
                LEFT JOIN BenefitsCategories bc ON eb.BenefitType = bc.CategoryID
                WHERE eb.EmployeeID = :employee_id
                ORDER BY eb.StartDate DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get employee salary information
     */
    public function getEmployeeSalary($employeeId) {
        $sql = "SELECT
                    s.SalaryID, s.EmployeeID, s.BaseSalary, s.EffectiveDate,
                    s.Status, s.Notes
                FROM Salaries s
                WHERE s.EmployeeID = :employee_id
                ORDER BY s.EffectiveDate DESC
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

