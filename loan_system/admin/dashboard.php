<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
$isLoggedIn = false;
$user = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    $pdo = getDBConnection();
    if ($pdo !== null) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, u.status
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.session_token = ?
              AND s.is_active = TRUE
              AND s.expires_at > CURRENT_TIMESTAMP
              AND u.status = 'active'
        ");
        $stmt->execute([$_SESSION['session_token']]);
        $user = $stmt->fetch();
        $isLoggedIn = $user !== false;
    }
}

// Redirect if not logged in or not admin
if (!$isLoggedIn || $user['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get statistics
$pdo = getDBConnection();
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Active users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stats['active_users'] = $stmt->fetch()['total'];

// Admin users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$stats['admin_users'] = $stmt->fetch()['total'];

// Regular users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['regular_users'] = $stmt->fetch()['total'];

// Active sessions
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions WHERE is_active = TRUE AND expires_at > NOW()");
$stats['active_sessions'] = $stmt->fetch()['total'];

// Recent activity logs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['recent_activities'] = $stmt->fetch()['total'];

// Get all users for management
$stmt = $pdo->query("
    SELECT id, name, email, role, status, email_verified, created_at, last_login 
    FROM users 
    ORDER BY created_at DESC
");
$allUsers = $stmt->fetchAll();

// Get recent activity logs
$stmt = $pdo->query("
    SELECT al.*, u.name as user_name, u.email as user_email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 20
");
$recentLogs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Loan System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 24px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: white;
            color: #210a1a;
        }

        .btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            color: #333;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .icon {
            font-size: 40px;
            float: right;
            opacity: 0.2;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h2 {
            color: #333;
            font-size: 22px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-top: 1px solid #f0f0f0;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-suspended {
            background: #fff3cd;
            color: #856404;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .role-admin {
            background: #210a1a;
            color: white;
        }

        .role-user {
            background: #6c757d;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #210a1a;
            color: white;
        }

        .btn-edit:hover {
            background: #1a080f;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-toggle {
            background: #28a745;
            color: white;
        }

        .btn-toggle:hover {
            background: #218838;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #210a1a;
            border-bottom-color: #210a1a;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .log-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-info {
            flex: 1;
        }

        .log-action {
            font-weight: 600;
            color: #210a1a;
            margin-right: 10px;
        }

        .log-description {
            color: #666;
            font-size: 14px;
        }

        .log-time {
            color: #999;
            font-size: 12px;
        }

        .user-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #210a1a;
            outline: none;
        }

        .filter-group input {
            min-width: 250px;
        }

        .filter-group select {
            min-width: 150px;
        }

        .user-count {
            margin-left: auto;
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏦 Admin Dashboard</h1>
            <div class="header-actions">
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user['name']); ?></span>
                    <span class="badge">ADMIN</span>
                </div>
                <a href="loans.php" class="btn btn-primary">📋 Loan Management</a>
                <a href="overdue_payments.php" class="btn btn-primary">💳 Overdue Payments</a>
                <a href="register_admin.php" class="btn btn-primary">+ Register Admin</a>
                <a href="../home.php" class="btn btn-primary">← Home</a>
                <a href="../api/logout.php" class="btn btn-secondary" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <h3>Total Users</h3>
                <div class="value"><?php echo $stats['total_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">✅</div>
                <h3>Active Users</h3>
                <div class="value"><?php echo $stats['active_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">👑</div>
                <h3>Admin Users</h3>
                <div class="value"><?php echo $stats['admin_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">👤</div>
                <h3>Regular Users</h3>
                <div class="value"><?php echo $stats['regular_users']; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">🔐</div>
                <h3>Active Sessions</h3>
                <div class="value"><?php echo $stats['active_sessions']; ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">📊</div>
                <h3>Recent Activities (24h)</h3>
                <div class="value"><?php echo $stats['recent_activities']; ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('users')">User Management</button>
                <button class="tab" onclick="switchTab('logs')">Activity Logs</button>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content active">
                <div class="card-header">
                    <h2>All Users</h2>
                </div>
                
                <div class="user-filters">
                    <div class="filter-group">
                        <label>🔍 Search</label>
                        <input type="text" id="userSearch" placeholder="Search by name or email..." onkeyup="filterUsers()">
                    </div>
                    <div class="filter-group">
                        <label>Role</label>
                        <select id="roleFilter" onchange="filterUsers()">
                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="statusFilter" onchange="filterUsers()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Email Verified</label>
                        <select id="emailVerifiedFilter" onchange="filterUsers()">
                            <option value="all">All</option>
                            <option value="yes">Verified</option>
                            <option value="no">Not Verified</option>
                        </select>
                    </div>
                    <div class="user-count">
                        <span id="userCount"><?php echo count($allUsers); ?></span> user(s) found
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Email Verified</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                            <tr class="user-row" 
                                data-name="<?php echo strtolower(htmlspecialchars($u['name'])); ?>"
                                data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>"
                                data-role="<?php echo $u['role']; ?>"
                                data-status="<?php echo $u['status']; ?>"
                                data-email-verified="<?php echo $u['email_verified'] ? 'yes' : 'no'; ?>">
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $u['role']; ?>">
                                        <?php echo strtoupper($u['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $u['status']; ?>">
                                        <?php echo ucfirst($u['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $u['email_verified'] ? '✅ Yes' : '❌ No'; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($u['last_login']) {
                                        echo date('M d, Y H:i', strtotime($u['last_login']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-edit" onclick="viewUserHistory(<?php echo $u['id']; ?>)">History</button>
                                        <?php if ($u['id'] != $user['id']): ?>
                                            <button class="btn-sm btn-toggle" onclick="toggleUserStatus(<?php echo $u['id']; ?>, '<?php echo $u['status']; ?>')">
                                                <?php echo $u['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-sm btn-edit" onclick="editUser(<?php echo $u['id']; ?>)">Edit</button>
                                        <?php if ($u['id'] != $user['id'] && $u['role'] != 'admin'): ?>
                                            <button class="btn-sm btn-delete" onclick="deleteUser(<?php echo $u['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="noResults" class="no-results" style="display: none;">
                        <p>No users found matching your filters.</p>
                    </div>
                </div>
            </div>

            <!-- Activity Logs Tab -->
            <div id="logs-tab" class="tab-content">
                <div class="card-header">
                    <h2>Recent Activity Logs</h2>
                </div>
                <div>
                    <?php if (empty($recentLogs)): ?>
                        <p style="text-align: center; color: #999; padding: 40px;">No activity logs found.</p>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item">
                            <div class="log-info">
                                <span class="log-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                <span class="log-description">
                                    <?php 
                                    if ($log['user_name']) {
                                        echo htmlspecialchars($log['user_name']) . ' - ';
                                    }
                                    echo htmlspecialchars($log['description']); 
                                    ?>
                                </span>
                                <div class="log-time">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                    <?php if ($log['ip_address']): ?>
                                        | IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        function toggleUserStatus(userId, currentStatus) {
            if (!confirm('Are you sure you want to change this user\'s status?')) {
                return;
            }

            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            fetch('api/toggle_user_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId, status: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User status updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update user status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function viewUserHistory(userId) {
            window.location.href = 'user_history.php?user_id=' + userId;
        }

        function editUser(userId) {
            alert('Edit user functionality will be implemented here.\nUser ID: ' + userId);
            // You can implement a modal or redirect to edit page
        }

        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone!')) {
                return;
            }

            fetch('api/delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete user'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function filterUsers() {
            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const emailVerifiedFilter = document.getElementById('emailVerifiedFilter').value;
            
            const rows = document.querySelectorAll('.user-row');
            const table = document.getElementById('usersTable');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const email = row.getAttribute('data-email');
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');
                const emailVerified = row.getAttribute('data-email-verified');
                
                // Check search term (name or email)
                const matchesSearch = !searchTerm || 
                    name.includes(searchTerm) || 
                    email.includes(searchTerm);
                
                // Check role filter
                const matchesRole = roleFilter === 'all' || role === roleFilter;
                
                // Check status filter
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                // Check email verified filter
                const matchesEmailVerified = emailVerifiedFilter === 'all' || emailVerified === emailVerifiedFilter;
                
                // Show or hide row based on all filters
                if (matchesSearch && matchesRole && matchesStatus && matchesEmailVerified) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update user count
            document.getElementById('userCount').textContent = visibleCount;
            
            // Show/hide table and no results message
            if (visibleCount === 0) {
                table.style.display = 'none';
                noResults.style.display = 'block';
            } else {
                table.style.display = '';
                noResults.style.display = 'none';
            }
        }
    </script>
</body>
</html>

