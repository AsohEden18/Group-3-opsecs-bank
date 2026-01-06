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

// Loan request handler
require_once(__DIR__ . '/../includes/config.php');
require_once('../includes/functions.php');

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
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);
    $loan_purpose = trim($_POST['loan_purpose'] ?? '');
    $loan_duration = intval($_POST['loan_duration'] ?? 0);
    $employment_status = trim($_POST['employment_status'] ?? '');
    $monthly_income = floatval($_POST['monthly_income'] ?? 0);
    $additional_info = trim($_POST['additional_info'] ?? '');
    
    // Validate required fields
    if (!$full_name || !$email || !$phone || !$account_number || !$loan_amount || !$loan_purpose || !$loan_duration || !$employment_status || !$monthly_income) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate email format
    if (!validateEmail($email)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate phone format (Cameroon)
    if (!validatePhone($phone)) {
        throw new Exception('Invalid phone number format');
    }
    
    // Validate loan amount
    if ($loan_amount < 1000) {
        throw new Exception('Loan amount must be at least XAF 1000');
    }
    
    // Validate loan duration
    if ($loan_duration < 1 || $loan_duration > 60) {
        throw new Exception('Loan duration must be between 1 and 60 months');
    }
    
    // Check if email exists in users table
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $stmt->close();
    
    if ($user_result->num_rows == 0) {
        throw new Exception('Email not found in our system. Please sign up first.');
    }
    
    $user = $user_result->fetch_assoc();
    $user_id = $user['id'];
    
    // Check if account number exists and belongs to this user
    $stmt = $conn->prepare("SELECT id FROM accounts WHERE account_number = ? AND user_id = ?");
    $stmt->bind_param("si", $account_number, $user_id);
    $stmt->execute();
    $account_result = $stmt->get_result();
    $stmt->close();
    
    if ($account_result->num_rows == 0) {
        throw new Exception('Account number does not match your profile. Please verify and try again.');
    }
    
    $account = $account_result->fetch_assoc();
    $account_id = $account['id'];
    
    // Insert loan request
    $status = 'pending';
    $loan_type = $loan_purpose;
    $interest_rate = 0; // To be set by admin
    $monthly_payment = 0; // To be calculated by admin
    
    $stmt = $conn->prepare("INSERT INTO loans (user_id, account_id, loan_amount, loan_type, interest_rate, term_months, monthly_payment, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsdids", $user_id, $account_id, $loan_amount, $loan_type, $interest_rate, $loan_duration, $monthly_payment, $status);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit loan request: ' . $stmt->error);
    }
    
    $loan_id = $stmt->insert_id;
    $stmt->close();
    
    // Insert additional loan request details
    $stmt = $conn->prepare("INSERT INTO loan_request_details (loan_id, employment_status, monthly_income, additional_info) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $loan_id, $employment_status, $monthly_income, $additional_info);
    $stmt->execute();
    $stmt->close();
    
    $response['success'] = true;
    $response['message'] = 'Loan application submitted successfully! Our team will review your request and contact you soon.';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
