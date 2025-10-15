<?php
include '../DB/config.php'; 
session_start(); // Start the session to access the logged-in user's data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve POST data
    $partyName = trim($_POST['partyName']);
    $partyCode = trim($_POST['partyCode']);
    
    // Ensure the logged-in user's ID is retrieved from the session
    if (isset($_SESSION['user_id'])) {
        $createdBy = $_SESSION['user_id']; // Get the logged-in user's ID
    } else {
        die("Error: User not logged in.");
    }

    if (!empty($partyName) && !empty($partyCode)) {
        try {
            // Check if the user already has a party
            $stmt_check = $pdo->prepare("SELECT * FROM parties WHERE created_by = :created_by");
            $stmt_check->execute([':created_by' => $createdBy]);
            
            if ($stmt_check->rowCount() > 0) {
                // Redirect to dashboard with a session message
                $_SESSION['message'] = "You can only have one party.";
                header("Location: ../Views/dashboard.php?error=1");
                exit();
            } else {
                // Step 1: Insert the party details into the `parties` table
                $stmt = $pdo->prepare("
                    INSERT INTO parties (name, code, created_by) 
                    VALUES (:name, :code, :created_by)
                ");
                
                // Execute the statement with bound parameters
                $stmt->execute([
                    ':name' => $partyName,
                    ':code' => $partyCode,
                    ':created_by' => $createdBy
                ]);

                // Step 2: Create a new table for the party
                $tableName = "community_$partyCode"; // Table name based on party code
                $sql = "
                    CREATE TABLE `$tableName` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `sender_id` int(11) DEFAULT NULL,
                        `receiver_id` int(11) DEFAULT NULL,
                        `party_id` int(11) DEFAULT NULL,
                        `community_message` text NOT NULL,
                        `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
                        `image_path` varchar(255) DEFAULT NULL,
                        `reply_to_message_id` int(11) DEFAULT NULL,
                        `original_message` text DEFAULT NULL,
                        `replied` tinyint(1) DEFAULT 0,
                        `name_sender` varchar(255) DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";

                // Execute the SQL statement to create the table
                $pdo->exec($sql);

                // Redirect to dashboard on success
                $_SESSION['success'] = "Party created successfully!";
                header("Location: ../Views/dashboard.php?success=1");
                exit();
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "All fields are required.";
    }
}
?>
