<?php
include '../DB/config.php'; // Include your database configuration

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture and sanitize input data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // Prepare and execute SQL query to fetch user data
        $stmt = $pdo->prepare("SELECT * FROM login WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify user credentials
        if ($user && password_verify($password, $user['password'])) {
            session_start();
            session_regenerate_id(true); // Regenerate session ID for security

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role']; // Store user role

            // Redirect to the confirmation page with role parameter
            if ($user['role'] === 'admin') {
                header("Location: confirm_login.php?status=success&role=admin");
            } else {
                header("Location: confirm_login.php?status=success&role=user");
            }
            exit();
        } else {
            // Invalid credentials, redirect with error status
            header("Location: confirm_login.php?status=error");
            exit();
        }
    } else {
        // Empty fields, redirect with error status
        header("Location: confirm_login.php?status=error");
        exit();
    }
}
?>
