<?php
session_start();
include '../DB/config.php'; // Include your database configuration

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $partyCode = $data['partyCode'] ?? null;
    $replayDivState = $data['replayDivState'] ?? null; // Get the replaydiv state from the request

    if ($partyCode) {
        // Determine the chatbox table based on the party code
        $chatboxTable = 'community_' . $partyCode;

        try {
            // Check if the table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE :tableName");
            $stmt->bindParam(':tableName', $chatboxTable, PDO::PARAM_STR);
            $stmt->execute();
            $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tableExists) {
                // Fetch messages from the chatbox table
                $stmt = $pdo->prepare("
                    SELECT c.*, l.fullname 
                    FROM $chatboxTable c
                    JOIN login l ON c.sender_id = l.id
                    ORDER BY c.timestamp ASC
                ");
                $stmt->execute();
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Prepare the response with messages and replaydiv state
                echo json_encode([
                    'success' => true,
                    'messages' => $messages,
                    'replayDivState' => $replayDivState, // Include the replaydiv state in the response
                ]);
            } else {
                // Table does not exist
                echo json_encode([
                    'success' => false,
                    'error' => 'No party found with this code.',
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error fetching messages: ' . $e->getMessage(),
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid party code.',
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method.',
    ]);
}
?>