<?php
// Anti-fraude simplifié
header("Cache-Control: no-store, no-cache, must-revalidate");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['idcandidat']) && basename($_SERVER['PHP_SELF']) == 'examen.php') {
    header("Location: ../candidature/auth.php");
    exit();
}

if (isset($_SESSION['temps_debut']) && basename($_SERVER['PHP_SELF']) == 'examen.php') {
    $temps_ecoule = time() - $_SESSION['temps_debut'];
    if ($temps_ecoule > (90 * 60)) { // 90 minutes
        header("Location: ../candidature/soumettre_examen.php?timeout=1");
        exit();
    }
}
?>