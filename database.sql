-- =============================================
-- Loan System Database Schema
-- =============================================
-- Database: loan_system
-- Description: Database schema for login, register, and user management
-- =============================================

-- Create database
CREATE DATABASE IF NOT EXISTS loan_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loan_system;

-- =============================================
-- Users Table
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Sessions Table (for login tracking)
-- =============================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Password Reset Tokens Table
-- =============================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Activity Log Table (optional - for tracking user activities)
-- =============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Insert Sample Data
-- =============================================

-- Default Admin Account
-- Password: admin123
-- IMPORTANT: The hash below is for password 'admin123'
-- If login doesn't work, use one of these solutions:
-- 1. Run: admin/reset_admin.php in your browser
-- 2. Run: setup_admin.sql in phpMyAdmin
-- 3. Or manually update: UPDATE users SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' WHERE email = 'admin@loansystem.com';
INSERT INTO users (name, email, password, role, status, email_verified) VALUES
('Admin User', 'admin@loansystem.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 'active', TRUE)
ON DUPLICATE KEY UPDATE 
    password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy',
    role = 'admin',
    status = 'active';

-- Default Regular User Account  
-- Password: user123
-- IMPORTANT: The hash below is for password 'user123'
INSERT INTO users (name, email, password, role, status, email_verified) VALUES
('Test User', 'user@loansystem.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'user', 'active', TRUE)
ON DUPLICATE KEY UPDATE 
    password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

-- =============================================
-- Stored Procedures (Optional - for common operations)
-- =============================================

-- Procedure: Get user by email
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS GetUserByEmail(IN user_email VARCHAR(100))
BEGIN
    SELECT id, name, email, password, role, status, email_verified, created_at, last_login
    FROM users
    WHERE email = user_email AND status = 'active';
END //
DELIMITER ;

-- Procedure: Update last login
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS UpdateLastLogin(IN user_id INT)
BEGIN
    UPDATE users
    SET last_login = CURRENT_TIMESTAMP
    WHERE id = user_id;
END //
DELIMITER ;

-- Procedure: Create session
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CreateSession(
    IN p_user_id INT,
    IN p_session_token VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_expires_at TIMESTAMP
)
BEGIN
    INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
    VALUES (p_user_id, p_session_token, p_ip_address, p_user_agent, p_expires_at);
END //
DELIMITER ;

-- Procedure: Validate session
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS ValidateSession(IN p_session_token VARCHAR(255))
BEGIN
    SELECT s.id, s.user_id, s.expires_at, s.is_active,
           u.name, u.email, u.role, u.status
    FROM sessions s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.session_token = p_session_token
      AND s.is_active = TRUE
      AND s.expires_at > CURRENT_TIMESTAMP
      AND u.status = 'active';
END //
DELIMITER ;

-- =============================================
-- Views (Optional - for easier queries)
-- =============================================

-- View: Active users summary
CREATE OR REPLACE VIEW v_active_users AS
SELECT 
    id,
    name,
    email,
    role,
    created_at,
    last_login,
    CASE 
        WHEN last_login IS NULL THEN 'Never'
        WHEN last_login > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Recent'
        ELSE 'Inactive'
    END AS login_status
FROM users
WHERE status = 'active';

-- View: User sessions summary
CREATE OR REPLACE VIEW v_user_sessions AS
SELECT 
    s.id,
    s.user_id,
    u.name,
    u.email,
    u.role,
    s.ip_address,
    s.created_at,
    s.expires_at,
    CASE 
        WHEN s.expires_at > NOW() AND s.is_active = TRUE THEN 'Active'
        ELSE 'Expired'
    END AS session_status
FROM sessions s
INNER JOIN users u ON s.user_id = u.id;

-- =============================================
-- Triggers (Optional - for automatic actions)
-- =============================================

-- Trigger: Log user registration
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description)
    VALUES (NEW.id, 'REGISTER', CONCAT('User registered: ', NEW.email));
END //
DELIMITER ;

-- Trigger: Log user login
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_session_insert
AFTER INSERT ON sessions
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
    VALUES (NEW.user_id, 'LOGIN', 'User logged in', NEW.ip_address, NEW.user_agent);
END //
DELIMITER ;

-- =============================================
-- Cleanup Procedures (for expired sessions)
-- =============================================

-- Procedure: Clean expired sessions
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanExpiredSessions()
BEGIN
    UPDATE sessions
    SET is_active = FALSE
    WHERE expires_at < CURRENT_TIMESTAMP AND is_active = TRUE;
    
    DELETE FROM password_resets
    WHERE expires_at < CURRENT_TIMESTAMP OR used = TRUE;
END //
DELIMITER ;

-- =============================================
-- Sample Queries (for reference)
-- =============================================

-- Get all admin users
-- SELECT * FROM users WHERE role = 'admin' AND status = 'active';

-- Get all regular users
-- SELECT * FROM users WHERE role = 'user' AND status = 'active';

-- Get user with their active sessions
-- SELECT u.*, s.session_token, s.expires_at 
-- FROM users u 
-- LEFT JOIN sessions s ON u.id = s.user_id 
-- WHERE s.is_active = TRUE AND s.expires_at > NOW();

-- Get recent activity logs
-- SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 50;

-- =============================================
-- Notes:
-- =============================================
-- 1. Passwords should be hashed using password_hash() in PHP
--    Example: $hashed_password = password_hash($password, PASSWORD_BCRYPT);
--    Verify with: password_verify($password, $hashed_password)
--
-- 2. Session tokens should be generated securely
--    Example: bin2hex(random_bytes(32)) or use PHP's session_id()
--
-- 3. Always use prepared statements to prevent SQL injection
--
-- 4. Set appropriate session expiration times (e.g., 24 hours)
--
-- 5. Regularly clean up expired sessions and tokens
--
-- 6. The default passwords in sample data are:
--    Admin: admin123
--    User: user123
--    (These are example hashes - generate new ones in your application)
-- =============================================

