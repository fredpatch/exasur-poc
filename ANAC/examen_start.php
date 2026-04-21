<?php
/**
 * examen_qcm.php — Examen QCM général EXASUR ANAC GABON
 * EXASUR/ANAC/candidat/examen_qcm.php
 *
 * Gère TOUS les examens à choix multiples :
 *  - AS  (Agent de Sûreté)
 *  - IF  Théorie (Inspection Filtrage — partie théorique)
 *  - INST (Instructeur)
 *  - SENS (Sensibilisation Sûreté)
 *  - FORM (Évaluation Formation par modules)
 *
 * NOTE : L'examen IF Pratique (images scanner) est géré par examen.php
 *
 * Fonctionnalités :
 *  - Navigation : Précédent / Suivant / Terminer
 *  - Timer décompte depuis session (temps_debut + duree_minutes)
 *  - Sauvegarde réponse en AJAX → save_reponse.php
 *  - Mise à jour progression → update_progression.php
 *  - Anti-triche : détection focus, onglet, DevTools, copie
 *  - Interface responsive, charte ANAC GABON
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../php/db_connection.php';
include '../lang/lang_loader.php';

/* ── Vérifier session active ──────────────────────────────── */
if (!isset($_SESSION['idcandidat'], $_SESSION['id_session'], $_SESSION['idtype_examen'])) {
    header("Location: ../../index.php"); exit();
}

$idcandidat    = intval($_SESSION['idcandidat']);
$id_session    = intval($_SESSION['id_session']);
$idtype_examen = intval($_SESSION['idtype_examen']);
$type_session  = $_SESSION['type_session'] ?? 'normal';
$nom_complet   = htmlspecialchars($_SESSION['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8');
$code_acces    = htmlspecialchars($_SESSION['code_acces']   ?? '', ENT_QUOTES, 'UTF-8');
$nom_session   = htmlspecialchars($_SESSION['nom_session']  ?? '', ENT_QUOTES, 'UTF-8');
$questions     = $_SESSION['questions'] ?? [];
$nb_questions  = intval($_SESSION['nb_questions'] ?? 0);
$current_index = intval($_SESSION['current_index'] ?? 0);

/* ── Rediriger IF pratique vers examen.php ────────────────── */
if ($idtype_examen == 2 && $type_session === 'pratique') {
    header("Location: examen.php"); exit();
}

/* ── Vérifier session en cours ────────────────────────────── */
$sess_chk = $conn->prepare(
    "SELECT id_session, duree_minutes FROM session_examen
     WHERE id_session=? AND statut IN ('planifiee','en_cours')"
);
$sess_chk->bind_param("i", $id_session);
$sess_chk->execute();
$sess_row = $sess_chk->get_result()->fetch_assoc();
$sess_chk->close();
if (!$sess_row) {
    header("Location: ../../index.php"); exit();
}

/* ── Vérifier résultat existant ───────────────────────────── */
$cr = $conn->prepare("SELECT id FROM resultats WHERE idcandidat=? AND id_session=? AND (note_finale>0 OR locked=1) LIMIT 1");
$cr->bind_param("ii", $idcandidat, $id_session);
$cr->execute();
if ($cr->get_result()->num_rows > 0) {
    $cr->close();
    $conn->close();
    header("Location: resultat.php"); exit();
}
$cr->close();

/* ── Vérifier is_logged_in ────────────────────────────────── */
$cl = $conn->prepare("SELECT is_logged_in FROM candidat WHERE idcandidat=?");
$cl->bind_param("i", $idcandidat);
$cl->execute();
$cl_row = $cl->get_result()->fetch_assoc();
$cl->close();
if (!$cl_row || $cl_row['is_logged_in'] != 1) {
    session_destroy();
    header("Location: auth.php?type=$idtype_examen"); exit();
}

/* ── Aucune question ──────────────────────────────────────── */
if ($nb_questions === 0 || empty($questions)) {
    $conn->close();
    header("Location: soumettre_examen.php"); exit();
}

/* ── Calcul temps restant ─────────────────────────────────── */
$duree_min    = intval($_SESSION['duree_minutes'] ?? intval($sess_row['duree_minutes'] ?? 90));
$temps_debut  = intval($_SESSION['temps_debut'] ?? time());
$temps_ecoule = time() - $temps_debut;
$temps_restant = max(0, ($duree_min * 60) - $temps_ecoule);
if ($temps_restant === 0) {
    $conn->close();
    header("Location: soumettre_examen.php?timeout=1"); exit();
}

/* ── Type examen infos ────────────────────────────────────── */
$type_info = ['nom_fr' => 'Examen', 'code' => 'QCM', 'seuil_reussite' => 80];
$st = $conn->prepare("SELECT nom_fr, code, seuil_reussite FROM type_examen WHERE idtype_examen=?");
if ($st) {
    $st->bind_param("i", $idtype_examen);
    $st->execute();
    $ti = $st->get_result()->fetch_assoc();
    $st->close();
    if ($ti) $type_info = $ti;
}

/* ── Réponses existantes ──────────────────────────────────── */
$reponses = $_SESSION['reponses'] ?? [];
$repondues = count(array_filter($reponses, fn($v) => $v !== null));

/* ── Infractions ──────────────────────────────────────────── */
$infractions = intval($_SESSION['infractions'] ?? 0);

$conn->close();

/* ── Préparer données JSON pour JS ────────────────────────── */
$questions_js = json_encode(array_map(function($q) {
    return [
        'id'  => (int)$q['id'],
        'fr'  => htmlspecialchars($q['question_text_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
        'en'  => htmlspecialchars($q['question_text_en'] ?? '', ENT_QUOTES, 'UTF-8'),
        'opt' => [
            htmlspecialchars($q['option1_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($q['option2_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($q['option3_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($q['option4_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
        ],
        'correct' => (int)$q['correct_option'],
        'bareme'  => (float)$q['bareme'],
    ];
}, $questions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$reponses_js = json_encode((object)$reponses);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>EXASUR — <?= htmlspecialchars($type_info['code']) ?> — QCM</title>
<link rel="icon" href="../assets/images/LOGOANAC.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
/* ══ VARIABLES ══ */
:root{--b:#03224c;--g:#D4AF37;--red:#dc2626;--green:#16a34a;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Candara','Calibri',sans-serif;background:linear-gradient(135deg,#f0f4ff,#e8ecf5);min-height:100vh;padding-top:72px;user-select:none;-webkit-user-select:none;}

/* ══ NAVBAR ══ */
.nav-exam{
    position:fixed;top:0;left:0;right:0;z-index:1000;
    background:linear-gradient(135deg,var(--b),#0a3a6b);
    border-bottom:3px solid var(--g);
    padding:10px 20px;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;
}
.nav-logo{display:flex;align-items:center;gap:10px;}
.nav-logo img{height:36px;}
.nav-logo .title{color:#fff;font-weight:800;font-size:.95rem;}
.nav-logo .subtitle{color:var(--g);font-size:.75rem;font-weight:600;}
.nav-center{display:flex;align-items:center;gap:12px;}
.timer-box{
    background:#111;color:#fff;font-family:monospace;font-size:1.4rem;
    font-weight:bold;padding:6px 16px;border-radius:10px;
    border:2px solid var(--g);min-width:100px;text-align:center;
}
.timer-box.warn{background:var(--red);animation:pulse .8s infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.04);}}
.nav-right{display:flex;align-items:center;gap:10px;}
.badge-inf{background:var(--red);color:#fff;border-radius:50px;
    padding:4px 12px;font-size:.8rem;font-weight:700;}
.badge-prog{background:rgba(212,175,55,.2);color:var(--g);border-radius:50px;
    padding:4px 12px;font-size:.8rem;font-weight:600;}

/* ══ CONTENU ══ */
.main{max-width:800px;margin:0 auto;padding:20px;}
.card-q{
    background:#fff;border-radius:20px;
    box-shadow:0 10px 30px rgba(3,34,76,.12);
    border-top:4px solid var(--g);
    margin-bottom:16px;overflow:hidden;
}
.card-head{
    background:linear-gradient(135deg,var(--b),#0a3a6b);
    color:#fff;padding:16px 22px;
    display:flex;justify-content:space-between;align-items:center;
}
.card-head .q-num{font-weight:800;font-size:.9rem;color:var(--g);}
.card-head .q-pts{font-size:.8rem;color:rgba(255,255,255,.7);}
.card-body{padding:22px;}
.q-text{
    font-size:1rem;font-weight:700;color:var(--b);
    line-height:1.5;margin-bottom:18px;
}

/* ══ OPTIONS ══ */
.options{display:flex;flex-direction:column;gap:10px;}
.opt{
    display:flex;align-items:center;gap:12px;
    padding:13px 16px;border-radius:12px;
    border:2px solid #e0e5f0;cursor:pointer;
    transition:all .2s;background:#fafbff;
}
.opt:hover{border-color:var(--b);background:#f0f4ff;transform:translateX(3px);}
.opt.selected{border-color:var(--b);background:linear-gradient(135deg,#e8f0fe,#dce7fd);font-weight:700;}
.opt input[type=radio]{width:18px;height:18px;accent-color:var(--b);flex-shrink:0;cursor:pointer;}
.opt-label{color:#1e2a4a;font-size:.93rem;}

/* ══ BARRE PROGRESSION ══ */
.prog-bar-wrap{background:#e8ecf5;border-radius:50px;height:6px;margin:14px 0 6px;}
.prog-bar-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--b),var(--g));transition:width .4s;}
.prog-text{color:#6b7a90;font-size:.78rem;text-align:right;}

/* ══ NAVIGATION QUESTIONS ══ */
.q-nav{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
.q-nav-btn{
    width:36px;height:36px;border-radius:8px;border:2px solid #dde3f0;
    background:#fff;font-weight:700;font-size:.82rem;cursor:pointer;
    transition:all .2s;color:var(--b);
}
.q-nav-btn:hover{border-color:var(--b);background:#f0f4ff;}
.q-nav-btn.done{background:var(--b);color:var(--g);border-color:var(--b);}
.q-nav-btn.current{border-color:var(--g);background:#fff8dc;box-shadow:0 0 0 2px var(--g);}

/* ══ BOUTONS ACTION ══ */
.action-bar{display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between;margin-top:14px;}
.btn-prev,.btn-next,.btn-submit{
    padding:12px 26px;border-radius:50px;font-weight:700;font-size:.93rem;
    cursor:pointer;border:none;transition:all .3s;font-family:'Candara','Calibri',sans-serif;
}
.btn-prev{background:#e8ecf5;color:var(--b);border:2px solid #c8d0e0;}
.btn-prev:hover:not(:disabled){background:#dde3f0;}
.btn-prev:disabled{opacity:.4;cursor:not-allowed;}
.btn-next{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;border:2px solid var(--g);}
.btn-next:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(3,34,76,.3);}
.btn-submit{background:linear-gradient(135deg,var(--green),#15803d);color:#fff;display:none;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(22,163,74,.4);}

/* ══ SESSION INFO ══ */
.sess-info{background:#fff;border-radius:14px;padding:14px 18px;margin-bottom:16px;
    border-left:4px solid var(--g);display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.sess-info .s-item{font-size:.82rem;color:#5a6380;}
.sess-info .s-item strong{color:var(--b);}

/* ══ RESPONSIVE ══ */
@media(max-width:600px){
    .nav-exam{padding:8px 12px;}
    .timer-box{font-size:1.1rem;padding:4px 12px;}
    .main{padding:12px;}
    .action-bar{flex-direction:column;}
    .btn-prev,.btn-next,.btn-submit{width:100%;text-align:center;}
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="nav-exam">
    <div class="nav-logo">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"
             onerror="this.style.display='none'">
        <div>
            <div class="title">EXASUR — ANAC GABON</div>
            <div class="subtitle"><?= htmlspecialchars($type_info['code']) ?> — <?= htmlspecialchars($type_info['nom_fr'] ?? '') ?></div>
        </div>
    </div>
    <div class="nav-center">
        <div class="timer-box" id="timer">00:00:00</div>
    </div>
    <div class="nav-right">
        <span class="badge-prog" id="progBadge">0 / <?= $nb_questions ?></span>
        <span class="badge-inf" id="infrBadge" style="display:none;">
            <i class="fas fa-exclamation-triangle"></i> <span id="infrCount"><?= $infractions ?></span>
        </span>
    </div>
</nav>

<!-- ══ MAIN ══ -->
<div class="main">

    <!-- Infos session -->
    <div class="sess-info">
        <div class="s-item"><i class="fas fa-user me-1" style="color:var(--g);"></i> <strong><?= $nom_complet ?></strong></div>
        <div class="s-item"><i class="fas fa-key me-1" style="color:var(--g);"></i> Code : <strong><?= $code_acces ?></strong></div>
        <div class="s-item"><i class="fas fa-calendar me-1" style="color:var(--g);"></i> <strong><?= $nom_session ?></strong></div>
        <?php if ($type_session === 'theorie'): ?>
        <div class="s-item" style="color:#7c3aed;"><i class="fas fa-book me-1"></i> <strong>Épreuve Théorique</strong></div>
        <?php endif; ?>
    </div>

    <!-- Navigation questions -->
    <div class="q-nav" id="qNav"></div>

    <!-- Barre de progression -->
    <div class="prog-bar-wrap"><div class="prog-bar-fill" id="progBar" style="width:0%"></div></div>
    <div class="prog-text" id="progText">0 réponse(s) sur <?= $nb_questions ?></div>

    <!-- Carte question -->
    <div class="card-q">
        <div class="card-head">
            <span class="q-num" id="qNum">Question 1 / <?= $nb_questions ?></span>
            <span class="q-pts" id="qPts"></span>
        </div>
        <div class="card-body">
            <div class="q-text" id="qText">Chargement...</div>
            <div class="options" id="optContainer"></div>
        </div>
    </div>

    <!-- Boutons navigation -->
    <div class="action-bar">
        <button class="btn-prev" id="btnPrev" onclick="prevQ()" disabled>
            <i class="fas fa-chevron-left me-1"></i> Précédente
        </button>
        <button class="btn-next" id="btnNext" onclick="nextQ()">
            Suivante <i class="fas fa-chevron-right ms-1"></i>
        </button>
        <button class="btn-submit" id="btnSubmit" onclick="confirmerSoumission()">
            <i class="fas fa-paper-plane me-2"></i> Terminer l'examen
        </button>
    </div>

</div><!-- /main -->

<script>
/* ════════════════════════════════════════════════════════════
   DONNÉES
════════════════════════════════════════════════════════════ */
const QUESTIONS    = <?= $questions_js ?>;
const REPONSES_OLD = <?= $reponses_js ?>;
const TOTAL_Q      = <?= $nb_questions ?>;
const TYPE_EXAMEN  = <?= $idtype_examen ?>;
const TYPE_SESSION = '<?= $type_session ?>';
const SEUIL        = <?= floatval($type_info['seuil_reussite'] ?? 80) ?>;
const INFR_MAX     = 5;
const PARTIE       = (TYPE_SESSION === 'pratique') ? 'pratique' : 'theorique';

let currentIdx    = <?= $current_index ?>;
let reponses      = {};
let tempsRestant  = <?= $temps_restant ?>;
let infrCount     = <?= $infractions ?>;
let examSoumis    = false;
let lastInfrTime  = 0;
let timerWarned10 = false;
let timerWarned5  = false;

/* Charger réponses existantes */
try {
    const old = JSON.parse('<?= addslashes(json_encode($reponses)) ?>') || {};
    for (const k in old) {
        if (old[k] !== null && old[k] !== undefined) {
            reponses[parseInt(k)] = parseInt(old[k]);
        }
    }
} catch(e) {}

/* ════════════════════════════════════════════════════════════
   INITIALISATION
════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
    buildNavGrid();
    loadQuestion(currentIdx);
    updateProgress();
    startTimer();
    attachAntiCheat();
});

/* ── Grille de navigation ─────────────────────────────────── */
function buildNavGrid() {
    const nav = document.getElementById('qNav');
    nav.innerHTML = '';
    QUESTIONS.forEach((q, i) => {
        const btn = document.createElement('button');
        btn.className = 'q-nav-btn' + (reponses[q.id] !== undefined ? ' done' : '') + (i === currentIdx ? ' current' : '');
        btn.textContent = i + 1;
        btn.onclick = () => { goToQ(i); };
        nav.appendChild(btn);
    });
}

/* ── Charger une question ─────────────────────────────────── */
function loadQuestion(idx) {
    if (idx < 0 || idx >= TOTAL_Q) return;
    currentIdx = idx;
    const q = QUESTIONS[idx];

    document.getElementById('qNum').textContent = 'Question ' + (idx+1) + ' / ' + TOTAL_Q;
    document.getElementById('qPts').textContent = q.bareme + ' point(s)';
    document.getElementById('qText').textContent = q.fr;

    const cont = document.getElementById('optContainer');
    cont.innerHTML = '';
    q.opt.forEach((o, i) => {
        if (!o) return;
        const num = i + 1;
        const selected = reponses[q.id] === num;
        const div = document.createElement('div');
        div.className = 'opt' + (selected ? ' selected' : '');
        div.dataset.qid = q.id;
        div.dataset.val = num;
        div.innerHTML = '<input type="radio" name="opt_' + q.id + '" value="' + num + '"' + (selected?' checked':'') + '>'
                      + '<span class="opt-label">' + esc(o) + '</span>';
        div.addEventListener('click', function() { selectionnerReponse(q.id, num, this); });
        div.querySelector('input').addEventListener('change', function() { selectionnerReponse(q.id, num, div); });
        cont.appendChild(div);
    });

    /* Boutons navigation */
    document.getElementById('btnPrev').disabled = (idx === 0);
    if (idx === TOTAL_Q - 1) {
        document.getElementById('btnNext').style.display  = 'none';
        document.getElementById('btnSubmit').style.display = 'inline-block';
    } else {
        document.getElementById('btnNext').style.display  = 'inline-block';
        document.getElementById('btnSubmit').style.display = 'none';
    }

    /* Mettre à jour la grille */
    buildNavGrid();
    updateProgress();
    syncProgression(idx);
}

/* ── Sélectionner une réponse ─────────────────────────────── */
function selectionnerReponse(qid, val, elem) {
    reponses[qid] = val;
    /* Mettre à jour la classe visuelle */
    elem.closest('.options').querySelectorAll('.opt').forEach(d => d.classList.remove('selected'));
    elem.classList.add('selected');
    elem.querySelector('input').checked = true;
    /* Sauvegarder en AJAX */
    saveReponse(qid, val);
    updateProgress();
}

/* ── Navigation ───────────────────────────────────────────── */
function nextQ() { if (currentIdx < TOTAL_Q - 1) loadQuestion(currentIdx + 1); }
function prevQ() { if (currentIdx > 0) loadQuestion(currentIdx - 1); }
function goToQ(i) { loadQuestion(i); }

/* ── Progression ──────────────────────────────────────────── */
function updateProgress() {
    const done = Object.keys(reponses).length;
    const pct  = TOTAL_Q > 0 ? Math.round(done / TOTAL_Q * 100) : 0;
    document.getElementById('progBar').style.width = pct + '%';
    document.getElementById('progText').textContent = done + ' réponse(s) sur ' + TOTAL_Q;
    document.getElementById('progBadge').textContent = done + ' / ' + TOTAL_Q;
}

/* ════════════════════════════════════════════════════════════
   AJAX — Sauvegarder réponse
════════════════════════════════════════════════════════════ */
function saveReponse(question_id, selected_option) {
    fetch('save_reponse.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({question_id, selected_option})
    }).catch(e => console.warn('save_reponse:', e));
}

/* ── Synchroniser progression ─────────────────────────────── */
function syncProgression(idx) {
    fetch('update_progression.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({current_index: idx, partie: PARTIE})
    }).catch(e => console.warn('update_progression:', e));
}

/* ════════════════════════════════════════════════════════════
   SOUMISSION
════════════════════════════════════════════════════════════ */
function confirmerSoumission() {
    const done = Object.keys(reponses).length;
    const manq = TOTAL_Q - done;
    let html = '<p style="font-family:Candara,sans-serif;">';
    if (manq > 0) {
        html += '<strong style="color:#dc2626;">' + manq + ' question(s) sans réponse.</strong><br>';
    }
    html += 'Répondues : <strong>' + done + ' / ' + TOTAL_Q + '</strong></p>';

    Swal.fire({
        title: 'Terminer l\'examen ?',
        html: html,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Oui, soumettre',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Continuer',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#03224c'
    }).then(r => {
        if (r.isConfirmed) {
            examSoumis = true;
            Swal.fire({
                title: 'Envoi en cours…',
                html: '<i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#03224c;"></i>',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            window.location.href = 'soumettre_examen.php';
        }
    });
}

/* ════════════════════════════════════════════════════════════
   TIMER
════════════════════════════════════════════════════════════ */
function startTimer() {
    const el = document.getElementById('timer');
    const iv = setInterval(() => {
        if (examSoumis) { clearInterval(iv); return; }
        if (tempsRestant <= 0) {
            clearInterval(iv);
            examSoumis = true;
            Swal.fire({
                title: '⏰ Temps écoulé !',
                text: 'Votre temps est épuisé. L\'examen est automatiquement soumis.',
                icon: 'warning',
                allowOutsideClick: false,
                showConfirmButton: false,
                timer: 2500
            }).then(() => { window.location.href = 'soumettre_examen.php?timeout=1'; });
            return;
        }
        tempsRestant--;
        const h = Math.floor(tempsRestant / 3600);
        const m = Math.floor((tempsRestant % 3600) / 60);
        const s = tempsRestant % 60;
        el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);

        if (tempsRestant <= 600) el.classList.add('warn');

        if (tempsRestant === 600 && !timerWarned10) {
            timerWarned10 = true;
            Swal.fire({title:'⏰ 10 minutes restantes',text:'Gérez bien votre temps.',icon:'warning',timer:4000,showConfirmButton:false});
        }
        if (tempsRestant === 300 && !timerWarned5) {
            timerWarned5 = true;
            Swal.fire({title:'⚠️ 5 minutes restantes',text:'Finalisez vos réponses !',icon:'error',timer:4000,showConfirmButton:false});
        }
    }, 1000);
}
function pad(n) { return n.toString().padStart(2, '0'); }

/* ════════════════════════════════════════════════════════════
   ANTI-TRICHE
════════════════════════════════════════════════════════════ */
function attachAntiCheat() {
    document.addEventListener('contextmenu', e => { e.preventDefault(); logInfr('Clic droit détecté'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['i','I','j','J','c','C'].includes(e.key))) {
            e.preventDefault(); logInfr('Outils développeur'); return false;
        }
        if (e.ctrlKey && ['c','C','v','V','x','X'].includes(e.key)) {
            e.preventDefault(); logInfr('Copier/Coller'); return false;
        }
        if (e.ctrlKey && ['r','R'].includes(e.key)) {
            e.preventDefault(); logInfr('Rafraîchissement'); return false;
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !examSoumis) logInfr('Changement d\'onglet');
    });
    window.addEventListener('blur', () => { if (!examSoumis) logInfr('Perte de focus'); });
    document.addEventListener('copy',  e => { e.preventDefault(); logInfr('Tentative de copie'); });
    document.addEventListener('cut',   e => { e.preventDefault(); logInfr('Tentative de couper'); });
}

function logInfr(action) {
    if (examSoumis) return;
    const now = Date.now();
    if (now - lastInfrTime < 2000) return;
    lastInfrTime = now;
    infrCount++;

    const el = document.getElementById('infrCount');
    if (el) el.textContent = infrCount;
    const badge = document.getElementById('infrBadge');
    if (badge) badge.style.display = 'inline-block';

    Swal.fire({
        icon: 'warning',
        title: '⚠️ Action interdite',
        html: '<p style="font-family:Candara,sans-serif;"><strong>' + action + '</strong><br>Tentative ' + infrCount + ' / ' + INFR_MAX + '</p>',
        timer: 3000,
        timerProgressBar: true,
        confirmButtonColor: '#03224c',
        showConfirmButton: true
    });

    fetch('register_infraction.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action})
    })
    .then(r => r.json())
    .then(d => {
        if (d.infractions >= INFR_MAX) {
            examSoumis = true;
            Swal.fire({
                title: '🔒 Examen verrouillé',
                html: '<p style="font-family:Candara,sans-serif;">Vous avez atteint le maximum d\'infractions. L\'examen est verrouillé.</p>',
                icon: 'error',
                allowOutsideClick: false,
                confirmButtonColor: '#03224c'
            }).then(() => { window.location.href = 'soumettre_examen.php?lock=1&reason=5+infractions'; });
        }
    })
    .catch(() => {});
}

/* ── Utilitaire ───────────────────────────────────────────── */
function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
</script>
</body>
</html>
<?php $conn->close(); ?>