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

$pdo = getDBConnection();

// Get all overdue payments
try {
    $stmt = $pdo->query("
        SELECT op.*, u.name as user_name, u.email as user_email
        FROM overdue_payments op
        INNER JOIN users u ON op.user_id = u.id
        WHERE op.is_settled = FALSE
        ORDER BY op.created_at DESC
    ");
    $overduePayments = $stmt->fetchAll();
} catch (PDOException $e) {
    $overduePayments = [];
}

// Get all users for creating new overdue payment
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name");
$allUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Overdue Payments - Loan System</title>
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            color: #333;
            font-size: 24px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-edit {
            background: #007bff;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-edit:hover {
            background: #0056b3;
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
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 24px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #210a1a;
        }

        .qr-code-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .file-upload {
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>💳 Manage Overdue Payments</h1>
            <div>
                <a href="dashboard.php" class="btn btn-primary">← Dashboard</a>
                <button class="btn btn-success" onclick="openCreateModal()">+ Add Overdue Payment</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Overdue Payments</h2>
            </div>
            <div class="table-container">
                <?php if (empty($overduePayments)): ?>
                <div class="empty-state">
                    <p>No overdue payments found.</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Loan Type</th>
                            <th>Days Overdue</th>
                            <th>Amount to Pay</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overduePayments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['user_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($payment['user_email']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    <?php echo strtoupper($payment['loan_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $payment['days_overdue']; ?> day(s)</td>
                            <td>₱<?php echo number_format($payment['amount_to_pay'], 2); ?></td>
                            <td><?php echo $payment['due_date'] ? date('M d, Y', strtotime($payment['due_date'])) : 'N/A'; ?></td>
                            <td>
                                <button class="btn-edit" onclick="editPayment(<?php echo htmlspecialchars(json_encode($payment)); ?>)">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Overdue Payment</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                <input type="hidden" id="paymentId" name="payment_id">
                <input type="hidden" id="userId" name="user_id">
                
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="userName" readonly>
                </div>

                <div class="form-group">
                    <label for="daysOverdue">Days Overdue *</label>
                    <input type="number" id="daysOverdue" name="days_overdue" min="0" required>
                </div>

                <div class="form-group">
                    <label for="amountToPay">Amount to Pay (₱) *</label>
                    <input type="number" id="amountToPay" name="amount_to_pay" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="dueDate">Due Date</label>
                    <input type="date" id="dueDate" name="due_date">
                </div>

                <div class="form-group">
                    <label>QR Code 1</label>
                    <div id="qrCode1Preview"></div>
                    <input type="file" id="qrCode1" name="qr_code_1" accept="image/*" class="file-upload">
                </div>

                <div class="form-group">
                    <label>QR Code 2</label>
                    <div id="qrCode2Preview"></div>
                    <input type="file" id="qrCode2" name="qr_code_2" accept="image/*" class="file-upload">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Update Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Overdue Payment</h2>
                <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            <form id="createForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="createUserId">User *</label>
                    <select id="createUserId" name="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="createLoanId">Loan ID *</label>
                    <input type="number" id="createLoanId" name="loan_id" required>
                </div>

                <div class="form-group">
                    <label for="createLoanType">Loan Type *</label>
                    <select id="createLoanType" name="loan_type" required>
                        <option value="">Select Loan Type</option>
                        <option value="e_loan">E Loan</option>
                        <option value="atm_loan">ATM Loan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="createDaysOverdue">Days Overdue *</label>
                    <input type="number" id="createDaysOverdue" name="days_overdue" min="0" required>
                </div>

                <div class="form-group">
                    <label for="createAmountToPay">Amount to Pay (₱) *</label>
                    <input type="number" id="createAmountToPay" name="amount_to_pay" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="createDueDate">Due Date</label>
                    <input type="date" id="createDueDate" name="due_date">
                </div>

                <div class="form-group">
                    <label for="createQrCode1">QR Code 1</label>
                    <input type="file" id="createQrCode1" name="qr_code_1" accept="image/*" class="file-upload">
                </div>

                <div class="form-group">
                    <label for="createQrCode2">QR Code 2</label>
                    <input type="file" id="createQrCode2" name="qr_code_2" accept="image/*" class="file-upload">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Create Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editPayment(payment) {
            document.getElementById('paymentId').value = payment.id;
            document.getElementById('userId').value = payment.user_id;
            document.getElementById('userName').value = payment.user_name + ' (' + payment.user_email + ')';
            document.getElementById('daysOverdue').value = payment.days_overdue;
            document.getElementById('amountToPay').value = payment.amount_to_pay;
            document.getElementById('dueDate').value = payment.due_date || '';
            
            // Preview QR codes
            const qr1Preview = document.getElementById('qrCode1Preview');
            const qr2Preview = document.getElementById('qrCode2Preview');
            qr1Preview.innerHTML = '';
            qr2Preview.innerHTML = '';
            
            if (payment.qr_code_1_path) {
                qr1Preview.innerHTML = '<img src="../' + payment.qr_code_1_path + '" class="qr-code-preview" alt="QR Code 1">';
            }
            if (payment.qr_code_2_path) {
                qr2Preview.innerHTML = '<img src="../' + payment.qr_code_2_path + '" class="qr-code-preview" alt="QR Code 2">';
            }
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }

        // Handle edit form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/update_overdue_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update payment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        });

        // Handle create form submission
        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/create_overdue_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Overdue payment created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create payment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const editModal = document.getElementById('editModal');
            const createModal = document.getElementById('createModal');
            if (e.target === editModal) {
                closeModal();
            }
            if (e.target === createModal) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>

