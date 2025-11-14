<?php
/**
 * Grant Loan API
 * Allows users to mark their approved loan as "loan_granted"
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

session_start();

// Check if user is logged in
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

// Verify session
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.status
    FROM sessions s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.session_token = ?
      AND s.is_active = TRUE
      AND s.expires_at > CURRENT_TIMESTAMP
      AND u.status = 'active'
");
$stmt->execute([$_SESSION['session_token']]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
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
    
    // Check if loan exists and belongs to user, and is approved
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM $tableName WHERE id = ? AND user_id = ?");
    $stmt->execute([$loanId, $user['id']]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan application not found']);
        exit;
    }
    
    // Only allow granting if status is approved
    if ($loan['status'] !== 'approved') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only approved loans can be granted']);
        exit;
    }
    
    // Update loan status to loan_granted
    // Note: This assumes the database ENUM has been updated to include 'loan_granted'
    // If not, you'll need to run: ALTER TABLE e_loans MODIFY status ENUM('pending', 'approved', 'disapproved', 'loan_granted') NOT NULL DEFAULT 'pending';
    // ALTER TABLE atm_loans MODIFY status ENUM('pending', 'approved', 'disapproved', 'loan_granted') NOT NULL DEFAULT 'pending';
    $stmt = $pdo->prepare("UPDATE $tableName SET status = 'loan_granted' WHERE id = ?");
    $stmt->execute([$loanId]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Loan granted successfully',
        'loan_id' => $loanId,
        'loan_type' => $loanType
    ]);
    
} catch (PDOException $e) {
    error_log("Grant Loan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to grant loan. Please try again.']);
}

