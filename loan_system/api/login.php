<?php
/**
 * User Login API
 * Handles user login requests
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Start session
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];
$remember = isset($input['remember']) && $input['remember'] === true;

try {
    $pdo = getDBConnection();
    
    if ($pdo === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get user by email
    $stmt = $pdo->prepare("
        SELECT id, name, email, password, role, status, email_verified 
        FROM users 
        WHERE email = ? AND status = 'active'
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Verify user exists and password is correct (plain text comparison)
    // WARNING: This compares plain text passwords - not secure!
    if (!$user || $password !== $user['password']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Generate session token
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // 24 hour session
    
    // Create session record
    $stmt = $pdo->prepare("
        INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $sessionToken,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expiresAt
    ]);
    
    // Set PHP session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['session_token'] = $sessionToken;
    
    // Set cookie if remember me is checked
    if ($remember) {
        setcookie('remember_token', $sessionToken, time() + (86400 * 30), '/'); // 30 days
    }
    
    // Log login activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
        VALUES (?, 'LOGIN', 'User logged in', ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'session_token' => $sessionToken
    ]);
    
} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
}

