-- =============================================
-- Add Credit Score Override to Users Table
-- =============================================
-- This migration adds credit_score_override field to users table
-- =============================================

USE loan_system;

-- Add credit_score_override column to users table
ALTER TABLE users 
ADD COLUMN credit_score_override ENUM('good', 'bad') DEFAULT NULL AFTER status;

-- =============================================
-- Migration Complete
-- =============================================

