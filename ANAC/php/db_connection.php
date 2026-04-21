<?php
// Connexion aux bases de données
$servername = "localhost";
$username   = "root";
$password   = "eth@n@2018"; // À modifier selon votre configuration

// Base principale du projet AIR SECURE
$dbname_secure = "quiz_app_du";
$conn = new mysqli($servername, $username, $password, $dbname_secure);
if ($conn->connect_error) {
    die("Connexion à quiz_app_du échouée : " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Base si_anac (pour stagiaire, organisme, typeformation)
$dbname_anac = "si_anac";
$conn_anac = new mysqli($servername, $username, $password, $dbname_anac);
if ($conn_anac->connect_error) {
    die("Connexion à si_anac échouée : " . $conn_anac->connect_error);
}
$conn_anac->set_charset("utf8mb4");

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>