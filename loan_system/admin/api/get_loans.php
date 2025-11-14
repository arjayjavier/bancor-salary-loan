<?php
/**
 * Get Loans API
 * Returns all loan applications for admin review
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
                el.ids_with_signatures_path,
                el.verification_photo_path,
                u.id as user_id,
                u.name as user_name,
                u.email as user_email,
                reviewer.name as reviewed_by_name
            FROM e_loans el
            INNER JOIN users u ON el.user_id = u.id
            LEFT JOIN users reviewer ON el.reviewed_by = reviewer.id
        ";
        
        $params = [];
        if ($status !== 'all') {
            $sql .= " WHERE el.status = ?";
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
                u.id as user_id,
                u.name as user_name,
                u.email as user_email,
                reviewer.name as reviewed_by_name,
                al.company_id_photo_path,
                al.government_id_photo_path,
                al.coe_payslip_path,
                al.simcard_photo_path,
                al.online_account_photo_path,
                al.bank_statement_path,
                al.verification_photo_path
            FROM atm_loans al
            INNER JOIN users u ON al.user_id = u.id
            LEFT JOIN users reviewer ON al.reviewed_by = reviewer.id
        ";
        
        $params = [];
        if ($status !== 'all') {
            $sql .= " WHERE al.status = ?";
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
    error_log("Get Loans Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve loans. Please try again.']);
}

