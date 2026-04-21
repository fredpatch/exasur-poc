<?php
/**
 * auth_module.php — REDIRECTION PROPRE
 *
 * Ce fichier n'est plus utilisé dans la nouvelle architecture FORM.
 * Chaque session de module FORM se fait via auth.php?type=5
 *
 * Cette redirection est maintenue pour compatibilité
 * si d'anciens liens pointent encore vers ce fichier.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Rediriger vers la page d'authentification FORM
header("Location: auth.php?type=5");
exit();
?>