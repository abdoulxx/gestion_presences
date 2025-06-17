<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

if (!isset($_GET['cours_id']) || !isset($_GET['date'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

$cours_id = $_GET['cours_id'];
$date = $_GET['date'];

try {
    // Récupérer la classe du cours
    $stmt = $conn->prepare("
        SELECT classe_id 
        FROM emploi_temps 
        WHERE id = ?
    ");
    $stmt->execute([$cours_id]);
    $classe_id = $stmt->fetchColumn();

    if (!$classe_id) {
        throw new Exception('Cours non trouvé');
    }

    // Récupérer les étudiants de la classe qui n'ont pas encore de présence marquée pour ce cours à cette date
    $stmt = $conn->prepare("
        SELECT u.id, u.nom, u.prenom
        FROM users u
        WHERE u.classe_id = ? 
        AND u.role = 'etudiant'
        AND NOT EXISTS (
            SELECT 1 
            FROM presences p 
            WHERE p.etudiant_id = u.id 
            AND p.cours_id = ? 
            AND p.date = ?
        )
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$classe_id, $cours_id, $date]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($etudiants);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
