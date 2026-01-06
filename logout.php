<?php
session_start();

// Destroy session
session_destroy();

// Delete remember me cookie if exists
if (isset($_COOKIE['opsecs_remember'])) {
    setcookie('opsecs_remember', '', time() - 3600, '/');
}

// Redirect to login
header('Location: login.php');
exit();
?>
