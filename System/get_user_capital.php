<?php
// Inclure la configuration de la base de données
include '../DB/config.php'; 
// Définir le type de contenu en JSON
header('Content-Type: application/json');

// Démarrer la session
session_start();

// Obtenir les données envoyées via AJAX
$data = json_decode(file_get_contents('php://input'), true);

// Récupérer l'ID de l'utilisateur
$userId = $data['userId'];

if ($userId) {
    try {
        // Requête SQL pour obtenir le capital
        $sql = "SELECT users.capital 
        FROM users 
        LEFT JOIN journal ON users.id = journal.id_users
        WHERE users.id = :userId";

        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Retourner le capital au format JSON
            echo json_encode(['success' => true, 'capital' => $result['capital']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Aucun capital trouvé.']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur manquant.']);
}
