<?php
/**
 * sessions_edit.php — Modifier session + gérer candidats
 * admin/sessions_edit.php?id=X
 *
 * LOGIQUE :
 *  - Pour TOUS types : modifier infos session (nom, dates, durée...)
 *  - Pour FORM uniquement : section "Candidats" — voir tous les candidats
 *    de la session parente, ajouter/retirer, afficher mdp nouveaux
 *  - Pour les autres types (AS, IF, INST, SENS) : pas de section candidats ici
 *    (gestion via candidats.php ou import AGFAC-DU)
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$id = intval($_GET['id']??0);
$se = $conn->query("
    SELECT se.*, te.code AS tc, te.idtype_examen AS ite
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen=te.idtype_examen
    WHERE se.id_session=$id
")->fetch_assoc();
if (!$se || in_array($se['statut'],['terminee','annulee'])) { header("Location: sessions.php"); exit(); }

$is_form = ($se['ite'] == 5);

$types_arr=[];$tr=$conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");while($t=$tr->fetch_assoc()) $types_arr[]=$t;
$formations=$conn->query("SELECT idtypeforma,nomforma FROM si_anac.typeformation ORDER BY nomforma");
$modules_all=$conn->query("SELECT mf.*,tf.nomforma FROM module_formation mf JOIN si_anac.typeformation tf ON mf.idtypeformation=tf.idtypeforma WHERE mf.actif=1 ORDER BY mf.idtypeformation,mf.numero_module");
$mods_data=[];
if($modules_all) while($m=$modules_all->fetch_assoc())
    $mods_data[$m['idtypeformation']][]=['id'=>$m['idmodule'],'num'=>$m['numero_module'],'nom'=>$m['nom_module_fr']];

/* Helpers */
function genMdp($n=9){$a='abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';return substr(str_shuffle($a),0,$n);}

/* Candidats actuellement habilités dans cette session */
$cands_actifs=$conn->query("
    SELECT c.idcandidat, c.code_acces, s.nomstagiaire, s.prenomstagiaire
    FROM candidat_session cs
    JOIN candidat c ON cs.idcandidat=c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    WHERE cs.id_session=$id AND cs.habilite=1
    ORDER BY s.nomstagiaire, s.prenomstagiaire
");
$cands_actifs_arr=[]; while($r=$cands_actifs->fetch_assoc()) $cands_actifs_arr[]=$r;
$ids_actifs=array_column($cands_actifs_arr,'idcandidat');

/* Si FORM : chercher aussi tous les candidats de la session parente (même formation) */
$tous_session_arr=[];
if ($is_form && $se['idtypeformation']) {
    /* Tous les candidats habilités dans n'importe quelle session FORM de la même formation */
    $itf=$se['idtypeformation'];
    $r2=$conn->query("
        SELECT DISTINCT c.idcandidat, c.code_acces, s.nomstagiaire, s.prenomstagiaire
        FROM candidat_session cs
        JOIN session_examen se2 ON cs.id_session=se2.id_session
        JOIN candidat c ON cs.idcandidat=c.idcandidat
        JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
        WHERE se2.idtypeformation=$itf AND cs.habilite=1
        ORDER BY s.nomstagiaire, s.prenomstagiaire
    ");
    while($r=$r2->fetch_assoc()) $tous_session_arr[]=$r;
}

/* Tous les candidats BDD (pour ajouter n'importe qui) */
$tous_bdd=$conn->query("
    SELECT c.idcandidat, c.code_acces, s.nomstagiaire, s.prenomstagiaire
    FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    ORDER BY s.nomstagiaire, s.prenomstagiaire
");
$tous_bdd_arr=[]; while($r=$tous_bdd->fetch_assoc()) $tous_bdd_arr[]=$r;

$msg=''; $export_rows=[];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';

    /* ── Modifier infos session ── */
    if ($action==='edit_session') {
        $nom=$conn->real_escape_string($_POST['nom_session']??'');
        $ite=intval($_POST['idtype_examen']);
        $itf=!empty($_POST['idtypeformation'])?intval($_POST['idtypeformation']):'NULL';
        $imod=!empty($_POST['idmodule'])?intval($_POST['idmodule']):'NULL';
        $ts=$conn->real_escape_string($_POST['type_session']??'normal');
        $deb=$conn->real_escape_string($_POST['date_debut']??'');
        $fin=$conn->real_escape_string($_POST['date_fin']??'');
        $dur=intval($_POST['duree_minutes']??90);
        $stat=$conn->real_escape_string($_POST['statut']??'planifiee');
        $conn->query("UPDATE session_examen SET nom_session='$nom',idtype_examen=$ite,idtypeformation=$itf,idmodule=$imod,type_session='$ts',date_debut='$deb',date_fin='$fin',duree_minutes=$dur,statut='$stat' WHERE id_session=$id");
        $msg=$conn->error?:'edit_ok';
        $se=$conn->query("SELECT se.*,te.code AS tc,te.idtype_examen AS ite FROM session_examen se JOIN type_examen te ON se.idtype_examen=te.idtype_examen WHERE se.id_session=$id")->fetch_assoc();
    }

    /* ── Mettre à jour candidats (FORM + autres) ── */
    if ($action==='update_cands') {
        $sel_ids = array_map('intval', $_POST['cands_garder']??[]); /* Actifs conservés */
        $add_ids = array_map('intval', $_POST['cands_ajouter']??[]); /* Nouveaux à ajouter */
        $final_ids = array_unique(array_filter(array_merge($sel_ids, $add_ids)));

        /* Désactiver tout puis réactiver les sélectionnés */
        $conn->query("UPDATE candidat_session SET habilite=0 WHERE id_session=$id");

        foreach ($final_ids as $idcand) {
            if (!$idcand) continue;
            $chk=$conn->query("SELECT id FROM candidat_session WHERE idcandidat=$idcand AND id_session=$id LIMIT 1");
            if ($chk->num_rows>0)
                $conn->query("UPDATE candidat_session SET habilite=1 WHERE idcandidat=$idcand AND id_session=$id");
            else
                $conn->query("INSERT INTO candidat_session (idcandidat,id_session,habilite) VALUES ($idcand,$id,1)");

            /* Déterminer si nouveau ou existant */
            $est_nouveau = !in_array($idcand, $ids_actifs);
            $inf=$conn->query("SELECT c.code_acces, s.nomstagiaire, s.prenomstagiaire FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire WHERE c.idcandidat=$idcand")->fetch_assoc();
            if (!$inf) continue;

            if ($est_nouveau) {
                /* Nouveau candidat → générer nouveau mdp et l'afficher */
                $mdp_plain = genMdp(9);
                $mdp_hash  = password_hash($mdp_plain, PASSWORD_DEFAULT);
                $conn->query("UPDATE candidat SET mot_de_passe='$mdp_hash', tentatives=0, is_logged_in=0, bloque=0 WHERE idcandidat=$idcand");
                $export_rows[]=['nom'=>$inf['nomstagiaire'].' '.$inf['prenomstagiaire'],'code'=>$inf['code_acces'],'mdp'=>$mdp_plain,'nouveau'=>true];
            } else {
                /* Candidat existant → afficher son mot de passe actuel (réinitialiser si demandé) */
                if (isset($_POST['reset_mdp_'.  $idcand])) {
                    $mdp_plain = genMdp(9);
                    $mdp_hash  = password_hash($mdp_plain, PASSWORD_DEFAULT);
                    $conn->query("UPDATE candidat SET mot_de_passe='$mdp_hash', tentatives=0, is_logged_in=0, bloque=0 WHERE idcandidat=$idcand");
                    $export_rows[]=['nom'=>$inf['nomstagiaire'].' '.$inf['prenomstagiaire'],'code'=>$inf['code_acces'],'mdp'=>$mdp_plain,'nouveau'=>false];
                }
            }
        }

        /* Recharger candidats actifs */
        $ca2=$conn->query("SELECT c.idcandidat,c.code_acces,s.nomstagiaire,s.prenomstagiaire FROM candidat_session cs JOIN candidat c ON cs.idcandidat=c.idcandidat JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire WHERE cs.id_session=$id AND cs.habilite=1 ORDER BY s.nomstagiaire,s.prenomstagiaire");
        $cands_actifs_arr=[]; while($r=$ca2->fetch_assoc()) $cands_actifs_arr[]=$r;
        $ids_actifs=array_column($cands_actifs_arr,'idcandidat');
        $msg = !empty($export_rows) ? 'cands_ok' : 'cands_ok_no_export';
    }
}
$active_page='sessions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Modifier session #<?= $id ?> — EXASUR</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.cand-tbl{width:100%;border-collapse:collapse;font-size:.84rem;}
.cand-tbl th{background:var(--blue);color:#fff;padding:8px 12px;font-weight:700;font-size:.78rem;text-align:left;}
.cand-tbl td{padding:8px 12px;border-bottom:1px solid #f0f4fa;vertical-align:middle;}
.cand-tbl tr.sel td{background:#e8f0fe;}
.cand-tbl tr:hover td{background:#f8faff;}
.cand-chk{width:17px;height:17px;accent-color:var(--blue);cursor:pointer;}
.code-b{background:var(--blue);color:#fff;padding:3px 10px;border-radius:50px;font-weight:800;font-size:.8rem;font-family:monospace;}
.mdp-b{background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-weight:800;font-family:monospace;}
.mdp-b.new{background:#fef3c7;color:#92400e;}
.exp-tbl{width:100%;border-collapse:collapse;font-size:.88rem;}
.exp-tbl th{background:var(--blue);color:#fff;padding:10px 14px;font-weight:700;text-align:left;}
.exp-tbl td{padding:9px 14px;border-bottom:1px solid #e5e7eb;}
.exp-tbl tr:nth-child(even) td{background:#f8faff;}
.section-sep{background:#f8faff;border-left:4px solid var(--gold);border-radius:0 10px 10px 0;padding:10px 16px;margin-bottom:16px;font-weight:700;color:var(--blue);font-size:.9rem;}
@media print{body *{visibility:hidden!important;}#printZone,#printZone *{visibility:visible!important;}#printZone{position:fixed;top:0;left:0;width:100%;padding:20px;background:#fff;}}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title">
        <i class="fas fa-edit me-2"></i>Modifier session
        <span style="background:var(--gold);color:var(--blue);padding:2px 10px;border-radius:20px;font-size:.78rem;margin-left:8px;">#<?= $id ?></span>
        <span class="tp tp-<?= $se['tc'] ?> ms-2"><?= $se['tc'] ?></span>
    </div>
    <div class="topbar-breadcrumb">
        <a href="dashboard.php">Accueil</a><i class="fas fa-chevron-right" style="font-size:.65rem"></i>
        <a href="sessions.php">Sessions</a><i class="fas fa-chevron-right" style="font-size:.65rem"></i><span>Modifier</span>
    </div>
    <div class="ms-auto"><span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span></div>
</div>
<div class="admin-content">

<?php /* ── Tableau export mots de passe (seulement si nouveaux candidats) ── */
if (!empty($export_rows)): ?>
<div id="printZone">
<div class="card-admin mb-4" style="border-left:4px solid #16a34a;">
    <div class="card-admin-header" style="background:linear-gradient(135deg,#03224c,#0a3a6b);">
        <i class="fas fa-key me-2" style="color:var(--gold)"></i>
        <h5 style="color:#fff;">Codes &amp; mots de passe — À distribuer</h5>
        <div class="ms-auto d-flex gap-2">
            <button onclick="copierTableau()" style="background:var(--gold);color:var(--blue);border:none;border-radius:50px;padding:5px 14px;font-weight:700;font-size:.78rem;cursor:pointer;">
                <i class="fas fa-copy me-1"></i>Copier Word
            </button>
            <button onclick="window.print()" style="background:#fff;color:var(--blue);border:1px solid #e0e7f0;border-radius:50px;padding:5px 14px;font-weight:700;font-size:.78rem;cursor:pointer;">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
        </div>
    </div>
    <div class="card-admin-body p-0">
        <div style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;padding:10px 18px;font-size:.82rem;color:#166534;">
            <i class="fas fa-info-circle me-1"></i>
            <span style="color:#f59e0b;font-weight:700;">🟡 Nouveau</span> = nouveau mot de passe généré |
            <span style="color:#16a34a;font-weight:700;">🟢 Existant</span> = mot de passe réinitialisé à la demande
        </div>
        <div style="padding:16px;overflow-x:auto;">
            <table class="exp-tbl" id="exportTable">
                <thead><tr><th>#</th><th>Nom &amp; Prénom</th><th>Code d'accès</th><th>Mot de passe</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach($export_rows as $i=>$r): ?>
                <tr>
                    <td style="color:#9ca3af;"><?= $i+1 ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($r['nom']) ?></td>
                    <td><span class="code-b"><?= htmlspecialchars($r['code']) ?></span></td>
                    <td><span class="mdp-b <?= $r['nouveau']?'new':'' ?>"><?= htmlspecialchars($r['mdp']) ?></span></td>
                    <td>
                        <?php if($r['nouveau']): ?>
                        <span style="font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:50px;font-weight:700;">🟡 Nouveau</span>
                        <?php else: ?>
                        <span style="font-size:.72rem;background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:50px;font-weight:700;">🟢 Réinitialisé</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<!-- ① Modifier infos session -->
<div class="card-admin mb-4">
    <div class="card-admin-header"><i class="fas fa-edit me-2" style="color:var(--gold)"></i><h5>Informations de la session</h5></div>
    <div class="card-admin-body">
        <form method="POST">
            <input type="hidden" name="action" value="edit_session">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label-admin">Nom de la session *</label>
                    <input type="text" name="nom_session" class="form-control-admin" required value="<?= htmlspecialchars($se['nom_session']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Statut</label>
                    <select name="statut" class="form-select-admin">
                        <?php foreach(['planifiee'=>'Planifiée','en_cours'=>'En cours','terminee'=>'Terminée','annulee'=>'Annulée'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $se['statut']==$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Type d'examen *</label>
                    <select name="idtype_examen" class="form-select-admin s2" required>
                        <?php foreach($types_arr as $t): ?>
                        <option value="<?= $t['idtype_examen'] ?>" <?= $t['idtype_examen']==$se['idtype_examen']?'selected':'' ?>>
                            <?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Type épreuve</label>
                    <select name="type_session" class="form-select-admin">
                        <option value="normal" <?= $se['type_session']==='normal'?'selected':'' ?>>Normal</option>
                        <option value="theorie" <?= $se['type_session']==='theorie'?'selected':'' ?>>Théorie (IF)</option>
                        <option value="pratique" <?= $se['type_session']==='pratique'?'selected':'' ?>>Pratique (IF)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Cours / Formation</label>
                    <select name="idtypeformation" class="form-select-admin s2">
                        <option value="">— Sans formation —</option>
                        <?php if($formations){$formations->data_seek(0);while($f=$formations->fetch_assoc()): ?>
                        <option value="<?= $f['idtypeforma'] ?>" <?= $se['idtypeformation']==$f['idtypeforma']?'selected':'' ?>><?= htmlspecialchars($f['nomforma']) ?></option>
                        <?php endwhile;} ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-admin">Module évalué</label>
                    <select name="idmodule" class="form-select-admin s2">
                        <option value="">— Sans module —</option>
                        <?php foreach($mods_data as $itf=>$mods): foreach($mods as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $se['idmodule']==$m['id']?'selected':'' ?>>Module <?= $m['num'] ?> — <?= htmlspecialchars($m['nom']) ?></option>
                        <?php endforeach;endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-admin">Date début *</label>
                    <input type="date" name="date_debut" class="form-control-admin" required value="<?= $se['date_debut'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-admin">Date fin *</label>
                    <input type="date" name="date_fin" class="form-control-admin" required value="<?= $se['date_fin'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-admin">Durée (min)</label>
                    <input type="number" name="duree_minutes" class="form-control-admin" value="<?= $se['duree_minutes'] ?>" min="10">
                </div>
            </div>
            <div class="d-flex gap-3 mt-4">
                <button type="submit" class="btn-gold"><i class="fas fa-save me-2"></i>Enregistrer</button>
                <a href="sessions.php" class="btn-anac" style="background:white;color:var(--blue);"><i class="fas fa-arrow-left me-2"></i>Retour</a>
            </div>
        </form>
    </div>
</div>

<!-- ② Candidats (TOUS types : voir + gérer) -->
<div class="card-admin">
    <div class="card-admin-header">
        <i class="fas fa-users me-2" style="color:var(--gold)"></i>
        <h5>Candidats de la session</h5>
        <span class="badge-count ms-2"><?= count($cands_actifs_arr) ?></span>
        <?php if($is_form): ?>
        <span style="font-size:.72rem;background:#fce7f3;color:#9d174d;padding:2px 9px;border-radius:20px;font-weight:700;margin-left:8px;">FORM — Gestion complète</span>
        <?php endif; ?>
    </div>
    <div class="card-admin-body">
        <form method="POST" id="candForm">
            <input type="hidden" name="action" value="update_cands">

            <?php if($is_form && !empty($tous_session_arr)): ?>
            <!-- Explication FORM -->
            <div style="background:#fce7f3;border:1px solid #f9a8d4;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:#831843;">
                <i class="fas fa-info-circle me-1"></i>
                Les candidats de la même formation sont affichés. Cochez ceux qui participent à cette évaluation.
                Les <strong>nouveaux</strong> recevront un nouveau mot de passe affiché ci-dessus après enregistrement.
            </div>
            <?php endif; ?>

            <!-- Section A : Candidats actifs (cochés = gardés) -->
            <div class="section-sep">
                <i class="fas fa-user-check me-1" style="color:var(--gold);"></i>
                Candidats actuellement inscrits (<?= count($cands_actifs_arr) ?>) — Décochez pour retirer
            </div>
            <?php if(empty($cands_actifs_arr)): ?>
            <div style="text-align:center;color:#9ca3af;padding:14px;font-size:.83rem;margin-bottom:14px;">
                <i class="fas fa-user-slash me-1"></i>Aucun candidat inscrit.
            </div>
            <?php else: ?>
            <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;flex-wrap:wrap;">
                <span id="cntA" style="font-size:.82rem;font-weight:700;color:var(--blue);"><?= count($cands_actifs_arr) ?> inscrit(s)</span>
                <button type="button" onclick="selAllA(true)"  style="font-size:.74rem;padding:3px 10px;border:1px solid var(--blue);border-radius:6px;background:transparent;color:var(--blue);cursor:pointer;">Tous</button>
                <button type="button" onclick="selAllA(false)" style="font-size:.74rem;padding:3px 10px;border:1px solid #dc2626;border-radius:6px;background:transparent;color:#dc2626;cursor:pointer;">Aucun</button>
            </div>
            <div style="border:1.5px solid #e0e7f0;border-radius:12px;overflow:hidden;max-height:240px;overflow-y:auto;margin-bottom:20px;">
                <table class="cand-tbl">
                    <thead><tr><th width="36">✓</th><th>#</th><th>Nom &amp; Prénom</th><th>Code accès</th><th>Reset mdp ?</th></tr></thead>
                    <tbody>
                    <?php foreach($cands_actifs_arr as $i=>$c): ?>
                    <tr class="sel" id="rowA_<?= $c['idcandidat'] ?>">
                        <td><input type="checkbox" class="cand-chk chkA" name="cands_garder[]" value="<?= $c['idcandidat'] ?>" checked></td>
                        <td style="color:#9ca3af;font-size:.78rem;"><?= $i+1 ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></td>
                        <td><span class="code-b"><?= htmlspecialchars($c['code_acces']) ?></span></td>
                        <td>
                            <label style="display:flex;align-items:center;gap:5px;font-size:.75rem;cursor:pointer;color:#9ca3af;">
                                <input type="checkbox" name="reset_mdp_<?= $c['idcandidat'] ?>" value="1" style="width:14px;height:14px;accent-color:var(--blue);">
                                Réinitialiser
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Section B : Ajouter des candidats -->
            <div class="section-sep">
                <i class="fas fa-user-plus me-1" style="color:var(--gold);"></i>
                <?php if($is_form && !empty($tous_session_arr)): ?>
                Candidats de la même formation (non encore inscrits) — cochez pour ajouter
                <?php else: ?>
                Ajouter d'autres candidats
                <?php endif; ?>
            </div>

            <?php if($is_form && !empty($tous_session_arr)):
                /* FORM : afficher candidats de la même formation pas encore dans cette session */
                $ids_non_inscrits = array_filter($tous_session_arr, fn($c)=>!in_array($c['idcandidat'],$ids_actifs));
                if(!empty($ids_non_inscrits)): ?>
            <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                <span id="cntB" style="font-size:.82rem;font-weight:700;color:#16a34a;">0 à ajouter</span>
                <button type="button" onclick="selAllB(true)"  style="font-size:.74rem;padding:3px 10px;border:1px solid #16a34a;border-radius:6px;background:transparent;color:#16a34a;cursor:pointer;">Tous</button>
                <button type="button" onclick="selAllB(false)" style="font-size:.74rem;padding:3px 10px;border:1px solid #dc2626;border-radius:6px;background:transparent;color:#dc2626;cursor:pointer;">Aucun</button>
            </div>
            <div style="border:1.5px solid #bbf7d0;border-radius:12px;overflow:hidden;max-height:200px;overflow-y:auto;margin-bottom:14px;">
                <table class="cand-tbl">
                    <thead><tr><th width="36">+</th><th>#</th><th>Nom &amp; Prénom</th><th>Code accès</th></tr></thead>
                    <tbody>
                    <?php foreach(array_values($ids_non_inscrits) as $i=>$c): ?>
                    <tr id="rowB_<?= $c['idcandidat'] ?>">
                        <td><input type="checkbox" class="cand-chk chkB" name="cands_ajouter[]" value="<?= $c['idcandidat'] ?>"></td>
                        <td style="color:#9ca3af;font-size:.78rem;"><?= $i+1 ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></td>
                        <td><span class="code-b"><?= htmlspecialchars($c['code_acces']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="font-size:.82rem;color:#9ca3af;padding:10px;margin-bottom:10px;">Tous les candidats de cette formation sont déjà inscrits.</p>
            <?php endif; else: ?>
            <!-- Autres types : Select2 multiple pour ajouter n'importe qui -->
            <select name="cands_ajouter[]" multiple class="form-select-admin s2multi" style="width:100%;min-height:50px;margin-bottom:14px;">
                <?php foreach($tous_bdd_arr as $c):
                    if(in_array($c['idcandidat'],$ids_actifs)) continue; ?>
                <option value="<?= $c['idcandidat'] ?>">
                    <?= htmlspecialchars($c['code_acces'].' — '.$c['nomstagiaire'].' '.$c['prenomstagiaire']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <div class="d-flex gap-3 mt-3">
                <button type="button" class="btn-gold" onclick="validerCands()">
                    <i class="fas fa-users-cog me-2"></i>Mettre à jour les candidats
                </button>
                <a href="sessions.php" class="btn-anac" style="background:white;color:var(--blue);">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
        </form>
    </div>
</div>

</div></main></div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));
$('.s2').select2({width:'100%',placeholder:'-- Sélectionner --',allowClear:true});
$('.s2multi').select2({width:'100%',placeholder:'Rechercher un candidat par nom ou code...',allowClear:true});

/* Candidats A (actifs) */
function selAllA(v){document.querySelectorAll('.chkA').forEach(c=>{c.checked=v;c.closest('tr').classList.toggle('sel',v);});updateCntA();}
function updateCntA(){const n=document.querySelectorAll('.chkA:checked').length;const el=document.getElementById('cntA');if(el)el.textContent=n+' inscrit(s) conservé(s)';}
document.querySelectorAll('.chkA').forEach(c=>c.addEventListener('change',function(){this.closest('tr').classList.toggle('sel',this.checked);updateCntA();}));

/* Candidats B (à ajouter) */
function selAllB(v){document.querySelectorAll('.chkB').forEach(c=>{c.checked=v;c.closest('tr').classList.toggle('sel',v);});updateCntB();}
function updateCntB(){const n=document.querySelectorAll('.chkB:checked').length;const el=document.getElementById('cntB');if(el)el.textContent=n+' à ajouter';}
document.querySelectorAll('.chkB').forEach(c=>c.addEventListener('change',function(){this.closest('tr').classList.toggle('sel',this.checked);updateCntB();}));

/* Valider */
function validerCands(){
    const a=document.querySelectorAll('.chkA:checked').length;
    const b=document.querySelectorAll('.chkB:checked').length;
    const adds=<?= json_encode(!$is_form) ?> ? ($('.s2multi').val()||[]).length : b;
    const total=a+adds;
    Swal.fire({
        title:'Confirmer la mise à jour ?',
        html:`<div style="font-family:Candara,sans-serif;text-align:left;">
            <p>Candidats conservés : <strong>${a}</strong></p>
            <p>Candidats ajoutés : <strong>${adds}</strong></p>
            <p style="margin-top:8px;color:#03224c;font-weight:700;border-top:1px solid #e5e7eb;padding-top:8px;">Total : ${total} candidat(s)</p>
        </div>`,
        icon:'question',showCancelButton:true,
        confirmButtonText:'<i class="fas fa-save me-1"></i>Confirmer',
        cancelButtonText:'Annuler',
        confirmButtonColor:'#03224c',cancelButtonColor:'#6b7280'
    }).then(r=>{if(r.isConfirmed) document.getElementById('candForm').submit();});
}

/* Copier tableau */
function copierTableau(){
    const tbl=document.getElementById('exportTable');if(!tbl)return;
    const r=document.createRange();r.selectNode(tbl);
    const s=window.getSelection();s.removeAllRanges();s.addRange(r);
    try{document.execCommand('copy');Swal.fire({title:'📋 Copié !',text:'Collez dans Word avec Ctrl+V',icon:'success',timer:2000,showConfirmButton:false,toast:true,position:'top-end'});}catch(e){}
    window.getSelection().removeAllRanges();
}

/* Notifications */
<?php if($msg==='edit_ok'): ?>
Swal.fire({title:'✅ Session modifiée',icon:'success',timer:2000,showConfirmButton:false,toast:true,position:'top-end'});
<?php elseif($msg==='cands_ok'): ?>
Swal.fire({title:'✅ Candidats mis à jour',text:'Tableau des accès affiché ci-dessus.',icon:'success',timer:2500,showConfirmButton:false,toast:true,position:'top-end'});
setTimeout(()=>document.getElementById('printZone')?.scrollIntoView({behavior:'smooth'}),300);
<?php elseif($msg==='cands_ok_no_export'): ?>
Swal.fire({title:'✅ Candidats mis à jour',text:'Aucun nouveau candidat ajouté — pas de tableau d\'accès.',icon:'success',timer:2500,showConfirmButton:false,toast:true,position:'top-end'});
<?php elseif($msg && !in_array($msg,['edit_ok','cands_ok','cands_ok_no_export'])): ?>
Swal.fire({title:'Erreur',text:<?= json_encode($msg) ?>,icon:'error',confirmButtonColor:'#dc2626'});
<?php endif; ?>
</script>
</body></html>
<?php $conn->close(); ?>