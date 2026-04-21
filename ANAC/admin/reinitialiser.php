<?php
/**
 * reinitialiser.php — Réinitialiser un examen candidat
 * admin/reinitialiser.php
 *
 * 2 MODES :
 * ① Continuer (défaut) : conserve réponses, reprend à la question suivante
 * ② Complet : supprime tout → repartir de zéro
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $idcandidat   = intval($_POST['idcandidat']   ?? 0);
    $id_session   = intval($_POST['id_session']   ?? 0);
    $mode_complet = (($_POST['mode_complet'] ?? '0') === '1');

    if ($idcandidat && $id_session) {
        $cn = $conn->query("SELECT s.nomstagiaire,s.prenomstagiaire FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire WHERE c.idcandidat=$idcandidat")->fetch_assoc();
        $sn = $conn->query("SELECT nom_session FROM session_examen WHERE id_session=$id_session")->fetch_assoc();
        $nom = ($cn['nomstagiaire']??'').' '.($cn['prenomstagiaire']??'');
        $ses = $sn['nom_session'] ?? '';

        if ($mode_complet) {
            /* MODE COMPLET — tout supprimer */
            $conn->query("DELETE FROM resultats WHERE idcandidat=$idcandidat AND id_session=$id_session");
            $conn->query("DELETE FROM reponses_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session");
            $conn->query("DELETE FROM progression_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session");
            $conn->query("UPDATE candidat SET is_logged_in=0, tentatives=0, bloque=0 WHERE idcandidat=$idcandidat");
            $msg = 'complet:'.htmlspecialchars($nom).'|'.htmlspecialchars($ses);
        } else {
            /* MODE CONTINUER — conserver réponses, réinitialiser uniquement le résultat final */

            /* Compter réponses déjà données → le candidat reprendra à cette question */
            $nb_rep = intval($conn->query("SELECT COUNT(*) FROM reponses_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session AND selected_option IS NOT NULL")->fetch_row()[0] ?? 0);

            /* Mettre à jour ou créer la progression */
            $chk = $conn->query("SELECT id FROM progression_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session LIMIT 1");
            if ($chk && $chk->num_rows > 0) {
                $upd = $conn->prepare("UPDATE progression_candidat SET current_index_theo=?,current_index_pra=?,updated_at=NOW() WHERE idcandidat=? AND id_session=?");
                if ($upd) { $upd->bind_param("iiii",$nb_rep,$nb_rep,$idcandidat,$id_session); $upd->execute(); $upd->close(); }
            } else {
                $ins = $conn->prepare("INSERT INTO progression_candidat (idcandidat,id_session,current_index_theo,current_index_pra,infractions) VALUES (?,?,?,?,0)");
                if ($ins) { $ins->bind_param("iiii",$idcandidat,$id_session,$nb_rep,$nb_rep); $ins->execute(); $ins->close(); }
            }

            /* Supprimer résultat final uniquement (pour autoriser re-soumission) */
            $conn->query("DELETE FROM resultats WHERE idcandidat=$idcandidat AND id_session=$id_session");

            /* Déverrouiller le compte */
            $conn->query("UPDATE candidat SET is_logged_in=0, tentatives=0, bloque=0 WHERE idcandidat=$idcandidat");

            $msg = 'ok:'.htmlspecialchars($nom).'|'.htmlspecialchars($ses).'|'.$nb_rep;
        }
    }
}

$candidats = $conn->query("SELECT c.idcandidat,c.code_acces,s.nomstagiaire,s.prenomstagiaire FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire ORDER BY s.nomstagiaire,s.prenomstagiaire");
$active_page = 'reinitialiser';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Réinitialiser — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.mode-card{border:2px solid #e0e7f0;border-radius:14px;padding:18px 20px;cursor:pointer;transition:all .25s;background:#fff;margin-bottom:0;}
.mode-card:hover{border-color:var(--blue);background:#f8faff;}
.mode-card.sel{border-color:var(--blue);background:var(--blue-light);box-shadow:0 0 0 3px rgba(3,34,76,.08);}
.mode-card.danger:hover,.mode-card.danger.sel{border-color:#dc2626;background:#fff1f2;box-shadow:0 0 0 3px rgba(220,38,38,.08);}
.mode-title{font-weight:800;font-size:.95rem;display:flex;align-items:center;gap:8px;margin-bottom:6px;}
.mode-desc{font-size:.82rem;color:#6b7280;line-height:1.5;}
.mode-tag{font-size:.7rem;padding:2px 8px;border-radius:50px;font-weight:700;}
.sess-info-bar{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;font-size:.82rem;color:#166534;margin-top:7px;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-undo-alt me-2"></i>Réinitialiser un examen</div>
    <div class="ms-auto">
        <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']??'') ?></span>
    </div>
</div>

<div class="admin-content">

<!-- Explication -->
<div class="card-admin mb-4" style="border-left:4px solid var(--gold)">
    <div class="card-admin-body">
        <div class="d-flex align-items-start gap-3">
            <div style="width:46px;height:46px;background:#f9f0c4;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-info-circle fa-xl" style="color:var(--gold)"></i>
            </div>
            <div class="row g-3" style="flex:1;">
                <div class="col-md-6">
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;">
                        <div style="font-weight:700;color:#16a34a;margin-bottom:6px;font-size:.9rem;">
                            <i class="fas fa-redo me-1"></i>Continuer l'examen
                        </div>
                        <ul style="padding-left:15px;font-size:.82rem;color:#374151;margin:0;line-height:1.8;">
                            <li>✅ Conserve les réponses déjà données</li>
                            <li>✅ Reprend à la question suivante</li>
                            <li>🔓 Déverrouille le compte</li>
                            <li><em>Idéal pour un problème technique</em></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="background:#fff1f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">
                        <div style="font-weight:700;color:#dc2626;margin-bottom:6px;font-size:.9rem;">
                            <i class="fas fa-trash me-1"></i>Repartir de zéro
                        </div>
                        <ul style="padding-left:15px;font-size:.82rem;color:#374151;margin:0;line-height:1.8;">
                            <li>❌ Supprime toutes les réponses</li>
                            <li>❌ Supprime résultats et progression</li>
                            <li>⚠️ Action irréversible</li>
                            <li><em>À utiliser avec précaution</em></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire -->
<div class="card-admin">
    <div class="card-admin-header">
        <i class="fas fa-undo-alt me-2" style="color:var(--gold)"></i>
        <h5>Formulaire de réinitialisation</h5>
    </div>
    <div class="card-admin-body">
        <form method="POST" id="reinitForm">
            <input type="hidden" name="mode_complet" id="modeCompletInput" value="0">

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label-admin">
                        <i class="fas fa-user me-2" style="color:var(--gold)"></i>Candidat *
                    </label>
                    <select name="idcandidat" id="selCand" class="form-select-admin select2-main" required>
                        <option value="">-- Choisir un candidat --</option>
                        <?php while($c=$candidats->fetch_assoc()): ?>
                        <option value="<?= $c['idcandidat'] ?>">
                            <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire'].' ('.$c['code_acces'].')') ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-admin">
                        <i class="fas fa-calendar me-2" style="color:var(--gold)"></i>Session d'examen *
                    </label>
                    <select name="id_session" id="selSess" class="form-select-admin select2-main" required>
                        <option value="">-- Sélectionner d'abord un candidat --</option>
                    </select>
                    <div id="sessInfo" class="sess-info-bar" style="display:none;">
                        <i class="fas fa-info-circle me-1"></i>
                        <span id="sessInfoTxt"></span>
                    </div>
                </div>
            </div>

            <!-- 2 BOUTONS DIRECTS — plus de cases à cocher -->
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:24px;justify-content:center;">

                <!-- Bouton Continuer -->
                <button type="button" class="btn-anac"
                        style="padding:14px 36px;font-size:.95rem;font-weight:700;
                               background:#16a34a;color:#fff;border:none;border-radius:50px;
                               transition:all .3s;display:flex;align-items:center;gap:10px;"
                        onclick="confirmReinit(0)">
                    <i class="fas fa-redo fa-lg"></i>
                    <div style="text-align:left;">
                        <div>Continuer l'examen</div>
                        <div style="font-size:.76rem;opacity:.85;font-weight:400;">Conserve les réponses déjà données</div>
                    </div>
                </button>

                <!-- Bouton Repartir de zéro -->
                <button type="button"
                        style="padding:14px 36px;font-size:.95rem;font-weight:700;
                               background:#dc2626;color:#fff;border:none;border-radius:50px;
                               transition:all .3s;display:flex;align-items:center;gap:10px;cursor:pointer;"
                        onclick="confirmReinit(1)">
                    <i class="fas fa-trash fa-lg"></i>
                    <div style="text-align:left;">
                        <div>Repartir de zéro</div>
                        <div style="font-size:.76rem;opacity:.85;font-weight:400;">Supprime tout — irréversible</div>
                    </div>
                </button>

            </div>
        </form>
    </div>
</div>

</div>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));
$('.select2-main').select2({width:'100%',placeholder:'Sélectionner...',allowClear:true});

let modeComplet=0;

/* Charger sessions via AJAX */
$('#selCand').on('change',function(){
    const id=$(this).val();
    $('#selSess').empty().append('<option value="">Chargement...</option>');
    document.getElementById('sessInfo').style.display='none';
    if(!id){$('#selSess').empty().append('<option value="">-- Sélectionner un candidat --</option>');return;}
    $.post('get_sessions_by_candidat.php',{idcandidat:id},function(d){
        $('#selSess').empty().append('<option value="">-- Sélectionner une session --</option>');
        if(!d||!d.length){$('#selSess').append('<option disabled>Aucune session trouvée</option>');return;}
        d.forEach(s=>$('#selSess').append(`<option value="${s.id_session}">${s.nom_session}</option>`));
        $('#selSess').trigger('change.select2');
    },'json');
});

/* Infos nb réponses */
$('#selSess').on('change',function(){
    const cid=$('#selCand').val(), sid=$(this).val();
    if(!cid||!sid){document.getElementById('sessInfo').style.display='none';return;}
    $.post('get_session_info.php',{idcandidat:cid,id_session:sid},function(d){
        if(d && d.nb_reponses!==undefined){
            const nb=parseInt(d.nb_reponses);
            let txt=`<strong>${nb}</strong> réponse(s) déjà enregistrée(s).`;
            if(nb>0) txt+=` En mode "Continuer", le candidat reprendra à la <strong>Q.${nb+1}</strong>.`;
            document.getElementById('sessInfoTxt').innerHTML=txt;
            document.getElementById('sessInfo').style.display='block';
        }
    },'json');
});

function confirmReinit(mode){
    const c=document.getElementById('selCand'), s=document.getElementById('selSess');
    if(!c.value||!s.value){Swal.fire('Champs manquants','Sélectionnez un candidat et une session.','warning');return;}
    const cn=c.options[c.selectedIndex].text, sn=s.options[s.selectedIndex].text;
    const isCmplt=(mode===1);
    document.getElementById('modeCompletInput').value = isCmplt ? '1' : '0';
    Swal.fire({
        title:isCmplt?'⚠️ Repartir de zéro ?':'🔄 Continuer l\'examen ?',
        html:`<div style="font-family:Candara,sans-serif;text-align:left;">
            <div style="background:#f4f7fc;border-radius:12px;padding:12px 14px;margin-bottom:10px;">
                <p><i class="fas fa-user me-1" style="color:var(--gold);"></i><strong>${cn}</strong></p>
                <p style="margin-top:4px;font-size:.87rem;color:#6b7280;">${sn}</p>
            </div>
            ${isCmplt
                ?'<p style="color:#dc2626;font-size:.88rem;"><i class="fas fa-exclamation-triangle me-1"></i><strong>Attention :</strong> Toutes les réponses seront supprimées définitivement. Le candidat recommencera depuis Q.1.</p>'
                :'<p style="color:#16a34a;font-size:.88rem;"><i class="fas fa-check-circle me-1"></i>Les réponses déjà données sont conservées. Le candidat pourra continuer son examen.</p>'
            }</div>`,
        icon:isCmplt?'warning':'question',showCancelButton:true,
        confirmButtonText:isCmplt?'<i class="fas fa-trash me-1"></i>Oui, supprimer tout':'<i class="fas fa-redo me-1"></i>Oui, continuer',
        cancelButtonText:'<i class="fas fa-times me-1"></i>Annuler',
        confirmButtonColor:isCmplt?'#dc2626':'#16a34a',cancelButtonColor:'#6b7280'
    }).then(r=>{if(r.isConfirmed)document.getElementById('reinitForm').submit();});
}

/* Notifications */
<?php if(str_starts_with($msg,'ok:')): $p=explode('|',substr($msg,3)); ?>
Swal.fire({
    title:'✅ Réinitialisation effectuée',
    html:`<div style="font-family:Candara,sans-serif;">
        <p><strong><?= htmlspecialchars($p[0]??'') ?></strong></p>
        <p style="color:#6b7280;font-size:.87rem;margin-top:4px;"><?= htmlspecialchars($p[1]??'') ?></p>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 14px;margin-top:12px;">
            <p style="color:#16a34a;font-size:.88rem;"><i class="fas fa-check-circle me-1"></i>
            Le candidat peut reprendre son examen.</p>
        </div>
    </div>`,
    icon:'success',confirmButtonColor:'#03224c'
});
<?php elseif(str_starts_with($msg,'complet:')): $p=explode('|',substr($msg,8)); ?>
Swal.fire({
    title:'🗑 Examen remis à zéro',
    html:`<div style="font-family:Candara,sans-serif;">
        <p><strong><?= htmlspecialchars($p[0]??'') ?></strong></p>
        <p style="color:#6b7280;font-size:.87rem;margin-top:4px;"><?= htmlspecialchars($p[1]??'') ?></p>
        <p style="color:#dc2626;margin-top:10px;font-size:.85rem;">
            <i class="fas fa-info-circle me-1"></i>Toutes les données ont été supprimées. Le candidat recommence depuis le début.
        </p>
    </div>`,
    icon:'success',confirmButtonColor:'#03224c'
});
<?php endif; ?>
</script>
</body></html>
<?php $conn->close(); ?>