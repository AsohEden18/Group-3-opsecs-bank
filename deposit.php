<?php
// Set error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $errstr
    ]);
    exit();
});

header('Content-Type: application/json');
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/functions.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $deposit_method = trim($_POST['deposit_method'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate required fields
    if (!$full_name || !$email || !$phone || !$account_number || !$amount || !$deposit_method) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Deposit amount must be greater than zero');
    }
    
    // Check if account exists
    $stmt = $conn->prepare("SELECT id FROM accounts WHERE account_number = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception('Account not found');
    }
    
    $account = $result->fetch_assoc();
    $account_id = $account['id'];
    
    // Generate reference number if not provided
    if (!$reference_number) {
        $reference_number = 'DEP-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update account balance
        $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $account_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update account balance');
        }
        $stmt->close();
        
        // Record the deposit transaction
        $stmt = $conn->prepare("
            INSERT INTO transactions (account_id, transaction_type, amount, description, reference_number, status)
            VALUES (?, 'deposit', ?, ?, ?, 'completed')
        ");
        
        $transaction_description = $description ?: 'Deposit';
        $stmt->bind_param("idss", $account_id, $amount, $transaction_description, $reference_number);
        if (!$stmt->execute()) {
            throw new Exception('Failed to record deposit: ' . $stmt->error);
        }
        $transaction_id = $stmt->insert_id;
        $stmt->close();
    
    // Get or create user for this deposit
        $user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $user_stmt->bind_param("s", $email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            $user_id = $user['id'];
        } else {
            // Create temporary user for deposit
            $temp_password = password_hash('temp_' . time(), PASSWORD_BCRYPT);
            $user_stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, account_type, status) VALUES (?, ?, ?, ?, 'individual', 'active')");
            $user_stmt->bind_param("ssss", $full_name, $email, $phone, $temp_password);
            $user_stmt->execute();
            $user_id = $user_stmt->insert_id;
        }
        $user_stmt->close();
        
        // Record in deposits table
        $deposit_stmt = $conn->prepare("
            INSERT INTO deposits (account_id, transaction_id, user_id, full_name, email, phone, amount, deposit_method, reference_number, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
        ");
        
        $deposit_stmt->bind_param("iissssdsss", $account_id, $transaction_id, $user_id, $full_name, $email, $phone, $amount, $deposit_method, $reference_number, $description);
        
        if (!$deposit_stmt->execute()) {
            throw new Exception('Failed to record deposit details: ' . $deposit_stmt->error);
        }
        $deposit_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log the action
        logAction('deposit_created', 'deposits', $user_id, 
            "Deposit of " . $amount . " XAF from " . $full_name . " - Reference: " . $reference_number);
        
        $response = [
            'success' => true,
            'message' => 'Deposit recorded successfully',
            'reference_number' => $reference_number,
            'amount' => $amount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        if ($conn->connect_error == null) {
            $conn->rollback();
        }
        $response['message'] = $e->getMessage();
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>