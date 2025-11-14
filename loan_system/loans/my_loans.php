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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loan History - Loan System</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
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

        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 15px;
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

        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-group select:focus {
            border-color: #210a1a;
            outline: none;
        }

        .loans-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
            cursor: pointer;
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

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .section-header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .section-header h2 {
            color: #210a1a;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .section-header p {
            color: #666;
            font-size: 14px;
        }

        .tabs-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            color: #666;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
        }

        .tab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        .loan-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }

        .btn-view {
            flex: 1;
            padding: 10px;
            background: linear-gradient(135deg, #210a1a 0%, #41081a 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 10, 26, 0.4);
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

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 768px) {
            .loans-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 My Loan History</h1>
            <a href="../home.php" class="btn btn-secondary">← Back to Home</a>
        </div>

        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchTab('active')">Active Loans</button>
            <button class="tab-btn" onclick="switchTab('completed')">Completed Loans</button>
        </div>

        <!-- Active Loans Tab -->
        <div id="activeLoansTab" class="tab-content active">
            <div class="filters">
                <div class="filter-group">
                    <label>Loan Type</label>
                    <select id="loanTypeFilter">
                        <option value="all">All Loans</option>
                        <option value="e_loan">E Loans</option>
                        <option value="atm_loan">ATM Loans</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="disapproved">Disapproved</option>
                        <option value="loan_granted">Loan Granted</option>
                    </select>
                </div>
            </div>

            <div class="loans-container">
                <div id="loansContainer" class="loading">
                    Loading loans...
                </div>
            </div>
        </div>

        <!-- Completed Loans Tab -->
        <div id="completedLoansTab" class="tab-content">
            <div class="filters">
                <div class="filter-group">
                    <label>Loan Type</label>
                    <select id="completedLoanTypeFilter">
                        <option value="all">All Loans</option>
                        <option value="e_loan">E Loans</option>
                        <option value="atm_loan">ATM Loans</option>
                    </select>
                </div>
            </div>

            <div class="loans-container">
                <div id="completedLoansContainer" class="loading">
                    Loading completed loans...
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTab = 'active';

        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            if (tab === 'active') {
                document.getElementById('activeLoansTab').classList.add('active');
                loadLoans();
            } else {
                document.getElementById('completedLoansTab').classList.add('active');
                loadCompletedLoans();
            }
        }

        // Load active loans
        function loadLoans() {
            const loanType = document.getElementById('loanTypeFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            document.getElementById('loansContainer').innerHTML = '<div class="loading">Loading loans...</div>';
            
            fetch(`api/get_my_loans.php?type=${loanType}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLoans(data.loans, 'loansContainer');
                    } else {
                        document.getElementById('loansContainer').innerHTML = 
                            '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Failed to load loans</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loansContainer').innerHTML = 
                        '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading loans</p></div>';
                });
        }

        // Load completed loans
        function loadCompletedLoans() {
            const loanType = document.getElementById('completedLoanTypeFilter').value;
            
            document.getElementById('completedLoansContainer').innerHTML = '<div class="loading">Loading completed loans...</div>';
            
            fetch(`api/get_completed_loans.php?type=${loanType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCompletedLoans(data.loans);
                    } else {
                        document.getElementById('completedLoansContainer').innerHTML = 
                            '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Failed to load completed loans</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('completedLoansContainer').innerHTML = 
                        '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading completed loans</p></div>';
                });
        }

        // Display active loans
        function displayLoans(loans, containerId) {
            const container = document.getElementById(containerId);
            
            if (loans.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📭</div><p>No loan history found</p></div>';
                return;
            }
            
            container.innerHTML = '<div class="loans-grid"></div>';
            const grid = container.querySelector('.loans-grid');
            
            loans.forEach(loan => {
                const card = document.createElement('div');
                card.className = 'loan-card';
                
                const statusClass = `status-${loan.status}`;
                const loanTypeClass = loan.loan_type === 'e_loan' ? 'e-loan' : 'atm-loan';
                
                card.innerHTML = `
                    <div class="loan-header">
                        <span class="loan-type-badge ${loanTypeClass}">${loan.loan_type === 'e_loan' ? 'E Loan' : 'ATM Loan'}</span>
                        <span class="status-badge ${statusClass}">${loan.status.toUpperCase().replace('_', ' ')}</span>
                    </div>
                    <div class="loan-amount">₱${parseFloat(loan.loan_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    <div class="loan-info">
                        <div class="loan-info-item">
                            <strong>Application ID:</strong>
                            <span>#${loan.id}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Submitted:</strong>
                            <span>${new Date(loan.created_at).toLocaleDateString()}</span>
                        </div>
                        ${loan.reviewed_at ? `
                        <div class="loan-info-item">
                            <strong>Reviewed:</strong>
                            <span>${new Date(loan.reviewed_at).toLocaleDateString()}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="loan-actions">
                        <a href="application_status.php?loan_id=${loan.id}&loan_type=${loan.loan_type}" class="btn-view">View Details</a>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }

        // Display completed loans
        function displayCompletedLoans(loans) {
            const container = document.getElementById('completedLoansContainer');
            
            if (loans.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">✅</div><p>No completed loans found</p></div>';
                return;
            }
            
            container.innerHTML = '<div class="loans-grid"></div>';
            const grid = container.querySelector('.loans-grid');
            
            loans.forEach(loan => {
                const card = document.createElement('div');
                card.className = 'loan-card';
                
                const loanTypeClass = loan.loan_type === 'e_loan' ? 'e-loan' : 'atm-loan';
                
                card.innerHTML = `
                    <div class="loan-header">
                        <span class="loan-type-badge ${loanTypeClass}">${loan.loan_type === 'e_loan' ? 'E Loan' : 'ATM Loan'}</span>
                        <span class="status-badge status-completed">COMPLETED</span>
                    </div>
                    <div class="loan-amount">₱${parseFloat(loan.loan_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    <div class="loan-info">
                        <div class="loan-info-item">
                            <strong>Loan ID:</strong>
                            <span>#${loan.loan_id}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Started:</strong>
                            <span>${new Date(loan.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Completed:</strong>
                            <span>${new Date(loan.completed_at).toLocaleDateString()}</span>
                        </div>
                        ${loan.reviewed_at ? `
                        <div class="loan-info-item">
                            <strong>Reviewed:</strong>
                            <span>${new Date(loan.reviewed_at).toLocaleDateString()}</span>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }

        // Event listeners
        document.getElementById('loanTypeFilter').addEventListener('change', loadLoans);
        document.getElementById('statusFilter').addEventListener('change', loadLoans);
        document.getElementById('completedLoanTypeFilter').addEventListener('change', loadCompletedLoans);

        // Load loans on page load
        loadLoans();
    </script>
</body>
</html>
