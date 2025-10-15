<?php
// Include your database configuration file
include '../DB/config.php';
session_start(); // Start the session to access session variables

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the input values and trim whitespace
    $fullname = trim($_POST['updatename']);
    $email = trim($_POST['updateemail']);
    $address = htmlspecialchars(trim($_POST['updateaddress']));
    $password = trim($_POST['updatepassword']);
    $phone = trim($_POST['updatephone']);
    $userId = $_SESSION['user_id']; // Assuming user_id is stored in the session

    // Validate input
    if (empty($fullname) || empty($email) || empty($address) || empty($password) || empty($phone)) {
        echo "Please fill in all fields.";
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format.";
        exit();
    }

    if (!is_numeric($phone) || strlen($phone) < 10 || strlen($phone) > 15) {
        echo "Invalid phone number.";
        exit();
    }

    // Hash the new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Prepare the SQL update statement
        $stmt = $pdo->prepare("
            UPDATE login 
            SET fullname = :fullname, email = :email, Address = :address, password = :password, phone = :phone 
            WHERE id = :id
        ");
        // Execute the statement with the updated values
        $stmt->execute([
            ':fullname' => $fullname,
            ':email' => $email,
            ':address' => $address,
            ':password' => $hashedPassword,
            ':phone' => $phone,
            ':id' => $userId
        ]);

        // Update successful, redirect to a success page or logout
        header("Location: ../System/logout.php");
        exit();
    } catch (PDOException $e) {
        // Handle update failure
        echo "Error: Could not update user information. " . htmlspecialchars($e->getMessage());
        exit();
    }
} else {
    // Redirect to the dashboard if the request method is not POST
    header("Location: ../System/logout.php");
    exit();
}
?>
