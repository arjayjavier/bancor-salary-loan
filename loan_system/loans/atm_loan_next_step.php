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

$pdo = isset($pdo) ? $pdo : getDBConnection();
$loanId = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : null;
$atmLoan = null;

if ($pdo !== null) {
    if ($loanId) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM atm_loans
            WHERE id = ? AND user_id = ? AND status IN ('approved', 'loan_granted')
            LIMIT 1
        ");
        $stmt->execute([$loanId, $user['id']]);
        $atmLoan = $stmt->fetch();
    }

    if (!$atmLoan) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM atm_loans
            WHERE user_id = ? AND status IN ('approved', 'loan_granted')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $atmLoan = $stmt->fetch();
    }
}

if (!$atmLoan) {
    header('Location: application_status.php');
    exit;
}

$officeMapUrl = 'https://maps.app.goo.gl/nAR8WycXWEDKY7CN7';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Loan Next Steps - Loan System</title>
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
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .card-header h1 {
            font-size: 28px;
            color: #210a1a;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            color: #333;
        }

        .btn-primary {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(33, 10, 26, 0.4);
        }

        .intro-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            line-height: 1.7;
            font-size: 18px;
            color: #444;
        }

        .location-section {
            text-align: center;
            margin-bottom: 35px;
        }

        .location-section p {
            font-size: 16px;
            color: #555;
            margin-bottom: 15px;
        }

        .requirements-section {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 12px;
            padding: 25px 20px;
        }

        .requirements-section h2 {
            font-size: 20px;
            color: #856404;
            margin-bottom: 15px;
        }

        .requirements-section ul {
            list-style: none;
            padding-left: 0;
        }

        .requirements-section li {
            font-size: 16px;
            color: #856404;
            padding: 8px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .requirements-section li span {
            font-weight: 600;
            color: #715c02;
        }

        .requirements-section li::before {
            content: "•";
            font-size: 20px;
            line-height: 1;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .card {
                padding: 30px 20px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>Next Steps for Your ATM Loan</h1>
                <a href="../home.php" class="btn btn-secondary">← Back to Home</a>
            </div>

            <div class="intro-section">
                To continue your application, please visit our office. You can click the button below to view our house office location.
            </div>

            <div class="location-section">
                <p>Click the button to open our office location in Google Maps.</p>
                <a href="<?php echo htmlspecialchars($officeMapUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                    View Office Location
                </a>
            </div>

            <div class="intro-section" style="margin-bottom: 30px;">
                To submit the hard copies of your required documents, including your ATM card and SIM card for your online account, click the button above to view our office location.
            </div>

            <div class="requirements-section">
                <h2>Items to Bring:</h2>
                <ul>
                    <li><span>2 Valid IDs</span> (Printed copy)</li>
                    <li><span>Payslip</span></li>
                    <li><span>Payroll ATM</span></li>
                    <li><span>SIM card</span> where the online account of your payroll is registered</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>

