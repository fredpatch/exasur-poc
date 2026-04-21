<?php
/**
 * examen.php — Examen Pratique IF EXASUR ANAC GABON
 * candidat/examen.php
 *
 * LOGIQUE TRAITEMENTS (NOUVELLE) :
 *   - Chaque image est associée à UN traitement défini par l'admin
 *   - Les boutons de traitement affichés = les traitements des images de la question
 *   - Cliquer sur "Contours" → affiche l'IMAGE associée à "Contours"
 *   - Cliquer sur "HP" → affiche l'IMAGE associée à "Haute Pénétration"
 *   - PAS de notification "traitement incorrect" → c'est la logique de galerie
 *   - Le candidat navigue entre images via leurs traitements
 *
 * STRUCTURE ÉCRAN :
 *   1. Consigne d'analyse (en haut, avant les images)
 *   2. Zone image principale (canvas)
 *   3. Boutons TRAITEMENTS (sélecteur d'image par traitement)
 *   4. Boutons OUTILS : Zoom+, Zoom-, Reset, Rotation, Plein écran (communs)
 *   5. Colonne droite : Réponse (Clair / Suspect + catégorie)
 *
 * RÉPONSE IF :
 *   - Bagage CLAIR (option 1) → correct_option=1
 *   - Bagage SUSPECT + catégorie (option 2-6) → correct_option = numéro catégorie
 *
 * TIMER : 45 secondes par question → popup SweetAlert centré au dépassement
 */
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

if (!isset($_SESSION['idcandidat'], $_SESSION['id_session'], $_SESSION['idtype_examen'])) {
    header("Location: ../../index.php"); exit();
}
$idcandidat    = intval($_SESSION['idcandidat']);
$id_session    = intval($_SESSION['id_session']);
$idtype_examen = intval($_SESSION['idtype_examen']);
$type_session  = $_SESSION['type_session'] ?? 'normal';
$nom_complet   = htmlspecialchars($_SESSION['nom_complet'] ?? '');
$code_acces    = htmlspecialchars($_SESSION['code_acces']  ?? '');
$nom_session   = htmlspecialchars($_SESSION['nom_session'] ?? '');

/* Seul IF pratique utilise ce fichier */
$sess_chk = $conn->prepare("SELECT id_session, duree_minutes FROM session_examen WHERE id_session=? AND statut IN ('planifiee','en_cours')");
$sess_chk->bind_param("i", $id_session);
$sess_chk->execute();
$sess_row = $sess_chk->get_result()->fetch_assoc();
$sess_chk->close();
if (!$sess_row) { header("Location: ../../index.php"); exit(); }

/* Charger les questions pratiques + images_traitements */
$stmt_q = $conn->prepare("
    SELECT q.id, q.question_text_fr, q.question_text_en,
           q.images, q.images_traitements,
           q.option1_fr, q.option2_fr, q.option3_fr, q.option4_fr,
           q.correct_option, q.bareme, q.type_question
    FROM session_questions sq
    JOIN question q ON q.id = sq.question_id
    WHERE sq.session_id = ? AND q.type_question = 'pratique'
    ORDER BY sq.ordre ASC, sq.id ASC
");
$stmt_q->bind_param("i", $id_session);
$stmt_q->execute();
$res_q    = $stmt_q->get_result();
$questions = [];
while ($row = $res_q->fetch_assoc()) $questions[] = $row;
$stmt_q->close();

$total_q = count($questions);
if ($total_q === 0) { header("Location: soumettre_examen.php?empty=1"); exit(); }

/* ════════════════════════════════════════════════════════════
   RANDOMISATION QUESTIONS IF PRATIQUE PAR CANDIDAT
   Même logique que examen_qcm.php (Fisher-Yates)
   Seed = idcandidat × 31337 + id_session × 17
   L'ordre est unique par candidat et stable (mémorisé en session)
════════════════════════════════════════════════════════════ */
$order_key_prat = 'qorder_prat_'.$idcandidat.'_'.$id_session;

if (!isset($_SESSION[$order_key_prat])) {
    $indices = range(0, $total_q - 1);
    mt_srand(intval($idcandidat * 31337 + $id_session * 17 + 1)); /* +1 pour différer du seed QCM */
    for ($i = $total_q - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$indices[$i], $indices[$j]] = [$indices[$j], $indices[$i]];
    }
    $_SESSION[$order_key_prat] = $indices;
    mt_srand(); /* Réinitialiser le générateur */
}

/* Réordonner les questions selon l'ordre mémorisé */
$questions_ordered = [];
foreach ($_SESSION[$order_key_prat] as $idx) {
    if (isset($questions[$idx])) $questions_ordered[] = $questions[$idx];
}
$questions = $questions_ordered;

/* Progression */
$prog = $conn->query("SELECT * FROM progression_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session LIMIT 1")->fetch_assoc();
$idx_start   = $prog ? intval($prog['current_index_pra']) : 0;
$infractions = $prog ? intval($prog['infractions'])       : 0;
$reponses_old = $prog ? json_decode($prog['reponses_json'] ?? '{}', true) : [];
if (!is_array($reponses_old)) $reponses_old = [];

$duree_totale = $total_q * 45;

/* Préparer JSON questions pour JS — inclure images_traitements */
$questions_js = [];
foreach ($questions as $q) {
    $imgs   = !empty($q['images'])            ? (json_decode($q['images'], true) ?? [])            : [];
    $traits = !empty($q['images_traitements']) ? (json_decode($q['images_traitements'], true) ?? []) : [];
    if (!is_array($imgs))   $imgs   = [];
    if (!is_array($traits)) $traits = [];
    /* Map inverse : traitement → image (pour navigation par traitement) */
    $trait_to_img = [];
    foreach ($imgs as $img) {
        $t = $traits[$img] ?? 'normal';
        $trait_to_img[$t] = $img;
    }
    $questions_js[] = [
        'id'          => (int)$q['id'],
        'txt'         => htmlspecialchars($q['question_text_fr'], ENT_QUOTES, 'UTF-8'),
        'images'      => $imgs,
        'traitements' => $traits,
        'traitToImg'  => $trait_to_img,
        'correct'     => (int)$q['correct_option'],
        'bareme'      => (float)$q['bareme'],
    ];
}

/* Libellés traitements */
$trait_labels = [
    'normal'    => 'Normal',
    'grayscale' => 'N/B',
    'color'     => 'Couleur+',
    'hp'        => 'Haute Pénétration',
    'organic'   => 'Mat. Organique',
    'inorganic' => 'Mat. Inorganique',
    'contour'   => 'Renforcement Contours',
    'zoom'      => 'Zoom ×2',
];

/* Catégories menace OACI */
$categories_menace = [
    2 => 'Armes à feu',
    3 => 'Objets pointus tranchants',
    4 => 'Instruments contondants',
    5 => 'Matières explosives et substances inflammables',
    6 => 'Substances chimiques et toxiques',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>EXASUR — Examen Pratique IF</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
:root{
    --blue:#03224c;--blue-mid:#0a3a6b;--gold:#D4AF37;
    --green:#16a34a;--red:#dc2626;--bg:#f0f4fa;
    --radius:14px;--shadow:0 8px 30px rgba(3,34,76,.15);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Candara','Calibri',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}

/* TOPBAR */
.exam-topbar{background:linear-gradient(135deg,var(--blue),var(--blue-mid));border-bottom:3px solid var(--gold);padding:10px 20px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:200;box-shadow:0 4px 20px rgba(3,34,76,.3);}
.top-logo{height:44px;background:white;padding:4px 6px;border-radius:8px;}
.top-title{color:white;font-weight:800;font-size:.95rem;}
.top-sub{color:rgba(255,255,255,.65);font-size:.75rem;}
.top-badge{background:rgba(255,255,255,.12);color:white;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;border:1px solid rgba(212,175,55,.3);}
.top-badge.gold{background:rgba(212,175,55,.2);color:var(--gold);}
.infraction-badge{background:rgba(220,38,38,.3);color:#fca5a5;border-color:#dc2626;}

/* Timer 45s */
.timer-bar-wrap{height:10px;background:rgba(255,255,255,.15);border-radius:5px;overflow:hidden;width:200px;flex-shrink:0;}
.timer-bar-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,var(--gold),#f0c040);transition:width 1s linear;}
.timer-display{color:white;font-weight:800;font-size:1.1rem;min-width:44px;text-align:right;font-variant-numeric:tabular-nums;}
.timer-display.danger{color:#fca5a5;}

/* LAYOUT */
.exam-main{flex:1;display:grid;grid-template-columns:1fr 350px;gap:20px;padding:20px;max-width:1400px;margin:0 auto;width:100%;align-items:start;}
@media(max-width:900px){.exam-main{grid-template-columns:1fr;}}

/* SCANNER PANEL */
.scanner-panel{background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;border-top:4px solid var(--blue);}
.scanner-header{background:linear-gradient(135deg,var(--blue),var(--blue-mid));color:white;padding:14px 18px;border-bottom:2px solid var(--gold);display:flex;align-items:center;gap:10px;}
.scanner-header h3{font-size:.92rem;font-weight:700;margin:0;}
.scanner-badge{background:rgba(212,175,55,.2);color:var(--gold);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;border:1px solid rgba(212,175,55,.4);margin-left:auto;}

/* CONSIGNE (EN HAUT AVANT L'IMAGE) */
.consigne-bar{
    background:#fffbef;border-bottom:2px solid var(--gold);padding:12px 18px;
    display:flex;align-items:flex-start;gap:10px;
}
.consigne-bar .ico{color:var(--gold);font-size:1rem;flex-shrink:0;margin-top:2px;}
.consigne-bar .txt{font-weight:700;font-size:.92rem;color:var(--blue);line-height:1.5;}
.consigne-bar .sub{font-size:.78rem;color:#6b7a99;font-style:italic;margin-top:3px;}

/* Numéro question */
.q-num-bar{padding:10px 18px;border-bottom:1px solid #f3f4f6;font-weight:800;font-size:1rem;color:var(--blue);display:flex;align-items:center;justify-content:space-between;}

/* Zone image */
.main-image-wrap{position:relative;background:#111;min-height:320px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:grab;}
.main-image-wrap:active{cursor:grabbing;}
#mainCanvas{max-width:100%;max-height:400px;display:block;margin:0 auto;transition:transform .2s;}

/* Vignettes */
.thumbnails{display:flex;gap:8px;padding:10px 14px;overflow-x:auto;background:#0d0d0d;scrollbar-width:thin;scrollbar-color:var(--gold) #222;}
.thumb{width:68px;height:52px;object-fit:cover;border-radius:6px;cursor:pointer;flex-shrink:0;border:2px solid transparent;transition:border-color .2s,transform .2s;opacity:.7;}
.thumb:hover{opacity:1;transform:scale(1.07);}
.thumb.active{border-color:var(--gold);opacity:1;}

/* BOUTONS TRAITEMENTS — sélecteur d'image par traitement */
.treatments{
    display:flex;gap:6px;flex-wrap:wrap;padding:10px 14px;
    border-top:1px solid #e5e7eb;background:#f8f9fa;align-items:center;
}
.treat-label{font-size:.72rem;font-weight:700;color:#6b7a99;align-self:center;margin-right:4px;flex-shrink:0;}
.treat-btn{
    background:white;border:1.5px solid #d1d5db;border-radius:8px;padding:5px 11px;
    font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer;transition:all .2s;color:#374151;
    display:flex;align-items:center;gap:4px;
}
.treat-btn:hover{background:var(--blue);color:white;border-color:var(--blue);}
.treat-btn.active{background:var(--gold);color:var(--blue);border-color:var(--gold);font-weight:800;}
.treat-btn .img-dot{width:8px;height:8px;border-radius:50%;background:currentColor;display:inline-block;}

/* BOUTONS OUTILS — Zoom, Rotation, Plein écran (communs à toutes images) */
.img-tools{
    display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;
    border-top:1px solid #e5e7eb;background:#fff;align-items:center;
}
.tool-label{font-size:.72rem;font-weight:700;color:#9ca3af;align-self:center;margin-right:4px;flex-shrink:0;}
.tool-btn{
    background:#f3f4f6;border:1.5px solid #e5e7eb;border-radius:8px;padding:5px 10px;
    font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer;transition:all .2s;color:#374151;
    display:flex;align-items:center;gap:4px;
}
.tool-btn:hover{background:var(--blue);color:white;border-color:var(--blue);}
.zoom-pct{font-size:.72rem;font-weight:700;color:#9ca3af;align-self:center;min-width:40px;text-align:center;}

/* RÉPONSES */
.answer-panel{background:white;border-radius:var(--radius);box-shadow:var(--shadow);border-top:4px solid var(--gold);position:sticky;top:100px;}
.answer-header{background:linear-gradient(135deg,var(--blue),var(--blue-mid));color:white;padding:14px 18px;border-bottom:2px solid var(--gold);display:flex;align-items:center;gap:10px;}
.answer-header h3{font-size:.92rem;font-weight:700;margin:0;}
.answer-body{padding:20px 18px;}

/* Dots progression */
.q-counter{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:16px;}
.q-dot{width:22px;height:22px;border-radius:50%;background:#e5e7eb;border:2px solid #d1d5db;font-size:.66rem;font-weight:700;color:#9ca3af;display:flex;align-items:center;justify-content:center;cursor:default;transition:all .2s;}
.q-dot.answered{background:var(--green);border-color:var(--green);color:white;}
.q-dot.current{background:var(--gold);border-color:var(--gold);color:var(--blue);}
.q-dot.skipped{background:var(--red);border-color:var(--red);color:white;}

/* Radios Clair/Suspect */
.radio-main{display:flex;align-items:center;gap:14px;padding:18px 16px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;margin-bottom:12px;transition:all .2s;user-select:none;}
.radio-main:hover{border-color:var(--blue);background:#f0f3f9;}
.radio-main.checked-clair{border-color:var(--green);background:#f0fdf4;}
.radio-main.checked-suspect{border-color:var(--red);background:#fff1f2;}
.radio-main input[type="radio"]{width:20px;height:20px;accent-color:var(--blue);cursor:pointer;flex-shrink:0;}
.radio-label{font-weight:700;font-size:1rem;color:#1a1f2e;}
.radio-emoji{font-size:1.6rem;}

/* Catégories menace */
.categories-block{background:#fff1f2;border:2px solid #fecaca;border-radius:12px;padding:16px;margin-top:4px;display:none;animation:fadeIn .25s ease;}
.categories-block.show{display:block;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:none;}}
.cat-title{font-size:.82rem;font-weight:800;color:var(--red);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.radio-cat{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #fecaca;border-radius:10px;cursor:pointer;margin-bottom:7px;transition:all .2s;background:white;user-select:none;}
.radio-cat:hover{border-color:var(--red);background:#fff5f5;}
.radio-cat.selected{border-color:var(--red);background:#fee2e2;}
.radio-cat input[type="radio"]{width:17px;height:17px;accent-color:var(--red);flex-shrink:0;}
.radio-cat label{cursor:pointer;font-size:.87rem;font-weight:600;color:#7f1d1d;}

/* Bouton suivant */
.btn-next{width:100%;padding:16px;background:linear-gradient(135deg,var(--blue),var(--blue-mid));border:2px solid var(--gold);border-radius:50px;color:white;font-weight:800;font-size:1rem;cursor:pointer;font-family:inherit;transition:all .3s;margin-top:16px;display:flex;align-items:center;justify-content:center;gap:10px;}
.btn-next:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(212,175,55,.4);}

/* Infraction */
.infraction-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;}
.infraction-overlay.show{display:flex;}
.infraction-box{background:white;border-radius:20px;padding:36px 32px;max-width:420px;width:90%;text-align:center;border-top:6px solid var(--red);}
.infraction-box h3{color:var(--red);font-weight:800;font-size:1.4rem;margin-bottom:8px;}
.infraction-box p{color:#555;font-size:.95rem;margin-bottom:20px;}
.btn-ack{background:var(--red);color:white;border:none;padding:12px 32px;border-radius:50px;font-weight:700;cursor:pointer;font-family:inherit;font-size:.95rem;}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="exam-topbar">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" class="top-logo" onerror="this.style.display='none'">
    <div><div class="top-title">EXASUR — Examen Pratique IF</div><div class="top-sub">Inspection Filtrage — Images radiologiques</div></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-left:auto;">
        <span class="top-badge gold"><i class="fas fa-user me-1"></i><?= $nom_complet ?></span>
        <span class="top-badge"><i class="fas fa-key me-1"></i><?= $code_acces ?></span>
        <span class="top-badge"><i class="fas fa-images me-1"></i><span id="topQ">1</span>/<?= $total_q ?></span>
        <span class="top-badge infraction-badge" id="infrBadge" style="display:none;"><i class="fas fa-exclamation-triangle me-1"></i><span id="infraCnt"><?= $infractions ?></span>/5</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-left:16px;">
        <i class="fas fa-stopwatch" style="color:var(--gold);font-size:1rem;"></i>
        <div class="timer-bar-wrap"><div class="timer-bar-fill" id="timerBar" style="width:100%"></div></div>
        <div class="timer-display" id="timerDisplay">45</div>
    </div>
</div>

<!-- LAYOUT -->
<div class="exam-main">

    <!-- COLONNE GAUCHE : Scanner + Question -->
    <div class="scanner-panel">
        <div class="scanner-header">
            <i class="fas fa-x-ray" style="color:var(--gold);font-size:1rem;"></i>
            <h3>Analyse radiologique — Scanner X</h3>
            <span class="scanner-badge" id="imgCount">1 / 1 image</span>
        </div>

        <!-- ① CONSIGNE EN HAUT (avant les images) -->
        <div class="consigne-bar">
            <i class="fas fa-search ico"></i>
            <div>
                <div style="font-size:.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">
                    <i class="fas fa-circle-dot me-1" style="color:var(--gold);"></i>Consigne d'analyse
                </div>
                <div class="txt" id="qText">Chargement...</div>
            </div>
        </div>

        <!-- Numéro question -->
        <div class="q-num-bar">
            <span>Question <strong id="qNumDisplay">1</strong> sur <?= $total_q ?></span>
            <span style="font-size:.78rem;color:#6b7a99;font-weight:600;">⏱ 45 secondes par question</span>
        </div>

        <!-- Zone image principale -->
        <div class="main-image-wrap" id="imgWrap">
            <canvas id="mainCanvas"></canvas>
            <div id="noImgMsg" style="display:none;color:#9ca3af;text-align:center;padding:40px;">
                <i class="fas fa-image fa-3x" style="margin-bottom:12px;display:block;"></i>
                <p>Aucune image scanner disponible.</p>
            </div>
        </div>

        <!-- Vignettes (optionnelles, pour navigation directe) -->
        <div class="thumbnails" id="thumbsRow"></div>

        <!-- ② BOUTONS TRAITEMENTS = sélecteur d'image par traitement associé -->
        <div class="treatments" id="treatBar">
            <span class="treat-label"><i class="fas fa-sliders-h me-1"></i>Vue :</span>
            <!-- Générés dynamiquement par JS selon les traitements des images de la question -->
        </div>

        <!-- ③ BOUTONS OUTILS (communs à toutes les images) -->
        <div class="img-tools">
            <span class="tool-label"><i class="fas fa-toolbox me-1"></i>Outils :</span>
            <button class="tool-btn" onclick="toolZoomIn()" title="Zoom +"><i class="fas fa-search-plus"></i> Zoom +</button>
            <span class="zoom-pct" id="zoomPct">100%</span>
            <button class="tool-btn" onclick="toolZoomOut()" title="Zoom -"><i class="fas fa-search-minus"></i> Zoom -</button>
            <button class="tool-btn" onclick="toolReset()" title="Réinitialiser vue"><i class="fas fa-compress-arrows-alt"></i> Reset</button>
            <button class="tool-btn" onclick="toolRotLeft()" title="Rotation gauche"><i class="fas fa-undo"></i></button>
            <button class="tool-btn" onclick="toolRotRight()" title="Rotation droite"><i class="fas fa-redo"></i></button>
            <button class="tool-btn" onclick="toolFullscreen()" title="Plein écran"><i class="fas fa-expand"></i> Plein écran</button>
        </div>

    </div><!-- /scanner-panel -->

    <!-- COLONNE DROITE : Réponses -->
    <div class="answer-panel">
        <div class="answer-header">
            <i class="fas fa-clipboard-check" style="color:var(--gold);"></i>
            <h3>Votre réponse</h3>
        </div>
        <div class="answer-body">
            <!-- Dots progression -->
            <div class="q-counter" id="qCounter"></div>

            <div style="font-size:.8rem;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
                <i class="fas fa-suitcase me-1" style="color:var(--gold);"></i>Classification du bagage
            </div>

            <!-- Bagage CLAIR -->
            <label class="radio-main" id="lblClair" for="rClair" onclick="selectMain('clair')">
                <span class="radio-emoji">🧳</span>
                <input type="radio" name="main_choice" id="rClair" value="clair">
                <div><div class="radio-label" style="color:var(--green);">Bagage CLAIR</div><div style="font-size:.78rem;color:#6b7a99;">Aucun objet prohibé détecté</div></div>
            </label>

            <!-- Bagage SUSPECT -->
            <label class="radio-main" id="lblSuspect" for="rSuspect" onclick="selectMain('suspect')">
                <span class="radio-emoji">⚠️</span>
                <input type="radio" name="main_choice" id="rSuspect" value="suspect">
                <div><div class="radio-label" style="color:var(--red);">Bagage SUSPECT</div><div style="font-size:.78rem;color:#6b7a99;">Objet potentiellement dangereux</div></div>
            </label>

            <!-- Catégories si SUSPECT -->
            <div class="categories-block" id="catsBlock">
                <div class="cat-title"><i class="fas fa-exclamation-triangle"></i>Catégorie de menace</div>
                <?php foreach ($categories_menace as $k => $v): ?>
                <label class="radio-cat" id="catLabel_<?= $k ?>" for="rCat<?= $k ?>">
                    <input type="radio" name="cat_choice" id="rCat<?= $k ?>" value="<?= $k ?>">
                    <label for="rCat<?= $k ?>"><?= htmlspecialchars($v) ?></label>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="font-size:.76rem;color:#9ca3af;padding:8px;background:#f9fafb;border-radius:8px;margin-top:12px;line-height:1.6;">
                <i class="fas fa-info-circle" style="color:var(--gold);margin-right:4px;"></i>
                Si le temps expire, la question est passée automatiquement.
            </div>

            <button class="btn-next" id="btnNext" onclick="goNext()">
                <i class="fas fa-arrow-right me-1"></i>
                <span id="btnNextLabel">Question suivante</span>
            </button>
        </div>
    </div>

</div><!-- /exam-main -->

<!-- Infraction overlay -->
<div class="infraction-overlay" id="infraOverlay">
    <div class="infraction-box">
        <h3><i class="fas fa-exclamation-triangle me-2"></i>Infraction détectée</h3>
        <p id="infraMsg">Vous avez quitté la fenêtre de l'examen.</p>
        <div style="font-size:.82rem;color:#dc2626;font-weight:700;margin-bottom:16px;">
            Tentative <span id="infraN">1</span> sur 5
        </div>
        <button class="btn-ack" onclick="closeInfraction()"><i class="fas fa-check me-1"></i>Je reprends</button>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════
   DONNÉES GLOBALES
══════════════════════════════════════════════════════════ */
const QUESTIONS  = <?= json_encode($questions_js, JSON_UNESCAPED_UNICODE) ?>;
const TOTAL_Q    = <?= $total_q ?>;
const ID_SESSION = <?= $id_session ?>;
const IDCANDIDAT = <?= $idcandidat ?>;
const IMG_BASE   = '../assets/images/';
const TIMER_MAX  = 45;

/* Labels traitements */
const TRAIT_LABELS = <?= json_encode($trait_labels, JSON_UNESCAPED_UNICODE) ?>;

let currentIdx   = <?= $idx_start ?>;
let infractions  = <?= $infractions ?>;
let timerSec     = TIMER_MAX;
let timerInterval= null;
let currentImgEl = null;
let zoomLevel    = 1;
let rotation     = 0;
let reponses     = {};

/* Restaurer réponses existantes */
(function() {
    const old = <?= json_encode($reponses_old, JSON_UNESCAPED_UNICODE) ?>;
    for (const k in old) reponses[parseInt(k)] = old[k];
})();

document.addEventListener('DOMContentLoaded', () => {
    buildDots();
    loadQuestion(currentIdx);
    setupAntiCheat();
    /* Zoom molette */
    document.getElementById('imgWrap').addEventListener('wheel', e => {
        e.preventDefault();
        if (e.deltaY < 0) toolZoomIn(); else toolZoomOut();
    }, { passive:false });
});

/* ──────────────────────────────────────────────────────────
   DOTS
────────────────────────────────────────────────────────── */
function buildDots() {
    const cnt = document.getElementById('qCounter');
    cnt.innerHTML = '';
    for (let i = 0; i < TOTAL_Q; i++) {
        const d = document.createElement('div');
        d.id = 'dot' + i;
        d.className = 'q-dot' + (i === currentIdx ? ' current' : '');
        d.textContent = i + 1;
        cnt.appendChild(d);
    }
}
function updateDots() {
    for (let i = 0; i < TOTAL_Q; i++) {
        const d = document.getElementById('dot' + i);
        if (!d) continue;
        d.className = 'q-dot';
        if (i === currentIdx)           d.classList.add('current');
        else if (reponses[QUESTIONS[i].id]) d.classList.add('answered');
    }
}

/* ──────────────────────────────────────────────────────────
   CHARGER QUESTION
────────────────────────────────────────────────────────── */
function loadQuestion(idx) {
    if (idx >= TOTAL_Q) { submitExam(); return; }
    currentIdx = idx;
    const q    = QUESTIONS[idx];

    /* Reset UI */
    document.getElementById('rClair').checked   = false;
    document.getElementById('rSuspect').checked = false;
    document.querySelectorAll('[name="cat_choice"]').forEach(r => r.checked = false);
    document.getElementById('catsBlock').classList.remove('show');
    document.getElementById('lblClair').classList.remove('checked-clair');
    document.getElementById('lblSuspect').classList.remove('checked-suspect');
    document.querySelectorAll('.radio-cat').forEach(l => l.classList.remove('selected'));

    /* Restaurer réponse */
    const prev = reponses[q.id];
    if (prev) {
        if (prev.main === 'clair')   selectMain('clair',   true);
        if (prev.main === 'suspect') selectMain('suspect', true, prev.cat);
    }

    /* Texte (consigne) */
    document.getElementById('qText').textContent       = q.txt;
    document.getElementById('qNumDisplay').textContent  = idx + 1;
    document.getElementById('topQ').textContent         = idx + 1;
    document.getElementById('btnNextLabel').textContent = (idx === TOTAL_Q - 1) ? 'Terminer l\'examen' : 'Question suivante';

    /* Réinitialiser zoom/rotation */
    zoomLevel = 1; rotation = 0; applyTransform();
    document.getElementById('zoomPct').textContent = '100%';

    /* Masquer l'image précédente pour éviter un flash à la transition */
    const imgEl = document.getElementById('mainImgDisplay');
    if (imgEl) imgEl.style.display = 'none';
    document.getElementById('mainCanvas').style.display = 'none';
    document.getElementById('noImgMsg').style.display   = 'none';

    /* ① Construire les boutons de traitement selon les images de la question */
    buildTreatButtons(q);

    /* Charger la première image */
    if (q.images && q.images.length > 0) {
        loadImageGallery(q);
    } else {
        showNoImage();
    }

    updateDots();
    startTimer();
}

/* ──────────────────────────────────────────────────────────
   ① BOUTONS TRAITEMENT = SÉLECTEUR D'IMAGE
   Chaque bouton correspond à UNE image via son traitement
   Cliquer sur "Contours" → affiche l'image dont le traitement est "contour"
────────────────────────────────────────────────────────── */
function buildTreatButtons(q) {
    const bar = document.getElementById('treatBar');
    /* Garder seulement le label, supprimer les boutons */
    const label = bar.querySelector('.treat-label');
    bar.innerHTML = '';
    if (label) bar.appendChild(label);

    if (!q.images || q.images.length === 0) {
        const span = document.createElement('span');
        span.style.cssText = 'font-size:.72rem;color:#9ca3af;font-style:italic;';
        span.textContent = 'Aucune image';
        bar.appendChild(span);
        return;
    }

    /* Un bouton par IMAGE, libellé = traitement associé */
    q.images.forEach((imgName, i) => {
        const traitCode  = q.traitements[imgName] || 'normal';
        const traitLabel = TRAIT_LABELS[traitCode] || traitCode;

        const btn = document.createElement('button');
        btn.className  = 'treat-btn' + (i === 0 ? ' active' : '');
        btn.dataset.imgIndex = i;
        btn.dataset.trait    = traitCode;
        btn.innerHTML  = `<span class="img-dot"></span>${traitLabel}`;
        btn.title      = `Afficher l'image avec traitement : ${traitLabel}`;

        btn.addEventListener('click', () => {
            /* Activer ce bouton */
            bar.querySelectorAll('.treat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            /* Afficher simplement l'image associée à ce traitement, sans filtre */
            showImageByIndex(i, q.images);
        });
        bar.appendChild(btn);
    });
}

/* ──────────────────────────────────────────────────────────
   GALERIE IMAGES (chargement initial)
────────────────────────────────────────────────────────── */
function loadImageGallery(q) {
    const thumbsRow = document.getElementById('thumbsRow');
    const badge     = document.getElementById('imgCount');

    thumbsRow.innerHTML = '';
    badge.textContent = '1 / ' + q.images.length + ' image' + (q.images.length > 1 ? 's' : '');

    document.getElementById('mainCanvas').style.display = 'block';
    document.getElementById('noImgMsg').style.display   = 'none';

    q.images.forEach((src, i) => {
        const th = document.createElement('img');
        th.src = IMG_BASE + src;
        th.alt = 'Scan ' + (i + 1);
        th.className = 'thumb' + (i === 0 ? ' active' : '');
        th.onerror = () => { th.style.opacity = '.3'; };
        th.addEventListener('click', () => {
            /* Clic vignette : sélectionner le bouton de traitement correspondant */
            document.querySelectorAll('.treat-btn').forEach((b, j) => {
                b.classList.toggle('active', j === i);
            });
            showImageByIndex(i, q.images);
        });
        thumbsRow.appendChild(th);
    });

    /* Afficher la première image avec son traitement (canvas filtre) */
    showImageByIndex(0, q.images);
}

function showNoImage() {
    document.getElementById('mainCanvas').style.display = 'none';
    /* Masquer aussi l'img principale si elle existe */
    const imgEl = document.getElementById('mainImgDisplay');
    if (imgEl) imgEl.style.display = 'none';
    document.getElementById('noImgMsg').style.display   = 'block';
    document.getElementById('imgCount').textContent     = '0 image';
    document.getElementById('thumbsRow').innerHTML      = '';
    document.getElementById('treatBar').querySelectorAll('.treat-btn').forEach(b => b.remove());
}

/* ──────────────────────────────────────────────────────────
   AFFICHER UNE IMAGE PAR INDEX
   Affiche simplement l'image originale uploadée par l'admin,
   SANS aucune transformation ni filtre canvas.
   Le bouton de traitement sert uniquement à naviguer vers
   l'image qui lui est associée.
────────────────────────────────────────────────────────── */
function showImageByIndex(i, imgs) {
    const src   = IMG_BASE + imgs[i];
    const badge = document.getElementById('imgCount');
    badge.textContent = (i + 1) + ' / ' + imgs.length + ' image' + (imgs.length > 1 ? 's' : '');

    /* Activer la vignette correspondante */
    document.querySelectorAll('.thumb').forEach((th, j) => th.classList.toggle('active', j === i));

    /* Afficher l'image originale sans aucun filtre */
    afficherImageOriginale(src);
}

/* ──────────────────────────────────────────────────────────
   AFFICHER L'IMAGE ORIGINALE — pas de filtre, pas de canvas
   On utilise une balise <img> classique pour préserver
   fidèlement l'image telle qu'elle a été uploadée.
────────────────────────────────────────────────────────── */
function afficherImageOriginale(src) {
    const wrap   = document.getElementById('imgWrap');
    const canvas = document.getElementById('mainCanvas');

    /* Masquer le canvas, on utilise une balise img à la place */
    canvas.style.display = 'none';

    /* Récupérer ou créer la balise img d'affichage principal */
    let imgEl = document.getElementById('mainImgDisplay');
    if (!imgEl) {
        imgEl = document.createElement('img');
        imgEl.id = 'mainImgDisplay';
        imgEl.style.cssText = 'max-width:100%;max-height:400px;display:block;margin:0 auto;object-fit:contain;';
        wrap.appendChild(imgEl);
    }
    imgEl.style.display = 'block';

    /* Gestion erreur de chargement */
    imgEl.onerror = () => {
        imgEl.style.display = 'none';
        document.getElementById('noImgMsg').style.display = 'block';
    };

    /* Charger l'image originale — aucun filtre appliqué */
    imgEl.src = src;

    /* Réappliquer zoom/rotation via CSS transform */
    applyTransform();
}

/* ──────────────────────────────────────────────────────────
   ③ OUTILS IMAGE (zoom, rotation, plein écran)
────────────────────────────────────────────────────────── */
/* Applique le zoom et la rotation à l'élément d'affichage actif
   (balise <img> principale ou canvas en fallback) */
function applyTransform() {
    const transform = `scale(${zoomLevel}) rotate(${rotation}deg)`;
    const imgEl  = document.getElementById('mainImgDisplay');
    const canvas = document.getElementById('mainCanvas');
    if (imgEl)  imgEl.style.transform  = transform;
    if (canvas) canvas.style.transform = transform;
}
function toolZoomIn()  { zoomLevel = Math.min(zoomLevel+0.25, 4);  applyTransform(); document.getElementById('zoomPct').textContent = Math.round(zoomLevel*100)+'%'; }
function toolZoomOut() { zoomLevel = Math.max(zoomLevel-0.25, 0.5); applyTransform(); document.getElementById('zoomPct').textContent = Math.round(zoomLevel*100)+'%'; }
function toolReset()   { zoomLevel=1; rotation=0; applyTransform(); document.getElementById('zoomPct').textContent='100%'; }
function toolRotLeft() { rotation = (rotation-90+360)%360; applyTransform(); }
function toolRotRight(){ rotation = (rotation+90)%360;     applyTransform(); }
function toolFullscreen() {
    const wrap = document.getElementById('imgWrap');
    if (!document.fullscreenElement) wrap.requestFullscreen().catch(()=>{});
    else document.exitFullscreen();
}

/* ──────────────────────────────────────────────────────────
   RÉPONSES
────────────────────────────────────────────────────────── */
function selectMain(choice, silent=false, catVal=null) {
    const rC=document.getElementById('rClair'),rS=document.getElementById('rSuspect');
    const lC=document.getElementById('lblClair'),lS=document.getElementById('lblSuspect');
    const cb=document.getElementById('catsBlock');
    lC.classList.remove('checked-clair'); lS.classList.remove('checked-suspect');
    if (choice==='clair') {
        rC.checked=true; rS.checked=false; lC.classList.add('checked-clair');
        cb.classList.remove('show');
        document.querySelectorAll('[name="cat_choice"]').forEach(r=>r.checked=false);
        document.querySelectorAll('.radio-cat').forEach(l=>l.classList.remove('selected'));
    } else {
        rS.checked=true; rC.checked=false; lS.classList.add('checked-suspect');
        cb.classList.add('show');
        if (catVal) {
            const rCat=document.getElementById('rCat'+catVal);
            if (rCat) { rCat.checked=true; document.getElementById('catLabel_'+catVal).classList.add('selected'); }
        }
    }
}

/* Clic catégorie */
document.querySelectorAll('.radio-cat').forEach(lbl => {
    lbl.addEventListener('click', () => {
        document.querySelectorAll('.radio-cat').forEach(l=>l.classList.remove('selected'));
        lbl.classList.add('selected');
    });
});

/* ──────────────────────────────────────────────────────────
   NAVIGATION
────────────────────────────────────────────────────────── */
function goNext(forced=false) {
    const q    = QUESTIONS[currentIdx];
    const main = document.querySelector('[name="main_choice"]:checked');

    if (!forced && main && main.value==='suspect') {
        const cat = document.querySelector('[name="cat_choice"]:checked');
        if (!cat) {
            Swal.fire({icon:'warning',title:'Catégorie requise',text:'Sélectionnez la catégorie de menace.',confirmButtonColor:'#03224c'});
            return;
        }
    }

    if (main) {
        const cat = document.querySelector('[name="cat_choice"]:checked');
        reponses[q.id] = { main:main.value, cat:(main.value==='suspect'&&cat)?parseInt(cat.value):null };
        saveReponse(q.id, main.value, reponses[q.id].cat);
    }

    stopTimer();
    if (currentIdx >= TOTAL_Q-1) { if (!forced) confirmSubmit(); else submitExam(); return; }
    loadQuestion(currentIdx+1);
}

function saveReponse(qId, mainChoice, catChoice) {
    const selectedOption = mainChoice==='clair' ? 1 : (catChoice||2);
    fetch('save_reponse.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({idcandidat:IDCANDIDAT,id_session:ID_SESSION,question_id:qId,selected_option:selectedOption,main_choice:mainChoice,cat_menace:catChoice})
    }).catch(()=>{});
}

/* ──────────────────────────────────────────────────────────
   TIMER 45s — popup centré SweetAlert au dépassement
────────────────────────────────────────────────────────── */
function startTimer() {
    stopTimer();
    timerSec = TIMER_MAX;
    updateTimerUI();
    timerInterval = setInterval(() => {
        timerSec--;
        updateTimerUI();
        if (timerSec <= 0) { stopTimer(); autoPassQuestion(); }
    }, 1000);
}
function stopTimer() { if (timerInterval){clearInterval(timerInterval);timerInterval=null;} }
function updateTimerUI() {
    const display=document.getElementById('timerDisplay');
    const bar=document.getElementById('timerBar');
    display.textContent = timerSec;
    bar.style.width = ((timerSec/TIMER_MAX)*100)+'%';
    if (timerSec<=10){ display.classList.add('danger'); bar.style.background='linear-gradient(90deg,#dc2626,#f87171)'; }
    else if(timerSec<=20){ bar.style.background='linear-gradient(90deg,#f59e0b,#fcd34d)'; display.classList.remove('danger'); }
    else { bar.style.background='linear-gradient(90deg,var(--gold),#f0c040)'; display.classList.remove('danger'); }
}
function autoPassQuestion() {
    const qId = QUESTIONS[currentIdx].id;
    const dot  = document.getElementById('dot'+currentIdx);
    if (!reponses[qId] && dot) dot.classList.replace('current','skipped');

    /* Popup SweetAlert CENTRÉ avec compte à rebours 3s */
    Swal.fire({
        icon:'warning', title:'⏱ Temps écoulé !',
        html:`<p style="font-family:Candara,sans-serif;font-size:.95rem;">
                Le temps pour la <strong>question ${currentIdx+1}</strong> est écoulé.<br>
                <span style="color:#9ca3af;font-size:.85rem;">Comptée comme non répondue.</span>
              </p>
              <div style="margin-top:12px;font-size:.88rem;color:#03224c;font-weight:700;">
                Passage automatique dans <span id="cntDwn">3</span>s…
              </div>`,
        confirmButtonText:'<i class="fas fa-arrow-right me-1"></i>Suivante',
        confirmButtonColor:'#03224c',
        allowOutsideClick:false,
        timer:3000, timerProgressBar:true,
        didOpen:() => {
            let n=3;
            const iv=setInterval(()=>{n--;const el=document.getElementById('cntDwn');if(el)el.textContent=Math.max(0,n);if(n<=0)clearInterval(iv);},1000);
        }
    }).then(()=>goNext(true));
}

/* ──────────────────────────────────────────────────────────
   SOUMISSION
────────────────────────────────────────────────────────── */
function confirmSubmit() {
    const answered=Object.keys(reponses).length, missing=TOTAL_Q-answered;
    Swal.fire({
        title:'🏁 Terminer l\'examen pratique ?',
        html:`<div style="text-align:left;font-family:'Candara',sans-serif;">
                <div style="background:#f4f7fc;border-radius:12px;padding:14px;margin-bottom:12px;">
                    <p>✅ Répondues : <strong>${answered}</strong></p>
                    <p>⏭ Passées : <strong>${missing}</strong></p>
                    <p>📊 Total : <strong>${TOTAL_Q}</strong></p>
                </div>
                ${missing>0?`<p style="color:#dc2626;font-size:.88rem;">⚠️ ${missing} question(s) comptées comme incorrectes.</p>`:''}
              </div>`,
        icon:'question', showCancelButton:true,
        confirmButtonText:'<i class="fas fa-flag-checkered me-1"></i>Oui, terminer',
        cancelButtonText:'<i class="fas fa-arrow-left me-1"></i>Revenir',
        confirmButtonColor:'#03224c', cancelButtonColor:'#6c757d'
    }).then(r=>{ if(r.isConfirmed) submitExam(); });
}
function submitExam() {
    stopTimer();
    Swal.fire({title:'Calcul du score…',html:'<i class="fas fa-spinner fa-spin fa-2x" style="color:#03224c;"></i>',allowOutsideClick:false,showConfirmButton:false});
    setTimeout(()=>{ window.location.href='soumettre_examen.php?session='+ID_SESSION+'&type=2'; },1000);
}

/* ──────────────────────────────────────────────────────────
   ANTI-TRICHE
────────────────────────────────────────────────────────── */


function setupAntiCheat() {
    document.addEventListener('visibilitychange',()=>{ if(document.hidden) registerInfraction('Changement d\'onglet détecté.'); });
    window.addEventListener('blur',()=>{ setTimeout(()=>{ if(document.hidden) registerInfraction('Perte de focus.'); },500); });
    document.addEventListener('contextmenu',e=>{ e.preventDefault(); registerInfraction('Clic droit interdit.'); });
    document.addEventListener('keydown',e=>{
        if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&'iIjJ'.includes(e.key))){e.preventDefault();registerInfraction('Outils développeur.');}
    });
}
function registerInfraction(msg) {
    infractions++;
    const badge = document.getElementById('infrBadge');
    document.getElementById('infraCnt').textContent = infractions;
    document.getElementById('infraMsg').textContent  = msg;
    document.getElementById('infraN').textContent    = infractions;
    document.getElementById('infraOverlay').classList.add('show');
    if (badge) badge.style.display = 'flex';
    stopTimer();
    fetch('register_infraction.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({idcandidat:IDCANDIDAT,id_session:ID_SESSION,infractions})}).catch(()=>{});
    if (infractions>=5) { document.getElementById('infraOverlay').classList.remove('show'); lockExam(); }
}
function closeInfraction() { document.getElementById('infraOverlay').classList.remove('show'); startTimer(); }
function lockExam() {
    stopTimer();
    Swal.fire({icon:'error',title:'🔒 Examen verrouillé',html:'<strong>5 infractions détectées.</strong><br>Votre examen est annulé.',confirmButtonColor:'#dc2626',confirmButtonText:'Retour',allowOutsideClick:false})
        .then(()=>{window.location.href='../../index.php';});
}
</script>
</body>
</html>
<?php $conn->close(); ?>