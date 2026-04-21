<?php
/**
 * resultat.php — VERSION DÉFINITIVE
 * Gère : AS, IF théorie, IF pratique, SENS, INST, FORM final
 */
include '../php/db_connection.php';
include '../lang/lang_loader.php';
if (session_status()===PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['resultat'])) {
    if (isset($_SESSION['idcandidat'],$_SESSION['id_session'])) {
        $ic=intval($_SESSION['idcandidat']); $is=intval($_SESSION['id_session']);
        $sq=$conn->prepare("SELECT r.*,c.code_acces,s.nomstagiaire,s.prenomstagiaire,te.code AS type_code,te.nom_fr AS type_nom FROM resultats r JOIN candidat c ON r.idcandidat=c.idcandidat JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire JOIN type_examen te ON r.idtype_examen=te.idtype_examen WHERE r.idcandidat=? AND r.id_session=? ORDER BY r.id DESC LIMIT 1");
        if($sq){$sq->bind_param("ii",$ic,$is);$sq->execute();$row=$sq->get_result()->fetch_assoc();$sq->close();
            if($row){$ns=floatval($row['note_sur'])?:100;$_SESSION['resultat']=['nom'=>$row['nomstagiaire'].' '.$row['prenomstagiaire'],'code'=>$row['code_acces'],'nb_bonnes'=>$row['note_finale'],'note_sur'=>$row['note_sur'],'pourcentage'=>round($row['pourcentage'],2),'reussite'=>$row['reussite'],'type_code'=>$row['type_code'],'type_nom'=>$row['type_nom'],'type_session'=>$_SESSION['type_session']??'normal','pct_theo'=>$row['note_theorique']!==null?round(floatval($row['note_theorique'])/$ns*100,1):null,'pct_prat'=>$row['note_pratique']!==null?round(floatval($row['note_pratique'])/$ns*100,1):null,'moyenne_if'=>$row['moyenne_if']??null,'reussite_theo'=>$row['reussite_theo']??null,'reussite_prat'=>$row['reussite_prat']??null];}
            else{header("Location: ../../index.php");exit();}
        }else{header("Location: ../../index.php");exit();}
    }else{header("Location: ../../index.php");exit();}
}

$r=$_SESSION['resultat']; $ic=$_SESSION['idcandidat']??0; $lc=$_SESSION['lang']??'fr';
$hasEval=false;
if($ic){$se=$conn->prepare("SELECT id FROM evaluations WHERE idcandidat=?");if($se){$se->bind_param("i",$ic);$se->execute();$hasEval=($se->get_result()->num_rows>0);$se->close();}}

$tc=$r['type_code']??'AS'; $ts=$r['type_session']??'normal';
$ok=intval($r['reussite']??0); $pct=floatval($r['pourcentage']??0);
$ns=floatval($r['note_sur']??0); $nb=floatval($r['nb_bonnes']??0);
$is_if=($tc==='IF'); $is_form=($tc==='FORM');
$pct_theo=isset($r['pct_theo'])?floatval($r['pct_theo']):null;
$pct_prat=isset($r['pct_prat'])?floatval($r['pct_prat']):null;
$moy_if=isset($r['moyenne_if'])?floatval($r['moyenne_if']):null;
$rt=isset($r['reussite_theo'])?intval($r['reussite_theo']):null;
$rp=isset($r['reussite_prat'])?intval($r['reussite_prat']):null;
$est_th=($is_if&&$ts==='theorie'); $est_pr=($is_if&&$ts==='pratique');
$is_form_final=$r['is_form_final']??false; $det_mods=$r['detail_modules']??[];
$moy_form=$r['moyenne_form']??null; $nb_mods=$r['nb_modules']??0; $seuil_form=$r['seuil']??70;
$col=$est_th?'#D4AF37':($ok?'#28a745':'#dc3545');
$if_json=''; if($est_pr&&$pct_theo!==null&&$pct_prat!==null){$m=$moy_if!==null?$moy_if:round(($pct_theo+$pct_prat)/2,1);$if_json=json_encode(['pt'=>round($pct_theo,1),'pp'=>round($pct_prat,1),'moy'=>round($m,1),'rt'=>$rt,'rp'=>$rp,'rg'=>$ok]);}
$form_json=''; if($is_form_final&&!empty($det_mods)){$form_json=json_encode(['modules'=>$det_mods,'moy'=>round($moy_form,1),'rg'=>$ok,'seuil'=>$seuil_form]);}
?>
<!DOCTYPE html><html lang="<?= $lc ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ANAC — <?= __('resultat_officiel') ?></title>
<link rel="icon" href="../assets/images/LOGOANAC.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--b:#03224c;--g:#D4AF37;}
body{background:linear-gradient(135deg,#f0f4ff,#e8ecf5);font-family:'Nunito','Candara',sans-serif;min-height:100vh;display:flex;align-items:center;padding:20px;}
.wrap{max-width:680px;margin:0 auto;width:100%;}
.logo-box{text-align:center;margin-bottom:20px;}
.logo-box img{max-height:92px;background:#fff;padding:12px 20px;border-radius:18px;box-shadow:0 8px 24px rgba(3,34,76,.18);}
.card-r{border:none;border-radius:26px;box-shadow:0 20px 50px rgba(3,34,76,.22);overflow:hidden;animation:su .5s ease both;}
@keyframes su{from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:none;}}
.hdr{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;text-align:center;padding:22px 26px;border-bottom:4px solid var(--g);}
.hdr h2{margin:0;font-weight:800;font-size:1.5rem;}
.bdy{padding:30px;background:#fff;}
.badge-t{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;padding:7px 20px;border-radius:50px;font-size:.93rem;font-weight:700;display:inline-block;margin-bottom:12px;border:2px solid var(--g);}
.circle{width:180px;height:180px;border-radius:50%;margin:12px auto;display:flex;flex-direction:column;justify-content:center;align-items:center;color:#fff;box-shadow:0 10px 24px rgba(0,0,0,.25);animation:pulse 2.5s infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.04);}}
.cn{font-size:2.8rem;font-weight:900;line-height:1;} .cs{font-size:1rem;opacity:.88;} .cp{font-size:1.4rem;font-weight:800;margin-top:4px;}
.bw{background:#e8ecf5;border-radius:50px;height:12px;overflow:hidden;margin:5px 0;}
.bf{height:100%;border-radius:50px;transition:width .8s ease;}
.tbl{width:100%;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden;font-size:.87rem;margin:14px 0;}
.tbl thead th{background:var(--b);color:var(--g);padding:8px 12px;text-align:center;font-weight:700;}
.tbl thead th:first-child{text-align:left;}
.tbl tbody td{padding:8px 12px;text-align:center;border-bottom:1px solid #eef0f5;vertical-align:middle;}
.tbl tbody td:first-child{text-align:left;}
.tbl .moy td{background:#f4f7fc;font-weight:800;font-size:.9rem;}
.tok{background:#d1fae5;color:#065f46;border-radius:50px;padding:2px 11px;font-weight:700;font-size:.78rem;}
.tfail{background:#fee2e2;color:#b91c1c;border-radius:50px;padding:2px 11px;font-weight:700;font-size:.78rem;}
.ic{background:linear-gradient(135deg,#f8f9fc,#eef0f8);border-radius:15px;padding:16px 20px;margin:16px 0;border-left:5px solid var(--g);}
.ir{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #e0e4ef;font-size:.9rem;}
.ir:last-child{border-bottom:none;} .il{color:#5a6380;font-weight:600;} .iv{font-weight:800;color:var(--b);}
.ag{background:#fffbef;border:1.5px solid #f0d060;border-radius:11px;padding:10px 13px;font-size:.84rem;color:#7a5800;margin:8px 0;}
.as{background:#f0fff4;border:1.5px solid #86efac;border-radius:11px;padding:10px 13px;font-size:.84rem;color:#166534;margin:8px 0;}
.af{background:#fff1f2;border:1.5px solid #fca5a5;border-radius:11px;padding:10px 13px;font-size:.84rem;color:#991b1b;margin:8px 0;}
.ai{background:#eff6ff;border:1.5px solid #93c5fd;border-radius:11px;padding:10px 13px;font-size:.84rem;color:#1e3a8a;margin:8px 0;}
.btn-a{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;border:2px solid var(--g);padding:10px 26px;border-radius:50px;font-weight:700;font-size:.93rem;text-decoration:none;display:inline-block;margin:5px;transition:all .3s;}
.btn-a:hover{transform:translateY(-3px);box-shadow:0 8px 18px rgba(3,34,76,.3);color:#fff;}
.rw{background:#fffaf0;border-radius:15px;padding:16px;margin-top:16px;border:1.5px solid var(--g);}
.rb{border:2px solid transparent;border-radius:50px;padding:8px 18px;margin:4px;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .3s;}
.rb:hover{transform:translateY(-3px);box-shadow:0 5px 12px rgba(0,0,0,.16);}
.rbg{background:linear-gradient(135deg,#28a745,#20c997);color:#fff;} .rby{background:linear-gradient(135deg,#ffc107,#fd7e14);color:#fff;} .rbr{background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;}
.mention{font-size:1.4rem;font-weight:900;margin:6px 0 3px;}
.lsw{position:fixed;top:14px;right:14px;display:flex;gap:7px;z-index:1000;}
.lbtn{padding:5px 14px;border:2px solid var(--g);border-radius:20px;color:#fff;text-decoration:none;font-weight:700;background:var(--b);font-size:.8rem;transition:all .3s;}
.lbtn:hover,.lbtn.active{background:var(--g);color:var(--b);}
</style>
</head>
<body>
<div class="lsw"><a href="?lang=fr" class="lbtn <?= $lc==='fr'?'active':'' ?>">FR</a><a href="?lang=en" class="lbtn <?= $lc==='en'?'active':'' ?>">EN</a></div>
<div class="container"><div class="wrap">
<div class="logo-box"><img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"></div>
<div class="card-r">
<div class="hdr"><h2><i class="fas fa-clipboard-check me-2"></i><?= __('resultat_officiel') ?></h2></div>
<div class="bdy text-center">

<div class="badge-t"><i class="fas fa-tag me-2"></i><?= htmlspecialchars(($r['type_code']??'').' – '.($r['type_nom']??'')) ?></div>

<?php /* Nom session conteneur FORM — affiché comme titre, comme pour les autres types */
$nom_conteneur = $r['nom_conteneur'] ?? '';
if ($is_form_final && $nom_conteneur): ?>
<div style="background:linear-gradient(135deg,#fce7f3,#fdf2f8);border:1.5px solid #f9a8d4;border-radius:10px;padding:8px 16px;display:inline-block;margin-bottom:10px;">
    <div style="font-size:.7rem;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:1px;">Session de formation</div>
    <div style="font-weight:700;color:#831843;font-size:.86rem;"><?= htmlspecialchars($nom_conteneur) ?></div>
</div>
<?php endif; ?>

<h4 class="mb-1"><?= htmlspecialchars($r['nom']??'') ?></h4>
<p class="text-muted mb-3"><i class="fas fa-key me-1"></i><?= __('code') ?> : <strong><?= htmlspecialchars($r['code']??'') ?></strong></p>

<?php if ($est_th): $ins=($ok==0); ?>
<div class="circle mx-auto" style="background:linear-gradient(135deg,#D4AF37,#b8963a);">
<div class="cn"><?= round($nb,1) ?></div><div class="cs">/ <?= round($ns,1) ?> pts</div><div class="cp"><?= round($pct,1) ?>%</div></div>
<div class="bw mt-2 mb-3"><div class="bf" style="width:<?= min($pct,100) ?>%;background:<?= $ins?'#e03a3a':'#D4AF37' ?>;"></div></div>
<div class="mention" style="color:<?= $ins?'#e03a3a':'#19a96b' ?>"><?= $ins?'⚠️ SCORE INSUFFISANT':'✅ THÉORIE VALIDÉE' ?></div>
<?php if($ins): ?><div class="ag text-start mt-3"><i class="fas fa-exclamation-triangle me-2"></i><strong>Score théorique : <?= round($pct,1) ?>%</strong>. La pratique reste <strong>obligatoire</strong>.</div>
<?php else: ?><div class="as text-start mt-3"><i class="fas fa-check-circle me-2"></i><strong>Théorie réussie — <?= round($pct,1) ?>%</strong>. Passez la <strong>pratique</strong> après 15 min de pause.</div><?php endif; ?>
<div class="ai text-start mt-2"><i class="fas fa-info-circle me-2"></i><strong>Règle IF :</strong> Théorie ≥ 80% ET Pratique ≥ 80% ET Moyenne ≥ 80%.</div>

<?php elseif ($est_pr&&$pct_theo!==null&&$pct_prat!==null): ?>
<div class="circle mx-auto" style="background:linear-gradient(135deg,<?= $ok?'#28a745,#20c997':'#dc3545,#c82333' ?>);">
<div class="cn"><?= round($nb,1) ?></div><div class="cs">/ <?= round($ns,1) ?> pts</div><div class="cp"><?= round($pct,1) ?>%</div></div>
<div class="mention mt-2" style="color:<?= $ok?'#28a745':'#dc3545' ?>"><?= $ok?'✅ ADMIS(E) — CERTIFICATION IF':'❌ NON ADMIS(E)' ?></div>
<h6 class="mt-3 mb-2" style="color:var(--b);font-weight:800;"><i class="fas fa-chart-bar me-2" style="color:var(--g);"></i>Récapitulatif certification IF</h6>
<table class="tbl">
<thead><tr><th>Épreuve</th><th>Note</th><th>Score</th><th>Seuil</th><th>Décision</th></tr></thead>
<tbody>
<tr><td><i class="fas fa-book me-1"></i><strong><?= __('partie_theorique') ?></strong></td><td><?= round($pct_theo/100*$ns,1) ?>/<?= round($ns,1) ?>pts</td><td><?= round($pct_theo,1) ?>%<div class="bw"><div class="bf" style="width:<?= min($pct_theo,100) ?>%;background:<?= $rt?'#28a745':'#dc3545' ?>;"></div></div></td><td>≥80%</td><td><?= $rt?'<span class="tok">✓</span>':'<span class="tfail">✗</span>' ?></td></tr>
<tr><td><i class="fas fa-eye me-1"></i><strong><?= __('partie_pratique') ?></strong></td><td><?= round($nb,1) ?>/<?= round($ns,1) ?>pts</td><td><?= round($pct_prat,1) ?>%<div class="bw"><div class="bf" style="width:<?= min($pct_prat,100) ?>%;background:<?= $rp?'#28a745':'#dc3545' ?>;"></div></div></td><td>≥80%</td><td><?= $rp?'<span class="tok">✓</span>':'<span class="tfail">✗</span>' ?></td></tr>
<?php if($moy_if!==null): ?><tr class="moy"><td><i class="fas fa-calculator me-1"></i><strong>MOYENNE IF</strong></td><td colspan="2"><strong><?= round($moy_if,1) ?>%</strong><div class="bw"><div class="bf" style="width:<?= min($moy_if,100) ?>%;background:<?= $ok?'#28a745':'#dc3545' ?>;"></div></div></td><td>≥80%</td><td><?= $ok?'<span class="tok">✓ CERTIFIÉ</span>':'<span class="tfail">✗ RECALÉ</span>' ?></td></tr><?php endif; ?>
</tbody></table>
<?php if(!$ok&&($r['raison_echec']??'')): ?><div class="af text-start"><i class="fas fa-times-circle me-2"></i><strong>Motif :</strong> <?= htmlspecialchars($r['raison_echec']) ?></div><?php endif; ?>

<?php elseif ($is_form_final&&!empty($det_mods)): ?>
<!-- FORM FINAL -->
<div class="circle mx-auto" style="background:linear-gradient(135deg,<?= $ok?'#28a745,#20c997':'#dc3545,#c82333' ?>);">
<div class="cn"><?= round($nb,1) ?></div><div class="cs">pts sur <?= round($ns,1) ?></div><div class="cp"><?= round($pct,1) ?>%</div></div>
<div class="mention mt-2" style="color:<?= $ok?'#28a745':'#dc3545' ?>"><?= $ok?'✅ '.__('reussite'):'❌ '.__('echec') ?></div>
<p class="lead text-muted mt-1"><?= $ok?__('felicitations'):__('essayez_encore') ?></p>
<h6 class="mt-3 mb-2" style="color:var(--b);font-weight:800;"><i class="fas fa-chart-bar me-2" style="color:var(--g);"></i>Récapitulatif de toutes les évaluations</h6>
<table class="tbl">
<thead><tr><th>Module évalué</th><th>Note obtenue</th><th>Score</th><th>Seuil</th><th>Résultat</th></tr></thead>
<tbody>
<?php foreach($det_mods as $dm): ?>
<tr><td><?= $dm['num_module']??($dm['num']??'') ?> – <?= htmlspecialchars($dm['nom_module']??($dm['nom']??'')) ?></td><td><?= $dm['note'] ?>/<?= round(floatval($dm['sur']??0),1) ?> pts</td><td><?= $dm['pct'] ?>%<div class="bw"><div class="bf" style="width:<?= min($dm['pct'],100) ?>%;background:<?= $dm['reussite']?'#28a745':'#dc3545' ?>;"></div></div></td><td>≥<?= intval($seuil_form) ?>%</td><td><?= $dm['reussite']?'<span class="tok">✓ OK</span>':'<span class="tfail">✗</span>' ?></td></tr>
<?php endforeach; ?>
<tr class="moy"><td colspan="1"><strong>MOYENNE GÉNÉRALE (<?= $nb_mods ?> modules)</strong></td><td><strong><?= round($moy_form,1) ?>%</strong></td><td colspan="2"><div class="bw"><div class="bf" style="width:<?= min($moy_form,100) ?>%;background:<?= $ok?'#28a745':'#dc3545' ?>;"></div></div></td><td><?= $ok?'<span class="tok">✓ ADMIS</span>':'<span class="tfail">✗ RECALÉ</span>' ?></td></tr>
</tbody></table>
<div class="ai text-start"><i class="fas fa-info-circle me-2"></i>Moyenne calculée sur <strong><?= $nb_mods ?> évaluation(s) de module(s)</strong> — Seuil : <?= intval($seuil_form) ?>%.</div>

<?php else: ?>
<!-- AS / INST / SENS -->
<div class="circle mx-auto" style="background:linear-gradient(135deg,<?= $ok?'#28a745,#20c997':'#dc3545,#c82333' ?>);">
<div class="cn"><?= round($nb,1) ?></div><div class="cs">/ <?= round($ns,1) ?> pts</div><div class="cp"><?= round($pct,1) ?>%</div></div>
<div class="bw mt-2 mb-3"><div class="bf" style="width:<?= min($pct,100) ?>%;background:<?= $col ?>;"></div></div>
<div class="mention" style="color:<?= $col ?>"><?= $ok?'✅ '.__('reussite'):'❌ '.__('echec') ?></div>
<p class="lead text-muted mt-1"><?= $ok?__('felicitations'):__('essayez_encore') ?></p>
<div class="ic text-start">
<div class="ir"><span class="il"><i class="fas fa-star me-2" style="color:var(--g);"></i>Note obtenue</span><span class="iv"><?= round($nb,1) ?> / <?= round($ns,1) ?> pts</span></div>
<div class="ir"><span class="il"><i class="fas fa-percent me-2"></i><?= __('pourcentage') ?></span><span class="iv" style="color:<?= $col ?>"><?= round($pct,1) ?>%</span></div>
<div class="ir"><span class="il"><i class="fas fa-trophy me-2" style="color:var(--g);"></i><?= __('seuil_reussite') ?></span><span class="iv"><?= $ok?__('atteint'):__('non_atteint') ?></span></div>
</div>
<?php endif; ?>

<?php if(!$hasEval&&!$est_th): ?>
<div class="rw">
<h6 class="mb-3"><i class="fas fa-star me-2" style="color:var(--g);"></i><?= __('evaluez_experience') ?></h6>
<div id="rB"><button class="rb rbg" onclick="sE('satisfait')"><i class="fas fa-smile me-2"></i><?= __('satisfait') ?></button><button class="rb rby" onclick="sE('moyen')"><i class="fas fa-meh me-2"></i><?= __('moyen') ?></button><button class="rb rbr" onclick="sE('insatisfait')"><i class="fas fa-frown me-2"></i><?= __('insatisfait') ?></button></div>
<p id="rD" class="text-success mt-2 mb-0 small" style="display:none;"><i class="fas fa-check-circle me-1"></i><?= __('evaluation_enregistree') ?></p>
</div>
<?php elseif($hasEval&&!$est_th): ?><div class="as text-start mt-3"><i class="fas fa-check-circle me-2"></i><?= __('merci_evaluation') ?> — <?= __('deja_evalue') ?></div><?php endif; ?>

<div class="mt-4">
<a href="../../index.php" class="btn-a"><i class="fas fa-home me-2"></i><?= __('retour_accueil_bouton') ?></a>

</div>
</div></div>
<footer class="text-center mt-4 text-muted small"><p>&copy; <?= date('Y') ?> ANAC GABON &ndash; EXASUR</p><p><?= __('document_officiel') ?></p></footer>
</div></div>

<script>
function sE(r){document.querySelectorAll('.rb').forEach(b=>{b.disabled=true;b.style.opacity='.5';});fetch('save_evaluation.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({rating:r})}).then(x=>x.json()).then(d=>{if(d.success){document.getElementById('rD').style.display='block';document.getElementById('rB').style.display='none';}}).catch(()=>{});}
<?php if($est_pr&&$if_json): ?>
document.addEventListener('DOMContentLoaded',function(){
const s=<?= $if_json ?>,c=s.rg?'#28a745':'#dc3545',v=s.rg?'🎉 ADMIS(E)':'❌ NON ADMIS(E)';
setTimeout(()=>Swal.fire({title:'📊 Récapitulatif IF',html:`<table style="width:100%;border-collapse:collapse;font-size:.9rem;"><thead><tr style="background:#03224c;color:#D4AF37;"><th style="padding:8px;text-align:left;">Épreuve</th><th>Note</th><th>Seuil</th><th>Résultat</th></tr></thead><tbody><tr style="border-bottom:1px solid #eee;"><td style="padding:8px;"><strong>📖 Théorie</strong></td><td style="text-align:center;font-weight:700;">${s.pt}%</td><td style="text-align:center;">≥80%</td><td style="text-align:center;">${s.rt?'✅':'❌'}</td></tr><tr style="border-bottom:1px solid #eee;"><td style="padding:8px;"><strong>🖼️ Pratique</strong></td><td style="text-align:center;font-weight:700;">${s.pp}%</td><td style="text-align:center;">≥80%</td><td style="text-align:center;">${s.rp?'✅':'❌'}</td></tr><tr style="background:#f4f7fc;"><td style="padding:8px;"><strong>📊 MOYENNE</strong></td><td style="text-align:center;font-weight:900;color:${c};">${s.moy}%</td><td style="text-align:center;">≥80%</td><td style="text-align:center;font-weight:700;color:${c};">${s.rg?'✅':'❌'}</td></tr></tbody></table><p style="text-align:center;font-size:1.1rem;font-weight:900;color:${c};margin-top:12px;">${v}</p>`,confirmButtonColor:'#03224c',confirmButtonText:'Compris',allowOutsideClick:false,width:'480px'}),700);});
<?php endif; ?>
<?php if($is_form_final&&$form_json): ?>
document.addEventListener('DOMContentLoaded',function(){
const d=<?= $form_json ?>,c=d.rg?'#28a745':'#dc3545',v=d.rg?'🎉 ADMIS(E) — ÉVALUATION FORMATION':'❌ NON ADMIS(E)';
let rows=d.modules.map(m=>`<tr style="border-bottom:1px solid #eee;"><td style="padding:7px;text-align:left;">Module ${m.num_module||m.num||'?'}</td><td style="text-align:center;">${m.note||'?'}pts</td><td style="text-align:center;font-weight:700;">${m.pct}%</td><td style="text-align:center;">${m.reussite?'✅':'❌'}</td></tr>`).join('');
setTimeout(()=>Swal.fire({title:'📊 Récapitulatif Formation',html:`<table style="width:100%;border-collapse:collapse;font-size:.88rem;"><thead><tr style="background:#03224c;color:#D4AF37;"><th style="padding:8px;text-align:left;">Module</th><th>Note</th><th>Score</th><th>Résultat</th></tr></thead><tbody>${rows}<tr style="background:#f4f7fc;font-weight:900;"><td style="padding:8px;text-align:left;"><strong>MOYENNE</strong></td><td style="text-align:center;"></td><td style="text-align:center;color:${c};font-size:1.1rem;">${d.moy}%</td><td style="text-align:center;color:${c};">${d.rg?'✅':'❌'}</td></tr></tbody></table><p style="text-align:center;font-size:1.1rem;font-weight:900;color:${c};margin-top:12px;">${v}</p><p style="color:#666;font-size:.8rem;text-align:center;">Seuil : ${d.seuil}%</p>`,confirmButtonColor:'#03224c',confirmButtonText:'Compris',allowOutsideClick:false,width:'500px'}),700);});
<?php endif; ?>
</script>
</body></html>
<?php
unset($_SESSION['questions'],$_SESSION['reponses'],$_SESSION['current_index'],$_SESSION['nb_questions'],$_SESSION['temps_debut'],$_SESSION['idmodule_en_cours'],$_SESSION['nom_module_en_cours']);
if(isset($conn))$conn->close();
?>