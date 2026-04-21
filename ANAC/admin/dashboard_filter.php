<?php
/**
 * dashboard_filter.php — Endpoint AJAX pour les statistiques filtrées du tableau de bord
 * ANAC GABON — AIR SECURE
 * Filtre par date_debut + date_fin EXACTES (pas d'intervalle) ou par année
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit();
}
include '../php/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

/* ── Récupération et sanitisation des paramètres ────────────────────── */
$date_debut = trim($_POST['date_debut'] ?? '');
$date_fin   = trim($_POST['date_fin']   ?? '');
$annee      = intval($_POST['annee']    ?? 0);

// Validation format date
if ($date_debut && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) $date_debut = '';
if ($date_fin   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin))   $date_fin   = '';
if ($annee < 2020 || $annee > 2099) $annee = 0;

/* ── Construction clause WHERE sessions ─────────────────────────────── */
// IMPORTANT : filtre EXACT sur date_debut ET date_fin (pas un intervalle)
// Une session du 2026-04-20 au 2026-05-24 ≠ session du 2026-04-20 au 2026-05-23
$mode = 'global'; // global | date_exacte | annee
$where_se_direct = "1=1"; // pour session_examen seul
$where_res = "1=1"; // pour les jointures avec session_examen

if ($date_debut !== '' && $date_fin !== '') {
    $mode = 'date_exacte';
    $deb_esc = $conn->real_escape_string($date_debut);
    $fin_esc = $conn->real_escape_string($date_fin);
    $where_se_direct = "se.date_debut = '$deb_esc' AND se.date_fin = '$fin_esc'";
    $where_res       = "se.date_debut = '$deb_esc' AND se.date_fin = '$fin_esc'";
} elseif ($annee > 0) {
    $mode = 'annee';
    $where_se_direct = "YEAR(se.date_debut) = $annee";
    $where_res       = "YEAR(se.date_debut) = $annee";
}

/* ── KPIs ────────────────────────────────────────────────────────────── */
$stats = [];

if ($mode === 'global') {
    $stats['candidats']   = (int)$conn->query("SELECT COUNT(*) FROM candidat")->fetch_row()[0];
    $stats['sessions']    = (int)$conn->query("SELECT COUNT(*) FROM session_examen WHERE statut='planifiee'")->fetch_row()[0];
    $stats['en_cours']    = (int)$conn->query("SELECT COUNT(*) FROM session_examen WHERE statut='en_cours'")->fetch_row()[0];
    $stats['resultats']   = (int)$conn->query("SELECT COUNT(*) FROM resultats")->fetch_row()[0];
    $stats['reussites']   = (int)$conn->query("SELECT COUNT(*) FROM resultats WHERE reussite=1")->fetch_row()[0];
    $stats['questions']   = (int)$conn->query("SELECT COUNT(*) FROM question")->fetch_row()[0];
    $stats['evaluations'] = (int)$conn->query("SELECT COUNT(*) FROM evaluations")->fetch_row()[0];
    $stats['online']      = (int)$conn->query("SELECT COUNT(*) FROM candidat WHERE is_logged_in=1")->fetch_row()[0];
} else {
    // Candidats ayant passé un examen dans ces sessions exactes
    $stats['candidats'] = (int)$conn->query("
        SELECT COUNT(DISTINCT cs.idcandidat)
        FROM candidat_session cs
        JOIN session_examen se ON cs.id_session = se.id_session
        WHERE cs.habilite = 1 AND $where_se_direct
    ")->fetch_row()[0];

    $stats['sessions'] = (int)$conn->query("
        SELECT COUNT(*) FROM session_examen se
        WHERE $where_se_direct AND se.statut = 'planifiee'
    ")->fetch_row()[0];

    $stats['en_cours'] = (int)$conn->query("
        SELECT COUNT(*) FROM session_examen se
        WHERE $where_se_direct AND se.statut = 'en_cours'
    ")->fetch_row()[0];

    $stats['resultats'] = (int)$conn->query("
        SELECT COUNT(r.id) FROM resultats r
        JOIN session_examen se ON r.id_session = se.id_session
        WHERE $where_res
    ")->fetch_row()[0];

    $stats['reussites'] = (int)$conn->query("
        SELECT COUNT(r.id) FROM resultats r
        JOIN session_examen se ON r.id_session = se.id_session
        WHERE $where_res AND r.reussite = 1
    ")->fetch_row()[0];

    $stats['questions']   = (int)$conn->query("SELECT COUNT(*) FROM question")->fetch_row()[0];
    $stats['evaluations'] = (int)$conn->query("SELECT COUNT(*) FROM evaluations")->fetch_row()[0];
    $stats['online']      = (int)$conn->query("SELECT COUNT(*) FROM candidat WHERE is_logged_in=1")->fetch_row()[0];
}

$taux_reussite = $stats['resultats'] > 0
    ? round($stats['reussites'] / $stats['resultats'] * 100, 1)
    : 0;

/* ── Graphique par type ──────────────────────────────────────────────── */
// On joint les résultats filtrés par session (date exacte ou année)
$join_graph = ($mode === 'global')
    ? "LEFT JOIN session_examen se ON se.id_session = r.id_session"
    : "JOIN session_examen se ON se.id_session = r.id_session AND ($where_res)";

$types_graph = $conn->query("
    SELECT te.code, te.nom_fr,
           COUNT(DISTINCT cs_f.idcandidat) AS nb_cand,
           SUM(CASE WHEN r.reussite = 1 THEN 1 ELSE 0 END) AS nb_ok,
           COUNT(r.id) AS nb_exam
    FROM type_examen te
    LEFT JOIN (
        SELECT r2.*, se2.date_debut, se2.date_fin
        FROM resultats r2
        JOIN session_examen se2 ON r2.id_session = se2.id_session
        " . ($mode !== 'global' ? "WHERE $where_res" : "") . "
    ) r ON r.idtype_examen = te.idtype_examen
    LEFT JOIN candidat_session cs_f ON cs_f.idcandidat = r.idcandidat AND cs_f.id_session = r.id_session AND cs_f.habilite = 1
    GROUP BY te.idtype_examen
    ORDER BY te.idtype_examen
");

$graph_labels = []; $graph_ok = []; $graph_ko = []; $type_stats_arr = [];
while ($g = $types_graph->fetch_assoc()) {
    $graph_labels[]    = $g['code'];
    $graph_ok[]        = (int)$g['nb_ok'];
    $graph_ko[]        = (int)($g['nb_exam'] - $g['nb_ok']);
    $type_stats_arr[]  = [
        'code'    => $g['code'],
        'nom_fr'  => htmlspecialchars($g['nom_fr']),
        'nb_cand' => (int)$g['nb_cand'],
        'nb_ok'   => (int)$g['nb_ok'],
        'nb_exam' => (int)$g['nb_exam'],
    ];
}

/* ── 10 derniers résultats filtrés ───────────────────────────────────── */
$recent_arr = [];
$where_last = ($mode === 'global') ? "1=1" : $where_res;
$last_res = $conn->query("
    SELECT r.*, s.nomstagiaire, s.prenomstagiaire, se.nom_session, te.code AS tc
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    JOIN candidat c        ON r.idcandidat = c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    JOIN type_examen te    ON r.idtype_examen = te.idtype_examen
    WHERE $where_last
    ORDER BY r.date_fin DESC
    LIMIT 10
");
if ($last_res) {
    while ($row = $last_res->fetch_assoc()) {
        $p = round((float)$row['pourcentage'], 1);
        $recent_arr[] = [
            'nom'     => htmlspecialchars($row['nomstagiaire'] . ' ' . $row['prenomstagiaire']),
            'type'    => $row['tc'],
            'session' => htmlspecialchars($row['nom_session']),
            'note'    => round((float)$row['note_finale'], 1) . '/' . round((float)$row['note_sur'], 1),
            'pct'     => $p,
            'reussite'=> (bool)$row['reussite'],
            'date'    => date('d/m/Y H:i', strtotime($row['date_fin'])),
        ];
    }
}

/* ── Sessions récentes filtrées ──────────────────────────────────────── */
$sessions_arr = [];
$sessions_q = $conn->query("
    SELECT se.*, te.code AS tc, te.nom_fr AS tn, COUNT(cs.id) AS nb
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    LEFT JOIN candidat_session cs ON cs.id_session = se.id_session AND cs.habilite = 1
    WHERE $where_se_direct
    GROUP BY se.id_session
    ORDER BY se.date_debut DESC
    LIMIT 5
");
if ($sessions_q) {
    while ($ss = $sessions_q->fetch_assoc()) {
        $sessions_arr[] = [
            'tc'     => $ss['tc'],
            'statut' => $ss['statut'],
            'nom'    => htmlspecialchars($ss['nom_session']),
            'debut'  => date('d/m/Y', strtotime($ss['date_debut'])),
            'fin'    => date('d/m/Y', strtotime($ss['date_fin'])),
            'nb'     => $ss['nb'],
        ];
    }
}

/* ── Réponse JSON ────────────────────────────────────────────────────── */
echo json_encode([
    'status'       => 'success',
    'mode'         => $mode,
    'stats'        => $stats,
    'taux'         => $taux_reussite,
    'graph_labels' => $graph_labels,
    'graph_ok'     => $graph_ok,
    'graph_ko'     => $graph_ko,
    'type_stats'   => $type_stats_arr,
    'recent'       => $recent_arr,
    'sessions'     => $sessions_arr,
], JSON_UNESCAPED_UNICODE);

$conn->close();