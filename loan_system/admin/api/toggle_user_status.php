<?php
/**
 * Toggle User Status API
 * Admin only - Activate/Deactivate users
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

if (!isset($input['user_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = (int)$input['user_id'];
$newStatus = $input['status'];

// Validate status
if (!in_array($newStatus, ['active', 'inactive', 'suspended'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Prevent admin from deactivating themselves
if ($userId == $admin['id'] && $newStatus !== 'active') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
    exit;
}

try {
    // Get current user info
    $stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Update user status
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $userId]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address) 
        VALUES (?, 'USER_STATUS_CHANGE', ?, ?)
    ");
    $description = "Admin changed user status: {$targetUser['email']} from {$targetUser['status']} to {$newStatus}";
    $stmt->execute([$admin['id'], $description, $_SERVER['REMOTE_ADDR'] ?? '']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'User status updated successfully',
        'user_id' => $userId,
        'new_status' => $newStatus
    ]);

} catch (PDOException $e) {
    error_log("Toggle User Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
}

