<?php
// Include database connection
include '../DB/config.php'; 

// Start the session
session_start();

// Check if the form was submitted and the user is logged in
if (isset($_POST['send_message']) && isset($_SESSION['user_id'])) {
    // Get the form data
    $user_id = $_SESSION['user_id'];
    $account_id = $_POST['account_id'] ?? null;
    $account_name = $_POST['account_name'] ?? null;
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    // Handle file upload (prove)
    $proveFileName = null;
    if (isset($_FILES['prove']) && $_FILES['prove']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['prove']['tmp_name'];
        $fileName = $_FILES['prove']['name'];
        $fileSize = $_FILES['prove']['size'];
        $fileType = $_FILES['prove']['type'];

        $allowedExtensions = ['jpg','jpeg','png','gif','pdf'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions)) {
            // Generate unique file name to avoid overwriting
            $newFileName = uniqid('prove_', true) . '.' . $fileExtension;

            $uploadDir = '../images/';
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $proveFileName = $newFileName; // save file name in DB
            } else {
                echo "Error moving the uploaded file.";
                exit();
            }
        } else {
            echo "Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.";
            exit();
        }
    }

    // Check if user_id exists in the login table
    $stmt = $pdo->prepare("SELECT id FROM login WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // If user exists, proceed with inserting the message
    if ($stmt->rowCount() > 0) {
        // Prepare the SQL query to insert the message into the database
        $stmt = $pdo->prepare("INSERT INTO messages 
        (user_id, account_id, account_name, name, email, subject, message, prove, created_at) 
        VALUES (:user_id, :account_id, :account_name, :name, :email, :subject, :message, :prove, NOW())");

        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':account_id', $account_id);
        $stmt->bindParam(':account_name', $account_name);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':prove', $proveFileName);


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
            echo "There was an error sending your message. Please try again.";
        }
    } else {
        echo "User ID does not exist in the database.";
    }
} else {
    header("Location: ../login.php");
    exit();
}
?>
