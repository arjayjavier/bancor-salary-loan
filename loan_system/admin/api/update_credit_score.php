<?php
/**
 * Update Credit Score API
 * Allows admin to manually set user's credit score override
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

// Verify session and admin role
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.status
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
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
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
if (!isset($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required field: user_id']);
    exit;
}

$userId = intval($input['user_id']);
$creditScoreOverride = isset($input['credit_score_override']) ? $input['credit_score_override'] : null;

// Validate credit_score_override
if ($creditScoreOverride !== null && !in_array($creditScoreOverride, ['good', 'bad'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid credit_score_override. Must be "good", "bad", or null']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if column exists, if not create it
    try {
        $stmt = $pdo->query("SELECT credit_score_override FROM users LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, create it
        $pdo->exec("ALTER TABLE users ADD COLUMN credit_score_override ENUM('good', 'bad') DEFAULT NULL AFTER status");
    }
    
    // Update credit score override
    $stmt = $pdo->prepare("UPDATE users SET credit_score_override = ? WHERE id = ?");
    $stmt->execute([$creditScoreOverride, $userId]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Credit score updated successfully',
        'user_id' => $userId,
        'credit_score_override' => $creditScoreOverride
    ]);
    
} catch (PDOException $e) {
    error_log("Update Credit Score Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update credit score. Please try again.']);
}

