# Hospital HR REST API Documentation

## Overview

This is a comprehensive RESTful API for a Hospital Human Resources Management System. The API provides endpoints for managing employees, users, departments, benefits, payroll, attendance, leave, HMO, and reports.

## Base URL
```
http://localhost/hospital-HR4/api
```

## Authentication

The API uses JWT (JSON Web Token) authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

## Response Format

All API responses follow this standardized format:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

## Error Responses

- **400 Bad Request**: Invalid input data
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **500 Internal Server Error**: Server error

## Endpoints

### Authentication (`/api/auth`)

#### Login
- **POST** `/api/auth/login`
- **Description**: Authenticate user and get JWT token
- **Body**:
  ```json
  {
    "username": "admin@gmail.com",
    "password": "password123"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Login successful",
    "data": {
      "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "user": {
        "user_id": 1,
        "username": "admin@gmail.com",
        "role": "System Admin"
      }
    }
  }
  ```

#### Logout
- **POST** `/api/auth/logout`
- **Description**: Logout user (client-side token removal)

#### Verify 2FA
- **POST** `/api/auth/verify-2fa`
- **Description**: Verify two-factor authentication code

#### Reset Password
- **POST** `/api/auth/reset-password`
- **Description**: Reset password via email

### Users (`/api/users`)

#### Get All Users
- **GET** `/api/users`
- **Query Parameters**: `page`, `limit`, `role_id`, `is_active`, `search`
- **Response**: Paginated list of users

#### Get User by ID
- **GET** `/api/users/{id}`
- **Response**: User details

#### Create User
- **POST** `/api/users`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "username": "newuser@example.com",
    "password": "password123",
    "role_id": 2,
    "is_active": 1
  }
  ```

#### Update User
- **PUT** `/api/users/{id}`
- **Body**: Partial user data

#### Delete User
- **DELETE** `/api/users/{id}`
- **Description**: Soft delete user

### Employees (`/api/employees`)

#### Get All Employees
- **GET** `/api/employees`
- **Query Parameters**: `page`, `limit`, `department_id`, `is_active`, `search`
- **Response**: Paginated list of employees

#### Get Employee by ID
- **GET** `/api/employees/{id}`
- **Response**: Employee details

#### Get Employee Benefits
- **GET** `/api/employees/{id}/benefits`
- **Response**: Employee's benefits

#### Get Employee Salary
- **GET** `/api/employees/{id}/salary`
- **Response**: Employee's salary information

#### Create Employee
- **POST** `/api/employees`
- **Body**:
  ```json
  {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@hospital.com",
    "phone": "+1234567890",
    "job_title": "Nurse",
    "department_id": 1,
    "hire_date": "2024-01-01",
    "base_salary": 50000
  }
  ```

#### Update Employee
- **PUT** `/api/employees/{id}`
- **Body**: Partial employee data

#### Delete Employee
- **DELETE** `/api/employees/{id}`
- **Description**: Soft delete employee

### Departments (`/api/departments`)

#### Get All Departments
- **GET** `/api/departments`
- **Query Parameters**: `page`, `limit`, `is_active`, `search`
- **Response**: Paginated list of departments

#### Get Department by ID
- **GET** `/api/departments/{id}`
- **Response**: Department details

#### Get Department Employees
- **GET** `/api/departments/{id}/employees`
- **Response**: Employees in the department

#### Create Department
- **POST** `/api/departments`
- **Body**:
  ```json
  {
    "department_name": "Emergency",
    "description": "Emergency Department",
    "is_active": 1
  }
  ```

#### Update Department
- **PUT** `/api/departments/{id}`
- **Body**: Partial department data

#### Delete Department
- **DELETE** `/api/departments/{id}`
- **Description**: Soft delete department

### Benefits (`/api/benefits`)

#### Get All Benefits
- **GET** `/api/benefits`
- **Query Parameters**: `page`, `limit`, `benefit_type`, `is_active`, `search`
- **Response**: Paginated list of benefits

#### Get Benefit by ID
- **GET** `/api/benefits/{id}`
- **Response**: Benefit details

#### Get Benefit Categories
- **GET** `/api/benefits/categories`
- **Response**: List of benefit categories

#### Get Employee Benefits
- **GET** `/api/benefits/{employee_id}/employees`
- **Response**: Employee's benefits

#### Create Benefit
- **POST** `/api/benefits`
- **Body**:
  ```json
  {
    "benefit_name": "Health Insurance",
    "description": "Comprehensive health coverage",
    "benefit_type": 1,
    "amount": 500,
    "is_percentage": 0,
    "is_active": 1
  }
  ```

#### Create Benefit Category
- **POST** `/api/benefits/categories`
- **Body**:
  ```json
  {
    "category_name": "Health Benefits",
    "category_description": "Health-related benefits"
  }
  ```

#### Assign Benefit to Employee
- **POST** `/api/benefits/assign`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "benefit_id": 1,
    "benefit_amount": 500,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "Active"
  }
  ```

#### Update Benefit
- **PUT** `/api/benefits/{id}`
- **Body**: Partial benefit data

#### Delete Benefit
- **DELETE** `/api/benefits/{id}`
- **Description**: Soft delete benefit

### Payroll (`/api/payroll`)

#### Get All Payroll Runs
- **GET** `/api/payroll`
- **Query Parameters**: `page`, `limit`, `status`, `pay_period_start`, `pay_period_end`
- **Response**: Paginated list of payroll runs

#### Get Payroll Run by ID
- **GET** `/api/payroll/{id}`
- **Response**: Payroll run details

#### Get Payslips for Payroll Run
- **GET** `/api/payroll/{id}/payslips`
- **Response**: Payslips for the payroll run

#### Process Payroll Run
- **POST** `/api/payroll/{id}/process`
- **Description**: Process payroll run and generate payslips

#### Create Payroll Run
- **POST** `/api/payroll`
- **Body**:
  ```json
  {
    "pay_period_start": "2024-01-01",
    "pay_period_end": "2024-01-15",
    "pay_date": "2024-01-16",
    "notes": "Bi-weekly payroll"
  }
  ```

#### Update Payroll Run
- **PUT** `/api/payroll/{id}`
- **Body**: Partial payroll run data

#### Delete Payroll Run
- **DELETE** `/api/payroll/{id}`
- **Description**: Delete payroll run (only if not completed)

### Attendance (`/api/attendance`)

#### Get All Attendance Records
- **GET** `/api/attendance`
- **Query Parameters**: `page`, `limit`, `employee_id`, `attendance_date`, `date_from`, `date_to`, `status`, `department_id`
- **Response**: Paginated list of attendance records

#### Get Attendance Record by ID
- **GET** `/api/attendance/{id}`
- **Response**: Attendance record details

#### Get Attendance Statistics
- **GET** `/api/attendance/statistics`
- **Query Parameters**: `date_from`, `date_to`, `department_id`
- **Response**: Attendance statistics

#### Get Employee Attendance Summary
- **GET** `/api/attendance/{employee_id}/summary`
- **Query Parameters**: `month`, `year`
- **Response**: Employee attendance summary

#### Clock In
- **POST** `/api/attendance/clock-in`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "date": "2024-01-01"
  }
  ```

#### Clock Out
- **POST** `/api/attendance/clock-out`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "date": "2024-01-01"
  }
  ```

#### Create Attendance Record
- **POST** `/api/attendance`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "attendance_date": "2024-01-01",
    "clock_in_time": "09:00:00",
    "clock_out_time": "17:00:00",
    "status": "Present",
    "notes": "Regular work day"
  }
  ```

#### Update Attendance Record
- **PUT** `/api/attendance/{id}`
- **Body**: Partial attendance record data

#### Delete Attendance Record
- **DELETE** `/api/attendance/{id}`
- **Description**: Delete attendance record

### Leave (`/api/leave`)

#### Get All Leave Requests
- **GET** `/api/leave`
- **Query Parameters**: `page`, `limit`, `employee_id`, `status`, `leave_type_id`, `date_from`, `date_to`
- **Response**: Paginated list of leave requests

#### Get Leave Request by ID
- **GET** `/api/leave/{id}`
- **Response**: Leave request details

#### Get Leave Types
- **GET** `/api/leave/types`
- **Response**: List of leave types

#### Get Employee Leave Balance
- **GET** `/api/leave/{employee_id}/balance`
- **Query Parameters**: `year`
- **Response**: Employee's leave balance

#### Create Leave Request
- **POST** `/api/leave`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "leave_type_id": 1,
    "start_date": "2024-01-15",
    "end_date": "2024-01-17",
    "reason": "Family emergency"
  }
  ```

#### Create Leave Type
- **POST** `/api/leave/types`
- **Body**:
  ```json
  {
    "leave_type_name": "Sick Leave",
    "description": "Medical leave",
    "max_days_per_year": 10
  }
  ```

#### Approve Leave Request
- **POST** `/api/leave/{id}/approve`
- **Body**:
  ```json
  {
    "comments": "Approved by HR Manager"
  }
  ```

#### Reject Leave Request
- **POST** `/api/leave/{id}/reject`
- **Body**:
  ```json
  {
    "comments": "Insufficient leave balance"
  }
  ```

#### Update Leave Request
- **PUT** `/api/leave/{id}`
- **Body**: Partial leave request data

#### Delete Leave Request
- **DELETE** `/api/leave/{id}`
- **Description**: Delete leave request

### HMO (`/api/hmo`)

#### Get All HMO Plans
- **GET** `/api/hmo`
- **Query Parameters**: `page`, `limit`, `provider_id`, `is_active`
- **Response**: Paginated list of HMO plans

#### Get HMO Plan by ID
- **GET** `/api/hmo/{id}`
- **Response**: HMO plan details

#### Get HMO Providers
- **GET** `/api/hmo/providers`
- **Response**: List of HMO providers

#### Get HMO Enrollments
- **GET** `/api/hmo/enrollments`
- **Query Parameters**: `page`, `limit`, `employee_id`, `status`
- **Response**: Paginated list of HMO enrollments

#### Create HMO Enrollment
- **POST** `/api/hmo/enrollments`
- **Body**:
  ```json
  {
    "employee_id": 1,
    "plan_id": 1,
    "monthly_deduction": 200,
    "enrollment_date": "2024-01-01",
    "effective_date": "2024-01-01"
  }
  ```

### Reports (`/api/reports`)

#### Get Available Reports
- **GET** `/api/reports`
- **Response**: List of available reports

#### Employee Summary Report
- **GET** `/api/reports/employee-summary`
- **Query Parameters**: `department_id`
- **Response**: Employee summary statistics

#### Attendance Summary Report
- **GET** `/api/reports/attendance-summary`
- **Query Parameters**: `date_from`, `date_to`, `department_id`
- **Response**: Attendance summary statistics

#### Payroll Summary Report
- **GET** `/api/reports/payroll-summary`
- **Query Parameters**: `date_from`, `date_to`
- **Response**: Payroll summary statistics

#### Leave Summary Report
- **GET** `/api/reports/leave-summary`
- **Query Parameters**: `date_from`, `date_to`, `department_id`
- **Response**: Leave summary statistics

#### Department-wise Employee Count
- **GET** `/api/reports/department-employees`
- **Response**: Employee count by department

#### Monthly Attendance Trend
- **GET** `/api/reports/attendance-trend`
- **Query Parameters**: `year`
- **Response**: Monthly attendance trend data

## Authorization

### Roles
- **System Admin**: Full access to all endpoints
- **HR Manager**: Access to most HR-related endpoints
- **Employee**: Limited access to own data

### Permission Matrix

| Endpoint | System Admin | HR Manager | Employee |
|----------|-------------|------------|----------|
| Auth | ✓ | ✓ | ✓ |
| Users | ✓ | ✓ | ✗ |
| Employees | ✓ | ✓ | Own data only |
| Departments | ✓ | ✓ | Read only |
| Benefits | ✓ | ✓ | Own data only |
| Payroll | ✓ | ✓ | Own data only |
| Attendance | ✓ | ✓ | Own data only |
| Leave | ✓ | ✓ | Own data only |
| HMO | ✓ | ✓ | Own data only |
| Reports | ✓ | ✓ | ✗ |

## Rate Limiting

- **Authentication endpoints**: 5 requests per minute
- **Other endpoints**: 100 requests per minute

## Pagination

Most list endpoints support pagination:

- **page**: Page number (default: 1)
- **limit**: Items per page (default: 20, max: 100)

Response includes pagination metadata:

```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "total_pages": 5,
    "has_next": true,
    "has_prev": false
  }
}
```

## Filtering and Searching

Most endpoints support filtering and searching:

- **search**: Text search across relevant fields
- **date_from/date_to**: Date range filtering
- **status**: Filter by status
- **department_id**: Filter by department
- **is_active**: Filter by active status

## Environment Variables

Create a `.env` file in the `/api` directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=hr_integrated_db
DB_USER=root
DB_PASS=

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key

# Email Configuration
GMAIL_USER=your-email@gmail.com
GMAIL_APP_PASSWORD=your-app-password

# Application Configuration
APP_URL=http://localhost/hospital-HR4
```

## Testing

### Postman Collection

Import the provided `postman_collection.json` file into Postman for easy testing.

### Environment Variables for Postman

- `base_url`: `http://localhost/hospital-HR4/api`
- `auth_token`: JWT token (set after login)

## Error Handling

All errors are returned in the standardized format:

```json
{
  "success": false,
  "message": "Error description",
  "data": null,
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

## Security Features

- JWT token-based authentication
- SQL injection protection via prepared statements
- Input validation and sanitization
- CORS support for frontend integration
- Role-based authorization
- Password hashing with bcrypt

## Support

For issues or questions:
1. Check the error logs in your web server
2. Verify all environment variables are set correctly
3. Test individual components using the test endpoints
4. Review the API documentation in `README.md`




