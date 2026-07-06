<?php
/**
 * resultats.php- Résultats d'examens EXASUR ANAC GABON
 *
 * NOUVELLES FONCTIONNALITÉS :
 *  - Filtre type d'examen + date début + date fin de session
 *  - Affiche TOUS les candidats inscrits : ceux ayant composé ET ceux en attente
 *  - Impression des résultats filtrés
 *  - KPIs dynamiques selon le filtre actif
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Filtres ──────────────────────────────────────────────────────── */
$f_type  = intval($_GET['f_type']  ?? 0);
$f_deb   = $conn->real_escape_string($_GET['f_deb']  ?? '');
$f_fin   = $conn->real_escape_string($_GET['f_fin']  ?? '');
$f_res   = isset($_GET['f_res'])  && $_GET['f_res']  !== '' ? intval($_GET['f_res']) : null;
$f_srch  = $conn->real_escape_string($_GET['f_srch'] ?? '');
$f_sess  = intval($_GET['f_sess'] ?? 0);
$f_ts    = $conn->real_escape_string($_GET['f_ts']   ?? ''); // theorie / pratique / normal

/* ── Filtres WHERE communs ─────────────────────────────────────────── */
$w_type  = $f_type  ? "AND se.idtype_examen = $f_type" : '';
$w_deb   = $f_deb   ? "AND se.date_debut >= '$f_deb'"  : '';
$w_fin   = $f_fin   ? "AND se.date_fin   <= '$f_fin'"   : '';
$w_sess  = $f_sess  ? "AND se.id_session  = $f_sess"   : '';
$w_ts    = $f_ts    ? "AND se.type_session = '$f_ts'"   : '';
$w_srch  = $f_srch  ? "AND (s.nomstagiaire LIKE '%$f_srch%'
                         OR s.prenomstagiaire LIKE '%$f_srch%'
                         OR c.code_acces LIKE '%$f_srch%')" : '';

/* ══════════════════════════════════════════════════════════════════
   REQUÊTE UNION- 2 sources pour ne rater aucun résultat IF

   PARTIE A : Tous les résultats existants (candidats ayant composé)
   → Source principale : table resultats
   → Attrape théorie ET pratique IF même si candidat_session
     ne contient qu'une des deux sessions (bug import ancien)

   PARTIE B : Candidats inscrits mais n'ayant pas encore composé
   → Source : candidat_session sans résultat correspondant
   → Affiche les sessions "En attente"

   UNION DISTINCT : déduplique si un candidat apparaît des deux côtés
══════════════════════════════════════════════════════════════════ */
$union_sql = "
    -- PARTIE A : résultats existants (théorie + pratique IF visibles)
    SELECT
        r.id_session,
        r.idcandidat,
        c.code_acces,
        s.nomstagiaire, s.prenomstagiaire, s.postestagiaire,
        o.nomorga,
        se.nom_session,
        se.date_debut  AS sess_debut,
        se.date_fin    AS sess_fin,
        se.type_session,
        se.statut      AS sess_statut,
        te.code        AS tc,
        te.nom_fr      AS tn,
        te.idtype_examen,
        mf.nom_module_fr, mf.numero_module,
        r.id           AS res_id,
        r.note_finale,
        r.note_sur,
        r.pourcentage,
        r.reussite,
        r.moyenne_if,
        r.locked,
        r.reason,
        r.date_fin     AS date_resultat
    FROM resultats r
    JOIN candidat c           ON r.idcandidat      = c.idcandidat
    JOIN si_anac.stagiaire s  ON c.idstagiaire     = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga      = o.idorga
    JOIN session_examen se    ON r.id_session       = se.id_session
    JOIN type_examen te       ON se.idtype_examen  = te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule   = mf.idmodule
    WHERE 1=1 $w_type $w_deb $w_fin $w_sess $w_ts $w_srch

    UNION

    -- PARTIE B : candidats inscrits sans résultat encore (En attente)
    SELECT
        cs.id_session,
        cs.idcandidat,
        c.code_acces,
        s.nomstagiaire, s.prenomstagiaire, s.postestagiaire,
        o.nomorga,
        se.nom_session,
        se.date_debut  AS sess_debut,
        se.date_fin    AS sess_fin,
        se.type_session,
        se.statut      AS sess_statut,
        te.code        AS tc,
        te.nom_fr      AS tn,
        te.idtype_examen,
        mf.nom_module_fr, mf.numero_module,
        NULL AS res_id,
        NULL AS note_finale,
        NULL AS note_sur,
        NULL AS pourcentage,
        NULL AS reussite,
        NULL AS moyenne_if,
        NULL AS locked,
        NULL AS reason,
        NULL AS date_resultat
    FROM candidat_session cs
    JOIN candidat c           ON cs.idcandidat     = c.idcandidat
    JOIN si_anac.stagiaire s  ON c.idstagiaire     = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga      = o.idorga
    JOIN session_examen se    ON cs.id_session     = se.id_session
    JOIN type_examen te       ON se.idtype_examen  = te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule   = mf.idmodule
    -- Exclure les candidats qui ont déjà un résultat pour cette session
    LEFT JOIN resultats r     ON r.idcandidat = cs.idcandidat
                             AND r.id_session = cs.id_session
    WHERE cs.habilite = 1
      AND r.id IS NULL
      $w_type $w_deb $w_fin $w_sess $w_ts $w_srch

    ORDER BY sess_debut DESC, tc, nomstagiaire, prenomstagiaire
";

$rows_res = $conn->query($union_sql);
$rows = [];
if ($rows_res) while ($row = $rows_res->fetch_assoc()) $rows[] = $row;

/* Filtre résultat côté PHP (incl. non passés) */
if ($f_res !== null) {
    if ($f_res == 2) {
        // "En attente" = pas encore de résultat
        $rows = array_filter($rows, fn($r) => $r['res_id'] === null);
    } else {
        $rows = array_filter($rows, fn($r) => $r['res_id'] !== null && intval($r['reussite']) === $f_res);
    }
    $rows = array_values($rows);
}

/* ── KPIs dynamiques ─────────────────────────────────────────────── */
$nb_total   = count($rows);
$nb_passes  = count(array_filter($rows, fn($r) => $r['res_id'] !== null));
$nb_attente = $nb_total - $nb_passes;
$nb_ok      = count(array_filter($rows, fn($r) => $r['res_id'] !== null && floatval($r['pourcentage']) >= 70));
$nb_ko      = $nb_passes - $nb_ok;
$tx         = $nb_passes > 0 ? round($nb_ok / $nb_passes * 100, 1) : 0;

/* ── Listes pour filtres ──────────────────────────────────────────── */
$types_arr = [];
$tr = $conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
while ($t = $tr->fetch_assoc()) $types_arr[] = $t;

$sessions_arr = [];
$sr = $conn->query("SELECT id_session,nom_session,date_debut,date_fin,idtype_examen
                    FROM session_examen ORDER BY date_debut DESC LIMIT 400");
while ($ss = $sr->fetch_assoc()) $sessions_arr[] = $ss;

$active_page = 'resultats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Résultats- EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* ── Badges type examen ── */
.tp{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;}
.tp-AS  {background:#dbeafe;color:#1e40af;}
.tp-IF  {background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}
.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}

/* ── Score bar ── */
.pb{height:7px;border-radius:4px;background:#f0f0f0;width:72px;display:inline-block;vertical-align:middle;}
.pf{height:100%;border-radius:4px;}

/* ── KPI cards ── */
.km{background:white;border-radius:11px;padding:14px 18px;
    box-shadow:0 2px 10px rgba(3,34,76,.07);flex:1;min-width:100px;
    border-left:3px solid transparent;cursor:pointer;transition:transform .2s;}
.km:hover{transform:translateY(-2px);}

/* ── Badges résultat ── */
.badge-resultat{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-weight:700;font-size:.76rem;}
.badge-reussi {background:#d1fae5;color:#065f46;border:1px solid #86efac;}
.badge-echec  {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.badge-attente{background:#fff8e1;color:#92400e;border:1px solid #fde68a;}

/* ── Filtre actif highlight ── */
.filter-active{border:2px solid var(--blue)!important;background:#eff6ff!important;}

/* ── Print ── */
@media print { body { display:none; } }

/* ── Ligne en attente (grisée) ── */
.row-attente td { opacity:.75; background:#fafafa!important; }
.row-attente:hover td { opacity:1; }
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<!-- ── Topbar ── -->
<div class="admin-topbar no-print">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-chart-bar me-2"></i>Résultats d'examens</div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <a href="print_results.php?<?= http_build_query(array_filter([
            'f_type' => $f_type  ?: null,
            'f_deb'  => $f_deb   ?: null,
            'f_fin'  => $f_fin   ?: null,
            'f_ts'   => $f_ts    ?: null,
            'f_sess' => $f_sess  ?: null,
            'f_srch' => $_GET['f_srch'] ?? null,
            'f_res'  => $f_res !== null ? $f_res : null,
        ])) ?>" target="_blank" class="btn-anac" style="font-size:.82rem;padding:7px 14px;">
            <i class="fas fa-print me-1"></i>Imprimer les résultats filtrés
        </a>
        <i class="fas fa-user-shield text-muted me-1"></i>
        <span style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span>
    </div>
</div>

<div class="admin-content">

<!-- ── En-tête impression ── -->
<div class="print-header">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" style="max-height:70px;margin-bottom:10px;" alt="ANAC">
    <h2>EXASUR- ANAC GABON- Résultats d'examens</h2>
    <p>
        <?php
        $label_parts = [];
        if ($f_type) {
            $tc = array_filter($types_arr, fn($t) => $t['idtype_examen'] == $f_type);
            if ($tc) $label_parts[] = 'Type : ' . reset($tc)['code'];
        }
        if ($f_deb) $label_parts[] = 'Du : ' . date('d/m/Y', strtotime($f_deb));
        if ($f_fin) $label_parts[] = 'Au : ' . date('d/m/Y', strtotime($f_fin));
        if ($f_srch) $label_parts[] = 'Recherche : ' . htmlspecialchars($f_srch);
        echo $label_parts ? implode(' | ', $label_parts) : 'Tous les résultats';
        ?>
        | Imprimé le <?= date('d/m/Y à H:i') ?>
    </p>
</div>

<!-- ── KPIs ── -->
<div class="d-flex gap-3 mb-4 flex-wrap no-print">
    <div class="km" style="border-left-color:#374151" onclick="setFRes('')">
        <div style="font-size:1.6rem;font-weight:800;color:#374151;"><?= $nb_total ?></div>
        <div style="font-size:.72rem;color:#9ca3af;font-weight:600;">INSCRITS TOTAL</div>
    </div>
    <div class="km" style="border-left-color:#0ea5e9" onclick="setFRes(2)">
        <div style="font-size:1.6rem;font-weight:800;color:#0ea5e9;"><?= $nb_attente ?></div>
        <div style="font-size:.72rem;color:#9ca3af;font-weight:600;">EN ATTENTE</div>
    </div>
    <div class="km" style="border-left-color:#16a34a" onclick="setFRes(1)">
        <div style="font-size:1.6rem;font-weight:800;color:#16a34a;"><?= $nb_ok ?></div>
        <div style="font-size:.72rem;color:#9ca3af;font-weight:600;">RÉUSSIS (≥70%)</div>
    </div>
    <div class="km" style="border-left-color:#dc2626" onclick="setFRes(0)">
        <div style="font-size:1.6rem;font-weight:800;color:#dc2626;"><?= $nb_ko ?></div>
        <div style="font-size:.72rem;color:#9ca3af;font-weight:600;">ÉCHECS (&lt;70%)</div>
    </div>
    <div class="km" style="border-left-color:#7c3aed">
        <div style="font-size:1.6rem;font-weight:800;color:#7c3aed;"><?= $tx ?>%</div>
        <div style="font-size:.72rem;color:#9ca3af;font-weight:600;">TAUX RÉUSSITE</div>
    </div>
</div>

<!-- ── Carte tableau ── -->
<div class="card-admin">
    <div class="card-admin-header no-print">
        <i class="fas fa-list me-2"></i>
        <h5>Résultats et candidats inscrits</h5>
        <span class="badge-count ms-2" id="visCount"><?= $nb_total ?></span>
        <?php if ($f_type || $f_deb || $f_fin || $f_srch || $f_sess || $f_res !== null): ?>
        <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;margin-left:8px;">
            <i class="fas fa-filter me-1"></i>Filtre actif
        </span>
        <?php endif; ?>
    </div>

    <!-- ── Barre de filtres ── -->
    <div class="filter-bar no-print"
         style="border-radius:0;box-shadow:none;border-bottom:1px solid var(--gray-border);flex-wrap:wrap;gap:8px;padding:14px 16px;">

        <!-- Type examen -->
        <div class="filter-group" style="min-width:120px;">
            <label><i class="fas fa-tag me-1" style="color:var(--gold);"></i>Type</label>
            <select class="form-select-admin" id="fType" onchange="applyServerFilter()">
                <option value="">Tous types</option>
                <?php foreach ($types_arr as $t): ?>
                <option value="<?= $t['idtype_examen'] ?>" <?= $f_type==$t['idtype_examen']?'selected':'' ?>>
                    <?= htmlspecialchars($t['code'].' - '.$t['nom_fr']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Épreuve théorie/pratique -->
        <div class="filter-group" style="min-width:130px;">
            <label><i class="fas fa-layer-group me-1" style="color:var(--gold);"></i>Épreuve</label>
            <select class="form-select-admin" id="fTs" onchange="applyServerFilter()">
                <option value="">Toutes épreuves</option>
                <option value="theorie"  <?= $f_ts==='theorie' ?'selected':'' ?>>📖 Théorie</option>
                <option value="pratique" <?= $f_ts==='pratique'?'selected':'' ?>>🖼️ Pratique</option>
                <option value="normal"   <?= $f_ts==='normal'  ?'selected':'' ?>>AS / INST / SENS</option>
            </select>
        </div>

        <!-- Date début de session -->
        <div class="filter-group" style="min-width:140px;">
            <label><i class="fas fa-calendar-plus me-1" style="color:var(--gold);"></i>Début session</label>
            <input type="date" class="form-control-admin" id="fDeb"
                   value="<?= htmlspecialchars($f_deb) ?>"
                   onchange="applyServerFilter()">
        </div>

        <!-- Date fin de session -->
        <div class="filter-group" style="min-width:140px;">
            <label><i class="fas fa-calendar-minus me-1" style="color:var(--gold);"></i>Fin session</label>
            <input type="date" class="form-control-admin" id="fFin"
                   value="<?= htmlspecialchars($f_fin) ?>"
                   onchange="applyServerFilter()">
        </div>

        <!-- Résultat -->
        <div class="filter-group" style="min-width:130px;">
            <label><i class="fas fa-medal me-1" style="color:var(--gold);"></i>Résultat</label>
            <select class="form-select-admin" id="fRes" onchange="applyServerFilter()">
                <option value="">Tous</option>
                <option value="1" <?= $f_res===1?'selected':'' ?>>✅ Réussis</option>
                <option value="0" <?= $f_res===0?'selected':'' ?>>❌ Échecs</option>
                <option value="2" <?= $f_res===2?'selected':'' ?>>⏳ En attente</option>
            </select>
        </div>

        <!-- Recherche nom/code -->
        <div class="filter-group" style="min-width:180px;">
            <label><i class="fas fa-search me-1"></i>Recherche</label>
            <input class="form-control-admin" id="fSrch"
                   placeholder="Nom, prénom, code..."
                   value="<?= htmlspecialchars($_GET['f_srch'] ?? '') ?>"
                   onkeypress="if(event.key==='Enter'){applyServerFilter();}">
        </div>

        <!-- Session spécifique -->
        <div class="filter-group" style="min-width:220px;">
            <label><i class="fas fa-calendar-check me-1" style="color:var(--gold);"></i>Session précise</label>
            <select class="form-select-admin s2sess" id="fSess" onchange="applyServerFilter()">
                <option value="">Toutes sessions</option>
                <?php foreach ($sessions_arr as $ss):
                    $lbl = $ss['nom_session'].' ('.date('d/m/Y',strtotime($ss['date_debut'])).' - '.date('d/m/Y',strtotime($ss['date_fin'])).')';
                ?>
                <option value="<?= $ss['id_session'] ?>" <?= $f_sess==$ss['id_session']?'selected':'' ?>>
                    <?= htmlspecialchars($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Effacer -->
        <div class="filter-group" style="align-self:flex-end;">
            <a href="resultats.php" class="btn-anac"
               style="background:#e8ecf5;color:var(--blue);border-color:#c8d0e0;font-size:.82rem;padding:7px 12px;text-decoration:none;">
                <i class="fas fa-times me-1"></i>Effacer
            </a>
        </div>

    </div><!-- /filter-bar -->

    <!-- ── Tableau ── -->
    <div class="table-responsive">
    <table class="table-admin" id="tblR">
        <thead>
            <tr>
                <th class="th-left">#</th>
                <th class="th-left">Candidat</th>
                <th>Code</th>
                <th>Type</th>
                <th>Session</th>
                <th>Dates session</th>
                <th>Note</th>
                <th>Score</th>
                <th>Résultat</th>
                <th class="no-print">Date examen</th>
                <th class="no-print">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php $rang = 0; foreach ($rows as $r):
            $rang++;
            $a_compose  = ($r['res_id'] !== null);
            $p          = $a_compose ? round(floatval($r['pourcentage']), 1) : null;
            $reussi     = $a_compose && ($p >= 70.0);
            $col_score  = $a_compose ? ($reussi ? '#16a34a' : '#dc2626') : '#9ca3af';
            $tc         = $r['tc'];
            $ts         = $r['type_session'] ?? 'normal';
            $row_class  = !$a_compose ? 'row-attente' : '';
        ?>
        <tr class="<?= $row_class ?>">

            <!-- # -->
            <td style="color:#9ca3af;font-size:.78rem;font-weight:700;"><?= $rang ?></td>

            <!-- Candidat -->
            <td>
                <div style="font-weight:700;font-size:.88rem;">
                    <?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?>
                </div>
                <?php if ($r['nomorga']): ?>
                <div style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($r['nomorga']) ?></div>
                <?php endif; ?>
            </td>

            <!-- Code -->
            <td>
                <span style="background:var(--blue);color:white;padding:3px 10px;border-radius:50px;font-weight:700;font-size:.78rem;">
                    <?= $r['code_acces'] ?>
                </span>
            </td>

            <!-- Type -->
            <td>
                <span class="tp tp-<?= $tc ?>"><?= $tc ?></span>
                <?php if ($ts==='theorie'): ?>
                <br><span style="font-size:.67rem;background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:20px;">📖 Théorie</span>
                <?php elseif ($ts==='pratique'): ?>
                <br><span style="font-size:.67rem;background:#fce7f3;color:#9d174d;padding:1px 6px;border-radius:20px;">🖼️ Pratique</span>
                <?php endif; ?>
            </td>

            <!-- Session -->
            <td style="font-size:.8rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($r['nom_session']) ?>
                <?php if ($r['nom_module_fr']): ?>
                <br><span style="font-size:.68rem;color:#9ca3af;">Mod.<?= $r['numero_module'] ?></span>
                <?php endif; ?>
            </td>

            <!-- Dates session -->
            <td style="font-size:.78rem;white-space:nowrap;color:#6b7280;">
                <?= date('d/m/Y', strtotime($r['sess_debut'])) ?>
                <br>→ <?= date('d/m/Y', strtotime($r['sess_fin'])) ?>
            </td>

            <!-- Note -->
            <td>
                <?php if ($a_compose): ?>
                <strong style="font-size:.92rem;"><?= round($r['note_finale'],1) ?></strong>
                <span style="color:#9ca3af;font-size:.76rem;">/ <?= round($r['note_sur'],1) ?>pts</span>
                <?php else: ?>
                <span style="color:#9ca3af;font-size:.78rem;">—</span>
                <?php endif; ?>
            </td>

            <!-- Score -->
            <td>
                <?php if ($a_compose): ?>
                <div style="display:flex;align-items:center;gap:5px;">
                    <div class="pb"><div class="pf" style="width:<?= min($p,100) ?>%;background:<?= $col_score ?>;"></div></div>
                    <strong style="color:<?= $col_score ?>;font-size:.86rem;"><?= $p ?>%</strong>
                </div>
                <?php if (!empty($r['moyenne_if'])): ?>
                <div style="font-size:.68rem;color:#6b7280;margin-top:2px;">
                    Moy.IF: <strong style="color:<?= $r['moyenne_if']>=70?'#16a34a':'#dc2626' ?>">
                        <?= round($r['moyenne_if'],1) ?>%
                    </strong>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <span style="color:#9ca3af;font-size:.78rem;">—</span>
                <?php endif; ?>
            </td>

            <!-- Résultat -->
            <td>
                <?php if (!$a_compose): ?>
                <span class="badge-resultat badge-attente">
                    <i class="fas fa-hourglass-half"></i>
                    <?= $r['sess_statut'] === 'planifiee' ? 'Planifié' : 'En attente' ?>
                </span>
                <?php elseif ($reussi): ?>
                <span class="badge-resultat badge-reussi">
                    <i class="fas fa-check-circle"></i> RÉUSSI
                </span>
                <?php else: ?>
                <span class="badge-resultat badge-echec">
                    <i class="fas fa-times-circle"></i> ÉCHEC
                </span>
                <?php endif; ?>
                <?php if ($a_compose && $r['locked']): ?>
                <div style="font-size:.67rem;color:#dc2626;margin-top:3px;">
                    <i class="fas fa-lock me-1"></i>Verrouillé
                </div>
                <?php endif; ?>
                <?php if ($a_compose && !empty($r['reason'])): ?>
                <div style="font-size:.64rem;color:#9ca3af;margin-top:2px;max-width:130px;overflow:hidden;text-overflow:ellipsis;"
                     title="<?= htmlspecialchars($r['reason']) ?>">
                    <?= htmlspecialchars(mb_substr($r['reason'],0,30)) ?>…
                </div>
                <?php endif; ?>
            </td>

            <!-- Date examen (masqué à l'impression) -->
            <td class="no-print" style="font-size:.76rem;white-space:nowrap;">
                <?php if ($a_compose && $r['date_resultat']): ?>
                <?= date('d/m/Y', strtotime($r['date_resultat'])) ?>
                <br><span style="color:#9ca3af;"><?= date('H:i', strtotime($r['date_resultat'])) ?></span>
                <?php else: ?>
                <span style="color:#9ca3af;">—</span>
                <?php endif; ?>
            </td>

            <!-- Actions -->
            <td class="no-print">
                <a href="view_candidat.php?id=<?= $r['idcandidat'] ?>"
                   class="btn-icon btn-icon-view" title="Fiche candidat">
                    <i class="fas fa-eye"></i>
                </a>
                <?php if ($a_compose): ?>
                <a href="print_candidat.php?id=<?= $r['idcandidat'] ?>&session=<?= $r['id_session'] ?>"
                   target="_blank" class="btn-icon btn-icon-manage" title="Imprimer résultat">
                    <i class="fas fa-print"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($rows)): ?>
        <tr><td colspan="11" style="text-align:center;padding:40px;color:#9ca3af;">
            <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
            Aucun résultat pour ces critères de filtre.
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div><!-- /table-responsive -->
</div><!-- /card-admin -->

</div><!-- /admin-content -->
</main>
</div><!-- /admin-layout -->

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('st').addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('open'));

/* ── Select2 sur la session précise ── */
$(document).ready(function () {
    $('.s2sess').select2({
        width: '100%',
        placeholder: 'Rechercher une session...',
        allowClear: true,
        language: { noResults: () => 'Aucune session trouvée' }
    });
    $('.s2sess').on('change', function () { applyServerFilter(); });
});

/* ── Appliquer les filtres (rechargement serveur) ────────────────── */
function applyServerFilter() {
    const url = new URL(window.location.href);
    const p   = url.searchParams;

    const type  = document.getElementById('fType').value;
    const ts    = document.getElementById('fTs').value;
    const deb   = document.getElementById('fDeb').value;
    const fin   = document.getElementById('fFin').value;
    const res   = document.getElementById('fRes').value;
    const srch  = document.getElementById('fSrch').value.trim();
    const sess  = document.getElementById('fSess').value;

    type  ? p.set('f_type', type)   : p.delete('f_type');
    ts    ? p.set('f_ts',   ts)     : p.delete('f_ts');
    deb   ? p.set('f_deb',  deb)    : p.delete('f_deb');
    fin   ? p.set('f_fin',  fin)    : p.delete('f_fin');
    res   ? p.set('f_res',  res)    : p.delete('f_res');
    srch  ? p.set('f_srch', srch)   : p.delete('f_srch');
    sess  ? p.set('f_sess', sess)   : p.delete('f_sess');

    window.location.href = url.toString();
}

/* ── Clic rapide sur KPI ── */
function setFRes(val) {
    document.getElementById('fRes').value = val;
    applyServerFilter();
}

/* ── Recherche texte en temps réel (filtre JS côté client) ────────
   Utilisé uniquement pour filtrage instantané SANS rechargement.
   Pour les filtres type/date, on fait un rechargement serveur.
── */
document.getElementById('fSrch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    let vis = 0;
    document.querySelectorAll('#tblR tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = !q || text.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    const badge = document.getElementById('visCount');
    if (badge) badge.textContent = vis;
});


/* ── Entrée = soumettre filtre ── */
document.getElementById('fSrch').addEventListener('keypress', e => {
    if (e.key === 'Enter') { e.preventDefault(); applyServerFilter(); }
});
</script>
</body>
</html>
<?php $conn->close(); ?>