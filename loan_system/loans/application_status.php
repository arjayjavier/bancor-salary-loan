<?php
session_start();
require_once '../config/database.php';

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

// Redirect to login if not authenticated
if (!$isLoggedIn) {
    header('Location: ../index.php');
    exit;
}

$pdo = getDBConnection();
$loan = null;
$loanType = null;

// Get loan_id and loan_type from URL parameters
$loanId = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : null;
$loanTypeParam = isset($_GET['loan_type']) ? $_GET['loan_type'] : null;

// If no parameters, get the latest application
if (!$loanId || !$loanTypeParam) {
    // Get latest E loan
    $stmt = $pdo->prepare("
        SELECT 
            el.id,
            'e_loan' as loan_type,
            el.loan_amount,
            el.status,
            el.admin_notes,
            el.created_at,
            el.reviewed_at
        FROM e_loans el
        WHERE el.user_id = ?
        ORDER BY el.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $eLoan = $stmt->fetch();
    
    // Get latest ATM loan
    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            'atm_loan' as loan_type,
            al.loan_amount,
            al.status,
            al.admin_notes,
            al.created_at,
            al.reviewed_at
        FROM atm_loans al
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $atmLoan = $stmt->fetch();
    
    // Determine which is more recent
    if ($eLoan && $atmLoan) {
        $loan = strtotime($eLoan['created_at']) > strtotime($atmLoan['created_at']) ? $eLoan : $atmLoan;
    } elseif ($eLoan) {
        $loan = $eLoan;
    } elseif ($atmLoan) {
        $loan = $atmLoan;
    }
} else {
    // Get specific loan
    if ($loanTypeParam === 'e_loan') {
        $stmt = $pdo->prepare("
            SELECT 
                el.id,
                'e_loan' as loan_type,
                el.loan_amount,
                el.status,
                el.admin_notes,
                el.created_at,
                el.reviewed_at
            FROM e_loans el
            WHERE el.id = ? AND el.user_id = ?
        ");
        $stmt->execute([$loanId, $user['id']]);
        $loan = $stmt->fetch();
    } elseif ($loanTypeParam === 'atm_loan') {
        $stmt = $pdo->prepare("
            SELECT 
                al.id,
                'atm_loan' as loan_type,
                al.loan_amount,
                al.status,
                al.admin_notes,
                al.created_at,
                al.reviewed_at
            FROM atm_loans al
            WHERE al.id = ? AND al.user_id = ?
        ");
        $stmt->execute([$loanId, $user['id']]);
        $loan = $stmt->fetch();
    }
}

$status = $loan ? $loan['status'] : null;
$adminNotes = $loan ? $loan['admin_notes'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - Loan System</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 700px;
            width: 100%;
        }

        .status-card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
        }

        .status-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: bounce 1s ease-in-out;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .status-title {
            color: #210a1a;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .status-message {
            color: #666;
            font-size: 18px;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .status-pending {
            color: #856404;
        }

        .status-approved {
            color: #155724;
        }

        .status-disapproved {
            color: #721c24;
        }

        .status-loan-granted {
            color: #28a745;
        }

        .admin-notes-section {
            background: #f8f9fa;
            border-left: 4px solid #210a1a;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .admin-notes-section h3 {
            color: #210a1a;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .admin-notes-section p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .no-notes {
            color: #999;
            font-style: italic;
        }

        .loan-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .loan-info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .loan-info-item:last-child {
            border-bottom: none;
        }

        .loan-info-item strong {
            color: #333;
            font-size: 14px;
        }

        .loan-info-item span {
            color: #666;
            font-size: 14px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-top: 30px;
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

        .status-top-actions {
            position: absolute;
            top: 20px;
            left: 20px;
        }

        .btn-top-left {
            background: #f5f5f5;
            color: #666;
            padding: 10px 20px;
        }

        .btn-top-left:hover {
            background: #e0e0e0;
            color: #333;
        }

        .no-application {
            text-align: center;
        }

        .no-application-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-application h2 {
            color: #666;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .no-application p {
            color: #999;
            font-size: 16px;
        }

        .contact-section {
            background: #e7f3ff;
            border-left: 4px solid #210a1a;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .contact-section p {
            color: #333;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .btn-contact {
            background: linear-gradient(135deg, #1877f2 0%, #0d5fcc 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(24, 119, 242, 0.3);
        }

        .btn-contact:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(24, 119, 242, 0.4);
        }

        @media (max-width: 768px) {
            .status-card {
                padding: 30px 20px;
            }

            .status-icon {
                font-size: 60px;
            }

            .status-title {
                font-size: 24px;
            }

            .status-message {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-card">
            <?php if (!$loan): ?>
                <div class="no-application">
                    <div class="no-application-icon">📋</div>
                    <h2>No Application Found</h2>
                    <p>You haven't submitted any loan applications yet.</p>
                    <a href="../home.php" class="btn btn-primary">Go to Home</a>
                </div>
            <?php else: ?>
                <?php if ($status === 'pending'): ?>
                    <div class="status-icon">⏳</div>
                    <h1 class="status-title status-pending">Application Under Review</h1>
                    <p class="status-message">
                        We would like to inform you that your submitted documents are currently under review. Our team will complete the review within 24 hours.
                    </p>
                    
                    <?php if ($adminNotes): ?>
                        <div class="admin-notes-section">
                            <h3>📝 Admin Notes:</h3>
                            <p><?php echo htmlspecialchars($adminNotes); ?></p>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($status === 'approved'): ?>
                    <?php if ($loan['loan_type'] === 'atm_loan'): ?>
                        <div class="status-top-actions">
                            <a href="../home.php" class="btn btn-top-left">← Home</a>
                        </div>
                        <div class="status-icon">🎉</div>
                        <h1 class="status-title status-approved">Congratulations!</h1>
                        <p class="status-message">
                            We are thrilled to inform you that you have advanced to the next stage.
                        </p>
                        
                        <?php if ($adminNotes): ?>
                            <div class="admin-notes-section">
                                <h3>📝 Admin Notes:</h3>
                                <p><?php echo htmlspecialchars($adminNotes); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="status-icon">✅</div>
                        <h1 class="status-title status-approved">Congratulations!</h1>
                        <p class="status-message">
                            Your application has been approved.
                        </p>
                        
                        <?php if ($adminNotes): ?>
                            <div class="admin-notes-section">
                                <h3>📝 Admin Notes:</h3>
                                <p><?php echo htmlspecialchars($adminNotes); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="contact-section">
                            <p>Please reach out to us for you to be able to get your loanable amount</p>
                            <a href="https://www.facebook.com/share/1A8X3872FX/" target="_blank" class="btn-contact">Click here</a>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($status === 'loan_granted'): ?>
                    <div class="status-icon">🎉</div>
                    <h1 class="status-title status-loan-granted">Loan Granted</h1>
                    <p class="status-message">
                        Your loan has been granted. You can now proceed with your loan transaction.
                    </p>
                    
                    <?php if ($adminNotes): ?>
                        <div class="admin-notes-section">
                            <h3>📝 Admin Notes:</h3>
                            <p><?php echo htmlspecialchars($adminNotes); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="admin-notes-section">
                            <h3>📝 Admin Notes:</h3>
                            <p class="no-notes">No notes available.</p>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($status === 'disapproved'): ?>
                    <div class="status-icon">❌</div>
                    <h1 class="status-title status-disapproved">Application Disapproved</h1>
                    <p class="status-message">
                        Sorry, your application is disapproved.
                    </p>
                    
                    <?php if ($adminNotes): ?>
                        <div class="admin-notes-section">
                            <h3>📝 Admin Notes:</h3>
                            <p><?php echo htmlspecialchars($adminNotes); ?></p>
                        </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
                
                <div class="loan-info">
                    <div class="loan-info-item">
                        <strong>Application ID:</strong>
                        <span>#<?php echo $loan['id']; ?></span>
                    </div>
                    <div class="loan-info-item">
                        <strong>Loan Type:</strong>
                        <span><?php echo $loan['loan_type'] === 'e_loan' ? 'E Loan' : 'ATM Loan'; ?></span>
                    </div>
                    <div class="loan-info-item">
                        <strong>Loan Amount:</strong>
                        <span>₱<?php echo number_format($loan['loan_amount'], 2); ?></span>
                    </div>
                    <div class="loan-info-item">
                        <strong>Status:</strong>
                        <span style="text-transform: uppercase; font-weight: 600;"><?php echo $loan['status']; ?></span>
                    </div>
                    <div class="loan-info-item">
                        <strong>Submitted:</strong>
                        <span><?php echo date('F d, Y h:i A', strtotime($loan['created_at'])); ?></span>
                    </div>
                    <?php if ($loan['reviewed_at']): ?>
                    <div class="loan-info-item">
                        <strong>Reviewed:</strong>
                        <span><?php echo date('F d, Y h:i A', strtotime($loan['reviewed_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($loan['loan_type'] === 'atm_loan' && $status === 'approved'): ?>
                    <a href="atm_loan_next_step.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-primary">Next</a>
                <?php else: ?>
                    <a href="../home.php" class="btn btn-primary">Back to Home</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($loan && $status === 'pending'): ?>
    <script>
        // Auto-refresh every 30 seconds if status is pending
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
    <?php endif; ?>

</body>
</html>

