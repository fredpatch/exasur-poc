<?php
/**
 * candidats_edit.php — Fiche détaillée + actions admin sur un candidat
 * ANAC GABON — EXASUR
 * Corrections :
 *  - Bouton "Bloquer le compte" fonctionne via AJAX (sans rechargement)
 *  - Mentions VALIDÉ/AJOURNÉ dans l'historique
 *  - Reset mot de passe avec SweetAlert2
 *  - PDO préparé pour toutes les requêtes de modification
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: candidats.php"); exit(); }

/* ── Lecture candidat ────────────────────────────────────────── */
$c = $conn->query("
    SELECT c.*, s.nomstagiaire, s.prenomstagiaire, s.emailstagiaire, s.telstagiaire,
           s.postestagiaire, s.sexestagiaire, s.datenaiss, o.nomorga
    FROM candidat c
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga = o.idorga
    WHERE c.idcandidat = $id
")->fetch_assoc();

if (!$c) { header("Location: candidats.php"); exit(); }

$msg   = '';
$alert = 'success';

/* ── Réinitialiser le mot de passe ──────────────────────────── */
function genMdp($n = 8) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';
    return substr(str_shuffle($chars), 0, $n);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pw') {
    $new  = genMdp(9);
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $st   = $conn->prepare("UPDATE candidat SET mot_de_passe = ?, bloque = 0, tentatives = 0 WHERE idcandidat = ?");
    $st->bind_param("si", $hash, $id);
    $st->execute();
    $st->close();
    $msg   = 'reset:' . $new;
    $alert = 'password';
    // Recharger les données candidat
    $c = $conn->query("SELECT c.*,s.nomstagiaire,s.prenomstagiaire,s.emailstagiaire,s.telstagiaire,s.postestagiaire,s.sexestagiaire,s.datenaiss,o.nomorga FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire LEFT JOIN si_anac.organisme o ON s.idorga=o.idorga WHERE c.idcandidat=$id")->fetch_assoc();
}

/* ── Débloquer (reset is_logged_in) ─────────────────────────── */
if (isset($_GET['deblock'])) {
    $st = $conn->prepare("UPDATE candidat SET is_logged_in = 0, bloque = 0, tentatives = 0 WHERE idcandidat = ?");
    $st->bind_param("i", $id);
    $st->execute();
    $st->close();
    $_SESSION['edit_msg'] = 'Candidat débloqué avec succès.';
    header("Location: candidats_edit.php?id=$id");
    exit();
}

/* ── AJAX : Bloquer / débloquer le compte ───────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_bloque') {
    header('Content-Type: application/json');
    $bloque = intval($_POST['bloque'] ?? 0);
    $st     = $conn->prepare("UPDATE candidat SET bloque = ?, is_logged_in = 0 WHERE idcandidat = ?");
    $st->bind_param("ii", $bloque, $id);
    $ok = $st->execute();
    $st->close();
    echo json_encode([
        'success' => $ok,
        'bloque'  => $bloque,
        'message' => $bloque ? 'Compte bloqué avec succès.' : 'Compte débloqué avec succès.',
    ]);
    exit();
}

/* ── Données complémentaires ────────────────────────────────── */
$hist = $conn->query("
    SELECT r.*, se.nom_session, te.code AS tc
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    JOIN type_examen te ON r.idtype_examen = te.idtype_examen
    WHERE r.idcandidat = $id
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
$sess_hab = $conn->query("
    SELECT cs.*, se.nom_session, te.code AS tc, se.date_debut, se.statut
    FROM candidat_session cs
    JOIN session_examen se ON cs.id_session = se.id_session
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    WHERE cs.idcandidat = $id
    ORDER BY se.date_debut DESC
");

$nh = $conn->query("SELECT COUNT(*) FROM resultats WHERE idcandidat=$id")->fetch_row()[0];
$nr = $conn->query("SELECT COUNT(*) FROM resultats WHERE idcandidat=$id AND reussite=1")->fetch_row()[0];
$tx = $nh > 0 ? round($nr / $nh * 100, 1) : 0;

$active_page = 'candidats';
$emsg = $_SESSION['edit_msg'] ?? '';
unset($_SESSION['edit_msg']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fiche candidat — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.tp{display:inline-flex;padding:2px 9px;border-radius:50px;font-size:.7rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
.info-row{display:flex;gap:12px;padding:9px 0;border-bottom:1px solid #f3f4f6;font-size:.87rem;}
.info-row:last-child{border-bottom:none;}
.info-label{color:#6c7a8d;font-weight:600;min-width:140px;flex-shrink:0;}
.info-val{color:#1a1f2e;font-weight:500;}

/* Toggle switch animé */
.toggle-switch{position:relative;display:inline-block;width:52px;height:28px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{
    position:absolute;inset:0;cursor:pointer;
    border-radius:50px;transition:.3s;
    background:#e5e7eb;
}
.toggle-slider::before{
    content:'';position:absolute;
    width:22px;height:22px;background:white;border-radius:50%;
    left:3px;top:3px;transition:.3s;
    box-shadow:0 2px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-slider{background:#dc2626;}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(24px);}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<!-- Topbar -->
<div class="admin-topbar">
  <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
  <div class="topbar-title"><i class="fas fa-user-edit"></i> Fiche candidat</div>
  <div class="topbar-breadcrumb">
    <a href="dashboard.php">Accueil</a>
    <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
    <a href="candidats.php">Candidats</a>
    <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
    <span><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></span>
  </div>
  <div class="ms-auto d-flex align-items-center gap-2">
    <i class="fas fa-user-shield text-muted"></i>
    <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span>
  </div>
</div>

<div class="admin-content">
<div class="row g-4">

  <!-- ══ Colonne gauche ════════════════════════════════════════ -->
  <div class="col-lg-5">

    <!-- Fiche candidat -->
    <div class="card-admin mb-4">
      <div class="card-admin-header">
        <i class="fas fa-user me-2" style="color:var(--gold)"></i>
        <h5>Fiche candidat</h5>
      </div>
      <div class="card-admin-body">
        <div style="text-align:center;margin-bottom:18px;">
          <div style="width:68px;height:68px;background:linear-gradient(135deg,var(--blue),#0a3a6b);
                      border-radius:50%;display:flex;align-items:center;justify-content:center;
                      color:var(--gold);font-size:1.6rem;margin:0 auto 10px;">
            <i class="fas fa-user"></i>
          </div>
          <h4 style="color:var(--blue);font-weight:800;margin-bottom:6px;">
            <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?>
          </h4>
          <span style="background:var(--blue);color:white;padding:4px 14px;border-radius:50px;font-weight:700;font-size:1rem;letter-spacing:1px;">
            <?= htmlspecialchars($c['code_acces']) ?>
          </span>
        </div>

        <div class="info-row">
          <span class="info-label"><i class="fas fa-envelope me-2"></i>Email</span>
          <span class="info-val"><?= htmlspecialchars($c['emailstagiaire']??'—') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-phone me-2"></i>Téléphone</span>
          <span class="info-val"><?= htmlspecialchars($c['telstagiaire']??'—') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-building me-2"></i>Organisme</span>
          <span class="info-val"><?= htmlspecialchars($c['nomorga']??'—') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-briefcase me-2"></i>Poste</span>
          <span class="info-val"><?= htmlspecialchars($c['postestagiaire']??'—') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-calendar me-2"></i>Naissance</span>
          <span class="info-val"><?= $c['datenaiss'] ? date('d/m/Y', strtotime($c['datenaiss'])) : '—' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-clock me-2"></i>Dernière co.</span>
          <span class="info-val"><?= $c['last_login'] ? date('d/m/Y H:i', strtotime($c['last_login'])) : 'Jamais' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><i class="fas fa-info-circle me-2"></i>Statut</span>
          <span class="info-val" id="statutBadge">
            <?php if ($c['bloque']): ?>
            <span class="badge-status badge-annulee"><i class="fas fa-lock me-1"></i>Bloqué</span>
            <?php elseif ($c['is_logged_in']): ?>
            <span class="badge-status badge-en_cours"><i class="fas fa-circle fa-xs me-1"></i>En ligne</span>
            <?php else: ?>
            <span class="badge-status badge-terminee">Hors ligne</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Actions administratives -->
    <div class="card-admin mb-4">
      <div class="card-admin-header">
        <i class="fas fa-tools me-2" style="color:var(--gold)"></i>
        <h5>Actions administratives</h5>
      </div>
      <div class="card-admin-body">

        <!-- ── Toggle Bloquer / Débloquer (AJAX) ──────────────── -->
        <div style="display:flex;align-items:center;gap:14px;padding:14px;
                    background:#f8faff;border-radius:12px;border:1.5px solid var(--gray-border);
                    margin-bottom:12px;">
          <div style="flex:1;">
            <div style="font-weight:700;font-size:.9rem;color:var(--blue);" id="bloquerLabel">
              <?= $c['bloque'] ? '🔒 Compte bloqué' : '🔓 Compte actif'; ?>
            </div>
            <div style="font-size:.78rem;color:#6c7a8d;" id="bloquerSubLabel">
              <?= $c['bloque'] ? 'Cliquez pour débloquer l\'accès.' : 'Basculez pour bloquer l\'accès au candidat.'; ?>
            </div>
          </div>
          <!-- Toggle switch -->
          <label class="toggle-switch" title="Bloquer / Débloquer">
            <input type="checkbox" id="toggleBloque"
                   <?= $c['bloque'] ? 'checked' : '' ?>
                   onchange="toggleBloque(this)">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <!-- ── Reset mot de passe ─────────────────────────────── -->
        <form method="POST" class="mb-2">
          <input type="hidden" name="action" value="reset_pw">
          <button type="submit" class="btn-anac w-100"
                  onclick="return confirm('Générer un nouveau mot de passe pour ce candidat ?')"
                  style="background:white;color:var(--blue);border-color:var(--gray-border);">
            <i class="fas fa-key me-2"></i>Réinitialiser le mot de passe
          </button>
        </form>

        <!-- ── Forcer déconnexion ─────────────────────────────── -->
        <?php if ($c['is_logged_in']): ?>
        <a href="?id=<?= $id ?>&deblock=1"
           class="btn-anac w-100 mb-2"
           style="background:white;color:var(--warn);border-color:var(--warn);justify-content:center;"
           onclick="return confirm('Forcer la déconnexion de ce candidat ?')">
          <i class="fas fa-sign-out-alt me-2"></i>Forcer la déconnexion
        </a>
        <?php endif; ?>

        <!-- ── Liens rapides ─────────────────────────────────── -->
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
          <a href="view_candidat.php?id=<?= $id ?>" class="btn-anac"
             style="flex:1;justify-content:center;font-size:.82rem;padding:8px;">
            <i class="fas fa-eye me-1"></i>Voir fiche
          </a>
          <a href="print_candidat.php?id=<?= $id ?>" target="_blank" class="btn-anac"
             style="flex:1;justify-content:center;font-size:.82rem;padding:8px;">
            <i class="fas fa-print me-1"></i>Imprimer fiche
          </a>
          <a href="print_results.php?candidat=<?= $id ?>" target="_blank" class="btn-anac"
             style="flex:1;justify-content:center;font-size:.82rem;padding:8px;background:#f0fdf4;color:#166534;border-color:#bbf7d0;">
            <i class="fas fa-file-invoice me-1"></i>Relevé notes
          </a>
        </div>
      </div>
    </div>

  </div><!-- /col gauche -->


  <!-- ══ Colonne droite ════════════════════════════════════════ -->
  <div class="col-lg-7">

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6">
        <div class="stat-card" style="border-left-color:#2563eb">
          <div class="stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-file-alt"></i></div>
          <div>
            <div class="stat-value" style="color:#2563eb"><?= $nh ?></div>
            <div class="stat-label">Examens passés</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card" style="border-left-color:#16a34a">
          <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-check-circle"></i></div>
          <div>
            <div class="stat-value" style="color:#16a34a"><?= $nr ?></div>
            <div class="stat-label">Validés</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card" style="border-left-color:#dc2626">
          <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
          <div>
            <div class="stat-value" style="color:#dc2626"><?= $nh - $nr ?></div>
            <div class="stat-label">Ajournés</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card" style="border-left-color:#D4AF37">
          <div class="stat-icon" style="background:#f9f0c4;color:#92400e"><i class="fas fa-percent"></i></div>
          <div>
            <div class="stat-value" style="color:#D4AF37"><?= $tx ?>%</div>
            <div class="stat-label">Taux de validation</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Historique examens -->
    <div class="card-admin mb-4">
      <div class="card-admin-header">
        <i class="fas fa-history me-2"></i><h5>Historique des examens</h5>
        <span class="badge-count ms-2"><?= $nh ?></span>
      </div>
      <div class="card-admin-body p-0">
        <?php if ($nh == 0): ?>
        <div style="text-align:center;padding:40px;color:#9ca3af;">
          <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
          <p>Aucun examen passé</p>
        </div>
        <?php else:
            $hist->data_seek(0);
            while ($r = $hist->fetch_assoc()):
                $p       = round($r['pourcentage'], 1);
                $valide  = ($r['reussite'] == 1);
                $mention = $valide ? '✅ VALIDÉ' : '⛔ AJOURNÉ';
                $col_m   = $valide ? '#16a34a' : '#dc2626';
        ?>
        <div style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
            <div>
              <div style="font-weight:700;font-size:.88rem;color:var(--blue);">
                <?= htmlspecialchars($r['nom_session']) ?>
              </div>
              <div style="font-size:.76rem;color:#9ca3af;">
                <i class="fas fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($r['date_fin'])) ?>
              </div>
            </div>
            <span style="font-size:.82rem;font-weight:700;color:<?= $col_m ?>;"><?= $mention ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex:1;">
              <div style="height:22px;border-radius:20px;background:#e5e7eb;overflow:hidden;">
                <div style="width:<?= $p ?>%;height:100%;border-radius:20px;
                             background:<?= $valide ? '#16a34a' : '#dc2626' ?>;
                             display:flex;align-items:center;justify-content:flex-end;
                             padding-right:8px;font-size:.74rem;font-weight:700;color:white;">
                  <?= $p ?>%
                </div>
              </div>
            </div>
            <div style="min-width:80px;text-align:right;font-weight:700;color:var(--blue);font-size:.9rem;">
              <?= round($r['note_finale'], 1) ?>/<?= round($r['note_sur'], 1) ?>pts
            </div>
          </div>
        </div>
        <?php endwhile; endif; ?>
      </div>
    </div>

    <!-- Sessions habilités -->
    <div class="card-admin">
      <div class="card-admin-header">
        <i class="fas fa-calendar-check me-2"></i><h5>Sessions habilités</h5>
      </div>
      <div class="card-admin-body p-0">
        <div class="table-responsive">
          <table class="table-admin">
            <thead><tr><th>Session</th><th>Type</th><th>Date</th><th>Statut</th></tr></thead>
            <tbody>
            <?php while ($s = $sess_hab->fetch_assoc()): ?>
            <tr>
              <td style="font-size:.85rem;"><?= htmlspecialchars($s['nom_session']) ?></td>
              <td><span class="tp tp-<?= $s['tc'] ?>"><?= $s['tc'] ?></span></td>
              <td style="font-size:.8rem;"><?= date('d/m/Y', strtotime($s['date_debut'])) ?></td>
              <td>
                <span class="badge-status badge-<?= $s['statut'] ?>" style="font-size:.7rem;">
                  <?= ucfirst($s['statut']) ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /col droite -->
</div><!-- /row -->

<div style="text-align:center;margin-top:16px;">
  <a href="candidats.php" class="btn-anac">
    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
  </a>
</div>

</div><!-- /admin-content -->
</main>
</div>

<script>
/* ── Sidebar toggle ─────────────────────────────────── */
document.getElementById('st').addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('open');
});

/* ══════════════════════════════════════════════════════
   TOGGLE BLOQUER — AJAX sans rechargement de page
══════════════════════════════════════════════════════ */
function toggleBloque(chk) {
    const bloque  = chk.checked ? 1 : 0;
    const action  = bloque ? 'bloquer' : 'débloquer';
    const icon    = bloque ? 'warning' : 'question';

    Swal.fire({
        icon: icon,
        title: bloque ? '🔒 Bloquer ce compte ?' : '🔓 Débloquer ce compte ?',
        html: bloque
            ? 'Le candidat <strong><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></strong> ne pourra plus se connecter.'
            : 'Le candidat <strong><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></strong> pourra de nouveau se connecter.',
        showCancelButton: true,
        confirmButtonColor: bloque ? '#dc2626' : '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: bloque ? '🔒 Oui, bloquer' : '🔓 Oui, débloquer',
        cancelButtonText: 'Annuler',
    }).then(result => {
        if (result.isConfirmed) {
            /* Appel AJAX */
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'toggle_bloque',
                    bloque: bloque,
                },
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        /* Mettre à jour les libellés sans rechargement */
                        const label    = document.getElementById('bloquerLabel');
                        const subLabel = document.getElementById('bloquerSubLabel');
                        const badge    = document.getElementById('statutBadge');

                        if (data.bloque) {
                            label.textContent    = '🔒 Compte bloqué';
                            subLabel.textContent = 'Cliquez pour débloquer l\'accès.';
                            badge.innerHTML      = '<span class="badge-status badge-annulee"><i class="fas fa-lock me-1"></i>Bloqué</span>';
                        } else {
                            label.textContent    = '🔓 Compte actif';
                            subLabel.textContent = 'Basculez pour bloquer l\'accès au candidat.';
                            badge.innerHTML      = '<span class="badge-status badge-terminee">Hors ligne</span>';
                        }

                        Swal.fire({
                            icon: 'success',
                            title: data.message,
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            position: 'top-end',
                            toast: true,
                        });
                    } else {
                        chk.checked = !bloque; // Revenir en arrière
                        Swal.fire({icon:'error', title:'Erreur', text:'Impossible de modifier le statut.',confirmButtonColor:'#03224c'});
                    }
                },
                error: function () {
                    chk.checked = !bloque;
                    Swal.fire({icon:'error', title:'Erreur réseau', text:'Impossible de contacter le serveur.',confirmButtonColor:'#03224c'});
                }
            });
        } else {
            // Annulé : revenir à l'état précédent
            chk.checked = !bloque;
        }
    });
}

/* ── Notifications PHP → SweetAlert ─────────────────── */
<?php if ($msg === 'Candidat mis à jour.'): ?>
Swal.fire({title:'Enregistré',icon:'success',timer:2000,timerProgressBar:true,showConfirmButton:false,position:'top-end',toast:true});
<?php elseif (str_starts_with($msg, 'reset:')): ?>
Swal.fire({
    title: 'Mot de passe réinitialisé',
    html: `Nouveau mot de passe : <strong style="font-size:1.3rem;color:#03224c;letter-spacing:2px;"><?= substr($msg, 6) ?></strong><br>
           <small style="color:#6b7a99;">Communiquez ce mot de passe au candidat et conservez-le.</small>`,
    icon: 'success',
    confirmButtonColor: '#03224c',
    confirmButtonText: 'Compris'
});
<?php endif; ?>
<?php if ($emsg): ?>
Swal.fire({title:'<?= addslashes($emsg) ?>',icon:'success',timer:2200,showConfirmButton:false,position:'top-end',toast:true});
<?php endif; ?>
</script>
</body></html>
<?php $conn->close(); ?>