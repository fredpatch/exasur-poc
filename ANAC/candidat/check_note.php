<?php
/**
 * check_note.php — Vérification de note publique
 * ANAC GABON — EXASUR
 * Modifications :
 *  - Code accès 4 chiffres (codeserv depuis si_anac.stagiaire)
 *  - Filtre optionnel par date début/fin de session
 *  - Mention VALIDÉ / AJOURNÉ
 *  - Retour du nom de session dans la réponse
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

/* ── Lecture des données JSON ────────────────────────────────────── */
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit();
}

/* ── Nettoyage des entrées ───────────────────────────────────────── */
$code_acces    = preg_replace('/\D/', '', $data['code'] ?? '');
$idtype_examen = intval($data['type'] ?? 0);
$date_deb      = !empty($data['date_deb']) ? $data['date_deb'] : null;
$date_fin      = !empty($data['date_fin']) ? $data['date_fin'] : null;

/* ── Validation basique ─────────────────────────────────────────── */
if (strlen($code_acces) !== 4 || $idtype_examen < 1) {
    echo json_encode(['success' => false, 'message' => __('code_incorrect_ou_categorie')]);
    exit();
}

/* ── Recherche du candidat par code_acces (4 chiffres = codeserv) ── */
$stmt = $conn->prepare("
    SELECT c.idcandidat, c.code_acces,
           s.nomstagiaire, s.prenomstagiaire
    FROM candidat c
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    WHERE c.code_acces = ?
    LIMIT 1
");
$stmt->bind_param("s", $code_acces);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => __('code_incorrect_ou_categorie')]);
    exit();
}

$candidat = $result->fetch_assoc();
$stmt->close();

/* ── Recherche du résultat avec filtres dates optionnels ─────────── */
// Construction de la clause WHERE dynamique selon les dates fournies
$where_dates = '';
$params_types = 'ii';
$params_vals  = [$candidat['idcandidat'], $idtype_examen];

if ($date_deb) {
    $where_dates    .= ' AND se.date_debut >= ?';
    $params_types   .= 's';
    $params_vals[]   = $date_deb;
}
if ($date_fin) {
    $where_dates    .= ' AND se.date_fin <= ?';
    $params_types   .= 's';
    $params_vals[]   = $date_fin;
}

$sql_res = "
    SELECT r.note_finale, r.note_sur, r.pourcentage, r.reussite, r.date_fin,
           se.nom_session, se.date_debut, se.date_fin AS sess_date_fin
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    WHERE r.idcandidat = ?
      AND se.idtype_examen = ?
      $where_dates
    ORDER BY r.date_fin DESC
    LIMIT 1
";

$stmt2 = $conn->prepare($sql_res);
$stmt2->bind_param($params_types, ...$params_vals);
$stmt2->execute();
$res2 = $stmt2->get_result();

if ($res2->num_rows === 0) {
    // Aucun résultat pour ces critères
    $msg = ($date_deb || $date_fin)
        ? 'Aucun résultat trouvé pour ces dates et ce type d\'examen.'
        : __('aucun_examen_trouve_pour_ce_type');

    echo json_encode([
        'success' => false,
        'message' => $msg,
        'nom'     => $candidat['nomstagiaire'],
        'prenom'  => $candidat['prenomstagiaire'],
    ]);
    exit();
}

$row = $res2->fetch_assoc();
$stmt2->close();

/* ── Formater la date de fin d'examen ───────────────────────────── */
$date_formattee = $row['date_fin']
    ? date('d/m/Y à H:i', strtotime($row['date_fin']))
    : '—';

/* ── Réponse JSON ────────────────────────────────────────────────── */
echo json_encode([
    'success'     => true,
    'nom'         => htmlspecialchars($candidat['nomstagiaire'],   ENT_QUOTES, 'UTF-8'),
    'prenom'      => htmlspecialchars($candidat['prenomstagiaire'],ENT_QUOTES, 'UTF-8'),
    'code'        => $candidat['code_acces'],
    'note'        => round($row['note_finale'], 1),
    'note_sur'    => round($row['note_sur'],    1),
    'pourcentage' => round($row['pourcentage'], 2),
    'reussite'    => ($row['reussite'] == 1),
    'mention'     => ($row['reussite'] == 1) ? 'VALIDÉ' : 'AJOURNÉ',
    'date'        => $date_formattee,
    'session'     => htmlspecialchars($row['nom_session'] ?? '', ENT_QUOTES, 'UTF-8'),
]);

$conn->close();
?>