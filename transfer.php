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
    $sender_name = trim($_POST['sender_name'] ?? '');
    $sender_account = trim($_POST['sender_account'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_account = trim($_POST['recipient_account'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $transfer_date = trim($_POST['transfer_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate required fields
    if (!$sender_name || !$sender_account || !$recipient_name || !$recipient_account || !$amount || !$transfer_date) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Transfer amount must be greater than zero');
    }
    
    // Check if sender account exists
    $stmt = $conn->prepare("SELECT id, balance FROM accounts WHERE account_number = ?");
    $stmt->bind_param("s", $sender_account);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception('Sender account not found');
    }
    
    $sender = $result->fetch_assoc();
    $sender_account_id = $sender['id'];
    
    // Check if sender has sufficient balance
    if ($sender['balance'] < $amount) {
        throw new Exception('Insufficient balance for this transfer');
    }
    
    // Check if recipient account exists
    $stmt = $conn->prepare("SELECT id FROM accounts WHERE account_number = ?");
    $stmt->bind_param("s", $recipient_account);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception('Recipient account not found');
    }
    
    $recipient = $result->fetch_assoc();
    $recipient_account_id = $recipient['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate base reference number
        $base_reference = 'TRF-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        
        // Deduct from sender account
        $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $sender_account_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to deduct from sender account');
        }
        $stmt->close();
        
        // Add to recipient account
        $stmt = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $recipient_account_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to add to recipient account');
        }
        $stmt->close();
        
        // Record sender transaction
        $sender_reference = $base_reference . '-OUT';
        $stmt = $conn->prepare("
            INSERT INTO transactions (account_id, transaction_type, amount, description, reference_number, status)
            VALUES (?, 'transfer', ?, ?, ?, 'completed')
        ");
        
        $sender_description = "Transfer to " . $recipient_name . ": " . $description;
        $stmt->bind_param("idss", $sender_account_id, $amount, $sender_description, $sender_reference);
        if (!$stmt->execute()) {
            throw new Exception('Failed to record sender transaction');
        }
        $stmt->close();
        
        // Record recipient transaction
        $recipient_reference = $base_reference . '-IN';
        $stmt = $conn->prepare("
            INSERT INTO transactions (account_id, transaction_type, amount, description, reference_number, status)
            VALUES (?, 'transfer', ?, ?, ?, 'completed')
        ");
        
        $recipient_description = "Transfer from " . $sender_name . ": " . $description;
        $stmt->bind_param("idss", $recipient_account_id, $amount, $recipient_description, $recipient_reference);
        if (!$stmt->execute()) {
            throw new Exception('Failed to record recipient transaction');
        }
        $stmt->close();
        
        $conn->commit();
        
        // Log the action
        logAction('transfer_completed', 'transactions', $sender_account_id, 
            "Transfer of " . $amount . " XAF from " . $sender_name . " to " . $recipient_name);
        
        $response = [
            'success' => true,
            'message' => 'Transfer completed successfully',
            'reference_number' => $base_reference,
            'amount' => $amount,
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