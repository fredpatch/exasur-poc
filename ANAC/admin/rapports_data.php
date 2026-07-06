<?php
/**
 * rapports_data.php- Endpoint AJAX pour rapports.php
 * ANAC GABON- EXASUR
 *
 * Retourne TOUJOURS du JSON valide.
 * Le try/catch global empêche tout HTTP 500.
 */

/* ── Bloquer tout affichage PHP avant le JSON ── */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();                    // capture toute sortie parasite (warnings, BOM, etc.)

session_start();

header('Content-Type: application/json; charset=utf-8');

/* ── Fonction helper : sortie JSON et exit ── */
function jout($data) {
    ob_end_clean();            // vider les sorties parasites
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Auth ── */
if (!isset($_SESSION['admin_id'])) {
    jout(['status'=>'error','message'=>'Non autorisé (session expirée).']);
}

/* ── DB ── */
try {
    include '../php/db_connection.php';
} catch (Throwable $e) {
    jout(['status'=>'error','message'=>'Connexion BDD impossible : '.$e->getMessage()]);
}

/* ── Paramètres POST ── */
$date_debut  = trim($_POST['date_debut']  ?? '');
$date_fin    = trim($_POST['date_fin']    ?? '');
$type_id     = intval($_POST['type_id']   ?? 0);
$cand_id     = intval($_POST['cand_id']   ?? 0);

/* ── Validation dates ── */
if (($date_debut && !$date_fin) || (!$date_debut && $date_fin)) {
    jout(['status'=>'error','message'=>'Renseignez la date de début ET la date de fin ensemble.']);
}

/* ══════════════════════════════════════════════════════════════════
   TOUT LE RESTE dans un try/catch → plus jamais de HTTP 500
══════════════════════════════════════════════════════════════════ */
try {

/* ── Construction du WHERE sessions ── */
$where_parts = [];
$params      = [];
$types_bind  = '';

if ($date_debut && $date_fin) {
    $where_parts[] = "se.date_debut >= ?";
    $where_parts[] = "se.date_fin   <= ?";
    $params[]      = $date_debut;
    $params[]      = $date_fin;
    $types_bind   .= 'ss';
}
if ($type_id > 0) {
    $where_parts[] = "se.idtype_examen = ?";
    $params[]      = $type_id;
    $types_bind   .= 'i';
}

$where_sql = $where_parts ? implode(' AND ', $where_parts) : '1=1';

/* ── Requête sessions ── */
$sql_sess = "
    SELECT se.id_session, se.nom_session, se.type_session,
           se.idtype_examen, se.idtypeformation,
           te.code AS type_code, te.nom_fr AS type_nom,
           te.a_deux_parties,
           COUNT(DISTINCT cs.idcandidat) AS nb_candidats
    FROM session_examen se
    JOIN type_examen te ON te.idtype_examen = se.idtype_examen
    LEFT JOIN candidat_session cs ON cs.id_session = se.id_session AND cs.habilite = 1
    WHERE $where_sql
    GROUP BY se.id_session
    ORDER BY te.idtype_examen, se.type_session
";

if (empty($params)) {
    /* Pas de paramètres → query directe */
    $r = $conn->query($sql_sess);
    if ($r === false) {
        jout(['status'=>'error','message'=>'Erreur SQL sessions : '.$conn->error]);
    }
    $sessions_raw = $r->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare($sql_sess);
    if ($stmt === false) {
        jout(['status'=>'error','message'=>'Prepare sessions échoué : '.$conn->error]);
    }
    $stmt->bind_param($types_bind, ...$params);
    $stmt->execute();
    $sessions_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ── Aucune session ── */
if (empty($sessions_raw)) {
    $msg = $date_debut
        ? "Aucune session du $date_debut au $date_fin"
        : "Aucune session trouvée.";
    if ($type_id > 0) $msg .= " pour ce type d'examen";
    jout(['status'=>'empty','message'=>$msg,'total_candidats'=>0,'groupes'=>[]]);
}

/* ── Regrouper par type ── */
$groupes_sessions = [];
foreach ($sessions_raw as $s) {
    $code = $s['type_code'];
    if (!isset($groupes_sessions[$code])) {
        $groupes_sessions[$code] = [
            'code'           => $code,
            'nom'            => $s['type_nom'],
            'a_deux_parties' => (bool)$s['a_deux_parties'],
            'idtype_examen'  => $s['idtype_examen'],
            'idtypeformation'=> $s['idtypeformation'],
            'sessions'       => [],
        ];
    }
    $groupes_sessions[$code]['sessions'][] = $s;
}

/* ── Filtre candidat ── */
$cand_filter       = '';
$cand_filter_types = '';
$cand_filter_vals  = [];
if ($cand_id > 0) {
    $cand_filter        = " AND cs.idcandidat = ?";
    $cand_filter_types  = 'i';
    $cand_filter_vals[] = $cand_id;
}

/* ══════════════════════════════════════════════════════
   TRAITEMENT PAR TYPE
══════════════════════════════════════════════════════ */
$groupes_result  = [];
$total_candidats = 0;

foreach ($groupes_sessions as $code => $grp) {

    $session_ids  = array_column($grp['sessions'], 'id_session');
    $ph           = implode(',', array_fill(0, count($session_ids), '?'));
    $ids_types    = str_repeat('i', count($session_ids));

    /* ─── CAS IF ─── */
    if ($code === 'IF' && $grp['a_deux_parties']) {

        $id_theo = null;
        $id_prat = null;
        foreach ($grp['sessions'] as $s) {
            if ($s['type_session'] === 'theorie')  $id_theo = $s['id_session'];
            if ($s['type_session'] === 'pratique') $id_prat = $s['id_session'];
        }

        /* Candidats */
        $sql_c = "SELECT DISTINCT cs.idcandidat,
                         st.nomstagiaire, st.prenomstagiaire,
                         ca.code_acces,
                         o.nomorga AS orga, st.postestagiaire AS poste
                  FROM candidat_session cs
                  JOIN candidat ca ON ca.idcandidat = cs.idcandidat
                  JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
                  LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
                  WHERE cs.id_session IN ($ph) AND cs.habilite = 1 $cand_filter
                  ORDER BY st.nomstagiaire, st.prenomstagiaire";
        $bt = $ids_types.$cand_filter_types;
        $bv = array_merge($session_ids, $cand_filter_vals);
        $st = $conn->prepare($sql_c);
        $st->bind_param($bt, ...$bv);
        $st->execute();
        $cands_if = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        /* Résultats théorie */
        $res_theo = [];
        if ($id_theo) {
            $st = $conn->prepare("SELECT idcandidat,note_finale,note_sur,pourcentage,reussite_theo,reussite FROM resultats WHERE id_session=?");
            $st->bind_param('i', $id_theo);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
                $res_theo[$row['idcandidat']] = $row;
            $st->close();
        }

        /* Résultats pratique */
        $res_prat = [];
        if ($id_prat) {
            $st = $conn->prepare("SELECT idcandidat,note_finale,note_sur,pourcentage,reussite_prat,reussite,moyenne_if FROM resultats WHERE id_session=?");
            $st->bind_param('i', $id_prat);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
                $res_prat[$row['idcandidat']] = $row;
            $st->close();
        }

        /* Fusion */
        $candidats_if = [];
        foreach ($cands_if as $c) {
            $cid = $c['idcandidat'];
            $th  = $res_theo[$cid] ?? null;
            $pr  = $res_prat[$cid] ?? null;

            $pct_theo      = $th ? round((float)$th['pourcentage'], 1) : null;
            $pct_prat      = $pr ? round((float)$pr['pourcentage'], 1) : null;
            $moy_if        = ($pct_theo !== null && $pct_prat !== null)
                             ? round(($pct_theo + $pct_prat) / 2, 1) : null;
            $reussite_theo = $th ? (bool)$th['reussite_theo'] : null;
            $reussite_prat = $pr ? (bool)$pr['reussite_prat'] : null;
            $reussite_if   = ($reussite_theo && $reussite_prat) ? true : false;

            $candidats_if[] = [
                'idcandidat'    => $cid,
                'nom'           => $c['nomstagiaire'].' '.$c['prenomstagiaire'],
                'code'          => $c['code_acces'],
                'orga'          => $c['orga'] ?? '',
                'poste'         => $c['poste'] ?? '',
                'a_passe'       => (bool)($th || $pr),
                'reussite'      => $reussite_if,
                'pct_theo'      => $pct_theo,
                'note_theo'     => $th ? round((float)$th['note_finale'],1).'/'.round((float)$th['note_sur'],1).' pts' : null,
                'reussite_theo' => $reussite_theo,
                'pct_prat'      => $pct_prat,
                'note_prat'     => $pr ? round((float)$pr['note_finale'],1).'/'.round((float)$pr['note_sur'],1).' pts' : null,
                'reussite_prat' => $reussite_prat,
                'moy_if'        => $moy_if,
                'pct'           => $moy_if,
                'note'          => null,
                'moy'           => null,
                'total_pts'     => null,
                'modules'       => [],
            ];
        }

        usort($candidats_if, fn($a,$b) => ($b['moy_if']??-1) <=> ($a['moy_if']??-1));
        $seuil = 70;
        $nb_ok = count(array_filter($candidats_if, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'      => $code,
            'nom'       => $grp['nom'],
            'seuil'     => $seuil,
            'sessions'  => array_map(fn($s) => ['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']], $grp['sessions']),
            'modules'   => [],
            'candidats' => $candidats_if,
            'nb_ok'     => $nb_ok,
            'nb_ko'     => count($candidats_if) - $nb_ok,
            'nb_total'  => count($candidats_if),
            'taux'      => count($candidats_if) > 0 ? round($nb_ok/count($candidats_if)*100,1) : 0,
        ];
        $total_candidats += count($candidats_if);

    /* ─── CAS FORM ─── */
    } elseif ($code === 'FORM') {

        $idtype_form = intval($grp['idtypeformation'] ?? 0);
        $modules_list = [];

        if ($idtype_form > 0) {
            $st = $conn->prepare("SELECT idmodule,numero_module,nom_module_fr AS nom FROM module_formation WHERE idtypeformation=? ORDER BY numero_module");
            $st->bind_param('i', $idtype_form);
            $st->execute();
            $modules_list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
        }

        $module_ids = array_column($modules_list, 'idmodule');

        /* Candidats */
        $sql_c = "SELECT DISTINCT cs.idcandidat,
                         st.nomstagiaire, st.prenomstagiaire, ca.code_acces,
                         o.nomorga AS orga, st.postestagiaire AS poste
                  FROM candidat_session cs
                  JOIN candidat ca ON ca.idcandidat = cs.idcandidat
                  JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
                  LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
                  WHERE cs.id_session IN ($ph) AND cs.habilite = 1 $cand_filter
                  ORDER BY st.nomstagiaire, st.prenomstagiaire";
        $bt = $ids_types.$cand_filter_types;
        $bv = array_merge($session_ids, $cand_filter_vals);
        $st = $conn->prepare($sql_c);
        $st->bind_param($bt, ...$bv);
        $st->execute();
        $cands_form = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        /* Notes par module */
        $notes_module = [];
        if (!empty($module_ids)) {
            $ph_mod = implode(',', array_fill(0, count($module_ids), '?'));
            $sql_ev = "SELECT em.idcandidat, em.idmodule,
                              em.note_obtenue, em.note_sur, em.pourcentage, em.reussite
                       FROM evaluation_module em
                       WHERE em.id_session IN ($ph) AND em.idmodule IN ($ph_mod)";
            $bt2 = $ids_types.str_repeat('i', count($module_ids));
            $bv2 = array_merge($session_ids, $module_ids);
            $st  = $conn->prepare($sql_ev);
            if ($st) {
                $st->bind_param($bt2, ...$bv2);
                $st->execute();
                foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                    $notes_module[$row['idcandidat']][$row['idmodule']] = [
                        'note'    => round((float)$row['note_obtenue'],1).'/'.round((float)$row['note_sur'],1).' pts',
                        'pct'     => round((float)$row['pourcentage'],1),
                        'reussite'=> (bool)$row['reussite'],
                    ];
                }
                $st->close();
            }
        }

        /* Assembler */
        $candidats_form = [];
        foreach ($cands_form as $c) {
            $cid  = $c['idcandidat'];
            $mods = $notes_module[$cid] ?? [];
            $pcts = array_column($mods, 'pct');
            $moy  = $pcts ? round(array_sum($pcts)/count($pcts), 1) : null;
            $all_ok = $pcts && count($pcts) === count($module_ids)
                      && count(array_filter($mods, fn($m) => $m['reussite'])) === count($module_ids);
            $candidats_form[] = [
                'idcandidat' => $cid,
                'nom'        => $c['nomstagiaire'].' '.$c['prenomstagiaire'],
                'code'       => $c['code_acces'],
                'orga'       => $c['orga'] ?? '',
                'poste'      => $c['poste'] ?? '',
                'a_passe'    => !empty($mods),
                'reussite'   => $all_ok,
                'pct'        => $moy,
                'moy'        => $moy,
                'note'       => null,
                'total_pts'  => null,
                'modules'    => $mods,
                'pct_theo'   => null,'note_theo'=>null,'reussite_theo'=>null,
                'pct_prat'   => null,'note_prat'=>null,'reussite_prat'=>null,'moy_if'=>null,
            ];
        }

        usort($candidats_form, fn($a,$b) => ($b['moy']??-1) <=> ($a['moy']??-1));
        $seuil = 70;
        $nb_ok = count(array_filter($candidats_form, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'      => $code,
            'nom'       => $grp['nom'],
            'seuil'     => $seuil,
            'sessions'  => array_map(fn($s)=>['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']],$grp['sessions']),
            'modules'   => array_map(fn($m)=>['idmodule'=>$m['idmodule'],'num'=>$m['numero_module'],'nom'=>$m['nom']],$modules_list),
            'candidats' => $candidats_form,
            'nb_ok'     => $nb_ok,
            'nb_ko'     => count($candidats_form)-$nb_ok,
            'nb_total'  => count($candidats_form),
            'taux'      => count($candidats_form)>0?round($nb_ok/count($candidats_form)*100,1):0,
        ];
        $total_candidats += count($candidats_form);

    /* ─── CAS STANDARD : AS / INST / SENS ─── */
    } else {

        $sql_std = "
            SELECT cs.idcandidat,
                   st.nomstagiaire, st.prenomstagiaire, ca.code_acces,
                   o.nomorga AS orga, st.postestagiaire AS poste,
                   r.id AS rid, r.note_finale, r.note_sur, r.pourcentage, r.reussite
            FROM candidat_session cs
            JOIN candidat ca ON ca.idcandidat = cs.idcandidat
            JOIN si_anac.stagiaire st ON st.idstagiaire = ca.idstagiaire
            LEFT JOIN si_anac.organisme o ON o.idorga = st.idorga
            LEFT JOIN resultats r ON r.idcandidat = cs.idcandidat
                AND r.id_session = cs.id_session
            WHERE cs.id_session IN ($ph) AND cs.habilite = 1 $cand_filter
            ORDER BY st.nomstagiaire, st.prenomstagiaire
        ";
        $bt = $ids_types.$cand_filter_types;
        $bv = array_merge($session_ids, $cand_filter_vals);
        $st = $conn->prepare($sql_std);
        if ($st === false) {
            /* SQL échoué → passer ce groupe */
            continue;
        }
        $st->bind_param($bt, ...$bv);
        $st->execute();
        $rows_std = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        /* Dédoublonner par candidat */
        $map = [];
        foreach ($rows_std as $row) {
            $cid = $row['idcandidat'];
            if (!isset($map[$cid]) ||
                ((float)$row['pourcentage'] > (float)($map[$cid]['pourcentage']??-1))) {
                $map[$cid] = $row;
            }
        }

        $candidats_std = [];
        foreach ($map as $cid => $row) {
            $pct = $row['rid'] ? round((float)$row['pourcentage'],1) : null;
            $candidats_std[] = [
                'idcandidat'    => $cid,
                'nom'           => $row['nomstagiaire'].' '.$row['prenomstagiaire'],
                'code'          => $row['code_acces'],
                'orga'          => $row['orga'] ?? '',
                'poste'         => $row['poste'] ?? '',
                'a_passe'       => (bool)$row['rid'],
                'reussite'      => $row['rid'] ? (bool)$row['reussite'] : false,
                'pct'           => $pct,
                'note'          => $row['rid'] ? round((float)$row['note_finale'],1).'/'.round((float)$row['note_sur'],1).' pts' : null,
                'moy'           => $pct,
                'total_pts'     => null,
                'modules'       => [],
                'pct_theo'      => null,'note_theo'=>null,'reussite_theo'=>null,
                'pct_prat'      => null,'note_prat'=>null,'reussite_prat'=>null,'moy_if'=>null,
            ];
        }

        usort($candidats_std, fn($a,$b) => ($b['pct']??-1) <=> ($a['pct']??-1));
        $seuil = 70;
        $nb_ok = count(array_filter($candidats_std, fn($x) => $x['reussite']));

        $groupes_result[] = [
            'code'      => $code,
            'nom'       => $grp['nom'],
            'seuil'     => $seuil,
            'sessions'  => array_map(fn($s)=>['id'=>$s['id_session'],'nom'=>$s['nom_session'],'type'=>$s['type_session']],$grp['sessions']),
            'modules'   => [],
            'candidats' => $candidats_std,
            'nb_ok'     => $nb_ok,
            'nb_ko'     => count($candidats_std)-$nb_ok,
            'nb_total'  => count($candidats_std),
            'taux'      => count($candidats_std)>0?round($nb_ok/count($candidats_std)*100,1):0,
        ];
        $total_candidats += count($candidats_std);
    }
}

/* ── Réponse finale ── */
jout([
    'status'          => 'success',
    'date_debut'      => $date_debut,
    'date_fin'        => $date_fin,
    'total_candidats' => $total_candidats,
    'nb_groupes'      => count($groupes_result),
    'groupes'         => $groupes_result,
]);

} catch (Throwable $e) {
    /* Capture toute erreur fatale → JSON au lieu de HTTP 500 */
    jout([
        'status'  => 'error',
        'message' => 'Erreur serveur : '.$e->getMessage().' (ligne '.$e->getLine().')',
    ]);
}