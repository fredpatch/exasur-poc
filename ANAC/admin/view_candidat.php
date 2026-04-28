<?php
/**
 * view_candidat.php — Fiche candidat EXASUR ANAC GABON
 * SEUIL UNIQUE : 70% pour TOUS les examens (AS, IF, INST, SENS, FORM)
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: candidats.php"); exit(); }

$c = $conn->query("
    SELECT c.*, s.nomstagiaire, s.prenomstagiaire, s.emailstagiaire,
           s.telstagiaire, s.postestagiaire, s.sexestagiaire, s.datenaiss,
           s.nationalite, o.nomorga
    FROM candidat c
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga = o.idorga
    WHERE c.idcandidat = $id
")->fetch_assoc();
if (!$c) { header("Location: candidats.php"); exit(); }

$hist = $conn->query("
    SELECT r.*, se.nom_session, se.type_session, te.code AS tc, te.nom_fr AS tn,
           te.seuil_reussite, te.a_deux_parties
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    JOIN type_examen te ON r.idtype_examen = te.idtype_examen
    WHERE r.idcandidat = $id AND r.note_finale > 0
    ORDER BY r.date_fin DESC
");

$mods = $conn->query("
    SELECT em.*, mf.nom_module_fr, mf.numero_module, se.nom_session
    FROM evaluation_module em
    JOIN module_formation mf ON em.idmodule = mf.idmodule
    JOIN session_examen se ON em.id_session = se.id_session
    WHERE em.idcandidat = $id
    ORDER BY em.date_eval DESC
");

$nh = $conn->query("SELECT COUNT(*) FROM resultats WHERE idcandidat=$id AND note_finale>0")->fetch_row()[0];
$nr = $conn->query("SELECT COUNT(*) FROM resultats WHERE idcandidat=$id AND reussite=1")->fetch_row()[0];
$ne = $nh - $nr;
$tx = $nh > 0 ? round($nr / $nh * 100, 1) : 0;

$active_page = 'candidats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fiche candidat — ANAC EXASUR</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin_shared.css">
<style>
.tp{display:inline-flex;padding:2px 9px;border-radius:50px;font-size:.7rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
.info-row{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:.86rem;}
.info-row:last-child{border-bottom:none;}
.info-lbl{color:#6c7a8d;font-weight:600;min-width:130px;flex-shrink:0;}
.hist-item{padding:12px 16px;border-bottom:1px solid #f0f2f8;transition:background .2s;}
.hist-item:hover{background:#f8faff;}
.hist-item:last-child{border-bottom:none;}
.prog-bar-wrap{background:#e5e7eb;border-radius:20px;height:20px;overflow:hidden;}
.prog-bar-fill{height:100%;border-radius:20px;font-size:.72rem;font-weight:700;
               display:flex;align-items:center;justify-content:center;color:#fff;
               transition:width .6s ease;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-user me-1"></i>Fiche candidat</div>
    <div class="topbar-breadcrumb">
        <a href="dashboard.php">Accueil</a>
        <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
        <a href="candidats.php">Candidats</a>
        <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
        <span><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></span>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom'] ?? '') ?></span>
    </div>
</div>

<div class="admin-content">
<div class="row g-4">

    <!-- Colonne gauche : identité -->
    <div class="col-md-4">
        <div class="card-admin mb-4">
            <div class="card-admin-header">
                <i class="fas fa-user me-2" style="color:var(--gold)"></i><h5>Identité</h5>
            </div>
            <div class="card-admin-body">
                <div style="text-align:center;margin-bottom:16px;">
                    <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--blue),#0a3a6b);
                         border-radius:50%;display:flex;align-items:center;justify-content:center;
                         color:var(--gold);font-size:1.5rem;margin:0 auto 10px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5 style="color:var(--blue);font-weight:800;margin-bottom:6px;">
                        <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?>
                    </h5>
                    <span style="background:var(--blue);color:white;padding:4px 14px;border-radius:50px;font-weight:700;">
                        <?= $c['code_acces'] ?>
                    </span>
                    <br><br>
                    <?php if ($c['bloque']): ?>
                    <span class="badge-status badge-annulee"><i class="fas fa-lock me-1"></i>Bloqué</span>
                    <?php elseif ($c['is_logged_in']): ?>
                    <span class="badge-status badge-en_cours"><i class="fas fa-circle fa-xs me-1"></i>En ligne</span>
                    <?php else: ?>
                    <span class="badge-status badge-terminee">Hors ligne</span>
                    <?php endif; ?>
                    <?php if ($c['last_login']): ?>
                    <div style="font-size:.73rem;color:#9ca3af;margin-top:6px;">
                        Dernière connexion : <?= date('d/m/Y H:i', strtotime($c['last_login'])) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-envelope me-2"></i>Email</span>
                    <?= htmlspecialchars($c['emailstagiaire'] ?? '—') ?>
                </div>
                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-phone me-2"></i>Tél.</span>
                    <?= htmlspecialchars($c['telstagiaire'] ?? '—') ?>
                </div>
                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-building me-2"></i>Organisme</span>
                    <?= htmlspecialchars($c['nomorga'] ?? '—') ?>
                </div>
                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-briefcase me-2"></i>Poste</span>
                    <?= htmlspecialchars($c['postestagiaire'] ?? '—') ?>
                </div>
                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-flag me-2"></i>Nationalité</span>
                    <?= htmlspecialchars($c['nationalite'] ?? '—') ?>
                </div>
                <div class="info-row">
                    <span class="info-lbl"><i class="fas fa-calendar me-2"></i>Naissance</span>
                    <?= $c['datenaiss'] ? date('d/m/Y', strtotime($c['datenaiss'])) : '—' ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="candidats_edit.php?id=<?= $id ?>" class="btn-gold flex-fill" style="justify-content:center;">
                <i class="fas fa-edit me-1"></i>Modifier
            </a>
            <a href="print_candidat.php?id=<?= $id ?>" target="_blank" class="btn-anac flex-fill" style="justify-content:center;">
                <i class="fas fa-print me-1"></i>Imprimer
            </a>
        </div>
    </div>

    <!-- Colonne droite : stats + historique -->
    <div class="col-md-8">

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <?php foreach ([
                ['fa-file-alt','#2563eb','#dbeafe', $nh,    'Examens'],
                ['fa-check-circle','#16a34a','#dcfce7', $nr, 'Réussites'],
                ['fa-times-circle','#991b1b','#fee2e2', $ne, 'Échecs'],
                ['fa-percent','#D4AF37','#f9f0c4', $tx.'%', 'Taux'],
            ] as $k): ?>
            <div class="col-6 col-md-3">
                <div class="stat-card" style="border-left-color:<?= $k[1] ?>">
                    <div class="stat-icon" style="background:<?= $k[2] ?>;color:<?= $k[1] ?>">
                        <i class="fas <?= $k[0] ?>"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="color:<?= $k[1] ?>"><?= $k[3] ?></div>
                        <div class="stat-label"><?= $k[4] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Historique examens -->
        <div class="card-admin mb-4">
            <div class="card-admin-header">
                <i class="fas fa-history me-2"></i>
                <h5>Historique</h5>
                <span class="badge-count ms-2"><?= $nh ?></span>
            </div>
            <div class="card-admin-body p-0">
                <?php if ($nh == 0): ?>
                <div style="text-align:center;padding:32px;color:#9ca3af;">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    <p>Aucun examen passé</p>
                </div>
                <?php else: while ($r = $hist->fetch_assoc()):
                    $p = round(floatval($r['pourcentage']), 1);
                    $seuil = 70; // SEUIL UNIQUE 70%
                    $ts = $r['type_session'] ?? 'normal';
                    $is_if = ($r['tc'] === 'IF');
                    
                    // Règle unique : score >= 70% = validé
                    $valide = ($p >= 70);
                    $bar_color = $valide ? '#16a34a' : '#dc2626';
                    $badge_txt = $valide ? '✅ Validé' : '❌ Ajourné';
                    $badge_col = $valide ? 'badge-en_cours' : 'badge-annulee';
                ?>
                <div class="hist-item">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <span class="tp tp-<?= $r['tc'] ?>"><?= $r['tc'] ?></span>
                            <?php if ($ts === 'theorie'): ?>
                            <span style="font-size:.68rem;background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:20px;">📖 Th.</span>
                            <?php elseif ($ts === 'pratique'): ?>
                            <span style="font-size:.68rem;background:#fce7f3;color:#9d174d;padding:1px 7px;border-radius:20px;">🖼️ Pr.</span>
                            <?php endif; ?>
                            <span style="font-size:.84rem;font-weight:600;color:var(--blue);">
                                <?= htmlspecialchars($r['nom_session']) ?>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="badge-status <?= $badge_col ?>" style="font-size:.72rem;">
                                <?= $badge_txt ?>
                            </span>
                            <small style="color:#9ca3af;"><?= date('d/m/Y', strtotime($r['date_fin'])) ?></small>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="flex:1;">
                            <div class="prog-bar-wrap">
                                <div class="prog-bar-fill"
                                     style="width:<?= min($p, 100) ?>%;background:<?= $bar_color ?>;">
                                    <?= $p ?>%
                                </div>
                            </div>
                            <div style="font-size:.68rem;color:#6b7280;margin-top:3px;">
                                Seuil de validation : 70%
                            </div>
                            <?php if (!is_null($r['moyenne_if'])): ?>
                            <div style="font-size:.72rem;color:#6b7280;margin-top:2px;">
                                Moyenne IF : <strong><?= round($r['moyenne_if'], 1) ?>%</strong>
                                (théorie + pratique) / 2
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight:700;color:var(--blue);min-width:72px;text-align:right;white-space:nowrap;">
                            <?= round($r['note_finale'], 1) ?>/<?= round($r['note_sur'], 1) ?>pts
                        </div>
                    </div>
                </div>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <!-- Évaluations modules FORM -->
        <?php if ($mods && $mods->num_rows > 0): ?>
        <div class="card-admin">
            <div class="card-admin-header">
                <i class="fas fa-layer-group me-2" style="color:var(--gold)"></i>
                <h5>Évaluations FORM par module</h5>
                <span class="badge-count ms-2"><?= $mods->num_rows ?></span>
            </div>
            <div class="card-admin-body p-0">
                <?php while ($m = $mods->fetch_assoc()):
                    $mp = round(floatval($m['pourcentage']), 1);
                    $mc = $m['reussite'] ? '#16a34a' : '#dc2626';
                ?>
                <div class="hist-item">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
                        <div>
                            <span style="background:var(--blue);color:var(--gold);padding:2px 9px;border-radius:50px;font-size:.75rem;font-weight:800;margin-right:6px;">
                                Mod.<?= $m['numero_module'] ?>
                            </span>
                            <strong style="font-size:.85rem;color:var(--blue);"><?= htmlspecialchars($m['nom_module_fr']) ?></strong>
                            <div style="font-size:.76rem;color:#9ca3af;margin-top:2px;"><?= htmlspecialchars($m['nom_session']) ?></div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="background:<?= $m['reussite']?'#dcfce7':'#fee2e2' ?>;color:<?= $mc ?>;padding:2px 9px;border-radius:50px;font-size:.72rem;font-weight:700;">
                                <?= $m['reussite'] ? '✅ OK' : '❌ Insuffisant' ?>
                            </span>
                            <small style="color:#9ca3af;"><?= $m['date_eval'] ? date('d/m/Y', strtotime($m['date_eval'])) : '' ?></small>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="flex:1;">
                            <div class="prog-bar-wrap">
                                <div class="prog-bar-fill" style="width:<?= min($mp, 100) ?>%;background:<?= $mc ?>;">
                                    <?= $mp ?>%
                                </div>
                            </div>
                            <div style="font-size:.68rem;color:#6b7280;margin-top:3px;">
                                Seuil de validation : 70%
                            </div>
                        </div>
                        <div style="font-weight:700;color:var(--blue);min-width:72px;text-align:right;white-space:nowrap;">
                            <?= round($m['note_obtenue'], 1) ?>/<?= round($m['note_sur'], 1) ?>pts
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div style="text-align:center;margin-top:16px;">
    <a href="candidats.php" class="btn-anac">
        <i class="fas fa-arrow-left me-2"></i>Retour à la liste
    </a>
</div>

</div>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('st').addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('open'));
</script>
</body>
</html>
<?php $conn->close(); ?>