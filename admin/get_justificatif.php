<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID manquant']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT j.*, 
               u.nom as etudiant_nom, u.prenom as etudiant_prenom,
               c.nom as classe_nom, c.niveau as classe_niveau
        FROM justificatifs j
        JOIN users u ON j.etudiant_id = u.id
        JOIN inscriptions i ON u.id = i.etudiant_id
        JOIN classes c ON i.classe_id = c.id
        WHERE j.id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $justificatif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$justificatif) {
        echo json_encode(['error' => 'Justificatif non trouvé']);
        exit();
    }

    echo json_encode($justificatif);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des données']);
}
