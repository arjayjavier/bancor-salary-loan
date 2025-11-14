<?php
/**
 * Update My Loan API
 * Allows users to update their own loan applications (only if pending)
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
    
    // Check if loan exists and belongs to user, and is still pending
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM $tableName WHERE id = ? AND user_id = ?");
    $stmt->execute([$loanId, $user['id']]);
    $loan = $stmt->fetch();
    
    if (!$loan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan application not found']);
        exit;
    }
    
    // Only allow editing if status is pending
    if ($loan['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot edit loan application. Only pending applications can be edited.']);
        exit;
    }
    
    // Update loan based on type
    if ($loanType === 'e_loan') {
        $updateFields = [];
        $params = [];
        
        if (isset($input['loan_amount'])) {
            $updateFields[] = "loan_amount = ?";
            $params[] = floatval($input['loan_amount']);
        }
        if (isset($input['company_id_type'])) {
            $updateFields[] = "company_id_type = ?";
            $params[] = $input['company_id_type'];
        }
        if (isset($input['government_id_type'])) {
            $updateFields[] = "government_id_type = ?";
            $params[] = $input['government_id_type'];
        }
        if (isset($input['contact_number'])) {
            $updateFields[] = "contact_number = ?";
            $params[] = $input['contact_number'];
        }
        if (isset($input['address'])) {
            $updateFields[] = "address = ?";
            $params[] = trim($input['address']) ?: null;
        }
        if (isset($input['loan_purpose'])) {
            $updateFields[] = "loan_purpose = ?";
            $params[] = trim($input['loan_purpose']) ?: null;
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        $params[] = $loanId;
        $sql = "UPDATE $tableName SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
    } else { // atm_loan
        $updateFields = [];
        $params = [];
        
        if (isset($input['loan_amount'])) {
            $updateFields[] = "loan_amount = ?";
            $params[] = floatval($input['loan_amount']);
        }
        if (isset($input['company_id_type'])) {
            $updateFields[] = "company_id_type = ?";
            $params[] = $input['company_id_type'];
        }
        if (isset($input['government_id_type'])) {
            $updateFields[] = "government_id_type = ?";
            $params[] = $input['government_id_type'];
        }
        if (isset($input['contact_number'])) {
            $updateFields[] = "contact_number = ?";
            $params[] = $input['contact_number'];
        }
        if (isset($input['address'])) {
            $updateFields[] = "address = ?";
            $params[] = trim($input['address']) ?: null;
        }
        if (isset($input['loan_purpose'])) {
            $updateFields[] = "loan_purpose = ?";
            $params[] = trim($input['loan_purpose']) ?: null;
        }
        if (isset($input['is_iqor_employee'])) {
            $updateFields[] = "is_iqor_employee = ?";
            $params[] = $input['is_iqor_employee'] ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        $params[] = $loanId;
        $sql = "UPDATE $tableName SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $action = strtoupper($loanType) . '_UPDATED';
    $description = "User updated loan application #$loanId ($loanType)";
    $stmt->execute([$user['id'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Loan application updated successfully',
        'loan_id' => $loanId
    ]);
    
} catch (PDOException $e) {
    error_log("Update Loan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update loan application. Please try again.']);
}

