<?php
/**
 * examen_qcm.php — Examen QCM EXASUR ANAC GABON
 * candidat/examen_qcm.php
 *
 * CORRECTION ① : Questions aléatoires par candidat
 *   → Fisher-Yates PHP avec seed (idcandidat × 31337 + id_session × 17)
 *   → L'ordre est UNIQUE par candidat et STABLE (mémorisé en session)
 *   → Candidat X et Candidat Y voient des ordres différents dans la même session
 */
if (session_status()===PHP_SESSION_NONE) session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

if (!isset($_SESSION['idcandidat'],$_SESSION['id_session'],$_SESSION['idtype_examen'])) {
    header("Location: ../../index.php"); exit();
}
$idcandidat    = intval($_SESSION['idcandidat']);
$id_session    = intval($_SESSION['id_session']);
$idtype_examen = intval($_SESSION['idtype_examen']);
$type_session  = $_SESSION['type_session'] ?? 'normal';
$nom_complet   = $_SESSION['nom_complet']  ?? '';
$code_acces    = $_SESSION['code_acces']   ?? '';
$nom_session   = $_SESSION['nom_session']  ?? '';
$questions_raw = $_SESSION['questions']    ?? [];
$nb_questions  = intval($_SESSION['nb_questions'] ?? 0);
$current_index = intval($_SESSION['current_index'] ?? 0);

/* IF pratique → examen.php */
if ($idtype_examen==2 && $type_session==='pratique') { header("Location: examen.php"); exit(); }

/* Vérifier session */
$sc=$conn->prepare("SELECT id_session FROM session_examen WHERE id_session=? AND statut IN ('planifiee','en_cours')");
$sc->bind_param("i",$id_session); $sc->execute();
if ($sc->get_result()->num_rows===0){$conn->close();header("Location: ../../index.php");exit();}
$sc->close();

/* Déjà passé ? */
$cr=$conn->prepare("SELECT id FROM resultats WHERE idcandidat=? AND id_session=? AND (note_finale>0 OR locked=1) LIMIT 1");
$cr->bind_param("ii",$idcandidat,$id_session); $cr->execute();
if ($cr->get_result()->num_rows>0){$cr->close();$conn->close();header("Location: resultat.php");exit();}
$cr->close();

/* ════════════════════════════════════════════════════════════
   RECHARGEMENT QUESTIONS DEPUIS BDD si session PHP vide
   (cas : réinitialisation admin → session PHP perdue)
════════════════════════════════════════════════════════════ */
if ($nb_questions===0 || empty($questions_raw)) {
    /* Recharger depuis session_questions en BDD */
    $rq=$conn->prepare("
        SELECT q.id,q.question_text_fr,q.question_text_en,
               q.option1_fr,q.option1_en,q.option2_fr,q.option2_en,
               q.option3_fr,q.option3_en,q.option4_fr,q.option4_en,
               q.correct_option,q.bareme,q.type_question
        FROM session_questions sq
        JOIN question q ON q.id=sq.question_id
        WHERE sq.session_id=? AND q.type_question='theorique'
        ORDER BY sq.ordre ASC
    ");
    if ($rq) {
        $rq->bind_param("i",$id_session); $rq->execute();
        $rr=$rq->get_result();
        while($row=$rr->fetch_assoc()) $questions_raw[]=$row;
        $rq->close();
        $nb_questions = count($questions_raw);
        $_SESSION['questions']    = $questions_raw;
        $_SESSION['nb_questions'] = $nb_questions;
    }
    /* Si toujours vide → aucune question affectée */
    if ($nb_questions===0){
        $conn->close();
        header("Location: attente.php?msg=no_questions"); exit();
    }
}

/* Lire current_index depuis la progression BDD (après réinit) */
$prog_row = $conn->query("SELECT current_index_theo FROM progression_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session LIMIT 1")->fetch_assoc();
if ($prog_row) {
    $current_index = min(intval($prog_row['current_index_theo']), max(0,$nb_questions-1));
    $_SESSION['current_index'] = $current_index;
}

/* ════════════════════════════════════════════════════════════
   ① RANDOMISATION QUESTIONS PAR CANDIDAT
   Seed = idcandidat * 31337 + id_session * 17
   L'ordre est mémorisé en session → stable si le candidat revient
════════════════════════════════════════════════════════════ */
$order_key = 'qorder_'.$idcandidat.'_'.$id_session;

if (!isset($_SESSION[$order_key])) {
    $indices = range(0, count($questions_raw)-1);
    mt_srand(intval($idcandidat * 31337 + $id_session * 17));
    for ($i=count($indices)-1; $i>0; $i--) {
        $j = mt_rand(0, $i);
        [$indices[$i],$indices[$j]] = [$indices[$j],$indices[$i]];
    }
    $_SESSION[$order_key] = $indices;
    mt_srand(); /* Réinitialiser le seed */
}

/* Réordonner les questions selon l'ordre du candidat */
$questions = [];
foreach ($_SESSION[$order_key] as $idx) {
    if (isset($questions_raw[$idx])) $questions[] = $questions_raw[$idx];
}

$duree_min     = intval($_SESSION['duree_minutes'] ?? 90);
$temps_restant = max(0,($duree_min*60)-(time()-intval($_SESSION['temps_debut']??time())));
if ($temps_restant===0){$conn->close();header("Location: soumettre_examen.php?timeout=1");exit();}

/* Infos type examen */
$type_code='QCM'; $type_nom='Examen'; $seuil=80;
$st=$conn->prepare("SELECT code,nom_fr,seuil_reussite FROM type_examen WHERE idtype_examen=?");
if ($st){$st->bind_param("i",$idtype_examen);$st->execute();$ti=$st->get_result()->fetch_assoc();$st->close();if($ti){$type_code=$ti['code'];$type_nom=$ti['nom_fr'];$seuil=floatval($ti['seuil_reussite']);}}

$reponses    = $_SESSION['reponses']    ?? [];
$infractions = intval($_SESSION['infractions'] ?? 0);
$partie      = ($type_session==='pratique')?'pratique':'theorique';
$epreuve_label = match($type_session){
    'theorie'  => 'Partie théorique',
    'pratique' => 'Partie pratique',
    default    => 'Examen'
};

$questions_js = json_encode(array_map(function($q){
    return ['id'=>(int)($q['id']??0),'fr'=>$q['question_text_fr']??'','en'=>$q['question_text_en']??'',
            'opt_fr'=>[$q['option1_fr']??'',$q['option2_fr']??'',$q['option3_fr']??'',$q['option4_fr']??''],
            'opt_en'=>[$q['option1_en']??'',$q['option2_en']??'',$q['option3_en']??'',$q['option4_en']??''],
            'correct'=>(int)($q['correct_option']??1),'bareme'=>(float)($q['bareme']??2)];
},$questions),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
$reponses_js=json_encode((object)$reponses);
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>EXASUR — <?= htmlspecialchars($type_code) ?></title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
:root{--blue:#03224c;--blue-mid:#0a3a6b;--gold:#D4AF37;--green:#16a34a;--red:#dc2626;--bg:#f0f4fa;--sidebar:300px;}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:'Candara','Calibri',sans-serif;}
body{background:var(--bg);display:flex;flex-direction:column;user-select:none;-webkit-user-select:none;overflow-x:hidden;}
.topbar{background:linear-gradient(135deg,var(--blue),var(--blue-mid));border-bottom:3px solid var(--gold);padding:0 20px;height:60px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:300;box-shadow:0 4px 20px rgba(3,34,76,.35);flex-shrink:0;}
.top-logo{height:40px;background:#fff;padding:3px 5px;border-radius:7px;flex-shrink:0;}
.top-title{color:#fff;font-weight:800;font-size:.88rem;white-space:nowrap;}
.top-session{background:rgba(255,255,255,.1);border:1px solid rgba(212,175,55,.3);color:#fff;padding:4px 12px;border-radius:8px;font-size:.74rem;display:flex;flex-direction:column;flex-shrink:0;}
.top-session .sn{font-weight:700;color:var(--gold);}
.top-epreuve{background:var(--gold);color:var(--blue);padding:5px 14px;border-radius:50px;font-weight:800;font-size:.78rem;white-space:nowrap;flex-shrink:0;}
.top-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-shrink:0;}
.top-badge{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2);padding:4px 11px;border-radius:50px;font-size:.74rem;font-weight:700;display:flex;align-items:center;gap:5px;}
.top-badge.gold{border-color:var(--gold);color:var(--gold);}
.top-badge.red{background:rgba(220,38,38,.25);border-color:#dc2626;color:#fca5a5;}
.timer-box{background:#111;color:#fff;font-family:monospace;font-size:1.1rem;font-weight:900;padding:5px 13px;border-radius:8px;border:2px solid var(--gold);min-width:90px;text-align:center;}
.timer-box.warn{background:var(--red);animation:pulse .8s infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.05);}}
.layout{display:flex;flex:1;min-height:0;overflow:hidden;}
.sidebar{width:var(--sidebar);flex-shrink:0;background:var(--blue);display:flex;flex-direction:column;overflow-y:auto;border-right:3px solid var(--gold);}
.sidebar-head{padding:14px 16px;border-bottom:2px solid rgba(255,255,255,.1);color:#fff;font-weight:800;font-size:.88rem;display:flex;align-items:center;gap:8px;flex-shrink:0;}
.sidebar-head i{color:var(--gold);}
.stats-box{margin:10px 14px;background:rgba(255,255,255,.06);border-radius:10px;padding:10px 14px;border:1px solid rgba(255,255,255,.08);flex-shrink:0;}
.stat-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;}
.stat-row:last-child{margin-bottom:0;}
.stat-label{color:rgba(255,255,255,.6);font-size:.78rem;}
.stat-val{color:#fff;font-weight:800;font-size:.82rem;}
.stat-val.gold{color:var(--gold);}
.q-grid{padding:10px 14px 14px;display:flex;flex-wrap:wrap;gap:6px;align-content:flex-start;}
.q-btn{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.15);color:rgba(255,255,255,.7);font-size:.76rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.q-btn:hover{background:rgba(255,255,255,.2);}
.q-btn.done{background:var(--blue-mid);border-color:var(--gold);color:var(--gold);}
.q-btn.current{background:var(--gold);border-color:var(--gold);color:var(--blue);box-shadow:0 0 0 3px rgba(212,175,55,.4);}
.legend{padding:8px 14px 12px;display:flex;gap:12px;flex-shrink:0;border-top:1px solid rgba(255,255,255,.08);}
.legend-item{display:flex;align-items:center;gap:5px;font-size:.72rem;color:rgba(255,255,255,.55);}
.leg-dot{width:11px;height:11px;border-radius:50%;}
.main-content{flex:1;overflow-y:auto;display:flex;flex-direction:column;min-width:0;}
.q-header{background:#fff;border-bottom:2px solid #e8ecf5;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;position:sticky;top:0;z-index:10;box-shadow:0 2px 8px rgba(3,34,76,.06);}
.q-icon{width:30px;height:30px;background:var(--blue);color:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;}
.q-title{color:var(--blue);font-weight:700;font-size:.9rem;}
.q-counter-badge{background:var(--blue);color:var(--gold);padding:4px 13px;border-radius:50px;font-size:.78rem;font-weight:800;white-space:nowrap;}
.progress-bar{height:4px;background:#e8ecf5;flex-shrink:0;}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--blue),var(--gold));transition:width .4s;}
.q-zone{padding:24px 28px;flex:1;}
.q-text-fr{font-size:1rem;font-weight:700;color:var(--blue);line-height:1.55;margin-bottom:8px;border-left:4px solid var(--gold);padding-left:16px;}
.q-text-en{font-size:.82rem;color:#8898b0;font-style:italic;padding-left:20px;margin-bottom:20px;line-height:1.5;}
.options-list{display:flex;flex-direction:column;gap:10px;}
.option{display:flex;align-items:flex-start;gap:14px;padding:14px 17px;border-radius:12px;border:2px solid #e0e7f0;cursor:pointer;background:#fafbff;transition:all .2s;}
.option:hover{border-color:var(--blue);background:#f0f4fa;transform:translateX(3px);}
.option.selected{border-color:var(--blue);background:linear-gradient(135deg,#e8f0fe,#dce7fd);box-shadow:0 2px 12px rgba(3,34,76,.1);}
.opt-radio{width:20px;height:20px;accent-color:var(--blue);flex-shrink:0;cursor:pointer;margin-top:1px;}
.opt-texts{display:flex;flex-direction:column;gap:3px;}
.opt-fr{font-size:.92rem;color:#1a1f2e;font-weight:600;line-height:1.4;}
.opt-en{font-size:.76rem;color:#9ca3af;font-style:italic;}
.nav-bar{background:#fff;border-top:2px solid #e8ecf5;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;position:sticky;bottom:0;z-index:10;}
.btn-prev,.btn-next,.btn-submit{padding:12px 26px;border-radius:50px;font-weight:800;font-size:.88rem;cursor:pointer;border:none;transition:all .3s;font-family:inherit;display:flex;align-items:center;gap:8px;}
.btn-prev{background:#e8ecf5;color:var(--blue);border:2px solid #c8d0e0;}
.btn-prev:hover:not(:disabled){background:#dde3f0;}
.btn-prev:disabled{opacity:.35;cursor:not-allowed;}
.btn-next{background:linear-gradient(135deg,var(--blue),var(--blue-mid));color:#fff;border:2px solid var(--gold);}
.btn-next:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(3,34,76,.3);}
.btn-submit{background:linear-gradient(135deg,var(--green),#15803d);color:#fff;display:none;}
.btn-submit:hover{transform:translateY(-2px);}
.inf-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:9999;align-items:center;justify-content:center;}
.inf-overlay.show{display:flex;}
.inf-box{background:#fff;border-radius:20px;padding:36px 32px;max-width:420px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);}
.inf-box h3{color:var(--red);font-weight:800;font-size:1.3rem;margin-bottom:8px;}
.inf-box p{color:#555;font-size:.9rem;margin-bottom:20px;line-height:1.5;}
.btn-ack{background:var(--red);color:#fff;border:none;padding:12px 28px;border-radius:50px;font-weight:700;cursor:pointer;font-family:inherit;font-size:.92rem;}
@media(max-width:900px){.sidebar{display:none;}.q-zone{padding:14px 16px;}.nav-bar{padding:10px 16px;}}
@media(max-width:580px){.topbar{padding:0 10px;gap:6px;}.top-session,.top-epreuve{display:none;}}
</style>
</head>
<body>
<div class="topbar">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" class="top-logo" onerror="this.style.display='none'">
    <div class="top-title">Examen <?= htmlspecialchars($type_code) ?></div>
    <div class="top-session"><span class="sn"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($nom_session) ?></span></div>
    <div class="top-epreuve"><?= htmlspecialchars($epreuve_label) ?></div>
    <div class="top-right">
        <div class="top-badge"><i class="fas fa-user"></i><span><?= htmlspecialchars($nom_complet) ?></span></div>
        <div class="top-badge gold"><i class="fas fa-key"></i><span><?= htmlspecialchars($code_acces) ?></span></div>
        <div class="timer-box" id="timerBox">--:--:--</div>
        <div class="top-badge red" id="infrBadge" style="display:none;"><i class="fas fa-exclamation-triangle"></i><span id="infraCnt"><?= $infractions ?></span>/5</div>
    </div>
</div>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-head"><i class="fas fa-list-ol"></i>QUESTIONS</div>
        <div class="stats-box">
            <div class="stat-row"><span class="stat-label">Répondues :</span><span class="stat-val gold" id="statDone">0/<?= $nb_questions ?></span></div>
            <div class="stat-row"><span class="stat-label">Restantes :</span><span class="stat-val" id="statLeft"><?= $nb_questions ?></span></div>
        </div>
        <div class="q-grid" id="qGrid"></div>
        <div class="legend">
            <div class="legend-item"><div class="leg-dot" style="background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.2);"></div>Non répondue</div>
            <div class="legend-item"><div class="leg-dot" style="background:var(--blue-mid);border:2px solid var(--gold);"></div>Répondue</div>
        </div>
    </aside>
    <div class="main-content">
        <div class="q-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="q-icon"><i class="fas fa-question"></i></div>
                <div class="q-title" id="qTitleBar">Question 1</div>
            </div>
            <div class="q-counter-badge" id="qCounterBadge">1 / <?= $nb_questions ?></div>
        </div>
        <div class="progress-bar"><div class="progress-fill" id="progFill" style="width:0%"></div></div>
        <div class="q-zone">
            <div class="q-text-fr" id="qTextFr">Chargement...</div>
            <div class="q-text-en" id="qTextEn"></div>
            <div class="options-list" id="optList"></div>
        </div>
        <div class="nav-bar">
            <button class="btn-prev" id="btnPrev" onclick="prevQ()" disabled><i class="fas fa-arrow-left"></i> PRÉCÉDENTE</button>
            <button class="btn-next" id="btnNext" onclick="nextQ()">SUIVANTE <i class="fas fa-arrow-right"></i></button>
            <button class="btn-submit" id="btnSubmit" onclick="confirmerSoumission()"><i class="fas fa-paper-plane"></i> TERMINER</button>
        </div>
    </div>
</div>
<div class="inf-overlay" id="infraOverlay">
    <div class="inf-box">
        <h3><i class="fas fa-exclamation-triangle me-2"></i>Action interdite</h3>
        <p id="infraMsg">Une action non autorisée a été détectée.</p>
        <div style="font-size:.82rem;color:var(--red);font-weight:700;margin-bottom:16px;">Infraction <span id="infraN">1</span> sur 5</div>
        <button class="btn-ack" onclick="closeInfraction()"><i class="fas fa-check me-1"></i>Je reprends</button>
    </div>
</div>
<script>
const QUESTIONS  = <?= $questions_js ?>;
const TOTAL_Q    = <?= $nb_questions ?>;
const PARTIE     = '<?= $partie ?>';
const INFR_MAX   = 5;
let currentIdx   = <?= $current_index ?>;
let reponses     = {};
let tempsRestant = <?= $temps_restant ?>;
let infrCount    = <?= $infractions ?>;
let examSoumis   = false;
let lastInfrTime = 0;
let warned10=false, warned5=false;

(function(){const old=<?= $reponses_js ?>;for(const k in old){const v=parseInt(old[k]);if(!isNaN(v)&&v>0)reponses[parseInt(k)]=v;}})();

document.addEventListener('DOMContentLoaded',function(){buildGrid();loadQuestion(currentIdx);startTimer();attachAntiCheat();});

function buildGrid(){
    const grid=document.getElementById('qGrid');grid.innerHTML='';
    QUESTIONS.forEach(function(q,i){
        const btn=document.createElement('button');
        btn.id='qbtn'+i;btn.className='q-btn'+(reponses[q.id]?' done':'')+(i===currentIdx?' current':'');
        btn.textContent=i+1;btn.title='Question '+(i+1);
        btn.addEventListener('click',function(){loadQuestion(i);});
        grid.appendChild(btn);
    });updateStats();
}
function updateGrid(){
    QUESTIONS.forEach(function(q,i){
        const btn=document.getElementById('qbtn'+i);if(!btn)return;
        btn.className='q-btn'+(reponses[q.id]?' done':'')+(i===currentIdx?' current':'');
    });updateStats();
}
function updateStats(){
    const done=Object.keys(reponses).length;
    document.getElementById('statDone').textContent=done+'/'+TOTAL_Q;
    document.getElementById('statLeft').textContent=(TOTAL_Q-done);
    document.getElementById('progFill').style.width=(TOTAL_Q>0?Math.round(done/TOTAL_Q*100):0)+'%';
}
function loadQuestion(idx){
    if(idx<0||idx>=TOTAL_Q)return;
    currentIdx=idx;const q=QUESTIONS[idx];const num=idx+1;
    document.getElementById('qTitleBar').textContent='Question '+num;
    document.getElementById('qCounterBadge').textContent=num+' / '+TOTAL_Q;
    document.getElementById('qTextFr').textContent=q.fr;
    document.getElementById('qTextEn').textContent=q.en||'';
    const cont=document.getElementById('optList');cont.innerHTML='';
    q.opt_fr.forEach(function(txtFr,i){
        if(!txtFr&&txtFr!=='0')return;const numOpt=i+1;const sel=(reponses[q.id]===numOpt);
        const div=document.createElement('div');div.className='option'+(sel?' selected':'');
        div.dataset.qid=q.id;div.dataset.val=numOpt;
        const radio=document.createElement('input');radio.type='radio';radio.name='opt_'+q.id;radio.value=numOpt;radio.className='opt-radio';radio.checked=sel;
        const texts=document.createElement('div');texts.className='opt-texts';
        const fr=document.createElement('div');fr.className='opt-fr';fr.textContent=txtFr;texts.appendChild(fr);
        const txtEn=q.opt_en[i];if(txtEn){const en=document.createElement('div');en.className='opt-en';en.textContent=txtEn;texts.appendChild(en);}
        div.appendChild(radio);div.appendChild(texts);
        div.addEventListener('click',function(){selReponse(q.id,numOpt,div);});
        cont.appendChild(div);
    });
    document.getElementById('btnPrev').disabled=(idx===0);
    if(idx===TOTAL_Q-1){document.getElementById('btnNext').style.display='none';document.getElementById('btnSubmit').style.display='flex';}
    else{document.getElementById('btnNext').style.display='flex';document.getElementById('btnSubmit').style.display='none';}
    updateGrid();syncProg(idx);
}
function selReponse(qid,val,elem){
    reponses[qid]=val;
    document.querySelectorAll('[data-qid="'+qid+'"]').forEach(function(d){d.classList.remove('selected');d.querySelector('input').checked=false;});
    elem.classList.add('selected');elem.querySelector('input').checked=true;
    saveReponse(qid,val);updateGrid();
}
function nextQ(){
    if(currentIdx>=TOTAL_Q-1)return;
    const q=QUESTIONS[currentIdx];
    if(!reponses[q.id]){
        Swal.fire({icon:'warning',title:'⚠️ Question sans réponse',
            html:'<p style="font-family:Candara,sans-serif;">Vous n\'avez pas coché de réponse pour la question <strong>'+(currentIdx+1)+'</strong>.<br>Voulez-vous continuer quand même ?</p>',
            showCancelButton:true,confirmButtonText:'<i class="fas fa-arrow-right me-1"></i>Continuer',cancelButtonText:'<i class="fas fa-arrow-left me-1"></i>Revenir répondre',
            confirmButtonColor:'#03224c',cancelButtonColor:'#6b7280',timer:8000,timerProgressBar:true
        }).then(function(r){if(r.isConfirmed)loadQuestion(currentIdx+1);});
    } else { loadQuestion(currentIdx+1); }
}
function prevQ(){if(currentIdx>0)loadQuestion(currentIdx-1);}
function saveReponse(qid,val){fetch('save_reponse.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({question_id:qid,selected_option:val})}).catch(function(){});}
function syncProg(idx){fetch('update_progression.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current_index:idx,partie:PARTIE})}).catch(function(){});}
function confirmerSoumission(){
    const done=Object.keys(reponses).length;const manq=TOTAL_Q-done;
    let html='<div style="font-family:Candara,sans-serif;text-align:left;"><div style="background:#f4f7fc;border-radius:12px;padding:14px;margin-bottom:10px;"><p>✅ Répondues : <strong>'+done+' / '+TOTAL_Q+'</strong></p>';
    if(manq>0)html+='<p style="color:#dc2626;margin-top:4px;">⚠️ '+manq+' question(s) sans réponse</p>';
    html+='</div></div>';
    Swal.fire({title:'Terminer l\'examen ?',html:html,icon:'question',showCancelButton:true,
        confirmButtonText:'<i class="fas fa-paper-plane me-1"></i>Soumettre',
        cancelButtonText:'<i class="fas fa-times me-1"></i>Continuer',
        confirmButtonColor:'#03224c',cancelButtonColor:'#6b7280'
    }).then(function(r){
        if(r.isConfirmed){
            examSoumis=true;
            /* Pas de loader infini — redirection directe */
            window.location.href='soumettre_examen.php';
        }
    });
}
function startTimer(){
    const box=document.getElementById('timerBox');refreshTimer(box,tempsRestant);
    const iv=setInterval(function(){
        if(examSoumis){clearInterval(iv);return;}
        if(tempsRestant<=0){clearInterval(iv);examSoumis=true;Swal.fire({title:'⏰ Temps écoulé !',text:'L\'examen est automatiquement soumis.',icon:'warning',allowOutsideClick:false,showConfirmButton:false,timer:2500}).then(function(){window.location.href='soumettre_examen.php?timeout=1';});return;}
        tempsRestant--;refreshTimer(box,tempsRestant);
        if(tempsRestant<=600)box.classList.add('warn');
        if(tempsRestant===600&&!warned10){warned10=true;Swal.fire({title:'⏰ 10 min restantes',text:'Gérez votre temps.',icon:'warning',timer:4000,showConfirmButton:false});}
        if(tempsRestant===300&&!warned5){warned5=true;Swal.fire({title:'⚠️ 5 min restantes !',text:'Finalisez vos réponses !',icon:'error',timer:4000,showConfirmButton:false});}
    },1000);
}
function refreshTimer(el,s){const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sec=s%60;el.textContent=pad(h)+':'+pad(m)+':'+pad(sec);}
function pad(n){return n.toString().padStart(2,'0');}
function attachAntiCheat(){
    document.addEventListener('contextmenu',function(e){e.preventDefault();logInfr('Clic droit interdit');});
    document.addEventListener('keydown',function(e){
        if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'iIjJcC'.includes(e.key))){e.preventDefault();logInfr('Outils développeur');return false;}
        if(e.ctrlKey&&'cCvVxX'.includes(e.key)){e.preventDefault();logInfr('Copier/Coller interdit');return false;}
        if(e.ctrlKey&&'rR'.includes(e.key)){e.preventDefault();logInfr('Rafraîchissement interdit');return false;}
        if(e.key==='PrintScreen'||e.keyCode===44){e.preventDefault();logInfr('Capture d\'écran interdite');return false;}
    });
    document.addEventListener('visibilitychange',function(){if(document.hidden&&!examSoumis)logInfr('Changement d\'onglet');});
    window.addEventListener('blur',function(){if(!examSoumis)logInfr('Perte de focus');});
    document.addEventListener('copy',function(e){e.preventDefault();logInfr('Copie interdite');});
    document.addEventListener('cut',function(e){e.preventDefault();logInfr('Couper interdit');});
    document.addEventListener('selectstart',function(e){e.preventDefault();});
}
function logInfr(action){
    if(examSoumis)return;const now=Date.now();if(now-lastInfrTime<2000)return;lastInfrTime=now;infrCount++;
    document.getElementById('infraCnt').textContent=infrCount;document.getElementById('infrBadge').style.display='flex';
    document.getElementById('infraN').textContent=infrCount;document.getElementById('infraMsg').textContent=action;
    document.getElementById('infraOverlay').classList.add('show');
    fetch('register_infraction.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action})})
        .then(function(r){return r.json();})
        .then(function(d){if(d.infractions>=INFR_MAX){examSoumis=true;document.getElementById('infraOverlay').classList.remove('show');Swal.fire({title:'🔒 Examen verrouillé',html:'<p style="font-family:Candara,sans-serif;">5 infractions. Examen soumis et verrouillé.</p>',icon:'error',allowOutsideClick:false,confirmButtonColor:'#03224c'}).then(function(){window.location.href='soumettre_examen.php?lock=1&reason=5+infractions';});}}).catch(function(){});
}
function closeInfraction(){document.getElementById('infraOverlay').classList.remove('show');}
</script>
</body>
</html>