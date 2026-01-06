<?php
require_once('../includes/config.php');
require_once('../includes/functions.php');

// Check if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $account_type = sanitize($_POST['account_type'] ?? 'individual');

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "Valid email is required";
    }
    if (empty($phone) || !validatePhone($phone)) {
        $errors[] = "Valid phone number is required";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Check if email already exists
    if (empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $check_email->close();
    }

    // Register user if no errors
    if (empty($errors)) {
        $hashed_password = hashPassword($password);
        
        $insert = $conn->prepare("INSERT INTO users (full_name, email, phone, password, account_type) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $account_type);
        
        if ($insert->execute()) {
            $user_id = $insert->insert_id;
            
            // Create default account
            $account_number = 'ACC' . str_pad($user_id, 10, '0', STR_PAD_LEFT);
            $account_insert = $conn->prepare("INSERT INTO accounts (user_id, account_number) VALUES (?, ?)");
            $account_insert->bind_param("is", $user_id, $account_number);
            $account_insert->execute();
            $account_insert->close();
            
            // Redirect immediately after successful registration
            header("Location: login.php?success=1");
            exit();
        } else {
            $errors[] = "Registration failed: " . $insert->error;
        }
        $insert->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - OPSECS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background: #0d6efd;
            color: white;
            padding: 15px 20px;
            text-align: center;
        }
        .navbar h1 {
            font-size: 1.5rem;
        }
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        h2 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.3);
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover {
            background: #0b5ed7;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #0d6efd;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        footer {
            background: #222;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>OPSECS Ltd - Registration</h1>
    </div>

    <main>
        <div class="container">
            <h2>Create Your Account</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p>• <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+237 6XX XX XX XX" required>
                </div>

                <div class="form-group">
                    <label for="account_type">Account Type</label>
                    <select id="account_type" name="account_type" required>
                        <option value="individual">Individual</option>
                        <option value="business">Business</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn">Create Account</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 OPSECS Ltd. All rights reserved.</p>
    </footer>
</body>
</html>
