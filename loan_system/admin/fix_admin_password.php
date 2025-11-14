<?php
/**
 * Fix Admin Password Script
 * This script will update the admin password to 'admin123'
 * Run this once to fix the admin account password
 */

require_once '../config/database.php';

// Generate the correct password hash for 'admin123'
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

echo "<h2>Admin Password Fix Script</h2>";
echo "<p>This script will update the admin password in the database.</p>";

try {
    $pdo = getDBConnection();
    
    if ($pdo === null) {
        die("<p style='color: red;'>❌ Database connection failed!</p>");
    }
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE email = 'admin@loansystem.com'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Create admin if doesn't exist
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, status, email_verified) 
            VALUES (?, ?, ?, 'admin', 'active', TRUE)
        ");
        $stmt->execute(['Admin User', 'admin@loansystem.com', $hashedPassword]);
        echo "<p style='color: green;'>✅ Admin account created successfully!</p>";
    } else {
        // Update existing admin password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@loansystem.com'");
        $stmt->execute([$hashedPassword]);
        echo "<p style='color: green;'>✅ Admin password updated successfully!</p>";
    }
    
    echo "<hr>";
    echo "<h3>Admin Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> admin@loansystem.com</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p><strong>Hashed Password:</strong> " . $hashedPassword . "</p>";
    echo "<hr>";
    echo "<p><a href='../index.php'>← Go to Login Page</a></p>";
    echo "<p style='color: orange;'><strong>⚠️ Important:</strong> Delete this file (fix_admin_password.php) after use for security!</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

