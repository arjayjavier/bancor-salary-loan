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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - Admin</title>
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

        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            border-radius: 8px;
        }

        .tab.active {
            color: #210a1a;
            background: #f0f0f0;
            border-bottom-color: #210a1a;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .loans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .loan-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .loan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .loan-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .loan-type {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .loan-type.e-loan {
            background: #e7f3ff;
            color: #210a1a;
        }

        .loan-type.atm-loan {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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

        .btn-grant {
            background: #28a745;
            color: white;
        }

        .btn-grant:hover {
            background: #218838;
        }

        .btn-renew {
            background: #ffc107;
            color: #000;
        }

        .btn-renew:hover {
            background: #e0a800;
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

        .loan-amount {
            font-size: 24px;
            font-weight: 700;
            color: #210a1a;
            margin: 10px 0;
        }

        .loan-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }

        .btn-action {
            padding: 8px 6px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-disapprove {
            background: #dc3545;
            color: white;
        }

        .btn-disapprove:hover {
            background: #c82333;
        }

        .btn-pending {
            background: #ffc107;
            color: #000;
        }

        .btn-pending:hover {
            background: #e0a800;
        }

        .btn-view {
            background: #210a1a;
            color: white;
        }

        .btn-view:hover {
            background: #41081a;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .details-modal-content {
            max-width: 700px;
        }

        .details-modal-body {
            padding: 0;
        }

        .details-section {
            margin-bottom: 25px;
        }

        .details-section:last-child {
            margin-bottom: 0;
        }

        .details-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #210a1a;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 15px;
            padding: 15px 0;
        }

        .details-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .details-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .details-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .details-value.status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .details-value.status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .details-value.status-approved {
            background: #d4edda;
            color: #155724;
        }

        .details-value.status-disapproved {
            background: #f8d7da;
            color: #721c24;
        }

        .details-value.status-loan-granted {
            background: #d1ecf1;
            color: #0c5460;
        }

        .details-amount {
            font-size: 28px;
            font-weight: 700;
            color: #210a1a;
            margin: 10px 0;
        }

        .details-loan-type {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .details-loan-type.e-loan {
            background: #e7f3ff;
            color: #210a1a;
        }

        .details-loan-type.atm-loan {
            background: #fff3cd;
            color: #856404;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }

        .modal-body textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            min-height: 100px;
            font-family: inherit;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
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

            .loan-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .btn-action {
                font-size: 11px;
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📋 Loan Management</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
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

        <div class="tabs">
            <button class="tab active" onclick="switchTab('all')">All Loans</button>
            <button class="tab" onclick="switchTab('pending')">Pending</button>
            <button class="tab" onclick="switchTab('approved')">Approved</button>
            <button class="tab" onclick="switchTab('disapproved')">Disapproved</button>
            <button class="tab" onclick="switchTab('loan_granted')">Loan Granted</button>
        </div>

        <div id="loansContainer" class="loading">
            Loading loans...
        </div>
    </div>

    <!-- Modal for Admin Notes -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Action</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="modalMessage"></p>
                <label style="display: block; margin-top: 15px; font-weight: 600;">Admin Notes (Optional):</label>
                <textarea id="adminNotes" placeholder="Enter notes about this decision..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-action btn-disapprove" onclick="closeModal()">Cancel</button>
                <button class="btn btn-action" id="confirmBtn" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Modal for Loan Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content details-modal-content">
            <div class="modal-header">
                <h2>📋 Loan Details</h2>
                <button class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body details-modal-body">
                <div id="detailsContent"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-action btn-view" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let allLoans = [];
        let currentLoanId = null;
        let currentLoanType = null;
        let currentAction = null;

        // Load loans
        function loadLoans() {
            const loanType = document.getElementById('loanTypeFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            document.getElementById('loansContainer').innerHTML = '<div class="loading">Loading loans...</div>';
            
            fetch(`api/get_loans.php?type=${loanType}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allLoans = data.loans;
                        displayLoans(data.loans);
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

        // Display loans
        function displayLoans(loans) {
            const container = document.getElementById('loansContainer');
            
            if (loans.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📭</div><p>No loans found</p></div>';
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
                        <span class="loan-type ${loanTypeClass}">${loan.loan_type === 'e_loan' ? 'E Loan' : 'ATM Loan'}</span>
                        <span class="status-badge ${statusClass}">${loan.status.toUpperCase()}</span>
                    </div>
                    <div class="loan-amount">₱${parseFloat(loan.loan_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                    <div class="loan-info">
                        <div class="loan-info-item">
                            <strong>Applicant:</strong>
                            <span>${loan.user_name}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Email:</strong>
                            <span>${loan.user_email}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Contact:</strong>
                            <span>${loan.contact_number}</span>
                        </div>
                        <div class="loan-info-item">
                            <strong>Submitted:</strong>
                            <span>${new Date(loan.created_at).toLocaleDateString()}</span>
                        </div>
                        ${loan.reviewed_by_name ? `
                        <div class="loan-info-item">
                            <strong>Reviewed by:</strong>
                            <span>${loan.reviewed_by_name}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="loan-actions">
                        ${loan.status === 'pending' ? `
                            <button class="btn-action btn-approve" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'approved')"> Approve </button>
                            <button class="btn-action btn-disapprove" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'disapproved')"> Disapprove </button>
                            <button class="btn-action btn-pending" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'pending')"> Pending </button>
                        ` : loan.status === 'approved' ? `
                            <button class="btn-action btn-grant" onclick="grantLoan(${loan.id}, '${loan.loan_type}')"> Loan Granted </button>
                        ` : loan.status === 'loan_granted' ? `
                            <button class="btn-action btn-renew" onclick="renewLoan(${loan.id}, '${loan.loan_type}')"> Renew </button>
                        ` : loan.status === 'disapproved' ? `
                            <button class="btn-action btn-approve" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'approved')"> Approve </button>
                            <button class="btn-action btn-disapprove" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'disapproved')"> Disapprove </button>
                            <button class="btn-action btn-pending" onclick="openActionModal(${loan.id}, '${loan.loan_type}', 'pending')"> Pending </button>
                        ` : ''}
                        <button class="btn-action btn-view" onclick="viewLoanDetails(${loan.id}, '${loan.loan_type}')">View Details</button>
                    </div>
                `;
                
                grid.appendChild(card);
            });
        }

        // Switch tabs
        function switchTab(status) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            document.getElementById('statusFilter').value = status;
            loadLoans();
        }

        // Open action modal
        function openActionModal(loanId, loanType, action) {
            currentLoanId = loanId;
            currentLoanType = loanType;
            currentAction = action;
            
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            
            let actionText = '';
            let buttonText = '';
            let buttonClass = '';
            
            if (action === 'approved') {
                actionText = 'Approve Loan';
                buttonText = 'Approve';
                buttonClass = 'btn-approve';
                message.textContent = `Are you sure you want to approve this ${loanType === 'e_loan' ? 'E Loan' : 'ATM Loan'} application?`;
            } else if (action === 'disapproved') {
                actionText = 'Disapprove Loan';
                buttonText = 'Disapprove';
                buttonClass = 'btn-disapprove';
                message.textContent = `Are you sure you want to disapprove this ${loanType === 'e_loan' ? 'E Loan' : 'ATM Loan'} application?`;
            } else if (action === 'pending') {
                actionText = 'Set to Pending';
                buttonText = 'Set to Pending';
                buttonClass = 'btn-pending';
                message.textContent = `Are you sure you want to set this ${loanType === 'e_loan' ? 'E Loan' : 'ATM Loan'} application back to pending?`;
            }
            
            title.textContent = actionText;
            confirmBtn.textContent = buttonText;
            confirmBtn.className = `btn btn-action ${buttonClass}`;
            document.getElementById('adminNotes').value = '';
            
            modal.classList.add('active');
        }

        // Close modal
        function closeModal() {
            document.getElementById('actionModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });

        // Confirm action
        function confirmAction() {
            const adminNotes = document.getElementById('adminNotes').value;
            
            fetch('api/update_loan_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    loan_type: currentLoanType,
                    loan_id: currentLoanId,
                    status: currentAction,
                    admin_notes: adminNotes
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMessage = '';
                    if (currentAction === 'approved') {
                        successMessage = 'Loan application approved successfully!';
                    } else if (currentAction === 'disapproved') {
                        successMessage = 'Loan application disapproved successfully!';
                    } else if (currentAction === 'pending') {
                        successMessage = 'Loan application set to pending successfully!';
                    } else if (currentAction === 'loan_granted') {
                        successMessage = 'Loan granted successfully!';
                    }
                    alert(successMessage);
                    closeModal();
                    loadLoans();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update loan status'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        // Grant loan
        function grantLoan(loanId, loanType) {
            currentLoanId = loanId;
            currentLoanType = loanType;
            currentAction = 'loan_granted';
            
            const modal = document.getElementById('actionModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            
            title.textContent = 'Grant Loan';
            message.textContent = `Are you sure you want to mark this ${loanType === 'e_loan' ? 'E Loan' : 'ATM Loan'} application as granted?`;
            confirmBtn.textContent = 'Grant Loan';
            confirmBtn.className = 'btn btn-action btn-grant';
            document.getElementById('adminNotes').value = '';
            
            modal.classList.add('active');
        }

        // Renew loan
        function renewLoan(loanId, loanType) {
            if (!confirm('Are you sure you want to renew this loan? This will delete the current loan application and allow the user to apply for a new loan.')) {
                return;
            }

            fetch('api/renew_loan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    loan_type: loanType,
                    loan_id: loanId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Loan renewed successfully! The user can now apply for a new loan.');
                    loadLoans();
                } else {
                    alert('Error: ' + (data.message || 'Failed to renew loan'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        // View loan details
        function viewLoanDetails(loanId, loanType) {
            const loan = allLoans.find(l => l.id === loanId && l.loan_type === loanType);
            if (!loan) return;
            
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('detailsContent');
            
            const loanTypeClass = loanType === 'e_loan' ? 'e-loan' : 'atm-loan';
            const statusClass = `status-${loan.status}`;
            
            content.innerHTML = `
                <div class="details-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span class="details-loan-type ${loanTypeClass}">${loanType === 'e_loan' ? 'E Loan' : 'ATM Loan'}</span>
                        <span class="details-value status ${statusClass}">${loan.status.toUpperCase().replace('_', ' ')}</span>
                    </div>
                    <div class="details-amount">₱${parseFloat(loan.loan_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                </div>

                <div class="details-section">
                    <div class="details-section-title">Applicant Information</div>
                    <div class="details-grid">
                        <div class="details-item">
                            <span class="details-label">Applicant Name</span>
                            <span class="details-value">${loan.user_name}</span>
                        </div>
                        <div class="details-item">
                            <span class="details-label">Email</span>
                            <span class="details-value">${loan.user_email}</span>
                        </div>
                        <div class="details-item">
                            <span class="details-label">Contact Number</span>
                            <span class="details-value">${loan.contact_number}</span>
                        </div>
                        ${loan.address ? `
                        <div class="details-item" style="grid-column: 1 / -1;">
                            <span class="details-label">Address</span>
                            <span class="details-value">${loan.address}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div class="details-section">
                    <div class="details-section-title">Identification Documents</div>
                    <div class="details-grid">
                        <div class="details-item">
                            <span class="details-label">Company ID Type</span>
                            <span class="details-value">${loan.company_id_type}</span>
                        </div>
                        <div class="details-item">
                            <span class="details-label">Government ID Type</span>
                            <span class="details-value">${loan.government_id_type}</span>
                        </div>
                    </div>
                </div>

                ${loan.loan_purpose ? `
                <div class="details-section">
                    <div class="details-section-title">Loan Purpose</div>
                    <div class="details-value" style="padding: 10px; background: #f8f9fa; border-radius: 8px; line-height: 1.6;">
                        ${loan.loan_purpose}
                    </div>
                </div>
                ` : ''}

                <div class="details-section">
                    <div class="details-section-title">Application Timeline</div>
                    <div class="details-grid">
                        <div class="details-item">
                            <span class="details-label">Submitted</span>
                            <span class="details-value">${new Date(loan.created_at).toLocaleString()}</span>
                        </div>
                        ${loan.reviewed_at ? `
                        <div class="details-item">
                            <span class="details-label">Reviewed</span>
                            <span class="details-value">${new Date(loan.reviewed_at).toLocaleString()}</span>
                        </div>
                        ` : ''}
                        ${loan.reviewed_by_name ? `
                        <div class="details-item">
                            <span class="details-label">Reviewed By</span>
                            <span class="details-value">${loan.reviewed_by_name}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>

                ${loan.admin_notes ? `
                <div class="details-section">
                    <div class="details-section-title">Admin Notes</div>
                    <div class="details-value" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 8px; line-height: 1.6;">
                        ${loan.admin_notes}
                    </div>
                </div>
                ` : ''}
            `;
            
            modal.classList.add('active');
        }

        // Close details modal
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        // Event listeners
        document.getElementById('loanTypeFilter').addEventListener('change', loadLoans);
        document.getElementById('statusFilter').addEventListener('change', loadLoans);

        // Load loans on page load
        loadLoans();
    </script>
</body>
</html>

