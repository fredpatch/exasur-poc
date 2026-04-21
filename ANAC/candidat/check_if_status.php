<?php
/**
 * check_if_status.php
 * Endpoint AJAX pour auth.php — détection automatique de l'étape IF.
 * CORRECTION : ajout d'une gestion d'erreur propre et arrêt du spinner côté client.
 *
 * GET ?code=XXXXX
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_start();
include '../php/db_connection.php';

$code = isset($_GET['code']) ? intval($_GET['code']) : 0;
if (!$code) {
    echo json_encode(['found' => false]);
    exit();
}

// ── 1. Trouver le candidat ────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT c.idcandidat, s.nomstagiaire, s.prenomstagiaire
     FROM candidat c
     JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
     WHERE c.code_acces = ? AND c.bloque = 0"
);
$stmt->bind_param("i", $code);
$stmt->execute();
$cand = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cand) {
    echo json_encode(['found' => false]);
    $conn->close();
    exit();
}

$idcandidat = $cand['idcandidat'];
$nom        = trim($cand['nomstagiaire'] . ' ' . $cand['prenomstagiaire']);

// ── 2. Résultat théorie IF ────────────────────────────────────────────────────
$stmt_theo = $conn->prepare(
    "SELECT r.pourcentage, r.reussite
     FROM resultats r
     JOIN session_examen se ON r.id_session = se.id_session
     WHERE r.idcandidat = ? AND se.idtype_examen = 2 AND se.type_session = 'theorie'
     ORDER BY r.date_fin DESC LIMIT 1"
);
$stmt_theo->bind_param("i", $idcandidat);
$stmt_theo->execute();
$res_theo = $stmt_theo->get_result()->fetch_assoc();
$stmt_theo->close();

// ── 3. Résultat pratique IF ───────────────────────────────────────────────────
$stmt_prat = $conn->prepare(
    "SELECT r.pourcentage, r.reussite
     FROM resultats r
     JOIN session_examen se ON r.id_session = se.id_session
     WHERE r.idcandidat = ? AND se.idtype_examen = 2 AND se.type_session = 'pratique'
     ORDER BY r.date_fin DESC LIMIT 1"
);
$stmt_prat->bind_param("i", $idcandidat);
$stmt_prat->execute();
$res_prat = $stmt_prat->get_result()->fetch_assoc();
$stmt_prat->close();

$conn->close();

echo json_encode([
    'found'          => true,
    'nom'            => $nom,
    'theorie_faite'  => ($res_theo !== null),
    'pratique_faite' => ($res_prat !== null),
    'note_theorie'   => $res_theo ? round($res_theo['pourcentage'], 1) : null,
    'note_pratique'  => $res_prat ? round($res_prat['pourcentage'], 1) : null,
    'reussite_theo'  => $res_theo ? (bool)$res_theo['reussite']        : null,
    'reussite_prat'  => $res_prat ? (bool)$res_prat['reussite']        : null,
]);
?>