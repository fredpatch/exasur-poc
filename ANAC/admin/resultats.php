<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$f_sess  = intval($_GET['f_session']??0);
$f_type  = intval($_GET['f_type']??0);
$f_res   = isset($_GET['f_result'])&&$_GET['f_result']!=='' ? intval($_GET['f_result']) : null;
$f_srch  = $conn->real_escape_string($_GET['f_search']??'');
$f_cand  = intval($_GET['f_cand']??0);

$w="WHERE 1=1";
if($f_sess)        $w.=" AND r.id_session=$f_sess";
if($f_type)        $w.=" AND r.idtype_examen=$f_type";
if($f_res!==null)  $w.=" AND r.reussite=".intval($f_res);
if($f_cand)        $w.=" AND r.idcandidat=$f_cand";
if($f_srch)        $w.=" AND (s.nomstagiaire LIKE '%$f_srch%' OR s.prenomstagiaire LIKE '%$f_srch%' OR c.code_acces LIKE '%$f_srch%')";

$resultats=$conn->query("
    SELECT r.*,
           s.nomstagiaire, s.prenomstagiaire,
           c.code_acces,
           se.nom_session, se.type_session,
           te.code AS tc, te.nom_fr AS tn,
           mf.nom_module_fr, mf.numero_module
    FROM resultats r
    JOIN candidat c ON r.idcandidat=c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    JOIN session_examen se ON r.id_session=se.id_session
    JOIN type_examen te ON r.idtype_examen=te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule
    $w ORDER BY r.date_fin DESC
");
$sessions=$conn->query("SELECT id_session,nom_session FROM session_examen ORDER BY date_debut DESC");
$types_arr=[]; $tr=$conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
while($t=$tr->fetch_assoc()) $types_arr[]=$t;

$candidats_list=$conn->query("
    SELECT DISTINCT c.idcandidat, c.code_acces,
           s.nomstagiaire, s.prenomstagiaire
    FROM resultats r
    JOIN candidat c ON r.idcandidat=c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    ORDER BY s.nomstagiaire, s.prenomstagiaire
");

$tot=$conn->query("SELECT COUNT(*) FROM resultats")->fetch_row()[0];
$ok =$conn->query("SELECT COUNT(*) FROM resultats WHERE reussite=1")->fetch_row()[0];
$ko =$tot-$ok;
$tx =$tot>0?round($ok/$tot*100,1):0;
$active_page='resultats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Résultats — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.tp{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:.72rem;font-weight:700;}
.tp-AS{background:#dbeafe;color:#1e40af;}.tp-IF{background:#d1fae5;color:#065f46;}
.tp-INST{background:#fef3c7;color:#92400e;}.tp-SENS{background:#ede9fe;color:#5b21b6;}
.tp-FORM{background:#fce7f3;color:#9d174d;}
.pb{height:6px;border-radius:3px;background:#f0f0f0;width:72px;display:inline-block;vertical-align:middle;}
.pf{height:100%;border-radius:3px;}
.km{background:white;border-radius:11px;padding:14px 18px;box-shadow:0 2px 10px rgba(3,34,76,.07);flex:1;min-width:110px;border-left:3px solid transparent;}
.badge-resultat {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.78rem;
}
.badge-reussi {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #86efac;
}
.badge-echec {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">
<div class="admin-topbar">
  <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
  <div class="topbar-title"><i class="fas fa-chart-bar"></i> Résultats d'examens</div>
  <div class="ms-auto d-flex align-items-center gap-2">
    <a href="print_results.php<?= $f_sess?"?f_session=$f_sess":'' ?>" target="_blank" class="btn-anac" style="font-size:.82rem;padding:7px 14px;"><i class="fas fa-print me-1"></i>Imprimer</a>
    <i class="fas fa-user-shield text-muted me-1"></i>
    <span style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($_SESSION['admin_nom']) ?></span>
  </div>
</div>
<div class="admin-content">

<!-- KPIs -->
<div class="d-flex gap-3 mb-4 flex-wrap">
  <div class="km" style="border-left-color:#6b7280">
    <div style="font-size:1.6rem;font-weight:800;color:#374151;"><?= $tot ?></div>
    <div style="font-size:.74rem;color:#9ca3af;font-weight:600;">TOTAL EXAMENS</div>
  </div>
  <div class="km" style="border-left-color:#16a34a">
    <div style="font-size:1.6rem;font-weight:800;color:#16a34a;"><?= $ok ?></div>
    <div style="font-size:.74rem;color:#9ca3af;font-weight:600;">RÉUSSIS (≥70%)</div>
  </div>
  <div class="km" style="border-left-color:#dc2626">
    <div style="font-size:1.6rem;font-weight:800;color:#dc2626;"><?= $ko ?></div>
    <div style="font-size:.74rem;color:#9ca3af;font-weight:600;">ÉCHECS (<70%)</div>
  </div>
  <div class="km" style="border-left-color:#7c3aed">
    <div style="font-size:1.6rem;font-weight:800;color:#7c3aed;"><?= $tx ?>%</div>
    <div style="font-size:.74rem;color:#9ca3af;font-weight:600;">TAUX RÉUSSITE GLOBAL</div>
  </div>
</div>

<div class="card-admin">
  <div class="card-admin-header">
    <i class="fas fa-list me-2"></i><h5>Résultats</h5>
    <span class="badge-count ms-2"><?= $tot ?></span>
  </div>
  <div class="card-admin-body p-0">
    <div class="filter-bar" style="border-radius:0;box-shadow:none;border-bottom:1px solid var(--gray-border);flex-wrap:wrap;gap:8px;">
      <div class="filter-group" style="min-width:220px;">
        <label><i class="fas fa-user me-1" style="color:var(--gold);"></i>Candidat</label>
        <select class="form-select-admin select2-cand" id="fCand" name="f_cand" onchange="submitFilter()">
          <option value="">Tous les candidats</option>
          <?php if($candidats_list) while($cl=$candidats_list->fetch_assoc()): ?>
          <option value="<?= $cl['idcandidat'] ?>" <?= $f_cand==$cl['idcandidat']?'selected':'' ?>>
            <?= htmlspecialchars($cl['code_acces'].' — '.$cl['nomstagiaire'].' '.$cl['prenomstagiaire']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="filter-group">
        <label><i class="fas fa-search me-1"></i>Recherche rapide</label>
        <input class="form-control-admin" id="srchR" placeholder="Nom, code..." value="<?= htmlspecialchars($_GET['f_search']??'') ?>">
      </div>
      <div class="filter-group" style="max-width:130px"><label>Type</label>
        <select class="form-select-admin" id="fType" onchange="filterR()">
          <option value="">Tous</option>
          <?php foreach($types_arr as $t): ?>
          <option value="<?= $t['code'] ?>" <?= ($_GET['f_type']??'')==$t['idtype_examen']?'selected':'' ?>>
            <?= $t['code'] ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group" style="max-width:120px"><label>Résultat</label>
        <select class="form-select-admin" id="fRes" onchange="filterR()">
          <option value="">Tous</option>
          <option value="1" <?= ($f_res===1)?'selected':'' ?>>✅ Réussis</option>
          <option value="0" <?= ($f_res===0)?'selected':'' ?>>❌ Échecs</option>
        </select>
      </div>
      <div class="filter-group" style="align-self:flex-end;">
        <a href="resultats.php" class="btn-anac" style="background:#e8ecf5;color:var(--blue);border-color:#c8d0e0;font-size:.82rem;padding:7px 12px;text-decoration:none;">
          <i class="fas fa-times me-1"></i>Effacer
        </a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table-admin" id="tblR">
        <thead><tr><th>Candidat</th><th>Code</th><th>Type</th><th>Session</th><th>Note</th><th>Score</th><th>Résultat</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php
        // RÈGLE UNIQUE : Score >= 70% → RÉUSSI, sinon ÉCHEC
        while($r=$resultats->fetch_assoc()):
            $p = round($r['pourcentage'], 1);
            $tc = $r['tc'];
            $ts = $r['type_session'] ?? 'normal';
            $reussi = ($p >= 70.0);
            $col = $reussi ? '#16a34a' : '#dc2626';
        ?>
        <tr data-type="<?= $tc ?>" data-res="<?= $reussi?1:0 ?>"
            data-s="<?= strtolower($r['nomstagiaire'].' '.$r['prenomstagiaire'].' '.$r['code_acces']) ?>">
          <td><div style="font-weight:700;"><?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?></div></td>
          <td><span style="background:var(--blue);color:white;padding:3px 10px;border-radius:50px;font-weight:700;font-size:.8rem;"><?= $r['code_acces'] ?></span></td>
          <td><span class="tp tp-<?= $tc ?>"><?= $tc ?></span>
            <?php if($ts==='theorie'): ?><br><span style="font-size:.68rem;background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:20px;">📖 Théorie</span>
            <?php elseif($ts==='pratique'): ?><br><span style="font-size:.68rem;background:#fce7f3;color:#9d174d;padding:1px 7px;border-radius:20px;">🖼️ Pratique</span><?php endif; ?>
          </td>
          <td style="font-size:.82rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['nom_session']) ?>
            <?php if($r['nom_module_fr']): ?><br><span style="font-size:.7rem;color:#9ca3af;">Mod.<?= $r['numero_module'] ?></span><?php endif; ?>
          </td>
          <td><strong style="font-size:.95rem;"><?= round($r['note_finale'],1) ?></strong><span style="color:#9ca3af;font-size:.78rem;">/<?= round($r['note_sur'],1) ?>pts</span></td>
          <td>
            <div style="display:flex;align-items:center;gap:5px;">
              <div class="pb"><div class="pf" style="width:<?= min($p,100) ?>%;background:<?= $col ?>;"></div></div>
              <strong style="color:<?= $col ?>;font-size:.88rem;"><?= $p ?>%</strong>
            </div>
            <?php if(!empty($r['moyenne_if'])): ?>
            <div style="font-size:.7rem;color:#6b7280;margin-top:2px;">
              Moy.IF: <strong style="color:<?= $r['moyenne_if']>=70?'#16a34a':'#dc2626' ?>"><?= round($r['moyenne_if'],1) ?>%</strong>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if($reussi): ?>
              <span class="badge-resultat badge-reussi">
                <i class="fas fa-check-circle"></i> RÉUSSI (<?= $p ?>%)
              </span>
            <?php else: ?>
              <span class="badge-resultat badge-echec">
                <i class="fas fa-times-circle"></i> ÉCHEC (<?= $p ?>%)
              </span>
            <?php endif; ?>
            <?php if($r['locked']): ?><div style="font-size:.68rem;color:#dc2626;margin-top:3px;"><i class="fas fa-lock me-1"></i>Verrouillé</div><?php endif; ?>
            <?php if(!empty($r['reason'])): ?>
            <div style="font-size:.65rem;color:#9ca3af;margin-top:2px;max-width:140px;overflow:hidden;text-overflow:ellipsis;"
                 title="<?= htmlspecialchars($r['reason']) ?>">
                <?= htmlspecialchars(mb_substr($r['reason'],0,35)) ?>…
            </div>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;white-space:nowrap;"><?= date('d/m/Y',strtotime($r['date_fin'])) ?><br><span style="color:#9ca3af;"><?= date('H:i',strtotime($r['date_fin'])) ?></span></td>
          <td>
            <a href="view_candidat.php?id=<?= $r['idcandidat'] ?>" class="btn-icon btn-icon-view" title="Fiche"><i class="fas fa-eye"></i></a>
            <a href="print_candidat.php?id=<?= $r['idcandidat'] ?>&session=<?= $r['id_session'] ?>" target="_blank" class="btn-icon btn-icon-manage" title="Imprimer"><i class="fas fa-print"></i></a>
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
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.getElementById('st').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('open'));

$('.select2-cand').select2({
    width:'100%',
    placeholder:'Code ou nom du candidat...',
    allowClear:true,
    language:{noResults:()=>'Aucun candidat trouvé'}
});

$('.select2-cand').on('change',function(){submitFilter();});

function submitFilter(){
    const cand=document.getElementById('fCand').value;
    const srch=document.getElementById('srchR').value;
    const url=new URL(window.location);
    if(cand) url.searchParams.set('f_cand',cand); else url.searchParams.delete('f_cand');
    if(srch)  url.searchParams.set('f_search',srch); else url.searchParams.delete('f_search');
    window.location.href=url.toString();
}

function filterR(){
    const q=document.getElementById('srchR').value.toLowerCase();
    const t=document.getElementById('fType').value;
    const r=document.getElementById('fRes').value;
    let vis=0;
    document.querySelectorAll('#tblR tbody tr').forEach(row=>{
        const m=(!q||row.dataset.s.includes(q))&&(!t||row.dataset.type===t)&&(r===''||row.dataset.res===r);
        row.style.display=m?'':'none';
        if(m) vis++;
    });
    const badge=document.querySelector('.badge-count');
    if(badge) badge.textContent=vis;
}

document.getElementById('srchR').addEventListener('input',filterR);
document.getElementById('srchR').addEventListener('keypress',e=>{if(e.key==='Enter'){e.preventDefault();submitFilter();}});
document.getElementById('fType').addEventListener('change',filterR);
document.getElementById('fRes').addEventListener('change',filterR);

filterR();
</script>
</body></html>
<?php $conn->close(); ?>