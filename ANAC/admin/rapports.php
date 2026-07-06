<?php
/**
 * rapports.php -Centre de rapports croisés ANAC GABON
 * CORRECTIONS :
 *  - Suppression * { font-family !important } → icônes FA réparées
 *  - id="st" (sidebar) + fa-chart-bar (icône valide)
 *  - Fonctions JS propres, aucun }) orphelin
 *  - Dates optionnelles, filtre PLAGE (>= / <=)
 *  - Seuils 70% partout
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$plages_res = $conn->query("
    SELECT date_debut, date_fin,
           GROUP_CONCAT(DISTINCT te.code ORDER BY te.code SEPARATOR ', ') AS types
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    GROUP BY date_debut, date_fin
    ORDER BY date_debut DESC
");
$plages = [];
while ($p = $plages_res->fetch_assoc()) $plages[] = $p;

$types_res = $conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
$types_list = [];
while ($t = $types_res->fetch_assoc()) $types_list[] = $t;

$cands_res = $conn->query("
    SELECT c.idcandidat, s.nomstagiaire, s.prenomstagiaire, c.code_acces
    FROM candidat c JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    ORDER BY s.nomstagiaire
");
$cands_list = [];
while ($c = $cands_res->fetch_assoc()) $cands_list[] = $c;

$active_page = 'rapports';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rapports -ANAC GABON</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
/* IMPORTANT : ne JAMAIS mettre font-family sur * avec !important
   cela écrase la police interne de FontAwesome → carrés blancs.
   On cible uniquement les éléments visibles de l'UI. */
body, input, button, select, textarea,
.rpt-input, .rpt-select, .rpt-btn,
.ss-display, .ss-search, .ss-opt, .plage-chip,
.res-table td, .res-table th, .mini-stat,
.type-block-header { font-family: 'Candara','Calibri',sans-serif; }

.rpt-bar { background:linear-gradient(135deg,#03224c,#0a3a6b); border-radius:13px; padding:20px 24px; margin-bottom:20px; border-bottom:4px solid #D4AF37; }
.rpt-bar-title { color:#D4AF37; font-weight:800; font-size:.92rem; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.rpt-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; gap:12px; align-items:end; }
@media(max-width:900px){.rpt-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:560px){.rpt-grid{grid-template-columns:1fr;}}
.rpt-lbl { display:block; color:rgba(255,255,255,.85); font-size:.74rem; font-weight:600; margin-bottom:4px; }
.rpt-inp, .rpt-sel {
  width:100%; padding:9px 11px; border:2px solid rgba(255,255,255,.25);
  border-radius:8px; background:rgba(255,255,255,.12); color:white;
  font-size:.86rem; outline:none; transition:border-color .2s;
}
.rpt-inp:focus,.rpt-sel:focus { border-color:#D4AF37; }
.rpt-inp::placeholder { color:rgba(255,255,255,.45); }
.rpt-sel option { background:#03224c; }
.rpt-btn { padding:9px 16px; border:none; border-radius:8px; font-weight:700; font-size:.86rem; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap; transition:all .2s; }
.rpt-ok { background:#D4AF37; color:#03224c; } .rpt-ok:hover { background:#c49e2a; }
.rpt-rst { background:rgba(255,255,255,.15); color:white; border:1.5px solid rgba(255,255,255,.3); }
.rpt-rst:hover { background:rgba(255,255,255,.25); }
.rpt-prt { background:white; color:#03224c; } .rpt-prt:hover { background:#f0f4ff; }
.chip { display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); color:white; padding:4px 11px; border-radius:50px; font-size:.73rem; font-weight:600; cursor:pointer; margin:3px; transition:all .2s; user-select:none; }
.chip:hover,.chip.active { background:#D4AF37; color:#03224c; border-color:#D4AF37; }
.ss-wrap { position:relative; }
.ss-disp { width:100%; padding:9px 11px; border:2px solid rgba(255,255,255,.25); border-radius:8px; background:rgba(255,255,255,.12); color:white; font-size:.86rem; outline:none; cursor:pointer; }
.ss-disp:focus { border-color:#D4AF37; }
.ss-drop { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:white; border:2px solid #0a3a6b; border-radius:8px; box-shadow:0 8px 24px rgba(3,34,76,.18); z-index:9999; max-height:230px; overflow:hidden; flex-direction:column; }
.ss-drop.open { display:flex; }
.ss-srch { padding:8px 12px; border:none; border-bottom:1px solid #e5e7eb; outline:none; font-size:.84rem; flex-shrink:0; color:#222; }
.ss-lst { overflow-y:auto; flex:1; }
.ss-opt { padding:7px 12px; font-size:.82rem; cursor:pointer; color:#374151; }
.ss-opt:hover { background:#f0f4ff; }
.ss-opt.on { background:#dbeafe; color:#1e40af; font-weight:700; }
.ss-opt.nr { color:#9ca3af; font-style:italic; pointer-events:none; }
#rz { min-height:200px; }
.es { text-align:center; padding:60px 20px; color:#9ca3af; }
.es i { font-size:3rem; margin-bottom:14px; display:block; }
.tb { margin-bottom:28px; }
.tbh { background:linear-gradient(135deg,#03224c,#0a3a6b); color:white; padding:11px 18px; border-radius:10px 10px 0 0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:.93rem; }
.tbb { border:2px solid #03224c; border-top:none; border-radius:0 0 10px 10px; overflow-x:auto; }
.bc { background:#D4AF37; color:#03224c; padding:3px 12px; border-radius:50px; font-size:.78rem; font-weight:800; }
.rt { width:100%; border-collapse:collapse; font-size:.80rem; }
.rt th { background:#f0f4ff; color:#03224c; padding:7px 9px; border:1px solid #c7d2fe; font-weight:700; text-align:center; font-size:.74rem; white-space:nowrap; }
.rt th.tl { text-align:left; }
.rt td { border:1px solid #e5e7eb; padding:6px 8px; text-align:center; vertical-align:middle; }
.rt td.tl { text-align:left; }
.rt tr:nth-child(even) td { background:#fafbff; }
.rt tr:hover td { background:#f0f4ff; }
.ok { color:#16a34a; font-weight:800; }
.ko { color:#dc2626; font-weight:800; }
.md { color:#ca8a04; font-weight:800; }
.bok { background:#dcfce7; color:#16a34a; padding:2px 9px; border-radius:50px; font-weight:800; font-size:.75rem; white-space:nowrap; }
.bko { background:#fee2e2; color:#dc2626; padding:2px 9px; border-radius:50px; font-weight:800; font-size:.75rem; white-space:nowrap; }
.rb { width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.73rem; margin:0 auto; }
.r1{background:#D4AF37;color:#03224c;} .r2{background:#e5e7eb;color:#374151;} .r3{background:#fde68a;color:#92400e;} .rn{background:#f3f4f6;color:#6b7280;}
.sm { display:flex; gap:10px; padding:10px 14px; background:#f8faff; border-top:1px solid #e5e7eb; font-size:.79rem; flex-wrap:wrap; }
.ms { font-weight:600; color:#03224c; }
.ms span { color:#6c7a8d; font-weight:400; margin-right:4px; }
.ib { background:linear-gradient(135deg,#f0f4ff,#e8f0fe); border:1.5px solid #c7d7f9; border-radius:12px; padding:14px 18px; margin-bottom:18px; display:flex; gap:14px; font-size:.82rem; color:#374151; line-height:1.65; }
@media print {
  .admin-sidebar,.admin-topbar,.rpt-bar,.np,.ib { display:none!important; }
  .admin-main { margin-left:0!important; }
  .tbh { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .rt th { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  #sumBlock { break-inside:avoid; }
  #txtComment { border:1px solid #ccc!important; background:#fafafa!important; }
  canvas { max-width:100%!important; }
}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
  <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
  <div class="topbar-title"><i class="fas fa-chart-bar me-2"></i>Centre de rapports croisés</div>
  <div class="ms-auto"><span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span></div>
</div>

<div class="admin-content">

<div class="ib">
  <div style="font-size:2rem;flex-shrink:0">📊</div>
  <div>
    <strong style="color:#03224c">À quoi sert cette page ?</strong><br>
    Rapport croisé des résultats d'examen par session.
    Filtrez par <strong>plage de dates</strong> (ex : 12/05/2026 - 31/05/2026), <strong>type</strong> et/ou <strong>candidat</strong>.
    Cliquez sur un bouton <em>Sélection rapide</em> pour remplir les dates automatiquement.
    <br><span style="color:#6b7280;font-size:.77rem">Dates optionnelles -laissez vides pour voir tous les examens du type choisi.</span>
  </div>
</div>

<div class="rpt-bar">
  <div class="rpt-bar-title"><i class="fas fa-filter"></i> FILTRES -RAPPORTS D'EXAMENS</div>
  <div class="rpt-grid">

    <div>
      <label class="rpt-lbl"><i class="fas fa-user me-1"></i>Candidat</label>
      <div class="ss-wrap">
        <input type="text" class="ss-disp" id="f_cd" placeholder="Tous les candidats" readonly onclick="ssT('cand')">
        <input type="hidden" id="f_cv">
        <div class="ss-drop" id="ss_cand">
          <input type="text" class="ss-srch" placeholder="Rechercher..." oninput="ssF('cand',this.value)">
          <div class="ss-lst" id="ssl_cand">
            <div class="ss-opt" data-v="" onclick="ssS('cand','','Tous les candidats')">Tous les candidats</div>
            <?php foreach ($cands_list as $c):
              $lbl = htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire'].' ('.$c['code_acces'].')', ENT_QUOTES);
              $ds  = strtolower($c['nomstagiaire'].' '.$c['prenomstagiaire'].' '.$c['code_acces']);
            ?>
            <div class="ss-opt" data-v="<?= $c['idcandidat'] ?>" data-s="<?= $ds ?>"
                 onclick="ssS('cand','<?= $c['idcandidat'] ?>','<?= $lbl ?>')">
              <?= $lbl ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div>
      <label class="rpt-lbl"><i class="fas fa-tag me-1"></i>Type d'examen</label>
      <select id="f_tp" class="rpt-sel">
        <option value="">Tous les types</option>
        <?php foreach ($types_list as $t): ?>
        <option value="<?= $t['idtype_examen'] ?>"><?= $t['code'] ?> - <?= htmlspecialchars($t['nom_fr']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="rpt-lbl"><i class="fas fa-calendar-day me-1"></i>Début session</label>
      <input type="date" id="f_db" class="rpt-inp">
    </div>

    <div>
      <label class="rpt-lbl"><i class="fas fa-calendar-check me-1"></i>Fin session</label>
      <input type="date" id="f_fn" class="rpt-inp">
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
      <button class="rpt-btn rpt-ok" onclick="go()">
        <i class="fas fa-search"></i> Rechercher
      </button>
      <button class="rpt-btn rpt-rst" onclick="raz()">
        <i class="fas fa-times"></i> Réinitialiser
      </button>
    </div>
  </div>

  <div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,.15)">
    <span style="color:rgba(255,255,255,.7);font-size:.74rem;font-weight:600;margin-right:6px">
      <i class="fas fa-bolt" style="color:#D4AF37"></i> Sélection rapide :
    </span>
    <?php foreach ($plages as $pl): ?>
    <span class="chip" onclick="quick('<?= $pl['date_debut'] ?>','<?= $pl['date_fin'] ?>',this)">
      <i class="fas fa-calendar-alt"></i>
      <?= date('d/m/Y',strtotime($pl['date_debut'])) ?> - <?= date('d/m/Y',strtotime($pl['date_fin'])) ?>
      <span style="opacity:.7">[<?= $pl['types'] ?>]</span>
    </span>
    <?php endforeach; ?>
    <?php if (empty($plages)): ?>
    <span style="color:rgba(255,255,255,.45);font-size:.77rem;font-style:italic">Aucune session en base</span>
    <?php endif; ?>
  </div>
</div>

<div id="rz">
  <div class="es">
    <i class="fas fa-chart-bar" style="color:#D4AF37"></i>
    <p style="font-size:1rem;font-weight:700;color:#03224c">Appliquez un filtre pour voir les résultats</p>
    <p style="font-size:.84rem">Utilisez les filtres ci-dessus ou cliquez sur une Sélection rapide.</p>
  </div>
</div>

</div>
</main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Sidebar */
document.getElementById('st').addEventListener('click', function(){
  document.getElementById('adminSidebar').classList.toggle('open');
});

/* Fermer dropdown */
document.addEventListener('click', function(e){
  if (!e.target.closest('.ss-wrap'))
    document.querySelectorAll('.ss-drop').forEach(function(d){ d.classList.remove('open'); });
});

/* Searchable select */
function ssT(k){
  var d=document.getElementById('ss_'+k);
  document.querySelectorAll('.ss-drop').forEach(function(x){ if(x!==d)x.classList.remove('open'); });
  d.classList.toggle('open');
  var s=d.querySelector('.ss-srch');
  if(d.classList.contains('open')&&s) s.focus();
}
function ssS(k,v,l){
  document.getElementById('f_'+(k==='cand'?'cv':'cv')).value = v; /* toujours f_cv pour cand */
  if(k==='cand'){ document.getElementById('f_cv').value=v; document.getElementById('f_cd').value=l; }
  document.getElementById('ss_'+k).classList.remove('open');
  document.querySelectorAll('#ssl_'+k+' .ss-opt').forEach(function(o){
    o.classList.toggle('on', o.dataset.v==v);
  });
}
function ssF(k,q){
  q=q.toLowerCase().trim();
  var opts=document.querySelectorAll('#ssl_'+k+' .ss-opt[data-v]');
  var vis=0;
  opts.forEach(function(o){
    var m=!q||(o.dataset.s||o.textContent.toLowerCase()).includes(q);
    o.style.display=m?'':'none';
    if(m&&o.dataset.v)vis++;
  });
  var nr=document.querySelector('#ssl_'+k+' .nr');
  if(!nr){ nr=document.createElement('div'); nr.className='ss-opt nr'; nr.textContent='Aucun résultat'; document.getElementById('ssl_'+k).appendChild(nr); }
  nr.style.display=(vis===0&&q)?'':'none';
}

/* Sélection rapide */
function quick(db,fn,el){
  document.getElementById('f_db').value=db;
  document.getElementById('f_fn').value=fn;
  document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
  if(el) el.classList.add('active');
  go();
}

/* Réinitialiser */
function raz(){
  document.getElementById('f_db').value='';
  document.getElementById('f_fn').value='';
  document.getElementById('f_tp').value='';
  document.getElementById('f_cv').value='';
  document.getElementById('f_cd').value='Tous les candidats';
  document.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
  document.getElementById('rz').innerHTML=
    '<div class="es"><i class="fas fa-chart-bar" style="color:#D4AF37"></i>'+
    '<p style="font-size:1rem;font-weight:700;color:#03224c">Filtres réinitialisés</p>'+
    '<p style="font-size:.84rem">Sélectionnez vos critères et cliquez sur Rechercher.</p></div>';
}

/* === RECHERCHER === */
function go(){
  var db=document.getElementById('f_db').value;
  var fn=document.getElementById('f_fn').value;
  var tp=document.getElementById('f_tp').value;
  var cv=document.getElementById('f_cv').value;

  if((db&&!fn)||(!db&&fn)){
    Swal.fire({icon:'warning',title:'Filtre incomplet',
      text:'Renseignez Début ET Fin ensemble, ou laissez les deux vides.',
      confirmButtonColor:'#03224c'}); return;
  }
  if(!db&&!fn&&!tp&&!cv){
    Swal.fire({icon:'question',title:'Aucun filtre',
      text:'Afficher tous les résultats de toutes les sessions ?',
      showCancelButton:true,confirmButtonColor:'#03224c',cancelButtonColor:'#6b7280',
      confirmButtonText:'Oui',cancelButtonText:'Non'
    }).then(function(r){ if(r.isConfirmed) ajax(db,fn,tp,cv); }); return;
  }
  ajax(db,fn,tp,cv);
}

function ajax(db,fn,tp,cv){
  Swal.fire({title:'Chargement…',
    html:'<div style="margin:12px 0;font-size:1.8rem"><i class="fas fa-circle-notch fa-spin" style="color:#03224c"></i></div>',
    showConfirmButton:false,allowOutsideClick:false});

  $.ajax({
    url:'rapports_data.php', method:'POST',
    data:{date_debut:db,date_fin:fn,type_id:tp,cand_id:cv},
    dataType:'json',
    success:function(d){
      Swal.close();
      if(!d||d.status==='error'){
        Swal.fire({icon:'error',title:'Erreur serveur',
          text:d?d.message:'Réponse invalide.',confirmButtonColor:'#03224c'}); return;
      }
      if(d.status==='empty'){
        document.getElementById('rz').innerHTML=
          '<div class="es"><i class="fas fa-search" style="color:#D4AF37;font-size:2.5rem"></i>'+
          '<p style="font-size:1rem;font-weight:700;color:#03224c;margin-top:12px">Aucune session trouvée</p>'+
          '<p style="font-size:.83rem;color:#6b7280">'+eh(d.message||'Essayez une autre plage de dates.')+'</p></div>';
        return;
      }
      render(d,db,fn);
    },
    error:function(xhr,st,err){
      Swal.close();
      var msg='';
      try{ var d=JSON.parse(xhr.responseText); msg=d.message||err; }catch(e){ msg=err||'Erreur inconnue'; }
      Swal.fire({icon:'error',title:'Erreur serveur (HTTP '+xhr.status+')',
        html:'<code style="font-size:.8rem;color:#dc2626">'+msg+'</code>',
        confirmButtonColor:'#03224c'});
    }
  });
}

/* === RENDU RÉSULTATS === */
function render(data,db,fn){
  if(!data.groupes||!data.groupes.length){
    document.getElementById('rz').innerHTML=
      '<div class="es"><i class="fas fa-inbox" style="color:#9ca3af"></i>'+
      '<p style="font-size:1rem;font-weight:700;color:#03224c">Aucun résultat trouvé</p></div>'; return;
  }
  var lbl=db?'Session du <strong>'+fd(db)+'</strong> au <strong>'+fd(fn)+'</strong>':'Tous les examens';
  var h='<div style="background:#f0f4ff;border-left:4px solid #D4AF37;padding:10px 15px;border-radius:8px;margin-bottom:16px;font-size:.86rem;color:#03224c;font-weight:600">'+
    '<i class="fas fa-filter" style="color:#D4AF37;margin-right:7px"></i>'+lbl+
    ' -<strong>'+data.total_candidats+'</strong> candidat(s)'+
    '<button class="rpt-btn rpt-prt np" style="float:right;padding:5px 12px;font-size:.77rem" onclick="printRapport()">'+
    '<i class="fas fa-print"></i> Imprimer PDF</button></div>';

  /* ── Bloc commentaire DG + graphiques ── */
  h += buildSummary(data, db, fn);

  data.groupes.forEach(function(g){ h+=bloc(g); });
  document.getElementById('rz').innerHTML=h;

  /* Dessiner les graphiques après insertion dans le DOM */
  setTimeout(function(){ drawCharts(data); }, 100);
}

/* ════════════════════════════════════════════════════════════════
   IMPRESSION -Ouvre une nouvelle fenêtre propre (sans sidebar admin)
   et déclenche window.print() après chargement.
   Les canvas sont convertis en images PNG avant ouverture.
════════════════════════════════════════════════════════════════ */
function printRapport() {
  var rz = document.getElementById('rz');
  if (!rz || !rz.querySelector('#sumBlock')) {
    Swal.fire({ icon:'warning', title:'Rien à imprimer',
      text:'Lancez d\'abord une recherche.', confirmButtonColor:'#03224c' }); return;
  }

  /* ── 1. Convertir les canvas en images PNG (avant clonage) ── */
  var canvasImages = {};
  rz.querySelectorAll('canvas').forEach(function(cv){
    try { canvasImages[cv.id] = cv.toDataURL('image/png'); } catch(e){}
  });

  /* ── 2. Récupérer le commentaire édité ── */
  var ta = document.getElementById('txtComment');
  var commentTxt = ta ? ta.value : '';

  /* ── 3. Cloner et préparer le contenu ── */
  var clone = rz.cloneNode(true);

  /* Supprimer boutons */
  clone.querySelectorAll('.np, button').forEach(function(el){ el.remove(); });

  /* Remplacer textarea → pre */
  var cloneTa = clone.querySelector('#txtComment');
  if (cloneTa) {
    var pre = document.createElement('pre');
    pre.style.cssText = 'white-space:pre-wrap;font-family:Candara,sans-serif;font-size:10pt;'+
      'border:1px solid #ccc;border-radius:8px;padding:12px;line-height:1.6;'+
      'background:#fafafa;margin:0;color:#374151;';
    pre.textContent = commentTxt;
    cloneTa.parentNode.replaceChild(pre, cloneTa);
  }

  /* Remplacer canvas → img PNG */
  clone.querySelectorAll('canvas').forEach(function(cv){
    if (canvasImages[cv.id]) {
      var img = document.createElement('img');
      img.src   = canvasImages[cv.id];
      img.style.cssText = 'max-width:'+Math.min(cv.width,260)+'px;width:100%;'+
                          'display:block;margin:0 auto;';
      cv.parentNode.replaceChild(img, cv);
    }
  });

  /* ── 4. Ouvrir fenêtre d'impression ── */
  var win = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
  if (!win) {
    Swal.fire({ icon:'error', title:'Fenêtre bloquée',
      text:'Autorisez les popups pour ce site (icône dans la barre d\'adresse).',
      confirmButtonColor:'#03224c' }); return;
  }

  var bodyHtml = clone.innerHTML;
  var dateImpression = new Date().toLocaleDateString('fr-FR',
    {day:'2-digit', month:'long', year:'numeric'});

  win.document.write('<!DOCTYPE html>\n<html lang="fr">\n<head>\n'+
    '<meta charset="UTF-8">\n'+
    '<meta name="viewport" content="width=device-width,initial-scale=1">\n'+
    '<title>Rapport EXASUR -ANAC GABON</title>\n'+
    '<style>\n'+
    /* ── CRITIQUE : forcer l\'impression des couleurs d\'arrière-plan ── */
    '*, *::before, *::after {\n'+
    '  -webkit-print-color-adjust: exact !important;\n'+
    '  print-color-adjust: exact !important;\n'+
    '  color-adjust: exact !important;\n'+
    '  box-sizing: border-box;\n'+
    '}\n'+
    /* ── Base ── */
    'body {\n'+
    '  font-family: Candara, Calibri, Arial, sans-serif;\n'+
    '  font-size: 10.5pt;\n'+
    '  color: #222;\n'+
    '  background: #fff;\n'+
    '  margin: 0;\n'+
    '  padding: 10mm 12mm;\n'+
    '}\n'+
    /* ── Boutons écran (masqués à l\'impression) ── */
    '.print-actions {\n'+
    '  margin-bottom: 14px;\n'+
    '  display: flex;\n'+
    '  gap: 10px;\n'+
    '}\n'+
    '.btn-do-print {\n'+
    '  background: #03224c;\n'+
    '  color: #fff;\n'+
    '  border: none;\n'+
    '  padding: 10px 22px;\n'+
    '  border-radius: 8px;\n'+
    '  font-family: Candara, sans-serif;\n'+
    '  font-size: 1rem;\n'+
    '  font-weight: 700;\n'+
    '  cursor: pointer;\n'+
    '}\n'+
    '.btn-do-close {\n'+
    '  background: #e8ecf5;\n'+
    '  color: #03224c;\n'+
    '  border: 2px solid #c8d0e0;\n'+
    '  padding: 10px 18px;\n'+
    '  border-radius: 8px;\n'+
    '  font-family: Candara, sans-serif;\n'+
    '  font-size: .95rem;\n'+
    '  font-weight: 700;\n'+
    '  cursor: pointer;\n'+
    '}\n'+
    /* ── En-tête impression ── */
    '.rpt-entete {\n'+
    '  text-align: center;\n'+
    '  border-bottom: 3px solid #D4AF37;\n'+
    '  padding-bottom: 10px;\n'+
    '  margin-bottom: 16px;\n'+
    '}\n'+
    '.rpt-entete h1 { font-size: 1.05rem; color: #03224c; margin: 0 0 3px; }\n'+
    '.rpt-entete p  { font-size: .78rem; color: #6b7280; margin: 0; }\n'+
    /* ── Flex helpers (les inline styles utilisent display:flex) ── */
    '.flex-row { display: flex !important; flex-wrap: wrap; }\n'+
    /* ── sumBlock ── */
    '#sumBlock {\n'+
    '  border: 2px solid #03224c;\n'+
    '  border-radius: 12px;\n'+
    '  overflow: hidden;\n'+
    '  margin-bottom: 20px;\n'+
    '  page-break-inside: avoid;\n'+
    '  break-inside: avoid;\n'+
    '}\n'+
    /* ── Tableaux de données ── */
    '.tb  { margin-bottom: 20px; page-break-inside: avoid; break-inside: avoid; }\n'+
    '.tbb { border: 2px solid #03224c; border-top: none; border-radius: 0 0 10px 10px; overflow: hidden; }\n'+
    '.tbh {\n'+
    '  background: #03224c !important;\n'+
    '  color: #fff !important;\n'+
    '  padding: 10px 16px;\n'+
    '  display: flex;\n'+
    '  align-items: center;\n'+
    '  gap: 9px;\n'+
    '  font-weight: 800;\n'+
    '  font-size: 9pt;\n'+
    '  border-radius: 10px 10px 0 0;\n'+
    '}\n'+
    '.bc {\n'+
    '  background: #D4AF37 !important;\n'+
    '  color: #03224c !important;\n'+
    '  padding: 2px 10px;\n'+
    '  border-radius: 50px;\n'+
    '  font-size: 8pt;\n'+
    '  font-weight: 800;\n'+
    '}\n'+
    '.rt { width: 100%; border-collapse: collapse; font-size: 8pt; }\n'+
    '.rt th {\n'+
    '  background: #f0f4ff !important;\n'+
    '  color: #03224c !important;\n'+
    '  padding: 6px 8px;\n'+
    '  border: 1px solid #c7d2fe;\n'+
    '  font-weight: 700;\n'+
    '  text-align: center;\n'+
    '  font-size: 7.5pt;\n'+
    '}\n'+
    '.rt th.tl { text-align: left; }\n'+
    '.rt td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: center; vertical-align: middle; }\n'+
    '.rt td.tl { text-align: left; }\n'+
    '.rt tr:nth-child(even) td { background: #fafbff !important; }\n'+
    '.ok  { color: #16a34a; font-weight: 800; }\n'+
    '.ko  { color: #dc2626; font-weight: 800; }\n'+
    '.md  { color: #ca8a04; font-weight: 800; }\n'+
    '.bok { background: #dcfce7 !important; color: #16a34a; padding: 2px 8px; border-radius: 50px; font-weight: 800; font-size: 7.5pt; white-space: nowrap; }\n'+
    '.bko { background: #fee2e2 !important; color: #dc2626; padding: 2px 8px; border-radius: 50px; font-weight: 800; font-size: 7.5pt; white-space: nowrap; }\n'+
    '.rb  { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 7pt; }\n'+
    '.r1  { background: #D4AF37 !important; color: #03224c !important; }\n'+
    '.r2  { background: #e5e7eb !important; }\n'+
    '.r3  { background: #fde68a !important; }\n'+
    '.rn  { background: #f3f4f6 !important; color: #6b7280; }\n'+
    '.sm  { display: flex; gap: 8px; padding: 8px 12px; background: #f8faff !important; border-top: 1px solid #e5e7eb; font-size: 8pt; flex-wrap: wrap; }\n'+
    '.ms  { font-weight: 600; color: #03224c; }\n'+
    '.ms span { color: #6c7a8d; font-weight: 400; margin-right: 3px; }\n'+
    /* ── Règles impression ── */
    '@media print {\n'+
    '  .print-actions { display: none !important; }\n'+
    '  body { padding: 0; }\n'+
    '  @page { size: A4 portrait; margin: 10mm 12mm; }\n'+
    '  .tb { page-break-inside: avoid; }\n'+
    '  #sumBlock { page-break-inside: avoid; }\n'+
    '}\n'+
    '</style>\n'+
    '</head>\n<body>\n'+

    /* Boutons écran */
    '<div class="print-actions">\n'+
    '  <button class="btn-do-print" onclick="window.print()">🖨️&nbsp; Imprimer / Enregistrer PDF</button>\n'+
    '  <button class="btn-do-close" onclick="window.close()">✕&nbsp; Fermer</button>\n'+
    '</div>\n'+

    /* En-tête rapport */
    '<div class="rpt-entete">\n'+
    '  <h1>EXASUR -ANAC GABON -Rapport de résultats d\'examens de certification</h1>\n'+
    '  <p>Direction de la Sûreté &amp; Facilitation &nbsp;·&nbsp; Confidentiel &nbsp;·&nbsp; Imprimé le '+dateImpression+'</p>\n'+
    '</div>\n'+

    /* Contenu du rapport */
    bodyHtml +

    '\n</body>\n</html>');

  win.document.close();
}

/* ── SYNTHÈSE DG avec graphiques ── */
function buildSummary(data, db, fn) {
  /* ═══════════════════════════════════════════════════════════════
     CALCULS FIABLES : on recompte directement depuis les candidats
     Pour éviter tout décalage si le PHP envoie des valeurs erronées.

     Inscrits   = tous les candidats de la session
     Composé    = ont effectivement passé au moins une épreuve (a_passe=true)
     Admis      = ont composé ET décision reussite=true (score ≥ 70%)
     Ajournés   = ont composé ET décision reussite=false (score < 70%)
     En attente = inscrits MAIS n'ont pas encore composé
     Taux réussite = Admis ÷ Composé × 100  (pas sur inscrits !)
  ═══════════════════════════════════════════════════════════════ */
  var tot_ins=0, tot_pass=0, tot_ok=0, tot_ko=0, tot_att=0;
  data.groupes.forEach(function(g){
    g.candidats.forEach(function(c){
      tot_ins++;
      if (c.a_passe) {
        tot_pass++;
        if (c.reussite) tot_ok++;
        else            tot_ko++;
      } else {
        tot_att++;
      }
    });
  });

  var tx     = tot_pass > 0 ? (tot_ok / tot_pass * 100).toFixed(1) : 0;
  var tx_ins = tot_ins  > 0 ? (tot_ok / tot_ins  * 100).toFixed(1) : 0;

  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-FR',{day:'2-digit',month:'long',year:'numeric'});

  /* Tableau récap par type */
  var lignesTypes = data.groupes.map(function(g){
    var ins  = g.candidats.length;
    var pass = g.candidats.filter(function(c){return c.a_passe;}).length;
    var ok   = g.candidats.filter(function(c){return c.reussite;}).length;
    var ko   = pass - ok;
    var att  = ins - pass;
    var tx_g = pass > 0 ? (ok/pass*100).toFixed(1) : 0;
    var tx_i = ins  > 0 ? (ok/ins*100).toFixed(1)  : 0;
    return '<tr style="border-bottom:1px solid #e5e7eb;">'+
      '<td style="padding:7px 10px;font-weight:700;text-align:left">'+eh(g.code)+' -'+eh(g.nom)+'</td>'+
      '<td style="padding:7px;text-align:center">'+ins+'</td>'+
      '<td style="padding:7px;text-align:center">'+pass+'</td>'+
      '<td style="padding:7px;text-align:center;color:#16a34a;font-weight:700">'+ok+'</td>'+
      '<td style="padding:7px;text-align:center;color:#dc2626;font-weight:700">'+ko+'</td>'+
      '<td style="padding:7px;text-align:center;color:#ca8a04;font-weight:700">'+att+'</td>'+
      '<td style="padding:7px;text-align:center;font-weight:800;color:'+(tx_g>=70?'#16a34a':'#dc2626')+'">'+tx_g+'%</td>'+
      '<td style="padding:7px;text-align:center;font-weight:700;color:#7c3aed">'+tx_i+'%</td>'+
      '</tr>';
  }).join('');

  return '<div id="sumBlock" style="background:white;border:2px solid #03224c;border-radius:14px;margin-bottom:24px;overflow:hidden;">'+

    /* En-tête */
    '<div style="background:linear-gradient(135deg,#03224c,#0a3a6b);color:white;padding:14px 20px;display:flex;align-items:center;gap:14px;">'+
      '<div style="font-size:1.8rem">📋</div>'+
      '<div>'+
        '<div style="font-weight:800;font-size:1rem">NOTE DE SYNTHÈSE -DIRECTION GÉNÉRALE</div>'+
        '<div style="font-size:.78rem;opacity:.75">EXASUR -ANAC GABON -Résultats d\'examens -'+dateStr+'</div>'+
      '</div>'+
      '<button class="np" onclick="printRapport()" '+
        'style="margin-left:auto;background:#D4AF37;color:#03224c;border:none;padding:8px 16px;'+
        'border-radius:8px;font-weight:700;font-size:.8rem;cursor:pointer;">'+
        '🖨️ Imprimer / PDF</button>'+
    '</div>'+

    /* 6 KPIs bien expliqués */
    '<div style="display:flex;gap:0;border-bottom:2px solid #e5e7eb;flex-wrap:wrap;">'+
      kpi2(tot_ins,  'Inscrits',                '#374151','Candidats enregistrés pour cette session')+
      kpi2(tot_pass, 'Ont composé',             '#0ea5e9','Ont effectivement passé l\'examen')+
      kpi2(tot_att,  'En attente',              '#ca8a04','Inscrits n\'ayant pas encore composé')+
      kpi2(tot_ok,   'Admis ≥ 70%',            '#16a34a','Ont composé ET score ≥ 70%')+
      kpi2(tot_ko,   'Ajournés < 70%',         '#dc2626','Ont composé ET score < 70%')+
      kpi2(tx+'%',   'Taux réussite',           '#7c3aed','Admis ÷ Composé ('+tot_ok+'/'+tot_pass+')')+
    '</div>'+

    /* Formules explicatives */
    '<div style="background:#fffbeb;padding:9px 18px;font-size:.74rem;color:#92400e;border-bottom:1px solid #fde68a;line-height:1.9;">'+
      '<strong>Formules :</strong>&nbsp;'+
      '<strong style="color:#7c3aed">Taux réussite</strong> = Admis ÷ Composé = '+tot_ok+' ÷ '+tot_pass+' = <strong>'+tx+'%</strong>'+
      '&nbsp;&nbsp;|&nbsp;&nbsp;'+
      '<strong style="color:#7c3aed">Taux / Inscrits</strong> = Admis ÷ Inscrits = '+tot_ok+' ÷ '+tot_ins+' = <strong>'+tx_ins+'%</strong>'+
      '&nbsp;&nbsp;|&nbsp;&nbsp;'+
      '<strong style="color:#dc2626">Ajournés</strong> = ont composé ET < 70% (≠ non composés qui sont "En attente")'+
    '</div>'+

    '<div style="display:flex;gap:0;flex-wrap:wrap;">'+

      /* Camembert */
      '<div style="flex:1;min-width:270px;padding:18px 20px;border-right:1px solid #e5e7eb;">'+
        '<div style="font-weight:700;color:#03224c;font-size:.88rem;margin-bottom:4px;">'+
          '<i class="fas fa-chart-pie" style="color:#D4AF37;margin-right:6px"></i>'+
          'Répartition des '+tot_ins+' inscrits'+
        '</div>'+
        '<div style="font-size:.72rem;color:#9ca3af;margin-bottom:12px;">Base = total inscrits (100%)</div>'+
        '<canvas id="chartPie" width="220" height="220" style="max-width:220px;display:block;margin:0 auto;"></canvas>'+
        '<div style="margin-top:14px;font-size:.8rem;line-height:2.2;">'+
          '<div><span style="display:inline-block;width:12px;height:12px;background:#16a34a;border-radius:2px;margin-right:7px;vertical-align:middle"></span>'+
            '<strong style="color:#16a34a">Admis :</strong> '+tot_ok+' candidat(s) -'+
            '<strong>'+tx_ins+'% des inscrits</strong>'+
          '</div>'+
          '<div><span style="display:inline-block;width:12px;height:12px;background:#dc2626;border-radius:2px;margin-right:7px;vertical-align:middle"></span>'+
            '<strong style="color:#dc2626">Ajournés :</strong> '+tot_ko+' candidat(s) -'+
            '<strong>'+(tot_ins>0?(tot_ko/tot_ins*100).toFixed(1):0)+'% des inscrits</strong>'+
          '</div>'+
          '<div><span style="display:inline-block;width:12px;height:12px;background:#f59e0b;border-radius:2px;margin-right:7px;vertical-align:middle"></span>'+
            '<strong style="color:#ca8a04">En attente :</strong> '+tot_att+' candidat(s) -'+
            '<strong>'+(tot_ins>0?(tot_att/tot_ins*100).toFixed(1):0)+'% des inscrits</strong>'+
          '</div>'+
          '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb;color:#7c3aed;font-weight:700">'+
            '→ Taux réussite (Admis/Composé) : <strong>'+tx+'%</strong>'+
          '</div>'+
        '</div>'+
      '</div>'+

      /* Barres */
      '<div style="flex:2;min-width:280px;padding:18px 20px;">'+
        '<div style="font-weight:700;color:#03224c;font-size:.88rem;margin-bottom:4px;">'+
          '<i class="fas fa-chart-bar" style="color:#D4AF37;margin-right:6px"></i>'+
          'Taux de réussite par type (Admis ÷ Composé)'+
        '</div>'+
        '<div style="font-size:.72rem;color:#9ca3af;margin-bottom:12px;">'+
          'Ligne pointillée = seuil requis 70%&nbsp;·&nbsp;'+
          'Vert = atteint le seuil&nbsp;·&nbsp;Rouge = sous le seuil'+
        '</div>'+
        '<canvas id="chartBar" height="180"></canvas>'+
      '</div>'+

    '</div>'+

    /* Tableau récap -colonnes bien définies avec % */
    '<div style="padding:0 18px 18px;">'+
      '<div style="font-weight:700;color:#03224c;font-size:.85rem;margin:14px 0 8px;">'+
        '<i class="fas fa-table" style="color:#D4AF37;margin-right:6px"></i>'+
        'Tableau récapitulatif par type d\'examen'+
      '</div>'+
      '<table style="width:100%;border-collapse:collapse;font-size:.82rem;table-layout:fixed;">'+
        '<colgroup>'+
          '<col style="width:28%">'+
          '<col style="width:9%">'+
          '<col style="width:9%">'+
          '<col style="width:9%">'+
          '<col style="width:9%">'+
          '<col style="width:9%">'+
          '<col style="width:13%">'+
          '<col style="width:14%">'+
        '</colgroup>'+
        '<thead><tr style="background:#03224c;color:white;">'+
          '<th style="padding:8px 10px;text-align:left">Type d\'examen</th>'+
          '<th style="padding:8px;text-align:center">Inscrits</th>'+
          '<th style="padding:8px;text-align:center">Composé</th>'+
          '<th style="padding:8px;text-align:center;color:#86efac">Admis</th>'+
          '<th style="padding:8px;text-align:center;color:#fca5a5">Ajournés</th>'+
          '<th style="padding:8px;text-align:center;color:#fde68a">En attente</th>'+
          '<th style="padding:8px;text-align:center">Taux réussite<br><small style="font-weight:400;font-size:.7rem">(Admis÷Composé)</small></th>'+
          '<th style="padding:8px;text-align:center">Taux<br><small style="font-weight:400;font-size:.7rem">(Admis÷Inscrits)</small></th>'+
        '</tr></thead>'+
        '<tbody>'+lignesTypes+
        '<tr style="background:#f0f4ff;font-weight:800;border-top:2px solid #03224c;">'+
          '<td style="padding:8px 10px;text-align:left;color:#03224c">TOTAL GÉNÉRAL</td>'+
          '<td style="padding:8px;text-align:center">'+tot_ins+'</td>'+
          '<td style="padding:8px;text-align:center">'+tot_pass+'</td>'+
          '<td style="padding:8px;text-align:center;color:#16a34a">'+tot_ok+'</td>'+
          '<td style="padding:8px;text-align:center;color:#dc2626">'+tot_ko+'</td>'+
          '<td style="padding:8px;text-align:center;color:#ca8a04">'+tot_att+'</td>'+
          '<td style="padding:8px;text-align:center;font-size:1rem;color:'+(tx>=70?'#16a34a':'#dc2626')+'">'+tx+'%</td>'+
          '<td style="padding:8px;text-align:center;font-size:1rem;color:#7c3aed">'+tx_ins+'%</td>'+
        '</tr>'+
        '</tbody>'+
      '</table>'+
    '</div>'+

    /* Commentaire */
    '<div style="padding:0 18px 18px;">'+
      '<div style="font-weight:700;color:#03224c;font-size:.85rem;margin-bottom:8px;">'+
        '<i class="fas fa-comment-dots" style="color:#D4AF37;margin-right:6px"></i>'+
        'Commentaires et observations '+
        '<span style="font-weight:400;color:#9ca3af;font-size:.74rem">(éditable avant impression)</span>'+
      '</div>'+
      '<textarea id="txtComment" rows="5" '+
        'style="width:100%;border:1.5px solid #e0e7f0;border-radius:10px;padding:12px 14px;'+
        'font-size:.84rem;font-family:Candara,Calibri,sans-serif;line-height:1.6;'+
        'resize:vertical;color:#374151;outline:none;">'+
'ANAC GABON -Direction de la Sûreté & Facilitation\n'+
'Période : '+(db?fd(db)+' au '+fd(fn):'Toutes sessions')+'\n\n'+
'Bilan : '+tot_ins+' inscrits -'+tot_pass+' ont composé -'+tot_ok+' admis ('+tx+'%) -'+tot_ko+' ajournés -'+tot_att+' en attente\n\n'+
'Observations :\n\n'+
'Recommandations :\n\n'+
'Visa DG :'+
      '</textarea>'+
    '</div>'+

  '</div>';
}


function kpi2(v,l,c,sub){
  return '<div style="flex:1;text-align:center;padding:14px 8px;border-right:1px solid #e5e7eb;min-width:80px;">'+
    '<div style="font-size:1.45rem;font-weight:800;color:'+c+'">'+v+'</div>'+
    '<div style="font-size:.68rem;color:#374151;text-transform:uppercase;font-weight:700;margin-top:2px">'+l+'</div>'+
    (sub?'<div style="font-size:.62rem;color:#9ca3af;margin-top:2px;line-height:1.3">'+sub+'</div>':'')+
  '</div>';
}

/* ── Graphiques Canvas ── */
function drawCharts(data) {
  /* Recomputer from candidats -same logic as buildSummary */
  var tot_ins=0, tot_pass=0, tot_ok=0, tot_ko=0, tot_att=0;
  data.groupes.forEach(function(g){
    g.candidats.forEach(function(c){
      tot_ins++;
      if (c.a_passe){ tot_pass++; if(c.reussite) tot_ok++; else tot_ko++; }
      else { tot_att++; }
    });
  });

  /* --- Camembert (base = tot_ins) --- */
  var cvP = document.getElementById('chartPie');
  if (cvP && tot_ins > 0) {
    var ctx = cvP.getContext('2d');
    ctx.clearRect(0, 0, cvP.width, cvP.height);
    var slices = [
      {v:tot_ok,  c:'#16a34a', lbl:'Admis'},
      {v:tot_ko,  c:'#dc2626', lbl:'Ajournés'},
      {v:tot_att, c:'#f59e0b', lbl:'En attente'},
    ];
    var start = -Math.PI/2;
    var cx = cvP.width/2, cy = cvP.height/2, r = Math.min(cx,cy)-8;
    slices.forEach(function(s){
      if (!s.v) return;
      var angle = (s.v / tot_ins) * 2 * Math.PI;
      ctx.beginPath(); ctx.moveTo(cx,cy);
      ctx.arc(cx,cy,r,start,start+angle);
      ctx.closePath();
      ctx.fillStyle=s.c; ctx.fill();
      ctx.strokeStyle='white'; ctx.lineWidth=3; ctx.stroke();
      /* % dans la tranche si assez grande */
      var midAngle = start + angle/2;
      var pct = (s.v/tot_ins*100).toFixed(0)+'%';
      if (s.v/tot_ins > 0.08) {
        var tx2 = cx + Math.cos(midAngle)*r*0.68;
        var ty2 = cy + Math.sin(midAngle)*r*0.68;
        ctx.fillStyle='white'; ctx.font='bold 11px Candara,sans-serif';
        ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.fillText(pct, tx2, ty2);
      }
      start += angle;
    });
    /* Trou central */
    ctx.beginPath(); ctx.arc(cx,cy,r*0.46,0,2*Math.PI);
    ctx.fillStyle='white'; ctx.fill();
    /* Taux admis/composé au centre */
    var tx_c = tot_pass>0 ? (tot_ok/tot_pass*100).toFixed(0) : 0;
    ctx.fillStyle='#03224c'; ctx.font='bold 17px Candara,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(tx_c+'%', cx, cy-10);
    ctx.font='9px Candara,sans-serif'; ctx.fillStyle='#9ca3af';
    ctx.fillText(tot_ok+' admis', cx, cy+5);
    ctx.fillText('sur '+tot_pass+' composé', cx, cy+16);
  }

  /* --- Barres (taux Admis÷Composé par type) --- */
  var cvB = document.getElementById('chartBar');
  if (!cvB || !data.groupes.length) return;
  var ctx2 = cvB.getContext('2d');
  var labels=[], vals=[], passes_arr=[], ok_arr=[], colors=[];
  data.groupes.forEach(function(g){
    var pass_g = g.candidats.filter(function(c){return c.a_passe;}).length;
    var ok_g   = g.candidats.filter(function(c){return c.reussite;}).length;
    labels.push(g.code);
    passes_arr.push(pass_g);
    ok_arr.push(ok_g);
    var tx_g = pass_g > 0 ? +(ok_g/pass_g*100).toFixed(1) : 0;
    vals.push(tx_g);
    colors.push(tx_g >= 70 ? '#16a34a' : '#dc2626');
  });

  var W=cvB.clientWidth||400, H=180, pad=32, bw=Math.min(56,(W-pad*2)/Math.max(labels.length,1)-14);
  cvB.width=W; cvB.height=H;

  /* Grille */
  ctx2.strokeStyle='#e5e7eb'; ctx2.lineWidth=1; ctx2.setLineDash([]);
  [0,25,50,75,100].forEach(function(v){
    var y=H-pad-(v/100)*(H-pad*1.8);
    ctx2.beginPath(); ctx2.moveTo(pad,y); ctx2.lineTo(W-10,y); ctx2.stroke();
    ctx2.fillStyle='#9ca3af'; ctx2.font='10px Candara,sans-serif';
    ctx2.textAlign='right'; ctx2.fillText(v+'%',pad-4,y+3);
  });
  /* Seuil 70% */
  var y70=H-pad-(70/100)*(H-pad*1.8);
  ctx2.strokeStyle='#D4AF37'; ctx2.lineWidth=2; ctx2.setLineDash([5,4]);
  ctx2.beginPath(); ctx2.moveTo(pad,y70); ctx2.lineTo(W-10,y70); ctx2.stroke();
  ctx2.setLineDash([]);
  ctx2.fillStyle='#D4AF37'; ctx2.font='bold 9px Candara,sans-serif';
  ctx2.textAlign='left'; ctx2.fillText('Seuil 70%',W-56,y70-4);

  /* Barres */
  labels.forEach(function(lbl,i){
    var x=pad+14+i*(bw+16), v=vals[i];
    var barH=Math.max((v/100)*(H-pad*1.8), 2);
    var y=H-pad-barH;
    /* Barre avec dégradé */
    var grd=ctx2.createLinearGradient(x,y,x,H-pad);
    grd.addColorStop(0,colors[i]);
    grd.addColorStop(1,colors[i]+'99');
    ctx2.fillStyle=grd;
    ctx2.fillRect(x,y,bw,barH);
    /* Valeur au-dessus */
    ctx2.fillStyle='#374151'; ctx2.font='bold 11px Candara,sans-serif';
    ctx2.textAlign='center';
    ctx2.fillText(v+'%', x+bw/2, Math.max(y-5, 12));
    /* Admis/Composé sous % */
    ctx2.fillStyle='#9ca3af'; ctx2.font='9px Candara,sans-serif';
    ctx2.fillText(ok_arr[i]+'/'+passes_arr[i], x+bw/2, Math.max(y-16, 6));
    /* Label type */
    ctx2.fillStyle='#03224c'; ctx2.font='bold 12px Candara,sans-serif';
    ctx2.fillText(lbl, x+bw/2, H-6);
  });
}

function bloc(g){
  var ic={AS:'shield-alt',IF:'eye',INST:'chalkboard-teacher',SENS:'bell',FORM:'graduation-cap'};
  var t=g.code==='IF'?tIF(g):g.code==='FORM'?tFORM(g):tSTD(g);
  /* Recalcul fiable depuis les candidats */
  var ins  = g.candidats.length;
  var pass = g.candidats.filter(function(c){return c.a_passe;}).length;
  var ok   = g.candidats.filter(function(c){return c.reussite;}).length;
  var ko   = pass - ok;
  var att  = ins - pass;
  var tx   = pass>0?(ok/pass*100).toFixed(1):0;
  return '<div class="tb"><div class="tbh">'+
    '<i class="fas fa-'+(ic[g.code]||'clipboard-check')+'"></i>'+
    '<span class="bc">'+eh(g.code)+'</span>'+eh(g.nom)+
    '<span style="margin-left:auto;font-size:.77rem;opacity:.8">'+
    g.sessions.map(function(s){return eh(s.nom);}).join(' | ')+'</span></div>'+
    '<div class="tbb">'+t+
    '<div class="sm">'+
    '<div class="ms"><span>Inscrits :</span>'+ins+'</div>'+
    '<div class="ms" style="color:#0ea5e9"><span>Composé :</span>'+pass+'</div>'+
    '<div class="ms" style="color:#ca8a04"><span>En attente :</span>'+att+'</div>'+
    '<div class="ms ok"><span>Admis :</span>'+ok+'</div>'+
    '<div class="ms ko"><span>Ajournés :</span>'+ko+'</div>'+
    '<div class="ms" style="color:'+(tx>=70?'#16a34a':'#dc2626')+'">'+
      '<span>Taux réussite (Admis÷Composé) :</span><strong>'+tx+'%</strong>'+
    '</div>'+
    '<div class="ms"><span>Seuil requis :</span>'+g.seuil+'%</div>'+
    '</div></div></div>';
}

function tIF(g){
  var rows='',r=1;
  var s=g.candidats.slice().sort(function(a,b){return (b.moy_if||0)-(a.moy_if||0);});
  s.forEach(function(c){
    var rc=['','r1','r2','r3'][r]||'rn';
    var pt=c.pct_theo!==null?parseFloat(c.pct_theo).toFixed(1)+'%':'-';
    var pp=c.pct_prat!==null?parseFloat(c.pct_prat).toFixed(1)+'%':'-';
    var moy=c.moy_if!==null?parseFloat(c.moy_if).toFixed(1)+'%':'-';
    rows+='<tr>'+
      '<td><div class="rb '+rc+'">'+(r++)+'</div></td>'+
      '<td class="tl" style="font-weight:700">'+eh(c.nom)+'</td>'+
      '<td><span style="background:#03224c;color:#fff;padding:2px 8px;border-radius:20px;font-size:.74rem">'+eh(c.code)+'</span></td>'+
      '<td style="font-size:.75rem;color:#6c7a8d">'+eh(c.orga||'-')+'</td>'+
      '<td style="font-weight:700">'+(c.note_theo!==null?eh(c.note_theo):'-')+'</td>'+
      '<td class="'+pc(c.pct_theo,70)+'">'+pt+'</td>'+
      '<td>'+bth(c.reussite_theo)+'</td>'+
      '<td style="font-weight:700">'+(c.note_prat!==null?eh(c.note_prat):'-')+'</td>'+
      '<td class="'+pc(c.pct_prat,70)+'">'+pp+'</td>'+
      '<td class="'+pc(c.moy_if,70)+'" style="font-weight:800;font-size:.88rem">'+moy+'</td>'+
      '<td>'+bdec(c.a_passe, c.reussite)+'</td>'+
      '</tr>';
  });
  return '<table class="rt"><thead><tr>'+
    '<th>Rang</th><th class="tl">Candidat</th><th>Code</th><th>Organisme</th>'+
    '<th>Note Théorie</th><th>% Théorie</th><th>Résul.Théo</th>'+
    '<th>Note Pratique</th><th>% Pratique</th><th>Moy.IF</th><th>Décision</th>'+
    '</tr></thead><tbody>'+rows+'</tbody></table>';
}

function tFORM(g){
  var mods=g.modules||[];
  var th=mods.map(function(m){return '<th colspan="2">Mod.'+m.num+'<br><small style="font-weight:400;font-size:.68rem">'+eh(m.nom.substring(0,18))+'</small></th>';}).join('');
  var sh=mods.map(function(){return '<th>Note</th><th>%</th>';}).join('');
  var rows='',r=1;
  var s=g.candidats.slice().sort(function(a,b){return (b.moy||0)-(a.moy||0);});
  s.forEach(function(c){
    var rc=['','r1','r2','r3'][r]||'rn';
    var mc=mods.map(function(m){
      var ev=c.modules?c.modules[m.idmodule]:null;
      var p=ev?parseFloat(ev.pct):null;
      return '<td style="font-weight:700">'+(ev?eh(ev.note):'-')+'</td>'+
             '<td class="'+pc(p,70)+'">'+(ev?p.toFixed(1)+'%':'-')+'</td>';
    }).join('');
    var mp=c.moy!==null?parseFloat(c.moy):null;
    rows+='<tr>'+
      '<td><div class="rb '+rc+'">'+(r++)+'</div></td>'+
      '<td class="tl" style="font-weight:700">'+eh(c.nom)+'</td>'+
      '<td>'+eh(c.code)+'</td>'+
      '<td style="font-size:.75rem;color:#6c7a8d">'+eh(c.orga||'-')+'</td>'+
      mc+
      '<td style="font-weight:800">'+(c.total_pts||'-')+'</td>'+
      '<td class="'+pc(mp,70)+'" style="font-weight:800">'+(mp!==null?mp.toFixed(1)+'%':'-')+'</td>'+
      '<td>'+bdec(c.a_passe,c.reussite)+'</td>'+
      '</tr>';
  });
  return '<table class="rt"><thead>'+
    '<tr><th rowspan="2">Rang</th><th rowspan="2" class="tl">Candidat</th><th rowspan="2">Code</th><th rowspan="2">Organisme</th>'+
    th+'<th rowspan="2">Total</th><th rowspan="2">Moy.</th><th rowspan="2">Décision</th></tr>'+
    '<tr>'+sh+'</tr></thead><tbody>'+rows+'</tbody></table>';
}

function tSTD(g){
  var rows='',r=1;
  var s=g.candidats.slice().sort(function(a,b){return (b.pct||0)-(a.pct||0);});
  s.forEach(function(c){
    var rc=['','r1','r2','r3'][r]||'rn';
    var pct=c.pct!==null?parseFloat(c.pct):null;
    rows+='<tr>'+
      '<td><div class="rb '+rc+'">'+(r++)+'</div></td>'+
      '<td class="tl" style="font-weight:700">'+eh(c.nom)+'</td>'+
      '<td><span style="background:#03224c;color:#fff;padding:2px 8px;border-radius:20px;font-size:.74rem">'+eh(c.code)+'</span></td>'+
      '<td style="font-size:.75rem;color:#6c7a8d">'+eh(c.orga||'-')+'</td>'+
      '<td style="font-size:.75rem">'+eh(c.poste||'-')+'</td>'+
      '<td style="font-weight:800">'+(c.note!==null?eh(c.note):'-')+'</td>'+
      '<td class="'+pc(pct,g.seuil)+'" style="font-weight:800">'+(pct!==null?pct.toFixed(1)+'%':'-')+'</td>'+
      '<td>'+bdec(c.a_passe,c.reussite)+'</td>'+
      '</tr>';
  });
  return '<table class="rt"><thead><tr>'+
    '<th>Rang</th><th class="tl">Candidat</th><th>Code</th><th>Organisme</th><th>Poste</th>'+
    '<th>Note / Sur</th><th>Pourcentage</th><th>Décision</th>'+
    '</tr></thead><tbody>'+rows+'</tbody></table>';
}

/* ── Utilitaires ── */
function eh(s){ if(s===null||s===undefined)return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fd(d){ if(!d)return ''; var p=d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
function pc(p,seuil){
  if(p===null||p===undefined||p==='')return '';
  p=parseFloat(p); seuil=parseFloat(seuil)||70;
  if(isNaN(p))return '';
  return p>=seuil?'ok':(p>=seuil*0.875?'md':'ko');
}

/* ── Badge décision : si non composé → tiret simple ── */
function bdec(a_passe, reussite) {
  if (!a_passe) return '<span style="color:#9ca3af;font-size:.85rem">-</span>';
  return reussite
    ? '<span class="bok">ADMIS</span>'
    : '<span class="bko">AJOURNÉ</span>';
}
/* ── Badge OK/NON pour théorie IF : si null → tiret ── */
function bth(val) {
  if (val === null || val === undefined) return '<span style="color:#9ca3af">-</span>';
  return val ? '<span class="bok">OK</span>' : '<span class="bko">NON</span>';
}
</script>
</body>
</html>
<?php $conn->close(); ?>