<?php
require_once '../DB/config.php'; // Ensure your database connection file is included

session_start(); // Start the session to access the logged-in user's data

try {
    // Ensure the logged-in user's ID is retrieved from the session
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    } else {
        die(json_encode(['error' => 'User not logged in.']));
    }

    // Prepare the SQL statement
    $stmt = $pdo->prepare("
        SELECT name, code 
        FROM parties 
        WHERE created_by = :created_by OR created_by = 0
    ");
    $stmt->execute([':created_by' => $userId]);

    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($parties);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
