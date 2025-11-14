<?php
/**
 * Register Admin API
 * Admin only - Create new admin accounts
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

// Verify admin session
$stmt = $pdo->prepare("
    SELECT u.id, u.role, u.status
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
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
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
if (!isset($input['name']) || !isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$name = trim($input['name']);
$email = trim($input['email']);
$password = $input['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password length
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Store password as plain text (NO HASHING)
    // WARNING: This is not secure! Passwords will be visible in database.
    
    // Insert new admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, status, email_verified) 
        VALUES (?, ?, ?, 'admin', 'active', TRUE)
    ");
    
    $stmt->execute([$name, $email, $password]);
    
    $userId = $pdo->lastInsertId();
    
    // Log admin creation activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'ADMIN_CREATE', ?, ?)
    ");
    $description = "Admin created new admin account: $email (ID: $userId)";
    $stmt->execute([$admin['id'], $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Admin account created successfully',
        'user_id' => $userId,
        'email' => $email
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

