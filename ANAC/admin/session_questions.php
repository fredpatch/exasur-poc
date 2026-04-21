<?php
/**
 * session_questions.php — Affecter les questions à une session
 * BUGS CORRIGÉS :
 *  1. SQL : boucle $ord++ dans query() → remplacé par INSERT multi-lignes en une seule requête
 *  2. JS  : toggleQ(card) interceptait le clic avant la checkbox → logique corrigée
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$session_id = intval($_GET['id'] ?? 0);
$se = $conn->query("
    SELECT se.*, te.code AS tc, te.nom_fr AS tn, te.idtype_examen, te.nb_questions_theorique, te.nb_questions_pratique
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    WHERE se.id_session = $session_id
")->fetch_assoc();

if (!$se || in_array($se['statut'], ['terminee', 'annulee'])) {
    header("Location: sessions.php"); exit();
}

$id_type = intval($se['idtype_examen']);
$swal_script = '';

// ── Traitement POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {

    $sel = isset($_POST['questions']) && is_array($_POST['questions'])
           ? array_map('intval', $_POST['questions'])
           : [];

    // Quota max selon type
    $quota = match($id_type) {
        4 => intval($se['nb_questions_theorique'] ?: 20),   // SENS
        2 => intval($se['nb_questions_theorique'] ?: 50),   // IF théorie
        default => intval($se['nb_questions_theorique'] ?: 50)
    };
    // Pour IF pratique, quota différent
    if ($se['type_session'] === 'pratique') {
        $quota = intval($se['nb_questions_pratique'] ?: 50);
    }

    if (count($sel) > $quota) {
        $over = count($sel) - $quota;
        $swal_script = "Swal.fire({title:'Quota dépassé',text:'Maximum $quota questions autorisées. Désélectionnez $over question(s).',icon:'error',confirmButtonColor:'#03224c'});";
    } else {
        // Supprimer les anciennes affectations
        $conn->query("DELETE FROM session_questions WHERE session_id = $session_id");

        // ── FIX SQL : INSERT multi-lignes en une seule requête (évite $ord++ dans query) ──
        if (!empty($sel)) {
            $values = [];
            $ord    = 1;
            foreach ($sel as $qid) {
                $values[] = "($session_id, $qid, $ord)";
                $ord++;
            }
            $sql = "INSERT INTO session_questions (session_id, question_id, ordre) VALUES " . implode(', ', $values);
            $conn->query($sql);
        }

        $nb = count($sel);
        $swal_script = "Swal.fire({title:'Enregistré !',html:'<b>$nb</b> question(s) affectée(s) avec succès.',icon:'success',timer:2500,timerProgressBar:true,showConfirmButton:false,position:'top-end',toast:true});";
    }
}

// ── Données ──────────────────────────────────────────────────────
// Questions disponibles pour ce type d'examen
// Pour IF : filtrer par type_question selon type_session (theorie/pratique)
$where_tq = "";
if ($id_type == 2) {
    if ($se['type_session'] === 'theorie')  $where_tq = " AND type_question = 'theorique'";
    if ($se['type_session'] === 'pratique') $where_tq = " AND type_question = 'pratique'";
}

$all_q = $conn->query("
    SELECT id, question_text_fr, question_text_en, type_question, images, bareme
    FROM question
    WHERE idtype_examen = $id_type $where_tq
    ORDER BY type_question, id ASC
")->fetch_all(MYSQLI_ASSOC);

$assigned_rows = $conn->query("SELECT question_id FROM session_questions WHERE session_id = $session_id");
$assigned_ids  = [];
while ($a = $assigned_rows->fetch_assoc()) $assigned_ids[] = intval($a['question_id']);

$total_dispo = count($all_q);
$total_ass   = count($assigned_ids);

// Quota recommandé
$quota_recommande = match($id_type) {
    4 => intval($se['nb_questions_theorique'] ?: 20),
    default => intval($se['nb_questions_theorique'] ?: 50)
};
if ($se['type_session'] === 'pratique') {
    $quota_recommande = intval($se['nb_questions_pratique'] ?: 50);
}

$active_page = 'sessions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Affecter questions — ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* ── Layout grille ─────────────────────────────────────────── */
.assign-wrap {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 22px;
    align-items: start;
}
@media(max-width: 991px){ .assign-wrap { grid-template-columns: 1fr; } }

/* ── Barre de recherche ────────────────────────────────────── */
.srch-bar {
    display: flex; gap: 10px; align-items: center;
    padding: 14px 16px;
    background: var(--gray-bg);
    border-bottom: 1px solid var(--gray-border);
}
.srch-input {
    flex: 1;
    border: 2px solid var(--gray-border);
    border-radius: 8px;
    padding: 8px 12px;
    font-family: inherit; font-size: .86rem;
    transition: border-color .2s;
}
.srch-input:focus { outline: none; border-color: var(--blue); }

/* ── Carte question ────────────────────────────────────────── */
.q-card {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    border: 1.5px solid var(--gray-border);
    border-radius: var(--radius-sm);
    padding: 11px 14px;
    margin-bottom: 7px;
    background: white;
    transition: border-color .2s, background .2s, box-shadow .2s;
    /* ── FIX JS : le curseur pointer est géré par le label, pas la div ── */
    cursor: default;
}
.q-card:hover {
    border-color: rgba(3,34,76,.3);
    box-shadow: 0 2px 8px rgba(3,34,76,.07);
}
.q-card.selected {
    border-color: var(--blue);
    background: var(--blue-light);
    box-shadow: 0 0 0 3px rgba(3,34,76,.08);
}

/* ── FIX JS : le label englobe tout le contenu cliquable ────── */
.q-card-label {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    width: 100%;
    cursor: pointer;
    margin: 0;
    user-select: none;
}
.q-card-label input[type="checkbox"] {
    width: 17px; height: 17px;
    margin-top: 2px; flex-shrink: 0;
    accent-color: var(--blue);
    cursor: pointer;
}
.q-txt {
    font-size: .84rem;
    line-height: 1.45;
    color: var(--text-dark);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    flex: 1;
}
.q-meta {
    display: flex; gap: 5px; align-items: center;
    flex-shrink: 0; margin-left: auto;
    padding-left: 8px;
}
.img-th {
    width: 32px; height: 32px;
    border-radius: 5px; object-fit: cover;
    border: 1px solid #ddd; flex-shrink: 0;
}
.q-id { font-size: .7rem; color: #9ca3af; font-weight: 600; white-space: nowrap; }
.q-pts { font-size: .7rem; background: #f9f0c4; color: #92400e; padding: 1px 6px; border-radius: 10px; font-weight: 700; }
.tq-theo { font-size: .68rem; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 10px; font-weight: 700; }
.tq-prat { font-size: .68rem; background: #fce7f3; color: #9d174d; padding: 1px 6px; border-radius: 10px; font-weight: 700; }

/* ── Zone défilante ────────────────────────────────────────── */
.q-list-scroll {
    max-height: 60vh;
    overflow-y: auto;
    padding: 12px 14px;
    scrollbar-width: thin;
    scrollbar-color: var(--gold) transparent;
}
.q-list-scroll::-webkit-scrollbar { width: 4px; }
.q-list-scroll::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }

/* ── Panneau récap sticky ──────────────────────────────────── */
.recap-panel {
    position: sticky;
    top: 80px;
}
.count-big {
    font-size: 3rem; font-weight: 800; color: var(--blue);
    line-height: 1; text-align: center;
}
.count-lbl { font-size: .78rem; color: #9ca3af; text-align: center; margin-top: 2px; }
.quota-bar {
    height: 8px; border-radius: 4px; background: #e5e7eb;
    margin: 10px 0 4px; overflow: hidden;
}
.quota-fill { height: 100%; border-radius: 4px; background: var(--gold); transition: width .3s; }
.quota-fill.over { background: #dc2626; }
.recap-row { display: flex; justify-content: space-between; align-items: center; font-size: .83rem; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
.recap-row:last-child { border-bottom: none; }
.recap-row strong { color: var(--blue); }

/* ── Info session ──────────────────────────────────────────── */
.sess-info {
    background: white;
    border-radius: var(--radius);
    padding: 14px 18px;
    margin-bottom: 18px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--gold);
    display: flex; gap: 18px; flex-wrap: wrap; align-items: center;
}
.tp { display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700; }
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
  <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
  <div class="topbar-title"><i class="fas fa-tasks"></i> Affecter les questions</div>
  <div class="topbar-breadcrumb">
    <a href="dashboard.php">Accueil</a>
    <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
    <a href="sessions.php">Sessions</a>
    <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
    <span><?= htmlspecialchars($se['nom_session']) ?></span>
  </div>
  <div class="ms-auto d-flex align-items-center gap-2">
    <i class="fas fa-user-shield text-muted me-1"></i>
    <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span>
  </div>
</div>

<div class="admin-content">

<!-- Info session -->
<div class="sess-info">
  <div>
    <div style="font-weight:700;font-size:.95rem;color:var(--blue);margin-bottom:4px;">
      <?= htmlspecialchars($se['nom_session']) ?>
    </div>
    <div style="font-size:.8rem;color:#6c7a8d;">ID #<?= $session_id ?></div>
  </div>
  <span class="tp tp-<?= $se['tc'] ?>"><?= $se['tc'] ?></span>
  <?php if($se['type_session']==='theorie'): ?>
  <span style="font-size:.78rem;background:#dbeafe;color:#1e40af;padding:3px 11px;border-radius:20px;font-weight:700;">📖 Théorie</span>
  <?php elseif($se['type_session']==='pratique'): ?>
  <span style="font-size:.78rem;background:#fce7f3;color:#9d174d;padding:3px 11px;border-radius:20px;font-weight:700;">🖼️ Pratique</span>
  <?php endif; ?>
  <div style="margin-left:auto;font-size:.83rem;color:#6c7a8d;">
    <i class="fas fa-check-circle text-success me-1"></i>
    <strong><?= $total_ass ?></strong> affectée(s) / <strong><?= $total_dispo ?></strong> disponibles
    &nbsp;·&nbsp; Quota : <strong><?= $quota_recommande ?></strong>
  </div>
</div>

<!-- Formulaire + grille -->
<form method="POST" id="assignForm">
  <input type="hidden" name="assign" value="1">

  <div class="assign-wrap">

    <!-- Colonne gauche : liste des questions -->
    <div class="card-admin">
      <div class="card-admin-header">
        <i class="fas fa-question-circle me-2"></i>
        <h5>Questions disponibles (<?= $se['tc'] ?>)</h5>
        <span class="badge-count ms-2"><?= $total_dispo ?></span>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn-anac" style="font-size:.76rem;padding:5px 12px;" onclick="selectAll(true)">
            <i class="fas fa-check-square me-1"></i>Tout
          </button>
          <button type="button" class="btn-anac" style="font-size:.76rem;padding:5px 12px;background:white;color:var(--blue);border-color:var(--gray-border);" onclick="selectAll(false)">
            <i class="fas fa-square me-1"></i>Aucun
          </button>
          <!-- BOUTON SÉLECTION ALÉATOIRE -->
          <button type="button" class="btn-gold" style="font-size:.76rem;padding:5px 12px;" onclick="selectRandom()"
                  title="Sélectionner aléatoirement <?= $quota_recommande ?> questions">
            <i class="fas fa-random me-1"></i>Aléatoire (<?= $quota_recommande ?>)
          </button>
        </div>
      </div>

      <!-- Barre de recherche -->
      <div class="srch-bar">
        <input type="text" class="srch-input" id="srchQ" placeholder="🔍 Rechercher dans les questions...">
        <span id="srchCount" style="font-size:.78rem;color:#9ca3af;white-space:nowrap;"></span>
      </div>

      <!-- Liste -->
      <div class="q-list-scroll" id="qList">
        <?php if (empty($all_q)): ?>
        <div style="text-align:center;padding:40px;color:#9ca3af;">
          <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
          <p>Aucune question disponible pour ce type d'examen.</p>
          <a href="questions.php" class="btn-anac" style="font-size:.82rem;">
            <i class="fas fa-plus me-1"></i>Ajouter des questions
          </a>
        </div>
        <?php else: ?>
        <?php foreach ($all_q as $q):
            $is_sel = in_array($q['id'], $assigned_ids);
            $imgs   = $q['images'] ? (json_decode($q['images'], true) ?? []) : [];
        ?>
        <!-- ── FIX JS : .q-card contient un <label> qui enveloppe checkbox + contenu ── -->
        <div class="q-card <?= $is_sel ? 'selected' : '' ?>"
             data-s="<?= strtolower($q['question_text_fr']) ?>"
             data-tq="<?= $q['type_question'] ?>"
             id="card-<?= $q['id'] ?>">
          <label class="q-card-label" for="chk-<?= $q['id'] ?>">
            <input type="checkbox"
                   id="chk-<?= $q['id'] ?>"
                   name="questions[]"
                   value="<?= $q['id'] ?>"
                   class="q-chk"
                   <?= $is_sel ? 'checked' : '' ?>
                   onchange="onChkChange(this)">
            <div class="q-txt">
              <?= htmlspecialchars($q['question_text_fr']) ?>
              <?php if (!empty($q['question_text_en'])): ?>
              <div style="font-size:.75rem;color:#9ca3af;font-style:italic;margin-top:2px;">
                <?= htmlspecialchars(mb_substr($q['question_text_en'], 0, 80)) ?>...
              </div>
              <?php endif; ?>
            </div>
            <div class="q-meta">
              <?php if (!empty($imgs)): ?>
              <img src="../assets/images/<?= htmlspecialchars($imgs[0]) ?>"
                   class="img-th" onerror="this.style.opacity='.3'" alt="">
              <?php endif; ?>
              <?= $q['type_question']==='pratique' ? '<span class="tq-prat">🖼️ Prat.</span>' : '<span class="tq-theo">📝 Th.</span>' ?>
              <?php if($q['bareme']): ?><span class="q-pts"><?= $q['bareme'] ?>pts</span><?php endif; ?>
              <span class="q-id">#<?= $q['id'] ?></span>
            </div>
          </label>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Colonne droite : récap sticky -->
    <div class="recap-panel">
      <div class="card-admin mb-3">
        <div class="card-admin-header">
          <i class="fas fa-clipboard-list me-2"></i>
          <h5>Récapitulatif</h5>
        </div>
        <div class="card-admin-body">
          <div class="count-big" id="dispCount"><?= $total_ass ?></div>
          <div class="count-lbl">question(s) sélectionnée(s)</div>

          <!-- Barre de quota -->
          <div class="quota-bar">
            <div class="quota-fill" id="quotaFill"
                 style="width:<?= $quota_recommande > 0 ? min(100, round($total_ass/$quota_recommande*100)) : 0 ?>%"></div>
          </div>
          <div style="font-size:.72rem;color:#9ca3af;text-align:right;" id="quotaPct">
            <?= $total_ass ?> / <?= $quota_recommande ?> recommandé
          </div>

          <div style="margin-top:14px;">
            <div class="recap-row">
              <span style="color:#6c7a8d;">Disponibles</span>
              <strong><?= $total_dispo ?></strong>
            </div>
            <div class="recap-row">
              <span style="color:#6c7a8d;">Sélectionnées</span>
              <strong id="recapSel"><?= $total_ass ?></strong>
            </div>
            <div class="recap-row">
              <span style="color:#6c7a8d;">Quota recommandé</span>
              <strong><?= $quota_recommande ?></strong>
            </div>
            <?php if ($se['type_session'] === 'theorie' && $id_type == 2): ?>
            <div class="recap-row">
              <span style="color:#1e40af;">📖 Théoriques dispo.</span>
              <strong><?= count(array_filter($all_q, fn($q)=>$q['type_question']==='theorique')) ?></strong>
            </div>
            <?php elseif ($se['type_session'] === 'pratique' && $id_type == 2): ?>
            <div class="recap-row">
              <span style="color:#9d174d;">🖼️ Pratiques dispo.</span>
              <strong><?= count(array_filter($all_q, fn($q)=>$q['type_question']==='pratique')) ?></strong>
            </div>
            <?php endif; ?>
          </div>

          <!-- Alerte quota dépassé -->
          <div id="alertOver" style="display:none;background:#fee2e2;color:#991b1b;padding:8px 11px;border-radius:8px;font-size:.8rem;margin-top:10px;border-left:3px solid #dc2626;">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Quota dépassé !
          </div>
        </div>
      </div>

      <!-- Boutons d'action -->
      <button type="submit" class="btn-gold w-100 mb-2" id="btnSave">
        <i class="fas fa-save me-2"></i>Enregistrer la sélection
      </button>
      <a href="sessions.php" class="btn-anac w-100" style="justify-content:center;background:white;color:var(--blue);border-color:var(--gray-border);">
        <i class="fas fa-arrow-left me-2"></i>Retour aux sessions
      </a>

      <!-- Info type -->
      <div style="margin-top:14px;background:var(--blue-light);border-radius:var(--radius-sm);padding:10px 12px;font-size:.78rem;color:var(--blue);">
        <i class="fas fa-info-circle me-1" style="color:var(--gold)"></i>
        <strong><?= $se['tc'] ?></strong> — <?= htmlspecialchars($se['tn']) ?>
        <br>Seuil de réussite : <?= $conn->query("SELECT seuil_reussite FROM type_examen WHERE idtype_examen=$id_type")->fetch_row()[0] ?>%
      </div>
    </div>

  </div><!-- /assign-wrap -->
</form>

</div><!-- /admin-content -->
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════
   VARIABLES GLOBALES
══════════════════════════════════════════════════════ */
const QUOTA = <?= $quota_recommande ?>;

// Sidebar toggle
document.getElementById('st').addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('open');
});

/* ══════════════════════════════════════════════════════
   FIX JS — onChkChange : appelé par onchange de la checkbox
   Met à jour l'apparence de la carte + le compteur
══════════════════════════════════════════════════════ */
function onChkChange(chk) {
    const card = chk.closest('.q-card');
    if (chk.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    updateCount();
}

/* ══════════════════════════════════════════════════════
   Tout sélectionner / désélectionner
   (uniquement les cartes visibles après filtre)
══════════════════════════════════════════════════════ */
function selectAll(checked) {
    document.querySelectorAll('.q-card:not([style*="display: none"]) .q-chk').forEach(chk => {
        chk.checked = checked;
        onChkChange(chk);
    });
}

/* ══════════════════════════════════════════════════════
   Compteur + barre de quota
══════════════════════════════════════════════════════ */
function updateCount() {
    const n = document.querySelectorAll('.q-chk:checked').length;
    document.getElementById('dispCount').textContent = n;
    document.getElementById('recapSel').textContent  = n;

    // Barre de quota
    const pct = QUOTA > 0 ? Math.min(100, Math.round(n / QUOTA * 100)) : 0;
    const fill = document.getElementById('quotaFill');
    fill.style.width = pct + '%';
    fill.classList.toggle('over', n > QUOTA);

    document.getElementById('quotaPct').textContent = n + ' / ' + QUOTA + ' recommandé';
    document.getElementById('alertOver').style.display = (n > QUOTA) ? 'block' : 'none';
}

/* ══════════════════════════════════════════════════════
   Recherche / filtre
══════════════════════════════════════════════════════ */
document.getElementById('srchQ').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('.q-card').forEach(card => {
        const match = !q || card.dataset.s.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const total = document.querySelectorAll('.q-card').length;
    document.getElementById('srchCount').textContent = q ? `${visible} / ${total}` : '';
});

/* ══════════════════════════════════════════════════════
   Sélection ALÉATOIRE — sélectionne QUOTA questions
   parmi les visibles (après filtre de recherche)
══════════════════════════════════════════════════════ */
function selectRandom() {
    /* D'abord tout désélectionner */
    selectAll(false);

    /* Récupérer les cartes visibles */
    const visible = Array.from(document.querySelectorAll('.q-card:not([style*="display: none"])'));
    const n = Math.min(QUOTA, visible.length);

    if (n === 0) {
        Swal.fire({title:'Aucune question disponible',icon:'warning',confirmButtonColor:'#03224c'});
        return;
    }

    /* Mélanger (Fisher-Yates) et prendre les n premiers */
    const shuffled = [...visible].sort(() => Math.random() - 0.5);
    shuffled.slice(0, n).forEach(function(card) {
        const chk = card.querySelector('.q-chk');
        if (chk) {
            chk.checked = true;
            card.classList.add('selected');
        }
    });

    updateCount();
    updateNumGrid();

    Swal.fire({
        title:'✅ Sélection aléatoire',
        text: n + ' question(s) sélectionnée(s) aléatoirement parmi ' + visible.length + ' disponibles.',
        icon:'success', timer:2500, timerProgressBar:true,
        showConfirmButton:false, position:'top-end', toast:true
    });
}
document.getElementById('assignForm').addEventListener('submit', function (e) {
    const n = document.querySelectorAll('.q-chk:checked').length;
    if (n > QUOTA) {
        e.preventDefault();
        Swal.fire({
            title: 'Quota dépassé',
            html: `Vous avez sélectionné <b>${n}</b> questions alors que le maximum recommandé est <b>${QUOTA}</b>.<br>Voulez-vous quand même enregistrer ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#03224c',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Oui, enregistrer',
            cancelButtonText: 'Annuler'
        }).then(r => { if (r.isConfirmed) { this.submit(); } });
    }
});

/* ══════════════════════════════════════════════════════
   Notifications SweetAlert après traitement PHP
══════════════════════════════════════════════════════ */
<?php if ($swal_script) echo $swal_script; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>