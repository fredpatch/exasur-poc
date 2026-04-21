<?php
/**
 * questions_edit.php — Modifier une question EXASUR ANAC GABON
 * admin/questions_edit.php?id=X
 *
 * CORRECTIONS :
 *  ① Bouton "Marquer pour suppression" corrigé :
 *     utilise data-img="..." + event listener délégué
 *     (évite les problèmes d'apostrophes dans les noms de fichier)
 *  ② Suppression PHYSIQUE des fichiers images dans assets/images/
 *     quand ils sont marqués pour suppression et que le formulaire est soumis
 *  ③ Pratique uniquement pour IF (idtype_examen=2)
 *  ④ images_traitements sauvegardé en JSON
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$id = intval($_GET['id'] ?? 0);
$q  = $conn->query("SELECT q.*, te.code AS tc FROM question q JOIN type_examen te ON q.idtype_examen=te.idtype_examen WHERE q.id=$id")->fetch_assoc();
if (!$q) { header("Location: questions.php"); exit(); }

/* ── Traitements disponibles ───────────────────────────────── */
$traitements = [];
$tr_q = $conn->query("SELECT id, code, libelle FROM traitements_image WHERE actif=1 ORDER BY id");
if ($tr_q) while ($t = $tr_q->fetch_assoc()) $traitements[] = $t;
if (empty($traitements)) {
    $traitements = [
        ['id'=>1,'code'=>'normal',    'libelle'=>'Normal'],
        ['id'=>2,'code'=>'grayscale', 'libelle'=>'Noir et Blanc'],
        ['id'=>3,'code'=>'color',     'libelle'=>'Couleur+'],
        ['id'=>4,'code'=>'hp',        'libelle'=>'Haute Pénétration'],
        ['id'=>5,'code'=>'organic',   'libelle'=>'Mat. Organique'],
        ['id'=>6,'code'=>'inorganic', 'libelle'=>'Mat. Inorganique'],
        ['id'=>7,'code'=>'contour',   'libelle'=>'Renforcement Contours'],
    ];
}

$types_arr = [];
$tr2 = $conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
while ($t = $tr2->fetch_assoc()) $types_arr[] = $t;

$imgs_existing    = $q['images']             ? (json_decode($q['images'], true)            ?? []) : [];
$imgs_traitements = $q['images_traitements'] ? (json_decode($q['images_traitements'], true) ?? []) : [];

/* Chemin physique vers le dossier images */
define('IMG_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR);

$msg = '';

/* ── POST : Enregistrer modifications ──────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ite = intval($_POST['idtype_examen']     ?? 1);
    $tq  = ($_POST['type_question'] ?? 'theorique') === 'pratique' ? 'pratique' : 'theorique';
    if ($tq === 'pratique' && $ite != 2) { $tq = 'theorique'; }

    $qf  = $conn->real_escape_string($_POST['question_text_fr'] ?? '');
    $qe  = $conn->real_escape_string($_POST['question_text_en'] ?? '');
    $bar = floatval($_POST['bareme'] ?? 2);

    /* ── IF Pratique : options fixes Clair/Suspect + catégorie ── */
    if ($tq === 'pratique') {
        $o1f = "'Bagage CLAIR'";   $o1e = "'CLEAR Baggage'";
        $o2f = "'Bagage SUSPECT'"; $o2e = "'SUSPECT Baggage'";
        $co_prat = intval($_POST['correct_option_prat'] ?? 1);
        $cat_idx  = intval($_POST['correct_cat_idx']     ?? 0);
        if ($co_prat === 1) {
            $co  = 1; $o3f = 'NULL'; $o3e = 'NULL';
        } else {
            $co  = 1 + $cat_idx;
            $CATS = ['Armes à feu, fusils et autres armes','Armes tranchantes et objets pointus','Instruments contondants','Matières explosives et substances inflammables','Substances chimiques et toxiques'];
            $cat_lib = $CATS[max(0,$cat_idx-1)] ?? '';
            $o3f = "'".$conn->real_escape_string($cat_lib)."'";
            $o3e = 'NULL';
        }
        $o4f = 'NULL'; $o4e = 'NULL';
        $o = [['f'=>$o1f,'e'=>$o1e],['f'=>$o2f,'e'=>$o2e],['f'=>$o3f,'e'=>$o3e],['f'=>$o4f,'e'=>$o4e]];
    } else {
        /* ── Théorique : options libres ── */
        $co = intval($_POST['correct_option'] ?? 1);
        $o  = [];
        for ($n=1; $n<=4; $n++) {
            $of = !empty($_POST['option'.$n.'_fr']) ? "'".$conn->real_escape_string($_POST['option'.$n.'_fr'])."'" : 'NULL';
            $oe = !empty($_POST['option'.$n.'_en']) ? "'".$conn->real_escape_string($_POST['option'.$n.'_en'])."'" : 'NULL';
            $o[] = ['f'=>$of,'e'=>$oe];
        }
    }

    /* ── Gestion images existantes ── */
    $imgs_final   = $imgs_existing;
    $traits_final = $imgs_traitements;

    /* Suppressions : retirer de la liste + SUPPRIMER le fichier physique */
    $to_delete    = $_POST['delete_imgs'] ?? [];
    $nb_deleted   = 0;
    foreach ($to_delete as $img_del) {
        /* Sécurité : n'accepter que les noms sans slash, sans '..' */
        $img_del = basename($img_del);
        if (!$img_del) continue;

        /* Retirer de la liste BDD */
        $imgs_final   = array_values(array_filter($imgs_final, fn($i) => basename($i) !== $img_del));
        unset($traits_final[$img_del]);
        /* Suppression clé avec nom complet si présent */
        foreach (array_keys($traits_final) as $k) {
            if (basename($k) === $img_del) unset($traits_final[$k]);
        }

        /* ② Supprimer le fichier physiquement dans assets/images/ */
        $file_path = IMG_DIR . $img_del;
        if (is_file($file_path)) {
            unlink($file_path);
            $nb_deleted++;
        }
    }

    /* Mise à jour traitements des images restantes */
    foreach ($_POST['trait_existing'] ?? [] as $img_name => $trait_code) {
        $img_name = basename($img_name);
        if (in_array($img_name, array_map('basename', $imgs_final))) {
            $traits_final[$img_name] = $trait_code;
        }
    }

    /* Nouvelles images uploadées */
    $new_traits = $_POST['trait_new'] ?? [];
    if (!empty($_FILES['images']['name'][0]) && is_uploaded_file($_FILES['images']['tmp_name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (!is_uploaded_file($tmp)) continue;
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
            $fn = 'q_'.time().'_'.$i.'_'.rand(100,999).'.'.$ext;
            $dest = IMG_DIR . $fn;
            if (move_uploaded_file($tmp, $dest)) {
                $imgs_final[]      = $fn;
                $traits_final[$fn] = $new_traits[$i] ?? 'normal';
            }
        }
    }

    $imgs_json   = !empty($imgs_final)   ? "'".$conn->real_escape_string(json_encode(array_values($imgs_final)))."'" : 'NULL';
    $traits_json = !empty($traits_final) ? "'".$conn->real_escape_string(json_encode($traits_final))."'" : 'NULL';

    $conn->query("UPDATE question SET
        idtype_examen=$ite, type_question='$tq',
        question_text_fr='$qf', question_text_en='$qe',
        images=$imgs_json, images_traitements=$traits_json,
        option1_fr={$o[0]['f']}, option1_en={$o[0]['e']},
        option2_fr={$o[1]['f']}, option2_en={$o[1]['e']},
        option3_fr={$o[2]['f']}, option3_en={$o[2]['e']},
        option4_fr={$o[3]['f']}, option4_en={$o[3]['e']},
        correct_option=$co, bareme=$bar
        WHERE id=$id");

    if ($conn->error) {
        $msg = 'err:'.$conn->error;
    } else {
        $msg = 'ok';
        if ($nb_deleted > 0) $msg = 'ok_del:'.$nb_deleted;
    }

    /* Recharger la question */
    $q = $conn->query("SELECT q.*, te.code AS tc FROM question q JOIN type_examen te ON q.idtype_examen=te.idtype_examen WHERE q.id=$id")->fetch_assoc();
    $imgs_existing    = $q['images']             ? (json_decode($q['images'], true)            ?? []) : [];
    $imgs_traitements = $q['images_traitements'] ? (json_decode($q['images_traitements'], true) ?? []) : [];
}

$active_page    = 'questions';
$is_if_pratique = ($q['type_question'] === 'pratique' && $q['idtype_examen'] == 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Modifier question #<?= $id ?> — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* ── Images ─────────────────────────────────────────────────── */
.img-upload-zone{background:#f0f9ff;border:2px dashed #0891b2;border-radius:14px;padding:18px;margin-top:10px;}
.zone-title{color:#0891b2;font-weight:700;font-size:.88rem;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.drop-zone{border:2px dashed #c8d0e0;border-radius:10px;padding:18px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbff;}
.drop-zone:hover,.drop-zone.dragover{border-color:var(--blue);background:#f0f4ff;}
.drop-zone input{display:none;}
.img-preview-grid{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px;}

.img-card{
    background:#fff;border:2px solid #e0e7f0;border-radius:12px;
    overflow:hidden;width:195px;flex-shrink:0;
    box-shadow:0 2px 8px rgba(3,34,76,.07);transition:border-color .2s,opacity .3s;
}
.img-card img{width:100%;height:115px;object-fit:cover;display:block;}
.img-card-body{padding:9px 11px;}
.img-card-name{font-size:.71rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px;}

.treat-select{width:100%;padding:6px 10px;border:1.5px solid #d1d5db;border-radius:7px;font-family:inherit;font-size:.8rem;background:#fff;}
.treat-select:focus{outline:none;border-color:var(--blue);}
.treat-select.ok{border-color:#16a34a;background:#f0fdf4;}

/* Boutons supprimer / restaurer */
.del-img-btn{
    width:100%;padding:7px;background:#fee2e2;color:#dc2626;border:none;
    cursor:pointer;font-size:.78rem;font-weight:700;font-family:inherit;
    transition:background .2s;display:flex;align-items:center;justify-content:center;gap:5px;
}
.del-img-btn:hover{background:#fca5a5;}
.restore-img-btn{
    width:100%;padding:7px;background:#dcfce7;color:#16a34a;border:none;
    cursor:pointer;font-size:.78rem;font-weight:700;font-family:inherit;
    display:none;align-items:center;justify-content:center;gap:5px;
}
/* État "marqué pour suppression" */
.img-card.marked-del{opacity:.4;border-color:#dc2626;}
.img-card.marked-del .del-img-btn{display:none;}
.img-card.marked-del .restore-img-btn{display:flex;}

/* Overlay texte "À supprimer" */
.img-card.marked-del .img-card-body::before{
    content:'🗑 Sera supprimé';display:block;
    background:#dc2626;color:#fff;font-size:.72rem;font-weight:700;
    text-align:center;padding:3px;border-radius:6px;margin-bottom:5px;
}

/* Aperçu images existantes */
.exist-img-card{background:#fff;border:2px solid var(--gold);border-radius:12px;overflow:hidden;width:195px;flex-shrink:0;position:relative;box-shadow:0 2px 8px rgba(3,34,76,.08);}
.exist-img-card img{width:100%;height:115px;object-fit:cover;}
.trait-tag{position:absolute;top:6px;right:6px;background:rgba(3,34,76,.85);color:var(--gold);padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title">
        <i class="fas fa-edit me-1"></i>Modifier question
        <span style="background:var(--gold);color:var(--blue);padding:2px 10px;border-radius:20px;font-size:.78rem;margin-left:8px;">#<?= $id ?></span>
    </div>
    <div class="topbar-breadcrumb">
        <a href="dashboard.php">Accueil</a>
        <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
        <a href="questions.php">Questions</a>
        <i class="fas fa-chevron-right" style="font-size:.65rem"></i>
        <span>Modifier #<?= $id ?></span>
    </div>
    <div class="ms-auto">
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']??'') ?></span>
    </div>
</div>
<div class="admin-content">

<!-- Aperçu actuel -->
<div class="card-admin mb-4">
    <div class="card-admin-header"><i class="fas fa-eye me-2" style="color:var(--gold)"></i><h5>Aperçu actuel</h5></div>
    <div class="card-admin-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div style="background:var(--blue-light);padding:13px;border-radius:var(--radius-sm);border-left:4px solid var(--blue)">
                    <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:5px">🇫🇷 FR</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($q['question_text_fr']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div style="background:#f0fdf4;padding:13px;border-radius:var(--radius-sm);border-left:4px solid #16a34a">
                    <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:5px">🇬🇧 EN</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($q['question_text_en']??'') ?></div>
                </div>
            </div>
        </div>
        <?php if (!empty($imgs_existing)): ?>
        <div style="margin-bottom:12px;">
            <div style="font-size:.78rem;font-weight:700;color:var(--blue);margin-bottom:8px;">
                <i class="fas fa-images me-1" style="color:var(--gold);"></i><?= count($imgs_existing) ?> image(s)
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($imgs_existing as $img):
                    $tc  = $imgs_traitements[$img] ?? ($imgs_traitements[basename($img)] ?? 'normal');
                    $tl  = 'Normal';
                    foreach ($traitements as $t) { if ($t['code']===$tc){$tl=$t['libelle'];break;} }
                ?>
                <div class="exist-img-card">
                    <img src="../assets/images/<?= htmlspecialchars(basename($img)) ?>" alt="" onerror="this.style.opacity='.3'">
                    <div class="trait-tag"><i class="fas fa-palette me-1"></i><?= htmlspecialchars($tl) ?></div>
                    <div style="padding:5px 10px;font-size:.69rem;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(basename($img)) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <?php for ($n=1;$n<=4;$n++): if(!$q['option'.$n.'_fr'])continue; $ok=($n==$q['correct_option']); ?>
            <div class="question-detail-opt <?= $ok?'correct':'' ?>" style="flex:1;min-width:200px;">
                <span style="font-weight:700;"><?= $ok?'✅':'○' ?> <?= $n ?>.</span>
                <?= htmlspecialchars($q['option'.$n.'_fr']) ?>
                <?php if($q['option'.$n.'_en']): ?><div style="font-size:.73rem;color:var(--text-muted);margin-top:2px;">🇬🇧 <?= htmlspecialchars($q['option'.$n.'_en']) ?></div><?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Formulaire modification -->
<div class="card-admin">
    <div class="card-admin-header"><i class="fas fa-edit me-2" style="color:var(--gold)"></i><h5>Modifier</h5></div>
    <div class="card-admin-body">
        <form method="POST" enctype="multipart/form-data" id="editForm">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label-admin">Type d'examen *</label>
                    <select name="idtype_examen" class="form-select-admin" id="selTE" required onchange="onTE(this)">
                        <?php foreach ($types_arr as $t): ?>
                        <option value="<?= $t['idtype_examen'] ?>" data-code="<?= $t['code'] ?>"
                            <?= $t['idtype_examen']==$q['idtype_examen']?'selected':'' ?>>
                            <?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Type de question</label>
                    <select name="type_question" class="form-select-admin" id="selTQ" onchange="onTQ(this.value)">
                        <option value="theorique" <?= $q['type_question']!=='pratique'?'selected':'' ?>>📝 Théorique</option>
                        <option value="pratique"  <?= $q['type_question']==='pratique'?'selected':'' ?>>🖼️ Pratique (IF uniquement)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-admin">Barème</label>
                    <input type="number" name="bareme" class="form-control-admin" value="<?= $q['bareme'] ?>" step="0.5" min="0.5">
                </div>
                <div class="col-md-6">
                    <label class="form-label-admin">🇫🇷 Question (FR) *</label>
                    <textarea name="question_text_fr" class="form-control-admin" rows="3" required id="qTextFr"><?= htmlspecialchars($q['question_text_fr']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label-admin">🇬🇧 Question (EN)</label>
                    <textarea name="question_text_en" class="form-control-admin" rows="3"><?= htmlspecialchars($q['question_text_en']??'') ?></textarea>
                </div>

                <!-- OPTIONS THÉORIQUES (cachées si pratique) -->
                <div id="theoricOpts" class="col-12" <?= $is_if_pratique ? 'style="display:none"' : '' ?>>
                    <div class="row g-3">
                    <?php for ($n=1;$n<=4;$n++): $req=$n<=2?'required':''; ?>
                    <div class="col-md-6">
                        <label class="form-label-admin">🇫🇷 Option <?= $n ?> <?= $req?'*':'' ?></label>
                        <input type="text" name="option<?= $n ?>_fr" class="form-control-admin"
                               <?= ($is_if_pratique ? '' : $req) ?>
                               value="<?= htmlspecialchars($q['option'.$n.'_fr']??'') ?>"
                               id="opt<?= $n ?>fr">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">🇬🇧 Option <?= $n ?></label>
                        <input type="text" name="option<?= $n ?>_en" class="form-control-admin"
                               value="<?= htmlspecialchars($q['option'.$n.'_en']??'') ?>">
                    </div>
                    <?php endfor; ?>
                    </div>
                </div>

                <!-- BONNE RÉPONSE THÉORIQUE -->
                <div class="col-md-2" id="colCorrect" <?= $is_if_pratique ? 'style="display:none"' : '' ?>>
                    <label class="form-label-admin">Bonne réponse *</label>
                    <select name="correct_option" class="form-select-admin" id="selCO"
                            <?= $is_if_pratique ? 'disabled' : 'required' ?>>
                        <?php for($n=1;$n<=4;$n++): ?>
                        <option value="<?= $n ?>" <?= $q['correct_option']==$n?'selected':'' ?>>Option <?= $n ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- RÉPONSE IF PRATIQUE (visible seulement si pratique) -->
                <?php
                /* Déterminer état actuel pour IF pratique */
                $cur_co   = intval($q['correct_option']);
                $cur_clair = ($cur_co === 1);
                $cur_susp  = ($cur_co > 1);
                $cur_cat   = $cur_susp ? ($cur_co - 1) : 0; /* 1-5 */
                $CATS_ED   = ['Armes à feu, fusils et autres armes','Armes tranchantes et objets pointus','Instruments contondants','Matières explosives et substances inflammables','Substances chimiques et toxiques'];
                ?>
                <div class="col-12" id="pratAnswerBlock" <?= !$is_if_pratique ? 'style="display:none"' : '' ?>>
                    <div style="background:#f0f9ff;border:2px solid #0891b2;border-radius:14px;padding:18px;">
                        <div style="font-weight:700;color:#0891b2;margin-bottom:14px;font-size:.9rem;">
                            <i class="fas fa-clipboard-check me-2"></i>Bonne réponse pour cette question IF
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                            <!-- Bagage CLAIR -->
                            <label id="lbl_clair" onclick="onPratAnswer(1)"
                                   style="display:flex;align-items:center;gap:10px;padding:14px 18px;
                                   border:2px solid <?= $cur_clair?'#16a34a':'#e5e7eb' ?>;
                                   border-radius:12px;cursor:pointer;background:<?= $cur_clair?'#f0fdf4':'#fff' ?>;
                                   transition:all .2s;flex:1;min-width:180px;">
                                <input type="radio" name="correct_option_prat" value="1" id="rClair"
                                       <?= $cur_clair?'checked':'' ?> style="width:18px;height:18px;accent-color:#16a34a;">
                                <div>
                                    <div style="font-weight:700;color:#16a34a;font-size:.95rem;">🟢 Bagage CLAIR</div>
                                    <div style="font-size:.78rem;color:#6b7280;">Aucun objet prohibé détecté</div>
                                </div>
                            </label>
                            <!-- Bagage SUSPECT -->
                            <label id="lbl_susp" onclick="onPratAnswer(2)"
                                   style="display:flex;align-items:center;gap:10px;padding:14px 18px;
                                   border:2px solid <?= $cur_susp?'#dc2626':'#e5e7eb' ?>;
                                   border-radius:12px;cursor:pointer;background:<?= $cur_susp?'#fff1f2':'#fff' ?>;
                                   transition:all .2s;flex:1;min-width:180px;">
                                <input type="radio" name="correct_option_prat" value="2" id="rSusp"
                                       <?= $cur_susp?'checked':'' ?> style="width:18px;height:18px;accent-color:#dc2626;">
                                <div>
                                    <div style="font-weight:700;color:#dc2626;font-size:.95rem;">🔴 Bagage SUSPECT</div>
                                    <div style="font-size:.78rem;color:#6b7280;">Objet dangereux identifié</div>
                                </div>
                            </label>
                        </div>
                        <!-- Catégories si SUSPECT -->
                        <div id="catsBlock" <?= $cur_susp?'':'style="display:none"' ?>
                             style="background:#fff1f2;border:1.5px solid #fecaca;border-radius:12px;padding:14px;">
                            <div style="font-size:.82rem;font-weight:800;color:#dc2626;margin-bottom:10px;text-transform:uppercase;">
                                <i class="fas fa-exclamation-triangle me-1"></i>Catégorie de menace (obligatoire)
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                <?php foreach($CATS_ED as $ci=>$cv): $catNum=$ci+1; ?>
                                <label id="catOpt_<?= $catNum ?>"
                                       onclick="selectCat(<?= $catNum ?>)"
                                       style="display:flex;align-items:center;gap:8px;padding:9px 13px;
                                       border:1.5px solid <?= $cur_cat===$catNum?'#dc2626':'#fecaca' ?>;
                                       border-radius:9px;cursor:pointer;background:<?= $cur_cat===$catNum?'#fee2e2':'#fff' ?>;
                                       font-size:.84rem;font-weight:600;color:#7f1d1d;transition:all .2s;flex:1;min-width:200px;">
                                    <input type="radio" name="correct_cat_idx" value="<?= $catNum ?>" id="rCat<?= $catNum ?>"
                                           <?= $cur_cat===$catNum?'checked':'' ?> style="accent-color:#dc2626;">
                                    <?= htmlspecialchars($cv) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BLOC IMAGES IF PRATIQUE -->
            <div id="rowImg" class="<?= $is_if_pratique ? '' : 'd-none' ?>">

                <!-- Images existantes -->
                <?php if (!empty($imgs_existing)): ?>
                <div style="background:#fff8f0;border:1.5px solid var(--gold);border-radius:14px;padding:16px;margin-bottom:14px;">
                    <div style="font-weight:700;color:var(--blue);font-size:.88rem;margin-bottom:4px;">
                        <i class="fas fa-images me-2" style="color:var(--gold);"></i>Images actuelles
                    </div>
                    <div style="font-size:.78rem;color:#dc2626;margin-bottom:12px;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Les images marquées seront <strong>supprimées définitivement</strong> du serveur lors de l'enregistrement.
                    </div>
                    <div class="img-preview-grid" id="existImgGrid">
                        <?php foreach ($imgs_existing as $img):
                            $img_base   = basename($img);
                            $trait_code = $imgs_traitements[$img]      ?? ($imgs_traitements[$img_base] ?? 'normal');
                            $img_esc    = htmlspecialchars($img_base, ENT_QUOTES);
                        ?>
                        <!-- data-img contient le nom de fichier — pas dans onclick pour éviter les bugs -->
                        <div class="img-card" id="ec-<?= md5($img_base) ?>" data-img="<?= $img_esc ?>">
                            <img src="../assets/images/<?= $img_esc ?>" alt="" onerror="this.style.opacity='.3'">
                            <div class="img-card-body">
                                <div class="img-card-name" title="<?= $img_esc ?>"><?= $img_esc ?></div>
                                <select name="trait_existing[<?= $img_esc ?>]" class="treat-select ok">
                                    <?php foreach ($traitements as $t): ?>
                                    <option value="<?= $t['code'] ?>" <?= $trait_code===$t['code']?'selected':'' ?>>
                                        <?= htmlspecialchars($t['libelle']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- CORRECTION BUG : classe CSS, pas onclick avec paramètre -->
                            <button type="button" class="del-img-btn">
                                <i class="fas fa-trash"></i>Marquer pour suppression
                            </button>
                            <button type="button" class="restore-img-btn">
                                <i class="fas fa-undo"></i>Annuler suppression
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Upload nouvelles images -->
                <div class="img-upload-zone">
                    <div class="zone-title">
                        <i class="fas fa-cloud-upload-alt"></i>Ajouter de nouvelles images (JPG, PNG, WEBP)
                    </div>
                    <div class="drop-zone" id="dropZone" onclick="document.getElementById('imgInput').click()">
                        <input type="file" id="imgInput" name="images[]" multiple accept="image/*"
                               onchange="onFilesSelected(this.files)">
                        <span style="font-size:2rem;color:#c8d0e0;display:block;margin-bottom:8px;"><i class="fas fa-cloud-upload-alt"></i></span>
                        <div style="font-size:.84rem;color:#6b7280;">Cliquez ou glissez-déposez</div>
                        <div style="font-size:.73rem;color:#9ca3af;margin-top:4px;">Chaque image reçoit son traitement</div>
                    </div>
                    <div class="img-preview-grid" id="newImgGrid"></div>
                </div>

                <!-- Champs cachés suppressions (gérés par JS) -->
                <div id="deleteImgsContainer"></div>

                <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:9px;padding:10px 14px;margin-top:12px;font-size:.81rem;color:#3730a3;">
                    <i class="fas fa-info-circle me-2" style="color:#6366f1;"></i>
                    <strong>Règle :</strong> chaque image = un seul traitement. Le candidat est notifié s'il en choisit un différent.
                </div>
            </div>

            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Enregistrer les modifications</button>
                <a href="questions.php" class="btn-anac" style="background:white;color:var(--blue);">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
        </form>
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

const TRAITEMENTS = <?= json_encode($traitements) ?>;

function buildTraitSelect(name, def='normal') {
    let h = `<select name="${name}" class="treat-select ok">`;
    TRAITEMENTS.forEach(t => h += `<option value="${t.code}"${t.code===def?' selected':''}>${t.libelle}</option>`);
    return h + '</select>';
}

/* ── Type examen / question ────────────────────────────────── */
function onTE(sel) {
    const isIF = sel.options[sel.selectedIndex]?.dataset.code === 'IF';
    if (!isIF && document.getElementById('selTQ').value === 'pratique') {
        document.getElementById('selTQ').value = 'theorique';
        onTQ('theorique');
    }
}
function onTQ(v) {
    const isIF = document.getElementById('selTE').options[document.getElementById('selTE').selectedIndex]?.dataset.code === 'IF';
    if (v === 'pratique' && !isIF) {
        Swal.fire({icon:'warning',title:'⚠️ Non autorisé',
            text:'La pratique est réservée à l\'examen IF.',confirmButtonColor:'#03224c'});
        document.getElementById('selTQ').value = 'theorique'; return;
    }
    const isPrat = (v === 'pratique');
    /* Masquer/afficher blocs */
    document.getElementById('theoricOpts').style.display    = isPrat ? 'none' : '';
    document.getElementById('colCorrect').style.display     = isPrat ? 'none' : '';
    document.getElementById('pratAnswerBlock').style.display = isPrat ? '' : 'none';
    document.getElementById('rowImg').classList.toggle('d-none', !isPrat);

    /* CORRECTION BUG : désactiver required+disabled sur tous les champs théoriques cachés
       Les IDs sont opt1fr, opt2fr, opt3fr, opt4fr + selCO */
    for (let n=1; n<=4; n++) {
        const el = document.getElementById('opt'+n+'fr');
        if (el) { el.required = (n<=2 && !isPrat); el.disabled = isPrat; }
    }
    const selCO = document.getElementById('selCO');
    if (selCO) { selCO.required = !isPrat; selCO.disabled = isPrat; }
}

/* ── Réponse IF pratique ─────────────────────────────────── */
function onPratAnswer(v) {
    const lC = document.getElementById('lbl_clair');
    const lS = document.getElementById('lbl_susp');
    document.getElementById('rClair').checked = (v===1);
    document.getElementById('rSusp').checked  = (v===2);
    if (v===1) {
        lC.style.borderColor='#16a34a'; lC.style.background='#f0fdf4';
        lS.style.borderColor='#e5e7eb'; lS.style.background='#fff';
        document.getElementById('catsBlock').style.display='none';
        document.querySelectorAll('[name="correct_cat_idx"]').forEach(r=>r.checked=false);
        document.querySelectorAll('[id^="catOpt_"]').forEach(l=>{l.style.borderColor='#fecaca';l.style.background='#fff';});
    } else {
        lS.style.borderColor='#dc2626'; lS.style.background='#fff1f2';
        lC.style.borderColor='#e5e7eb'; lC.style.background='#fff';
        document.getElementById('catsBlock').style.display='';
    }
}
function selectCat(n) {
    document.querySelectorAll('[name="correct_cat_idx"]').forEach(r=>r.checked=false);
    document.querySelectorAll('[id^="catOpt_"]').forEach(l=>{l.style.borderColor='#fecaca';l.style.background='#fff';});
    document.getElementById('rCat'+n).checked=true;
    const lbl=document.getElementById('catOpt_'+n);
    if(lbl){lbl.style.borderColor='#dc2626';lbl.style.background='#fee2e2';}
}

/* ════════════════════════════════════════════════════════════
   CORRECTION BUG "Marquer pour suppression"
   Utilise event listener délégué + data-img
   → zéro problème d'apostrophe/guillemet dans les noms de fichier
════════════════════════════════════════════════════════════ */
const toDelete = new Set();

document.addEventListener('click', function(e) {
    const delBtn     = e.target.closest('.del-img-btn');
    const restoreBtn = e.target.closest('.restore-img-btn');

    if (delBtn) {
        const card    = delBtn.closest('.img-card');
        const imgName = card.dataset.img;      /* Nom lu depuis data-img, pas onclick() */
        toDelete.add(imgName);
        card.classList.add('marked-del');      /* CSS gère l'affichage boutons */
        syncDeleteFields();
        return;
    }
    if (restoreBtn) {
        const card    = restoreBtn.closest('.img-card');
        const imgName = card.dataset.img;
        toDelete.delete(imgName);
        card.classList.remove('marked-del');
        syncDeleteFields();
    }
});

/* Synchroniser les champs cachés avec le Set */
function syncDeleteFields() {
    const cont = document.getElementById('deleteImgsContainer');
    cont.innerHTML = '';
    toDelete.forEach(img => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'delete_imgs[]'; inp.value = img;
        cont.appendChild(inp);
    });
    /* Désactiver le select traitement des images marquées */
    toDelete.forEach(img => {
        const card = document.getElementById('ec-' + simpleHash(img));
        if (card) {
            const sel = card.querySelector('.treat-select');
            if (sel) sel.disabled = true;
        }
    });
}

/* Hash simple pour les IDs de carte (même logique que PHP md5 simplifié) */
function simpleHash(str) {
    let h = 0;
    for (let i=0; i<str.length; i++) { h = ((h<<5)-h)+str.charCodeAt(i); h|=0; }
    return Math.abs(h).toString(16);
}

/* ── Upload nouvelles images ────────────────────────────────── */
function onFilesSelected(files) {
    const grid = document.getElementById('newImgGrid');
    grid.innerHTML = '';
    Array.from(files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const card = document.createElement('div');
            card.className = 'img-card';
            const esc = s => { const d=document.createElement('div');d.textContent=s;return d.innerHTML; };
            card.innerHTML = `
                <img src="${e.target.result}" alt="">
                <div class="img-card-body">
                    <div class="img-card-name" title="${esc(file.name)}">${esc(file.name)}</div>
                    <div style="font-size:.71rem;color:#6b7280;margin-bottom:5px;">
                        <i class="fas fa-palette me-1" style="color:var(--gold);"></i>Traitement :
                    </div>
                    ${buildTraitSelect('trait_new[' + i + ']')}
                </div>`;
            grid.appendChild(card);
        };
        reader.readAsDataURL(file);
    });
}

/* Drag & Drop */
const dz = document.getElementById('dropZone');
if (dz) {
    dz.addEventListener('dragover',  e=>{e.preventDefault();dz.classList.add('dragover');});
    dz.addEventListener('dragleave', ()=>dz.classList.remove('dragover'));
    dz.addEventListener('drop', e=>{
        e.preventDefault();dz.classList.remove('dragover');
        document.getElementById('imgInput').files=e.dataTransfer.files;
        onFilesSelected(e.dataTransfer.files);
    });
}

/* ── Notifications ─────────────────────────────────────────── */
<?php if ($msg==='ok'): ?>
Swal.fire({title:'✅ Question modifiée',icon:'success',timer:2500,timerProgressBar:true,
    showConfirmButton:false,toast:true,position:'top-end'});
<?php elseif (str_starts_with($msg,'ok_del:')): ?>
Swal.fire({
    title:'✅ Question modifiée',
    html:`<p style="font-family:Candara,sans-serif;">
            Modifications enregistrées.<br>
            <strong><?= intval(substr($msg,7)) ?></strong> image(s) supprimée(s) définitivement du serveur.
          </p>`,
    icon:'success',timer:3500,timerProgressBar:true,confirmButtonColor:'#03224c'
});
<?php elseif (str_starts_with($msg,'err:')): ?>
Swal.fire({title:'Erreur',text:<?= json_encode(substr($msg,4)) ?>,icon:'error',confirmButtonColor:'#dc2626'});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>