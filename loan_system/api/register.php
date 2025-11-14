<?php
/**
 * User Registration API
 * Handles user registration requests
 */

header('Content-Type: application/json');
require_once '../config/database.php';

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
    $pdo = getDBConnection();
    
    if ($pdo === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
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
    
    // Insert new user (default role is 'user')
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, status, email_verified) 
        VALUES (?, ?, ?, 'user', 'active', FALSE)
    ");
    
    $stmt->execute([$name, $email, $password]);
    
    $userId = $pdo->lastInsertId();
    
    // Log registration activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'REGISTER', ?, ?)
    ");
    $stmt->execute([$userId, "User registered: $email", $_SERVER['REMOTE_ADDR'] ?? '']);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'user_id' => $userId
    ]);
    
} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

