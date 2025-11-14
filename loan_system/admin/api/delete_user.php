<?php
/**
 * Delete User API
 * Admin only - Delete users
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

if (!isset($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

$userId = (int)$input['user_id'];

// Prevent admin from deleting themselves
if ($userId == $admin['id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

try {
    // Get user info before deletion
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Prevent deleting other admins
    if ($targetUser['role'] === 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
        exit;
    }

    // Delete user (cascade will handle related records)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'USER_DELETE', ?, ?)
    ");
    $description = "Admin deleted user: {$targetUser['email']} (ID: {$targetUser['id']})";
    $stmt->execute([$admin['id'], $description, $_SERVER['REMOTE_ADDR'] ?? '']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully',
        'deleted_user_id' => $userId
    ]);

} catch (PDOException $e) {
    error_log("Delete User Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}

