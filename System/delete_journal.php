<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../DB/config.php'; 
$response = ['success' => false, 'message' => 'Unknown error.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get the ID of the journal entry to delete
        $data = json_decode(file_get_contents('php://input'), true);
        $journalId = $data['id'] ?? null;
        error_log("Journal ID to delete: " . $journalId);  // Debugging

        if ($journalId) {
            // Delete the journal entry from the database
            $stmt = $pdo->prepare("DELETE FROM journal WHERE id = ?");
            if ($stmt->execute([$journalId])) {
                $response['success'] = true;
                $response['message'] = 'Journal entry deleted successfully.';
            } else {
                throw new Exception('Error deleting journal entry.');
            }
        } else {
            throw new Exception('Journal ID missing.');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Error: " . $e->getMessage());  // Debugging
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
