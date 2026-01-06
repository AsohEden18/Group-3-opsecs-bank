<?php
header('Content-Type: application/json');
require_once('../includes/config.php');
require_once('../includes/functions.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    $sender_name = trim($_POST['sender_name'] ?? '');
    $sender_phone = trim($_POST['sender_phone'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_phone = trim($_POST['recipient_phone'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if (!$sender_name || !$sender_phone || !$recipient_name || !$recipient_phone || !$provider || !$amount) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than zero');
    }
    
    // Generate reference number
    $reference_number = 'MOB-' . strtoupper(uniqid());
    
    // Create mobile transfer record
    $stmt = $conn->prepare("
        INSERT INTO mobile_transfers (
            sender_name, sender_phone, recipient_name, recipient_phone, 
            provider, amount, reference_number, notes, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->bind_param("sssssdss", $sender_name, $sender_phone, $recipient_name, 
        $recipient_phone, $provider, $amount, $reference_number, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to process mobile transfer');
    }
    
    // Log the action
    logAction('mobile_transfer_created', 'mobile_transfers', $stmt->insert_id, 
        "Mobile transfer of " . $amount . " XAF to " . $recipient_phone . " via " . $provider);
    
    $response = [
        'success' => true,
        'message' => 'Mobile transfer submitted successfully',
        'reference_number' => $reference_number,
        'amount' => $amount,
        'provider' => $provider,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
