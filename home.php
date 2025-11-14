<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
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

// Check if user has any existing loans
$hasExistingLoan = false;
$creditScore = null;
$creditStatus = null;
$overduePayment = null;
$isBadPayer = false;
if ($isLoggedIn && $user) {
    $pdo = getDBConnection();
    if ($pdo !== null) {
        // Check for any existing loans (e_loan or atm_loan)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM (
                SELECT id FROM e_loans WHERE user_id = ?
                UNION ALL
                SELECT id FROM atm_loans WHERE user_id = ?
            ) as loans
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $result = $stmt->fetch();
        $hasExistingLoan = $result && $result['count'] > 0;
        
        // Get user's credit score override (check if column exists first)
        $creditScoreOverride = null;
        try {
            $stmt = $pdo->prepare("SELECT credit_score_override FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            $creditScoreOverride = $userData && isset($userData['credit_score_override']) ? $userData['credit_score_override'] : null;
        } catch (PDOException $e) {
            // Column doesn't exist yet, use null (auto-calculated)
            $creditScoreOverride = null;
        }
        
        // Calculate credit score
        // Get all loans with their statuses
        $stmt = $pdo->prepare("
            SELECT status FROM (
                SELECT status FROM e_loans WHERE user_id = ?
                UNION ALL
                SELECT status FROM atm_loans WHERE user_id = ?
            ) as all_loans
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $allLoans = $stmt->fetchAll();
        
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
            
            // Calculate credit score (0-100)
            // Base score: 50
            // +10 for each approved loan
            // +20 for each granted loan
            // -15 for each disapproved loan
            // +5 for pending (neutral)
            $autoCreditScore = 50;
            $autoCreditScore += ($approvedCount * 10);
            $autoCreditScore += ($grantedCount * 20);
            $autoCreditScore -= ($disapprovedCount * 15);
            $autoCreditScore += ($pendingCount * 5);
            
            // Clamp score between 0 and 100
            $autoCreditScore = max(0, min(100, $autoCreditScore));
            
            // Determine auto status
            if ($autoCreditScore >= 70) {
                $autoCreditStatus = 'good';
            } else {
                $autoCreditStatus = 'bad';
            }
        } else {
            // No loan history - neutral
            $autoCreditScore = 50;
            $autoCreditStatus = 'neutral';
        }
        
        // Use override if set, otherwise use auto-calculated
        if ($creditScoreOverride) {
            $creditStatus = $creditScoreOverride;
            $creditScore = $creditScoreOverride === 'good' ? 85 : 35;
        } else {
            $creditStatus = $autoCreditStatus;
            $creditScore = $autoCreditScore;
        }
        
        // Check if user is BAD payer
        $isBadPayer = ($creditStatus === 'bad');
        
        // Check for overdue payments (only if BAD payer)
        if ($isBadPayer) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, days_overdue, amount_to_pay, qr_code_1_path, qr_code_2_path, due_date, loan_id, loan_type
                    FROM overdue_payments
                    WHERE user_id = ? AND is_settled = FALSE
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $overduePayment = $stmt->fetch();
            } catch (PDOException $e) {
                // Table doesn't exist yet, will be created by migration
                $overduePayment = null;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Loan System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #210a1a;
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .user-info span {
            color: #666;
            font-size: 14px;
        }

        .user-info .user-name {
            color: #210a1a;
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
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 10, 26, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-section {
            background: white;
            border-radius: 20px;
            padding: 50px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-section h2 {
            color: #333;
            font-size: 36px;
            margin-bottom: 15px;
        }

        .welcome-section p {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
        }

        .credit-score-section {
            margin-bottom: 30px;
        }

        .credit-score-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .credit-score-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .credit-score-header h3 {
            color: #333;
            font-size: 24px;
        }

        .credit-score-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .credit-score-badge.credit-score-good {
            background: #d4edda;
            color: #155724;
        }

        .credit-score-badge.credit-score-bad {
            background: #f8d7da;
            color: #721c24;
        }

        .credit-score-badge.credit-score-neutral {
            background: #e2e3e5;
            color: #383d41;
        }

        .credit-score-value {
            text-align: center;
            margin: 20px 0;
        }

        .score-number {
            font-size: 48px;
            font-weight: 700;
            color: #210a1a;
        }

        .score-max {
            font-size: 24px;
            color: #666;
            margin-left: 5px;
        }

        .credit-score-bar {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .credit-score-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .credit-score-fill.credit-score-good {
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        }

        .credit-score-fill.credit-score-bad {
            background: linear-gradient(90deg, #dc3545 0%, #fd7e14 100%);
        }

        .credit-score-fill.credit-score-neutral {
            background: linear-gradient(90deg, #6c757d 0%, #adb5bd 100%);
        }

        .credit-score-note {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 15px;
            font-style: italic;
        }

        .loan-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .loan-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }

        .loan-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .loan-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #210a1a 0%, #41081a 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .loan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .loan-card:hover::before {
            transform: scaleX(1);
        }

        .loan-icon {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }

        .loan-card h3 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .loan-card p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .loan-btn {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 10, 26, 0.3);
            width: 100%;
        }

        .loan-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 10, 26, 0.4);
        }

        .loan-btn:active {
            transform: translateY(0);
        }

        .loan-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        .loan-card.disabled:hover {
            transform: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .loan-card.disabled .loan-btn {
            background: #ccc;
            cursor: not-allowed;
        }

        .overdue-notification {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(220, 53, 69, 0.3);
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .overdue-notification-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .overdue-notification-header h3 {
            font-size: 24px;
            margin: 0;
        }

        .overdue-notification-icon {
            font-size: 32px;
        }

        .overdue-notification-content {
            margin-bottom: 20px;
        }

        .overdue-notification-content p {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 10px;
        }

        .overdue-amount {
            font-size: 32px;
            font-weight: 700;
            margin: 15px 0;
            text-align: center;
        }

        .overdue-days {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            margin: 10px 0;
        }

        .qr-codes-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .qr-code-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
        }

        .qr-code-item h4 {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .qr-code-item img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            display: block;
            margin: 0 auto;
            background: white;
            padding: 10px;
        }

        .qr-code-image {
            min-width: 200px;
            min-height: 200px;
            max-width: 300px;
            max-height: 300px;
            object-fit: contain;
        }

        .features {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .features h3 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .feature-item {
            text-align: center;
            padding: 20px;
        }

        .feature-item .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .feature-item h4 {
            color: #210a1a;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .feature-item p {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 30px 20px;
            }

            .welcome-section h2 {
                font-size: 28px;
            }

            .loan-buttons {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .loan-card {
                padding: 30px 20px;
            }

            .header {
                flex-direction: column;
                text-align: center;
            }

            .overdue-notification {
                padding: 20px;
            }

            .overdue-notification-header h3 {
                font-size: 20px;
            }

            .overdue-amount {
                font-size: 24px;
            }

            .qr-codes-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏦 Loan System</h1>
            <div class="user-info">
                <?php if ($isLoggedIn): ?>
                    <span>Welcome, <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span></span>
                    <span class="badge" style="background: #210a1a; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px;">
                        <?php echo strtoupper($user['role']); ?>
                    </span>
                    <a href="loans/my_loans.php" class="btn btn-primary">📋 My Loan History</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin/dashboard.php" class="btn btn-primary">Admin Dashboard</a>
                    <?php endif; ?>
                    <a href="api/logout.php" class="btn btn-secondary" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-primary">Login / Register</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="welcome-section">
            <h2>Welcome to Our Loan System</h2>
            <p>Choose the type of loan that best fits your needs. We offer flexible options with competitive rates.</p>
        </div>

        <?php if ($isLoggedIn && $creditScore !== null): ?>
        <div class="credit-score-section">
            <div class="credit-score-card">
                <div class="credit-score-header">
                    <h3>💳 Credit Score</h3>
                    <span class="credit-score-badge credit-score-<?php echo $creditStatus; ?>">
                        <?php 
                        if ($creditStatus === 'good') {
                            echo 'Good Credit Score';
                        } elseif ($creditStatus === 'bad') {
                            echo 'Bad Credit Score';
                        } else {
                            echo 'No History';
                        }
                        ?>
                    </span>
                </div>
                <div class="credit-score-value">
                    <span class="score-number"><?php echo $creditScore; ?></span>
                    <span class="score-max">/ 100</span>
                </div>
                <div class="credit-score-bar">
                    <div class="credit-score-fill credit-score-<?php echo $creditStatus; ?>" style="width: <?php echo $creditScore; ?>%"></div>
                </div>
                <p class="credit-score-note">
                    <?php 
                    if ($creditStatus === 'good') {
                        echo 'Great! You have a good credit history.';
                    } elseif ($creditStatus === 'bad') {
                        echo 'Your credit score needs improvement.';
                    } else {
                        echo 'Start building your credit history by applying for a loan.';
                    }
                    ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && $isBadPayer && $overduePayment): ?>
        <div class="overdue-notification">
            <div class="overdue-notification-header">
                <span class="overdue-notification-icon">⚠️</span>
                <h3>Payment Settlement Required</h3>
            </div>
            <div class="overdue-notification-content">
                <p><strong>You need to settle your outstanding loan amount to continue using our loan services.</strong></p>
                <p>Please settle your payment as soon as possible to avoid further penalties.</p>
                <div class="overdue-amount">
                    Amount to Pay: ₱<?php echo number_format($overduePayment['amount_to_pay'], 2); ?>
                </div>
                <?php if ($overduePayment['days_overdue'] > 0): ?>
                <div class="overdue-days">
                    Days Overdue: <?php echo $overduePayment['days_overdue']; ?> day(s)
                </div>
                <?php endif; ?>
                <?php if ($overduePayment['qr_code_1_path'] || $overduePayment['qr_code_2_path']): ?>
                <div class="qr-codes-container">
                    <?php if ($overduePayment['qr_code_1_path']): ?>
                    <div class="qr-code-item">
                        <h4>Payment QR Code 1</h4>
                        <img src="<?php echo htmlspecialchars($overduePayment['qr_code_1_path']); ?>" alt="QR Code 1" class="qr-code-image" onerror="console.error('Failed to load QR Code 1: ' + this.src); this.style.display='none';">
                    </div>
                    <?php endif; ?>
                    <?php if ($overduePayment['qr_code_2_path']): ?>
                    <div class="qr-code-item">
                        <h4>Payment QR Code 2</h4>
                        <img src="<?php echo htmlspecialchars($overduePayment['qr_code_2_path']); ?>" alt="QR Code 2" class="qr-code-image" onerror="console.error('Failed to load QR Code 2: ' + this.src); this.style.display='none';">
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="loan-buttons">
            <!-- ATM Loan Card -->
            <div class="loan-card <?php echo $isBadPayer ? 'disabled' : ''; ?>" <?php if (!$isBadPayer): ?>onclick="window.location.href='loans/atm-loan.php'"<?php endif; ?>>
                <span class="loan-icon">🏧</span>
                <h3>ATM Loan</h3>
                <p>Quick and convenient loans accessible through ATM machines. Get instant cash when you need it most.</p>
                <?php if ($isBadPayer): ?>
                <button class="loan-btn" disabled>
                    Apply for ATM Loan (Disabled)
                </button>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">⚠️ Please settle your outstanding payment to apply for new loans.</p>
                <?php else: ?>
                <button class="loan-btn" onclick="event.stopPropagation(); window.location.href='loans/atm-loan.php'">
                    Apply for ATM Loan
                </button>
                <?php endif; ?>
            </div>

            <!-- E Loan Card -->
            <div class="loan-card <?php echo $isBadPayer ? 'disabled' : ''; ?>" <?php if (!$isBadPayer): ?>onclick="window.location.href='loans/e-loan.php'"<?php endif; ?>>
                <span class="loan-icon">💻</span>
                <h3>E Loan</h3>
                <p>Digital loan application process. Apply online from anywhere, anytime. Fast approval and easy management.</p>
                <?php if ($isBadPayer): ?>
                <button class="loan-btn" disabled>
                    Apply for E Loan (Disabled)
                </button>
                <p style="color: #dc3545; font-size: 14px; margin-top: 10px;">⚠️ Please settle your outstanding payment to apply for new loans.</p>
                <?php else: ?>
                <button class="loan-btn" onclick="event.stopPropagation(); window.location.href='loans/e-loan.php'">
                    Apply for E Loan
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="features">
            <h3>Why Choose Us?</h3>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="icon">⚡</div>
                    <h4>Fast Processing</h4>
                    <p>Quick approval and disbursement</p>
                </div>
                <div class="feature-item">
                    <div class="icon">🔒</div>
                    <h4>Secure & Safe</h4>
                    <p>Your data is protected</p>
                </div>
                <div class="feature-item">
                    <div class="icon">💰</div>
                    <h4>Competitive Rates</h4>
                    <p>Best interest rates in market</p>
                </div>
                <div class="feature-item">
                    <div class="icon">📱</div>
                    <h4>Easy Management</h4>
                    <p>Track and manage online</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

