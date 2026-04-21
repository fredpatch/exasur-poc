<?php
/**
 * dashboard.php — Tableau de bord EXASUR ANAC GABON
 * Filtres dynamiques (date exacte session), KPIs AJAX, rapports Select2
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── AJAX : données filtrées dynamiquement ── */
if (isset($_GET['ajax']) && $_GET['ajax']==='kpi') {
    header('Content-Type: application/json');

    $f_deb  = $conn->real_escape_string($_GET['f_deb']  ?? '');
    $f_fin  = $conn->real_escape_string($_GET['f_fin']  ?? '');
    $f_ann  = intval($_GET['f_ann']  ?? 0);
    $f_type = intval($_GET['f_type'] ?? 0);
    $hasFilter = ($f_deb || $f_fin || $f_ann || $f_type);

    /* ── Conditions sur session_examen ── */
    /* On construit une liste de conditions (sans WHERE) */
    $cse = ["1=1"];
    if ($f_deb)  $cse[] = "se.date_debut = '$f_deb'";   /* égalité exacte */
    if ($f_fin)  $cse[] = "se.date_fin   = '$f_fin'";   /* égalité exacte */
    if ($f_ann)  $cse[] = "YEAR(se.date_debut) = $f_ann";
    if ($f_type) $cse[] = "se.idtype_examen = $f_type";
    $where_se = "WHERE " . implode(" AND ", $cse);

    /* IDs de sessions filtrées (sous-requête réutilisable) */
    $sub_ids = "SELECT id_session FROM session_examen se $where_se";

    /* ── Conditions sur resultats ── */
    $cr = ["1=1"];
    if ($hasFilter) $cr[] = "r.id_session IN ($sub_ids)";
    if ($f_type)    $cr[] = "r.idtype_examen = $f_type";
    $where_r = "WHERE " . implode(" AND ", $cr);

    /* ── KPIs ── */
    $nb_candidats  = intval($conn->query("SELECT COUNT(DISTINCT idcandidat) FROM candidat")->fetch_row()[0]);
    $nb_sessions   = intval($conn->query("SELECT COUNT(*) FROM session_examen se $where_se")->fetch_row()[0]);
    $nb_planifiees = intval($conn->query("SELECT COUNT(*) FROM session_examen se $where_se AND se.statut='planifiee'")->fetch_row()[0]);
    $nb_en_cours   = intval($conn->query("SELECT COUNT(*) FROM session_examen se $where_se AND se.statut='en_cours'")->fetch_row()[0]);
    $nb_exams      = intval($conn->query("SELECT COUNT(*) FROM resultats r $where_r")->fetch_row()[0]);
    $nb_reussis    = intval($conn->query("SELECT COUNT(*) FROM resultats r $where_r AND r.reussite=1")->fetch_row()[0]);
    $nb_echecs     = $nb_exams - $nb_reussis;
    $taux          = $nb_exams > 0 ? round($nb_reussis / $nb_exams * 100, 1) : 0;
    $nb_online     = intval($conn->query("SELECT COUNT(*) FROM candidat WHERE is_logged_in=1")->fetch_row()[0]);
    $nb_questions  = intval($conn->query("SELECT COUNT(*) FROM question")->fetch_row()[0]);
    $nb_evals      = intval($conn->query("SELECT COUNT(*) FROM evaluations")->fetch_row()[0]);

    /* ── Stats par type ── */
    /* Conditions sur session_examen pour le JOIN (sans le préfixe WHERE) */
    $cse_join = implode(" AND ", $cse); /* ex: 1=1 AND se.date_debut='...' */
    $types_q = $conn->query("
        SELECT te.code, te.nom_fr,
               COUNT(DISTINCT cs.idcandidat)                              AS nb_cand,
               SUM(CASE WHEN r.reussite=1 THEN 1 ELSE 0 END)             AS nb_ok,
               COUNT(r.id)                                                AS nb_exam
        FROM type_examen te
        LEFT JOIN session_examen se
               ON se.idtype_examen = te.idtype_examen AND ($cse_join)
        LEFT JOIN candidat_session cs
               ON cs.id_session = se.id_session AND cs.habilite = 1
        LEFT JOIN resultats r
               ON r.id_session = se.id_session AND r.idtype_examen = te.idtype_examen
        GROUP BY te.idtype_examen
        ORDER BY te.idtype_examen
    ");
    $type_stats = [];
    if ($types_q) while ($t = $types_q->fetch_assoc()) $type_stats[] = $t;

    /* ── Derniers résultats filtrés ── */
    $derniers_q = $conn->query("
        SELECT r.*, s.nomstagiaire, s.prenomstagiaire,
               se.nom_session, te.code AS tc
        FROM resultats r
        JOIN candidat c   ON r.idcandidat = c.idcandidat
        JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
        JOIN session_examen se   ON r.id_session  = se.id_session
        JOIN type_examen te      ON r.idtype_examen = te.idtype_examen
        $where_r
        ORDER BY r.date_fin DESC LIMIT 10
    ");
    $derniers = [];
    if ($derniers_q) while ($row = $derniers_q->fetch_assoc()) $derniers[] = $row;

    /* ── Sessions filtrées ── */
    $sess_q = $conn->query("
        SELECT se.*, te.code AS tc,
               COUNT(DISTINCT cs.idcandidat) AS nb
        FROM session_examen se
        JOIN type_examen te ON se.idtype_examen = te.idtype_examen
        LEFT JOIN candidat_session cs ON cs.id_session = se.id_session AND cs.habilite = 1
        $where_se
        GROUP BY se.id_session
        ORDER BY se.date_debut DESC LIMIT 8
    ");
    $sessions = [];
    if ($sess_q) while ($s = $sess_q->fetch_assoc()) $sessions[] = $s;

    echo json_encode([
        'kpi' => [
            'candidats'  => $nb_candidats,
            'sessions'   => $nb_sessions,
            'planifiees' => $nb_planifiees,
            'en_cours'   => $nb_en_cours,
            'exams'      => $nb_exams,
            'reussis'    => $nb_reussis,
            'echecs'     => $nb_echecs,
            'taux'       => $taux,
            'online'     => $nb_online,
            'questions'  => $nb_questions,
            'evals'      => $nb_evals,
        ],
        'type_stats' => $type_stats,
        'derniers'   => $derniers,
        'sessions'   => $sessions,
    ]);
    exit();
}

/* ── Données initiales (sans filtre) ── */
$stats=[];
$stats['candidats']  = $conn->query("SELECT COUNT(*) FROM candidat")->fetch_row()[0];
$stats['sessions']   = $conn->query("SELECT COUNT(*) FROM session_examen")->fetch_row()[0];
$stats['planifiees'] = $conn->query("SELECT COUNT(*) FROM session_examen WHERE statut='planifiee'")->fetch_row()[0];
$stats['en_cours']   = $conn->query("SELECT COUNT(*) FROM session_examen WHERE statut='en_cours'")->fetch_row()[0];
$stats['resultats']  = $conn->query("SELECT COUNT(*) FROM resultats")->fetch_row()[0];
$stats['reussites']  = $conn->query("SELECT COUNT(*) FROM resultats WHERE reussite=1")->fetch_row()[0];
$stats['echecs']     = $stats['resultats'] - $stats['reussites'];
$stats['evaluations']= $conn->query("SELECT COUNT(*) FROM evaluations")->fetch_row()[0];
$stats['questions']  = $conn->query("SELECT COUNT(*) FROM question")->fetch_row()[0];
$stats['online']     = $conn->query("SELECT COUNT(*) FROM candidat WHERE is_logged_in=1")->fetch_row()[0];
$taux_global = $stats['resultats']>0 ? round($stats['reussites']/$stats['resultats']*100,1) : 0;

$types_graph=$conn->query("SELECT te.code,te.nom_fr,COUNT(DISTINCT cs.idcandidat) AS nb_cand,SUM(CASE WHEN r.reussite=1 THEN 1 ELSE 0 END) AS nb_ok,COUNT(r.id) AS nb_exam FROM type_examen te LEFT JOIN session_examen se ON se.idtype_examen=te.idtype_examen LEFT JOIN candidat_session cs ON cs.id_session=se.id_session AND cs.habilite=1 LEFT JOIN resultats r ON r.idtype_examen=te.idtype_examen GROUP BY te.idtype_examen ORDER BY te.idtype_examen");
$graph_labels=[];$graph_ok=[];$graph_ko=[];$type_stats=[];
if($types_graph)while($g=$types_graph->fetch_assoc()){$graph_labels[]=$g['code'];$graph_ok[]=(int)$g['nb_ok'];$graph_ko[]=(int)($g['nb_exam']-$g['nb_ok']);$type_stats[]=$g;}

/* Listes pour rapports */
$candidats_list=$conn->query("SELECT c.idcandidat,s.nomstagiaire,s.prenomstagiaire,c.code_acces FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire ORDER BY s.nomstagiaire,s.prenomstagiaire");
$sessions_list=$conn->query("SELECT id_session,nom_session,date_debut,date_fin FROM session_examen ORDER BY date_debut DESC");
$organismes_list=$conn->query("SELECT o.idorga,o.nomorga,o.trigrorganisme,o.ville_org,COUNT(DISTINCT c.idcandidat) AS nb_candidats FROM si_anac.organisme o LEFT JOIN si_anac.stagiaire s ON s.idorga=o.idorga LEFT JOIN candidat c ON c.idstagiaire=s.idstagiaire GROUP BY o.idorga HAVING nb_candidats>0 ORDER BY o.nomorga");
$types_list=$conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");

/* Années disponibles */
$annees=$conn->query("SELECT DISTINCT YEAR(date_debut) AS ann FROM session_examen ORDER BY ann DESC");
$annees_arr=[];if($annees)while($a=$annees->fetch_assoc())$annees_arr[]=$a['ann'];

$derniers_res=$conn->query("SELECT r.*,s.nomstagiaire,s.prenomstagiaire,se.nom_session,te.code AS tc FROM resultats r JOIN candidat c ON r.idcandidat=c.idcandidat JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire JOIN session_examen se ON r.id_session=se.id_session JOIN type_examen te ON r.idtype_examen=te.idtype_examen ORDER BY r.date_fin DESC LIMIT 10");
$sessions_rec=$conn->query("SELECT se.*,te.code AS tc,COUNT(cs.id) AS nb FROM session_examen se JOIN type_examen te ON se.idtype_examen=te.idtype_examen LEFT JOIN candidat_session cs ON cs.id_session=se.id_session AND cs.habilite=1 GROUP BY se.id_session ORDER BY se.created_at DESC LIMIT 8");

$active_page='dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tableau de bord — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
:root{--blue:#03224c;--gold:#D4AF37;--blue-mid:#0a3a6b;}
/* ── Bannière ── */
.banner{background:linear-gradient(135deg,var(--blue),var(--blue-mid));color:white;border-radius:14px;padding:18px 24px;margin-bottom:22px;border-bottom:4px solid var(--gold);display:flex;align-items:center;gap:16px;}
.banner img{height:52px;background:white;padding:6px;border-radius:10px;flex-shrink:0;}
.banner h1{font-size:1.1rem;font-weight:800;margin-bottom:2px;}
.banner p{font-size:.82rem;opacity:.8;margin:0;}
.banner-taux{font-size:2.2rem;font-weight:800;color:var(--gold);text-align:center;white-space:nowrap;}
.banner-taux-lbl{font-size:.72rem;opacity:.78;text-align:center;}
/* ── Barre de filtres ── */
.filter-dash{
    background:white;border-radius:14px;padding:14px 18px;margin-bottom:20px;
    box-shadow:0 2px 14px rgba(3,34,76,.08);border:1.5px solid #e0e7f0;
    display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;
}
.filter-dash .fg{display:flex;flex-direction:column;gap:3px;flex:1;min-width:110px;}
.filter-dash .fl{font-size:.71rem;font-weight:700;color:var(--blue);}
.filter-dash input,.filter-dash select{
    padding:8px 11px;border:1.5px solid #d1d5db;border-radius:9px;
    font-family:inherit;font-size:.83rem;background:#fff;transition:border-color .2s;
}
.filter-dash input:focus,.filter-dash select:focus{outline:none;border-color:var(--blue);}
.btn-filter{background:var(--blue);color:#fff;border:none;border-radius:9px;padding:9px 16px;font-family:inherit;font-weight:700;font-size:.83rem;cursor:pointer;display:flex;align-items:center;gap:5px;transition:opacity .2s;}
.btn-filter:hover{opacity:.9;}
.btn-reset{background:#f0f4fa;color:var(--blue);border:1px solid #d1dbe8;border-radius:9px;padding:9px 12px;font-family:inherit;font-weight:700;font-size:.83rem;cursor:pointer;}
.btn-reset:hover{background:#e2e8f5;}
/* Indicateur filtre actif */
.filter-active-badge{display:inline-flex;align-items:center;gap:5px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:50px;padding:3px 10px;font-size:.73rem;font-weight:700;margin-left:6px;}
/* ── KPIs ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px;margin-bottom:22px;}
.kpi{background:white;border-radius:13px;padding:16px 18px;box-shadow:0 3px 14px rgba(3,34,76,.08);display:flex;align-items:center;gap:13px;border-left:4px solid transparent;transition:transform .2s,box-shadow .2s;text-decoration:none;color:inherit;cursor:pointer;}
.kpi:hover{transform:translateY(-3px);box-shadow:0 6px 22px rgba(3,34,76,.13);}
.kpi-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.kpi-val{font-size:1.8rem;font-weight:800;line-height:1;transition:all .3s;}
.kpi-lbl{font-size:.7rem;font-weight:600;color:#6c7a8d;text-transform:uppercase;letter-spacing:.3px;margin-top:2px;}
.kpi.updating{opacity:.5;}
/* ── Types progress ── */
.tp-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px dashed #e5e7eb;}
.tp-row:last-child{border-bottom:none;}
.tp{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
/* ── Rapport cards ── */
.report-card{background:white;border-radius:14px;padding:20px;box-shadow:0 2px 14px rgba(3,34,76,.07);border-top:4px solid var(--gold);height:100%;transition:transform .2s,box-shadow .2s;}
.report-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(3,34,76,.12);}
.report-card h5{color:var(--blue);font-weight:700;margin-bottom:5px;font-size:.95rem;}
.report-card h5 i{color:var(--gold);}
.report-card p{font-size:.8rem;color:#6b7280;margin-bottom:12px;}
.report-card.blue-top{border-top-color:var(--blue);}
.report-card.green-top{border-top-color:#16a34a;}
/* ── Loader overlay ── */
.dash-loader{display:none;position:fixed;inset:0;background:rgba(3,34,76,.12);z-index:9999;align-items:center;justify-content:center;}
.dash-loader.show{display:flex;}
.dash-spinner{width:50px;height:50px;border:4px solid #e0e7f0;border-top-color:var(--blue);border-radius:50%;animation:spin .8s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
/* Badge sessions */
.sess-badge{display:inline-flex;padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700;background:#f0f3f9;color:#374151;}
.sess-planifiee{background:#dbeafe;color:#1e40af;}
.sess-en_cours{background:#dcfce7;color:#16a34a;}
.sess-terminee{background:#f3f4f6;color:#6b7280;}
.sess-annulee{background:#fee2e2;color:#dc2626;}
/* Taux barre */
.taux-bar{height:8px;border-radius:4px;background:#e5e7eb;overflow:hidden;margin:4px 0;}
.taux-bar-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--gold),var(--blue));transition:width .6s ease;}
/* Timestamp */
.last-update{font-size:.72rem;color:#9ca3af;font-style:italic;}
/* Bouton imprimer dans tableau */
.btn-print-row{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;
    background:#f0f3f9;color:var(--blue);border-radius:6px;text-decoration:none;
    font-size:.82rem;transition:background .2s,color .2s;border:1px solid #e0e7f0;}
.btn-print-row:hover{background:var(--blue);color:#fff;}
</style>
</head>
<body>
<!-- Loader -->
<div class="dash-loader" id="dashLoader"><div class="dash-spinner"></div></div>

<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-tachometer-alt me-2"></i>Tableau de bord</div>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="last-update" id="lastUpdate"><i class="fas fa-sync-alt me-1"></i>Données en temps réel</span>
        <span style="font-size:.78rem;color:#6c7a8d;"><i class="fas fa-calendar me-1"></i><?= date('d/m/Y H:i') ?></span>
        <?php if($stats['online']>0): ?>
        <span style="background:#dcfce7;color:#166534;padding:3px 11px;border-radius:50px;font-size:.76rem;font-weight:700;"><i class="fas fa-circle fa-xs me-1"></i><?= $stats['online'] ?> en ligne</span>
        <?php endif; ?>
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span>
    </div>
</div>

<div class="admin-content">

<!-- ══ BANNIÈRE ══ -->
<div class="banner">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" onerror="this.style.display='none'">
    <div>
        <h1>AIR SECURE — Administration ANAC GABON</h1>
        <p>Plateforme de gestion des examens de certification AVSEC-FAL — Aviation Civile du Gabon</p>
    </div>
    <div class="ms-auto text-center">
        <div class="banner-taux" id="banTaux"><?= $taux_global ?>%</div>
        <div class="banner-taux-lbl">Taux de réussite global</div>
    </div>
</div>

<!-- ══ BARRE DE FILTRES — Power BI style ══ -->
<div class="filter-dash">
    <div style="display:flex;align-items:center;gap:8px;margin-right:6px;">
        <div style="width:32px;height:32px;background:var(--blue);color:var(--gold);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-filter" style="font-size:.85rem;"></i>
        </div>
        <div style="font-weight:800;color:var(--blue);font-size:.88rem;white-space:nowrap;">
            Filtres <span id="filterBadge" style="display:none;" class="filter-active-badge"><i class="fas fa-check"></i>Actif</span>
        </div>
    </div>

    <div class="fg" style="max-width:135px;">
        <span class="fl"><i class="fas fa-calendar-day me-1"></i>Date début (exacte)</span>
        <input type="date" id="fDeb" oninput="scheduleRefresh()">
    </div>
    <div class="fg" style="max-width:135px;">
        <span class="fl"><i class="fas fa-calendar-day me-1"></i>Date fin (exacte)</span>
        <input type="date" id="fFin" oninput="scheduleRefresh()">
    </div>
    <div class="fg" style="max-width:110px;">
        <span class="fl"><i class="fas fa-calendar me-1"></i>Année</span>
        <select id="fAnn" onchange="scheduleRefresh()">
            <option value="">Toutes</option>
            <?php foreach($annees_arr as $ann): ?>
            <option value="<?= $ann ?>"><?= $ann ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fg" style="max-width:155px;">
        <span class="fl"><i class="fas fa-tag me-1"></i>Type d'examen</span>
        <select id="fType" onchange="scheduleRefresh()">
            <option value="">Tous types</option>
            <?php $types_list->data_seek(0);while($t=$types_list->fetch_assoc()): ?>
            <option value="<?= $t['idtype_examen'] ?>"><?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div style="display:flex;gap:6px;align-self:flex-end;">
        <button onclick="refreshDash()" class="btn-filter"><i class="fas fa-sync-alt"></i>Actualiser</button>
        <button onclick="resetFilters()" class="btn-reset"><i class="fas fa-times"></i></button>
    </div>
    <div style="align-self:flex-end;font-size:.72rem;color:#9ca3af;white-space:nowrap;">
        <i class="fas fa-info-circle me-1" style="color:var(--gold);"></i>
        Dates filtrées <strong>exactement</strong><br>
        (20/04→24/04 ≠ 20/04→23/04)
    </div>
</div>

<!-- ══ KPIs DYNAMIQUES ══ -->
<div class="kpi-grid" id="kpiGrid">
    <?php
    $kpis=[
        ['candidats.php','fa-users','#3b82f6','#dbeafe',$stats['candidats'],'Candidats inscrits','kv-candidats'],
        ['sessions.php','fa-calendar-alt','#d97706','#fef3c7',$stats['sessions'],'Total sessions','kv-sessions'],
        ['sessions.php','fa-play-circle','#16a34a','#dcfce7',$stats['en_cours'],'Sessions en cours','kv-encours'],
        ['resultats.php','fa-chart-bar','#7c3aed','#ede9fe',$stats['resultats'],'Examens passés','kv-exams'],
        ['resultats.php','fa-trophy','#b45309','#fef3c7',$stats['reussites'],'Candidats reçus','kv-reussis'],
        ['resultats.php','fa-times-circle','#dc2626','#fee2e2',$stats['echecs'],'Échecs','kv-echecs'],
        ['questions.php','fa-question-circle','#0891b2','#cffafe',$stats['questions'],'Questions banque','kv-questions'],
        ['evaluations.php','fa-star','#0891b2','#e0f2fe',$stats['evaluations'],'Évaluations','kv-eval'],
    ];
    foreach($kpis as [$href,$icon,$col,$bg,$val,$lbl,$kid]):?>
    <a href="<?= $href ?>" class="kpi" style="border-left-color:<?= $col ?>">
        <div class="kpi-ico" style="background:<?= $bg ?>;color:<?= $col ?>"><i class="fas <?= $icon ?>"></i></div>
        <div>
            <div class="kpi-val" style="color:<?= $col ?>" id="<?= $kid ?>"><?= number_format($val) ?></div>
            <div class="kpi-lbl"><?= $lbl ?></div>
        </div>
    </a>
    <?php endforeach;?>
</div>

<!-- ══ GRAPHIQUE + RÉPARTITION TYPES ══ -->
<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="card-admin h-100">
            <div class="card-admin-header">
                <i class="fas fa-chart-bar me-2" style="color:var(--gold)"></i>
                <h5>Résultats par type d'examen</h5>
                <span id="graphNote" class="ms-auto last-update"></span>
            </div>
            <div class="card-admin-body">
                <canvas id="statsChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card-admin h-100">
            <div class="card-admin-header">
                <i class="fas fa-users me-2" style="color:var(--gold)"></i>
                <h5>Répartition par type</h5>
            </div>
            <div class="card-admin-body" id="typeStatsContainer">
                <?php
                $colors=['#3b82f6','#16a34a','#d97706','#7c3aed','#0891b2'];
                foreach($type_stats as $i=>$t):
                    $pct=$stats['candidats']>0?round($t['nb_cand']/$stats['candidats']*100):0;
                    $col=$colors[$i%count($colors)];
                    $tx=$t['nb_exam']>0?round($t['nb_ok']/$t['nb_exam']*100):0;?>
                <div class="tp-row">
                    <span class="tp tp-<?= $t['code'] ?>"><?= $t['code'] ?></span>
                    <div style="flex:1">
                        <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
                            <span style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($t['nom_fr']) ?></span>
                            <span style="font-size:.8rem;color:#6b7280;"><?= $t['nb_cand'] ?> · <span style="color:<?= $tx>=70?'#16a34a':'#dc2626' ?>;font-weight:700;"><?= $tx ?>%</span></span>
                        </div>
                        <div class="taux-bar"><div class="taux-bar-fill" style="width:<?= $tx ?>%"></div></div>
                    </div>
                </div>
                <?php endforeach;?>
            </div>
        </div>
    </div>
</div>

<!-- ══ RAPPORTS D'IMPRESSION ══ -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
    <h4 style="color:var(--blue);font-weight:800;margin:0;"><i class="fas fa-print me-2" style="color:var(--gold)"></i>Générer des rapports d'impression</h4>
    <a href="print_results.php" target="_blank" style="font-size:.8rem;background:var(--blue);color:var(--gold);padding:5px 14px;border-radius:50px;text-decoration:none;font-weight:700;flex-shrink:0;">
        <i class="fas fa-external-link-alt me-1"></i>Rapports avancés par session
    </a>
</div>
<div class="row g-4 mb-4">

    <!-- Relevé individuel -->
    <div class="col-md-3">
        <div class="report-card">
            <h5><i class="fas fa-user me-2"></i>Relevé individuel</h5>
            <p>Résultats complets d'un candidat avec tableau de synthèse IF/FORM.</p>
            <label class="form-label-admin">Sélectionner le candidat</label>
            <select id="rpt_candidat" class="form-select-admin s2rpt" style="margin-bottom:12px;width:100%;">
                <option value="">-- Rechercher un candidat --</option>
                <?php $candidats_list->data_seek(0);while($c=$candidats_list->fetch_assoc()):?>
                <option value="<?= $c['idcandidat'] ?>"><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire'].' ('.$c['code_acces'].')') ?></option>
                <?php endwhile;?>
            </select>
            <button class="btn-anac w-100" onclick="printRpt('candidat')"><i class="fas fa-print me-2"></i>Imprimer le relevé</button>
        </div>
    </div>

    <!-- Rapport de session -->
    <div class="col-md-3">
        <div class="report-card">
            <h5><i class="fas fa-calendar-check me-2"></i>Rapport de session</h5>
            <p>Classement par mérite de tous les candidats d'une session.</p>
            <label class="form-label-admin">Sélectionner la session</label>
            <select id="rpt_session" class="form-select-admin s2rpt" style="margin-bottom:12px;width:100%;">
                <option value="">-- Rechercher une session --</option>
                <?php $sessions_list->data_seek(0);while($s=$sessions_list->fetch_assoc()):?>
                <option value="<?= $s['id_session'] ?>"><?= htmlspecialchars($s['nom_session'].' ['.date('d/m/Y',strtotime($s['date_debut'])).']') ?></option>
                <?php endwhile;?>
            </select>
            <button class="btn-anac w-100" onclick="printRpt('session')"><i class="fas fa-chart-bar me-2"></i>Classement par mérite</button>
        </div>
    </div>

    <!-- Rapport entité -->
    <div class="col-md-3">
        <div class="report-card blue-top">
            <h5><i class="fas fa-building me-2"></i>Rapport par entité</h5>
            <p>Résultats de tous les candidats d'un organisme.</p>
            <label class="form-label-admin">Sélectionner l'organisme</label>
            <select id="rpt_orga" class="form-select-admin s2rpt" style="margin-bottom:12px;width:100%;">
                <option value="">-- Rechercher un organisme --</option>
                <?php $organismes_list->data_seek(0);while($o=$organismes_list->fetch_assoc()):?>
                <option value="<?= $o['idorga'] ?>" data-nb="<?= $o['nb_candidats'] ?>"><?= htmlspecialchars(($o['trigrorganisme']?'['.$o['trigrorganisme'].'] ':'').$o['nomorga'].($o['ville_org']?' — '.$o['ville_org']:'')).' ('.$o['nb_candidats'].' cand.)' ?></option>
                <?php endwhile;?>
            </select>
            <div id="orgaInfo" style="display:none;background:#eff6ff;padding:8px 12px;border-radius:8px;font-size:.82rem;color:#1e40af;margin-bottom:10px;">
                <i class="fas fa-info-circle me-1"></i><span id="orgaInfoTxt"></span>
            </div>
            <button class="btn-gold w-100" onclick="printRpt('orga')"><i class="fas fa-building me-2"></i>Rapport de l'entité</button>
        </div>
    </div>

    <!-- Banque questions -->
    <div class="col-md-3">
        <div class="report-card green-top">
            <h5><i class="fas fa-question-circle me-2"></i>Banque de questions</h5>
            <p>Exporter les questions filtrées par type d'examen.</p>
            <label class="form-label-admin">Filtrer par type</label>
            <select id="rpt_type" class="form-select-admin s2rpt" style="margin-bottom:12px;width:100%;">
                <option value="">Toutes les questions</option>
                <?php $types_list->data_seek(0);while($t=$types_list->fetch_assoc()):?>
                <option value="<?= $t['idtype_examen'] ?>"><?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?></option>
                <?php endwhile;?>
            </select>
            <button class="btn-anac w-100" style="background:linear-gradient(135deg,#16a34a,#15803d)" onclick="printRpt('questions')"><i class="fas fa-list me-2"></i>Exporter les questions</button>
        </div>
    </div>
</div>

<!-- ══ DERNIERS RÉSULTATS + SESSIONS ══ -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-admin">
            <div class="card-admin-header">
                <i class="fas fa-history me-2" style="color:var(--gold)"></i>
                <h5>Derniers résultats</h5>
                <a href="resultats.php" class="ms-auto" style="color:var(--gold);font-size:.8rem;text-decoration:none;font-weight:600;">Voir tous <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-responsive" id="derniersContainer">
                <table class="table-admin" id="tblDerniers">
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Type</th>
                            <th>Session</th>
                            <th>Note</th>
                            <th>%</th>
                            <th>Résultat</th>
                            <th>Date</th>
                            <!-- ① AJOUT : colonne imprimer -->
                            <th style="width:36px;text-align:center;" title="Imprimer relevé">
                                <i class="fas fa-print" style="color:var(--gold);font-size:.8rem;"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="bodyDerniers">
                    <?php while($r=$derniers_res->fetch_assoc()):$p=round($r['pourcentage'],1);?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?></td>
                        <td><span class="tp tp-<?= $r['tc'] ?>"><?= $r['tc'] ?></span></td>
                        <td style="font-size:.82rem;color:#6b7280;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['nom_session']) ?></td>
                        <td style="font-weight:700;color:var(--blue)"><?= round($r['note_finale'],1).'/'.round($r['note_sur'],1) ?>pts</td>
                        <td style="color:<?= $p>=80?'#16a34a':($p>=70?'#ca8a04':'#dc2626') ?>;font-weight:700"><?= $p ?>%</td>
                        <td><?= $r['reussite']?'<span class="badge-status badge-en_cours"><i class="fas fa-check me-1"></i>Réussi</span>':'<span class="badge-status badge-annulee"><i class="fas fa-times me-1"></i>Échec</span>' ?></td>
                        <td style="color:#6b7280;font-size:.8rem"><?= date('d/m/Y H:i',strtotime($r['date_fin'])) ?></td>
                        <!-- ② AJOUT : bouton imprimer par ligne -->
                        <td style="text-align:center;">
                            <a href="print_candidat.php?id=<?= $r['idcandidat'] ?>&session=<?= $r['id_session'] ?>"
                               target="_blank"
                               title="Imprimer le relevé de <?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?>"
                               class="btn-print-row">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-admin h-100">
            <div class="card-admin-header">
                <i class="fas fa-calendar me-2" style="color:var(--gold)"></i>
                <h5>Sessions</h5>
                <a href="sessions.php" class="ms-auto" style="color:var(--gold);font-size:.8rem;text-decoration:none;font-weight:600;">Gérer →</a>
            </div>
            <div class="card-admin-body p-0" id="sessContainer">
                <?php while($ss=$sessions_rec->fetch_assoc()):?>
                <div style="padding:10px 15px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                        <span class="tp tp-<?= $ss['tc'] ?>"><?= $ss['tc'] ?></span>
                        <span class="sess-badge sess-<?= $ss['statut'] ?>"><?= ucfirst($ss['statut']) ?></span>
                        <span style="font-size:.7rem;color:#9ca3af;margin-left:auto;"><?= $ss['nb'] ?> cand.</span>
                    </div>
                    <div style="font-size:.84rem;font-weight:600;color:var(--blue);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ss['nom_session']) ?></div>
                    <div style="font-size:.74rem;color:#9ca3af;"><?= date('d/m/Y',strtotime($ss['date_debut'])) ?> → <?= date('d/m/Y',strtotime($ss['date_fin'])) ?></div>
                </div>
                <?php endwhile;?>
            </div>
        </div>
    </div>
</div>

</div><!-- /admin-content -->
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('sidebarToggle').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));

/* ── Select2 sur les rapports (avec recherche tapable) ── */
$(document).ready(function(){
    $('.s2rpt').select2({width:'100%',placeholder:'Rechercher...',allowClear:true,language:{noResults:()=>'Aucun résultat'}});
    $('#rpt_orga').on('change',function(){
        const nb=$(this).find(':selected').data('nb');
        if($(this).val()){$('#orgaInfoTxt').text(nb+' candidat(s) dans cet organisme');$('#orgaInfo').show();}
        else $('#orgaInfo').hide();
    });
});

/* ── Graphique ── */
const chartCtx=document.getElementById('statsChart').getContext('2d');
let myChart=new Chart(chartCtx,{
    type:'bar',
    data:{
        labels:<?= json_encode($graph_labels) ?>,
        datasets:[
            {label:'Réussis',data:<?= json_encode($graph_ok) ?>,backgroundColor:'rgba(22,163,74,.75)',borderRadius:6},
            {label:'Échecs', data:<?= json_encode($graph_ko) ?>,backgroundColor:'rgba(220,38,38,.65)',borderRadius:6}
        ]
    },
    options:{responsive:true,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:'rgba(0,0,0,.05)'}}}}
});

/* ── Rafraîchissement dynamique ── */
let refreshTimer=null;
function scheduleRefresh(){clearTimeout(refreshTimer);refreshTimer=setTimeout(refreshDash,600);}

function refreshDash(){
    const deb=document.getElementById('fDeb').value;
    const fin=document.getElementById('fFin').value;
    const ann=document.getElementById('fAnn').value;
    const type=document.getElementById('fType').value;

    /* Badge filtre actif */
    const hasFilter=deb||fin||ann||type;
    document.getElementById('filterBadge').style.display=hasFilter?'':'none';

    document.getElementById('dashLoader').classList.add('show');

    $.getJSON('dashboard.php',{ajax:'kpi',f_deb:deb,f_fin:fin,f_ann:ann,f_type:type},function(d){
        document.getElementById('dashLoader').classList.remove('show');
        if(!d){return;}
        const k=d.kpi;

        /* KPIs — animation compteur sur tous */
        animCount('kv-candidats', k.candidats);
        animCount('kv-sessions',  k.sessions);
        animCount('kv-encours',   k.en_cours);
        animCount('kv-exams',     k.exams);
        animCount('kv-reussis',   k.reussis);
        animCount('kv-echecs',    k.echecs);
        animCount('kv-questions', k.questions);
        animCount('kv-eval',      k.evals);

        /* Taux bannière */
        document.getElementById('banTaux').textContent=k.taux+'%';

        /* Graphique */
        const labels=d.type_stats.map(t=>t.code);
        const ok=d.type_stats.map(t=>parseInt(t.nb_ok)||0);
        const ko=d.type_stats.map(t=>(parseInt(t.nb_exam)||0)-(parseInt(t.nb_ok)||0));
        myChart.data.labels=labels;
        myChart.data.datasets[0].data=ok;
        myChart.data.datasets[1].data=ko;
        myChart.update('active');

        /* Note graphique */
        document.getElementById('graphNote').textContent=hasFilter?'(filtré)':'';

        /* Stats types */
        const colors=['#3b82f6','#16a34a','#d97706','#7c3aed','#0891b2'];
        let html='';
        d.type_stats.forEach((t,i)=>{
            const tx=t.nb_exam>0?Math.round(t.nb_ok/t.nb_exam*100):0;
            const col=colors[i%colors.length];
            html+=`<div class="tp-row">
                <span class="tp tp-${t.code}">${t.code}</span>
                <div style="flex:1">
                    <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
                        <span style="font-size:.82rem;font-weight:600">${esc(t.nom_fr)}</span>
                        <span style="font-size:.8rem;color:#6b7280;">${t.nb_cand} · <span style="color:${tx>=70?'#16a34a':'#dc2626'};font-weight:700;">${tx}%</span></span>
                    </div>
                    <div class="taux-bar"><div class="taux-bar-fill" style="width:${tx}%"></div></div>
                </div>
            </div>`;
        });
        document.getElementById('typeStatsContainer').innerHTML=html;

        /* Derniers résultats — avec bouton imprimer (colspan 8) */
        let rows='';
        d.derniers.forEach(r=>{
            const p=Math.round(parseFloat(r.pourcentage)*10)/10;
            const c=p>=80?'#16a34a':p>=70?'#ca8a04':'#dc2626';
            const nom=esc(r.nomstagiaire+' '+r.prenomstagiaire);
            rows+=`<tr>
                <td style="font-weight:600">${nom}</td>
                <td><span class="tp tp-${r.tc}">${r.tc}</span></td>
                <td style="font-size:.82rem;color:#6b7280;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(r.nom_session)}</td>
                <td style="font-weight:700;color:var(--blue)">${Math.round(parseFloat(r.note_finale)*10)/10}/${Math.round(parseFloat(r.note_sur)*10)/10}pts</td>
                <td style="color:${c};font-weight:700">${p}%</td>
                <td>${parseInt(r.reussite)?'<span class="badge-status badge-en_cours"><i class="fas fa-check me-1"></i>Réussi</span>':'<span class="badge-status badge-annulee"><i class="fas fa-times me-1"></i>Échec</span>'}</td>
                <td style="color:#6b7280;font-size:.8rem">${fmtDt(r.date_fin)}</td>
                <td style="text-align:center;">
                    <a href="print_candidat.php?id=${r.idcandidat}&session=${r.id_session}"
                       target="_blank" title="Imprimer relevé de ${nom}"
                       class="btn-print-row">
                        <i class="fas fa-print"></i>
                    </a>
                </td>
            </tr>`;
        });
        if(!rows)rows='<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:20px;">Aucun résultat pour ces filtres</td></tr>';
        document.getElementById('bodyDerniers').innerHTML=rows;

        /* Sessions */
        let shtml='';
        d.sessions.forEach(s=>{
            shtml+=`<div style="padding:10px 15px;border-bottom:1px solid #f3f4f6;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                    <span class="tp tp-${s.tc}">${s.tc}</span>
                    <span class="sess-badge sess-${s.statut}">${s.statut}</span>
                    <span style="font-size:.7rem;color:#9ca3af;margin-left:auto;">${s.nb} cand.</span>
                </div>
                <div style="font-size:.84rem;font-weight:600;color:var(--blue);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(s.nom_session)}</div>
                <div style="font-size:.74rem;color:#9ca3af;">${fmtDate(s.date_debut)} → ${fmtDate(s.date_fin)}</div>
            </div>`;
        });
        if(!shtml)shtml='<div style="text-align:center;color:#9ca3af;padding:20px;font-size:.83rem;">Aucune session</div>';
        document.getElementById('sessContainer').innerHTML=shtml;

        /* Timestamp */
        const now=new Date();
        document.getElementById('lastUpdate').innerHTML='<i class="fas fa-sync-alt me-1"></i>Mis à jour à '+now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0');
    }).fail(()=>{document.getElementById('dashLoader').classList.remove('show');});
}

function resetFilters(){
    ['fDeb','fFin'].forEach(id=>document.getElementById(id).value='');
    ['fAnn','fType'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('filterBadge').style.display='none';
    refreshDash();
}

/* Compteur animé */
function animCount(id,val){
    if(val===null)return;
    const el=document.getElementById(id);if(!el)return;
    const start=parseInt(el.textContent)||0;const end=parseInt(val)||0;
    if(start===end)return;
    const dur=400;const step=Math.ceil(Math.abs(end-start)/(dur/16));
    let cur=start;
    const iv=setInterval(()=>{
        cur+=(end>start?step:-step);
        if((end>start&&cur>=end)||(end<=start&&cur<=end)){cur=end;clearInterval(iv);}
        el.textContent=cur.toLocaleString();
    },16);
}

/* Impression rapports */
function printRpt(type){
    const map={
        'candidat':['rpt_candidat','print_candidat.php?id=','Veuillez choisir un candidat.'],
        'session': ['rpt_session', 'print_session.php?id=', 'Veuillez choisir une session.'],
        'orga':    ['rpt_orga',    'print_organisme.php?id=','Veuillez choisir un organisme.'],
        'questions':['rpt_type',  'print_questions.php?f_type=',''],
    };
    const [selId,url,msg]=map[type];
    const val=document.getElementById(selId).value;
    if(!val&&msg){Swal.fire('Sélection requise',msg,'warning');return;}
    window.open(url+(val||''),'_blank','width=1200,height=860');
}

/* Helpers */
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function fmtDate(d){if(!d)return'';const p=d.substring(0,10).split('-');return p[2]+'/'+p[1]+'/'+p[0];}
function fmtDt(d){if(!d)return'';const dt=new Date(d);return dt.toLocaleDateString('fr-FR')+' '+dt.getHours().toString().padStart(2,'0')+':'+dt.getMinutes().toString().padStart(2,'0');}
</script>
</body></html>
<?php $conn->close(); ?>