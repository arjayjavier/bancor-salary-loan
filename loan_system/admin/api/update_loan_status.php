<?php
/**
 * Update Loan Status API
 * Allows admin to approve or disapprove loan applications
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
if (!isset($input['loan_type']) || !isset($input['loan_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: loan_type, loan_id, status']);
    exit;
}

$loanType = $input['loan_type']; // 'e_loan' or 'atm_loan'
$loanId = intval($input['loan_id']);
$status = $input['status']; // 'pending', 'approved', 'disapproved' or 'loan_granted'
$adminNotes = isset($input['admin_notes']) ? trim($input['admin_notes']) : null;

// Validate status
if (!in_array($status, ['pending', 'approved', 'disapproved', 'loan_granted'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status. Must be "pending", "approved", "disapproved" or "loan_granted"']);
    exit;
}

// Validate loan type
if (!in_array($loanType, ['e_loan', 'atm_loan'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid loan type. Must be "e_loan" or "atm_loan"']);
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
    
    // Update loan status
    $stmt = $pdo->prepare("
        UPDATE $tableName 
        SET status = ?, 
            reviewed_by = ?, 
            reviewed_at = CURRENT_TIMESTAMP,
            admin_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $admin['id'], $adminNotes, $loanId]);
    
    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $action = strtoupper($loanType) . '_' . strtoupper($status);
    $description = "Loan Application #$loanId ($loanType) $status by admin " . $admin['name'];
    if ($adminNotes) {
        $description .= ". Notes: " . substr($adminNotes, 0, 100);
    }
    $stmt->execute([$admin['id'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Loan application $status successfully",
        'loan_id' => $loanId,
        'status' => $status
    ]);
    
} catch (PDOException $e) {
    error_log("Update Loan Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update loan status. Please try again.']);
}

