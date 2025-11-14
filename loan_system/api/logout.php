<?php
/**
 * User Logout API
 * Handles user logout requests
 */

require_once '../config/database.php';

session_start();

// Check if this is a GET request (direct link) or POST request (API call)
$isGetRequest = $_SERVER['REQUEST_METHOD'] === 'GET';
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isGetRequest && !$isPostRequest) {
    http_response_code(405);
    if ($isGetRequest) {
        header('Location: ../index.php');
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (isset($_SESSION['session_token']) && $pdo !== null) {
        // Deactivate session in database
        $stmt = $pdo->prepare("UPDATE sessions SET is_active = FALSE WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
        
        // Log logout activity
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'LOGOUT', 'User logged out', ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        }
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // If GET request, redirect to login page
    if ($isGetRequest) {
        header('Location: ../index.php?logout=success');
        exit;
    }
    
    // If POST request, return JSON response
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    
} catch (PDOException $e) {
    error_log("Logout Error: " . $e->getMessage());
    
    if ($isGetRequest) {
        header('Location: ../index.php?logout=error');
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Logout failed']);
    }
    exit;
}

