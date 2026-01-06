<?php
// Set error handler for API
set_error_handler('jsonErrorHandler');

require_once('../includes/config.php');
require_once('../includes/functions.php');

// Check if user is logged in
if (!isLoggedIn()) {
    apiResponse('error', 'Unauthorized', null);
}

$user_id = getUserId();
$action = sanitize($_GET['action'] ?? '');

// Get user accounts
if ($action == 'get_accounts') {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
    $stmt->close();
    
    apiResponse('success', 'Accounts retrieved', $accounts);
}

// Get account details with transactions
elseif ($action == 'get_account') {
    $account_id = $_GET['account_id'] ?? 0;
    
    // Verify account belongs to user
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $account_id, $user_id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$account) {
        apiResponse('error', 'Account not found', null);
    }
    
    // Get transactions
    $trans_stmt = $conn->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 50");
    $trans_stmt->bind_param("i", $account_id);
    $trans_stmt->execute();
    $transactions = $trans_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $trans_stmt->close();
    
    $account['transactions'] = $transactions;
    
    apiResponse('success', 'Account details retrieved', $account);
}

// Get user loans
elseif ($action == 'get_loans') {
    $stmt = $conn->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    $stmt->close();
    
    apiResponse('success', 'Loans retrieved', $loans);
}

// Apply for loan
elseif ($action == 'apply_loan' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = $_POST['account_id'] ?? 0;
    $loan_amount = $_POST['loan_amount'] ?? 0;
    $loan_type = sanitize($_POST['loan_type'] ?? '');
    $term_months = $_POST['term_months'] ?? 0;
    $interest_rate = $_POST['interest_rate'] ?? 5;

    // Validation
    if ($loan_amount <= 0) apiResponse('error', 'Invalid loan amount', null);
    if ($term_months <= 0) apiResponse('error', 'Invalid term', null);

    // Verify account belongs to user
    $check_stmt = $conn->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $account_id, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows == 0) {
        apiResponse('error', 'Account not found', null);
    }
    $check_stmt->close();

    // Calculate monthly payment
    $monthly_rate = $interest_rate / 100 / 12;
    $monthly_payment = $loan_amount * ($monthly_rate * pow(1 + $monthly_rate, $term_months)) / (pow(1 + $monthly_rate, $term_months) - 1);

    // Create loan application
    $stmt = $conn->prepare("INSERT INTO loans (user_id, account_id, loan_amount, loan_type, interest_rate, term_months, monthly_payment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iidsdid", $user_id, $account_id, $loan_amount, $loan_type, $interest_rate, $term_months, $monthly_payment);
    
    if ($stmt->execute()) {
        apiResponse('success', 'Loan application submitted successfully', ['loan_id' => $stmt->insert_id]);
    } else {
        apiResponse('error', 'Failed to submit loan application', null);
    }
    $stmt->close();
}

else {
    apiResponse('error', 'Invalid action', null);
}
?>
