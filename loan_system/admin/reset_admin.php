<?php
/**
 * Quick Admin Password Reset
 * Access this file in browser to reset admin password
 */

require_once '../config/database.php';

$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: green; }
        .error { color: red; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔧 Admin Password Reset</h2>
        
        <?php
        try {
            $pdo = getDBConnection();
            
            if ($pdo === null) {
                echo "<p class='error'>❌ Database connection failed!</p>";
                echo "<p>Please check your database configuration in config/database.php</p>";
            } else {
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
                    echo "<p class='success'>✅ Admin account created successfully!</p>";
                } else {
                    // Update existing admin password
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@loansystem.com'");
                    $stmt->execute([$hashedPassword]);
                    echo "<p class='success'>✅ Admin password updated successfully!</p>";
                }
                
                echo "<div class='info'>";
                echo "<h3>Admin Login Credentials:</h3>";
                echo "<p><strong>Email:</strong> admin@loansystem.com</p>";
                echo "<p><strong>Password:</strong> admin123</p>";
                echo "</div>";
                
                echo "<div class='warning'>";
                echo "<p><strong>⚠️ Security Warning:</strong></p>";
                echo "<p>Please delete this file (admin/reset_admin.php) after use!</p>";
                echo "</div>";
                
                echo "<p><a href='../index.php'>← Go to Login Page</a></p>";
            }
            
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>

