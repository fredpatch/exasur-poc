<?php
/**
 * rapports_data.php — Endpoint AJAX pour rapports.php (Centre d'impression croisé)
 * ANAC GABON — AIR SECURE
 *
 * POST params:
 *   date_debut  string  (obligatoire)  ex: 2026-04-20
 *   date_fin    string  (obligatoire)  ex: 2026-05-24
 *   type_id     int     (optionnel)    filtrer par type examen
 *   cand_id     int     (optionnel)    filtrer par candidat
 *   session_nom string  (optionnel)    filtrer par nom session (LIKE)
 *
 * Logique de filtrage : EXACT sur date_debut = ? AND date_fin = ?
 * (jamais d'intervalles — une session xx/yy est différente de xx/zz)
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Non autorisé']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
include '../php/db_connection.php';

/* ── Paramètres ──────────────────────────────────────────────────── */
$date_debut  = trim($_POST['date_debut']  ?? '');
$date_fin    = trim($_POST['date_fin']    ?? '');
$type_id     = intval($_POST['type_id']   ?? 0);
$cand_id     = intval($_POST['cand_id']   ?? 0);
$session_nom = trim($_POST['session_nom'] ?? '');

if (!$date_debut || !$date_fin) {
    echo json_encode(['status'=>'error','message'=>'Veuillez renseigner la date de début et de fin de session.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   ÉTAPE 1 — Trouver les sessions concernées (filtre EXACT)
   ══════════════════════════════════════════════════════════════════ */
$where_sess  = "se.date_debut = ? AND se.date_fin = ?";
$params      = [$date_debut, $date_fin];
$types_bind  = "ss";

if ($type_id > 0) {
    $where_sess .= " AND se.idtype_examen = ?";
    $params[]    = $type_id;
    $types_bind .= "i";
}
if ($session_nom !== '') {
    $where_sess .= " AND se.nom_session LIKE ?";
    $params[]    = '%' . $session_nom . '%';
    $types_bind .= "s";
}

$sql_sess = "
    SELECT se.id_session, se.nom_session, se.type_session,
           se.idtype_examen, se.idtypeformation,
           te.code AS type_code, te.nom_fr AS type_nom,
           te.a_deux_parties,
           COUNT(DISTINCT cs.idcandidat) AS nb_candidats
    FROM session_examen se
    JOIN type_examen te ON te.idtype_examen = se.idtype_examen
    LEFT JOIN candidat_session cs ON cs.id_session = se.id_session AND cs.habilite = 1
    WHERE $where_sess
    GROUP BY se.id_session
    ORDER BY te.idtype_examen, se.type_session
";

$stmt = $conn->prepare($sql_sess);
$stmt->bind_param($types_bind, ...$params);
$stmt->execute();
$sessions_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($sessions_raw)) {
    echo json_encode([
        'status'           => 'empty',
        'message'          => "Aucune session trouvée pour la période du $date_debut au $date_fin.",
        'total_candidats'  => 0,
        'groupes'          => []
    ]);
    exit;
}

/* ── Regrouper les sessions par type d'examen ────────────────────── */
$groupes_sessions = [];  // type_code => [sessions]
foreach ($sessions_raw as $s) {
    $code = $s['type_code'];
    if (!isset($groupes_sessions[$code])) {
        $groupes_sessions[$code] = [
            'code'           => $code,
            'nom'            => $s['type_nom'],
            'a_deux_parties' => (bool)$s['a_deux_parties'],
            'idtype_examen'  => $s['idtype_examen'],
            'idtypeformation'=> $s['idtypeformation'],
            'sessions'       => []
        ];
    }
    $groupes_sessions[$code]['sessions'][] = $s;
}

/* ══════════════════════════════════════════════════════════════════
   ÉTAPE 2 — Pour chaque groupe, charger les résultats des candidats
   ══════════════════════════════════════════════════════════════════ */
$groupes_result  = [];
$total_candidats = 0;

foreach ($groupes_sessions as $code => $grp) {

    $session_ids = array_column($grp['sessions'], 'id_session');
    $placeholders = implode(',', array_fill(0, count($session_ids), '?'));

    /* ── Filtre optionnel candidat ── */
    $cand_filter       = '';
    $cand_filter_types = '';
    $cand_filter_val   = [];
    if ($cand_id > 0) {
        $cand_filter        = " AND cs.idcandidat = ?";
        $cand_filter_types  = "i";
        $cand_filter_val[]  = $cand_id;
    }

    /* ─────────────────────────────────────────────────────────────
       CAS IF — deux parties théorie + pratique
       ───────────────────────────────────────────────────────────── */
    if ($code === 'IF' && $grp['a_deux_parties']) {

        /* IDs sessions théorie et pratique */
        $id_theo = null; $id_prat = null;
        foreach ($grp['sessions'] as $s) {
            if ($s['type_session'] === 'theorique') $id_theo = $s['id_session'];
            if ($s['type_session'] === 'pratique')  $id_prat = $s['id_session'];
        }

        /* Liste des candidats habilités (union des deux sessions) */
        $sql_cands = "
            SELECT DISTINCT cs.idcandidat,
                   st.nomstagiaire, st.prenomstagiaire,
                   ca.code_acces,
                   o.trigrorganisme AS orga, st.fonction AS poste
            FROM candidat_session cs
            JOIN candidat ca ON ca.idcandidat = cs.idcandidat
            JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
            LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
            WHERE cs.id_session IN ($placeholders) AND cs.habilite = 1
            $cand_filter
            ORDER BY st.nomstagiaire, st.prenomstagiaire
        ";
        $bind_types = str_repeat('i', count($session_ids)) . $cand_filter_types;
        $bind_vals  = array_merge($session_ids, $cand_filter_val);

        $stmt = $conn->prepare($sql_cands);
        $stmt->bind_param($bind_types, ...$bind_vals);
        $stmt->execute();
        $cands_if = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        /* Résultats théorie */
        $res_theo = [];
        if ($id_theo) {
            $st = $conn->prepare("
                SELECT r.idcandidat, r.note_finale, r.note_sur, r.pourcentage, r.reussite_theo, r.reussite
                FROM resultats r WHERE r.id_session = ?
            ");
            $st->bind_param('i', $id_theo);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
                $res_theo[$row['idcandidat']] = $row;
            $st->close();
        }

        /* Résultats pratique */
        $res_prat = [];
        if ($id_prat) {
            $st = $conn->prepare("
                SELECT r.idcandidat, r.note_finale, r.note_sur, r.pourcentage, r.reussite_prat, r.reussite, r.moyenne_if
                FROM resultats r WHERE r.id_session = ?
            ");
            $st->bind_param('i', $id_prat);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
                $res_prat[$row['idcandidat']] = $row;
            $st->close();
        }

        /* Construire liste candidats IF avec données fusionnées */
        $candidats_if = [];
        foreach ($cands_if as $c) {
            $cid  = $c['idcandidat'];
            $th   = $res_theo[$cid] ?? null;
            $pr   = $res_prat[$cid] ?? null;

            // Seuil IF = 80%
            $pct_theo = $th ? round($th['pourcentage'], 1) : null;
            $pct_prat = $pr ? round($pr['pourcentage'], 1) : null;
            $moy_if   = ($pct_theo !== null && $pct_prat !== null)
                        ? round(($pct_theo + $pct_prat) / 2, 1) : null;

            $reussite_theo = $th ? (bool)$th['reussite_theo'] : null;
            $reussite_prat = $pr ? (bool)$pr['reussite_prat'] : null;
            $reussite_if   = ($reussite_theo && $reussite_prat);

            $note_theo = $th ? round($th['note_finale'],1).'/'.round($th['note_sur'],1).' pts' : null;
            $note_prat = $pr ? round($pr['note_finale'],1).'/'.round($pr['note_sur'],1).' pts' : null;

            $candidats_if[] = [
                'idcandidat'    => $cid,
                'nom'           => $c['nomstagiaire'].' '.$c['prenomstagiaire'],
                'code'          => $c['code_acces'],
                'orga'          => $c['orga'] ?? '',
                'poste'         => $c['poste'] ?? '',
                'a_passe'       => ($th || $pr),
                'reussite'      => $reussite_if,
                'pct_theo'      => $pct_theo,
                'note_theo'     => $note_theo,
                'reussite_theo' => $reussite_theo,
                'pct_prat'      => $pct_prat,
                'note_prat'     => $note_prat,
                'reussite_prat' => $reussite_prat,
                'moy_if'        => $moy_if,
                'pct'           => $moy_if,
                'note'          => null,
                'moy'           => null,
                'total_pts'     => null,
                'modules'       => [],
            ];
        }

        // Tri par moy_if DESC
        usort($candidats_if, fn($a,$b) => ($b['moy_if']??-1) <=> ($a['moy_if']??-1));

        $seuil = 80;
        $nb_ok = count(array_filter($candidats_if, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'       => $code,
            'nom'        => $grp['nom'],
            'seuil'      => $seuil,
            'sessions'   => array_map(fn($s) => ['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']], $grp['sessions']),
            'modules'    => [],
            'candidats'  => $candidats_if,
            'nb_ok'      => $nb_ok,
            'nb_ko'      => count($candidats_if) - $nb_ok,
            'nb_total'   => count($candidats_if),
            'taux'       => count($candidats_if) > 0 ? round($nb_ok/count($candidats_if)*100,1) : 0,
        ];
        $total_candidats += count($candidats_if);

    /* ─────────────────────────────────────────────────────────────
       CAS FORM — formation par modules
       ───────────────────────────────────────────────────────────── */
    } elseif ($code === 'FORM') {

        $idtype_form = $grp['idtypeformation'];

        /* Liste des modules pour cette formation */
        $sql_mod = "
            SELECT mf.idmodule, mf.numero_module, mf.nom_module_fr AS nom
            FROM module_formation mf
            WHERE mf.idtypeformation = ?
            ORDER BY mf.numero_module
        ";
        $st = $conn->prepare($sql_mod);
        $st->bind_param('i', $idtype_form);
        $st->execute();
        $modules_list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        $module_ids = array_column($modules_list, 'idmodule');

        /* Candidats habilités */
        $sql_cands = "
            SELECT DISTINCT cs.idcandidat,
                   st.nomstagiaire, st.prenomstagiaire, ca.code_acces,
                   o.trigrorganisme AS orga, st.fonction AS poste
            FROM candidat_session cs
            JOIN candidat ca ON ca.idcandidat = cs.idcandidat
            JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
            LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
            WHERE cs.id_session IN ($placeholders) AND cs.habilite = 1
            $cand_filter
            ORDER BY st.nomstagiaire, st.prenomstagiaire
        ";
        $bind_types = str_repeat('i', count($session_ids)) . $cand_filter_types;
        $bind_vals  = array_merge($session_ids, $cand_filter_val);
        $stmt = $conn->prepare($sql_cands);
        $stmt->bind_param($bind_types, ...$bind_vals);
        $stmt->execute();
        $cands_form = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        /* Notes par module pour chaque candidat */
        $notes_module = [];  // [idcandidat][idmodule] = {note, pct, reussite}
        if (!empty($module_ids)) {
            $ph_mod = implode(',', array_fill(0, count($module_ids), '?'));
            $ph_ses = $placeholders;
            $sql_eval = "
                SELECT em.idcandidat, em.idmodule,
                       em.note_obtenue, em.note_sur, em.pourcentage, em.reussite
                FROM evaluation_module em
                WHERE em.id_session IN ($ph_ses)
                  AND em.idmodule IN ($ph_mod)
            ";
            $bt = str_repeat('i', count($session_ids)) . str_repeat('i', count($module_ids));
            $bv = array_merge($session_ids, $module_ids);
            $st = $conn->prepare($sql_eval);
            $st->bind_param($bt, ...$bv);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $notes_module[$row['idcandidat']][$row['idmodule']] = [
                    'note'     => round($row['note_obtenue'],1).'/'.round($row['note_sur'],1),
                    'pct'      => round($row['pourcentage'],1),
                    'reussite' => (bool)$row['reussite'],
                ];
            }
        }

        /* Résultat global (resultats table) */
        $res_glob = [];
        $st = $conn->prepare("
            SELECT r.idcandidat, r.note_finale, r.note_sur, r.pourcentage,
                   r.reussite, r.moyenne_if
            FROM resultats r WHERE r.id_session IN ($placeholders)
        ");
        $st->bind_param(str_repeat('i',count($session_ids)), ...$session_ids);
        $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
            $res_glob[$row['idcandidat']] = $row;
        $st->close();

        $candidats_form = [];
        foreach ($cands_form as $c) {
            $cid     = $c['idcandidat'];
            $glob    = $res_glob[$cid] ?? null;
            $mods_c  = $notes_module[$cid] ?? [];

            // Total points et % global
            $tot_note = $glob ? round($glob['note_finale'],1).'/'.round($glob['note_sur'],1).' pts' : null;
            $tot_pct  = $glob ? round($glob['pourcentage'],1) : null;
            $reussite = $glob ? (bool)$glob['reussite'] : false;

            $candidats_form[] = [
                'idcandidat' => $cid,
                'nom'        => $c['nomstagiaire'].' '.$c['prenomstagiaire'],
                'code'       => $c['code_acces'],
                'orga'       => $c['orga'] ?? '',
                'poste'      => $c['poste'] ?? '',
                'a_passe'    => (bool)$glob,
                'reussite'   => $reussite,
                'pct'        => $tot_pct,
                'note'       => $tot_note,
                'pct_theo'   => null,
                'note_theo'  => null,
                'reussite_theo' => null,
                'pct_prat'   => null,
                'note_prat'  => null,
                'reussite_prat' => null,
                'moy_if'     => null,
                'moy'        => $tot_pct,
                'total_pts'  => $tot_note,
                'modules'    => $mods_c,
            ];
        }

        // Tri par % desc
        usort($candidats_form, fn($a,$b) => ($b['pct']??-1) <=> ($a['pct']??-1));

        $seuil = 70;
        $nb_ok = count(array_filter($candidats_form, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'       => $code,
            'nom'        => $grp['nom'],
            'seuil'      => $seuil,
            'sessions'   => array_map(fn($s) => ['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']], $grp['sessions']),
            'modules'    => $modules_list,
            'candidats'  => $candidats_form,
            'nb_ok'      => $nb_ok,
            'nb_ko'      => count($candidats_form) - $nb_ok,
            'nb_total'   => count($candidats_form),
            'taux'       => count($candidats_form) > 0 ? round($nb_ok/count($candidats_form)*100,1) : 0,
        ];
        $total_candidats += count($candidats_form);

    /* ─────────────────────────────────────────────────────────────
       CAS STANDARD — AS / INST / SENS (théorique uniquement)
       ───────────────────────────────────────────────────────────── */
    } else {

        /* Candidats + résultats en une seule requête */
        $sql_std = "
            SELECT cs.idcandidat,
                   st.nomstagiaire, st.prenomstagiaire, ca.code_acces,
                   o.trigrorganisme AS orga, st.fonction AS poste,
                   r.id AS rid, r.note_finale, r.note_sur, r.pourcentage, r.reussite,
                   se.id_session, se.nom_session
            FROM candidat_session cs
            JOIN candidat ca ON ca.idcandidat = cs.idcandidat
            JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
            LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
            LEFT JOIN resultats r ON r.idcandidat = cs.idcandidat
                AND r.id_session IN ($placeholders)
            JOIN session_examen se ON cs.id_session = se.id_session
            WHERE cs.id_session IN ($placeholders) AND cs.habilite = 1
            $cand_filter
            ORDER BY st.nomstagiaire, st.prenomstagiaire
        ";
        $bind_types = str_repeat('i', count($session_ids)*2) . $cand_filter_types;
        $bind_vals  = array_merge($session_ids, $session_ids, $cand_filter_val);
        $stmt = $conn->prepare($sql_std);
        $stmt->bind_param($bind_types, ...$bind_vals);
        $stmt->execute();
        $rows_std = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Dédoublonner par candidat (prendre le meilleur résultat si doublon)
        $map_cand = [];
        foreach ($rows_std as $row) {
            $cid = $row['idcandidat'];
            if (!isset($map_cand[$cid]) ||
                ($row['pourcentage'] > ($map_cand[$cid]['pct'] ?? -1))) {
                $map_cand[$cid] = $row;
            }
        }

        $candidats_std = [];
        foreach ($map_cand as $cid => $row) {
            $pct = $row['rid'] ? round($row['pourcentage'],1) : null;
            $candidats_std[] = [
                'idcandidat'   => $cid,
                'nom'          => $row['nomstagiaire'].' '.$row['prenomstagiaire'],
                'code'         => $row['code_acces'],
                'orga'         => $row['orga'] ?? '',
                'poste'        => $row['poste'] ?? '',
                'a_passe'      => (bool)$row['rid'],
                'reussite'     => $row['rid'] ? (bool)$row['reussite'] : false,
                'pct'          => $pct,
                'note'         => $row['rid']
                                  ? round($row['note_finale'],1).'/'.round($row['note_sur'],1).' pts'
                                  : null,
                'pct_theo'     => null,
                'note_theo'    => null,
                'reussite_theo'=> null,
                'pct_prat'     => null,
                'note_prat'    => null,
                'reussite_prat'=> null,
                'moy_if'       => null,
                'moy'          => $pct,
                'total_pts'    => null,
                'modules'      => [],
            ];
        }

        // Tri par % desc
        usort($candidats_std, fn($a,$b) => ($b['pct']??-1) <=> ($a['pct']??-1));

        $seuil = 80; // seuil défaut pour AS/INST/SENS
        $nb_ok = count(array_filter($candidats_std, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'       => $code,
            'nom'        => $grp['nom'],
            'seuil'      => $seuil,
            'sessions'   => array_map(fn($s) => ['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']], $grp['sessions']),
            'modules'    => [],
            'candidats'  => $candidats_std,
            'nb_ok'      => $nb_ok,
            'nb_ko'      => count($candidats_std) - $nb_ok,
            'nb_total'   => count($candidats_std),
            'taux'       => count($candidats_std) > 0 ? round($nb_ok/count($candidats_std)*100,1) : 0,
        ];
        $total_candidats += count($candidats_std);
    }
}

/* ── Réponse finale ─────────────────────────────────────────────── */
echo json_encode([
    'status'          => 'success',
    'date_debut'      => $date_debut,
    'date_fin'        => $date_fin,
    'total_candidats' => $total_candidats,
    'nb_groupes'      => count($groupes_result),
    'groupes'         => $groupes_result,
], JSON_UNESCAPED_UNICODE);

$conn->close();