<?php
session_start();
require_once('../includes/config.php');
require_once('../includes/functions.php');

// Require login
requireLogin();

$user_id = getUserId();

// Get user data
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Get accounts
$accounts_stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ?");
$accounts_stmt->bind_param("i", $user_id);
$accounts_stmt->execute();
$accounts = $accounts_stmt->get_result();
$accounts_stmt->close();

// Get recent transactions
$trans_stmt = $conn->prepare("SELECT t.* FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE a.user_id = ? ORDER BY t.created_at DESC LIMIT 10");
$trans_stmt->bind_param("i", $user_id);
$trans_stmt->execute();
$transactions = $trans_stmt->get_result();
$trans_stmt->close();

// Get loans
$loans_stmt = $conn->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
$loans_stmt->bind_param("i", $user_id);
$loans_stmt->execute();
$loans = $loans_stmt->get_result();
$loans_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - OPSECS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }
        .navbar {
            background: #0d6efd;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 {
            font-size: 1.5rem;
        }
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .navbar-right a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            background: rgba(255,255,255,0.2);
        }
        .navbar-right a:hover {
            background: rgba(255,255,255,0.3);
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .card-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .card-value {
            color: #0d6efd;
            font-size: 28px;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn:hover {
            background: #0b5ed7;
        }
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .welcome {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .welcome h3 {
            color: #0d6efd;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>OPSECS Dashboard</h1>
        <div class="navbar-right">
            <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h3>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h3>
            <p>Account Type: <strong><?php echo ucfirst($user['account_type']); ?></strong></p>
            <p>KYC Status: <strong><?php echo $user['kyc_verified'] ? 'Verified' : 'Not Verified'; ?></strong></p>
        </div>

        <h2>Your Accounts</h2>
        <div class="dashboard-grid">
            <?php while($account = $accounts->fetch_assoc()): ?>
                <div class="card">
                    <div class="card-title">
                        <?php echo ucfirst($account['account_type']); ?> Account
                    </div>
                    <div class="card-value">
                        <?php echo formatCurrency($account['balance']); ?>
                    </div>
                    <p style="font-size: 12px; color: #999; margin-top: 10px;">
                        <?php echo $account['account_number']; ?>
                    </p>
                    <span class="status <?php echo $account['status'] == 'active' ? 'status-active' : ''; ?>">
                        <?php echo ucfirst($account['status']); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        </div>

        <h2>Your Loans</h2>
        <?php if($loans->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Loan Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Interest Rate</th>
                            <th>Monthly Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($loan = $loans->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                <td><?php echo ucfirst($loan['loan_type']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $loan['status']; ?>">
                                        <?php echo ucfirst($loan['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td><?php echo formatCurrency($loan['monthly_payment']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <a href="apply-loan.php" class="btn">Apply for New Loan</a>
        <?php else: ?>
            <div class="card">
                <p>You don't have any loans yet.</p>
                <a href="apply-loan.php" class="btn">Apply for a Loan</a>
            </div>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Recent Transactions</h2>
        <?php if($transactions->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($trans = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($trans['created_at'])); ?></td>
                                <td><?php echo ucfirst($trans['transaction_type']); ?></td>
                                <td><?php echo formatCurrency($trans['amount']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $trans['status']; ?>">
                                        <?php echo ucfirst($trans['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($trans['description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card">
                <p>No transactions yet.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
