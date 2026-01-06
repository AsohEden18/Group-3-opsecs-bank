<?php
require_once('../includes/config.php');
require_once('../includes/functions.php');

// Check if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$remember = false;

// Check for success message from registration
if (isset($_GET['success'])) {
    $success = "Registration successful! Please login with your credentials.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        // Check user in database
        $stmt = $conn->prepare("SELECT id, full_name, password, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Check if account is active
            if ($user['status'] !== 'active') {
                $error = "Your account is not active. Please contact support.";
            } elseif (verifyPassword($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $email;

                // Set remember me cookie
                if ($remember) {
                    $token = generateToken();
                    setcookie('opsecs_remember', $token, time() + (30 * 24 * 60 * 60), '/');
                    // Store token in database if needed
                }

                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OPSECS</title>
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
            max-width: 450px;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
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
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        input:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.3);
        }
        .checkbox-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 8px;
        }
        .checkbox-group label {
            margin: 0;
        }
        .forgot-link {
            font-size: 14px;
        }
        .forgot-link a {
            color: #0d6efd;
            text-decoration: none;
        }
        .alert {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            margin-bottom: 20px;
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .register-link a {
            color: #0d6efd;
            text-decoration: none;
        }
        .register-link a:hover {
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
        <h1>OPSECS Ltd - Login</h1>
    </div>

    <main>
        <div class="container">
            <h2>Sign In</h2>
            <p class="subtitle">Access your account securely</p>

            <?php if ($success): ?>
                <div class="alert" style="background: #d4edda; color: #155724; border-color: #c3e6cb;"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <span class="forgot-link">
                        <a href="forgot-password.php">Forgot password?</a>
                    </span>
                </div>

                <button type="submit" class="btn">Sign In</button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 OPSECS Ltd. All rights reserved.</p>
    </footer>
</body>
</html>
