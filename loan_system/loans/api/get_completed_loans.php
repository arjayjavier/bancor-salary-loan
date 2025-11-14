<?php
/**
 * Get Completed Loans API
 * Returns completed loan history for the logged-in user
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

// Get filter parameters
$loanType = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'e_loan', 'atm_loan', or 'all'

try {
    $sql = "
        SELECT 
            id,
            loan_id,
            loan_type,
            loan_amount,
            company_id_type,
            government_id_type,
            contact_number,
            address,
            loan_purpose,
            status,
            admin_notes,
            created_at,
            reviewed_at,
            completed_at
        FROM loan_completed_history
        WHERE user_id = ?
    ";
    
    $params = [$user['id']];
    if ($loanType !== 'all') {
        $sql .= " AND loan_type = ?";
        $params[] = $loanType;
    }
    
    $sql .= " ORDER BY completed_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'loans' => $loans,
        'count' => count($loans)
    ]);
    
} catch (PDOException $e) {
    error_log("Get Completed Loans Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve completed loans. Please try again.']);
}

