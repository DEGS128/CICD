# Hospital HR Management API

A RESTful API for the Hospital HR Management System built with PHP and MySQL.

## Features

- **RESTful Design**: Follows REST conventions with proper HTTP methods and status codes
- **JWT Authentication**: Secure token-based authentication with 2FA support
- **Standardized Responses**: Consistent JSON response format across all endpoints
- **Parameterized Queries**: SQL injection protection using prepared statements
- **CORS Support**: Cross-origin resource sharing enabled for frontend integration
- **Error Handling**: Comprehensive error handling with structured error responses
- **Modular Structure**: Clean separation of concerns with controllers, models, and middleware

## API Structure

```
/api
├── index.php              # Main API entry point
├── .htaccess              # URL rewriting rules
├── postman_collection.json # Postman collection for testing
├── env.example            # Environment variables template
├── README.md              # This file
├── /routes                # API route controllers
│   ├── auth.php           # Authentication routes
│   ├── users.php          # User management
│   ├── employees.php      # Employee management
│   ├── departments.php    # Department management
│   ├── dashboard.php      # Dashboard data
│   ├── benefits.php       # Benefits management
│   ├── payroll.php        # Payroll management
│   ├── attendance.php     # Attendance management
│   ├── leave.php          # Leave management
│   ├── hmo.php           # HMO management
│   └── reports.php        # Report generation
├── /models                # Data models
│   ├── User.php           # User model
│   ├── Employee.php       # Employee model
│   └── Department.php     # Department model
├── /middlewares           # Middleware components
│   ├── AuthMiddleware.php # Authentication middleware
│   └── ErrorHandler.php   # Error handling middleware
└── /utils                 # Utility classes
    ├── Response.php       # Response formatting
    └── Request.php        # Request handling
```

## Installation

1. **Copy environment file**:
   ```bash
   cp env.example .env
   ```

2. **Configure environment variables** in `.env`:
   ```env
   DB_HOST=localhost
   DB_NAME=hr_integrated_db
   DB_USER=root
   DB_PASS=your_password
   JWT_SECRET=your-super-secret-jwt-key
   GMAIL_USER=your-email@gmail.com
   GMAIL_APP_PASSWORD=your-app-password
   APP_URL=http://localhost/hospital-HR4
   ```

3. **Ensure mod_rewrite is enabled** in Apache

4. **Set proper permissions** for the API directory

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/verify-2fa` - Verify 2FA code
- `POST /api/auth/reset-password` - Reset password
- `POST /api/auth/logout` - User logout

### Users
- `GET /api/users` - Get all users (with pagination)
- `GET /api/users/{id}` - Get user by ID
- `POST /api/users` - Create new user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user (soft delete)

### Employees
- `GET /api/employees` - Get all employees (with pagination)
- `GET /api/employees/{id}` - Get employee by ID
- `GET /api/employees/{id}/benefits` - Get employee benefits
- `GET /api/employees/{id}/salary` - Get employee salary
- `POST /api/employees` - Create new employee
- `PUT /api/employees/{id}` - Update employee
- `DELETE /api/employees/{id}` - Delete employee (soft delete)

### Departments
- `GET /api/departments` - Get all departments (with pagination)
- `GET /api/departments/{id}` - Get department by ID
- `GET /api/departments/{id}/employees` - Get department employees
- `POST /api/departments` - Create new department
- `PUT /api/departments/{id}` - Update department
- `DELETE /api/departments/{id}` - Delete department (soft delete)

### Dashboard
- `GET /api/dashboard` - Get dashboard data

### Other Modules
- Benefits, Payroll, Attendance, Leave, HMO, and Reports endpoints are available but implementation is pending.

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "details": { ... },
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

### Paginated Response
```json
{
  "success": true,
  "message": "Success",
  "data": {
    "items": [ ... ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "total_pages": 5,
      "has_next": true,
      "has_prev": false
    }
  },
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

## HTTP Status Codes

- `200` - OK (Success)
- `201` - Created (Resource created)
- `204` - No Content (Successful deletion)
- `400` - Bad Request (Validation errors)
- `401` - Unauthorized (Authentication required)
- `403` - Forbidden (Insufficient permissions)
- `404` - Not Found (Resource not found)
- `405` - Method Not Allowed
- `500` - Internal Server Error

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer your-jwt-token-here
```

## Testing with Postman

1. Import the `postman_collection.json` file into Postman
2. Set the environment variables:
   - `base_url`: Your API base URL (e.g., `http://localhost/hospital-HR4/api`)
   - `auth_token`: JWT token obtained from login
3. Start testing the endpoints

## Error Handling

The API includes comprehensive error handling:
- Validation errors return 400 with detailed field errors
- Authentication errors return 401
- Authorization errors return 403
- Not found errors return 404
- Server errors return 500 with generic messages

## Security Features

- **SQL Injection Protection**: All queries use prepared statements
- **Input Validation**: Comprehensive input validation and sanitization
- **JWT Security**: Secure token-based authentication
- **CORS Configuration**: Proper CORS headers for cross-origin requests
- **Error Logging**: Detailed error logging without exposing sensitive information

## Development Notes

- The API is designed to be stateless
- All database operations use PDO with prepared statements
- Error handling is centralized through middleware
- Response formatting is standardized across all endpoints
- The modular structure allows for easy extension and maintenance

