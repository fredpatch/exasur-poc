<?php
/**
 * rapports.php — Centre d'impression croisé ANAC GABON
 * Filtre par date_debut EXACTE + date_fin EXACTE (même session)
 * Affiche un tableau croisé par type d'examen (IF, FORM, AS, SENS, INST)
 * avec possibilité de filtrer par candidat, type, nom session
 * et d'imprimer en PDF
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Listes pour les filtres ────────────────────────────────────────── */
// Plages de dates distinctes (sessions groupées par date_debut + date_fin)
$plages_res = $conn->query("
    SELECT date_debut, date_fin,
           COUNT(*) AS nb_sessions,
           GROUP_CONCAT(DISTINCT te.code ORDER BY te.code SEPARATOR ', ') AS types
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    GROUP BY date_debut, date_fin
    ORDER BY date_debut DESC
");
$plages = [];
while ($p = $plages_res->fetch_assoc()) $plages[] = $p;

// Types d'examen
$types_res = $conn->query("SELECT * FROM type_examen ORDER BY idtype_examen");
$types_list = [];
while ($t = $types_res->fetch_assoc()) $types_list[] = $t;

// Candidats pour filtre
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Centre d'impression — ANAC GABON</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_shared.css">
<style>
* { font-family: 'Candara', 'Calibri', sans-serif !important; box-sizing: border-box; }

/* ── Barre de filtres ──────────────────────────────────────────────── */
.rpt-filter-bar {
    background: linear-gradient(135deg, #03224c, #0056b3);
    border-radius: 13px;
    padding: 20px 24px;
    margin-bottom: 24px;
    border-bottom: 4px solid #FFD700;
    box-shadow: 0 4px 18px rgba(3,34,76,.18);
}
.rpt-filter-title {
    color: #FFD700;
    font-weight: 800;
    font-size: .95rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rpt-filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 12px;
    align-items: end;
}
@media(max-width:900px){.rpt-filter-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:560px){.rpt-filter-grid{grid-template-columns:1fr;}}

.rpt-label {
    display: block;
    color: rgba(255,255,255,.8);
    font-size: .75rem;
    font-weight: 600;
    margin-bottom: 5px;
}
.rpt-input, .rpt-select {
    width: 100%;
    padding: 9px 11px;
    border: 2px solid rgba(255,255,255,.25);
    border-radius: 8px;
    background: rgba(255,255,255,.1);
    color: white;
    font-size: .87rem;
    outline: none;
    transition: border-color .25s;
    font-family: 'Candara','Calibri',sans-serif !important;
}
.rpt-input:focus,.rpt-select:focus { border-color: #FFD700; background: rgba(255,255,255,.18); }
.rpt-input::placeholder { color: rgba(255,255,255,.5); }
.rpt-select option { background: #03224c; color: white; }

.rpt-btn {
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: .87rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Candara','Calibri',sans-serif !important;
    transition: all .2s;
    white-space: nowrap;
}
.rpt-btn-apply { background: #FFD700; color: #03224c; }
.rpt-btn-apply:hover { background: #f0c500; }
.rpt-btn-print { background: white; color: #03224c; }
.rpt-btn-print:hover { background: #f0f4ff; }
.rpt-btn-reset { background: rgba(255,255,255,.15); color: white; border: 1.5px solid rgba(255,255,255,.3); }

/* Plages rapides */
.plage-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.2);
    color: white;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: .74rem;
    font-weight: 600;
    cursor: pointer;
    margin: 3px;
    transition: all .2s;
}
.plage-chip:hover, .plage-chip.active { background: #FFD700; color: #03224c; border-color: #FFD700; }

/* ── Searchable select ────────────────────────────────────────────── */
.ss-wrap { position: relative; }
.ss-display {
    width: 100%;
    padding: 9px 11px;
    border: 2px solid rgba(255,255,255,.25);
    border-radius: 8px;
    background: rgba(255,255,255,.1);
    color: white;
    font-size: .87rem;
    outline: none;
    cursor: pointer;
    font-family: 'Candara','Calibri',sans-serif !important;
}
.ss-display:focus { border-color: #FFD700; }
.ss-drop {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    background: white;
    border: 2px solid #0056b3;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(3,34,76,.18);
    z-index: 9999;
    max-height: 240px;
    overflow: hidden;
    flex-direction: column;
}
.ss-drop.open { display: flex; }
.ss-search {
    padding: 8px 12px;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    outline: none;
    font-size: .85rem;
    font-family: 'Candara','Calibri',sans-serif !important;
    flex-shrink: 0;
}
.ss-list { overflow-y: auto; flex: 1; }
.ss-opt { padding: 8px 12px; font-size: .83rem; cursor: pointer; transition: background .15s; color: #374151; }
.ss-opt:hover { background: #f0f4ff; }
.ss-opt.active { background: #dbeafe; color: #1e40af; font-weight: 700; }
.ss-opt.no-r { color: #9ca3af; font-style: italic; pointer-events: none; }

/* ── Zone résultats ────────────────────────────────────────────────── */
#resultatZone { min-height: 200px; }
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }

/* ── Bloc par type ─────────────────────────────────────────────────── */
.type-block { margin-bottom: 32px; }
.type-block-header {
    background: linear-gradient(135deg, #03224c, #0056b3);
    color: white;
    padding: 11px 18px;
    border-radius: 10px 10px 0 0;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 800;
    font-size: .95rem;
}
.type-block-header .badge-code {
    background: #FFD700;
    color: #03224c;
    padding: 3px 12px;
    border-radius: 50px;
    font-size: .8rem;
    font-weight: 800;
}
.type-block-body {
    border: 2px solid #03224c;
    border-top: none;
    border-radius: 0 0 10px 10px;
    overflow-x: auto;
}

/* ── Tableau résultats ─────────────────────────────────────────────── */
.res-table { width: 100%; border-collapse: collapse; font-size: .81rem; }
.res-table th {
    background: #f0f4ff;
    color: #03224c;
    padding: 8px 10px;
    border: 1px solid #c7d2fe;
    font-weight: 700;
    text-align: center;
    font-size: .75rem;
    white-space: nowrap;
}
.res-table th.th-l { text-align: left; }
.res-table td { border: 1px solid #e5e7eb; padding: 7px 9px; text-align: center; vertical-align: middle; }
.res-table td.td-l { text-align: left; }
.res-table tr:nth-child(even) td { background: #fafbff; }
.res-table tr:hover td { background: #f0f4ff; }
.pct-ok { color: #16a34a; font-weight: 800; }
.pct-ko { color: #dc2626; font-weight: 800; }
.pct-mid{ color: #ca8a04; font-weight: 800; }
.badge-ok  { background: #dcfce7; color: #16a34a; padding: 2px 10px; border-radius: 50px; font-weight: 800; font-size: .76rem; white-space: nowrap; }
.badge-ko  { background: #fee2e2; color: #dc2626; padding: 2px 10px; border-radius: 50px; font-weight: 800; font-size: .76rem; white-space: nowrap; }
.rang-b { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .74rem; margin: 0 auto; }
.r1{background:#FFD700;color:#03224c;} .r2{background:#e5e7eb;color:#374151;} .r3{background:#fde68a;color:#92400e;} .rn{background:#f3f4f6;color:#6b7280;}

/* Stats résumé du bloc */
.type-stats-mini {
    display: flex;
    gap: 10px;
    padding: 10px 16px;
    background: #f8faff;
    border-top: 1px solid #e5e7eb;
    font-size: .8rem;
    flex-wrap: wrap;
}
.mini-stat { font-weight: 600; color: #03224c; }
.mini-stat span { color: #6c7a8d; font-weight: 400; margin-right: 4px; }

@media print {
    .admin-sidebar,.admin-topbar,.rpt-filter-bar,.no-print-btn { display: none !important; }
    .admin-main { margin-left: 0 !important; }
    .admin-content { padding: 0 !important; }
    .type-block-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .res-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .type-block { page-break-inside: avoid; }
}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
  <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
  <div class="topbar-title"><i class="fas fa-file-chart-column"></i> États d'impression croisés</div>
  <div class="ms-auto d-flex align-items-center gap-3">
    <button class="rpt-btn no-print-btn" style="background:#03224c;color:white" onclick="window.print()">
      <i class="fas fa-print"></i> Imprimer PDF
    </button>
  </div>
</div>

<div class="admin-content">

<!-- ══ Barre de filtres ══════════════════════════════════════════════ -->
<div class="rpt-filter-bar">
  <div class="rpt-filter-title">
    <i class="fas fa-filter"></i>
    FILTRES — ÉTATS D'IMPRESSION CROISÉS
    <span style="font-size:.72rem;opacity:.7;font-weight:400;margin-left:6px">
      Filtre par date exacte de session (date début + date fin identiques)
    </span>
  </div>
  <div class="rpt-filter-grid">

    <!-- Candidat (searchable) -->
    <div>
      <label class="rpt-label"><i class="fas fa-user me-1"></i>Candidat</label>
      <div class="ss-wrap">
        <input type="text" class="ss-display" id="f_cand_display" placeholder="Tous les candidats" readonly onclick="ssToggle('cand')">
        <input type="hidden" id="f_cand_val">
        <div class="ss-drop" id="ss_drop_cand">
          <input type="text" class="ss-search" placeholder="🔍 Rechercher..." oninput="ssFilter('cand',this.value)">
          <div class="ss-list" id="ss_list_cand">
            <div class="ss-opt" data-val="" onclick="ssSelect('cand','','Tous les candidats')">Tous les candidats</div>
            <?php foreach ($cands_list as $c): ?>
            <div class="ss-opt"
                 data-val="<?= $c['idcandidat'] ?>"
                 data-s="<?= strtolower($c['nomstagiaire'].' '.$c['prenomstagiaire'].' '.$c['code_acces']) ?>"
                 onclick="ssSelect('cand','<?= $c['idcandidat'] ?>','<?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire'].' ('.$c['code_acces'].')',ENT_QUOTES) ?>')">
              <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire'].' ('.$c['code_acces'].')') ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Type examen -->
    <div>
      <label class="rpt-label"><i class="fas fa-tag me-1"></i>Type d'examen</label>
      <select id="f_type" class="rpt-select">
        <option value="">Tous les types</option>
        <?php foreach ($types_list as $t): ?>
        <option value="<?= $t['idtype_examen'] ?>"><?= $t['code'] ?> — <?= htmlspecialchars($t['nom_fr']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Date début exacte -->
    <div>
      <label class="rpt-label"><i class="fas fa-calendar-day me-1"></i>Date début (exacte)</label>
      <input type="date" id="f_debut" class="rpt-input">
    </div>

    <!-- Date fin exacte -->
    <div>
      <label class="rpt-label"><i class="fas fa-calendar-check me-1"></i>Date fin (exacte)</label>
      <input type="date" id="f_fin" class="rpt-input">
    </div>

    <!-- Boutons -->
    <div style="display:flex;flex-direction:column;gap:7px">
      <button class="rpt-btn rpt-btn-apply" onclick="lancerRecherche()">
        <i class="fas fa-search"></i> Rechercher
      </button>
      <button class="rpt-btn rpt-btn-reset" onclick="reinit()">
        <i class="fas fa-times"></i> Réinitialiser
      </button>
    </div>
  </div>

  <!-- Sélection rapide par plage de dates -->
  <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15)">
    <span style="color:rgba(255,255,255,.75);font-size:.75rem;font-weight:600;margin-right:6px">
      <i class="fas fa-bolt" style="color:#FFD700"></i> Sélection rapide :
    </span>
    <?php foreach ($plages as $pl): ?>
    <span class="plage-chip" onclick="selPlage('<?= $pl['date_debut'] ?>','<?= $pl['date_fin'] ?>')">
      <i class="fas fa-calendar-alt"></i>
      <?= date('d/m/Y',strtotime($pl['date_debut'])) ?> → <?= date('d/m/Y',strtotime($pl['date_fin'])) ?>
      <span style="opacity:.7">[<?= $pl['types'] ?>]</span>
    </span>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ Zone résultats ════════════════════════════════════════════════ -->
<div id="resultatZone">
  <div class="empty-state">
    <i class="fas fa-filter" style="color:#FFD700"></i>
    <p style="font-size:1rem;font-weight:700;color:#03224c">Appliquez un filtre pour voir les résultats</p>
    <p style="font-size:.85rem">Sélectionnez une date de session ou cliquez sur une sélection rapide ci-dessus.</p>
  </div>
</div>

</div><!-- /admin-content -->
</main>
</div>

<!-- ── Scripts ────────────────────────────────────────────────────── -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
/* ── Sidebar toggle ──────────────────────────────────────────── */
document.getElementById('sidebarToggle').addEventListener('click', () => {
  document.getElementById('adminSidebar').classList.toggle('open');
});

/* ── Searchable select ───────────────────────────────────────── */
document.addEventListener('click', (e) => {
  if (!e.target.closest('.ss-wrap')) {
    document.querySelectorAll('.ss-drop').forEach(d => d.classList.remove('open'));
  }
});

function ssToggle(key) {
  const d = document.getElementById('ss_drop_' + key);
  document.querySelectorAll('.ss-drop').forEach(dd => { if(dd!==d) dd.classList.remove('open'); });
  d.classList.toggle('open');
  if (d.classList.contains('open')) d.querySelector('.ss-search')?.focus();
}

function ssSelect(key, val, label) {
  document.getElementById('f_' + key + '_val').value = val;
  document.getElementById('f_' + key + '_display').value = label;
  document.getElementById('ss_drop_' + key).classList.remove('open');
  document.querySelectorAll('#ss_list_' + key + ' .ss-opt').forEach(opt => {
    opt.classList.toggle('active', opt.dataset.val == val);
  });
}

function ssFilter(key, q) {
  q = q.toLowerCase().trim();
  const opts = document.querySelectorAll('#ss_list_' + key + ' .ss-opt[data-val]');
  let visible = 0;
  opts.forEach(opt => {
    const match = !q || (opt.dataset.s||opt.textContent.toLowerCase()).includes(q);
    opt.style.display = match ? '' : 'none';
    if (match && opt.dataset.val) visible++;
  });
  let noR = document.querySelector('#ss_list_' + key + ' .no-r');
  if (!noR) {
    noR = document.createElement('div');
    noR.className = 'ss-opt no-r';
    noR.textContent = 'Aucun résultat';
    document.getElementById('ss_list_' + key).appendChild(noR);
  }
  noR.style.display = (visible === 0 && q) ? '' : 'none';
}

/* ── Sélection rapide de plage ───────────────────────────────── */
function selPlage(debut, fin) {
  document.getElementById('f_debut').value = debut;
  document.getElementById('f_fin').value   = fin;
  document.querySelectorAll('.plage-chip').forEach(c => c.classList.remove('active'));
  event.target.closest('.plage-chip').classList.add('active');
  lancerRecherche();
}

function reinit() {
  document.getElementById('f_debut').value = '';
  document.getElementById('f_fin').value   = '';
  document.getElementById('f_type').value  = '';
  ssSelect('cand', '', 'Tous les candidats');
  document.querySelectorAll('.plage-chip').forEach(c => c.classList.remove('active'));
  document.getElementById('resultatZone').innerHTML = `
    <div class="empty-state">
      <i class="fas fa-filter" style="color:#FFD700"></i>
      <p style="font-size:1rem;font-weight:700;color:#03224c">Appliquez un filtre pour voir les résultats</p>
    </div>`;
}

/* ── Lancer la recherche AJAX ────────────────────────────────── */
function lancerRecherche() {
  const debut   = document.getElementById('f_debut').value;
  const fin     = document.getElementById('f_fin').value;
  const type_id = document.getElementById('f_type').value;
  const cand_id = document.getElementById('f_cand_val').value;

  if ((debut && !fin) || (!debut && fin)) {
    Swal.fire({ icon:'warning', title:'Filtre incomplet',
      text:'Renseignez la date de début ET la date de fin ensemble.',
      confirmButtonColor:'#003087' });
    return;
  }
  if (!debut && !fin && !type_id && !cand_id) {
    Swal.fire({ icon:'info', title:'Aucun filtre', text:'Sélectionnez au moins une plage de dates ou un type.',
      confirmButtonColor:'#003087' });
    return;
  }

  Swal.fire({ title:'Chargement des données…',
    html: '<i class="fas fa-sync fa-spin fa-2x" style="color:#003087"></i>',
    showConfirmButton: false, allowOutsideClick: false });

  $.ajax({
    url: 'rapports_data.php',
    method: 'POST',
    data: { date_debut: debut, date_fin: fin, type_id: type_id, cand_id: cand_id },
    dataType: 'json',
    success: function(data) {
      Swal.close();
      if (data.status !== 'success') {
        Swal.fire('Erreur', data.message || 'Erreur serveur.', 'error');
        return;
      }
      afficherResultats(data, debut, fin);
    },
    error: function() {
      Swal.fire('Erreur réseau', 'Impossible de contacter le serveur.', 'error');
    }
  });
}

/* ── Affichage des résultats ─────────────────────────────────── */
function afficherResultats(data, debut, fin) {
  if (!data.groupes || data.groupes.length === 0) {
    document.getElementById('resultatZone').innerHTML = `
      <div class="empty-state">
        <i class="fas fa-inbox" style="color:#9ca3af"></i>
        <p style="font-size:1rem;font-weight:700;color:#03224c">Aucun résultat trouvé</p>
        <p>Aucune session ne correspond aux critères sélectionnés.</p>
      </div>`;
    return;
  }

  let html = '';

  // En-tête filtre actif
  html += `<div style="background:#f0f4ff;border-left:4px solid #FFD700;padding:10px 16px;border-radius:8px;margin-bottom:18px;font-size:.87rem;color:#03224c;font-weight:600">
    <i class="fas fa-filter" style="color:#FFD700;margin-right:8px"></i>
    ${debut ? `Session du <strong>${fmtDate(debut)}</strong> au <strong>${fmtDate(fin)}</strong>` : 'Filtre appliqué'}
    — <strong>${data.total_candidats}</strong> candidat(s) | <strong>${data.groupes.length}</strong> type(s) d'examen
    <button class="rpt-btn no-print-btn" style="float:right;background:#03224c;color:white;padding:5px 14px;font-size:.78rem" onclick="window.print()">
      <i class="fas fa-print"></i> Imprimer PDF
    </button>
  </div>`;

  data.groupes.forEach(groupe => {
    html += buildGroupeBlock(groupe);
  });

  document.getElementById('resultatZone').innerHTML = html;
}

function buildGroupeBlock(groupe) {
  const typeIcons = {AS:'shield',IF:'eye',INST:'chalkboard-teacher',SENS:'bell',FORM:'graduation-cap'};
  const ico = typeIcons[groupe.code] || 'clipboard-check';

  let html = `<div class="type-block">
    <div class="type-block-header">
      <i class="fas fa-${ico}"></i>
      <span class="badge-code">${groupe.code}</span>
      ${escHtml(groupe.nom)}
      <span style="margin-left:auto;font-size:.8rem;opacity:.8">
        ${groupe.sessions.map(s=>escHtml(s.nom)).join(' | ')}
      </span>
    </div>
    <div class="type-block-body">`;

  // Construit le tableau selon le type
  if (groupe.code === 'IF') {
    html += buildTableIF(groupe);
  } else if (groupe.code === 'FORM') {
    html += buildTableFORM(groupe);
  } else {
    html += buildTableStandard(groupe);
  }

  // Stats mini
  const ok  = groupe.candidats.filter(c => c.reussite).length;
  const tot = groupe.candidats.filter(c => c.a_passe).length;
  const tx  = tot > 0 ? (ok/tot*100).toFixed(1) : 0;
  html += `<div class="type-stats-mini">
    <div class="mini-stat"><span>Candidats :</span>${groupe.candidats.length}</div>
    <div class="mini-stat"><span>Passés :</span>${tot}</div>
    <div class="mini-stat pct-ok"><span>Reçus :</span>${ok}</div>
    <div class="mini-stat pct-ko"><span>Ajournés :</span>${tot-ok}</div>
    <div class="mini-stat" style="color:${tx>=80?'#16a34a':tx>=70?'#ca8a04':'#dc2626'}"><span>Taux :</span>${tx}%</div>
    <div class="mini-stat"><span>Seuil :</span>${groupe.seuil}%</div>
  </div>`;

  html += `</div></div>`;
  return html;
}

/* Tableau IF : théorie + pratique + moyenne */
function buildTableIF(g) {
  let rows = '';
  let rang = 1;
  // Tri par moy_if desc
  const sorted = [...g.candidats].sort((a,b) => (b.moy_if||0) - (a.moy_if||0));
  sorted.forEach(c => {
    const rCls = ['','r1','r2','r3'][rang] || 'rn';
    const pctT = c.pct_theo !== null ? fmtPct(c.pct_theo) : '—';
    const pctP = c.pct_prat !== null ? fmtPct(c.pct_prat) : '—';
    const moy  = c.moy_if  !== null ? fmtPct(c.moy_if)  : '—';
    rows += `<tr>
      <td><div class="rang-b ${rCls}">${rang++}</div></td>
      <td class="td-l" style="font-weight:700">${escHtml(c.nom)}</td>
      <td>${escHtml(c.code)}</td>
      <td style="font-size:.77rem;color:#6c7a8d">${escHtml(c.orga||'—')}</td>
      <td style="font-weight:700">${c.note_theo !== null ? c.note_theo : '—'}</td>
      <td class="${pctCls(c.pct_theo,80)}">${pctT}</td>
      <td>${c.reussite_theo!==null?(c.reussite_theo?'<span class="badge-ok">✅</span>':'<span class="badge-ko">❌</span>'):'—'}</td>
      <td style="font-weight:700">${c.note_prat !== null ? c.note_prat : '—'}</td>
      <td class="${pctCls(c.pct_prat,80)}">${pctP}</td>
      <td class="${pctCls(c.moy_if,80)}" style="font-weight:800;font-size:.9rem">${moy}</td>
      <td>${c.reussite?'<span class="badge-ok">🎓 ADMIS</span>':'<span class="badge-ko">❌ AJOURNÉ</span>'}</td>
    </tr>`;
  });
  return `<table class="res-table"><thead><tr>
    <th>Rang</th><th class="th-l">Candidat</th><th>Code</th><th>Organisme</th>
    <th>Note Théorie</th><th>% Théorie</th><th>Résul.Théo</th>
    <th>Note Pratique</th><th>% Pratique</th><th>Moy. IF</th><th>Décision finale</th>
  </tr></thead><tbody>${rows}</tbody></table>`;
}

/* Tableau FORM : modules en colonnes */
function buildTableFORM(g) {
  const mods = g.modules || [];
  let theadMods = mods.map(m => `<th colspan="2">Mod.${m.num}<br><small style="font-weight:400;font-size:.7rem">${escHtml(m.nom.substring(0,18)+'…')}</small></th>`).join('');
  let subMods   = mods.map(_ => '<th>Note</th><th>%</th>').join('');

  let rows = '';
  let rang = 1;
  const sorted = [...g.candidats].sort((a,b) => (b.moy||0) - (a.moy||0));
  sorted.forEach(c => {
    const rCls = ['','r1','r2','r3'][rang] || 'rn';
    let modCells = mods.map(m => {
      const ev = c.modules ? c.modules[m.idmodule] : null;
      const p = ev ? parseFloat(ev.pct) : null;
      return `<td style="font-weight:700">${ev ? ev.note : '—'}</td><td class="${pctCls(p,70)}">${ev ? p.toFixed(1)+'%' : '—'}</td>`;
    }).join('');
    const moyPct = c.moy !== null ? parseFloat(c.moy) : null;
    rows += `<tr>
      <td><div class="rang-b ${rCls}">${rang++}</div></td>
      <td class="td-l" style="font-weight:700">${escHtml(c.nom)}</td>
      <td>${escHtml(c.code)}</td>
      <td style="font-size:.77rem;color:#6c7a8d">${escHtml(c.orga||'—')}</td>
      ${modCells}
      <td style="font-weight:800">${c.total_pts || '—'}</td>
      <td class="${pctCls(moyPct,70)}" style="font-weight:800">${moyPct!==null?moyPct.toFixed(1)+'%':'—'}</td>
      <td>${c.a_passe?(c.reussite?'<span class="badge-ok">✅ ADMIS</span>':'<span class="badge-ko">❌ AJOURNÉ</span>'):'—'}</td>
    </tr>`;
  });
  return `<table class="res-table"><thead>
    <tr><th rowspan="2">Rang</th><th rowspan="2" class="th-l">Candidat</th><th rowspan="2">Code</th><th rowspan="2">Organisme</th>
    ${theadMods}<th rowspan="2">Total pts</th><th rowspan="2">Moyenne</th><th rowspan="2">Décision</th></tr>
    <tr>${subMods}</tr>
  </thead><tbody>${rows}</tbody></table>`;
}

/* Tableau standard : AS, INST, SENS */
function buildTableStandard(g) {
  let rows = '';
  let rang = 1;
  const sorted = [...g.candidats].sort((a,b) => (b.pct||0) - (a.pct||0));
  sorted.forEach(c => {
    const rCls = ['','r1','r2','r3'][rang] || 'rn';
    const pct  = c.pct !== null ? parseFloat(c.pct) : null;
    rows += `<tr>
      <td><div class="rang-b ${rCls}">${rang++}</div></td>
      <td class="td-l" style="font-weight:700">${escHtml(c.nom)}</td>
      <td>${escHtml(c.code)}</td>
      <td style="font-size:.77rem;color:#6c7a8d">${escHtml(c.orga||'—')}</td>
      <td style="font-size:.77rem">${escHtml(c.poste||'—')}</td>
      <td style="font-weight:800">${c.note !== null ? c.note : '—'}</td>
      <td class="${pctCls(pct,g.seuil)}" style="font-weight:800">${pct!==null?pct.toFixed(1)+'%':'—'}</td>
      <td>${c.a_passe?(c.reussite?'<span class="badge-ok">✅ ADMIS</span>':'<span class="badge-ko">❌ AJOURNÉ</span>'):'—'}</td>
    </tr>`;
  });
  return `<table class="res-table"><thead><tr>
    <th>Rang</th><th class="th-l">Candidat</th><th>Code</th><th>Organisme</th><th>Poste</th>
    <th>Note / Sur</th><th>Pourcentage</th><th>Décision</th>
  </tr></thead><tbody>${rows}</tbody></table>`;
}

/* ── Utilitaires ─────────────────────────────────────────────── */
function escHtml(s) {
  if (!s && s !== 0) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(d) {
  if (!d) return '';
  const [y,m,dd] = d.split('-');
  return dd+'/'+m+'/'+y;
}
function fmtPct(p) {
  return p !== null ? parseFloat(p).toFixed(1)+'%' : '—';
}
function pctCls(p, seuil) {
  if (p === null || p === undefined) return '';
  p = parseFloat(p);
  seuil = parseFloat(seuil) || 80;
  return p >= seuil ? 'pct-ok' : (p >= seuil*0.875 ? 'pct-mid' : 'pct-ko');
}
</script>
</body>
</html>
<?php $conn->close(); ?>