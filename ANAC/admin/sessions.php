<?php
/**
 * sessions.php — Gestion sessions d'examen EXASUR ANAC
 * admin/sessions.php
 *
 * ⚠️  LE FORMULAIRE "CRÉER UNE SESSION D'ÉVALUATION" EST RÉSERVÉ AU TYPE FORM.
 *     Pour AS, IF, INST, SENS : les sessions sont gérées directement dans le tableau
 *     via sessions_edit.php (modifier) ou en ajoutant manuellement une ligne.
 *
 * LOGIQUE FORM :
 *  Une formation (ex: 6 stagiaires) peut avoir PLUSIEURS évaluations (Module 2, 3, 6...).
 *  Chaque module évalué = une session distincte dans session_examen.
 *  On repart toujours de la même session de formation source pour voir TOUS les stagiaires
 *  et choisir ceux qui passent le module.
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$conn->query("UPDATE session_examen SET statut='terminee' WHERE date_fin<CURDATE() AND statut NOT IN('terminee','annulee')");

/* ── Helpers ─────────────────────────────────────────────── */
function genMdp($n=9){$a='abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';return substr(str_shuffle($a),0,$n);}

/* ── AJAX : sessions FORM planifiées (source = conteneurs import AGFAC-DU) ── */
if (isset($_GET['ajax']) && $_GET['ajax']==='sessions_form') {
    header('Content-Type: application/json');
    $r = $conn->query("
        SELECT se.id_session, se.nom_session, se.date_debut, se.date_fin,
               se.duree_minutes, se.idtypeformation, tf.nomforma,
               COUNT(DISTINCT cs.idcandidat) AS nb_c
        FROM session_examen se
        LEFT JOIN si_anac.typeformation tf ON se.idtypeformation=tf.idtypeforma
        LEFT JOIN candidat_session cs ON cs.id_session=se.id_session AND cs.habilite=1
        WHERE se.idtype_examen=5
          AND se.idmodule IS NULL
          AND se.statut='planifiee'
          AND se.date_fin >= CURDATE()
        GROUP BY se.id_session
        ORDER BY se.date_debut DESC
    ");
    $rows=[];
    if($r) while($row=$r->fetch_assoc()) $rows[]=$row;
    echo json_encode($rows); exit();
}

/* ── AJAX : stagiaires d'une session de formation ────────── */
if (isset($_GET['ajax']) && $_GET['ajax']==='cands_form') {
    $sid = intval($_GET['sid']??0);
    header('Content-Type: application/json');
    if(!$sid){echo json_encode([]);exit();}
    $r = $conn->query("
        SELECT c.idcandidat, c.code_acces,
               s.nomstagiaire, s.prenomstagiaire
        FROM candidat_session cs
        JOIN candidat c ON cs.idcandidat=c.idcandidat
        JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
        WHERE cs.id_session=$sid AND cs.habilite=1
        ORDER BY s.nomstagiaire, s.prenomstagiaire
    ");
    $rows=[];
    if($r) while($row=$r->fetch_assoc()) $rows[]=$row;
    echo json_encode($rows); exit();
}

/* ── AJAX : ajouter module sans rechargement ─────────────── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_module_ajax') {
    header('Content-Type: application/json');
    $itf  = intval($_POST['idtypeformation']??0);
    $num  = intval($_POST['numero_module']??0);
    $nomf = trim($_POST['nom_module_fr']??'');
    $nome = trim($_POST['nom_module_en']??'');
    $EVAL = [2,3,4,6,8,9,11];
    if(!$itf||!$num||!$nomf){echo json_encode(['status'=>'error','message'=>'Champs obligatoires manquants']);exit();}
    if(!in_array($num,$EVAL)){echo json_encode(['status'=>'error','message'=>"Module $num non évaluable (2,3,4,6,8,9,11)"]);exit();}
    $chk=$conn->prepare("SELECT idmodule FROM module_formation WHERE idtypeformation=? AND numero_module=?");
    $chk->bind_param("ii",$itf,$num);$chk->execute();
    if($chk->get_result()->num_rows>0){echo json_encode(['status'=>'error','message'=>"Module $num existe déjà pour cette formation"]);exit();}
    $chk->close();
    $ins=$conn->prepare("INSERT INTO module_formation (idtypeformation,numero_module,nom_module_fr,nom_module_en,actif) VALUES (?,?,?,?,1)");
    $ins->bind_param("iiss",$itf,$num,$nomf,$nome);$ins->execute();
    $nid=$conn->insert_id;
    echo json_encode(['status'=>'success','message'=>"Module $num ajouté",'idmodule'=>$nid,'numero_module'=>$num,'nom_module_fr'=>$nomf]);exit();
}

/* ── POST : CRÉER une nouvelle session d'évaluation FORM ─── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create_form_session') {
    $sid_src  = intval($_POST['sid_source']??0);
    $imod     = intval($_POST['idmodule']??0);
    $itf      = intval($_POST['idtypeformation']??0);
    $nom      = trim($_POST['nom_session']??'');
    $deb      = $_POST['date_debut']??'';
    $fin      = $_POST['date_fin']??'';
    $dur      = intval($_POST['duree_minutes']??90);
    $cands    = array_map('intval', $_POST['cands']??[]);
    $ite      = 5;       /* FORM */
    $ts       = 'normal';

    $export_rows = [];

    if ($sid_src && $imod && $nom && $deb && $fin && !empty($cands)) {
        $ins = $conn->prepare("INSERT INTO session_examen
            (nom_session, idtype_examen, idtypeformation, idmodule, type_session,
             date_debut, date_fin, duree_minutes, statut)
            VALUES (?,?,?,?,?,?,?,?,'planifiee')");
        $ins->bind_param("siiiissi", $nom,$ite,$itf,$imod,$ts,$deb,$fin,$dur);
        $ins->execute();
        $new_sid = $conn->insert_id;
        $ins->close();

        if ($conn->error || !$new_sid) {
            $_SESSION['sess_msg'] = 'err:'.($conn->error ?: 'Erreur insertion session');
            header("Location: sessions.php"); exit();
        }

        foreach ($cands as $idcand) {
            if (!$idcand) continue;
            $conn->query("INSERT IGNORE INTO candidat_session (idcandidat,id_session,habilite) VALUES ($idcand,$new_sid,1)");
            $inf = $conn->query("SELECT c.code_acces, s.nomstagiaire, s.prenomstagiaire
                FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
                WHERE c.idcandidat=$idcand")->fetch_assoc();
            if (!$inf) continue;
            $mdp_plain = genMdp(9);
            $mdp_hash  = password_hash($mdp_plain, PASSWORD_DEFAULT);
            $conn->query("UPDATE candidat SET mot_de_passe='$mdp_hash', tentatives=0, is_logged_in=0, bloque=0 WHERE idcandidat=$idcand");
            $export_rows[] = [
                'nom'  => $inf['nomstagiaire'].' '.$inf['prenomstagiaire'],
                'code' => $inf['code_acces'],
                'mdp'  => $mdp_plain,
            ];
        }

        $_SESSION['sess_export'] = $export_rows;
        $_SESSION['new_sid']     = $new_sid;
        $_SESSION['sess_msg']    = 'create_ok:'.$nom;
        header("Location: sessions.php"); exit();
    } else {
        $manquant = [];
        if (!$sid_src) $manquant[]='session source';
        if (!$imod)    $manquant[]='module';
        if (!$nom)     $manquant[]='nom';
        if (!$deb||!$fin) $manquant[]='dates';
        if (empty($cands)) $manquant[]='au moins un candidat';
        $_SESSION['sess_msg'] = 'err:Champs manquants : '.implode(', ',$manquant);
        header("Location: sessions.php"); exit();
    }
}

/* ── Statut rapide ───────────────────────────────────────── */
if (isset($_GET['set_statut'],$_GET['id'])) {
    $sid=intval($_GET['id']); $st=$conn->real_escape_string($_GET['set_statut']);
    $cur=$conn->query("SELECT statut FROM session_examen WHERE id_session=$sid")->fetch_assoc();
    if($cur && in_array($st,['planifiee','en_cours','terminee','annulee'])&&!in_array($cur['statut'],['terminee','annulee']))
        $conn->query("UPDATE session_examen SET statut='$st' WHERE id_session=$sid");
    header("Location: sessions.php"); exit();
}

/* ── Filtres tableau ─────────────────────────────────────── */
$fs=$conn->real_escape_string($_GET['f_statut']??'');
$ft=intval($_GET['f_type']??0);
$fq=$conn->real_escape_string($_GET['f_search']??'');
$w="WHERE 1=1";
if($fs) $w.=" AND se.statut='$fs'";
if($ft) $w.=" AND se.idtype_examen=$ft";
if($fq) $w.=" AND se.nom_session LIKE '%$fq%'";

$sessions=$conn->query("
    SELECT se.*,te.code AS tc,te.nom_fr AS tn,mf.nom_module_fr,mf.numero_module,tf.nomforma,
           COUNT(DISTINCT cs.idcandidat) AS nb_c, COUNT(DISTINCT sq.id) AS nb_q
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen=te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule
    LEFT JOIN si_anac.typeformation tf ON se.idtypeformation=tf.idtypeforma
    LEFT JOIN candidat_session cs ON cs.id_session=se.id_session AND cs.habilite=1
    LEFT JOIN session_questions sq ON sq.session_id=se.id_session
    $w GROUP BY se.id_session ORDER BY se.id_session DESC
");

$types_arr=[];$tr=$conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
while($t=$tr->fetch_assoc()) $types_arr[]=$t;

$formations_arr=[];
$fq2=$conn->query("SELECT idtypeforma,nomforma FROM si_anac.typeformation ORDER BY nomforma");
if($fq2) while($f=$fq2->fetch_assoc()) $formations_arr[]=$f;

$modules_all=$conn->query("SELECT mf.*,tf.nomforma FROM module_formation mf JOIN si_anac.typeformation tf ON mf.idtypeformation=tf.idtypeforma WHERE mf.actif=1 ORDER BY mf.idtypeformation,mf.numero_module");
$mods_data=[];
if($modules_all) while($m=$modules_all->fetch_assoc())
    $mods_data[$m['idtypeformation']][]=['id'=>$m['idmodule'],'num'=>$m['numero_module'],'nom'=>$m['nom_module_fr']];

$cnt=[];
foreach(['planifiee','en_cours','terminee','annulee'] as $sk)
    $cnt[$sk]=$conn->query("SELECT COUNT(*) FROM session_examen WHERE statut='$sk'")->fetch_row()[0];

$msg         = $_SESSION['sess_msg']??'';    unset($_SESSION['sess_msg']);
$export_rows = $_SESSION['sess_export']??[]; unset($_SESSION['sess_export']);
$new_sid     = $_SESSION['new_sid']??0;      unset($_SESSION['new_sid']);
$active_page = 'sessions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sessions — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
:root{--blue:#03224c;--blue-mid:#0a3a6b;--gold:#D4AF37;}
.tp{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
.km{background:white;border-radius:11px;padding:14px 18px;box-shadow:0 2px 10px rgba(3,34,76,.07);
    flex:1;min-width:110px;border-left:3px solid transparent;cursor:pointer;transition:transform .2s;}
.km:hover{transform:translateY(-2px);}
.cand-tbl{width:100%;border-collapse:collapse;font-size:.84rem;}
.cand-tbl th{background:var(--blue);color:#fff;padding:8px 12px;text-align:left;font-weight:700;font-size:.78rem;}
.cand-tbl td{padding:8px 12px;border-bottom:1px solid #f0f4fa;vertical-align:middle;}
.cand-tbl tr.sel td{background:#e8f0fe;}
.cand-tbl tr:hover td{background:#f8faff;}
.cand-chk{width:17px;height:17px;accent-color:var(--blue);cursor:pointer;}
.exp-tbl{width:100%;border-collapse:collapse;font-size:.88rem;font-family:'Candara',sans-serif;}
.exp-tbl th{background:var(--blue);color:#fff;padding:10px 14px;font-weight:700;text-align:left;}
.exp-tbl td{padding:9px 14px;border-bottom:1px solid #e5e7eb;}
.exp-tbl tr:nth-child(even) td{background:#f8faff;}
.code-b{background:var(--blue);color:#fff;padding:3px 10px;border-radius:50px;font-weight:800;font-size:.82rem;font-family:monospace;}
.mdp-b{background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-weight:800;font-family:monospace;}
.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;}
.modal-ov.show{display:flex;}
.modal-box{background:#fff;border-radius:18px;padding:28px;width:92%;max-width:500px;box-shadow:0 20px 60px rgba(3,34,76,.25);max-height:90vh;overflow-y:auto;}
.modal-box h4{font-weight:800;color:var(--blue);font-size:1rem;margin-bottom:14px;}
@media print {
    body * { visibility: hidden !important; }
    #printZone, #printZone * { visibility: visible !important; }
    #printZone { position: fixed; top: 0; left: 0; width: 100%; padding: 20px; background: #fff; }
    .no-print { display: none !important; }
}
/* Accordéon */
.accordion-toggle {
    transition: transform 0.2s;
    background: transparent;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    width: 28px;
    color: #9d174d;
}
.accordion-toggle .fa-plus-circle,
.accordion-toggle .fa-minus-circle {
    font-size: 1.1rem;
}
.container-row .accordion-toggle:hover {
    opacity: 0.7;
}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-calendar-alt me-2"></i>Sessions d'examen</div>
    <div class="ms-auto"><span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span></div>
</div>
<div class="admin-content">

<!-- KPIs -->
<div class="d-flex gap-3 mb-4 flex-wrap">
<?php foreach([
    [''          , array_sum($cnt), 'TOTAL'     , '#6b7280'],
    ['planifiee' , $cnt['planifiee'], 'PLANIFIÉES','#2563eb'],
    ['en_cours'  , $cnt['en_cours'], 'EN COURS'  ,'#16a34a'],
    ['terminee'  , $cnt['terminee'], 'TERMINÉES' ,'#6b7280'],
    ['annulee'   , $cnt['annulee'], 'ANNULÉES'   ,'#dc2626'],
] as [$k,$v,$l,$c]): ?>
<div class="km" style="border-left-color:<?= $c ?>" onclick="filSt('<?= $k ?>')">
    <div style="font-size:1.6rem;font-weight:800;color:<?= $c ?>;"><?= $v ?></div>
    <div style="font-size:.74rem;color:#9ca3af;font-weight:600;"><?= $l ?></div>
</div>
<?php endforeach; ?>
</div>

<?php if (!empty($export_rows)): ?>
<div id="printZone">
<div class="card-admin mb-4" style="border-left:4px solid #16a34a;">
    <div class="card-admin-header" style="background:linear-gradient(135deg,var(--blue),var(--blue-mid));">
        <i class="fas fa-key me-2" style="color:var(--gold)"></i>
        <h5 style="color:#fff;">Codes d'accès &amp; mots de passe — À distribuer aux candidats</h5>
        <div class="ms-auto d-flex gap-2 no-print">
            <button onclick="copierTableau()" style="background:var(--gold);color:var(--blue);border:none;border-radius:50px;padding:5px 14px;font-weight:700;font-size:.78rem;cursor:pointer;">
                <i class="fas fa-copy me-1"></i>Copier Word
            </button>
            <button onclick="window.print()" style="background:#fff;color:var(--blue);border:1px solid #e0e7f0;border-radius:50px;padding:5px 14px;font-weight:700;font-size:.78rem;cursor:pointer;">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
        </div>
    </div>
    <div class="card-admin-body p-0">
        <div style="padding:14px 18px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
            <div style="font-weight:700;color:#166534;font-size:.9rem;">
                <i class="fas fa-check-circle me-1"></i>ANAC GABON — EXASUR
            </div>
            <div style="font-size:.8rem;color:#166534;margin-top:3px;">
                <?= count($export_rows) ?> candidat(s) — Session créée avec succès
                <?php if($new_sid): ?> — ID #<?= $new_sid ?><?php endif; ?>
            </div>
        </div>
        <div style="padding:16px;overflow-x:auto;">
            <table class="exp-tbl" id="exportTable">
                <thead><tr><th>#</th><th>Nom &amp; Prénom</th><th>Code d'accès</th><th>Mot de passe</th></tr></thead>
                <tbody>
                <?php foreach($export_rows as $i=>$row): ?>
                <tr>
                    <td style="color:#9ca3af;font-weight:600;"><?= $i+1 ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($row['nom']) ?></td>
                    <td><span class="code-b"><?= htmlspecialchars($row['code']) ?></span></td>
                    <td><span class="mdp-b"><?= htmlspecialchars($row['mdp']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<!-- FORMULAIRE FORM UNIQUEMENT -->
<div class="add-panel mb-4">
    <div class="add-panel-header" id="addHdr">
        <i class="fas fa-layer-group" style="color:var(--gold)"></i>
        <span style="font-weight:700;">Créer une session d'évaluation FORM</span>
        <i class="fas fa-chevron-down ms-auto" id="addChv"></i>
    </div>
    <div class="add-panel-body d-none" id="addBody">
        <div style="background:#fce7f3;border:1.5px solid #f9a8d4;border-radius:12px;padding:14px 16px;margin-bottom:18px;">
            <div style="font-weight:700;color:#9d174d;margin-bottom:8px;font-size:.9rem;">
                <i class="fas fa-info-circle me-1"></i>Comment créer une évaluation de module FORM ?
            </div>
            <div style="font-size:.82rem;color:#831843;line-height:1.8;">
                <strong>Ce formulaire crée une session d'évaluation par module</strong>, à partir d'une session de formation importée depuis AGFAC-DU.<br>
                <strong>1.</strong> Sélectionnez la <strong>session de formation source</strong> (importée depuis AGFAC-DU — contient vos stagiaires).<br>
                <strong>2.</strong> Choisissez le <strong>module à évaluer</strong> parmi ceux évaluables (2, 3, 4, 6, 8, 9).<br>
                <strong>3.</strong> <strong>Cochez les stagiaires</strong> qui passent ce module.<br>
                <strong>4.</strong> Saisissez les <strong>dates de l'évaluation</strong> — différentes des dates de formation.<br>
                <strong>5.</strong> Cliquez <strong>Créer</strong> → session créée, tableau codes+mots de passe généré.<br>
                <br>
                <i class="fas fa-lightbulb" style="color:var(--gold);"></i>
                <strong>Conseil :</strong> pour évaluer plusieurs modules d'une même formation, répétez cette opération en choisissant la même session source mais un module différent.
            </div>
        </div>
        <form method="POST" id="addForm">
            <input type="hidden" name="action" value="create_form_session">
            <input type="hidden" name="sid_source" id="hidSID">
            <input type="hidden" name="idtypeformation" id="hidTF">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label-admin">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">1</span>
                        Session de formation planifiée *
                        <span style="font-size:.72rem;color:#9ca3af;">(non expirée, type FORM)</span>
                    </label>
                    <select class="form-select-admin s2" id="selSF" style="max-width:600px;">
                        <option value="">-- Chargement... --</option>
                    </select>
                    <div id="sfInfo" style="font-size:.75rem;color:#9ca3af;margin-top:4px;display:none;">
                        <i class="fas fa-users me-1" style="color:var(--gold);"></i>
                        <span id="sfInfoTxt"></span>
                    </div>
                </div>
                <div class="col-md-5 d-none" id="colTF">
                    <label class="form-label-admin">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">2a</span>
                        Cours / Formation
                    </label>
                    <select name="idtypeformation" class="form-select-admin s2" id="selTF">
                        <option value="">-- Choisir --</option>
                        <?php foreach($formations_arr as $f): ?>
                        <option value="<?= $f['idtypeforma'] ?>"><?= htmlspecialchars($f['nomforma']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7 d-none" id="colMod">
                    <label class="form-label-admin">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">2b</span>
                        Module à évaluer *
                        <span style="font-size:.71rem;color:#16a34a;margin-left:4px;font-weight:700;">Évaluables : 2,3,4,6,8,9,11</span>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <select name="idmodule" class="form-select-admin s2" id="selMod" required style="flex:1;">
                            <option value="">-- Sélectionner un module --</option>
                        </select>
                        <button type="button" onclick="openModModal()"
                            style="background:#f0f9ff;color:#0891b2;border:1.5px solid #bae6fd;border-radius:8px;padding:8px 13px;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                    <div style="font-size:.71rem;color:#9ca3af;margin-top:3px;">Sans évaluation : 1,5,7,10,12</div>
                </div>
                <div class="col-md-7 d-none" id="colNom">
                    <label class="form-label-admin">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">3</span>
                        Nom de la session d'évaluation *
                    </label>
                    <input type="text" name="nom_session" id="inpNom" class="form-control-admin" required placeholder="Ex: FORM — Module 3 — Sûreté Avancée — Avr.2026">
                    <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">
                        <i class="fas fa-magic me-1" style="color:var(--gold);"></i>Auto-généré — vous pouvez le modifier
                    </div>
                </div>
                <div class="col-12 d-none" id="colDates">
                    <div style="font-weight:700;color:var(--blue);font-size:.87rem;margin-bottom:8px;">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">4</span>
                        Dates de l'évaluation
                        <span style="font-size:.71rem;color:#9ca3af;font-weight:400;margin-left:6px;">(peuvent différer des dates de formation)</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label-admin">Date début *</label>
                            <input type="date" name="date_debut" id="inpDeb" class="form-control-admin" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Date fin *</label>
                            <input type="date" name="date_fin" id="inpFin" class="form-control-admin" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-admin">Durée (min)</label>
                            <input type="number" name="duree_minutes" class="form-control-admin" value="90" min="10">
                        </div>
                    </div>
                </div>
                <div class="col-12 d-none" id="colCands">
                    <div style="font-weight:700;color:var(--blue);font-size:.87rem;margin-bottom:8px;">
                        <span style="background:var(--blue);color:#fff;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-right:5px;">5</span>
                        Stagiaires à inscrire à cette évaluation
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                        <span id="candCount" style="font-size:.82rem;font-weight:700;color:var(--blue);">0 sélectionné(s)</span>
                        <button type="button" onclick="selAll(true)"  style="font-size:.74rem;padding:3px 10px;border:1px solid var(--blue);border-radius:6px;background:transparent;color:var(--blue);cursor:pointer;"><i class="fas fa-check-square me-1"></i>Tous</button>
                        <button type="button" onclick="selAll(false)" style="font-size:.74px;padding:3px 10px;border:1px solid #dc2626;border-radius:6px;background:transparent;color:#dc2626;cursor:pointer;"><i class="fas fa-square me-1"></i>Aucun</button>
                    </div>
                    <div id="candLoading" style="display:none;font-size:.82rem;color:#9ca3af;padding:10px;">
                        <i class="fas fa-spinner fa-spin me-1"></i>Chargement des stagiaires...
                    </div>
                    <div style="border:1.5px solid #e0e7f0;border-radius:12px;overflow:hidden;max-height:280px;overflow-y:auto;" id="candBox">
                        <table class="cand-tbl">
                            <thead><tr><th width="36">✓</th><th>#</th><th>Nom &amp; Prénom</th><th>Code accès</th></tr></thead>
                            <tbody id="candBody"><tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:20px;">Sélectionnez d'abord une session</td></tr></tbody>
                        </table>
                    </div>
                    <div id="noCands" style="display:none;text-align:center;color:#9ca3af;padding:14px;font-size:.83rem;">
                        <i class="fas fa-user-slash me-1"></i>Aucun stagiaire trouvé dans cette session.
                    </div>
                </div>
            </div>
            <div class="d-flex gap-3 mt-4 d-none" id="btnZone">
                <button type="submit" class="btn-gold" onclick="return validerForm()">
                    <i class="fas fa-plus-circle me-2"></i>Créer la session d'évaluation
                </button>
                <button type="button" class="btn-anac" style="background:#fff;color:var(--blue);" onclick="closeForm()">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ajout module -->
<div class="modal-ov" id="modModal">
    <div class="modal-box">
        <h4><i class="fas fa-layer-group me-2" style="color:var(--gold)"></i>Ajouter un module de formation</h4>
        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:9px;padding:9px 13px;font-size:.79rem;color:#166534;margin-bottom:10px;">
            <i class="fas fa-check-circle me-1"></i><strong>Évaluables :</strong> 2, 3, 4, 6, 8, 9, 11
        </div>
        <div style="background:#fff8e1;border:1.5px solid #fde68a;border-radius:9px;padding:9px 13px;font-size:.79rem;color:#92400e;margin-bottom:16px;">
            <i class="fas fa-info-circle me-1"></i><strong>Sans évaluation :</strong> 1, 5, 7, 10, 12
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label-admin">Type de formation *</label>
                <select class="form-select-admin" id="modTF" style="width:100%;">
                    <option value="">-- Choisir --</option>
                    <?php foreach($formations_arr as $f): ?>
                    <option value="<?= $f['idtypeforma'] ?>"><?= htmlspecialchars($f['nomforma']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4">
                <label class="form-label-admin">N° Module *</label>
                <input type="number" id="modNum" class="form-control-admin" min="1" max="20" placeholder="Ex: 6">
                <div id="modNumInfo" style="font-size:.71rem;margin-top:3px;"></div>
            </div>
            <div class="col-8">
                <label class="form-label-admin">🇫🇷 Nom du module *</label>
                <input type="text" id="modNomFr" class="form-control-admin" placeholder="Ex: Fouille des bagages">
            </div>
            <div class="col-12">
                <label class="form-label-admin">🇬🇧 Nom EN</label>
                <input type="text" id="modNomEn" class="form-control-admin" placeholder="Ex: Baggage search">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px;">
            <button type="button" class="btn-gold" onclick="saveModule()"><i class="fas fa-save me-1"></i>Enregistrer</button>
            <button type="button" class="btn-anac" style="background:#f3f4f6;color:#374151;" onclick="closeModModal()"><i class="fas fa-times me-1"></i>Annuler</button>
        </div>
    </div>
</div>

<!-- TABLEAU SESSIONS -->
<div class="card-admin">
    <div class="card-admin-header">
        <i class="fas fa-list me-2"></i><h5>Liste des sessions</h5>
        <span class="badge-count ms-2"><?= array_sum($cnt) ?></span>
    </div>
    <div class="card-admin-body p-0">
        <div class="filter-bar" style="border-radius:0;box-shadow:none;border-bottom:1px solid var(--gray-border);flex-wrap:wrap;gap:8px;">
            <div class="filter-group">
                <label>Recherche</label>
                <input class="form-control-admin" id="srchS" placeholder="Nom session...">
            </div>
            <div class="filter-group" style="min-width:220px;">
                <label>Nom session</label>
                <select class="form-select-admin select2-session" id="filNom" style="width:100%;">
                    <option value="">Toutes les sessions</option>
                    <?php
                    $sess_names=$conn->query("SELECT DISTINCT nom_session FROM session_examen ORDER BY nom_session");
                    if($sess_names) while($sn=$sess_names->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($sn['nom_session']) ?>"><?= htmlspecialchars(mb_substr($sn['nom_session'],0,60)) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group" style="max-width:145px;">
                <label>Date début</label>
                <input type="date" class="form-control-admin" id="filDeb" onchange="filterS()">
            </div>
            <div class="filter-group" style="max-width:145px;">
                <label>Date fin</label>
                <input type="date" class="form-control-admin" id="filFin" onchange="filterS()">
            </div>
            <div class="filter-group" style="max-width:140px">
                <label>Statut</label>
                <select class="form-select-admin" id="filStatut">
                    <option value="">Tous</option><option value="planifiee">Planifiée</option>
                    <option value="en_cours">En cours</option><option value="terminee">Terminée</option><option value="annulee">Annulée</option>
                </select>
            </div>
            <div class="filter-group" style="max-width:120px">
                <label>Type</label>
                <select class="form-select-admin" id="filType">
                    <option value="">Tous</option>
                    <?php foreach($types_arr as $t): ?><option value="<?= $t['code'] ?>"><?= $t['code'] ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="align-self:flex-end;">
                <button onclick="resetFiltres()" type="button" style="background:#f3f4f6;color:#374151;border:1px solid #e0e7f0;border-radius:8px;padding:7px 12px;font-size:.78rem;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-times me-1"></i>Effacer
                </button>
            </div>
        </div>

        <?php
        $all_sessions = $conn->query("
            SELECT se.*, te.code AS tc, te.nom_fr AS tn,
                   mf.nom_module_fr, mf.numero_module,
                   tf.nomforma,
                   COUNT(DISTINCT cs.idcandidat) AS nb_c,
                   COUNT(DISTINCT sq.id)         AS nb_q
            FROM session_examen se
            JOIN type_examen te ON se.idtype_examen=te.idtype_examen
            LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule
            LEFT JOIN si_anac.typeformation tf ON se.idtypeformation=tf.idtypeforma
            LEFT JOIN candidat_session cs ON cs.id_session=se.id_session AND cs.habilite=1
            LEFT JOIN session_questions sq ON sq.session_id=se.id_session
            WHERE 1=1
            GROUP BY se.id_session
            ORDER BY
                /* Priorité 1 : sessions en attente (planifiée / en cours) remontent */
                CASE WHEN se.statut IN ('planifiee','en_cours') THEN 0 ELSE 1 END ASC,
                /* Priorité 2 : plus récentes d'abord (ID décroissant) */
                se.id_session DESC
        ");

        $non_form = [];
        $form_conteneurs = [];
        $form_modules    = [];

        if($all_sessions) while($ss=$all_sessions->fetch_assoc()) {
            if ($ss['tc'] !== 'FORM') {
                $non_form[] = $ss;
            } elseif ($ss['idmodule'] === null) {
                $form_conteneurs[$ss['idtypeformation']] = $ss;
            } else {
                $form_modules[$ss['idtypeformation']][] = $ss;
            }
        }
        ?>

        <div class="table-responsive">
        <table class="table-admin" id="tblS" style="table-layout:auto;">
            <thead>
                <tr>
                    <th>Session / Formation</th>
                    <th>Type</th>
                    <th>Épreuve</th>
                    <th>Module</th>
                    <th>Dates</th>
                    <th>Durée</th>
                    <th>Candidats</th>
                    <th>Questions</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach($non_form as $ss):
                $lk=in_array($ss['statut'],['terminee','annulee']); ?>
            <tr data-type="<?= $ss['tc'] ?>" data-statut="<?= $ss['statut'] ?>"
                data-s="<?= strtolower($ss['nom_session']) ?>"
                data-deb="<?= $ss['date_debut'] ?>" data-fin="<?= $ss['date_fin'] ?>">
                <td>
                    <div style="font-weight:700;font-size:.88rem;"><?= htmlspecialchars($ss['nom_session']) ?></div>
                    <div style="font-size:.72rem;color:#9ca3af;">ID #<?= $ss['id_session'] ?></div>
                </td>
                <td><span class="tp tp-<?= $ss['tc'] ?>"><?= $ss['tc'] ?></span></td>
                <td>
                    <?php if($ss['type_session']==='theorie'): ?>
                    <span style="font-size:.72rem;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:20px;">📖 Théorie</span>
                    <?php elseif($ss['type_session']==='pratique'): ?>
                    <span style="font-size:.72rem;background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:20px;">🖼️ Pratique</span>
                    <?php else: ?><span style="font-size:.75rem;color:#9ca3af;">Normal</span><?php endif; ?>
                </td>
                <td style="font-size:.8rem;color:#9ca3af;">—</td>
                <td style="font-size:.8rem;white-space:nowrap;">
                    <?= date('d/m/Y',strtotime($ss['date_debut'])) ?><br>
                    <span style="color:#9ca3af;">→ <?= date('d/m/Y',strtotime($ss['date_fin'])) ?></span>
                </td>
                <td style="font-size:.82rem;"><?= $ss['duree_minutes'] ?>min</td>
                <td style="font-weight:700;color:<?= $ss['nb_c']>0?'var(--blue)':'#9ca3af' ?>;"><?= $ss['nb_c'] ?></td>
                <td style="font-weight:700;color:<?= $ss['nb_q']>0?'#16a34a':'#dc2626' ?>;">
                    <?= $ss['nb_q'] ?><?= $ss['nb_q']==0&&$ss['statut']==='planifiee'?' ⚠️':'' ?>
                </td>
                <td><span class="badge-status badge-<?= $ss['statut'] ?>"><?= ucfirst($ss['statut']) ?></span></td>
                <td>
                    <?php if(!$lk): ?>
                    <a href="session_questions.php?id=<?= $ss['id_session'] ?>" class="btn-icon btn-icon-manage" title="Questions"><i class="fas fa-tasks"></i></a>
                    <a href="sessions_edit.php?id=<?= $ss['id_session'] ?>" class="btn-icon btn-icon-edit" title="Modifier"><i class="fas fa-edit"></i></a>
                    <?php if($ss['statut']==='planifiee'): ?>
                    <a href="?set_statut=en_cours&id=<?= $ss['id_session'] ?>" class="btn-icon" style="background:#dcfce7;color:#16a34a;" title="Démarrer" onclick="return confirm('Démarrer ?')"><i class="fas fa-play"></i></a>
                    <?php elseif($ss['statut']==='en_cours'): ?>
                    <a href="?set_statut=terminee&id=<?= $ss['id_session'] ?>" class="btn-icon" style="background:#f3f4f6;color:#6b7280;" title="Terminer" onclick="return confirm('Terminer ?')"><i class="fas fa-stop"></i></a>
                    <?php endif; ?>
                    <?php else: ?><span class="btn-icon btn-icon-disabled"><i class="fas fa-lock"></i></span><?php endif; ?>
                    <a href="print_session.php?id=<?= $ss['id_session'] ?>" target="_blank" class="btn-icon" style="background:#f0f3f9;color:#374151;" title="Imprimer"><i class="fas fa-print"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php
            $itfs_all = array_unique(array_merge(
                array_keys($form_conteneurs),
                array_keys($form_modules)
            ));
            sort($itfs_all);

            foreach($itfs_all as $itf):
                $cont   = $form_conteneurs[$itf] ?? null;
                $mods   = $form_modules[$itf]    ?? [];
                $nb_mods= count($mods);

                if ($cont): ?>
            <tr data-type="FORM" data-statut="<?= $cont['statut'] ?>"
                data-s="<?= strtolower($cont['nom_session']) ?>"
                data-deb="<?= $cont['date_debut'] ?>" data-fin="<?= $cont['date_fin'] ?>"
                class="container-row" id="container-<?= $cont['id_session'] ?>"
                style="background:linear-gradient(135deg,#fdf4ff,#fce7f3);">
                <td colspan="10" style="padding:10px 14px;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" class="accordion-toggle" data-container="container-<?= $cont['id_session'] ?>">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                            <div style="background:#9d174d;color:#fff;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-graduation-cap" style="font-size:.82rem;"></i>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-weight:800;color:#831843;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($cont['nom_session']) ?>
                                </div>
                                <div style="font-size:.72rem;color:#be185d;margin-top:1px;">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?= date('d/m/Y',strtotime($cont['date_debut'])) ?> → <?= date('d/m/Y',strtotime($cont['date_fin'])) ?>
                                    &nbsp;·&nbsp;
                                    <i class="fas fa-users me-1"></i><?= $cont['nb_c'] ?> stagiaire(s)
                                    &nbsp;·&nbsp; ID #<?= $cont['id_session'] ?>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;flex-wrap:wrap;">
                            <span class="tp tp-FORM">FORM</span>
                            <span style="background:#fff;border:1px solid #f9a8d4;color:#9d174d;padding:2px 9px;border-radius:50px;font-size:.72rem;font-weight:700;">
                                <i class="fas fa-layer-group me-1"></i><?= $nb_mods ?> module<?= $nb_mods>1?'s':'' ?> évalué<?= $nb_mods>1?'s':'' ?>
                            </span>
                            <span class="badge-status badge-<?= $cont['statut'] ?>"><?= ucfirst($cont['statut']) ?></span>
                            <span style="background:#fce7f3;color:#9d174d;border:1px solid #f9a8d4;padding:2px 9px;border-radius:50px;font-size:.7rem;font-weight:600;font-style:italic;">
                                <i class="fas fa-info-circle me-1"></i>Source — non évalué directement
                            </span>
                        </div>
                    </div>
                </td>
            </tr>
                <?php endif;

                foreach($mods as $mod):
                    $lk=in_array($mod['statut'],['terminee','annulee']); ?>
            <tr data-type="FORM" data-statut="<?= $mod['statut'] ?>"
                data-s="<?= strtolower($mod['nom_session']) ?>"
                data-deb="<?= $mod['date_debut'] ?>" data-fin="<?= $mod['date_fin'] ?>"
                class="module-row" data-container="container-<?= $cont['id_session'] ?? '0' ?>"
                style="background:#fafafa; display:none;">
                <td style="padding-left:30px;">
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <span style="color:#d946a8;font-size:1rem;line-height:1.4;flex-shrink:0;">↳</span>
                        <div>
                            <div style="font-weight:700;font-size:.86rem;color:#374151;">
                                <?= htmlspecialchars($mod['nom_session']) ?>
                            </div>
                            <div style="font-size:.71rem;color:#9ca3af;">ID #<?= $mod['id_session'] ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="tp tp-FORM" style="opacity:.7;">FORM</span></td>
                <td><span style="font-size:.72rem;background:#f3e8ff;color:#7c3aed;padding:2px 8px;border-radius:20px;">📝 Module</span></td>
                <td style="font-size:.82rem;">
                    <?php if($mod['nom_module_fr']): ?>
                    <span style="background:var(--blue);color:#fff;padding:1px 7px;border-radius:50px;font-size:.72rem;font-weight:800;margin-right:4px;">
                        <?= $mod['numero_module'] ?>
                    </span>
                    <?= htmlspecialchars(mb_substr($mod['nom_module_fr'],0,28)) ?>
                    <?php else: ?><span style="color:#9ca3af;">—</span><?php endif; ?>
                </td>
                <td style="font-size:.8rem;white-space:nowrap;">
                    <?= date('d/m/Y',strtotime($mod['date_debut'])) ?><br>
                    <span style="color:#9ca3af;">→ <?= date('d/m/Y',strtotime($mod['date_fin'])) ?></span>
                </td>
                <td style="font-size:.82rem;"><?= $mod['duree_minutes'] ?>min</td>
                <td style="font-weight:700;color:<?= $mod['nb_c']>0?'var(--blue)':'#9ca3af' ?>;"><?= $mod['nb_c'] ?></td>
                <td>
                    <?php if($mod['nb_q']>0): ?>
                    <span style="font-weight:700;color:#16a34a;"><?= $mod['nb_q'] ?></span>
                    <?php else: ?>
                    <span style="font-size:.72rem;background:#fff8e1;color:#92400e;padding:2px 7px;border-radius:50px;font-weight:700;">
                        ⚠️ À affecter
                    </span>
                    <?php endif; ?>
                </td>
                <td><span class="badge-status badge-<?= $mod['statut'] ?>"><?= ucfirst($mod['statut']) ?></span></td>
                <td>
                    <?php if(!$lk): ?>
                    <a href="session_questions.php?id=<?= $mod['id_session'] ?>" class="btn-icon btn-icon-manage" title="Affecter questions"><i class="fas fa-tasks"></i></a>
                    <a href="sessions_edit.php?id=<?= $mod['id_session'] ?>" class="btn-icon btn-icon-edit" title="Modifier / Candidats"><i class="fas fa-edit"></i></a>
                    <?php if($mod['statut']==='planifiee'): ?>
                    <a href="?set_statut=en_cours&id=<?= $mod['id_session'] ?>" class="btn-icon" style="background:#dcfce7;color:#16a34a;" title="Démarrer" onclick="return confirm('Démarrer ce module ?')"><i class="fas fa-play"></i></a>
                    <?php elseif($mod['statut']==='en_cours'): ?>
                    <a href="?set_statut=terminee&id=<?= $mod['id_session'] ?>" class="btn-icon" style="background:#f3f4f6;color:#6b7280;" title="Terminer" onclick="return confirm('Terminer ce module ?')"><i class="fas fa-stop"></i></a>
                    <?php endif; ?>
                    <?php else: ?><span class="btn-icon btn-icon-disabled"><i class="fas fa-lock"></i></span><?php endif; ?>
                    <a href="print_session.php?id=<?= $mod['id_session'] ?>" target="_blank" class="btn-icon" style="background:#f0f3f9;color:#374151;" title="Imprimer"><i class="fas fa-print"></i></a>
                </td>
            </tr>
                <?php endforeach;

                if (!$cont && !empty($mods)): ?>
            <tr style="background:#fff8e1;">
                <td colspan="10" style="padding:8px 14px;font-size:.8rem;color:#92400e;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Modules sans session de formation source</strong> — formation ID <?= $itf ?>
                </td>
            </tr>
                <?php endif;
            endforeach; ?>

            </tbody>
        </table>
        </div>
    </div>
</div>

</div></main></div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));

const modsData  = <?= json_encode($mods_data) ?>;
const MODS_EVAL = [2,3,4,6,8,9,11];

/* Panneau */
document.getElementById('addHdr').addEventListener('click',function(){
    const b=document.getElementById('addBody');
    b.classList.toggle('d-none');
    document.getElementById('addChv').style.transform=b.classList.contains('d-none')?'':'rotate(180deg)';
    if(!b.classList.contains('d-none')) setTimeout(initS2,50);
});
function closeForm(){document.getElementById('addBody').classList.add('d-none');document.getElementById('addChv').style.transform='';}

function initS2(){
    const p=$('#addBody');
    $('#selSF').select2({dropdownParent:p,placeholder:'-- Sélectionner une session de formation --',width:'100%',allowClear:true});
    $('#selTF').select2({dropdownParent:p,placeholder:'-- Choisir le cours --',width:'100%',allowClear:true});
    $('#selMod').select2({dropdownParent:p,placeholder:'-- Module à évaluer --',width:'100%',allowClear:true});
    $('#modTF').select2({dropdownParent:$('#modModal'),placeholder:'-- Choisir --',width:'100%'});
    chargerSessionsFORM();
}

function chargerSessionsFORM(){
    $.getJSON('sessions.php?ajax=sessions_form',function(data){
        $('#selSF').empty().append('<option value="">-- Sélectionner une session de formation AGFAC-DU --</option>');
        if(!data||!data.length){
            $('#selSF').append('<option disabled>Aucune session FORM planifiée disponible</option>');
            return;
        }
        data.forEach(function(s){
            const deb=fmtDate(s.date_debut), fin=fmtDate(s.date_fin);
            const nom=(s.nomforma||s.nom_session)+' ['+deb+'→'+fin+'] — '+s.nb_c+' stagiaire(s)';
            $('<option>').val(s.id_session)
                .text(nom)
                .data('itf',s.idtypeformation||'')
                .data('nb',s.nb_c)
                .data('nom',s.nomforma||s.nom_session)
                .data('deb',s.date_debut)
                .data('fin',s.date_fin)
                .appendTo('#selSF');
        });
    });
}

$('#selSF').on('change',function(){
    const sid=$(this).val();
    const opt=$(this).find(':selected');
    const itf=opt.data('itf')||'';
    const nb=opt.data('nb')||0;
    const nomF=opt.data('nom')||'';
    const deb=opt.data('deb')||'';
    const fin=opt.data('fin')||'';

    document.getElementById('hidSID').value=sid||'';
    document.getElementById('hidTF').value=itf||'';

    ['colTF','colMod','colNom','colDates','colCands'].forEach(id=>document.getElementById(id).classList.add('d-none'));
    $('#btnZone').addClass('d-none');
    document.getElementById('sfInfo').style.display='none';

    if(!sid) return;

    document.getElementById('sfInfoTxt').textContent=nb+' stagiaire(s) dans cette session';
    document.getElementById('sfInfo').style.display='block';

    if(deb) document.getElementById('inpDeb').value=deb;
    if(fin) document.getElementById('inpFin').value=fin;

    if(itf){ $('#selTF').val(itf).trigger('change'); }

    document.getElementById('colTF').classList.remove('d-none');
    document.getElementById('colMod').classList.remove('d-none');
    document.getElementById('colNom').classList.remove('d-none');
    document.getElementById('colDates').classList.remove('d-none');
    document.getElementById('colCands').classList.remove('d-none');

    chargerCandidats(sid, nomF);
    $('#btnZone').removeClass('d-none');
});

function chargerCandidats(sid, nomF){
    const tbody=document.getElementById('candBody');
    document.getElementById('noCands').style.display='none';
    document.getElementById('candBox').style.display='';
    tbody.innerHTML='<tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:16px;"><i class="fas fa-spinner fa-spin me-1"></i>Chargement...</td></tr>';
    document.getElementById('candCount').textContent='0 sélectionné(s)';

    $.getJSON('sessions.php?ajax=cands_form&sid='+sid,function(data){
        tbody.innerHTML='';
        if(!data||!data.length){
            document.getElementById('candBox').style.display='none';
            document.getElementById('noCands').style.display='block';
            updateNomAuto(nomF);
            return;
        }
        data.forEach(function(c,i){
            const tr=document.createElement('tr');
            tr.className='sel';
            tr.innerHTML=`<td><input type="checkbox" class="cand-chk" name="cands[]" value="${c.idcandidat}" checked></td>
                <td style="color:#9ca3af;font-size:.78rem;">${i+1}</td>
                <td style="font-weight:700;">${esc(c.nomstagiaire)} ${esc(c.prenomstagiaire)}</td>
                <td><span class="code-b">${esc(c.code_acces)}</span></td>`;
            tr.querySelector('input').addEventListener('change',function(){
                tr.classList.toggle('sel',this.checked);updateCount();
            });
            tbody.appendChild(tr);
        });
        updateCount();
        updateNomAuto(nomF);
    });
}

function updateCount(){
    const chk=document.querySelectorAll('.cand-chk:checked').length;
    const tot=document.querySelectorAll('.cand-chk').length;
    document.getElementById('candCount').textContent=chk+' / '+tot+' sélectionné(s)';
}
function selAll(v){document.querySelectorAll('.cand-chk').forEach(c=>{c.checked=v;c.closest('tr').classList.toggle('sel',v);});updateCount();}

function updateNomAuto(nomF){
    const modOpt=$('#selMod').find(':selected');
    const modTxt=modOpt.length&&modOpt.val()?modOpt.text().split('—')[0].trim():'';
    const debVal=document.getElementById('inpDeb').value;
    let datePart='';
    if(debVal){
        const p=debVal.split('-');
        datePart=p[2]+'/'+p[1]+'/'+p[0];
    } else {
        const mois=['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const now=new Date(); datePart=mois[now.getMonth()]+'.'+now.getFullYear();
    }
    const nom=['FORM',modTxt,nomF?nomF.substring(0,30):'',datePart].filter(Boolean).join(' — ');
    document.getElementById('inpNom').value=nom;
}
$('#selMod').on('change',function(){
    const nomF=$('#selSF').find(':selected').data('nom')||'';
    updateNomAuto(nomF);
});
document.getElementById('inpDeb').addEventListener('change',function(){
    const nomF=$('#selSF').find(':selected').data('nom')||'';
    updateNomAuto(nomF);
});

function loadMods(itf){
    const sel=document.getElementById('selMod');
    sel.innerHTML='<option value="">-- Sélectionner un module --</option>';
    if(!itf){$('#selMod').trigger('change');return;}
    const mods=(modsData[itf]||[]).filter(m=>MODS_EVAL.includes(parseInt(m.num)));
    if(!mods.length){
        [{num:2,nom:'Cadre juridique et réglementaire',id:''},{num:3,nom:"Mesures de sûreté",id:''},
         {num:4,nom:"Contrôle d'accès",id:''},{num:6,nom:"Fouille des bagages",id:''},
         {num:8,nom:"Inspection filtrage",id:''},{num:9,nom:"Gestion des incidents",id:''},
         {num:11,nom:"Facteurs humains et comportementaux",id:''}].forEach(m=>{
            sel.innerHTML+=`<option value="${m.id}">Module ${m.num} — ${m.nom}</option>`;
        });
    } else {
        mods.forEach(m=>sel.innerHTML+=`<option value="${m.id}">Module ${m.num} — ${m.nom}</option>`);
    }
    $('#selMod').trigger('change');
}
$('#selTF').on('change',function(){loadMods(this.value);});

function validerForm(){
    const chk=document.querySelectorAll('.cand-chk:checked').length;
    if(chk===0){Swal.fire('Aucun candidat','Cochez au moins un stagiaire.','warning');return false;}
    return true;
}

/* Modal module */
function openModModal(){
    const itf=$('#selTF').val();
    if(itf){$('#modTF').val(itf).trigger('change.select2');}
    document.getElementById('modModal').classList.add('show');
}
function closeModModal(){
    document.getElementById('modModal').classList.remove('show');
    ['modNum','modNomFr','modNomEn'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('modNumInfo').textContent='';
}
document.getElementById('modNum').addEventListener('input',function(){
    const n=parseInt(this.value); const el=document.getElementById('modNumInfo');
    if(!n){el.textContent='';return;}
    el.innerHTML=MODS_EVAL.includes(n)
        ?'<span style="color:#16a34a;font-weight:700;">✅ Module évaluable</span>'
        :'<span style="color:#ca8a04;font-weight:700;">⚠️ Non évalué (cours uniquement)</span>';
});
document.getElementById('modModal').addEventListener('click',function(e){if(e.target===this)closeModModal();});

function saveModule(){
    const itf=$('#modTF').val(),num=$('#modNum').val(),nomf=$('#modNomFr').val().trim(),nome=$('#modNomEn').val().trim();
    if(!itf||!num||!nomf){Swal.fire('Champs manquants','Type, N° et nom FR obligatoires.','warning');return;}
    Swal.fire({title:'Enregistrement...',html:'<i class="fas fa-spinner fa-spin fa-2x" style="color:#03224c"></i>',allowOutsideClick:false,showConfirmButton:false});
    $.post('sessions.php',{action:'add_module_ajax',idtypeformation:itf,numero_module:num,nom_module_fr:nomf,nom_module_en:nome},function(d){
        Swal.close();
        if(d.status==='success'){
            $('#selMod').append(`<option value="${d.idmodule}" selected>Module ${d.numero_module} — ${d.nom_module_fr}</option>`).trigger('change');
            if(!modsData[itf]) modsData[itf]=[];
            modsData[itf].push({id:d.idmodule,num:d.numero_module,nom:d.nom_module_fr});
            closeModModal();
            Swal.fire({title:'✅ Module ajouté',icon:'success',timer:2000,showConfirmButton:false,toast:true,position:'top-end'});
        } else Swal.fire('Erreur',d.message,'error');
    },'json');
}

function copierTableau(){
    const tbl=document.getElementById('exportTable');if(!tbl)return;
    const r=document.createRange();r.selectNode(tbl);
    const s=window.getSelection();s.removeAllRanges();s.addRange(r);
    try{document.execCommand('copy');
        Swal.fire({title:'📋 Copié !',text:'Collez dans Word avec Ctrl+V',icon:'success',timer:2000,showConfirmButton:false,toast:true,position:'top-end'});
    }catch(e){}
    window.getSelection().removeAllRanges();
}

/* ACCORDÉON */
function initAccordion() {
    document.querySelectorAll('.module-row').forEach(row => {
        row.style.display = 'none';
    });
    document.querySelectorAll('.accordion-toggle').forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-minus-circle');
            icon.classList.add('fa-plus-circle');
        }
    });
}

function toggleModules(containerId, btn) {
    const icon = btn.querySelector('i');
    const modules = document.querySelectorAll(`.module-row[data-container="${containerId}"]`);
    const isHidden = modules.length > 0 && modules[0].style.display === 'none';
    
    modules.forEach(mod => {
        mod.style.display = isHidden ? '' : 'none';
    });
    
    if (icon) {
        if (isHidden) {
            icon.classList.remove('fa-plus-circle');
            icon.classList.add('fa-minus-circle');
        } else {
            icon.classList.remove('fa-minus-circle');
            icon.classList.add('fa-plus-circle');
        }
    }
}

function bindAccordionEvents() {
    document.querySelectorAll('.accordion-toggle').forEach(btn => {
        btn.removeEventListener('click', accordionClickHandler);
        btn.addEventListener('click', accordionClickHandler);
    });
}

function accordionClickHandler(e) {
    e.stopPropagation();
    const containerId = this.getAttribute('data-container');
    if (containerId) {
        const containerRow = document.getElementById(containerId);
        if (containerRow && containerRow.style.display !== 'none') {
            toggleModules(containerId, this);
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Session masquée',
                text: 'Cette session est actuellement filtrée. Réinitialisez les filtres pour la voir.',
                toast: true,
                position: 'top-end',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }
}

/* FILTRES */
function filterS() {
    const q = document.getElementById('srchS').value.toLowerCase();
    const st = document.getElementById('filStatut').value;
    const tp = document.getElementById('filType').value;
    const nom = document.getElementById('filNom').value.toLowerCase();
    const deb = document.getElementById('filDeb').value;
    const fin = document.getElementById('filFin').value;
    
    document.querySelectorAll('#tblS tbody tr').forEach(row => {
        if (row.classList.contains('module-row')) return;
        
        const rowNom = (row.dataset.s || '');
        const rowDeb = row.dataset.deb || '';
        const rowFin = row.dataset.fin || '';
        const matchQ   = !q   || rowNom.includes(q);
        const matchNom = !nom || rowNom.includes(nom);
        const matchSt  = !st  || row.dataset.statut === st;
        const matchTp  = !tp  || row.dataset.type === tp;
        const matchDeb = !deb || rowDeb >= deb;
        const matchFin = !fin || rowFin <= fin;
        const show = matchQ && matchNom && matchSt && matchTp && matchDeb && matchFin;
        
        row.style.display = show ? '' : 'none';
    });
    
    document.querySelectorAll('.module-row').forEach(row => {
        row.style.display = 'none';
    });
    document.querySelectorAll('.accordion-toggle').forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-minus-circle');
            icon.classList.add('fa-plus-circle');
        }
    });
}

function resetFiltres() {
    document.getElementById('srchS').value = '';
    if ($('#filNom').data('select2')) $('#filNom').val('').trigger('change');
    else document.getElementById('filNom').value = '';
    document.getElementById('filStatut').value = '';
    document.getElementById('filType').value = '';
    document.getElementById('filDeb').value = '';
    document.getElementById('filFin').value = '';
    filterS();
}
function filSt(v){document.getElementById('filStatut').value=v;filterS();}

/* Événements filtres */
document.getElementById('srchS').addEventListener('input', filterS);
document.getElementById('filStatut').addEventListener('change', filterS);
document.getElementById('filType').addEventListener('change', filterS);
document.getElementById('filDeb').addEventListener('change', filterS);
document.getElementById('filFin').addEventListener('change', filterS);

/* Initialisation Select2 + accordéon */
$(document).ready(function() {
    $('#filNom').select2({
        placeholder: "Rechercher une session...",
        allowClear: true,
        width: '100%'
    });
    $('#filNom').on('change', function() { filterS(); });
    
    initAccordion();
    bindAccordionEvents();
});

function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function fmtDate(d){if(!d)return'';const p=d.split('-');return p[2]+'/'+p[1]+'/'+p[0];}

<?php if(str_starts_with($msg,'create_ok:')): $nomC=htmlspecialchars(substr($msg,10)); ?>
Swal.fire({title:'✅ Session créée !',text:'<?= $nomC ?>',icon:'success',timer:3500,timerProgressBar:true,showConfirmButton:false,toast:true,position:'top-end'});
setTimeout(()=>document.getElementById('printZone')?.scrollIntoView({behavior:'smooth'}),500);
<?php elseif(str_starts_with($msg,'err:')): ?>
Swal.fire({title:'Erreur',text:<?= json_encode(substr($msg,4)) ?>,icon:'error',confirmButtonColor:'#03224c'});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>