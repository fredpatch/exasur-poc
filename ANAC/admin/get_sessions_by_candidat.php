<?php
/**
 * get_sessions_by_candidat.php — Sessions d'un candidat
 * CORRECTION BUG : cherche dans candidat_session ET resultats
 * (un candidat peut avoir passé un examen sans être dans candidat_session)
 */
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode([]); exit(); }
include '../php/db_connection.php';

$idcandidat = intval($_POST['idcandidat'] ?? 0);
if (!$idcandidat) { echo json_encode([]); exit(); }

/* UNION des deux sources pour ne rater aucune session */
$r = $conn->query("
    SELECT DISTINCT se.id_session, se.nom_session, se.statut,
           te.code AS tc, se.date_debut, se.date_fin, se.type_session
    FROM (
        SELECT id_session FROM candidat_session WHERE idcandidat=$idcandidat AND habilite=1
        UNION
        SELECT id_session FROM resultats WHERE idcandidat=$idcandidat
    ) AS ids
    JOIN session_examen se ON se.id_session = ids.id_session
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    ORDER BY se.date_debut DESC
");

$res = [];
while ($s = $r->fetch_assoc()) {
    $label = $s['nom_session'];
    if ($s['type_session'] !== 'normal') $label .= ' ['.ucfirst($s['type_session']).']';
    $label .= '  ·  '.$s['tc'];
    if ($s['date_debut']) $label .= '  ·  '.date('d/m/Y', strtotime($s['date_debut']));
    $res[] = ['id_session' => $s['id_session'], 'nom_session' => $label];
}
header('Content-Type: application/json');
echo json_encode($res);
$conn->close();