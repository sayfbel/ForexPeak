<?php 
include '../DB/config.php'; 
header('Content-Type: application/json');

$response = [];

if (isset($_GET['id_users'])) {
    $userId = $_GET['id_users'];
    
    // Utiliser la fonction DATE() pour ne récupérer que la date sans l'heure
    $query = "SELECT *, DATE(date_journal) AS date_only FROM journal WHERE id_users = :userId";
    $params = [':userId' => $userId];

    // Si une date spécifique est fournie, assure-toi qu'elle ne contient que la partie date
    if (isset($_GET['date'])) {
        $date = $_GET['date'];
        $query .= " AND DATE(date_journal) = :date";  // Comparaison uniquement sur la date
        $params[':date'] = $date;
    }

    $query .= " ORDER BY DATE(date_journal) DESC";  // Trier selon la date seulement

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $response = ['error' => $e->getMessage()];
    }
} else {
    $response = ['error' => 'No user ID provided'];
}

echo json_encode($response);
?>
