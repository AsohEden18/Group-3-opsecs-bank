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

// Get user's accounts
$accounts_stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ?");
$accounts_stmt->bind_param("i", $user_id);
$accounts_stmt->execute();
$accounts = $accounts_stmt->get_result();
$accounts_stmt->close();

// Get all deposits for this user
$deposits_stmt = $conn->prepare("SELECT d.*, a.account_number FROM deposits d JOIN accounts a ON d.account_id = a.id WHERE d.account_id IN (SELECT id FROM accounts WHERE user_id = ?) ORDER BY d.created_at DESC");
$deposits_stmt->bind_param("i", $user_id);
$deposits_stmt->execute();
$deposits = $deposits_stmt->get_result();
$deposits_stmt->close();

// Get deposit statistics
$stats_stmt = $conn->prepare("SELECT COUNT(*) as total_deposits, SUM(amount) as total_amount FROM deposits d JOIN accounts a ON d.account_id = a.id WHERE a.user_id = ?");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposits - OPSECS Solidarity Ltd</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0d6efd;
            padding: 15px 30px;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .navbar-menu {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .navbar-menu a, .navbar-menu button {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .navbar-menu a:hover, .navbar-menu button:hover {
            background: rgba(255,255,255,0.2);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 32px;
            color: #0d6efd;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #666;
            font-size: 16px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #0d6efd;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #0d6efd;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 22px;
            color: #0d6efd;
            margin-bottom: 20px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        table tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .amount {
            font-weight: 600;
            color: #0d6efd;
        }
        .no-deposits {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .no-deposits p {
            margin: 10px 0;
        }
        .action-links {
            margin-top: 30px;
            text-align: center;
        }
        .action-links a {
            display: inline-block;
            background: #0d6efd;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            margin: 0 10px;
            transition: background 0.3s;
        }
        .action-links a:hover {
            background: #0a58ca;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #999;
            border-top: 1px solid #ddd;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">OPSECS Solidarity Ltd</div>
        <div class="navbar-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="deposits.php" style="background: rgba(255,255,255,0.3);">Deposits</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Your Deposits</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Deposits</h3>
                <div class="value"><?php echo $stats['total_deposits'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Amount Deposited</h3>
                <div class="value">XAF <?php echo number_format($stats['total_amount'] ?? 0, 0, '.', ','); ?></div>
            </div>
        </div>

        <!-- Deposits Table -->
        <div class="section">
            <h2>Deposit History</h2>
            <?php if ($deposits->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reference #</th>
                            <th>Account</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($deposit = $deposits->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($deposit['reference_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($deposit['account_number']); ?></td>
                                <td class="amount">XAF <?php echo number_format($deposit['amount'], 2, '.', ','); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $deposit['deposit_method']))); ?></td>
                                <td>
                                    <?php 
                                    $status = $deposit['status'] ?? 'pending';
                                    $badge_class = 'badge-' . $status;
                                    echo '<span class="badge ' . $badge_class . '">' . ucfirst($status) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($deposit['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-deposits">
                    <p>No deposits yet.</p>
                    <p>Start by making your first deposit!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Links -->
        <div class="action-links">
            <a href="../../deposits.html">Make a Deposit</a>
            <a href="dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2024 OPSECS Solidarity Ltd. All rights reserved.</p>
    </div>
</body>
</html>
