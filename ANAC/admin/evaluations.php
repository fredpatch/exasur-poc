<?php
/**
 * evaluations.php — Gestion des évaluations candidats EXASUR
 * ANAC GABON — Direction de la Sûreté et de la Facilitation
 *
 * Filtres disponibles (tous côté SERVEUR, impactent les statistiques) :
 *  ① Recherche texte (nom/prénom candidat)
 *  ② Satisfaction (satisfait / moyen / insatisfait)
 *  ③ Type d'examen (AS / IF / INST / SENS / FORM)
 *  ④ Date début session ≥
 *  ⑤ Date fin session ≤
 *
 * Stratégie :
 *  - evaluations n'a pas de id_session → jointure via la dernière entrée dans resultats
 *    du même candidat pour récupérer le type d'examen + dates de session.
 *  - Les KPIs (total, satisfait, moyen, insatisfait, taux) sont recalculés
 *    sur la même clause WHERE → ils reflètent TOUJOURS le filtre actif.
 *  - Un badge "Filtre actif" apparaît si au moins un filtre est posé.
 *  - Graphique Chart.js (donut) mis à jour dynamiquement via PHP JSON.
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ══════════════════════════════════════════════════════════════
   LECTURE DES FILTRES GET (nettoyage + validation)
══════════════════════════════════════════════════════════════ */
$f_rating   = in_array($_GET['f_rating'] ?? '', ['satisfait','moyen','insatisfait'])
              ? $_GET['f_rating'] : '';
$f_search   = $conn->real_escape_string(trim($_GET['f_search']   ?? ''));
$f_type     = intval($_GET['f_type']     ?? 0);    // idtype_examen
$f_date_deb = !empty($_GET['f_date_deb']) ? $conn->real_escape_string($_GET['f_date_deb']) : '';
$f_date_fin = !empty($_GET['f_date_fin']) ? $conn->real_escape_string($_GET['f_date_fin']) : '';

/* ── Déterminer si un filtre est actif ──────────────────── */
$is_filtered = ($f_rating || $f_search || $f_type || $f_date_deb || $f_date_fin);

/* ══════════════════════════════════════════════════════════════
   CONSTRUCTION DU WHERE COMMUN
   Jointure : evaluations → candidat → stagiaire (noms)
              evaluations → resultats → session_examen (dates + type)
   On prend la dernière session passée par ce candidat pour
   récupérer les informations de session.
══════════════════════════════════════════════════════════════ */

/*
 * Sous-requête : pour chaque candidat, récupérer les infos de la
 * session la plus récente (date_debut, date_fin, idtype_examen, code).
 * Cela permet le filtre par date de session + par type d'examen.
 */
$subq = "
    SELECT
        r_inner.idcandidat,
        se_inner.date_debut  AS sess_date_deb,
        se_inner.date_fin    AS sess_date_fin,
        se_inner.idtype_examen,
        te_inner.code        AS type_code
    FROM resultats r_inner
    JOIN session_examen se_inner ON r_inner.id_session = se_inner.id_session
    JOIN type_examen    te_inner ON se_inner.idtype_examen = te_inner.idtype_examen
    WHERE r_inner.id = (
        SELECT MAX(r2.id)
        FROM resultats r2
        WHERE r2.idcandidat = r_inner.idcandidat
    )
";

$w = "WHERE 1=1";
if ($f_search)   $w .= " AND (s.nomstagiaire LIKE '%$f_search%' OR s.prenomstagiaire LIKE '%$f_search%')";
if ($f_rating)   $w .= " AND e.rating = '$f_rating'";
if ($f_type)     $w .= " AND sess_info.idtype_examen = $f_type";
if ($f_date_deb) $w .= " AND sess_info.sess_date_deb >= '$f_date_deb'";
if ($f_date_fin) $w .= " AND sess_info.sess_date_fin <= '$f_date_fin'";

/* ── Base JOIN commune ───────────────────────────────────── */
$base_join = "
    FROM evaluations e
    JOIN candidat           c     ON e.idcandidat       = c.idcandidat
    JOIN si_anac.stagiaire  s     ON c.idstagiaire      = s.idstagiaire
    LEFT JOIN ($subq)       sess_info ON sess_info.idcandidat = e.idcandidat
";

/* ── Données filtrées ────────────────────────────────────── */
$evaluations = $conn->query("
    SELECT
        e.id, e.idcandidat, e.rating, e.commentaire, e.created_at,
        s.nomstagiaire, s.prenomstagiaire, c.code_acces,
        sess_info.sess_date_deb,
        sess_info.sess_date_fin,
        sess_info.type_code,
        sess_info.idtype_examen
    $base_join
    $w
    ORDER BY e.created_at DESC
");

/* ══════════════════════════════════════════════════════════════
   STATISTIQUES — calculées sur la MÊME clause WHERE
   → Les KPIs reflètent exactement le filtre actif
══════════════════════════════════════════════════════════════ */
$stat_total = intval($conn->query("SELECT COUNT(*) $base_join $w")->fetch_row()[0]);
$w_sat      = $w . ($f_rating ? '' : " AND e.rating='satisfait'");
$w_moy      = $w . ($f_rating ? '' : " AND e.rating='moyen'");
$w_ins      = $w . ($f_rating ? '' : " AND e.rating='insatisfait'");

/* Recalcul ciblé par rating si pas déjà filtré */
if ($f_rating) {
    $stat_sat = ($f_rating === 'satisfait')   ? $stat_total : 0;
    $stat_moy = ($f_rating === 'moyen')       ? $stat_total : 0;
    $stat_ins = ($f_rating === 'insatisfait') ? $stat_total : 0;
} else {
    // Compter en réutilisant les mêmes jointures + WHERE mais en ajoutant le rating
    $w_and_sat = $w . " AND e.rating='satisfait'";
    $w_and_moy = $w . " AND e.rating='moyen'";
    $w_and_ins = $w . " AND e.rating='insatisfait'";
    $stat_sat = intval($conn->query("SELECT COUNT(*) $base_join $w_and_sat")->fetch_row()[0]);
    $stat_moy = intval($conn->query("SELECT COUNT(*) $base_join $w_and_moy")->fetch_row()[0]);
    $stat_ins = intval($conn->query("SELECT COUNT(*) $base_join $w_and_ins")->fetch_row()[0]);
}

$stat_taux = $stat_total > 0 ? round($stat_sat / $stat_total * 100, 1) : 0;

/* Stats GLOBALES (sans filtre) pour comparaison */
$global_total = intval($conn->query("SELECT COUNT(*) FROM evaluations")->fetch_row()[0]);
$is_subset    = ($is_filtered && $stat_total !== $global_total);

/* ── Types d'examen pour le filtre ──────────────────────── */
$types_arr = [];
$tr = $conn->query("SELECT idtype_examen, code, nom_fr FROM type_examen ORDER BY idtype_examen");
while ($t = $tr->fetch_assoc()) $types_arr[] = $t;

/* ── Répartition par type pour le graphique ─────────────── */
$repartition_types = [];
foreach ($types_arr as $t) {
    $w_type = $w . " AND sess_info.idtype_examen = {$t['idtype_examen']}";
    $nb = intval($conn->query("SELECT COUNT(*) $base_join $w_type")->fetch_row()[0]);
    if ($nb > 0) {
        $repartition_types[] = ['code' => $t['code'], 'nom' => $t['nom_fr'], 'nb' => $nb];
    }
}

$active_page = 'evaluations';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Évaluations — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* ══════════════════════════════════════════════════════════
   STYLES SPÉCIFIQUES EVALUATIONS
══════════════════════════════════════════════════════════ */

/* ── Badges rating ──────────────────────────────────────── */
.rb{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 14px;border-radius:50px;font-weight:700;font-size:.8rem;
}
.rb-satisfait  { background:linear-gradient(135deg,#166534,#14532d);  color:#fff; }
.rb-moyen      { background:linear-gradient(135deg,#ca8a04,#92400e);  color:#fff; }
.rb-insatisfait{ background:linear-gradient(135deg,#991b1b,#7f1d1d);  color:#fff; }

/* ── KPI cards ──────────────────────────────────────────── */
.kpi-eval {
    background: white;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 3px 14px rgba(3,34,76,.08);
    border-left: 4px solid transparent;
    flex: 1; min-width: 130px;
    display: flex; align-items: center; gap: 14px;
    transition: transform .2s, box-shadow .2s;
    cursor: default;
}
.kpi-eval:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(3,34,76,.14); }
.kpi-ico {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.kpi-val { font-size: 2rem; font-weight: 900; line-height: 1; }
.kpi-lbl { font-size: .72rem; color: #9ca3af; font-weight: 600;
           text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
.kpi-sub { font-size: .7rem; color: #c4c9d4; margin-top: 1px; }

/* ── Badge filtre actif ──────────────────────────────────── */
.filter-active-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--blue); color: white;
    padding: 4px 14px; border-radius: 20px;
    font-size: .76rem; font-weight: 700;
}

/* ── Date input ─────────────────────────────────────────── */
.date-input {
    border: 2px solid var(--gray-border); border-radius: 8px;
    padding: 7px 11px; font-family: inherit; font-size: .84rem;
    transition: border-color .2s; color: var(--text-dark);
}
.date-input:focus { border-color: var(--blue); outline: none; }

/* ── Type badge dans tableau ────────────────────────────── */
.tp  { display:inline-flex;padding:2px 9px;border-radius:50px;font-size:.7rem;font-weight:700; }
.tp-AS   { background:#dbeafe;color:#1e40af; }
.tp-IF   { background:#d1fae5;color:#065f46; }
.tp-INST { background:#fef3c7;color:#92400e; }
.tp-SENS { background:#ede9fe;color:#5b21b6; }
.tp-FORM { background:#fce7f3;color:#9d174d; }

/* ── Commentaire tronqué ────────────────────────────────── */
.cmt {
    max-width: 260px; overflow: hidden; text-overflow: ellipsis;
    white-space: nowrap; font-size: .83rem; color: #6c7a8d;
    cursor: pointer;
}
.cmt:hover { color: var(--blue); text-decoration: underline; }

/* ── Graphique panel ────────────────────────────────────── */
.chart-panel {
    background: white; border-radius: 14px;
    padding: 20px; box-shadow: 0 3px 14px rgba(3,34,76,.08);
    border-top: 4px solid var(--gold);
    height: 100%;
}
.chart-panel h6 {
    font-weight: 800; color: var(--blue);
    font-size: .9rem; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}

/* ── Barre progression satisfaction ─────────────────────── */
.satis-bar {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 10px; font-size: .85rem;
}
.satis-bar-fill {
    flex: 1; height: 10px; border-radius: 5px; background: #e5e7eb; overflow: hidden;
}
.satis-bar-fill-inner {
    height: 100%; border-radius: 5px; transition: width .8s ease;
}
.satis-label { width: 110px; font-weight: 700; font-size: .82rem; }
.satis-count { width: 40px; text-align: right; font-weight: 800;
               font-size: .9rem; color: var(--blue); }
.satis-pct   { width: 48px; text-align: right; font-size: .78rem; color: #9ca3af; }

/* ── Filtre résumé ───────────────────────────────────────── */
.filter-summary {
    background: linear-gradient(135deg,rgba(3,34,76,.04),rgba(212,175,55,.06));
    border: 1.5px solid rgba(212,175,55,.4);
    border-radius: 10px; padding: 10px 16px;
    margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    font-size: .82rem;
}
.filter-tag {
    background: var(--blue-light); color: var(--blue);
    padding: 3px 10px; border-radius: 20px;
    font-size: .76rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<!-- ── Topbar ──────────────────────────────────────────────── -->
<div class="admin-topbar">
  <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
  <div class="topbar-title">
    <i class="fas fa-star"></i> Évaluations candidats — EXASUR
    <?php if ($is_filtered): ?>
    <span class="filter-active-badge ms-2">
      <i class="fas fa-filter"></i>
      Filtre actif · <?= $stat_total ?> résultat(s)
    </span>
    <?php endif; ?>
  </div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <?php if ($is_filtered): ?>
    <a href="evaluations.php"
       style="font-size:.78rem;color:#dc2626;text-decoration:none;font-weight:700;">
      <i class="fas fa-times me-1"></i>Effacer les filtres
    </a>
    <?php endif; ?>
    <i class="fas fa-user-shield text-muted me-1"></i>
    <span style="font-weight:600;font-size:.85rem">
      <?= htmlspecialchars($_SESSION['admin_nom']) ?>
    </span>
  </div>
</div>

<div class="admin-content">

<!-- ══════════════════════════════════════════════════════════
     KPIs — Calculés sur la même clause WHERE que le filtre
══════════════════════════════════════════════════════════ -->
<div class="d-flex gap-3 mb-4 flex-wrap">

  <!-- Total -->
  <div class="kpi-eval" style="border-left-color:#374151;">
    <div class="kpi-ico" style="background:#f3f4f6;color:#374151;">
      <i class="fas fa-clipboard-list"></i>
    </div>
    <div>
      <div class="kpi-val" style="color:#374151;"><?= $stat_total ?></div>
      <div class="kpi-lbl">TOTAL</div>
      <?php if ($is_subset): ?>
      <div class="kpi-sub">sur <?= $global_total ?> global</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Satisfaits -->
  <div class="kpi-eval" style="border-left-color:#16a34a;cursor:pointer;"
       onclick="filtrerRating('satisfait')">
    <div class="kpi-ico" style="background:#dcfce7;font-size:1.5rem;">😊</div>
    <div>
      <div class="kpi-val" style="color:#16a34a;"><?= $stat_sat ?></div>
      <div class="kpi-lbl">SATISFAITS</div>
    </div>
  </div>

  <!-- Moyens -->
  <div class="kpi-eval" style="border-left-color:#ca8a04;cursor:pointer;"
       onclick="filtrerRating('moyen')">
    <div class="kpi-ico" style="background:#fef3c7;font-size:1.5rem;">😐</div>
    <div>
      <div class="kpi-val" style="color:#ca8a04;"><?= $stat_moy ?></div>
      <div class="kpi-lbl">MOYENS</div>
    </div>
  </div>

  <!-- Insatisfaits -->
  <div class="kpi-eval" style="border-left-color:#dc2626;cursor:pointer;"
       onclick="filtrerRating('insatisfait')">
    <div class="kpi-ico" style="background:#fee2e2;font-size:1.5rem;">😞</div>
    <div>
      <div class="kpi-val" style="color:#dc2626;"><?= $stat_ins ?></div>
      <div class="kpi-lbl">INSATISFAITS</div>
    </div>
  </div>

  <!-- Taux satisfaction -->
  <div class="kpi-eval" style="border-left-color:#7c3aed;">
    <div class="kpi-ico" style="background:#ede9fe;color:#7c3aed;">
      <i class="fas fa-percentage"></i>
    </div>
    <div>
      <div class="kpi-val" style="color:#7c3aed;"><?= $stat_taux ?>%</div>
      <div class="kpi-lbl">TAUX SATISF.</div>
    </div>
  </div>

</div><!-- /KPIs -->


<!-- ══════════════════════════════════════════════════════════
     GRAPHIQUES + BARRES
══════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">

  <!-- Donut satisfaction -->
  <div class="col-lg-4">
    <div class="chart-panel">
      <h6>
        <i class="fas fa-chart-pie" style="color:var(--gold);"></i>
        Répartition satisfaction
        <?php if ($is_filtered): ?>
        <span style="font-size:.72rem;color:var(--gold);font-weight:600;">(filtrée)</span>
        <?php endif; ?>
      </h6>
      <?php if ($stat_total > 0): ?>
      <div style="position:relative;max-height:200px;display:flex;justify-content:center;">
        <canvas id="donutChart" style="max-height:200px;"></canvas>
      </div>
      <!-- Légende centrale -->
      <div style="text-align:center;margin-top:14px;">
        <div style="font-size:2rem;font-weight:900;color:var(--blue);line-height:1;">
          <?= $stat_taux ?>%
        </div>
        <div style="font-size:.75rem;color:#9ca3af;font-weight:600;text-transform:uppercase;">
          Taux de satisfaction<?= $is_filtered ? ' (filtrée)' : '' ?>
        </div>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:40px;color:#9ca3af;">
        <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
        Aucune évaluation pour ces critères.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Barres progression par rating -->
  <div class="col-lg-4">
    <div class="chart-panel">
      <h6><i class="fas fa-chart-bar" style="color:var(--gold);"></i> Détail par satisfaction</h6>

      <!-- Satisfaits -->
      <div class="satis-bar">
        <div class="satis-label" style="color:#16a34a;">😊 Satisfaits</div>
        <div class="satis-bar-fill">
          <div class="satis-bar-fill-inner"
               style="width:<?= $stat_total>0?round($stat_sat/$stat_total*100):0 ?>%;
                      background:linear-gradient(90deg,#16a34a,#22c55e);"
               id="barSat"></div>
        </div>
        <div class="satis-count"><?= $stat_sat ?></div>
        <div class="satis-pct"><?= $stat_total>0?round($stat_sat/$stat_total*100):0 ?>%</div>
      </div>

      <!-- Moyens -->
      <div class="satis-bar">
        <div class="satis-label" style="color:#ca8a04;">😐 Moyens</div>
        <div class="satis-bar-fill">
          <div class="satis-bar-fill-inner"
               style="width:<?= $stat_total>0?round($stat_moy/$stat_total*100):0 ?>%;
                      background:linear-gradient(90deg,#ca8a04,#f59e0b);"
               id="barMoy"></div>
        </div>
        <div class="satis-count"><?= $stat_moy ?></div>
        <div class="satis-pct"><?= $stat_total>0?round($stat_moy/$stat_total*100):0 ?>%</div>
      </div>

      <!-- Insatisfaits -->
      <div class="satis-bar">
        <div class="satis-label" style="color:#dc2626;">😞 Insatisfaits</div>
        <div class="satis-bar-fill">
          <div class="satis-bar-fill-inner"
               style="width:<?= $stat_total>0?round($stat_ins/$stat_total*100):0 ?>%;
                      background:linear-gradient(90deg,#dc2626,#f87171);"
               id="barIns"></div>
        </div>
        <div class="satis-count"><?= $stat_ins ?></div>
        <div class="satis-pct"><?= $stat_total>0?round($stat_ins/$stat_total*100):0 ?>%</div>
      </div>

      <?php if ($stat_total > 0): ?>
      <!-- Gauge satisfaction textuelle -->
      <div style="margin-top:20px;background:#f4f7fc;border-radius:10px;padding:14px;">
        <div style="font-size:.75rem;color:#9ca3af;font-weight:700;text-transform:uppercase;
                    letter-spacing:1px;margin-bottom:8px;">Indicateur de satisfaction</div>
        <div style="height:14px;background:#e5e7eb;border-radius:7px;overflow:hidden;">
          <div style="height:100%;border-radius:7px;
                      background:<?= $stat_taux >= 70 ? 'linear-gradient(90deg,#16a34a,#22c55e)' : ($stat_taux >= 40 ? 'linear-gradient(90deg,#ca8a04,#f59e0b)' : 'linear-gradient(90deg,#dc2626,#f87171)') ?>;
                      width:<?= $stat_taux ?>%;
                      transition:width .8s ease;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:.72rem;color:#9ca3af;">
          <span>0%</span><span style="font-weight:800;color:<?= $stat_taux>=70?'#16a34a':($stat_taux>=40?'#ca8a04':'#dc2626') ?>;"><?= $stat_taux ?>%</span><span>100%</span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Répartition par type d'examen -->
  <div class="col-lg-4">
    <div class="chart-panel">
      <h6>
        <i class="fas fa-layer-group" style="color:var(--gold);"></i>
        Par type d'examen
        <?php if ($is_filtered): ?>
        <span style="font-size:.72rem;color:var(--gold);font-weight:600;">(filtrée)</span>
        <?php endif; ?>
      </h6>
      <?php if (!empty($repartition_types)): ?>
      <?php
      $colors_type = ['AS'=>'#1e40af','IF'=>'#065f46','INST'=>'#92400e','SENS'=>'#5b21b6','FORM'=>'#9d174d'];
      foreach ($repartition_types as $rt):
          $pct_type = $stat_total > 0 ? round($rt['nb'] / $stat_total * 100) : 0;
          $col_type = $colors_type[$rt['code']] ?? '#374151';
      ?>
      <div class="satis-bar">
        <div class="satis-label">
          <span class="tp tp-<?= $rt['code'] ?>"><?= $rt['code'] ?></span>
        </div>
        <div class="satis-bar-fill">
          <div class="satis-bar-fill-inner"
               style="width:<?= $pct_type ?>%;background:<?= $col_type ?>;opacity:.8;"></div>
        </div>
        <div class="satis-count" style="font-size:.85rem;"><?= $rt['nb'] ?></div>
        <div class="satis-pct"><?= $pct_type ?>%</div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:32px;color:#9ca3af;font-size:.9rem;">
        Aucune donnée disponible.
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /row graphiques -->


<!-- ══════════════════════════════════════════════════════════
     TABLEAU PRINCIPAL
══════════════════════════════════════════════════════════ -->
<div class="card-admin">
  <div class="card-admin-header">
    <i class="fas fa-star me-2" style="color:var(--gold)"></i>
    <h5>Liste des évaluations</h5>
    <span class="badge-count ms-2"><?= $stat_total ?></span>
    <?php if ($is_subset): ?>
    <span style="font-size:.72rem;color:var(--gold);margin-left:8px;">
      (<?= $stat_total ?> sur <?= $global_total ?> total)
    </span>
    <?php endif; ?>
    <?php if ($is_filtered): ?>
    <a href="evaluations.php" class="ms-auto"
       style="font-size:.78rem;color:#dc2626;text-decoration:none;font-weight:700;">
      <i class="fas fa-times me-1"></i>Effacer tous les filtres
    </a>
    <?php endif; ?>
  </div>

  <div class="card-admin-body p-0">

    <!-- ══ BARRE DE FILTRES COMPLÈTE ══ -->
    <div class="filter-bar"
         style="border-radius:0;box-shadow:none;border-bottom:1px solid var(--gray-border);
                flex-wrap:wrap;gap:12px;padding:14px 16px;">

      <!-- ① Recherche nom candidat -->
      <div class="filter-group">
        <label>Candidat</label>
        <input class="form-control-admin" id="srchE"
               placeholder="Nom ou prénom..."
               value="<?= htmlspecialchars($f_search) ?>">
      </div>

      <!-- ② Satisfaction -->
      <div class="filter-group" style="max-width:160px">
        <label>Satisfaction</label>
        <select class="form-select-admin" id="fRating">
          <option value="">Toutes</option>
          <option value="satisfait"   <?= $f_rating==='satisfait'   ?'selected':'' ?>>😊 Satisfait</option>
          <option value="moyen"       <?= $f_rating==='moyen'       ?'selected':'' ?>>😐 Moyen</option>
          <option value="insatisfait" <?= $f_rating==='insatisfait' ?'selected':'' ?>>😞 Insatisfait</option>
        </select>
      </div>

      <!-- ③ Type d'examen -->
      <div class="filter-group" style="max-width:180px">
        <label>Type d'examen</label>
        <select class="form-select-admin" id="fType">
          <option value="">Tous les types</option>
          <?php foreach ($types_arr as $t): ?>
          <option value="<?= $t['idtype_examen'] ?>"
                  <?= $f_type == $t['idtype_examen'] ? 'selected' : '' ?>>
            <?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ④ Date début session ≥ -->
      <div class="filter-group">
        <label>Session début ≥</label>
        <input type="date" class="date-input" id="fDateDeb"
               value="<?= htmlspecialchars($f_date_deb) ?>">
      </div>

      <!-- ⑤ Date fin session ≤ -->
      <div class="filter-group">
        <label>Session fin ≤</label>
        <input type="date" class="date-input" id="fDateFin"
               value="<?= htmlspecialchars($f_date_fin) ?>">
      </div>

      <!-- Boutons Appliquer / Réinitialiser -->
      <div class="filter-group" style="align-self:flex-end;display:flex;gap:8px;">
        <button class="btn-gold" onclick="appliquerFiltres()"
                style="padding:7px 16px;font-size:.84rem;">
          <i class="fas fa-search me-1"></i>Filtrer
        </button>
        <a href="evaluations.php" class="btn-anac"
           style="padding:7px 14px;font-size:.82rem;background:white;color:var(--blue);
                  display:inline-flex;align-items:center;gap:5px;">
          <i class="fas fa-times"></i>Reset
        </a>
      </div>

    </div><!-- /filter-bar -->

    <!-- Résumé des filtres actifs -->
    <?php if ($is_filtered): ?>
    <div class="filter-summary">
      <i class="fas fa-filter" style="color:var(--gold);"></i>
      <strong style="color:var(--blue);">Filtres actifs :</strong>
      <?php if ($f_search): ?>
      <span class="filter-tag"><i class="fas fa-user"></i> "<?= htmlspecialchars($f_search) ?>"</span>
      <?php endif; ?>
      <?php if ($f_rating): ?>
      <span class="filter-tag">
        <?= ['satisfait'=>'😊','moyen'=>'😐','insatisfait'=>'😞'][$f_rating] ?>
        <?= ucfirst($f_rating) ?>
      </span>
      <?php endif; ?>
      <?php if ($f_type): ?>
      <?php $code_type = array_column($types_arr, 'code', 'idtype_examen')[$f_type] ?? '?'; ?>
      <span class="filter-tag"><i class="fas fa-tag"></i> <?= $code_type ?></span>
      <?php endif; ?>
      <?php if ($f_date_deb): ?>
      <span class="filter-tag"><i class="fas fa-calendar"></i> Depuis <?= date('d/m/Y', strtotime($f_date_deb)) ?></span>
      <?php endif; ?>
      <?php if ($f_date_fin): ?>
      <span class="filter-tag"><i class="fas fa-calendar-check"></i> Jusqu'au <?= date('d/m/Y', strtotime($f_date_fin)) ?></span>
      <?php endif; ?>
      <span style="color:#9ca3af;margin-left:auto;font-size:.78rem;">
        <?= $stat_total ?> résultat(s) · <a href="evaluations.php"
            style="color:#dc2626;font-weight:700;text-decoration:none;">Effacer</a>
      </span>
    </div>
    <?php endif; ?>

    <!-- ══ TABLEAU ══ -->
    <div class="table-responsive">
      <table class="table-admin" id="tblE">
        <thead>
          <tr>
            <th>#</th>
            <th>Candidat</th>
            <th>Code</th>
            <th>Type examen</th>
            <th>Satisfaction</th>
            <th>Commentaire</th>
            <th>Session (dates)</th>
            <th>Date éval.</th>
          </tr>
        </thead>
        <tbody>
        <?php
        if ($evaluations && $evaluations->num_rows > 0):
            while ($e = $evaluations->fetch_assoc()):
                $ico_map = ['satisfait'=>'😊','moyen'=>'😐','insatisfait'=>'😞'];
                $ico = $ico_map[$e['rating']] ?? '•';
                $has_dates = !empty($e['sess_date_deb']) && !empty($e['sess_date_fin']);
        ?>
        <tr>
          <td style="color:#9ca3af;font-size:.78rem;font-weight:700;">#<?= $e['id'] ?></td>
          <td>
            <div style="font-weight:700;"><?= htmlspecialchars($e['nomstagiaire'].' '.$e['prenomstagiaire']) ?></div>
          </td>
          <td>
            <span style="background:var(--blue);color:white;padding:3px 10px;
                         border-radius:50px;font-weight:700;font-size:.8rem;letter-spacing:1px;">
              <?= htmlspecialchars($e['code_acces']) ?>
            </span>
          </td>
          <td>
            <?php if ($e['type_code']): ?>
            <span class="tp tp-<?= htmlspecialchars($e['type_code']) ?>">
              <?= htmlspecialchars($e['type_code']) ?>
            </span>
            <?php else: ?>
            <span style="color:#d1d5db;font-size:.8rem;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="rb rb-<?= $e['rating'] ?>">
              <?= $ico ?> <?= ucfirst($e['rating']) ?>
            </span>
          </td>
          <td>
            <?php if ($e['commentaire']): ?>
            <div class="cmt"
                 onclick="showCmt(<?= json_encode(htmlspecialchars($e['commentaire'], ENT_QUOTES, 'UTF-8')) ?>,
                                  '<?= htmlspecialchars($e['nomstagiaire'].' '.$e['prenomstagiaire'], ENT_QUOTES) ?>')">
              <?= htmlspecialchars($e['commentaire']) ?>
            </div>
            <?php else: ?>
            <span style="color:#d1d5db;font-size:.8rem;">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;white-space:nowrap;">
            <?php if ($has_dates): ?>
            <span style="color:#6c7a8d;">
              <?= date('d/m/Y', strtotime($e['sess_date_deb'])) ?>
              <span style="color:#d1d5db;">→</span>
              <?= date('d/m/Y', strtotime($e['sess_date_fin'])) ?>
            </span>
            <?php else: ?>
            <span style="color:#d1d5db;">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;white-space:nowrap;">
            <?= date('d/m/Y', strtotime($e['created_at'])) ?>
            <br><span style="color:#9ca3af;"><?= date('H:i', strtotime($e['created_at'])) ?></span>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php else: ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:52px;color:#9ca3af;">
            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
            <strong>Aucune évaluation trouvée</strong> pour ces critères.
            <?php if ($is_filtered): ?>
            <br><a href="evaluations.php"
                   style="color:var(--blue);font-weight:700;font-size:.88rem;margin-top:8px;display:inline-block;">
              <i class="fas fa-times me-1"></i>Effacer les filtres
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div><!-- /table-responsive -->

  </div>
</div><!-- /card-admin -->

</div><!-- /admin-content -->
</main>
</div>

<script>
/* ── Sidebar toggle ─────────────────────────────────────── */
document.getElementById('st').addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('open'));

/* ══════════════════════════════════════════════════════════
   GRAPHIQUE DONUT — Chart.js
   Données calculées côté PHP avec le même filtre actif
══════════════════════════════════════════════════════════ */
<?php if ($stat_total > 0): ?>
(function () {
    const ctx = document.getElementById('donutChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['😊 Satisfaits', '😐 Moyens', '😞 Insatisfaits'],
            datasets: [{
                data: [<?= $stat_sat ?>, <?= $stat_moy ?>, <?= $stat_ins ?>],
                backgroundColor: ['#16a34a', '#ca8a04', '#dc2626'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct   = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                            return ` ${ctx.raw} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
})();
<?php endif; ?>

/* ══════════════════════════════════════════════════════════
   FILTRES — Application (rechargement serveur)
   Tous les filtres passent par GET → les stats PHP sont
   recalculées sur la même clause WHERE
══════════════════════════════════════════════════════════ */
function appliquerFiltres() {
    const params = new URLSearchParams();
    const search  = document.getElementById('srchE').value.trim();
    const rating  = document.getElementById('fRating').value;
    const type    = document.getElementById('fType').value;
    const dateDeb = document.getElementById('fDateDeb').value;
    const dateFin = document.getElementById('fDateFin').value;

    if (search)  params.set('f_search',  search);
    if (rating)  params.set('f_rating',  rating);
    if (type)    params.set('f_type',    type);
    if (dateDeb) params.set('f_date_deb', dateDeb);
    if (dateFin) params.set('f_date_fin', dateFin);

    window.location.href = 'evaluations.php?' + params.toString();
}

/* Déclencher avec Entrée dans la recherche texte */
document.getElementById('srchE').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') appliquerFiltres();
});

/* Déclencher immédiatement au changement des dates */
document.getElementById('fDateDeb').addEventListener('change', appliquerFiltres);
document.getElementById('fDateFin').addEventListener('change', appliquerFiltres);

/* Raccourci : clic sur un KPI satisfait/moyen/insatisfait */
function filtrerRating(val) {
    const params = new URLSearchParams();
    const search = document.getElementById('srchE').value.trim();
    const type   = document.getElementById('fType').value;
    const dDeb   = document.getElementById('fDateDeb').value;
    const dFin   = document.getElementById('fDateFin').value;

    if (search) params.set('f_search',  search);
    if (type)   params.set('f_type',    type);
    if (dDeb)   params.set('f_date_deb', dDeb);
    if (dFin)   params.set('f_date_fin', dFin);

    /* Basculer : si déjà filtré sur ce rating → effacer */
    const currentRating = '<?= $f_rating ?>';
    if (currentRating !== val) params.set('f_rating', val);

    window.location.href = 'evaluations.php?' + params.toString();
}

/* ══════════════════════════════════════════════════════════
   MODAL COMMENTAIRE COMPLET
══════════════════════════════════════════════════════════ */
function showCmt(txt, nom) {
    Swal.fire({
        title: '<i class="fas fa-comment-alt" style="color:#D4AF37;margin-right:8px;"></i>Commentaire',
        html: `<div style="text-align:left;padding:6px;">
                 <p style="font-size:.82rem;color:#9ca3af;margin-bottom:8px;">
                   <i class="fas fa-user me-1"></i>${nom || ''}
                 </p>
                 <div style="background:#f4f7fc;border-left:4px solid #D4AF37;
                             border-radius:8px;padding:14px;font-size:.95rem;
                             color:#1a1f2e;line-height:1.7;max-height:300px;overflow-y:auto;">
                   ${txt || '<em style="color:#9ca3af;">Aucun commentaire saisi.</em>'}
                 </div>
               </div>`,
        confirmButtonColor: '#03224c',
        confirmButtonText: 'Fermer',
        width: '560px'
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>