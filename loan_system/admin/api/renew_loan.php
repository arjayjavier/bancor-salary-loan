<?php
/**
 * Renew Loan API
 * Allows admin to delete a loan so user can apply again
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$pdo = getDBConnection();
if ($pdo === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify session and admin role
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.status
    FROM sessions s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.session_token = ?
      AND s.is_active = TRUE
      AND s.expires_at > CURRENT_TIMESTAMP
      AND u.status = 'active'
      AND u.role = 'admin'
");
$stmt->execute([$_SESSION['session_token']]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['loan_type']) || !isset($input['loan_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: loan_type, loan_id']);
    exit;
}

$loanType = $input['loan_type']; // 'e_loan' or 'atm_loan'
$loanId = intval($input['loan_id']);

// Validate loan type
if (!in_array($loanType, ['e_loan', 'atm_loan'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid loan type']);
    exit;
}

try {
    $tableName = $loanType === 'e_loan' ? 'e_loans' : 'atm_loans';
    
    // Check if loan exists
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM $tableName WHERE id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan application not found']);
        exit;
    }
    
    // Only allow renewing if status is loan_granted
    if ($loan['status'] !== 'loan_granted') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only loan_granted loans can be renewed']);
        exit;
    }
    
    // Get full loan details before deleting
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE id = ?");
    $stmt->execute([$loanId]);
    $loanDetails = $stmt->fetch();
    
    // Save to completed history before deleting
    try {
        $stmt = $pdo->prepare("
            INSERT INTO loan_completed_history (
                user_id, loan_id, loan_type, loan_amount,
                company_id_type, government_id_type, contact_number,
                address, loan_purpose, status, admin_notes,
                created_at, reviewed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $loanDetails['user_id'],
            $loanDetails['id'],
            $loanType,
            $loanDetails['loan_amount'],
            $loanDetails['company_id_type'] ?? null,
            $loanDetails['government_id_type'] ?? null,
            $loanDetails['contact_number'] ?? null,
            $loanDetails['address'] ?? null,
            $loanDetails['loan_purpose'] ?? null,
            $loanDetails['status'],
            $loanDetails['admin_notes'] ?? null,
            $loanDetails['created_at'],
            $loanDetails['reviewed_at'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Save to Completed History Warning: " . $e->getMessage());
        // Continue even if saving to history fails
    }
    
    // Delete the loan application
    $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = ?");
    $stmt->execute([$loanId]);
    
    // Mark any overdue payments for this loan as settled
    try {
        $stmt = $pdo->prepare("
            UPDATE overdue_payments
            SET is_settled = TRUE,
                settled_at = CURRENT_TIMESTAMP
            WHERE loan_id = ?
              AND loan_type = ?
              AND is_settled = FALSE
        ");
        $stmt->execute([$loanId, $loanType]);
    } catch (PDOException $e) {
        error_log("Renew Loan Overdue Update Warning: " . $e->getMessage());
        // Continue even if updating overdue payments fails
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Loan renewed successfully. User can now apply for a new loan.',
        'user_id' => $loan['user_id']
    ]);
    
} catch (PDOException $e) {
    error_log("Renew Loan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to renew loan. Please try again.']);
}

