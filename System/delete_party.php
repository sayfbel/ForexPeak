<?php
session_start();
include '../DB/config.php'; // Your PDO config

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $partyCode = $data['partyCode'] ?? null;

    if (!$partyCode) {
        echo json_encode(['success' => false, 'error' => 'Party code is required.']);
        exit();
    }

    // Ensure the user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not logged in.']);
        exit();
    }

    $userId = $_SESSION['user_id'];
    $tableName = "community_$partyCode";

    try {
        // 1. Delete the party from the `parties` table
        $stmt = $pdo->prepare("DELETE FROM parties WHERE code = :code AND created_by = :created_by");
        $stmt->execute([
            ':code' => $partyCode,
            ':created_by' => $userId
        ]);

        // 2. Drop the corresponding community table if it exists
        $pdo->exec("DROP TABLE IF EXISTS `$tableName`");

        echo json_encode(['success' => true, 'message' => 'Party deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
