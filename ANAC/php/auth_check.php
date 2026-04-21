<?php
// Vérification de l'authentification pour les pages protégées
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['idcandidat']) && !isset($_SESSION['idadmin'])) {
    header("Location: ../candidature/auth.php");
    exit();
}
?>