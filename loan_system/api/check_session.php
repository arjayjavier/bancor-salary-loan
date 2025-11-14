<?php
/**
 * Session Check API
 * Validates user session
 */

header('Content-Type: application/json');
require_once '../config/database.php';

session_start();

try {
    $pdo = getDBConnection();
    
    if ($pdo === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check if session exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    // Validate session token
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.expires_at, s.is_active,
               u.name, u.email, u.role, u.status
        FROM sessions s
        INNER JOIN users u ON s.user_id = u.id
        WHERE s.session_token = ?
          AND s.is_active = TRUE
          AND s.expires_at > CURRENT_TIMESTAMP
          AND u.status = 'active'
    ");
    $stmt->execute([$_SESSION['session_token']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        // Invalid session, destroy it
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $session['user_id'],
            'name' => $session['name'],
            'email' => $session['email'],
            'role' => $session['role']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Session Check Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Session validation failed']);
}

