# Loan System - Login & Register

A complete login and registration system with admin and user roles.

## Features

- ✅ User Registration
- ✅ User Login
- ✅ Admin and User Role Management
- ✅ Session Management
- ✅ Password Hashing (BCRYPT)
- ✅ Activity Logging
- ✅ Modern, Responsive UI

## Database Setup

### 1. Import the Database

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click on "Import" tab
3. Choose the `database.sql` file
4. Click "Go" to import

**OR** use command line:

```bash
mysql -u root -p < database.sql
```

### 2. Database Configuration

Edit `config/database.php` if your database credentials are different:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your password if you have one
define('DB_NAME', 'loan_system');
```

## Default Login Credentials

After importing the database, you can use these test accounts:

### Admin Account
- **Email:** admin@loansystem.com
- **Password:** admin123

### User Account
- **Email:** user@loansystem.com
- **Password:** user123

**Note:** These are example accounts. In production, change these passwords immediately!

## Project Structure

```
loan_system/
├── index.html              # Login/Register page
├── database.sql            # Database schema
├── config/
│   └── database.php        # Database connection
├── api/
│   ├── login.php           # Login API endpoint
│   ├── register.php        # Registration API endpoint
│   ├── logout.php          # Logout API endpoint
│   └── check_session.php   # Session validation endpoint
└── README.md               # This file
```

## Database Tables

### `users`
- Stores user accounts with name, email, password, role, and status
- Roles: `admin` or `user`
- Status: `active`, `inactive`, or `suspended`

### `sessions`
- Tracks user login sessions
- Stores session tokens, IP addresses, and expiration times

### `password_resets`
- Manages password reset tokens (for future implementation)

### `activity_logs`
- Logs user activities (login, register, logout, etc.)

## API Endpoints

### POST `/api/register.php`
Register a new user account.

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Account created successfully",
  "user_id": 1
}
```

### POST `/api/login.php`
Authenticate user and create session.

**Request:**
```json
{
  "email": "john@example.com",
  "password": "password123",
  "remember": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user"
  },
  "session_token": "..."
}
```

### POST `/api/logout.php`
Logout user and destroy session.

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### GET `/api/check_session.php`
Validate current session.

**Response:**
```json
{
  "success": true,
  "authenticated": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user"
  }
}
```

## Security Features

1. **Password Hashing:** All passwords are hashed using PHP's `password_hash()` with BCRYPT
2. **Prepared Statements:** All database queries use PDO prepared statements to prevent SQL injection
3. **Session Management:** Secure session tokens with expiration
4. **Input Validation:** Email format and password strength validation
5. **Activity Logging:** All login/logout activities are logged

## Usage

1. Start XAMPP and ensure Apache and MySQL are running
2. Place the project in `htdocs/loan_system/`
3. Import the database using phpMyAdmin or command line
4. Open `http://localhost/loan_system/` in your browser
5. Register a new account or login with test credentials

## Next Steps

After login, users are redirected based on their role:
- **Admin:** `admin/dashboard.php`
- **User:** `user/dashboard.php`

You'll need to create these dashboard pages for your loan system.

## Notes

- Passwords must be at least 6 characters long
- Email addresses must be unique
- Sessions expire after 24 hours
- All new registrations default to `user` role
- Only admins can be created manually in the database

## Troubleshooting

### Database Connection Error
- Check if MySQL is running in XAMPP
- Verify database credentials in `config/database.php`
- Ensure database `loan_system` exists

### API Not Working
- Check Apache is running
- Verify file paths are correct
- Check browser console for errors
- Ensure PHP error reporting is enabled for debugging

### Session Issues
- Check PHP session configuration
- Verify session directory is writable
- Clear browser cookies and try again

## License

This project is open source and available for use.

