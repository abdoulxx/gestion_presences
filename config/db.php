<?php
$host = "localhost";
$base = "gestion_presences";
$user = "root";
$pass = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$base", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>