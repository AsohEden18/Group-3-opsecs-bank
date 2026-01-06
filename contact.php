<?php
// Set error handler for API
set_error_handler('jsonErrorHandler');

require_once('../includes/config.php');
require_once('../includes/functions.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !validateEmail($email)) $errors[] = "Valid email is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message)) $errors[] = "Message is required";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
        
        if ($stmt->execute()) {
            // Send confirmation email (you can add email functionality)
            // For now, just store in database
            apiResponse('success', 'Your message has been sent successfully. We will contact you soon.', null);
        } else {
            apiResponse('error', 'Failed to send message. Please try again.', null);
        }
        $stmt->close();
    } else {
        apiResponse('error', 'Validation failed: ' . implode(', ', $errors), null);
    }
} else {
    apiResponse('error', 'Only POST requests are allowed', null);
}
?>
