<?php
// Include database connection
include '../DB/config.php'; 

// Start the session
session_start();

// Check if the form was submitted and the user is logged in
if (isset($_POST['send_message']) && isset($_SESSION['user_id'])) {
    // Get the form data
    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    // Check if user_id exists in the login table
    $stmt = $pdo->prepare("SELECT id FROM login WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // If user exists, proceed with inserting the message
    if ($stmt->rowCount() > 0) {
        // Prepare the SQL query to insert the message into the database
        $stmt = $pdo->prepare("INSERT INTO messages (user_id, name, email, mobile, subject, message) 
                               VALUES (:user_id, :name, :email, :mobile, :subject, :message)");

        // Bind the parameters
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':account_id', $account_id);
        $stmt->bindParam(':account_name', $account_name);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':mobile', $mobile);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);

        // Execute the query
        if ($stmt->execute()) {
            // Optionally, send a notification to the admin
            $adminStmt = $pdo->prepare("SELECT id FROM login WHERE role = 'admin' LIMIT 1");
            $adminStmt->execute();
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                // Create a notification for the admin
                $admin_id = $admin['id'];
                $notificationMessage = "New message from $name: $subject";

                $notificationStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) 
                                                   VALUES (:user_id, :message)");
                $notificationStmt->bindParam(':user_id', $admin_id);
                $notificationStmt->bindParam(':message', $notificationMessage);
                $notificationStmt->execute();
            }

            // Redirect to the dashboard to show the message in the conversation section
            header("Location: ../Views/dashboard.php?status=message_sent&success=1");
            exit();
        } else {
            // Handle error if the message couldn't be inserted
            echo "There was an error sending your message. Please try again.";
        }
    } else {
        // Handle case if user_id is not found in the login table
        echo "User ID does not exist in the database.";
    }
} else {
    // Redirect if the user is not logged in
    header("Location: ../login.php");
    exit();
}
?>
