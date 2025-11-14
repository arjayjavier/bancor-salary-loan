-- =============================================
-- Setup Admin Account Script
-- Run this in phpMyAdmin to fix admin account
-- =============================================

USE loan_system;

-- Delete existing admin if exists (to avoid duplicates)
DELETE FROM users WHERE email = 'admin@loansystem.com';

-- Insert Admin Account with proper password hash
-- Password: admin123
-- Generated using: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (name, email, password, role, status, email_verified) VALUES
('Admin User', 'admin@loansystem.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin', 'active', TRUE);

-- Verify the admin was created
SELECT id, name, email, role, status FROM users WHERE email = 'admin@loansystem.com';

-- Login Credentials:
-- Email: admin@loansystem.com
-- Password: admin123

