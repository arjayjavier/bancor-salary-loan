-- =============================================
-- Loan Applications Database Tables
-- =============================================
-- Add these tables to your existing loan_system database
-- =============================================

USE loan_system;

-- =============================================
-- E Loans Table
-- =============================================
CREATE TABLE IF NOT EXISTS e_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_amount DECIMAL(10, 2) NOT NULL,
    company_id_type VARCHAR(100) NOT NULL,
    government_id_type VARCHAR(100) NOT NULL,
    ids_with_signatures_path VARCHAR(500) NOT NULL,
    verification_photo_path VARCHAR(500) NULL,
    contact_number VARCHAR(20) NOT NULL,
    address TEXT NULL,
    loan_purpose TEXT NULL,
    status ENUM('pending', 'approved', 'disapproved') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ATM Loans Table
-- =============================================
CREATE TABLE IF NOT EXISTS atm_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_amount DECIMAL(10, 2) NOT NULL,
    company_id_type VARCHAR(100) NOT NULL,
    company_id_photo_path VARCHAR(500) NOT NULL,
    government_id_type VARCHAR(100) NOT NULL,
    government_id_photo_path VARCHAR(500) NOT NULL,
    coe_payslip_path VARCHAR(500) NOT NULL,
    simcard_photo_path VARCHAR(500) NOT NULL,
    online_account_photo_path VARCHAR(500) NOT NULL,
    bank_statement_path VARCHAR(500) NULL,
    is_iqor_employee BOOLEAN DEFAULT FALSE,
    verification_photo_path VARCHAR(500) NULL,
    contact_number VARCHAR(20) NOT NULL,
    address TEXT NULL,
    loan_purpose TEXT NULL,
    status ENUM('pending', 'approved', 'disapproved') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Loan Files Table (for additional files if needed)
-- =============================================
CREATE TABLE IF NOT EXISTS loan_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_type ENUM('e_loan', 'atm_loan') NOT NULL,
    loan_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loan (loan_type, loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

