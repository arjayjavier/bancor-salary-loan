<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
$isLoggedIn = false;
$user = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    $pdo = getDBConnection();
    if ($pdo !== null) {
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
        $isLoggedIn = $user !== false;
    }
}

// Check if user is admin
if (!$isLoggedIn || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Validate required fields
    if (!isset($_POST['user_id']) || !isset($_POST['loan_id']) || !isset($_POST['loan_type']) || 
        !isset($_POST['days_overdue']) || !isset($_POST['amount_to_pay'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $userId = intval($_POST['user_id']);
    $loanId = intval($_POST['loan_id']);
    $loanType = $_POST['loan_type'];
    $daysOverdue = intval($_POST['days_overdue']);
    $amountToPay = floatval($_POST['amount_to_pay']);
    $dueDate = isset($_POST['due_date']) && !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    // Validate loan type
    if (!in_array($loanType, ['e_loan', 'atm_loan'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid loan type']);
        exit;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if loan exists
    $loanTable = $loanType === 'e_loan' ? 'e_loans' : 'atm_loans';
    $stmt = $pdo->prepare("SELECT id FROM $loanTable WHERE id = ? AND user_id = ?");
    $stmt->execute([$loanId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/qr_codes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $qrCode1Path = null;
    $qrCode2Path = null;
    
    // Handle QR Code 1 upload
    if (isset($_FILES['qr_code_1']) && $_FILES['qr_code_1']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_code_1'];
        $fileName = 'qr1_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $qrCode1Path = 'uploads/qr_codes/' . $fileName;
        }
    }
    
    // Handle QR Code 2 upload
    if (isset($_FILES['qr_code_2']) && $_FILES['qr_code_2']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_code_2'];
        $fileName = 'qr2_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $qrCode2Path = 'uploads/qr_codes/' . $fileName;
        }
    }
    
    // Insert overdue payment
    $stmt = $pdo->prepare("
        INSERT INTO overdue_payments (
            user_id, loan_id, loan_type, days_overdue, amount_to_pay,
            qr_code_1_path, qr_code_2_path, due_date, is_settled
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE)
    ");
    
    $stmt->execute([
        $userId,
        $loanId,
        $loanType,
        $daysOverdue,
        $amountToPay,
        $qrCode1Path,
        $qrCode2Path,
        $dueDate
    ]);
    
    $paymentId = $pdo->lastInsertId();
    
    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'CREATE_OVERDUE_PAYMENT', ?, ?)
    ");
    $description = "Created overdue payment #$paymentId for user #$userId - Amount: ₱" . number_format($amountToPay, 2);
    $stmt->execute([$user['id'], $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Overdue payment created successfully',
        'payment_id' => $paymentId
    ]);
    
} catch (PDOException $e) {
    error_log("Create Overdue Payment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create overdue payment. Please try again.']);
}
?>

