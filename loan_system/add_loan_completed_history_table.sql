-- =============================================
-- Add Loan Completed History Table
-- =============================================
-- This migration adds loan_completed_history table to track completed loans
-- =============================================

USE loan_system;

-- Create loan_completed_history table
CREATE TABLE IF NOT EXISTS loan_completed_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id INT NOT NULL,
    loan_type ENUM('e_loan', 'atm_loan') NOT NULL,
    loan_amount DECIMAL(10, 2) NOT NULL,
    company_id_type VARCHAR(100) NULL,
    government_id_type VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    address TEXT NULL,
    loan_purpose TEXT NULL,
    status VARCHAR(50) NOT NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_loan_type (loan_type),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Migration Complete
-- =============================================

