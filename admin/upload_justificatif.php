<?php
session_start();
require_once('../config/db.php');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if (!isset($_FILES['document'])) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier envoyé']);
    exit();
}

$file = $_FILES['document'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpName = $file['tmp_name'];
$fileError = $file['error'];

// Vérifier les erreurs
if ($fileError !== 0) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload']);
    exit();
}

// Vérifier la taille (5MB max)
if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Le fichier est trop volumineux (max 5MB)']);
    exit();
}

// Vérifier le type de fichier
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
    exit();
}

// Générer un nom de fichier unique
$newFileName = uniqid() . '.' . $fileExtension;
$uploadPath = '../uploads/justificatifs/';

// Créer le dossier s'il n'existe pas
if (!file_exists($uploadPath)) {
    mkdir($uploadPath, 0777, true);
}

// Déplacer le fichier
if (move_uploaded_file($fileTmpName, $uploadPath . $newFileName)) {
    echo json_encode(['success' => true, 'fileName' => $newFileName]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier']);
}
