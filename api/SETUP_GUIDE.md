# Hospital HR API Setup Guide

## Quick Start

### 1. Environment Setup

Create a `.env` file in the `/api` directory with the following content:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=hr_integrated_db
DB_USER=root
DB_PASS=

# JWT Configuration
JWT_SECRET=hospital-hr-super-secret-jwt-key-2024-change-in-production

# Email Configuration (for 2FA and password reset)
GMAIL_USER=your-email@gmail.com
GMAIL_APP_PASSWORD=your-app-password

# Application Configuration
APP_URL=http://localhost/hospital-HR4
```

### 2. Test the API

Visit: `http://localhost/hospital-HR4/api/test_endpoint.php`

You should see a JSON response confirming the API is working.

### 3. Import Postman Collection

1. Open Postman
2. Click "Import" → "File"
3. Select `postman_collection.json` from the `/api` directory
4. Set up environment variables in Postman:
   - `base_url`: `http://localhost/hospital-HR4/api`
   - `auth_token`: (will be set after login)

### 4. Test Authentication

1. Use the "Login" request in Postman
2. Use credentials from your database (check Users table)
3. Copy the token from the response
4. Set it as the `auth_token` environment variable
5. Test other endpoints

## Available Endpoints

### Authentication
- `POST /api/auth/login` - Login with username/password
- `POST /api/auth/verify-2fa` - Verify 2FA code (if enabled)
- `POST /api/auth/reset-password` - Reset password via email
- `POST /api/auth/logout` - Logout (client-side token removal)

### Users Management
- `GET /api/users` - List all users (paginated)
- `GET /api/users/{id}` - Get specific user
- `POST /api/users` - Create new user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user (soft delete)

### Employees Management
- `GET /api/employees` - List all employees (paginated)
- `GET /api/employees/{id}` - Get specific employee
- `GET /api/employees/{id}/benefits` - Get employee benefits
- `GET /api/employees/{id}/salary` - Get employee salary
- `POST /api/employees` - Create new employee
- `PUT /api/employees/{id}` - Update employee
- `DELETE /api/employees/{id}` - Delete employee (soft delete)

### Departments Management
- `GET /api/departments` - List all departments (paginated)
- `GET /api/departments/{id}` - Get specific department
- `GET /api/departments/{id}/employees` - Get department employees
- `POST /api/departments` - Create new department
- `PUT /api/departments/{id}` - Update department
- `DELETE /api/departments/{id}` - Delete department (soft delete)

### Dashboard
- `GET /api/dashboard` - Get dashboard data and analytics

### Other Modules (Structure Ready)
- Benefits, Payroll, Attendance, Leave, HMO, Reports endpoints are structured but need full implementation

## Response Format

All API responses follow this structure:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "timestamp": "2024-01-01T00:00:00+00:00"
}
```

## Error Handling

- `400` - Bad Request (validation errors)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource not found)
- `500` - Internal Server Error

## Security Features

- JWT token-based authentication
- SQL injection protection via prepared statements
- Input validation and sanitization
- CORS support for frontend integration
- Role-based authorization

## Next Steps

1. **Test all endpoints** using the Postman collection
2. **Implement remaining modules** (Benefits, Payroll, etc.) as needed
3. **Customize responses** based on your specific requirements
4. **Add more validation** and business logic as required
5. **Deploy to production** with proper environment variables

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `.env` file
   - Ensure MySQL/MariaDB is running
   - Verify database exists

2. **Authentication Errors**
   - Check JWT_SECRET is set in `.env`
   - Verify user credentials in database
   - Ensure token is properly formatted in Authorization header

3. **CORS Issues**
   - Check `.htaccess` file is present
   - Verify mod_rewrite is enabled in Apache
   - Ensure proper headers are set

4. **File Not Found Errors**
   - Check file permissions
   - Verify all files are in correct directories
   - Ensure `.htaccess` is working properly

### Support

If you encounter issues:
1. Check the error logs in your web server
2. Verify all environment variables are set correctly
3. Test individual components using the test endpoint
4. Review the API documentation in `README.md`

