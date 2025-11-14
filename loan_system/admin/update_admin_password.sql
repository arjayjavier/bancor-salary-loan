-- Run this SQL query in phpMyAdmin to fix the admin password
-- Password will be: admin123

-- Option 1: Update existing admin
UPDATE users 
SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
WHERE email = 'admin@loansystem.com';

-- Option 2: If admin doesn't exist, insert new admin
INSERT INTO users (name, email, password, role, status, email_verified) 
VALUES ('Admin User', 'admin@loansystem.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 'active', TRUE)
ON DUPLICATE KEY UPDATE password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

-- Note: The hash above is for password: admin123
-- Generated using: password_hash('admin123', PASSWORD_BCRYPT)

