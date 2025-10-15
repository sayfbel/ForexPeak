<?php
include '../DB/config.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($email) && !empty($password)) {

        // Check if the email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetchColumn();

        if ($emailExists) {
            // Email already exists
            header("Location: confirm_login.php?status=error");
            exit();
        } else {
            // Email is unique, proceed with registration
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert with created_at timestamp
            $stmt = $pdo->prepare("
                INSERT INTO login (fullname, email, password, created_at) 
                VALUES (?, ?, ?, NOW())
            ");

            if ($stmt->execute([$username, $email, $hashedPassword])) {
                // Redirect to confirmation page
                header("Location: confirm.php?status=success");
                exit();
            } else {
                echo "Error: Could not register user.";
            }
        }
    } else {
        echo "Please fill in all fields.";
    }
}
?>
