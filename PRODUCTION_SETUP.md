# Production Deployment Setup Guide

## Critical Issues Fixed

Your login system was failing on the production domain due to several configuration issues:

### 1. Frontend API Endpoint
**FIXED**: Updated `index.php` to use the proper API endpoint:
- **Before**: `php/api/login.php` (old session-based system)
- **After**: `api/index.php?route=auth/login` (modern JWT-based system)

### 2. Environment Configuration Required

Create these environment configuration files on your production server:

#### Create `/api/.env` file:
```env
# Database Configuration - REQUIRED FOR PRODUCTION
DB_HOST=localhost
DB_NAME=hr_integrated_db
DB_USER=your_production_db_user
DB_PASS=your_production_db_password

# JWT Configuration - REQUIRED
JWT_SECRET=your-unique-production-jwt-secret-key-2024

# Email Configuration (for 2FA and password reset) - REQUIRED if using 2FA
GMAIL_USER=your-production-email@gmail.com
GMAIL_APP_PASSWORD=your-gmail-app-password

# Application Configuration - REQUIRED
APP_URL=https://hr4.health-ease-hospital.com
```

#### Create main project `.env` file:
```env
# Same content as above
DB_HOST=localhost
DB_NAME=hr_integrated_db
DB_USER=your_production_db_user
DB_PASS=your_production_db_password
JWT_SECRET=your-unique-production-jwt-secret-key-2024
GMAIL_USER=your-production-email@gmail.com
GMAIL_APP_PASSWORD=your-gmail-app-password
APP_URL=https://hr4.health-ease-hospital.com
```

### 3. Database Configuration Steps

1. **Update Database Credentials**: Replace the placeholders in the `.env` files with your actual production database credentials.

2. **Database Connection**: Ensure your production database:
   - Has the `hr_integrated_db` database
   - User has proper permissions
   - All required tables exist

3. **Test Database Connection**: Create a test file to verify database connectivity:

```php
<?php
// test_db.php - Delete after testing
require_once 'php/db_connect.php';
echo json_encode(['status' => 'Database connected successfully']);
?>
```

### 4. File Permissions

Ensure proper file permissions on your production server:
```bash
chmod 644 .env
chmod 644 api/.env
chmod 755 api/
chmod 755 php/
```

### 5. Common Production Issues & Solutions

#### Issue: "Database connection failed"
**Solution**: 
- Verify database credentials in `.env` files
- Check if database server is running
- Verify database user has proper permissions

#### Issue: "Invalid username or password" (even with correct credentials)
**Solution**:
- Check password hashing - ensure passwords in database are properly hashed
- Verify user accounts exist and are active
- Check error logs for specific database errors

#### Issue: "2FA email not sending"
**Solution**:
- Update Gmail credentials in `.env` files
- Use Gmail App Passwords (not regular password)
- Check email settings in `api/routes/auth.php`

### 6. Testing Steps

1. **Test Database**: Visit `https://hr4.health-ease-hospital.com/test_db.php`
2. **Test Login**: Try logging in with a known user account
3. **Check Error Logs**: Monitor server error logs for any PHP errors
4. **Browser Console**: Check browser console for JavaScript errors

### 7. Security Considerations

- **JWT Secret**: Use a strong, unique JWT secret in production
- **Database Passwords**: Use strong database passwords
- **HTTPS**: Ensure your domain uses HTTPS (which it does)
- **File Permissions**: Secure `.env` files (not web-accessible)

### 8. Debugging

If login still fails, check:
1. Server error logs
2. Browser network tab for API responses
3. Database connectivity
4. Environment variable loading

The main fix was updating the frontend to use the correct API endpoint. The old system was session-based, but your modern system uses JWT tokens.
