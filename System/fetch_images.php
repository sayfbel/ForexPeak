<?php
require __DIR__ . '/../vendor/autoload.php';
include '../DB/config.php';

// Get user ID from the query
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Validate user ID
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Fetch payout/deposit journal entries with images
    $stmt = $pdo->prepare("SELECT image FROM journal WHERE id_users = ? AND trade_type = 'payoutDeposit' AND image IS NOT NULL AND image != ''");
    $stmt->execute([$userId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $imagePaths = [];

    foreach ($images as $row) {
        $filename = htmlspecialchars($row['image']);
        $filePath = "../vireficationimage/" . $filename;

        if (file_exists($filePath)) {
            $imagePaths[] = $filePath;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($imagePaths);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch images.']);
}
