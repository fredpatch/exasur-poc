<?php
/**
 * check_perm.php — Vérification des permissions par module
 * EXASUR / ANAC / admin / check_perm.php
 *
 * USAGE (inclure en haut de chaque page admin) :
 *   $required_module = 'resultats';  // nom du module
 *   include 'check_perm.php';
 *
 * LOGIQUE :
 *   - superadmin → accès total, aucune restriction
 *   - admin      → vérifie admin_permissions.peut_voir = 1 pour ce module
 *   - Si refusé  → redirige vers dashboard.php avec message d'erreur
 */
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); exit();
}

/* Superadmin : accès total sans vérification BDD */
if (($_SESSION['admin_role'] ?? '') === 'superadmin') {
    return; // OK, continuer
}

/* Admin : vérifier la permission sur le module requis */
if (!isset($required_module)) {
    return; // pas de module requis = pas de restriction
}

/* Connexion si pas déjà ouverte */
if (!isset($conn) || !$conn) {
    include __DIR__ . '/../php/db_connection.php';
}

$stmt = $conn->prepare(
    "SELECT peut_voir FROM admin_permissions
     WHERE idadmin = ? AND module = ? AND peut_voir = 1
     LIMIT 1"
);
$stmt->bind_param('is', $_SESSION['admin_id'], $required_module);
$stmt->execute();
$allowed = ($stmt->get_result()->num_rows > 0);
$stmt->close();

if (!$allowed) {
    /* Stocker le message d'erreur pour l'afficher sur le dashboard */
    $_SESSION['perm_error'] = "Accès refusé : vous n'êtes pas autorisé(e) à accéder au module « {$required_module} ».";
    header("Location: dashboard.php"); exit();
}