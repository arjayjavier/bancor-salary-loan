-- =============================================
-- Add Overdue Payments Table
-- =============================================
-- This migration adds overdue_payments table to track customer overdue payments
-- =============================================

USE loan_system;

-- Create overdue_payments table
CREATE TABLE IF NOT EXISTS overdue_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id INT NOT NULL,
    loan_type ENUM('e_loan', 'atm_loan') NOT NULL,
    days_overdue INT DEFAULT 0,
    amount_to_pay DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    qr_code_1_path VARCHAR(500) NULL,
    qr_code_2_path VARCHAR(500) NULL,
    due_date DATE NULL,
    is_settled BOOLEAN DEFAULT FALSE,
    settled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_loan (loan_type, loan_id),
    INDEX idx_is_settled (is_settled),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Migration Complete
-- =============================================

