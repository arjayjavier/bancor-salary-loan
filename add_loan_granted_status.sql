-- =============================================
-- Add 'loan_granted' Status to Loan Tables
-- =============================================
-- This migration adds 'loan_granted' status to both e_loans and atm_loans tables
-- =============================================

USE loan_system;

-- Update e_loans table to include 'loan_granted' status
ALTER TABLE e_loans 
MODIFY status ENUM('pending', 'approved', 'disapproved', 'loan_granted') NOT NULL DEFAULT 'pending';

-- Update atm_loans table to include 'loan_granted' status
ALTER TABLE atm_loans 
MODIFY status ENUM('pending', 'approved', 'disapproved', 'loan_granted') NOT NULL DEFAULT 'pending';

-- =============================================
-- Migration Complete
-- =============================================

