<?php
/**
 * evaluations_modules.php — Gestion Modules FORM & Évaluations EXASUR ANAC
 * admin/evaluations_modules.php
 *
 * FONCTIONNALITÉS :
 *  ① Modules de formation (module_formation) : CRUD, liste par type de formation
 *  ② Suivi évaluations par module (evaluation_module) : résultats par candidat
 *  ③ Calcul de la moyenne FORM par candidat sur tous ses modules évalués
 *     → Seuil de réussite : moyenne ≥ 70%
 *  ④ Tableau de bord : qui a tout passé, qui est en cours, taux de réussite
 *
 * LOGIQUE MÉTIER :
 *  - Une session FORM peut couvrir plusieurs modules
 *  - Chaque module a sa propre date d'évaluation
 *  - Modules évaluables : 2, 3, 4, 6, 8, 9, 11
 *  - Modules sans évaluation : 1, 5, 7, 10, 12
 *  - Moyenne finale = somme des pourcentages / nombre de modules passés
 *  - Un candidat est "validé FORM" si sa moyenne ≥ 70%
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

define('MODULES_EVALUABLES', [2,3,4,6,8,9,11]);
define('SEUIL_FORM', 70);

/* ── Filtres session/candidat ──────────────────────────────── */
$f_sess = intval($_GET['f_sess'] ?? 0);
$f_cand = trim($_GET['f_cand']  ?? '');
$f_mod  = intval($_GET['f_mod'] ?? 0);
$tab    = $_GET['tab'] ?? 'dashboard';

/* ── Modules disponibles ────────────────────────────────────── */
$modules_all = $conn->query("
    SELECT mf.*, tf.nomforma
    FROM module_formation mf
    JOIN si_anac.typeformation tf ON mf.idtypeformation = tf.idtypeforma
    ORDER BY tf.nomforma, mf.numero_module
");
$modules_list = [];
if ($modules_all) while ($m = $modules_all->fetch_assoc()) $modules_list[] = $m;

/* ── Sessions FORM ──────────────────────────────────────────── */
$sessions_form = $conn->query("
    SELECT se.id_session, se.nom_session, se.statut,
           tf.nomforma, se.idtypeformation
    FROM session_examen se
    LEFT JOIN si_anac.typeformation tf ON se.idtypeformation = tf.idtypeforma
    WHERE se.idtype_examen = 5
    ORDER BY se.date_debut DESC
");
$sessions_list = [];
if ($sessions_form) while ($s = $sessions_form->fetch_assoc()) $sessions_list[] = $s;

/* ── Dashboard : résumé par candidat ayant au moins 1 évaluation FORM ── */
$w_cand = "WHERE 1=1";
if ($f_sess) $w_cand .= " AND em.id_session = $f_sess";
if ($f_cand) {
    $fc = $conn->real_escape_string($f_cand);
    $w_cand .= " AND (s.nomstagiaire LIKE '%$fc%' OR s.prenomstagiaire LIKE '%$fc%')";
}

$resume_q = $conn->query("
    SELECT
        c.idcandidat,
        s.nomstagiaire, s.prenomstagiaire,
        o.nomorga,
        se_main.nom_session,
        se_main.idtypeformation,
        tf.nomforma,
        COUNT(DISTINCT em.idmodule)                                   AS nb_modules_passes,
        ROUND(AVG(em.pourcentage), 1)                                 AS moyenne_form,
        SUM(em.reussite)                                              AS nb_reussis,
        MAX(em.date_eval)                                             AS derniere_eval,
        IF(AVG(em.pourcentage) >= ".SEUIL_FORM.", 1, 0)              AS valide_form
    FROM evaluation_module em
    JOIN candidat c ON em.idcandidat = c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga = o.idorga
    JOIN session_examen se_main ON em.id_session = se_main.id_session
    LEFT JOIN si_anac.typeformation tf ON se_main.idtypeformation = tf.idtypeforma
    $w_cand
    GROUP BY c.idcandidat, em.id_session
    ORDER BY valide_form DESC, moyenne_form DESC, s.nomstagiaire
");
$resume_rows = [];
if ($resume_q) while ($r = $resume_q->fetch_assoc()) $resume_rows[] = $r;

/* ── Détail évaluations module par module ───────────────────── */
$w_det = "WHERE 1=1";
if ($f_sess) $w_det .= " AND em.id_session = $f_sess";
if ($f_mod)  $w_det .= " AND em.idmodule = $f_mod";
if ($f_cand) {
    $fc = $conn->real_escape_string($f_cand);
    $w_det .= " AND (st.nomstagiaire LIKE '%$fc%' OR st.prenomstagiaire LIKE '%$fc%')";
}

$detail_q = $conn->query("
    SELECT em.*,
           st.nomstagiaire, st.prenomstagiaire, c.code_acces,
           mf.nom_module_fr, mf.numero_module,
           se.nom_session, se.statut AS sess_statut
    FROM evaluation_module em
    JOIN candidat c ON em.idcandidat = c.idcandidat
    JOIN si_anac.stagiaire st ON c.idstagiaire = st.idstagiaire
    JOIN module_formation mf ON em.idmodule = mf.idmodule
    JOIN session_examen se ON em.id_session = se.id_session
    $w_det
    ORDER BY em.date_eval DESC, st.nomstagiaire
");

/* ── Modules FORM gestion ───────────────────────────────────── */
$msg = '';

/* POST : Ajouter module */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_module') {
    $itf   = intval($_POST['idtypeformation']);
    $num   = intval($_POST['numero_module']);
    $nom_f = $conn->real_escape_string(trim($_POST['nom_module_fr']??''));
    $nom_e = $conn->real_escape_string(trim($_POST['nom_module_en']??''));
    if ($itf && $num && $nom_f) {
        $chk = $conn->prepare("SELECT idmodule FROM module_formation WHERE idtypeformation=? AND numero_module=?");
        $chk->bind_param("ii",$itf,$num); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'error:Ce numéro de module existe déjà pour cette formation.';
        } else {
            $chk->close();
            $conn->query("INSERT INTO module_formation (idtypeformation,numero_module,nom_module_fr,nom_module_en) VALUES ($itf,$num,'$nom_f','$nom_e')");
            $msg = $conn->error ?: 'ok:Module ajouté avec succès.';
        }
    }
    header("Location: evaluations_modules.php?tab=modules&msg=".urlencode($msg)); exit();
}

/* POST : Supprimer module (seulement si pas d'évaluations) */
if (isset($_GET['del_mod'])) {
    $mid = intval($_GET['del_mod']);
    $chk = $conn->query("SELECT COUNT(*) FROM evaluation_module WHERE idmodule=$mid")->fetch_row()[0];
    if ($chk > 0) {
        $msg = 'error:Impossible — des évaluations existent pour ce module.';
    } else {
        $conn->query("DELETE FROM module_formation WHERE idmodule=$mid");
        $msg = 'ok:Module supprimé.';
    }
    header("Location: evaluations_modules.php?tab=modules&msg=".urlencode($msg)); exit();
}

/* Message depuis redirect */
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

/* Formations pour select */
$formations = $conn->query("SELECT idtypeforma, nomforma FROM si_anac.typeformation ORDER BY nomforma");

/* Stats globales */
$nb_evals   = $conn->query("SELECT COUNT(*) FROM evaluation_module")->fetch_row()[0];
$nb_reussi  = $conn->query("SELECT COUNT(*) FROM evaluation_module WHERE reussite=1")->fetch_row()[0];
$nb_mods    = $conn->query("SELECT COUNT(*) FROM module_formation")->fetch_row()[0];
$nb_sess    = $conn->query("SELECT COUNT(*) FROM session_examen WHERE idtype_examen=5")->fetch_row()[0];

$active_page = 'evaluations';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Évaluations FORM — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* ── Onglets ────────────────────────────────────────────── */
.tab-nav{display:flex;gap:4px;margin-bottom:20px;background:#f0f3f9;padding:5px;border-radius:12px;width:fit-content;}
.tab-btn{padding:9px 22px;border:none;border-radius:9px;background:transparent;font-family:inherit;
         font-size:.88rem;font-weight:600;color:#6c7a8d;cursor:pointer;transition:all .2s;
         display:flex;align-items:center;gap:7px;}
.tab-btn.active{background:white;color:var(--blue);box-shadow:0 2px 10px rgba(3,34,76,.12);}
.tc{display:none;}.tc.active{display:block;}

/* KPIs */
.kpi-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px;}
.kpi-c{flex:1;min-width:110px;background:#fff;border-radius:12px;padding:14px 16px;
       text-align:center;box-shadow:0 2px 10px rgba(3,34,76,.07);border-top:3px solid var(--gold);}
.kpi-c .kn{font-size:1.7rem;font-weight:800;color:var(--blue);line-height:1;}
.kpi-c .kl{font-size:.71rem;color:#6b7280;margin-top:4px;font-weight:600;text-transform:uppercase;}

/* Barre de progression résultat */
.pb-wrap{height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;min-width:80px;display:inline-block;vertical-align:middle;}
.pb-fill{height:100%;border-radius:4px;}

/* Badge résultat */
.res-ok{background:#dcfce7;color:#16a34a;padding:2px 9px;border-radius:50px;font-size:.73rem;font-weight:700;}
.res-ko{background:#fee2e2;color:#dc2626;padding:2px 9px;border-radius:50px;font-size:.73rem;font-weight:700;}
.res-en{background:#dbeafe;color:#1e40af;padding:2px 9px;border-radius:50px;font-size:.73rem;font-weight:700;}

/* Modules list */
.mod-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;
           font-size:.73rem;font-weight:700;margin:2px;}
.mod-eval{background:#dbeafe;color:#1e40af;}
.mod-noeval{background:#f3f4f6;color:#9ca3af;}

/* Filtre bar */
.fbar{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;
      background:#f8faff;border:1.5px solid #e0e7f0;border-radius:12px;
      padding:14px 16px;margin-bottom:16px;}
.fg{display:flex;flex-direction:column;gap:3px;flex:1;min-width:140px;}
.fg label{font-size:.74rem;font-weight:700;color:var(--blue);}
.fi{padding:7px 10px;border:1.5px solid #d1d5db;border-radius:8px;
    font-family:inherit;font-size:.84rem;}
.fi:focus{outline:none;border-color:var(--blue);}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-layer-group me-2"></i>Évaluations FORM — Modules</div>
    <div class="ms-auto">
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']??'') ?></span>
    </div>
</div>

<div class="admin-content">

<!-- KPIs -->
<div class="kpi-row">
    <div class="kpi-c"><div class="kn"><?= $nb_sess ?></div><div class="kl"><i class="fas fa-calendar me-1"></i>Sessions FORM</div></div>
    <div class="kpi-c" style="border-top-color:#1e40af;"><div class="kn" style="color:#1e40af;"><?= $nb_mods ?></div><div class="kl"><i class="fas fa-layer-group me-1"></i>Modules définis</div></div>
    <div class="kpi-c" style="border-top-color:#16a34a;"><div class="kn" style="color:#16a34a;"><?= $nb_evals ?></div><div class="kl"><i class="fas fa-pen me-1"></i>Évaluations passées</div></div>
    <div class="kpi-c" style="border-top-color:#7c3aed;"><div class="kn" style="color:#7c3aed;"><?= $nb_evals>0?round($nb_reussi/$nb_evals*100,1):0 ?>%</div><div class="kl"><i class="fas fa-chart-bar me-1"></i>Taux réussite</div></div>
</div>

<!-- Onglets -->
<div class="tab-nav">
    <button class="tab-btn <?= $tab==='dashboard'?'active':'' ?>" onclick="goTab('dashboard',this)">
        <i class="fas fa-tachometer-alt"></i>Tableau de bord
    </button>
    <button class="tab-btn <?= $tab==='detail'?'active':'' ?>" onclick="goTab('detail',this)">
        <i class="fas fa-list-alt"></i>Résultats par module
    </button>
    <button class="tab-btn <?= $tab==='modules'?'active':'' ?>" onclick="goTab('modules',this)">
        <i class="fas fa-cog"></i>Gérer les modules
    </button>
</div>

<!-- ════════════════════════════════════════════════════════════
     ONGLET 1 : TABLEAU DE BORD — Résumé par candidat
════════════════════════════════════════════════════════════ -->
<div id="tab-dashboard" class="tc <?= $tab==='dashboard'?'active':'' ?>">

    <!-- Filtres -->
    <form method="GET">
        <input type="hidden" name="tab" value="dashboard">
        <div class="fbar">
            <div class="fg">
                <label><i class="fas fa-calendar me-1"></i>Session FORM</label>
                <select name="f_sess" class="fi">
                    <option value="">Toutes les sessions</option>
                    <?php foreach ($sessions_list as $sl): ?>
                    <option value="<?= $sl['id_session'] ?>" <?= $f_sess==$sl['id_session']?'selected':'' ?>>
                        <?= htmlspecialchars($sl['nom_session']) ?>
                        <? echo $sl['statut']==='terminee' ? ' ✓' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label><i class="fas fa-user me-1"></i>Candidat</label>
                <input type="text" name="f_cand" class="fi" placeholder="Nom..." value="<?= htmlspecialchars($f_cand) ?>">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn-anac" style="padding:8px 16px;"><i class="fas fa-search me-1"></i>Filtrer</button>
                <a href="evaluations_modules.php?tab=dashboard" class="btn-anac" style="background:#e8ecf5;color:var(--blue);border-color:#c8d0e0;padding:8px 12px;text-decoration:none;"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>

    <div class="card-admin">
        <div class="card-admin-header">
            <i class="fas fa-users me-2"></i><h5>Résumé par candidat — Moyenne FORM</h5>
            <span class="badge-count ms-2"><?= count($resume_rows) ?></span>
            <div style="margin-left:auto;font-size:.76rem;color:#9ca3af;">
                Seuil de validation : <strong style="color:var(--blue);"><?= SEUIL_FORM ?>%</strong> (moyenne de tous les modules)
            </div>
        </div>
        <div class="card-admin-body p-0">
            <?php if (empty($resume_rows)): ?>
            <div style="text-align:center;padding:36px;color:#9ca3af;">
                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                <p>Aucune évaluation FORM trouvée.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="table-admin">
                <thead>
                    <tr>
                        <th>Candidat</th><th>Formation</th><th>Session</th>
                        <th>Modules passés</th><th>Réussis</th>
                        <th>Moyenne FORM</th><th>Score</th>
                        <th>Statut global</th><th>Dernière éval.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resume_rows as $r):
                    $moy = floatval($r['moyenne_form']);
                    $col = $moy >= SEUIL_FORM ? '#16a34a' : ($moy >= 50 ? '#d97706' : '#dc2626');
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;color:var(--blue);">
                            <?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?>
                        </div>
                        <div style="font-size:.74rem;color:#9ca3af;"><?= htmlspecialchars($r['nomorga']??'') ?></div>
                    </td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($r['nomforma']??'—') ?></td>
                    <td style="font-size:.78rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($r['nom_session']??'') ?>
                    </td>
                    <td style="text-align:center;font-weight:700;color:var(--blue);"><?= $r['nb_modules_passes'] ?></td>
                    <td style="text-align:center;">
                        <span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:50px;font-weight:700;font-size:.8rem;">
                            <?= $r['nb_reussis'] ?>/<?= $r['nb_modules_passes'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="pb-wrap" style="width:80px;">
                                <div class="pb-fill" style="width:<?= min($moy,100) ?>%;background:<?= $col ?>;"></div>
                            </div>
                            <strong style="color:<?= $col ?>;font-size:.9rem;"><?= $moy ?>%</strong>
                        </div>
                    </td>
                    <td style="font-size:.8rem;color:#6b7280;">
                        Seuil : <?= SEUIL_FORM ?>%
                    </td>
                    <td>
                        <?php if ($r['valide_form']): ?>
                        <span class="res-ok"><i class="fas fa-check-circle me-1"></i>Validé FORM</span>
                        <?php elseif ($r['nb_modules_passes'] > 0): ?>
                        <span class="res-ko"><i class="fas fa-times-circle me-1"></i>Non validé</span>
                        <?php else: ?>
                        <span class="res-en">En cours</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">
                        <?= $r['derniere_eval'] ? date('d/m/Y', strtotime($r['derniere_eval'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Légende -->
    <div style="background:#f8faff;border-radius:10px;padding:12px 16px;margin-top:12px;font-size:.82rem;color:#6b7280;">
        <i class="fas fa-info-circle me-2" style="color:var(--gold);"></i>
        <strong>Comment fonctionne la moyenne FORM :</strong>
        Chaque module est noté en pourcentage → La moyenne finale = somme des % / nombre de modules passés.
        Si un candidat passe les modules 2, 4 et 9 avec respectivement 75%, 80% et 65%,
        sa moyenne = (75+80+65)/3 = <strong>73.3%</strong> → <span style="color:#16a34a;font-weight:700;">Validé</span> (≥70%).
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ONGLET 2 : RÉSULTATS DÉTAILLÉS PAR MODULE
════════════════════════════════════════════════════════════ -->
<div id="tab-detail" class="tc <?= $tab==='detail'?'active':'' ?>">

    <!-- Filtres -->
    <form method="GET">
        <input type="hidden" name="tab" value="detail">
        <div class="fbar">
            <div class="fg">
                <label><i class="fas fa-calendar me-1"></i>Session</label>
                <select name="f_sess" class="fi">
                    <option value="">Toutes</option>
                    <?php foreach ($sessions_list as $sl): ?>
                    <option value="<?= $sl['id_session'] ?>" <?= $f_sess==$sl['id_session']?'selected':'' ?>>
                        <?= htmlspecialchars($sl['nom_session']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label><i class="fas fa-layer-group me-1"></i>Module</label>
                <select name="f_mod" class="fi">
                    <option value="">Tous les modules</option>
                    <?php foreach ($modules_list as $ml): ?>
                    <option value="<?= $ml['idmodule'] ?>" <?= $f_mod==$ml['idmodule']?'selected':'' ?>>
                        <?= htmlspecialchars('Mod.'.$ml['numero_module'].' — '.$ml['nom_module_fr']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label><i class="fas fa-user me-1"></i>Candidat</label>
                <input type="text" name="f_cand" class="fi" placeholder="Nom..." value="<?= htmlspecialchars($f_cand) ?>">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit" class="btn-anac" style="padding:8px 16px;"><i class="fas fa-search me-1"></i>Filtrer</button>
                <a href="evaluations_modules.php?tab=detail" class="btn-anac" style="background:#e8ecf5;color:var(--blue);border-color:#c8d0e0;padding:8px 12px;text-decoration:none;"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>

    <div class="card-admin">
        <div class="card-admin-header">
            <i class="fas fa-list-alt me-2"></i><h5>Résultats par module d'évaluation</h5>
        </div>
        <div class="card-admin-body p-0">
            <div style="overflow-x:auto;">
            <table class="table-admin">
                <thead>
                    <tr><th>Candidat</th><th>Code</th><th>Module</th><th>Session</th>
                        <th>Note</th><th>Score</th><th>Résultat</th><th>Date éval.</th></tr>
                </thead>
                <tbody>
                <?php if ($detail_q && $detail_q->num_rows > 0):
                    while ($d = $detail_q->fetch_assoc()):
                    $p   = round(floatval($d['pourcentage']),1);
                    $col = $p >= SEUIL_FORM ? '#16a34a' : '#dc2626';
                ?>
                <tr>
                    <td><div style="font-weight:700;"><?= htmlspecialchars($d['nomstagiaire'].' '.$d['prenomstagiaire']) ?></div></td>
                    <td><span style="background:var(--blue);color:#fff;padding:2px 9px;border-radius:50px;font-weight:700;font-size:.82rem;"><?= $d['code_acces'] ?></span></td>
                    <td>
                        <span style="background:var(--blue);color:var(--gold);padding:2px 9px;border-radius:50px;font-weight:800;font-size:.75rem;margin-right:5px;">
                            Mod.<?= $d['numero_module'] ?>
                        </span>
                        <span style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($d['nom_module_fr']) ?></span>
                    </td>
                    <td style="font-size:.78rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($d['nom_session']) ?>
                    </td>
                    <td style="font-weight:700;">
                        <?= round($d['note_obtenue'],1) ?>/<?= $d['note_sur'] ?>pts
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="pb-wrap" style="width:70px;">
                                <div class="pb-fill" style="width:<?= min($p,100) ?>%;background:<?= $col ?>;"></div>
                            </div>
                            <strong style="color:<?= $col ?>;font-size:.88rem;"><?= $p ?>%</strong>
                        </div>
                    </td>
                    <td>
                        <?= $d['reussite']
                            ? '<span class="res-ok">✅ Réussi</span>'
                            : '<span class="res-ko">❌ Insuffisant</span>' ?>
                    </td>
                    <td style="font-size:.75rem;color:#9ca3af;white-space:nowrap;">
                        <?= $d['date_eval'] ? date('d/m/Y H:i', strtotime($d['date_eval'])) : '—' ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#9ca3af;">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Aucune évaluation trouvée.
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ONGLET 3 : GÉRER LES MODULES DE FORMATION
════════════════════════════════════════════════════════════ -->
<div id="tab-modules" class="tc <?= $tab==='modules'?'active':'' ?>">

    <!-- Info modules évaluables -->
    <div style="background:#f0f9ff;border:1.5px solid #93c5fd;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.85rem;color:#1e40af;">
        <i class="fas fa-info-circle me-2" style="color:#3b82f6;"></i>
        <strong>Modules évaluables :</strong> numéros 2, 3, 4, 6, 8, 9, 11 &nbsp;·&nbsp;
        <strong>Sans évaluation :</strong> 1, 5, 7, 10, 12 (cours uniquement)
    </div>

    <!-- Formulaire ajout module -->
    <div class="add-panel mb-4">
        <div class="add-panel-header" onclick="document.getElementById('addModBody').classList.toggle('d-none')">
            <i class="fas fa-plus-circle" style="color:var(--gold)"></i>
            <span style="font-weight:700;">Ajouter un module de formation</span>
            <i class="fas fa-chevron-down ms-auto"></i>
        </div>
        <div class="add-panel-body d-none" id="addModBody">
            <form method="POST">
                <input type="hidden" name="action" value="add_module">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label-admin">Type de formation *</label>
                        <select name="idtypeformation" class="form-select-admin" required>
                            <option value="">-- Choisir --</option>
                            <?php if ($formations) { $formations->data_seek(0); while($f=$formations->fetch_assoc()): ?>
                            <option value="<?= $f['idtypeforma'] ?>"><?= htmlspecialchars($f['nomforma']) ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-admin">N° Module *</label>
                        <input type="number" name="numero_module" class="form-control-admin" min="1" max="20" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label-admin">🇫🇷 Nom du module *</label>
                        <input type="text" name="nom_module_fr" class="form-control-admin" required placeholder="Ex : Cadre réglementaire">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label-admin">🇬🇧 Nom EN</label>
                        <input type="text" name="nom_module_en" class="form-control-admin" placeholder="Module name in English">
                    </div>
                </div>
                <div class="d-flex gap-3 mt-3">
                    <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Enregistrer</button>
                    <button type="button" class="btn-anac" style="background:white;color:var(--blue);"
                            onclick="document.getElementById('addModBody').classList.add('d-none')">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau modules -->
    <div class="card-admin">
        <div class="card-admin-header">
            <i class="fas fa-layer-group me-2"></i><h5>Modules de formation définis</h5>
            <span class="badge-count ms-2"><?= $nb_mods ?></span>
        </div>
        <div class="card-admin-body p-0">
            <div style="overflow-x:auto;">
            <table class="table-admin">
                <thead><tr><th>#</th><th>Formation</th><th>Module</th><th>Nom FR</th><th>Nom EN</th><th>Évaluable</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($modules_list as $ml):
                    $evaluable = in_array($ml['numero_module'], MODULES_EVALUABLES);
                    $nb_ev = $conn->query("SELECT COUNT(*) FROM evaluation_module WHERE idmodule={$ml['idmodule']}")->fetch_row()[0];
                ?>
                <tr>
                    <td style="color:#9ca3af;font-weight:600;"><?= $ml['idmodule'] ?></td>
                    <td style="font-size:.84rem;"><?= htmlspecialchars($ml['nomforma']) ?></td>
                    <td>
                        <span style="background:var(--blue);color:var(--gold);padding:2px 10px;border-radius:50px;font-weight:800;font-size:.78rem;">
                            Mod.<?= $ml['numero_module'] ?>
                        </span>
                    </td>
                    <td style="font-weight:600;"><?= htmlspecialchars($ml['nom_module_fr']) ?></td>
                    <td style="font-size:.82rem;color:#6b7280;"><?= htmlspecialchars($ml['nom_module_en']) ?></td>
                    <td>
                        <?= $evaluable
                            ? '<span class="mod-badge mod-eval"><i class="fas fa-check me-1"></i>Évaluable</span>'
                            : '<span class="mod-badge mod-noeval">Cours uniquement</span>' ?>
                        <?php if ($nb_ev > 0): ?>
                        <span style="font-size:.72rem;color:#9ca3af;margin-left:4px;"><?= $nb_ev ?> éval.</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($nb_ev == 0): ?>
                        <a href="evaluations_modules.php?del_mod=<?= $ml['idmodule'] ?>"
                           class="btn-icon" style="background:#fee2e2;color:#dc2626;"
                           title="Supprimer"
                           onclick="return confirm('Supprimer le module <?= htmlspecialchars($ml['nom_module_fr']) ?> ?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php else: ?>
                        <span class="btn-icon btn-icon-disabled" title="Des évaluations existent — non supprimable">
                            <i class="fas fa-lock"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

</div>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('st').addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('open'));

function goTab(id, btn) {
    document.querySelectorAll('.tc').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    if (btn) btn.classList.add('active');
}

/* ── Notifications ─────────────────────────────────────────── */
<?php if (str_starts_with($msg,'ok:')): ?>
Swal.fire({icon:'success',title:'✅ '+<?= json_encode(substr($msg,3)) ?>,timer:3000,timerProgressBar:true,
    showConfirmButton:false,toast:true,position:'top-end'});
<?php elseif (str_starts_with($msg,'error:')): ?>
Swal.fire({icon:'error',title:'Erreur',text:<?= json_encode(substr($msg,6)) ?>,confirmButtonColor:'#dc2626'});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>