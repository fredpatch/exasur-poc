<?php
/**
 * session_questions.php — Affecter les questions à une session
 * ANAC GABON — EXASUR
 *
 * DIRECTIVE DG :
 *  - AS  (1)  théorie  : quota 100 questions
 *  - IF  (2)  théorie  : quota 100 questions
 *  - IF  (2)  pratique : quota VARIABLE — l'admin saisit le nombre souhaité
 *  - INST(3)  théorie  : quota 100 questions
 *  - SENS(4)           : quota 20 questions (INCHANGÉ)
 *  - FORM(5)           : quota variable (inchangé)
 *
 * FORMULE BARÈME :
 *  Chaque question vaut 100 / N points (N = nb questions cochées pour la session)
 *  → toujours ramené sur 100 points au total, résultat exprimé en %
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

// ── Quota recommandé selon les nouvelles règles DG ──────────────────────
// SENS = 20 | AS/IF théorie/INST = 100 | IF pratique = variable (admin saisit)
// FORM = variable
$is_pratique_if = ($id_type == 2 && $se['type_session'] === 'pratique');
$is_sens        = ($id_type == 4);
$is_form        = ($id_type == 5);

if ($is_sens) {
    $quota_recommande = intval($se['nb_questions_theorique'] ?: 20);
} elseif ($is_pratique_if) {
    // Quota saisi par l'admin (0 = non défini)
    $quota_recommande = intval($se['nb_questions_pratique'] ?: 0);
} elseif ($is_form) {
    $quota_recommande = intval($se['nb_questions_theorique'] ?: 20);
} else {
    // AS, IF théorie, INST : 100
    $quota_recommande = intval($se['nb_questions_theorique'] ?: 100);
}

// ── Traitement POST ──────────────────────────────────────────────────────
$swal_script  = '';
$save_blocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {

    $sel = isset($_POST['questions']) && is_array($_POST['questions'])
           ? array_map('intval', $_POST['questions'])
           : [];

    $quota_admin_input = intval($_POST['quota_pratique_if'] ?? 0);
    if ($is_pratique_if && $quota_admin_input > 0) {
        $quota_recommande = $quota_admin_input;
    }

    $nb_sel    = count($sel);
    $quota_max = $is_pratique_if
                 ? ($quota_recommande > 0 ? $quota_recommande : $nb_sel)
                 : $quota_recommande;

    if (!$is_pratique_if && $quota_max > 0 && $nb_sel > $quota_max) {
        // Marqueur erreur — lu par fetch() côté JS
        $save_blocked = true;
        $over = $nb_sel - $quota_max;
        $swal_script = 'SQ_ERR:' . rawurlencode("Maximum {$quota_max} questions autorisées. Désélectionnez {$over} question(s).");
    } else {
        $conn->query("DELETE FROM session_questions WHERE session_id = $session_id");
        if (!empty($sel)) {
            $values = []; $ord = 1;
            foreach ($sel as $qid) { $values[] = "($session_id,$qid,$ord)"; $ord++; }
            $conn->query("INSERT INTO session_questions (session_id,question_id,ordre) VALUES " . implode(',', $values));
        }
        $nb              = count($sel);
        $bareme_unitaire = $nb > 0 ? round(100.0 / $nb, 2) : 0;
        // Marqueur succès — lu par fetch() côté JS
        $swal_script = "SQ_OK:{$nb}:{$bareme_unitaire}";
    }
}

// ── Données ──────────────────────────────────────────────────────────────
// Filtre type_question pour IF
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

// Barème dynamique actuel
$bareme_actuel = $total_ass > 0 ? round(100.0 / $total_ass, 4) : 0;

// Pour IF pratique : si aucun quota saisi, on utilise le nb de questions déjà affectées
// (pas de colonne nb_questions_pratique dans session_examen → on compte session_questions)
if ($is_pratique_if && $quota_recommande == 0 && $total_ass > 0) {
    $quota_recommande = $total_ass;
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
.assign-wrap {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 22px;
    align-items: start;
}
@media(max-width: 991px){ .assign-wrap { grid-template-columns: 1fr; } }

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

.q-card {
    display: flex; align-items: flex-start; gap: 11px;
    border: 1.5px solid var(--gray-border); border-radius: var(--radius-sm);
    padding: 11px 14px; margin-bottom: 7px; background: white;
    transition: border-color .2s, background .2s, box-shadow .2s; cursor: default;
}
.q-card:hover { border-color: rgba(3,34,76,.3); box-shadow: 0 2px 8px rgba(3,34,76,.07); }
.q-card.selected { border-color: var(--blue); background: var(--blue-light); box-shadow: 0 0 0 3px rgba(3,34,76,.08); }
.q-card-label { display: flex; align-items: flex-start; gap: 11px; width: 100%; cursor: pointer; margin: 0; user-select: none; }
.q-card-label input[type="checkbox"] { width: 17px; height: 17px; margin-top: 2px; flex-shrink: 0; accent-color: var(--blue); cursor: pointer; }
.q-txt { font-size: .84rem; line-height: 1.45; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; flex: 1; }
.q-meta { display: flex; gap: 5px; align-items: center; flex-shrink: 0; margin-left: auto; padding-left: 8px; }
.img-th { width: 32px; height: 32px; border-radius: 5px; object-fit: cover; border: 1px solid #ddd; flex-shrink: 0; }
.q-id  { font-size: .7rem; color: #9ca3af; font-weight: 600; white-space: nowrap; }
.q-pts { font-size: .7rem; background: #f9f0c4; color: #92400e; padding: 1px 6px; border-radius: 10px; font-weight: 700; }
.tq-theo { font-size: .68rem; background: #dbeafe; color: #1e40af; padding: 1px 6px; border-radius: 10px; font-weight: 700; }
.tq-prat { font-size: .68rem; background: #fce7f3; color: #9d174d; padding: 1px 6px; border-radius: 10px; font-weight: 700; }

.quota-bar { background: #e8ecf5; border-radius: 50px; height: 10px; margin: 8px 0; overflow: hidden; }
.quota-fill { height: 100%; background: linear-gradient(90deg, var(--blue), #1e5dbd); border-radius: 50px; transition: width .4s ease; }
.quota-fill.over { background: linear-gradient(90deg, #dc2626, #ef4444); }

.recap-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid var(--gray-border); font-size: .82rem; }
.recap-row:last-child { border-bottom: none; }

.bareme-info {
    background: linear-gradient(135deg, #f0f9e8, #e8f5e9);
    border: 1.5px solid #4caf50;
    border-radius: 10px; padding: 10px 14px; margin-top: 12px;
    font-size: .78rem; color: #1b5e20;
}
.bareme-info strong { color: #2e7d32; }

/* Champ quota IF pratique */
.quota-prat-box {
    background: linear-gradient(135deg, #fff8e1, #fff3cd);
    border: 2px solid #D4AF37;
    border-radius: 10px; padding: 12px 14px; margin-bottom: 14px;
}
.quota-prat-box label { font-weight: 700; color: #92400e; font-size: .82rem; display: block; margin-bottom: 6px; }
.quota-prat-box input {
    width: 100%; border: 2px solid #D4AF37; border-radius: 8px;
    padding: 7px 10px; font-size: .9rem; font-family: inherit; font-weight: 700;
    color: #03224c; text-align: center;
}
.quota-prat-box .hint { font-size: .72rem; color: #78350f; margin-top: 5px; }
</style>
</head>
<body>
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-content">
<?php if ($swal_script): ?>
<div id="phpResultMarker" data-result="<?= htmlspecialchars($swal_script, ENT_QUOTES) ?>" style="display:none;"></div>
<?php endif; ?>

<div class="page-header-admin">
  <div>
    <div class="page-title-admin">
      <i class="fas fa-tasks me-2" style="color:var(--gold)"></i>Affecter questions
    </div>
    <div class="page-sub-admin">
      Session : <strong><?= htmlspecialchars($se['nom_session']) ?></strong>
      — Type : <strong><?= $se['tc'] ?></strong>
      <?php if ($se['type_session'] !== 'normal'): ?>
      — <span style="color:<?= $se['type_session']==='pratique' ? '#9d174d':'#1e40af' ?>;font-weight:700;">
          <?= ucfirst($se['type_session']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <a href="sessions.php" class="btn-anac" style="background:white;color:var(--blue);border-color:var(--gray-border);">
    <i class="fas fa-arrow-left me-2"></i>Sessions
  </a>
</div>

<form method="POST" id="assignForm">
  <input type="hidden" name="assign" value="1">
  <?php if ($is_pratique_if): ?>
  <input type="hidden" name="quota_pratique_if" id="quotaPratInput" value="<?= $quota_recommande ?>">
  <?php endif; ?>

  <div class="assign-wrap">

    <!-- ═══ Liste des questions ═══ -->
    <div class="card-admin">
      <div class="srch-bar">
        <input type="text" class="srch-input" id="srchQ" placeholder="🔍 Rechercher une question...">
        <span id="srchCount" style="font-size:.76rem;color:#9ca3af;white-space:nowrap;"></span>
        <button type="button" class="btn-anac" style="font-size:.78rem;padding:6px 14px;white-space:nowrap;" onclick="selectAll(true)">
          <i class="fas fa-check-square me-1"></i>Tout
        </button>
        <button type="button" class="btn-anac" style="font-size:.78rem;padding:6px 14px;white-space:nowrap;background:white;color:var(--blue);" onclick="selectAll(false)">
          <i class="fas fa-square me-1"></i>Aucun
        </button>
      </div>
      <div class="card-admin-body" style="max-height:70vh;overflow-y:auto;padding:12px;">
        <?php if (empty($all_q)): ?>
        <div style="text-align:center;padding:40px;color:#9ca3af;">
          <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;"></i>
          Aucune question disponible pour ce type d'examen
          <?php if ($id_type==2): ?>(<?= $se['type_session'] ?>)<?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($all_q as $q): ?>
        <?php $is_sel = in_array($q['id'], $assigned_ids); ?>
        <div class="q-card <?= $is_sel ? 'selected' : '' ?>"
             data-s="<?= htmlspecialchars(strtolower($q['question_text_fr']), ENT_QUOTES) ?>">
          <label class="q-card-label">
            <input type="checkbox" name="questions[]" value="<?= $q['id'] ?>"
                   class="q-chk" <?= $is_sel ? 'checked' : '' ?>
                   onchange="onChkChange(this)">
            <span class="q-txt"><?= htmlspecialchars($q['question_text_fr']) ?></span>
            <span class="q-meta">
              <?php if (!empty($q['images'])): ?>
              <?php $imgs = json_decode($q['images'],true); if(!empty($imgs[0])): ?>
              <img class="img-th" src="../assets/images/<?= htmlspecialchars($imgs[0]) ?>" alt="img"
                   onerror="this.style.display='none'">
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($id_type==2): ?>
              <span class="<?= $q['type_question']==='pratique'?'tq-prat':'tq-theo' ?>">
                <?= $q['type_question']==='pratique'?'🖼️ Prat':'📖 Théo' ?>
              </span>
              <?php endif; ?>
              <span class="q-id">#<?= $q['id'] ?></span>
            </span>
          </label>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ Panneau latéral ═══ -->
    <div>

      <!-- Saisie quota IF pratique -->
      <?php if ($is_pratique_if): ?>
      <div class="quota-prat-box">
        <label><i class="fas fa-hashtag me-1"></i>Nombre de questions IF Pratique</label>
        <input type="number" id="quotaPratDisplay" min="1" max="<?= $total_dispo ?>"
               value="<?= $quota_recommande > 0 ? $quota_recommande : '' ?>"
               placeholder="Ex: 40"
               oninput="updateQuotaPrat(this.value)">
        <p class="hint">
          <i class="fas fa-info-circle me-1"></i>
          Saisissez le nombre de questions à utiliser (max disponible : <strong><?= $total_dispo ?></strong>).
          Le barème sera calculé automatiquement : 100 ÷ N pts/question.
        </p>
      </div>
      <?php endif; ?>

      <div class="card-admin" style="margin-bottom:14px;">
        <div class="card-admin-header">
          <i class="fas fa-chart-bar me-2"></i>Récapitulatif
        </div>
        <div class="card-admin-body">
          <div class="count-big" id="dispCount"><?= $total_ass ?></div>
          <div class="count-lbl">question(s) sélectionnée(s)</div>

          <!-- Barre de quota -->
          <?php $quota_pct = ($quota_recommande > 0) ? min(100, round($total_ass/$quota_recommande*100)) : 0; ?>
          <div class="quota-bar">
            <div class="quota-fill" id="quotaFill"
                 style="width:<?= $quota_pct ?>%"></div>
          </div>
          <div style="font-size:.72rem;color:#9ca3af;text-align:right;" id="quotaPct">
            <?= $total_ass ?> / <?= $quota_recommande > 0 ? $quota_recommande : '?' ?> <?= $is_pratique_if ? 'cible' : 'recommandé' ?>
          </div>

          <!-- Barème dynamique -->
          <div class="bareme-info" id="baremeInfo">
            <?php if ($total_ass > 0): ?>
            <i class="fas fa-calculator me-1"></i>
            Barème auto : <strong>100 ÷ <?= $total_ass ?> = <?= round(100/$total_ass,2) ?> pt/question</strong>
            <?php else: ?>
            <i class="fas fa-calculator me-1"></i>
            Barème calculé automatiquement (100 ÷ nb questions)
            <?php endif; ?>
          </div>

          <div style="margin-top:14px;">
            <div class="recap-row">
              <span style="color:#6c7a8d;">Questions disponibles</span>
              <strong><?= $total_dispo ?></strong>
            </div>
            <div class="recap-row">
              <span style="color:#6c7a8d;">Sélectionnées</span>
              <strong id="recapSel"><?= $total_ass ?></strong>
            </div>
            <?php if ($is_pratique_if): ?>
            <div class="recap-row">
              <span style="color:#9d174d;">🖼️ Cible admin</span>
              <strong id="recapQuota"><?= $quota_recommande > 0 ? $quota_recommande : '—' ?></strong>
            </div>
            <?php else: ?>
            <div class="recap-row">
              <span style="color:#6c7a8d;">Quota <?= $is_sens ? 'SENS' : 'DG' ?></span>
              <strong><?= $quota_recommande ?></strong>
            </div>
            <?php endif; ?>
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

          <div id="alertOver" style="display:none;background:#fee2e2;color:#991b1b;padding:8px 11px;border-radius:8px;font-size:.8rem;margin-top:10px;border-left:3px solid #dc2626;">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Quota dépassé !
          </div>
        </div>
      </div>

      <!-- Boutons action -->
      <?php if ($is_pratique_if): ?>
      <!-- IF Pratique : demander N avant sélection aléatoire -->
      <button type="button" class="btn-gold w-100 mb-2" onclick="selectRandomPratIF()">
        <i class="fas fa-random me-2"></i>Sélection aléatoire (N questions)
      </button>
      <?php else: ?>
      <button type="button" class="btn-gold w-100 mb-2" onclick="selectRandom()">
        <i class="fas fa-random me-2"></i>Sélection aléatoire (<?= $quota_recommande ?>)
      </button>
      <?php endif; ?>

      <!-- ══ Bouton ENREGISTRER — déclenche AJAX → modal → redirect ══ -->
      <button type="button" class="btn-anac w-100 mb-2" id="btnSave"
              onclick="enregistrerSelection()">
        <i class="fas fa-save me-2"></i>Enregistrer la sélection
      </button>
      <a href="sessions.php" class="btn-anac w-100" style="justify-content:center;background:white;color:var(--blue);border-color:var(--gray-border);">
        <i class="fas fa-arrow-left me-2"></i>Retour aux sessions
      </a>

      <!-- Info type + règle DG -->
      <div style="margin-top:14px;background:var(--blue-light);border-radius:var(--radius-sm);padding:10px 12px;font-size:.78rem;color:var(--blue);">
        <i class="fas fa-info-circle me-1" style="color:var(--gold)"></i>
        <strong><?= $se['tc'] ?></strong> — <?= htmlspecialchars($se['tn']) ?>
        <br>Seuil de réussite : <strong>70 %</strong>
        <?php if ($is_pratique_if): ?>
        <br><span style="color:#9d174d;">🖼️ IF Pratique : durée <strong>1h30</strong></span>
        <?php elseif (in_array($id_type, [1,2,3])): ?>
        <br><span style="color:#1e40af;">📖 Théorie : durée <strong>2h</strong> — max <strong>100 questions</strong></span>
        <?php elseif ($is_sens): ?>
        <br><span style="color:#16a34a;">Sensibilisation : <strong>20 questions</strong></span>
        <?php endif; ?>
        <br><br>
        <strong style="color:#92400e;"><i class="fas fa-calculator me-1"></i>Barème auto :</strong>
        <span style="color:#78350f;">100 ÷ N questions = points/question</span>
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
let QUOTA          = <?= $quota_recommande ?>;
const IS_PRAT_IF   = <?= $is_pratique_if ? 'true' : 'false' ?>;
const TOTAL_DISPO  = <?= $total_dispo ?>;

document.getElementById('st').addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('open');
});

/* ═══ Mise à jour quota IF pratique ═══ */
function updateQuotaPrat(val) {
    const n = parseInt(val) || 0;
    QUOTA = n;
    const el = document.getElementById('quotaPratInput');
    if (el) el.value = n;
    const elDisp = document.getElementById('recapQuota');
    if (elDisp) elDisp.textContent = n > 0 ? n : '—';
    updateCount();
}

/* ═══ onChkChange ═══ */
function onChkChange(chk) {
    const card = chk.closest('.q-card');
    if (chk.checked) card.classList.add('selected');
    else              card.classList.remove('selected');
    updateCount();
}

/* ═══ Tout sélectionner ═══ */
function selectAll(checked) {
    document.querySelectorAll('.q-card:not([style*="display: none"]) .q-chk').forEach(chk => {
        chk.checked = checked;
        onChkChange(chk);
    });
}

/* ═══ Compteur + barre de quota ═══ */
function updateCount() {
    const n = document.querySelectorAll('.q-chk:checked').length;
    document.getElementById('dispCount').textContent = n;
    document.getElementById('recapSel').textContent  = n;

    // Barre de quota
    const q   = QUOTA > 0 ? QUOTA : n;
    const pct = q > 0 ? Math.min(100, Math.round(n / q * 100)) : 0;
    const fill = document.getElementById('quotaFill');
    fill.style.width = pct + '%';
    fill.classList.toggle('over', QUOTA > 0 && n > QUOTA);

    document.getElementById('quotaPct').textContent = n + ' / ' + (QUOTA > 0 ? QUOTA : '?') + (IS_PRAT_IF ? ' cible' : ' recommandé');
    if (!IS_PRAT_IF) {
        document.getElementById('alertOver').style.display = (QUOTA > 0 && n > QUOTA) ? 'block' : 'none';
    }

    // Barème dynamique
    const bi = document.getElementById('baremeInfo');
    if (bi) {
        if (n > 0) {
            const ptQ = (100 / n).toFixed(2);
            bi.innerHTML = `<i class="fas fa-calculator me-1"></i>Barème auto : <strong>100 ÷ ${n} = ${ptQ} pt/question</strong>`;
        } else {
            bi.innerHTML = `<i class="fas fa-calculator me-1"></i>Barème calculé automatiquement (100 ÷ nb questions)`;
        }
    }
}

/* ═══ Recherche ═══ */
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

/* ═══ Sélection aléatoire standard (AS / IF théorie / INST / SENS) ═══ */
function selectRandom() {
    selectAll(false);
    const visible = Array.from(document.querySelectorAll('.q-card:not([style*="display: none"])'));
    const n = Math.min(QUOTA, visible.length);
    if (n === 0) {
        Swal.fire({title:'Aucune question disponible',icon:'warning',confirmButtonColor:'#03224c'});
        return;
    }
    const shuffled = [...visible].sort(() => Math.random() - 0.5);
    shuffled.slice(0, n).forEach(card => {
        const chk = card.querySelector('.q-chk');
        if (chk) { chk.checked = true; card.classList.add('selected'); }
    });
    updateCount();
    Swal.fire({
        title:'✅ Sélection aléatoire',
        text: n + ' question(s) sélectionnée(s) parmi ' + visible.length + ' disponibles.',
        icon:'success', timer:2500, timerProgressBar:true,
        showConfirmButton:false, position:'top-end', toast:true
    });
}

/* ═══ Sélection aléatoire IF Pratique (admin saisit N) ═══ */
function selectRandomPratIF() {
    const quotaInput = document.getElementById('quotaPratDisplay');
    const currentVal = quotaInput ? parseInt(quotaInput.value) : 0;

    Swal.fire({
        title: '🖼️ IF Pratique — Sélection aléatoire',
        html: `
            <p style="margin-bottom:12px;font-size:.9rem;color:#444;">
                Combien de questions images souhaitez-vous sélectionner aléatoirement ?<br>
                <small style="color:#9ca3af;">Questions disponibles : <strong>${TOTAL_DISPO}</strong></small>
            </p>
            <input type="number" id="swalNInput" class="swal2-input"
                   min="1" max="${TOTAL_DISPO}"
                   value="${currentVal > 0 ? currentVal : ''}"
                   placeholder="Ex : 40"
                   style="font-size:1.2rem;text-align:center;font-weight:700;">
        `,
        confirmButtonText: '<i class="fas fa-random me-1"></i> Sélectionner',
        cancelButtonText: 'Annuler',
        showCancelButton: true,
        confirmButtonColor: '#03224c',
        cancelButtonColor: '#6b7280',
        preConfirm: () => {
            const v = parseInt(document.getElementById('swalNInput').value);
            if (!v || v < 1) {
                Swal.showValidationMessage('Veuillez saisir un nombre ≥ 1');
                return false;
            }
            if (v > TOTAL_DISPO) {
                Swal.showValidationMessage(`Maximum disponible : ${TOTAL_DISPO} questions`);
                return false;
            }
            return v;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        const n = result.value;

        // Mettre à jour le champ de quota
        QUOTA = n;
        if (quotaInput) quotaInput.value = n;
        document.getElementById('quotaPratInput').value = n;
        const elDisp = document.getElementById('recapQuota');
        if (elDisp) elDisp.textContent = n;

        // Sélection aléatoire
        selectAll(false);
        const visible = Array.from(document.querySelectorAll('.q-card:not([style*="display: none"])'));
        const shuffled = [...visible].sort(() => Math.random() - 0.5);
        shuffled.slice(0, n).forEach(card => {
            const chk = card.querySelector('.q-chk');
            if (chk) { chk.checked = true; card.classList.add('selected'); }
        });
        updateCount();

        const ptQ = (100 / n).toFixed(2);
        Swal.fire({
            title:'✅ Sélection IF Pratique',
            html: `<b>${n}</b> question(s) sélectionnée(s) aléatoirement.<br>
                   <small style="color:#6c757d;">Barème auto : 100 ÷ ${n} = <b>${ptQ} pt/question</b></small>`,
            icon:'success', timer:3000, timerProgressBar:true,
            showConfirmButton:false, position:'top-end', toast:true
        });
    });
}

/* ═══ ENREGISTRER LA SÉLECTION — AJAX ═══════════════════════════════
   1. Valider le quota côté JS
   2. Envoyer les données en fetch POST (JSON)
   3. Afficher la modale SweetAlert2 SANS rechargement
   4. Rediriger vers sessions.php après fermeture ou timer
═══════════════════════════════════════════════════════════════════ */
function enregistrerSelection() {

    const cochees  = Array.from(document.querySelectorAll('.q-chk:checked'));
    const n        = cochees.length;

    /* ── Validation 0 question ── */
    if (n === 0) {
        Swal.fire({
            icon              : 'warning',
            title             : 'Aucune question sélectionnée',
            text              : 'Veuillez cocher au moins une question avant d\'enregistrer.',
            confirmButtonColor: '#03224c'
        });
        return;
    }

    /* ── Validation quota (bloquant) — uniquement hors IF pratique ── */
    if (!IS_PRAT_IF && QUOTA > 0 && n > QUOTA) {
        const over = n - QUOTA;
        Swal.fire({
            icon : 'error',
            title: 'Quota dépassé — Enregistrement bloqué',
            html : `<div style="text-align:center;">
                      <p style="font-size:1rem;color:#374151;margin-bottom:14px;">
                        Vous avez sélectionné <b style="color:#dc2626;font-size:1.3rem;">${n}</b> questions.<br>
                        Le quota maximum autorisé est <b style="color:#03224c;">${QUOTA}</b> questions.
                      </p>
                      <div style="background:#fee2e2;border:2px solid #fca5a5;border-radius:10px;padding:12px 18px;">
                        <i class="fas fa-exclamation-triangle" style="color:#dc2626;margin-right:8px;"></i>
                        Veuillez désélectionner <b>${over}</b> question(s) avant d'enregistrer.
                      </div>
                    </div>`,
            confirmButtonColor: '#03224c',
            confirmButtonText : '<i class="fas fa-arrow-left me-1"></i> Corriger la sélection',
            allowOutsideClick : false
        });
        return;
    }

    /* ── Désactiver le bouton pendant l'envoi ── */
    const btn = document.getElementById('btnSave');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enregistrement…';

    /* ── Construire le FormData ── */
    const fd = new FormData();
    fd.append('assign', '1');
    cochees.forEach(chk => fd.append('questions[]', chk.value));

    const qpInput = document.getElementById('quotaPratInput');
    if (qpInput) fd.append('quota_pratique_if', qpInput.value);

    /* ── Fetch AJAX ── */
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(resp) {
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.text();
        })
        .then(function(html) {
            /* Chercher le marqueur data-result injecté par PHP dans la réponse */
            const parser  = new DOMParser();
            const doc     = parser.parseFromString(html, 'text/html');
            const marker  = doc.getElementById('phpResultMarker');
            const result  = marker ? marker.getAttribute('data-result') : '';

            const successMatch = result.match(/^SQ_OK:(\d+):([\d.]+)$/);
            const errorMatch   = result.match(/^SQ_ERR:(.+)$/);

            if (successMatch) {
                const nb     = parseInt(successMatch[1]);
                const bareme = parseFloat(successMatch[2]).toFixed(2);

                /* ── Modale SUCCÈS ── */
                let countDown = 3;
                Swal.fire({
                    icon               : 'success',
                    title              : 'Enregistrement réussi !',
                    html               : `<div style="text-align:center;font-family:Candara,sans-serif;padding:6px 0;">
                                            <p style="font-size:1.05rem;color:#374151;margin-bottom:14px;">
                                              <b style="font-size:1.5rem;color:#16a34a;">${nb}</b>&nbsp;question(s) affectée(s).
                                            </p>
                                            <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:12px 20px;display:inline-block;margin-bottom:14px;">
                                              <i class="fas fa-calculator" style="color:#16a34a;margin-right:8px;"></i>
                                              Barème auto : <strong>100 ÷ ${nb} = ${bareme} pt/question</strong>
                                            </div>
                                            <p style="font-size:.85rem;color:#9ca3af;">
                                              <i class="fas fa-spinner fa-spin me-1"></i>
                                              Redirection dans <b id="sq_cnt">${countDown}</b>&nbsp;s…
                                            </p>
                                          </div>`,
                    confirmButtonColor : '#03224c',
                    confirmButtonText  : '<i class="fas fa-th-list me-1"></i> Voir les sessions',
                    allowOutsideClick  : false,
                    timer              : 3000,
                    timerProgressBar   : true,
                    didOpen            : function() {
                        const iv = setInterval(function() {
                            countDown--;
                            const el = document.getElementById('sq_cnt');
                            if (el) el.textContent = countDown;
                            if (countDown <= 0) clearInterval(iv);
                        }, 1000);
                    }
                }).then(function() {
                    window.location.href = 'sessions.php';
                });

            } else if (errorMatch) {
                /* ── Modale ERREUR (quota dépassé côté serveur) ── */
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i>Enregistrer la sélection';
                Swal.fire({
                    icon              : 'error',
                    title             : 'Quota dépassé',
                    text              : decodeURIComponent(errorMatch[1]),
                    confirmButtonColor: '#03224c'
                });
            } else {
                /* Réponse inattendue — réactiver le bouton */
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i>Enregistrer la sélection';
                Swal.fire({
                    icon : 'error',
                    title: 'Erreur inattendue',
                    text : 'La réponse du serveur est invalide. Veuillez réessayer.',
                    confirmButtonColor: '#03224c'
                });
            }
        })
        .catch(function(err) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Enregistrer la sélection';
            Swal.fire({
                icon : 'error',
                title: 'Erreur réseau',
                text : 'Impossible de contacter le serveur : ' + err.message,
                confirmButtonColor: '#03224c'
            });
        });
}

/* ═══ Notifications PHP au chargement (quota dépassé côté serveur) ═══ */
<?php if ($swal_script) echo $swal_script; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>