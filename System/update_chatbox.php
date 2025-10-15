<?php
session_start();
require '../DB/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['partyCode'])) {
        $partyCode = $data['partyCode'];
        $chatboxTable = "community_" . $partyCode;

        // Validate table name
        if (preg_match('/^[a-zA-Z0-9_]+$/', $chatboxTable)) {
            // Update the session variable
            $_SESSION['chatbox_table'] = $chatboxTable;

            try {
                // Fetch the database name (optional, for debugging purposes)
                $dbNameStmt = $pdo->query('SELECT DATABASE()');
                $dbName = $dbNameStmt->fetchColumn();

                // Fetch messages from the updated table
                $stmt = $pdo->query("
                    SELECT c.*, l.fullname 
                    FROM $chatboxTable c
                    JOIN login l ON c.sender_id = l.id
                    ORDER BY c.timestamp ASC
                ");
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Return success response with messages and database name
                echo json_encode([
                    'success' => true,
                    'messages' => $messages,
                    'databaseName' => $dbName // Include database name in the response (optional)
                ]);
            } catch (PDOException $e) {
                // Handle database errors
                echo json_encode(['success' => false, 'error' => 'Error fetching chatbox data: ' . htmlspecialchars($e->getMessage())]);
            }
        } else {
            // Handle invalid table name
            echo json_encode(['success' => false, 'error' => 'Invalid table name.']);
        }
    } else {
        // Handle missing party code
        echo json_encode(['success' => false, 'error' => 'Party code not provided.']);
    }
} else {
    // Handle invalid request method
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>