<?php
/**
 * questions.php v3 — EXASUR ANAC GABON
 * AJOUT : Bouton PDF dynamique qui respecte tous les filtres actifs
 *         (f_type, f_tq, f_q, f_sess, f_deb, f_fin)
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Traitements disponibles ──────────────────────────────── */
$traitements=[];
$tr_q=$conn->query("SELECT id,code,libelle FROM traitements_image WHERE actif=1 ORDER BY id");
if($tr_q) while($t=$tr_q->fetch_assoc()) $traitements[]=$t;
if(empty($traitements)) $traitements=[
    ['id'=>1,'code'=>'normal',    'libelle'=>'Normal'],
    ['id'=>2,'code'=>'grayscale', 'libelle'=>'Noir et Blanc'],
    ['id'=>3,'code'=>'color',     'libelle'=>'Couleur+'],
    ['id'=>4,'code'=>'hp',        'libelle'=>'Haute Pénétration'],
    ['id'=>5,'code'=>'organic',   'libelle'=>'Mat. Organique'],
    ['id'=>6,'code'=>'inorganic', 'libelle'=>'Mat. Inorganique'],
    ['id'=>7,'code'=>'contour',   'libelle'=>'Renforcement Contours'],
];

$CATS_SUSPECT = [
    1 => 'Armes à feu et armes à feu factices',
    2 => 'Armes tranchantes et objets pointus',
    3 => 'Instruments contondants',
    4 => 'Matières explosives et substances inflammables',
    5 => 'Substances chimiques et toxiques',
];

/* ── POST : Ajouter question ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_q') {
    $ite = intval($_POST['idtype_examen'] ?? 1);
    $tq  = ($_POST['type_question']??'theorique')==='pratique' ? 'pratique' : 'theorique';

    if ($tq==='pratique' && $ite!=2) {
        $_SESSION['q_err']='pratique_refused';
        header("Location: questions.php"); exit();
    }

    $qf  = $conn->real_escape_string(trim($_POST['question_text_fr']??''));
    $qe  = $conn->real_escape_string(trim($_POST['question_text_en']??''));
    $bar = floatval($_POST['bareme']??2);

    if ($tq==='pratique') {
        $o1f = 'Bagage CLAIR'; $o1e = 'CLEAR Baggage';
        $o2f = 'Bagage SUSPECT'; $o2e = 'SUSPECT Baggage';
        $co_prat = intval($_POST['correct_option_prat'] ?? 1);
        $cat_idx = intval($_POST['correct_cat_idx'] ?? 0);
        if ($co_prat === 1) {
            $co = 1; $o3f = 'NULL_STR';
        } else {
            $co = 1 + $cat_idx;
            $cat_lib = $CATS_SUSPECT[$cat_idx] ?? '';
            $o3f = $conn->real_escape_string($cat_lib);
        }
        $imgs_json='NULL'; $traits_json='NULL';
        if (!empty($_FILES['images']['name'][0])) {
            $up=[]; $traits=[]; $new_traits=$_POST['trait_new']??[];
            foreach ($_FILES['images']['tmp_name'] as $i=>$tmp) {
                if(!is_uploaded_file($tmp)) continue;
                $ext=strtolower(pathinfo($_FILES['images']['name'][$i],PATHINFO_EXTENSION));
                if(!in_array($ext,['jpg','jpeg','png','gif','webp'])) continue;
                $fn='q_'.time().'_'.$i.'_'.rand(100,999).'.'.$ext;
                if(move_uploaded_file($tmp,'../assets/images/'.$fn)){$up[]=$fn;$traits[$fn]=$new_traits[$i]??'normal';}
            }
            if($up){$imgs_json="'".$conn->real_escape_string(json_encode($up))."'";$traits_json="'".$conn->real_escape_string(json_encode($traits))."'";}
        }
        if ($qf) {
            $o3_sql = ($o3f && $o3f!=='NULL_STR') ? "'".$o3f."'" : 'NULL';
            $sql = "INSERT INTO question
                (idtype_examen, type_question, question_text_fr, question_text_en,
                 images, images_traitements, option1_fr, option1_en,
                 option2_fr, option2_en, option3_fr, correct_option, bareme)
                VALUES ($ite, 'pratique', '$qf', '$qe', $imgs_json, $traits_json,
                '$o1f', '$o1e', '$o2f', '$o2e', $o3_sql, $co, $bar)";
            $conn->query($sql);
            $_SESSION[$conn->error ? 'q_err' : 'q_msg'] = $conn->error ?: 'ok';
        }
    } else {
        $o1f=$conn->real_escape_string($_POST['option1_fr']??'');
        $o1e=$conn->real_escape_string($_POST['option1_en']??'');
        $o2f=$conn->real_escape_string($_POST['option2_fr']??'');
        $o2e=$conn->real_escape_string($_POST['option2_en']??'');
        $o3f=!empty($_POST['option3_fr'])?$conn->real_escape_string($_POST['option3_fr']):'';
        $o4f=!empty($_POST['option4_fr'])?$conn->real_escape_string($_POST['option4_fr']):'';
        $co=intval($_POST['correct_option']??1);
        if ($qf && $o1f && $o2f) {
            $o3s=$o3f?"'$o3f'":'NULL'; $o4s=$o4f?"'$o4f'":'NULL';
            $conn->query("INSERT INTO question
                (idtype_examen,type_question,question_text_fr,question_text_en,
                 option1_fr,option1_en,option2_fr,option2_en,option3_fr,option4_fr,
                 correct_option,bareme)
                VALUES ($ite,'theorique','$qf','$qe','$o1f','$o1e','$o2f','$o2e',$o3s,$o4s,$co,$bar)");
            $_SESSION[$conn->error ? 'q_err' : 'q_msg'] = $conn->error ?: 'ok';
        }
    }
    header("Location: questions.php"); exit();
}

/* ── Filtres ──────────────────────────────────────────────── */
$ft    = intval($_GET['f_type']  ?? 0);
$ftq   = $conn->real_escape_string($_GET['f_tq']  ?? '');
$fq    = $conn->real_escape_string($_GET['f_q']   ?? '');
$fsess = intval($_GET['f_sess']  ?? 0);
$fdeb  = $conn->real_escape_string($_GET['f_deb'] ?? '');
$ffin  = $conn->real_escape_string($_GET['f_fin'] ?? '');

$w = "WHERE 1=1";
if ($ft)    $w .= " AND q.idtype_examen=$ft";
if ($ftq)   $w .= " AND q.type_question='$ftq'";
if ($fq)    $w .= " AND (q.question_text_fr LIKE '%$fq%' OR q.question_text_en LIKE '%$fq%')";
if ($fsess) $w .= " AND q.id IN (SELECT question_id FROM session_questions WHERE session_id=$fsess)";
if ($fdeb)  $w .= " AND q.created_at >= '$fdeb 00:00:00'";
if ($ffin)  $w .= " AND q.created_at <= '$ffin 23:59:59'";

$questions = $conn->query("SELECT q.*,te.code AS tc,te.nom_fr AS tn FROM question q JOIN type_examen te ON q.idtype_examen=te.idtype_examen $w ORDER BY q.id DESC");

$types_arr=[];
$tr2=$conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
while($t=$tr2->fetch_assoc()) $types_arr[]=$t;

$sessions_f=$conn->query("SELECT id_session,nom_session,date_debut FROM session_examen ORDER BY date_debut DESC LIMIT 300");

$nb_tot=$conn->query("SELECT COUNT(*) FROM question")->fetch_row()[0];
$nb_th =$conn->query("SELECT COUNT(*) FROM question WHERE type_question='theorique'")->fetch_row()[0];
$nb_pr =$conn->query("SELECT COUNT(*) FROM question WHERE type_question='pratique'")->fetch_row()[0];
$nb_fi =$questions->num_rows;

$qmsg=$_SESSION['q_msg']??''; unset($_SESSION['q_msg']);
$qerr=$_SESSION['q_err']??''; unset($_SESSION['q_err']);
$active_page='questions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Questions — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.tp{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}.tp-FORM{background:#fce7f3;color:#9d174d;}
.q-txt{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.img-th{width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid #ddd;cursor:pointer;transition:transform .2s;}
.img-th:hover{transform:scale(1.15);}
.opt-ok{color:#16a34a;font-weight:700;font-size:.84rem;}

/* ════════════════════════════════════════════════════════════
   BOUTON PDF DYNAMIQUE
   ─────────────────────────────────────────────────────────
   Positionné dans le card-admin-header à droite du badge.
   Affiche le nombre de questions filtrées dans une pastille.
   Couleur dorée ANAC pour se démarquer visuellement.
════════════════════════════════════════════════════════════ */
.btn-pdf-questions {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    border: none;
    border-radius: 8px;
    font-family: 'Outfit', 'Candara', sans-serif;
    font-weight: 700;
    font-size: .82rem;
    cursor: pointer;
    background: linear-gradient(135deg, #b8860b, var(--gold, #D4AF37));
    color: var(--blue, #03224c);
    box-shadow: 0 2px 10px rgba(212,175,55,.35);
    transition: all .22s ease;
    margin-left: auto;  /* pousse le bouton à droite */
    white-space: nowrap;
}
.btn-pdf-questions:hover {
    background: linear-gradient(135deg, var(--gold, #D4AF37), #f0c040);
    box-shadow: 0 4px 16px rgba(212,175,55,.5);
    transform: translateY(-1px);
}
.btn-pdf-questions:active { transform: translateY(0); }
.btn-pdf-questions i { font-size: .88rem; }
.btn-pdf-questions .pdf-count {
    background: rgba(3,34,76,.2);
    padding: 1px 8px;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    transition: background .2s;
}
.btn-pdf-questions:hover .pdf-count { background: rgba(3,34,76,.35); }

/* Tooltip filtre actifs sous le bouton */
.pdf-filter-hint {
    font-size: .68rem;
    color: #9ca3af;
    margin-top: 3px;
    font-style: italic;
    display: none;    /* affiché par JS si des filtres sont actifs */
    text-align: right;
    padding-right: 2px;
}
.pdf-filter-hint.show { display: block; }
.pdf-filter-hint i { color: var(--gold, #D4AF37); }

/* ── TRADUCTION AUTOMATIQUE ── */
.trad-hint{font-size:.68rem;color:#6366f1;font-weight:600;background:#eef2ff;border:1px solid #c7d2fe;border-radius:50px;padding:1px 8px;margin-left:6px;vertical-align:middle;letter-spacing:.2px;}
.btn-trad-manuel{font-size:.68rem;font-weight:700;color:#0891b2;background:#e0f9ff;border:1px solid #a5f3fc;border-radius:50px;padding:2px 9px;margin-left:6px;cursor:pointer;font-family:inherit;transition:all .2s;vertical-align:middle;}
.btn-trad-manuel:hover{background:#0891b2;color:#fff;border-color:#0891b2;}
.btn-trad-manuel i{margin-right:3px;}
.trad-wrap{position:relative;}
.trad-spinner{display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#6366f1;font-size:.9rem;pointer-events:none;}
.trad-ok{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#16a34a;font-size:.9rem;pointer-events:none;animation:fadeInCheck .4s ease;}
@keyframes fadeInCheck{from{opacity:0;transform:translateY(-50%) scale(.6);}to{opacity:1;transform:translateY(-50%) scale(1);}}
.champ-en.trad-actif{border-color:#6366f1!important;background:#fafbff!important;transition:border-color .3s,background .3s;}
textarea.champ-fr,textarea.champ-en{padding-right:32px;}
.trad-status-bar{display:none;align-items:center;gap:8px;background:linear-gradient(135deg,#eef2ff,#f0f9ff);border:1.5px solid #c7d2fe;border-radius:10px;padding:8px 14px;font-size:.8rem;color:#3730a3;font-weight:600;margin-bottom:12px;animation:slideDown .3s ease;}
.trad-status-bar.show{display:flex;}
.trad-status-bar i{color:#6366f1;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}

/* Stats KPI */
.stat-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}
.stat-c{flex:1;min-width:110px;background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(3,34,76,.07);text-align:center;border-top:3px solid var(--gold);}
.stat-c .num{font-size:1.7rem;font-weight:800;color:var(--blue);line-height:1;}
.stat-c .lbl{font-size:.71rem;color:#6b7280;margin-top:4px;font-weight:600;text-transform:uppercase;}

/* Filtres */
.filter-ext{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding:14px 16px;background:var(--gray-bg);border-bottom:1px solid var(--gray-border);}
.fg{display:flex;flex-direction:column;gap:3px;flex:1;min-width:120px;}
.fg .fl{font-size:.73rem;font-weight:700;color:var(--blue);}
.fg .fc{padding:7px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-family:inherit;font-size:.83rem;}
.fg .fc:focus{outline:none;border-color:var(--blue);}
.btn-flt{padding:8px 14px;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-weight:700;font-size:.83rem;display:flex;align-items:center;gap:5px;white-space:nowrap;}
.btn-apply{background:var(--blue);color:#fff;}.btn-apply:hover{opacity:.9;}
.btn-rst{background:#e8ecf5;color:var(--blue);border:1.5px solid #c8d0e0;}

/* Zone IF pratique */
.if-answer-zone{background:#fff0f0;border:2px solid #fecaca;border-radius:14px;padding:18px;}
.if-answer-zone .label{font-weight:700;font-size:.88rem;color:#dc2626;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.radio-if{display:flex;flex-direction:column;gap:10px;margin-bottom:12px;}
.radio-if label{display:flex;align-items:center;gap:10px;padding:12px 16px;border:2px solid #fecaca;border-radius:10px;cursor:pointer;background:white;font-weight:600;font-size:.92rem;transition:all .2s;}
.radio-if label:hover{border-color:#dc2626;background:#fff5f5;}
.radio-if label.checked{border-color:#dc2626;background:#fee2e2;}
.radio-if input[type="radio"]{width:18px;height:18px;accent-color:#dc2626;flex-shrink:0;}
.cats-block{background:#fff;border:1.5px solid #fca5a5;border-radius:10px;padding:14px;display:none;}
.cats-block.show{display:block;}
.cats-block .label-cat{font-size:.78rem;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.cat-radio-row{display:flex;flex-direction:column;gap:6px;}
.cat-opt{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;background:white;transition:all .2s;}
.cat-opt:hover{border-color:#dc2626;background:#fff5f5;}
.cat-opt.selected{border-color:#dc2626;background:#fee2e2;}
.cat-opt input[type="radio"]{width:16px;height:16px;accent-color:#dc2626;flex-shrink:0;}
.cat-opt label{cursor:pointer;font-size:.86rem;font-weight:600;color:#7f1d1d;}

/* Upload images */
.img-upload-zone{background:#f0f9ff;border:2px dashed #0891b2;border-radius:14px;padding:18px;margin-top:10px;}
.zone-title{color:#0891b2;font-weight:700;font-size:.88rem;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.drop-zone{border:2px dashed #c8d0e0;border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbff;}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--blue);background:#f0f4ff;}
.drop-zone input{display:none;}
.img-preview-grid{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px;}
.img-card{background:#fff;border:2px solid #e0e7f0;border-radius:12px;overflow:hidden;width:195px;flex-shrink:0;box-shadow:0 2px 8px rgba(3,34,76,.07);}
.img-card img{width:100%;height:115px;object-fit:cover;display:block;}
.img-card-body{padding:9px 11px;}
.img-card-name{font-size:.71rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px;}
.treat-select{width:100%;padding:6px 10px;border:1.5px solid #d1d5db;border-radius:7px;font-family:inherit;font-size:.8rem;background:#fff;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-question-circle me-2"></i>Questions</div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span style="font-size:.82rem;color:#6c7a8d;"><?= $nb_tot ?> total · <?= $nb_th ?> théoriques · <?= $nb_pr ?> pratiques IF</span>
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']??'') ?></span>
    </div>
</div>
<div class="admin-content">

<!-- KPIs -->
<div class="stat-row">
    <div class="stat-c"><div class="num" id="kpi-tot"><?= $nb_tot ?></div><div class="lbl"><i class="fas fa-list me-1"></i>Total</div></div>
    <div class="stat-c" style="border-top-color:#1e40af;"><div class="num" id="kpi-th" style="color:#1e40af;"><?= $nb_th ?></div><div class="lbl">📝 Théoriques</div></div>
    <div class="stat-c" style="border-top-color:#9d174d;"><div class="num" id="kpi-pr" style="color:#9d174d;"><?= $nb_pr ?></div><div class="lbl">🖼️ Pratiques IF</div></div>
    <div class="stat-c" style="border-top-color:#7c3aed;"><div class="num" id="kpi-fi" style="color:#7c3aed;"><?= $nb_fi ?></div><div class="lbl"><i class="fas fa-filter me-1"></i>Filtrés</div></div>
</div>

<!-- ══ FORMULAIRE AJOUT ══ -->
<div class="add-panel mb-4">
    <div class="add-panel-header" id="addHdr">
        <i class="fas fa-plus-circle" style="color:var(--gold)"></i>
        <span style="font-weight:700;">Ajouter une question</span>
        <i class="fas fa-chevron-down ms-auto" id="addChv"></i>
    </div>
    <div class="add-panel-body d-none" id="addBody">
        <form method="POST" enctype="multipart/form-data" id="addForm">
            <input type="hidden" name="action" value="add_q">

            <div class="trad-status-bar" id="tradStatusBar">
                <i class="fas fa-circle-notch fa-spin"></i>
                <span id="tradStatusTxt">Traduction en cours...</span>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-admin">Type d'examen *</label>
                    <select name="idtype_examen" class="form-select-admin" id="qTE" required onchange="onQTE(this)">
                        <option value="">-- Choisir --</option>
                        <?php foreach($types_arr as $t): ?>
                        <option value="<?= $t['idtype_examen'] ?>" data-code="<?= $t['code'] ?>">
                            <?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Type de question *</label>
                    <select name="type_question" class="form-select-admin" id="qTQ" onchange="onQTQ(this.value)">
                        <option value="theorique">📝 Théorique (QCM texte)</option>
                        <option value="pratique" id="optPratique" disabled>🖼️ Pratique images — IF uniquement</option>
                    </select>
                    <div id="pratHint" style="font-size:.72rem;color:#9ca3af;margin-top:3px;display:none;">
                        <i class="fas fa-info-circle me-1" style="color:var(--gold);"></i>Disponible seulement pour IF
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label-admin">Barème (pts)</label>
                    <input type="number" name="bareme" class="form-control-admin" value="2" step="0.5" min="0.5">
                </div>
                <div class="col-md-2" id="colCorrect">
                    <label class="form-label-admin">Bonne réponse *</label>
                    <select name="correct_option" class="form-select-admin">
                        <option value="1">Option 1</option><option value="2">Option 2</option>
                        <option value="3">Option 3</option><option value="4">Option 4</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-admin">
                        🇫🇷 Question (FR) *
                        <span class="trad-hint">Saisir en français → traduction auto EN</span>
                    </label>
                    <div class="trad-wrap">
                        <textarea name="question_text_fr" id="q_fr" class="form-control-admin champ-fr"
                                  data-cible="q_en" rows="3" required
                                  placeholder="Libellé de la question en français..."></textarea>
                        <span class="trad-spinner" id="spin_q_fr"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-admin">
                        🇬🇧 Question (EN)
                        <button type="button" class="btn-trad-manuel" onclick="traduireManuel('q_fr','q_en')">
                            <i class="fas fa-language"></i> Traduire
                        </button>
                    </label>
                    <div class="trad-wrap">
                        <textarea name="question_text_en" id="q_en" class="form-control-admin champ-en"
                                  rows="3" placeholder="Traduction automatique en anglais..."></textarea>
                        <span class="trad-ok" id="ok_q_en" style="display:none;"><i class="fas fa-check-circle"></i></span>
                    </div>
                </div>

                <div id="theoricOpts" class="col-12">
                    <div class="row g-3">
                        <?php for($n=1;$n<=4;$n++): $req=$n<=2?'required':''; ?>
                        <div class="col-md-6">
                            <label class="form-label-admin">
                                🇫🇷 Option <?= $n ?> <?= $req?'*':'' ?>
                                <span class="trad-hint">→ auto EN</span>
                            </label>
                            <div class="trad-wrap">
                                <input type="text" name="option<?= $n ?>_fr" id="opt<?= $n ?>_fr"
                                       class="form-control-admin champ-fr" data-cible="opt<?= $n ?>_en"
                                       <?= $req ?> placeholder="Option <?= $n ?> en français">
                                <span class="trad-spinner" id="spin_opt<?= $n ?>_fr"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-admin">
                                🇬🇧 Option <?= $n ?> EN
                                <button type="button" class="btn-trad-manuel"
                                        onclick="traduireManuel('opt<?= $n ?>_fr','opt<?= $n ?>_en')">
                                    <i class="fas fa-language"></i> Traduire
                                </button>
                            </label>
                            <div class="trad-wrap">
                                <input type="text" name="option<?= $n ?>_en" id="opt<?= $n ?>_en"
                                       class="form-control-admin champ-en" placeholder="Traduction auto en anglais...">
                                <span class="trad-ok" id="ok_opt<?= $n ?>_en" style="display:none;"><i class="fas fa-check-circle"></i></span>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="col-12 d-none" id="pratAnswerBlock">
                    <div class="if-answer-zone">
                        <div class="label">
                            <i class="fas fa-clipboard-check" style="color:#dc2626;"></i>
                            Bonne réponse pour cette question
                        </div>
                        <div class="radio-if" id="mainChoiceIf">
                            <label id="lbl_clair" onclick="onPratAnswer(1)">
                                <input type="radio" name="correct_option_prat" value="1" id="rClair">
                                🟢 Bagage CLAIR — Aucun objet prohibé
                            </label>
                            <label id="lbl_susp" onclick="onPratAnswer(2)">
                                <input type="radio" name="correct_option_prat" value="2" id="rSusp">
                                🔴 Bagage SUSPECT — Objet dangereux identifié
                            </label>
                        </div>
                        <div class="cats-block" id="catsBlock">
                            <div class="label-cat"><i class="fas fa-exclamation-triangle me-1"></i>Catégorie de menace (obligatoire)</div>
                            <div class="cat-radio-row">
                                <?php foreach($CATS_SUSPECT as $ci=>$cv): ?>
                                <div class="cat-opt" id="catOpt_<?= $ci ?>" onclick="selectCat(<?= $ci ?>)">
                                    <input type="radio" name="correct_cat_idx" value="<?= $ci ?>" id="rCat<?= $ci ?>">
                                    <label for="rCat<?= $ci ?>"><?= htmlspecialchars($cv) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-none" id="rowImg">
                    <div class="img-upload-zone">
                        <div class="zone-title">
                            <i class="fas fa-images"></i>Images du scanner (IF Pratique)
                            <span style="background:var(--gold);color:var(--blue);padding:2px 9px;border-radius:50px;font-size:.72rem;font-weight:700;">
                                Chaque image = 1 traitement
                            </span>
                        </div>
                        <div class="drop-zone" id="dropZone" onclick="document.getElementById('imgInput').click()">
                            <input type="file" id="imgInput" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif" onchange="onFilesSelected(this.files)">
                            <span style="font-size:2rem;color:#c8d0e0;display:block;margin-bottom:8px;"><i class="fas fa-cloud-upload-alt"></i></span>
                            <div style="font-size:.84rem;color:#6b7280;">Cliquez ou glissez les images</div>
                            <div style="font-size:.73rem;color:#9ca3af;margin-top:4px;">Associez un traitement unique à chaque image</div>
                        </div>
                        <div class="img-preview-grid" id="imgPreviewGrid"></div>
                        <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:9px;padding:10px 14px;margin-top:12px;font-size:.81rem;color:#3730a3;">
                            <i class="fas fa-info-circle me-1"></i>
                            Lors de l'examen, cliquer sur un traitement affichera l'image correspondante.
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Enregistrer la question</button>
                <button type="button" class="btn-anac" style="background:white;color:var(--blue);" onclick="closeAddPanel()">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ TABLEAU QUESTIONS ══ -->
<div class="card-admin">
    <div class="card-admin-header" style="flex-wrap:wrap;gap:8px;row-gap:6px;">
        <i class="fas fa-list me-2"></i>
        <h5>Liste des questions</h5>
        <span class="badge-count ms-2" id="badgeQ"><?= $nb_fi ?></span>

        <!-- ══════════════════════════════════════════════════════
             BOUTON PDF DYNAMIQUE
             ──────────────────────────────────────────────────────
             Construit l'URL de print_questions.php avec TOUS les
             filtres actifs : type, épreuve, texte, session, dates.
             Ouvre dans un nouvel onglet pour aperçu + impression.
        ══════════════════════════════════════════════════════ -->
        <div style="margin-left:auto;display:flex;flex-direction:column;align-items:flex-end;">
            <button class="btn-pdf-questions" id="btnPdfQ" onclick="ouvrirImpression()">
                <i class="fas fa-file-pdf"></i>
                <span>Imprimer / PDF</span>
                <span class="pdf-count" id="pdfCount"><?= $nb_fi ?></span>
            </button>
            <!-- Indication des filtres actifs sous le bouton -->
            <div class="pdf-filter-hint <?= ($ft||$ftq||$fq||$fsess||$fdeb||$ffin)?'show':'' ?>" id="pdfFilterHint">
                <i class="fas fa-filter"></i>
                Impression avec filtres actifs
            </div>
        </div>
    </div>

    <div class="card-admin-body p-0">
        <!-- Filtres étendus -->
        <form method="GET" id="filterForm">
            <div class="filter-ext">
                <div class="fg" style="min-width:180px;">
                    <span class="fl"><i class="fas fa-search me-1"></i>Recherche</span>
                    <input type="text" name="f_q" class="fc" id="srchQ"
                           placeholder="Texte question..."
                           value="<?= htmlspecialchars($_GET['f_q']??'') ?>">
                </div>
                <div class="fg" style="max-width:130px;">
                    <span class="fl"><i class="fas fa-tag me-1"></i>Type examen</span>
                    <select name="f_type" class="fc" id="fTE" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <?php foreach($types_arr as $t): ?>
                        <option value="<?= $t['idtype_examen'] ?>" <?= $ft==$t['idtype_examen']?'selected':'' ?>><?= $t['code'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg" style="max-width:130px;">
                    <span class="fl"><i class="fas fa-layer-group me-1"></i>Épreuve</span>
                    <select name="f_tq" class="fc" id="fTQ" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        <option value="theorique" <?= $ftq==='theorique'?'selected':'' ?>>📝 Théorique</option>
                        <option value="pratique"  <?= $ftq==='pratique' ?'selected':'' ?>>🖼️ Pratique</option>
                    </select>
                </div>
                <div class="fg" style="min-width:180px;">
                    <span class="fl"><i class="fas fa-calendar-alt me-1"></i>Session</span>
                    <select name="f_sess" class="fc" onchange="this.form.submit()">
                        <option value="">Toutes sessions</option>
                        <?php if($sessions_f) while($sf=$sessions_f->fetch_assoc()): ?>
                        <option value="<?= $sf['id_session'] ?>" <?= $fsess==$sf['id_session']?'selected':'' ?>>
                            <?= htmlspecialchars(mb_substr($sf['nom_session'],0,38)) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="fg" style="max-width:145px;">
                    <span class="fl"><i class="fas fa-calendar me-1"></i>Ajoutée du</span>
                    <input type="date" name="f_deb" class="fc"
                           value="<?= htmlspecialchars($_GET['f_deb']??'') ?>"
                           onchange="this.form.submit()">
                </div>
                <div class="fg" style="max-width:145px;">
                    <span class="fl">au</span>
                    <input type="date" name="f_fin" class="fc"
                           value="<?= htmlspecialchars($_GET['f_fin']??'') ?>"
                           onchange="this.form.submit()">
                </div>
                <div style="display:flex;gap:6px;align-items:flex-end;">
                    <button type="submit" class="btn-flt btn-apply"><i class="fas fa-search"></i>Filtrer</button>
                    <a href="questions.php" class="btn-flt btn-rst" style="text-decoration:none;"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>

        <!-- Tableau -->
        <div style="overflow-x:auto;">
            <table class="table-admin" id="tblQ">
                <thead>
                    <tr><th>#</th><th>Type</th><th>Épreuve</th><th>Question</th><th>Images</th><th>Barème</th><th>Bonne réponse</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php $questions->data_seek(0); while($q=$questions->fetch_assoc()):
                    $imgs_q = !empty($q['images']) ? (json_decode($q['images'],true)??[]) : [];
                    $traits_q= !empty($q['images_traitements']) ? (json_decode($q['images_traitements'],true)??[]) : [];
                    $co = intval($q['correct_option']);
                    if ($q['type_question']==='pratique') {
                        if ($co===1) $rep_ok = '🟢 Bagage CLAIR';
                        else {
                            $cat_lib = $q['option3_fr'] ?? '';
                            $rep_ok = '🔴 SUSPECT' . ($cat_lib ? ' — '.$cat_lib : '');
                        }
                    } else {
                        $col_ok = 'option'.$co.'_fr';
                        $rep_ok = '✅ '.htmlspecialchars(mb_substr($q[$col_ok]??'',0,40));
                    }
                ?>
                <tr data-type="<?= $q['tc'] ?>" data-tq="<?= $q['type_question'] ?>"
                    data-s="<?= strtolower(htmlspecialchars($q['question_text_fr'])) ?>">
                    <td style="color:#9ca3af;font-size:.78rem;font-weight:600;">#<?= $q['id'] ?></td>
                    <td><span class="tp tp-<?= $q['tc'] ?>"><?= $q['tc'] ?></span></td>
                    <td>
                        <?= $q['type_question']==='pratique'
                            ? '<span style="font-size:.7rem;background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:20px;">🖼️ Pratique</span>'
                            : '<span style="font-size:.7rem;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:20px;">📝 Théorique</span>' ?>
                    </td>
                    <td>
                        <div class="q-txt" title="<?= htmlspecialchars($q['question_text_fr']) ?>">
                            <?= htmlspecialchars($q['question_text_fr']) ?>
                        </div>
                        <?php if($q['question_text_en']): ?>
                        <div style="font-size:.73rem;color:#9ca3af;font-style:italic;">
                            <?= htmlspecialchars(mb_substr($q['question_text_en'],0,55)) ?>…
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($imgs_q)): foreach(array_slice($imgs_q,0,3) as $im):
                            $tc = $traits_q[$im]??'normal'; ?>
                        <div style="display:inline-flex;flex-direction:column;align-items:center;gap:1px;margin-right:3px;">
                            <img src="../assets/images/<?= htmlspecialchars($im) ?>" class="img-th"
                                 onerror="this.style.opacity='.3'"
                                 onclick='showImgs(<?= json_encode($imgs_q) ?>)'>
                            <span style="font-size:.6rem;color:#6366f1;"><?= htmlspecialchars($tc) ?></span>
                        </div>
                        <?php endforeach;
                        if(count($imgs_q)>3): ?><small style="color:#9ca3af;">+<?= count($imgs_q)-3 ?></small><?php endif;
                        else: ?>—<?php endif; ?>
                    </td>
                    <td style="font-weight:700;"><?= $q['bareme'] ?>pts</td>
                    <td class="opt-ok" style="font-size:.82rem;max-width:200px;">
                        <?= $q['type_question']==='pratique'
                            ? htmlspecialchars($rep_ok)
                            : $rep_ok ?>
                    </td>
                    <td>
                        <a href="questions_edit.php?id=<?= $q['id'] ?>" class="btn-icon btn-icon-edit" title="Modifier"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</main>
</div>

<!-- Modal images -->
<div class="modal fade modal-admin" id="imgMod" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-images me-2"></i>Aperçu images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="imgBody"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));
const TRAITEMENTS=<?= json_encode($traitements) ?>;
const CATS_SUSPECT=<?= json_encode($CATS_SUSPECT) ?>;

/* ════════════════════════════════════════════════════════════════════
   BOUTON PDF — IMPRESSION DYNAMIQUE AVEC FILTRES
   ─────────────────────────────────────────────────────────────────
   Lit les valeurs actuelles des champs de filtre du formulaire,
   construit l'URL de print_questions.php avec tous les paramètres,
   puis ouvre un nouvel onglet pour aperçu + impression PDF.

   Paramètres transmis à print_questions.php :
     f_type  : idtype_examen sélectionné (int, 0 = tous)
     f_tq    : theorique | pratique | '' (tous)
     f_q     : texte de recherche
     f_sess  : id_session (int, 0 = toutes)
     f_deb   : date début (YYYY-MM-DD)
     f_fin   : date fin   (YYYY-MM-DD)
════════════════════════════════════════════════════════════════════ */
function ouvrirImpression() {
    /* Récupérer les valeurs actuelles des filtres */
    const fType = document.querySelector('select[name="f_type"]')?.value  || '';
    const fTq   = document.querySelector('select[name="f_tq"]')?.value    || '';
    const fQ    = document.querySelector('input[name="f_q"]')?.value.trim() || '';
    const fSess = document.querySelector('select[name="f_sess"]')?.value  || '';
    const fDeb  = document.querySelector('input[name="f_deb"]')?.value    || '';
    const fFin  = document.querySelector('input[name="f_fin"]')?.value    || '';

    /* Compter les filtres actifs pour l'affichage utilisateur */
    const filtresActifs = [fType,fTq,fQ,fSess,fDeb,fFin].filter(v=>v!=='').length;

    /* Construire les paramètres URL */
    const params = new URLSearchParams();
    if (fType) params.set('f_type', fType);
    if (fTq)   params.set('f_tq',   fTq);
    if (fQ)    params.set('f_q',    fQ);
    if (fSess) params.set('f_sess', fSess);
    if (fDeb)  params.set('f_deb',  fDeb);
    if (fFin)  params.set('f_fin',  fFin);

    /* URL finale de print_questions.php */
    const url = 'print_questions.php' + (params.toString() ? '?' + params.toString() : '');

    /* Confirmation SweetAlert2 si beaucoup de questions (pas de filtre) */
    const nbQuestions = parseInt(document.getElementById('pdfCount')?.textContent || '0');

    if (nbQuestions > 200 && filtresActifs === 0) {
        Swal.fire({
            icon             : 'warning',
            title            : '📄 Volume important',
            html             : `<p>Vous allez imprimer <strong>${nbQuestions} questions</strong> sans filtre.<br>
                                Ce document peut être volumineux.</p>
                                <p style="margin-top:8px;font-size:.88rem;color:#6b7280;">
                                Utilisez les filtres pour réduire le nombre de questions si nécessaire.</p>`,
            showCancelButton : true,
            confirmButtonText: '<i class="fas fa-print me-1"></i>Continuer',
            cancelButtonText : 'Annuler',
            confirmButtonColor: '#03224c',
            cancelButtonColor : '#6b7280',
        }).then(r => { if (r.isConfirmed) window.open(url, '_blank'); });
    } else {
        /* Ouvrir directement dans un nouvel onglet */
        window.open(url, '_blank');
    }
}

/* ── Mettre à jour le compteur PDF en temps réel ────────────────
   Observateur sur le badge filtrés (KPI) pour synchroniser
   le compteur du bouton PDF dès que les filtres changent.          */
const kpiFi    = document.getElementById('kpi-fi');
const pdfCount = document.getElementById('pdfCount');
const pdfHint  = document.getElementById('pdfFilterHint');

if (kpiFi && pdfCount) {
    /* Observer les changements du KPI filtré */
    const obs = new MutationObserver(() => {
        pdfCount.textContent = kpiFi.textContent;
    });
    obs.observe(kpiFi, { childList: true, subtree: true, characterData: true });
}

/* Raccourci clavier : Ctrl+P → appeler ouvrirImpression() au lieu
   de l'impression native du navigateur (optionnel, non bloquant)  */
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        /* Vérifier si on est sur la page questions */
        if (document.getElementById('btnPdfQ')) {
            e.preventDefault();
            ouvrirImpression();
        }
    }
});

/* ── Panneau ajout ──────────────────────────────────────────── */
document.getElementById('addHdr').addEventListener('click',()=>{
    const b=document.getElementById('addBody'),c=document.getElementById('addChv');
    b.classList.toggle('d-none');c.style.transform=b.classList.contains('d-none')?'':'rotate(180deg)';
});
function closeAddPanel(){document.getElementById('addBody').classList.add('d-none');document.getElementById('addChv').style.transform='';}

/* ── Changement Type examen ─────────────────────────────── */
function onQTE(sel){
    const code=sel.options[sel.selectedIndex]?.dataset.code||'';
    const isIF=(code==='IF'||sel.value=='2');
    const op=document.getElementById('optPratique');
    op.disabled=!isIF;
    document.getElementById('pratHint').style.display=!isIF?'block':'none';
    if(!isIF && document.getElementById('qTQ').value==='pratique'){
        document.getElementById('qTQ').value='theorique';
        onQTQ('theorique');
    }
}

/* ── Changement Type question ───────────────────────────── */
function onQTQ(v){
    const isIF=document.getElementById('qTE').options[document.getElementById('qTE').selectedIndex]?.dataset.code==='IF';
    if(v==='pratique'&&!isIF){
        Swal.fire({icon:'warning',title:'⚠️ Non autorisé',html:'<p>La pratique (images) est réservée à l\'examen <strong>IF — Inspection Filtrage</strong>.</p>',confirmButtonColor:'#03224c'});
        document.getElementById('qTQ').value='theorique';return;
    }
    const isPrat=(v==='pratique');
    document.getElementById('theoricOpts').style.display=isPrat?'none':'';
    document.getElementById('colCorrect').style.display=isPrat?'none':'';
    document.getElementById('pratAnswerBlock').classList.toggle('d-none',!isPrat);
    document.getElementById('rowImg').classList.toggle('d-none',!isPrat);
    document.querySelectorAll('#theoricOpts input[required], #theoricOpts textarea[required]').forEach(el=>{
        el.required = !isPrat; el.disabled = isPrat;
    });
    const qtf = document.querySelector('textarea[name="question_text_fr"]');
    if(qtf){ qtf.required=true; qtf.disabled=false; }
}

/* ── Réponse IF pratique ────────────────────────────────── */
function onPratAnswer(v){
    document.getElementById('lbl_clair').classList.toggle('checked',v===1);
    document.getElementById('lbl_susp').classList.toggle('checked',v===2);
    document.getElementById('rClair').checked=(v===1);
    document.getElementById('rSusp').checked=(v===2);
    document.getElementById('catsBlock').classList.toggle('show',v===2);
    if(v===1){
        document.querySelectorAll('.cat-opt').forEach(el=>el.classList.remove('selected'));
        document.querySelectorAll('[name="correct_cat_idx"]').forEach(r=>r.checked=false);
    }
}
function selectCat(ci){
    document.querySelectorAll('.cat-opt').forEach(el=>el.classList.remove('selected'));
    document.getElementById('catOpt_'+ci).classList.add('selected');
    document.getElementById('rCat'+ci).checked=true;
}

/* ── Upload images ──────────────────────────────────────── */
function buildTraitSelect(name,def='normal'){
    let h=`<select name="${name}" class="treat-select">`;
    TRAITEMENTS.forEach(t=>h+=`<option value="${t.code}"${t.code===def?' selected':''}>${t.libelle}</option>`);
    return h+'</select>';
}
function onFilesSelected(files){
    const grid=document.getElementById('imgPreviewGrid');grid.innerHTML='';
    Array.from(files).forEach((file,i)=>{
        const r=new FileReader();
        r.onload=e=>{
            const card=document.createElement('div');card.className='img-card';
            const esc=s=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;};
            card.innerHTML=`<img src="${e.target.result}" alt="">
            <div class="img-card-body">
                <div class="img-card-name" title="${esc(file.name)}">${esc(file.name)}</div>
                <div style="font-size:.71rem;color:#6b7280;margin-bottom:5px;"><i class="fas fa-palette me-1" style="color:var(--gold);"></i>Traitement :</div>
                ${buildTraitSelect('trait_new['+i+']')}
            </div>`;
            grid.appendChild(card);
        };r.readAsDataURL(file);
    });
}
const dz=document.getElementById('dropZone');
if(dz){
    dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('dragover');});
    dz.addEventListener('dragleave',()=>dz.classList.remove('dragover'));
    dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('dragover');document.getElementById('imgInput').files=e.dataTransfer.files;onFilesSelected(e.dataTransfer.files);});
}

/* ── Filtre texte Entrée ─────────────────────────────── */
document.getElementById('srchQ').addEventListener('keypress',function(e){
    if(e.key==='Enter'){e.preventDefault();document.getElementById('filterForm').submit();}
});

/* ── Modal images ────────────────────────────────────── */
function showImgs(imgs){
    document.getElementById('imgBody').innerHTML='<div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">'+imgs.map(i=>`<img src="../assets/images/${i}" style="max-height:260px;border-radius:10px;object-fit:contain;" onerror="this.style.opacity='.3'">`).join('')+'</div>';
    new bootstrap.Modal(document.getElementById('imgMod')).show();
}

/* ════════════════════════════════════════════════════════════════
   SYSTÈME DE TRADUCTION AUTOMATIQUE FR → EN
════════════════════════════════════════════════════════════════ */
const DELAI_TRAD_MS = 800;
const tradTimers    = {};
let   tradEnCours   = 0;

function majBarreStatut(actif, message = 'Traduction en cours...') {
    const barre = document.getElementById('tradStatusBar');
    const txt   = document.getElementById('tradStatusTxt');
    if (!barre) return;
    txt.textContent = message;
    barre.classList.toggle('show', actif);
}

function traduire(idSource, idCible, texte) {
    const champCible  = document.getElementById(idCible);
    const spinnerSrc  = document.getElementById('spin_' + idSource);
    const okCible     = document.getElementById('ok_'  + idCible);
    if (!champCible || !texte.trim()) return;
    if (okCible) okCible.style.display = 'none';
    if (spinnerSrc) spinnerSrc.style.display = 'inline-block';
    tradEnCours++;
    majBarreStatut(true, 'Traduction en cours (' + tradEnCours + ')...');
    $.ajax({
        url     : 'translate_proxy.php',
        type    : 'POST',
        contentType : 'application/json',
        data    : JSON.stringify({ text: texte, from: 'fr', to: 'en' }),
        timeout : 10000,
        success : function(reponse) {
            if (reponse.status === 'success' && reponse.translation) {
                champCible.value = reponse.translation;
                champCible.classList.add('trad-actif');
                setTimeout(() => champCible.classList.remove('trad-actif'), 1200);
                if (okCible) {
                    okCible.style.display = 'inline-block';
                    setTimeout(() => { okCible.style.display = 'none'; }, 3000);
                }
            } else {
                console.warn('[Traduction] Erreur API MyMemory :', reponse.message || 'inconnue');
            }
        },
        error    : function(xhr, status) { console.warn('[Traduction] Erreur réseau :', status); },
        complete : function() {
            if (spinnerSrc) spinnerSrc.style.display = 'none';
            tradEnCours = Math.max(0, tradEnCours - 1);
            if (tradEnCours === 0) majBarreStatut(false);
            else majBarreStatut(true, 'Traduction en cours (' + tradEnCours + ')...');
        }
    });
}

function attacherTradAuto(champFr) {
    const idSource = champFr.id;
    const idCible  = champFr.dataset.cible;
    if (!idSource || !idCible) return;
    champFr.addEventListener('input', function() {
        const texte = this.value.trim();
        clearTimeout(tradTimers[idSource]);
        const okCible = document.getElementById('ok_' + idCible);
        if (okCible) okCible.style.display = 'none';
        if (texte.length < 5) return;
        tradTimers[idSource] = setTimeout(function() { traduire(idSource, idCible, texte); }, DELAI_TRAD_MS);
    });
    champFr.addEventListener('blur', function() {
        const texte = this.value.trim();
        if (texte.length < 5) return;
        clearTimeout(tradTimers[idSource]);
        traduire(idSource, idCible, texte);
    });
}

function traduireManuel(idSource, idCible) {
    const champFr = document.getElementById(idSource);
    if (!champFr) return;
    const texte = champFr.value.trim();
    if (!texte) {
        Swal.fire({icon:'warning',title:'⚠️ Champ vide',text:'Saisissez d\'abord le texte en français avant de traduire.',confirmButtonColor:'#03224c',timer:3000,timerProgressBar:true});
        return;
    }
    clearTimeout(tradTimers[idSource]);
    traduire(idSource, idCible, texte);
}

function initTradAuto() {
    document.querySelectorAll('.champ-fr[data-cible]').forEach(function(champFr) {
        attacherTradAuto(champFr);
    });
}
document.addEventListener('DOMContentLoaded', function() { initTradAuto(); });

/* ── Notifications ──────────────────────────────────────── */
<?php if($qmsg==='ok'): ?>Swal.fire({title:'✅ Question ajoutée',icon:'success',timer:2500,timerProgressBar:true,showConfirmButton:false,position:'top-end',toast:true});<?php endif; ?>
<?php if($qerr==='pratique_refused'): ?>Swal.fire({icon:'warning',title:'⚠️ Non autorisé',text:'La pratique est réservée à l\'examen IF.',confirmButtonColor:'#03224c'});<?php endif; ?>
<?php if($qerr && $qerr!=='pratique_refused'): ?>Swal.fire({title:'Erreur SQL',text:<?= json_encode($qerr) ?>,icon:'error',confirmButtonColor:'#dc2626'});<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>