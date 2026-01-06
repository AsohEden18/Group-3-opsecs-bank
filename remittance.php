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
    $recipient_phone = trim($_POST['recipient_phone'] ?? '');
    $recipient_country = trim($_POST['recipient_country'] ?? '');
    $send_amount = floatval($_POST['send_amount'] ?? 0);
    $recipient_currency = trim($_POST['recipient_currency'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    // Validate required fields
    if (!$sender_name || !$sender_account || !$recipient_name || !$recipient_phone || 
        !$recipient_country || !$send_amount || !$recipient_currency) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate amount
    if ($send_amount <= 0) {
        throw new Exception('Send amount must be greater than zero');
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
    
    // Check if sender has sufficient balance (add 5% fee)
    $fee = $send_amount * 0.05;
    $total_deduction = $send_amount + $fee;
    
    if ($sender['balance'] < $total_deduction) {
        throw new Exception('Insufficient balance (includes 5% international transfer fee)');
    }
    
    // Generate reference number
    $reference_number = 'REM-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create remittance record
        $stmt = $conn->prepare("
            INSERT INTO remittances (
                account_id, recipient_name, recipient_phone, recipient_country, 
                send_amount, recipient_currency, fee_amount, reference_number, 
                purpose, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->bind_param("isssddsss", $sender_account_id, $recipient_name, $recipient_phone, 
            $recipient_country, $send_amount, $recipient_currency, $fee, $reference_number, $purpose);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to process remittance: ' . $stmt->error);
        }
        $remittance_id = $stmt->insert_id;
        $stmt->close();
        
        // Deduct from sender account
        $stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $total_deduction, $sender_account_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to deduct from account');
        }
        $stmt->close();
        
        // Record transaction
        $stmt = $conn->prepare("
            INSERT INTO transactions (account_id, transaction_type, amount, description, reference_number, status)
            VALUES (?, 'transfer', ?, ?, ?, 'completed')
        ");
        
        $trans_desc = "International remittance to " . $recipient_name . " in " . $recipient_country . " (Fee: " . $fee . " XAF)";
        $stmt->bind_param("idss", $sender_account_id, $total_deduction, $trans_desc, $reference_number);
        if (!$stmt->execute()) {
            throw new Exception('Failed to record transaction');
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log the action
        logAction('remittance_created', 'remittances', $remittance_id, 
            "Remittance of " . $send_amount . " XAF to " . $recipient_name . " in " . $recipient_country);
        
        $response = [
            'success' => true,
            'message' => 'Remittance submitted successfully',
            'reference_number' => $reference_number,
            'send_amount' => $send_amount,
            'fee' => $fee,
            'total_deduction' => $total_deduction,
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