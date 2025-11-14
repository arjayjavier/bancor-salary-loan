<?php
/**
 * Get My Loans API
 * Returns loan applications for the logged-in user
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
$status = isset($_GET['status']) ? $_GET['status'] : 'all'; // 'pending', 'approved', 'disapproved', or 'all'

try {
    $loans = [];
    
    // Get E Loans
    if ($loanType === 'all' || $loanType === 'e_loan') {
        $sql = "
            SELECT 
                el.id,
                'e_loan' as loan_type,
                el.loan_amount,
                el.company_id_type,
                el.government_id_type,
                el.contact_number,
                el.address,
                el.loan_purpose,
                el.status,
                el.admin_notes,
                el.created_at,
                el.reviewed_at,
                reviewer.name as reviewed_by_name
            FROM e_loans el
            LEFT JOIN users reviewer ON el.reviewed_by = reviewer.id
            WHERE el.user_id = ?
        ";
        
        $params = [$user['id']];
        if ($status !== 'all') {
            $sql .= " AND el.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY el.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $eLoans = $stmt->fetchAll();
        $loans = array_merge($loans, $eLoans);
    }
    
    // Get ATM Loans
    if ($loanType === 'all' || $loanType === 'atm_loan') {
        $sql = "
            SELECT 
                al.id,
                'atm_loan' as loan_type,
                al.loan_amount,
                al.company_id_type,
                al.government_id_type,
                al.contact_number,
                al.address,
                al.loan_purpose,
                al.status,
                al.admin_notes,
                al.is_iqor_employee,
                al.created_at,
                al.reviewed_at,
                reviewer.name as reviewed_by_name
            FROM atm_loans al
            LEFT JOIN users reviewer ON al.reviewed_by = reviewer.id
            WHERE al.user_id = ?
        ";
        
        $params = [$user['id']];
        if ($status !== 'all') {
            $sql .= " AND al.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY al.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $atmLoans = $stmt->fetchAll();
        $loans = array_merge($loans, $atmLoans);
    }
    
    // Sort by created_at descending
    usort($loans, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'loans' => $loans,
        'count' => count($loans)
    ]);
    
} catch (PDOException $e) {
    error_log("Get My Loans Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve loans. Please try again.']);
}

