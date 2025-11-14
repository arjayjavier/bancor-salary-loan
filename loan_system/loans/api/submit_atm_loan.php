<?php
/**
 * Submit ATM Loan Application API
 * Handles ATM loan application submissions with file uploads
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

// Create uploads directory if it doesn't exist
$uploadDir = '../../uploads/atm_loans/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Create user-specific directory
$userUploadDir = $uploadDir . 'user_' . $user['id'] . '/';
if (!file_exists($userUploadDir)) {
    mkdir($userUploadDir, 0777, true);
}

try {
    // Validate required fields
    $requiredFields = ['loanAmount', 'companyIdType', 'governmentIdType', 'contactNumber'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }

    $loanAmount = floatval($_POST['loanAmount']);
    
    // Validate loan amount
    if ($loanAmount < 1000 || $loanAmount > 30000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Loan amount must be between ₱1,000 and ₱30,000']);
        exit;
    }

    $isIqorEmployee = isset($_POST['isIqorEmployee']) && $_POST['isIqorEmployee'] === 'on';
    
    // Validate required files
    $requiredFiles = [
        'companyIdPhoto' => 'Company ID Photo',
        'governmentIdPhoto' => 'Government ID Photo',
        'coePayslip' => 'COE / Payslip',
        'simcardPhoto' => 'Simcard Photo',
        'onlineAccountPhoto' => 'Online Account Screenshot'
    ];
    
    if (!$isIqorEmployee) {
        $requiredFiles['bankStatement'] = 'Bank Statement';
    }
    
    $uploadedFiles = [];
    
    foreach ($requiredFiles as $field => $label) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Please upload: $label"]);
            exit;
        }
        
        $file = $_FILES[$field];
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $field . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $userUploadDir . $fileName;
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Invalid file type for $label. Only images and PDF are allowed."]);
            exit;
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "$label is too large. Maximum size is 5MB."]);
            exit;
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Failed to upload $label"]);
            exit;
        }
        
        $uploadedFiles[$field] = $filePath;
    }

    // Handle verification photo (from camera)
    $verificationPhotoPath = null;
    if (isset($_FILES['verificationPhotoFile']) && $_FILES['verificationPhotoFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['verificationPhotoFile'];
        $fileName = 'verification_' . time() . '_' . uniqid() . '.png';
        $filePath = $userUploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $verificationPhotoPath = $filePath;
        }
    }

    // Save application to database
    $stmt = $pdo->prepare("
        INSERT INTO atm_loans (
            user_id, loan_amount, company_id_type, company_id_photo_path,
            government_id_type, government_id_photo_path, coe_payslip_path,
            simcard_photo_path, online_account_photo_path, bank_statement_path,
            is_iqor_employee, verification_photo_path, contact_number,
            address, loan_purpose, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $address = isset($_POST['address']) ? trim($_POST['address']) : null;
    $loanPurpose = isset($_POST['loanPurpose']) ? trim($_POST['loanPurpose']) : null;
    $bankStatementPath = isset($uploadedFiles['bankStatement']) ? $uploadedFiles['bankStatement'] : null;
    
    $stmt->execute([
        $user['id'],
        $loanAmount,
        $_POST['companyIdType'],
        $uploadedFiles['companyIdPhoto'],
        $_POST['governmentIdType'],
        $uploadedFiles['governmentIdPhoto'],
        $uploadedFiles['coePayslip'],
        $uploadedFiles['simcardPhoto'],
        $uploadedFiles['onlineAccountPhoto'],
        $bankStatementPath,
        $isIqorEmployee ? 1 : 0,
        $verificationPhotoPath,
        $_POST['contactNumber'],
        $address,
        $loanPurpose
    ]);
    
    $loanId = $pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'ATM_LOAN_APPLICATION', ?, ?)
    ");
    $description = "ATM Loan Application #$loanId submitted: Amount: ₱" . number_format($loanAmount, 2);
    $stmt->execute([$user['id'], $description, $_SERVER['REMOTE_ADDR'] ?? '']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'ATM Loan application submitted successfully! Your application is now pending admin approval.',
        'loan_id' => $loanId,
        'loan_amount' => $loanAmount
    ]);

} catch (PDOException $e) {
    error_log("ATM Loan Application Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit application. Please try again.']);
}

