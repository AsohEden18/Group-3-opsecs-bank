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
    $account_type = trim($_POST['account_type'] ?? '');
    $initial_amount = floatval($_POST['initial_amount'] ?? 0);
    $term_months = intval($_POST['term_months'] ?? 12);
    $savings_goal = trim($_POST['savings_goal'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if (!$full_name || !$email || !$phone || !$account_type || $initial_amount <= 0) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email address');
    }
    
    // Check if user exists or create new user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
    } else {
        // Create new user
        $password = password_hash('temp_password_123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            INSERT INTO users (full_name, email, phone, password, account_type)
            VALUES (?, ?, ?, ?, 'individual')
        ");
        $stmt->bind_param("ssss", $full_name, $email, $phone, $password);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user account');
        }
        $user_id = $stmt->insert_id;
    }
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate account number
        $account_number = 'SAV-' . strtoupper(uniqid());
        
        // Create account
        $stmt = $conn->prepare("
            INSERT INTO accounts (user_id, account_number, account_type, balance, currency, status)
            VALUES (?, ?, ?, ?, 'XAF', 'active')
        ");
        
        $stmt->bind_param("issd", $user_id, $account_number, $account_type, $initial_amount);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create savings account');
        }
        
        $account_id = $stmt->insert_id;
        $stmt->close();
        
        // Record the initial deposit as a transaction
        $reference_number = 'SAV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare("
            INSERT INTO transactions (account_id, transaction_type, amount, description, reference_number, status)
            VALUES (?, 'deposit', ?, ?, ?, 'completed')
        ");
        
        $trans_desc = "Initial savings deposit - Goal: " . (!empty($savings_goal) ? $savings_goal : 'General Savings');
        $stmt->bind_param("idss", $account_id, $initial_amount, $trans_desc, $reference_number);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to record initial deposit');
        }
        $stmt->close();
        
        // Create savings details record
        $stmt = $conn->prepare("
            INSERT INTO savings_accounts (account_id, user_id, savings_goal, term_months, notes, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        if ($stmt) {
            $stmt->bind_param("iisss", $account_id, $user_id, $savings_goal, $term_months, $notes);
            if (!$stmt->execute()) {
                throw new Exception('Failed to record savings details');
            }
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log the action
        logAction('savings_account_opened', 'accounts', $account_id, 
            "Savings account opened for " . $full_name . " with initial deposit of " . $initial_amount . " XAF");
        
        $response = [
            'success' => true,
            'message' => 'Savings account created successfully',
            'account_number' => $account_number,
            'initial_deposit' => $initial_amount,
            'account_type' => $account_type,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>