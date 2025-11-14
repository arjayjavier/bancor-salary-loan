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

// Get user_id from URL
$viewUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$viewUserId) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();

// Get user information
try {
    $stmt = $pdo->prepare("SELECT id, name, email, role, status, credit_score_override FROM users WHERE id = ?");
    $stmt->execute([$viewUserId]);
    $viewUser = $stmt->fetch();
} catch (PDOException $e) {
    // If column doesn't exist, get without credit_score_override
    $stmt = $pdo->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
    $stmt->execute([$viewUserId]);
    $viewUser = $stmt->fetch();
    if ($viewUser) {
        $viewUser['credit_score_override'] = null;
    }
}

if (!$viewUser) {
    header('Location: dashboard.php');
    exit;
}

// Get all loans for this user
$stmt = $pdo->prepare("
    SELECT 
        el.id,
        'e_loan' as loan_type,
        el.loan_amount,
        el.company_id_type,
        el.government_id_type,
        el.contact_number,
        el.address,
        el.loan_purpose,
        el.status,
        el.admin_notes,
        el.created_at,
        el.reviewed_at,
        reviewer.name as reviewed_by_name
    FROM e_loans el
    LEFT JOIN users reviewer ON el.reviewed_by = reviewer.id
    WHERE el.user_id = ?
    ORDER BY el.created_at DESC
");
$stmt->execute([$viewUserId]);
$eLoans = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        al.id,
        'atm_loan' as loan_type,
        al.loan_amount,
        al.company_id_type,
        al.government_id_type,
        al.contact_number,
        al.address,
        al.loan_purpose,
        al.status,
        al.admin_notes,
        al.created_at,
        al.reviewed_at,
        reviewer.name as reviewed_by_name
    FROM atm_loans al
    LEFT JOIN users reviewer ON al.reviewed_by = reviewer.id
    WHERE al.user_id = ?
    ORDER BY al.created_at DESC
");
$stmt->execute([$viewUserId]);
$atmLoans = $stmt->fetchAll();

$allLoans = array_merge($eLoans, $atmLoans);
usort($allLoans, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate automatic credit score
$autoCreditScore = 50;
$autoCreditStatus = 'neutral';
if (count($allLoans) > 0) {
    $approvedCount = 0;
    $grantedCount = 0;
    $disapprovedCount = 0;
    $pendingCount = 0;
    
    foreach ($allLoans as $loan) {
        switch ($loan['status']) {
            case 'approved':
                $approvedCount++;
                break;
            case 'loan_granted':
                $grantedCount++;
                break;
            case 'disapproved':
                $disapprovedCount++;
                break;
            case 'pending':
                $pendingCount++;
                break;
        }
    }
    
    $autoCreditScore = 50;
    $autoCreditScore += ($approvedCount * 10);
    $autoCreditScore += ($grantedCount * 20);
    $autoCreditScore -= ($disapprovedCount * 15);
    $autoCreditScore += ($pendingCount * 5);
    $autoCreditScore = max(0, min(100, $autoCreditScore));
    
    if ($autoCreditScore >= 70) {
        $autoCreditStatus = 'good';
    } else {
        $autoCreditStatus = 'bad';
    }
}

// Use override if set, otherwise use auto
$currentCreditStatus = $viewUser['credit_score_override'] ? $viewUser['credit_score_override'] : $autoCreditStatus;
$currentCreditScore = $viewUser['credit_score_override'] ? ($viewUser['credit_score_override'] === 'good' ? 85 : 35) : $autoCreditScore;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User History - Admin</title>
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

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .user-info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .user-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .user-info-header h2 {
            color: #333;
            font-size: 24px;
        }

        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .user-detail-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .user-detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .credit-score-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }

        .credit-score-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .credit-score-header h3 {
            color: #333;
            font-size: 20px;
        }

        .credit-score-display {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .credit-score-value {
            text-align: center;
        }

        .score-number {
            font-size: 48px;
            font-weight: 700;
            color: #210a1a;
        }

        .score-max {
            font-size: 20px;
            color: #666;
        }

        .credit-score-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .credit-score-badge.good {
            background: #d4edda;
            color: #155724;
        }

        .credit-score-badge.bad {
            background: #f8d7da;
            color: #721c24;
        }

        .credit-score-badge.neutral {
            background: #e2e3e5;
            color: #383d41;
        }

        .credit-score-controls {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .btn-credit-good {
            background: #28a745;
            color: white;
        }

        .btn-credit-good:hover {
            background: #218838;
        }

        .btn-credit-bad {
            background: #dc3545;
            color: white;
        }

        .btn-credit-bad:hover {
            background: #c82333;
        }

        .btn-credit-reset {
            background: #6c757d;
            color: white;
        }

        .btn-credit-reset:hover {
            background: #5a6268;
        }

        .override-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
            color: #856404;
        }

        .loans-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .loans-section h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .loans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .loan-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .loan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            border-color: #210a1a;
        }

        .loan-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .loan-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .loan-type-badge.e-loan {
            background: #e7f3ff;
            color: #210a1a;
        }

        .loan-type-badge.atm-loan {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-disapproved {
            background: #f8d7da;
            color: #721c24;
        }

        .status-loan-granted {
            background: #d1ecf1;
            color: #0c5460;
        }

        .loan-amount {
            font-size: 24px;
            font-weight: 700;
            color: #210a1a;
            margin: 15px 0;
        }

        .loan-info {
            margin-bottom: 15px;
        }

        .loan-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .loan-info-item strong {
            color: #333;
        }

        .loan-info-item span {
            color: #666;
            text-align: right;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .loans-grid {
                grid-template-columns: 1fr;
            }

            .user-details {
                grid-template-columns: 1fr;
            }

            .credit-score-display {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👤 User History</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="user-info-card">
            <div class="user-info-header">
                <h2><?php echo htmlspecialchars($viewUser['name']); ?></h2>
            </div>
            
            <div class="user-details">
                <div class="user-detail-item">
                    <span class="user-detail-label">Email</span>
                    <span class="user-detail-value"><?php echo htmlspecialchars($viewUser['email']); ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">User ID</span>
                    <span class="user-detail-value">#<?php echo $viewUser['id']; ?></span>
                </div>
                <div class="user-detail-item">
                    <span class="user-detail-label">Total Loans</span>
                    <span class="user-detail-value"><?php echo count($allLoans); ?></span>
                </div>
            </div>

            <div class="credit-score-section">
                <div class="credit-score-header">
                    <h3>💳 Credit Score</h3>
                    <span class="credit-score-badge <?php echo $currentCreditStatus; ?>">
                        <?php 
                        if ($currentCreditStatus === 'good') {
                            echo 'Good Credit Score';
                        } elseif ($currentCreditStatus === 'bad') {
                            echo 'Bad Credit Score';
                        } else {
                            echo 'No History';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="credit-score-display">
                    <div class="credit-score-value">
                        <div class="score-number"><?php echo $currentCreditScore; ?></div>
                        <div class="score-max">/ 100</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 14px; color: #666; margin-bottom: 10px;">
                            <strong>Auto-calculated:</strong> <?php echo $autoCreditScore; ?> 
                            (<?php echo $autoCreditStatus === 'good' ? 'Good' : ($autoCreditStatus === 'bad' ? 'Bad' : 'Neutral'); ?>)
                        </div>
                        <?php if ($viewUser['credit_score_override']): ?>
                        <div class="override-note">
                            ⚠️ <strong>Manual Override Active:</strong> Credit score is manually set to 
                            <strong><?php echo $viewUser['credit_score_override'] === 'good' ? 'Good' : 'Bad'; ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="credit-score-controls">
                    <button class="btn btn-credit-good" onclick="setCreditScore(<?php echo $viewUserId; ?>, 'good')">
                        Set as Good Credit
                    </button>
                    <button class="btn btn-credit-bad" onclick="setCreditScore(<?php echo $viewUserId; ?>, 'bad')">
                        Set as Bad Credit
                    </button>
                    <?php if ($viewUser['credit_score_override']): ?>
                    <button class="btn btn-credit-reset" onclick="setCreditScore(<?php echo $viewUserId; ?>, null)">
                        Reset to Auto
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="loans-section">
            <h2>Loan History</h2>
            <?php if (empty($allLoans)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No loan history found for this user.</p>
                </div>
            <?php else: ?>
                <div class="loans-grid">
                    <?php foreach ($allLoans as $loan): ?>
                        <?php
                        $statusClass = 'status-' . $loan['status'];
                        $loanTypeClass = $loan['loan_type'] === 'e_loan' ? 'e-loan' : 'atm-loan';
                        ?>
                        <div class="loan-card">
                            <div class="loan-header">
                                <span class="loan-type-badge <?php echo $loanTypeClass; ?>">
                                    <?php echo $loan['loan_type'] === 'e_loan' ? 'E Loan' : 'ATM Loan'; ?>
                                </span>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $loan['status'])); ?>
                                </span>
                            </div>
                            <div class="loan-amount">
                                ₱<?php echo number_format($loan['loan_amount'], 2); ?>
                            </div>
                            <div class="loan-info">
                                <div class="loan-info-item">
                                    <strong>Application ID:</strong>
                                    <span>#<?php echo $loan['id']; ?></span>
                                </div>
                                <div class="loan-info-item">
                                    <strong>Submitted:</strong>
                                    <span><?php echo date('M d, Y', strtotime($loan['created_at'])); ?></span>
                                </div>
                                <?php if ($loan['reviewed_at']): ?>
                                <div class="loan-info-item">
                                    <strong>Reviewed:</strong>
                                    <span><?php echo date('M d, Y', strtotime($loan['reviewed_at'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($loan['reviewed_by_name']): ?>
                                <div class="loan-info-item">
                                    <strong>Reviewed By:</strong>
                                    <span><?php echo htmlspecialchars($loan['reviewed_by_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function setCreditScore(userId, status) {
            let confirmMessage = '';
            if (status === 'good') {
                confirmMessage = 'Are you sure you want to set this user\'s credit score to GOOD?';
            } else if (status === 'bad') {
                confirmMessage = 'Are you sure you want to set this user\'s credit score to BAD?';
            } else {
                confirmMessage = 'Are you sure you want to reset this user\'s credit score to auto-calculated?';
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            fetch('api/update_credit_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    credit_score_override: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Credit score updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update credit score'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }
    </script>
</body>
</html>

