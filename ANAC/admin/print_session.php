<?php
/**
 * print_session.php — État de session ANAC GABON
 * - IF (theorie ou pratique) : affiche les 2 parties + synthèse
 * - FORM : colonnes par module + total
 * - AS/INST/SENS : tableau standard note + % + décision
 * Toujours classement par mérite (pourcentage décroissant)
 * 
 * SEUIL UNIQUE : Score ≥ 70% = RÉUSSI / VALIDÉ
 *                Score < 70% = ÉCHEC / AJOURNÉ
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) die("ID session manquant");

define('SEUIL_GLOBAL', 70);

/* ── Session principale ─────────────────────────────────────────────── */
$se = $conn->query("
    SELECT se.*, te.code AS tc, te.nom_fr AS tn, te.seuil_reussite, te.a_deux_parties
    FROM session_examen se
    JOIN type_examen te ON se.idtype_examen = te.idtype_examen
    WHERE se.id_session = $id
")->fetch_assoc();
if (!$se) die("Session introuvable");

$code_type = $se['tc'];

/* ── Pour IF : trouver la session "jumelle" (même dates, même type, autre partie) */
$sess_jumelle = null;
$sess_theo    = null;
$sess_prat    = null;
if ($code_type === 'IF') {
    $other = $conn->query("
        SELECT * FROM session_examen
        WHERE idtype_examen = {$se['idtype_examen']}
          AND date_debut = '{$se['date_debut']}'
          AND date_fin   = '{$se['date_fin']}'
          AND id_session <> $id
    ")->fetch_assoc();
    $sess_jumelle = $other;

    // Identifier qui est théorie et qui est pratique
    if ($se['type_session'] === 'theorique') {
        $sess_theo = $se;
        $sess_prat = $other;
    } else {
        $sess_prat = $se;
        $sess_theo = $other;
    }
}

/* ── Pour FORM : modules de la session ─────────────────────────────── */
$modules = [];
if ($code_type === 'FORM' && $se['idmodule']) {
    $mod_res = $conn->query("
        SELECT mf.*
        FROM module_formation mf
        WHERE mf.idmodule = {$se['idmodule']}
    ");
    if ($mod_res) while ($m = $mod_res->fetch_assoc()) $modules[] = $m;
}
// Tous les modules si session FORM générale
if ($code_type === 'FORM' && !$se['idmodule']) {
    $mod_res = $conn->query("
        SELECT DISTINCT mf.*
        FROM module_formation mf
        JOIN evaluation_module em ON em.idmodule = mf.idmodule
        WHERE em.id_session = $id
        ORDER BY mf.numero_module
    ");
    if ($mod_res) while ($m = $mod_res->fetch_assoc()) $modules[] = $m;
}

/* ── Candidats de la session ────────────────────────────────────────── */
$candidats_res = $conn->query("
    SELECT c.idcandidat, c.code_acces,
           s.nomstagiaire, s.prenomstagiaire, s.postestagiaire,
           o.nomorga
    FROM candidat_session cs
    JOIN candidat c ON cs.idcandidat = c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga = o.idorga
    WHERE cs.id_session = $id AND cs.habilite = 1
    ORDER BY s.nomstagiaire, s.prenomstagiaire
");
$candidats = [];
while ($row = $candidats_res->fetch_assoc()) $candidats[] = $row;
$nb_candidats = count($candidats);

/* ── Résultats selon le type ────────────────────────────────────────── */
// Résultat de la session principale
$res_main = [];
$r_main = $conn->query("
    SELECT r.*, c.code_acces, s.nomstagiaire, s.prenomstagiaire
    FROM resultats r
    JOIN candidat c ON r.idcandidat = c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    WHERE r.id_session = $id
");
if ($r_main) while ($row = $r_main->fetch_assoc()) $res_main[$row['idcandidat']] = $row;

// Résultat de la session jumelle IF
$res_jum = [];
if ($sess_jumelle) {
    $r_jum = $conn->query("
        SELECT r.* FROM resultats r WHERE r.id_session = {$sess_jumelle['id_session']}
    ");
    if ($r_jum) while ($row = $r_jum->fetch_assoc()) $res_jum[$row['idcandidat']] = $row;
}

// Évaluations de modules FORM
$form_evals = [];
if ($code_type === 'FORM') {
    $ev_res = $conn->query("
        SELECT em.*, mf.idmodule AS module_id
        FROM evaluation_module em
        JOIN module_formation mf ON em.idmodule = mf.idmodule
        WHERE em.id_session = $id
    ");
    if ($ev_res) while ($ev = $ev_res->fetch_assoc()) {
        $form_evals[$ev['idcandidat']][$ev['module_id']] = $ev;
    }
}

/* ── Calcul des statistiques pour la synthèse (basées sur seuil 70%) ── */
$nb_ok = 0; $nb_exam = 0;
foreach ($candidats as $cand) {
    $r = $res_main[$cand['idcandidat']] ?? null;
    if ($r) {
        $nb_exam++;
        // Recalculer réussite selon seuil 70%
        if (floatval($r['pourcentage']) >= SEUIL_GLOBAL) $nb_ok++;
    }
}
$tx = $nb_exam > 0 ? round($nb_ok / $nb_exam * 100, 1) : 0;
$nb_q = (int)$conn->query("SELECT COUNT(*) FROM session_questions WHERE session_id = $id")->fetch_row()[0];

/* ── Construction du tableau de mérite (trié par pourcentage desc) ─── */
$classement = [];
foreach ($candidats as $cand) {
    $cid = $cand['idcandidat'];
    $r   = $res_main[$cid] ?? null;
    $rj  = $res_jum[$cid]  ?? null;

    $pct_tri = 0;
    if ($r) $pct_tri = (float)$r['pourcentage'];

    $classement[] = [
        'cand'    => $cand,
        'r_main'  => $r,
        'r_jum'   => $rj,
        'pct_tri' => $pct_tri,
        'form_ev' => $form_evals[$cid] ?? [],
    ];
}
// Tri par mérite décroissant
usort($classement, fn($a, $b) => $b['pct_tri'] <=> $a['pct_tri']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Session <?= htmlspecialchars($se['nom_session']) ?> — ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { font-family: 'Candara', 'Calibri', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
body { background: #f0f2f5; padding: 20px; }

.no-print {
    text-align: center;
    margin-bottom: 18px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}
.no-print button {
    padding: 9px 22px;
    border: none;
    border-radius: 9px;
    font-family: inherit;
    font-weight: 700;
    cursor: pointer;
    font-size: .9rem;
    display: flex;
    align-items: center;
    gap: 7px;
}
.btn-p { background: #03224c; color: white; }
.btn-c { background: #6b7280; color: white; }

.wrap {
    background: white;
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px;
    border: 2px solid #03224c;
    box-shadow: 0 4px 24px rgba(0,0,0,.15);
}

/* ── En-tête ────────────────────────────────────────────────────────── */
.header-img { width: 100%; max-height: 90px; object-fit: contain; margin-bottom: 16px; }
.doc-title { font-size: 1.38rem; font-weight: 800; color: #03224c; text-align: center; margin-bottom: 3px; }
.doc-sub   { text-align: center; color: #6c7a8d; font-size: .88rem; margin-bottom: 20px; }

/* ── Stats boxes ────────────────────────────────────────────────────── */
.stats-row { display: flex; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.stat-b {
    flex: 1; min-width: 100px;
    background: #f8faff;
    border-radius: 9px;
    padding: 13px 16px;
    text-align: center;
    border-left: 4px solid #03224c;
}
.stat-val { font-size: 1.6rem; font-weight: 800; color: #03224c; }
.stat-lbl { font-size: .7rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }

/* ── Tableau principal ──────────────────────────────────────────────── */
.main-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .81rem;
    margin-top: 6px;
}
.main-table th {
    background: #03224c;
    color: #FFD700;
    padding: 8px 10px;
    text-align: center;
    border: 1px solid rgba(255,255,255,.1);
    font-size: .75rem;
    white-space: nowrap;
}
.main-table th.th-left { text-align: left; }
.main-table td {
    border: 1px solid #e5e7eb;
    padding: 7px 9px;
    text-align: center;
    vertical-align: middle;
}
.main-table td.td-left { text-align: left; }
.main-table tr:nth-child(even) td { background: #fafbff; }
.main-table tr:hover td { background: #f0f4ff; }
.pct-ok { color: #16a34a; font-weight: 800; }
.pct-ko { color: #dc2626; font-weight: 800; }
.pct-mid{ color: #ca8a04; font-weight: 800; }
.res-ok  { background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 50px; font-weight: 800; font-size: .78rem; white-space: nowrap; }
.res-ko  { background: #fee2e2; color: #dc2626; padding: 3px 10px; border-radius: 50px; font-weight: 800; font-size: .78rem; white-space: nowrap; }
.rang-badge {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .78rem;
    margin: 0 auto;
}
.rang-1 { background: #FFD700; color: #03224c; }
.rang-2 { background: #e5e7eb; color: #374151; }
.rang-3 { background: #fde68a; color: #92400e; }
.rang-n { background: #f3f4f6; color: #6b7280; }

/* ── Tableau synthèse final ─────────────────────────────────────────── */
.synthese-wrap { margin-top: 32px; }
.synthese-title {
    background: linear-gradient(135deg, #FFD700, #f0c500);
    color: #03224c;
    padding: 11px 18px;
    font-weight: 800;
    font-size: .97rem;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    gap: 9px;
}
.synth-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
    border: 2px solid #FFD700;
    border-top: none;
    border-radius: 0 0 8px 8px;
    overflow: hidden;
}
.synth-table th { background: #03224c; color: #FFD700; padding: 8px 11px; text-align: center; border: 1px solid rgba(255,255,255,.1); }
.synth-table th.th-l { text-align: left; }
.synth-table td { border: 1px solid #e5e7eb; padding: 7px 10px; text-align: center; vertical-align: middle; }
.synth-table td.td-l { text-align: left; font-weight: 600; }
.synth-table tr:nth-child(even) td { background: #fafbff; }
.total-row td { background: #f0f4ff !important; font-weight: 800; border-top: 2px solid #03224c; }

/* ── Conclusion ─────────────────────────────────────────────────────── */
.concl-box {
    margin-top: 18px;
    padding: 16px 20px;
    border-radius: 8px;
    border-left: 6px solid;
    font-size: .88rem;
    display: flex;
    align-items: center;
    gap: 16px;
}
.concl-box.ok  { background: #f0fdf4; border-color: #16a34a; color: #15803d; }
.concl-box.mid { background: #fffbeb; border-color: #d97706; color: #92400e; }
.concl-box.ko  { background: #fff1f2; border-color: #dc2626; color: #b91c1c; }
.concl-ico { font-size: 2.2rem; flex-shrink: 0; }

/* ── Pied de page ───────────────────────────────────────────────────── */
.foot {
    margin-top: 24px;
    text-align: center;
    color: #9ca3af;
    font-size: .74rem;
    font-style: italic;
    border-top: 1px dashed #e5e7eb;
    padding-top: 14px;
}

@media print {
    .no-print { display: none !important; }
    body { background: white; padding: 0; }
    .wrap { box-shadow: none; }
    .main-table th, .synth-table th, .synthese-title, .stat-b {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .synthese-wrap { page-break-before: auto; }
}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn-p" onclick="window.print()"><i class="fas fa-print"></i>Imprimer / PDF</button>
  <button class="btn-c" onclick="window.close()"><i class="fas fa-times"></i>Fermer</button>
  <?php if ($code_type === 'IF' && $sess_jumelle): ?>
  <button class="btn-p" style="background:#0056b3" onclick="window.open('print_session.php?id=<?= $sess_jumelle['id_session'] ?>','_blank')">
    <i class="fas fa-external-link-alt"></i>
    Voir session <?= $sess_jumelle['type_session'] === 'theorique' ? 'Théorique' : 'Pratique' ?>
  </button>
  <?php endif; ?>
</div>

<div class="wrap">
  <img src="../assets/images/banierenteanac.png" alt="ANAC" class="header-img" onerror="this.style.display='none'">

  <?php if ($code_type === 'IF'): ?>
  <div class="doc-title">RÉSULTATS SESSION — AGENT INSPECTION FILTRAGE (IF)</div>
  <div class="doc-sub">
    <?= htmlspecialchars($se['nom_session']) ?>
    | Du <?= date('d/m/Y', strtotime($se['date_debut'])) ?> au <?= date('d/m/Y', strtotime($se['date_fin'])) ?>
    | Partie : <?= $se['type_session'] === 'theorique' ? '📖 Théorique' : '🖼️ Pratique (Imagerie)' ?>
  </div>
  <?php elseif ($code_type === 'FORM'): ?>
  <div class="doc-title">RÉSULTATS SESSION — ÉVALUATION DE FORMATION (FORM)</div>
  <div class="doc-sub">
    <?= htmlspecialchars($se['nom_session']) ?>
    | <?= date('d/m/Y', strtotime($se['date_debut'])) ?>
    <?php if ($modules): ?> | Module : <?= htmlspecialchars($modules[0]['nom_module_fr'] ?? '') ?><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="doc-title">RÉSULTATS DE SESSION — <?= htmlspecialchars($se['tc'] . ' : ' . $se['tn']) ?></div>
  <div class="doc-sub">
    <?= htmlspecialchars($se['nom_session']) ?>
    | Du <?= date('d/m/Y', strtotime($se['date_debut'])) ?> au <?= date('d/m/Y', strtotime($se['date_fin'])) ?>
    | Durée : <?= $se['duree_minutes'] ?> min | Seuil : <?= SEUIL_GLOBAL ?>%
  </div>
  <?php endif; ?>

  <!-- ── Stats ── -->
  <div class="stats-row">
    <div class="stat-b"><div class="stat-val"><?= $nb_candidats ?></div><div class="stat-lbl">Candidats</div></div>
    <div class="stat-b"><div class="stat-val"><?= $nb_q ?></div><div class="stat-lbl">Questions</div></div>
    <div class="stat-b" style="border-left-color:#16a34a">
      <div class="stat-val" style="color:#16a34a"><?= $nb_ok ?></div><div class="stat-lbl">Reçus (≥70%)</div>
    </div>
    <div class="stat-b" style="border-left-color:#dc2626">
      <div class="stat-val" style="color:#dc2626"><?= $nb_exam - $nb_ok ?></div><div class="stat-lbl">Ajournés (<70%)</div>
    </div>
    <div class="stat-b" style="border-left-color:#FFD700">
      <div class="stat-val" style="color:#d97706"><?= $tx ?>%</div><div class="stat-lbl">Taux réussite</div>
    </div>
    <?php if ($sess_jumelle): ?>
    <div class="stat-b" style="border-left-color:#0056b3;background:#eff6ff">
      <div class="stat-val" style="color:#0056b3;font-size:1rem">
        <?= ucfirst($sess_jumelle['type_session']) ?>
      </div>
      <div class="stat-lbl">Session jumelle</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ════════════════════════════════════════════════════════════════
       TYPE IF : TABLEAU AVEC 2 PARTIES (seuil 70%)
  ═════════════════════════════════════════════════════════════════ -->
  <?php if ($code_type === 'IF'): ?>

  <table class="main-table">
    <thead>
      <tr>
        <th>Rang</th>
        <th class="th-left">Candidat</th>
        <th>Code</th>
        <th>Organisme</th>
        <?php if ($se['type_session'] === 'theorique'): ?>
        <th>Note Théorie</th>
        <th>% Théorie</th>
        <th>Résultat Théorie</th>
        <?php if ($sess_prat): ?>
        <th>Note Pratique</th>
        <th>% Pratique</th>
        <th>Moy. IF</th>
        <th>Résultat IF</th>
        <?php endif; ?>
        <?php else: ?>
        <?php if ($sess_theo): ?>
        <th>Note Théorie</th>
        <th>% Théorie</th>
        <?php endif; ?>
        <th>Note Pratique</th>
        <th>% Pratique</th>
        <th>Moy. IF</th>
        <th>Résultat IF</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $rang = 1; foreach ($classement as $row):
      $cid   = $row['cand']['idcandidat'];
      $r     = $row['r_main'];
      $rj    = $row['r_jum'];

      if ($se['type_session'] === 'theorique') {
        $r_theo = $r;
        $r_prat = $rj;
      } else {
        $r_theo = $rj;
        $r_prat = $r;
      }

      $pct_theo  = $r_theo ? round((float)$r_theo['pourcentage'],1) : null;
      $pct_prat  = $r_prat ? round((float)$r_prat['pourcentage'],1) : null;
      $moy_if    = null;
      if ($r_prat && $r_prat['moyenne_if'] !== null) $moy_if = round((float)$r_prat['moyenne_if'],1);
      elseif ($pct_theo !== null && $pct_prat !== null) $moy_if = round(($pct_theo+$pct_prat)/2,1);

      // RÈGLE UNIQUE : score >= 70% = réussi
      $theo_reussite = ($pct_theo !== null && $pct_theo >= SEUIL_GLOBAL);
      $prat_reussite = ($pct_prat !== null && $pct_prat >= SEUIL_GLOBAL);
      $if_reussite = ($theo_reussite && $prat_reussite && $moy_if !== null && $moy_if >= SEUIL_GLOBAL);
      
      $pct_color = fn($p) => $p===null?'':($p>=SEUIL_GLOBAL?'pct-ok':($p>=SEUIL_GLOBAL-10?'pct-mid':'pct-ko'));

      $rang_cls = match($rang) { 1=>'rang-1', 2=>'rang-2', 3=>'rang-3', default=>'rang-n' };
    ?>
    <tr>
      <td><div class="rang-badge <?= $rang_cls ?>"><?= $rang++ ?></div></td>
      <td class="td-left" style="font-weight:700"><?= htmlspecialchars($row['cand']['nomstagiaire'].' '.$row['cand']['prenomstagiaire']) ?></td>
      <td><?= $row['cand']['code_acces'] ?></td>
      <td style="font-size:.78rem;color:#6c7a8d"><?= htmlspecialchars($row['cand']['nomorga']??'—') ?></td>

      <?php if ($se['type_session'] === 'theorique'): ?>
      <td style="font-weight:700"><?= $r_theo ? round($r_theo['note_finale'],1).'/'.$r_theo['note_sur'].' pts' : '—' ?></td>
      <td class="<?= $pct_color($pct_theo) ?>"><?= $pct_theo !== null ? $pct_theo.'%' : '—' ?></td>
      <td><?= $pct_theo !== null ? ($theo_reussite ? '<span class="res-ok">✅ RÉUSSI</span>' : '<span class="res-ko">❌ AJOURNÉ</span>') : '—' ?></td>
      <?php if ($sess_prat): ?>
      <td style="font-weight:700"><?= $r_prat ? round($r_prat['note_finale'],1).'/'.$r_prat['note_sur'].' pts' : '—' ?></td>
      <td class="<?= $pct_color($pct_prat) ?>"><?= $pct_prat !== null ? $pct_prat.'%' : '—' ?></td>
      <td class="<?= $moy_if!==null?$pct_color($moy_if):'' ?>" style="font-weight:800"><?= $moy_if !== null ? $moy_if.'%' : '—' ?></td>
      <td><?php
        if (!$r_theo && !$r_prat) echo '—';
        elseif ($if_reussite) echo '<span class="res-ok">🎓 ADMIS</span>';
        else echo '<span class="res-ko">❌ AJOURNÉ</span>';
      ?></td>
      <?php endif; ?>

      <?php else: ?>
      <?php if ($sess_theo): ?>
      <td style="font-weight:700"><?= $r_theo ? round($r_theo['note_finale'],1).'/'.$r_theo['note_sur'].' pts' : '—' ?></td>
      <td class="<?= $pct_color($pct_theo) ?>"><?= $pct_theo !== null ? $pct_theo.'%' : '—' ?></td>
      <?php endif; ?>
      <td style="font-weight:700"><?= $r_prat ? round($r_prat['note_finale'],1).'/'.$r_prat['note_sur'].' pts' : '—' ?></td>
      <td class="<?= $pct_color($pct_prat) ?>"><?= $pct_prat !== null ? $pct_prat.'%' : '—' ?></td>
      <td class="<?= $moy_if!==null?$pct_color($moy_if):'' ?>" style="font-weight:800"><?= $moy_if !== null ? $moy_if.'%' : '—' ?></td>
      <td><?php
        if (!$r_theo && !$r_prat) echo '—';
        elseif ($if_reussite) echo '<span class="res-ok">🎓 ADMIS</span>';
        else echo '<span class="res-ko">❌ AJOURNÉ</span>';
      ?></td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ════════════════════════════════════════════════════════════════
       TYPE FORM : TABLEAU PAR MODULE (seuil 70%)
  ═════════════════════════════════════════════════════════════════ -->
  <?php elseif ($code_type === 'FORM'): ?>

  <table class="main-table">
    <thead>
      <tr>
        <th>Rang</th>
        <th class="th-left">Candidat</th>
        <th>Code</th>
        <th>Organisme</th>
        <?php foreach ($modules as $mod): ?>
        <th style="min-width:90px">
          <?= htmlspecialchars('Mod.'.$mod['numero_module']) ?>
          <br><small style="font-weight:400;font-size:.7rem"><?= htmlspecialchars(substr($mod['nom_module_fr'],0,20)).'…' ?></small>
        </th>
        <th style="min-width:60px">%</th>
        <?php endforeach; ?>
        <th>Total pts</th>
        <th>Moyenne</th>
        <th>Décision</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $classement_form = [];
    foreach ($candidats as $cand) {
      $cid = $cand['idcandidat'];
      $evs = $form_evals[$cid] ?? [];
      $total_p = array_sum(array_column($evs, 'note_obtenue'));
      $total_s = array_sum(array_column($evs, 'note_sur'));
      $moy     = $total_s > 0 ? round($total_p/$total_s*100,1) : 0;
      $classement_form[] = ['cand'=>$cand,'evs'=>$evs,'total_p'=>$total_p,'total_s'=>$total_s,'moy'=>$moy];
    }
    usort($classement_form, fn($a,$b)=>$b['moy']<=>$a['moy']);
    $rang=1;
    foreach ($classement_form as $row):
      $cid = $row['cand']['idcandidat'];
      $evs = $row['evs'];
      // Recalculer réussite selon seuil 70%
      $reussite_glob = !empty($evs) && !in_array(false, array_map(fn($e)=>floatval($e['pourcentage'])>=SEUIL_GLOBAL, $evs));
      $rang_cls = match($rang){1=>'rang-1',2=>'rang-2',3=>'rang-3',default=>'rang-n'};
    ?>
    <tr>
      <td><div class="rang-badge <?= $rang_cls ?>"><?= $rang++ ?></div></td>
      <td class="td-left" style="font-weight:700"><?= htmlspecialchars($row['cand']['nomstagiaire'].' '.$row['cand']['prenomstagiaire']) ?></td>
      <td><?= $row['cand']['code_acces'] ?></td>
      <td style="font-size:.78rem;color:#6c7a8d"><?= htmlspecialchars($row['cand']['nomorga']??'—') ?></td>
      <?php foreach ($modules as $mod):
        $ev = $evs[$mod['idmodule']] ?? null;
        $pct_m = $ev ? round((float)$ev['pourcentage'],1) : null;
        $mod_reussite = ($pct_m !== null && $pct_m >= SEUIL_GLOBAL);
      ?>
      <td style="font-weight:700"><?= $ev ? round($ev['note_obtenue'],2).'/'.$ev['note_sur'] : '—' ?></td>
      <td class="<?= $mod_reussite?'pct-ok':'pct-ko' ?>"><?= $pct_m!==null?$pct_m.'%':'—' ?></td>
      <?php endforeach; ?>
      <td style="font-weight:800"><?= $row['total_s']>0?round($row['total_p'],2).'/'.$row['total_s']:'—' ?></td>
      <td class="<?= $row['moy']>=SEUIL_GLOBAL?'pct-ok':'pct-ko' ?>" style="font-weight:800"><?= $row['moy']>0?$row['moy'].'%':'—' ?></td>
      <td><?= empty($evs)?'—':($reussite_glob?'<span class="res-ok">✅ ADMIS</span>':'<span class="res-ko">❌ AJOURNÉ</span>') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ════════════════════════════════════════════════════════════════
       TYPE STANDARD : AS, INST, SENS (seuil 70%)
  ═════════════════════════════════════════════════════════════════ -->
  <?php else: ?>

  <table class="main-table">
    <thead>
      <tr>
        <th>Rang</th>
        <th class="th-left">Candidat</th>
        <th>Code</th>
        <th>Organisme</th>
        <th>Poste</th>
        <th>Note obtenue</th>
        <th>Note /sur</th>
        <th>Pourcentage</th>
        <th>Résultat</th>
      </tr>
    </thead>
    <tbody>
    <?php $rang=1; foreach ($classement as $row):
      $r = $row['r_main'];
      $pct = $r ? round((float)$r['pourcentage'],1) : null;
      $reussite = ($pct !== null && $pct >= SEUIL_GLOBAL);
      $pct_cls = $reussite ? 'pct-ok' : ($pct !== null ? 'pct-ko' : '');
      $rang_cls = match($rang){1=>'rang-1',2=>'rang-2',3=>'rang-3',default=>'rang-n'};
    ?>
    <tr>
      <td><div class="rang-badge <?= $rang_cls ?>"><?= $rang++ ?></div></td>
      <td class="td-left" style="font-weight:700"><?= htmlspecialchars($row['cand']['nomstagiaire'].' '.$row['cand']['prenomstagiaire']) ?></td>
      <td><?= $row['cand']['code_acces'] ?></td>
      <td style="font-size:.78rem;color:#6c7a8d"><?= htmlspecialchars($row['cand']['nomorga']??'—') ?></td>
      <td style="font-size:.78rem"><?= htmlspecialchars($row['cand']['postestagiaire']??'—') ?></td>
      <td style="font-weight:800"><?= $r?round($r['note_finale'],1):'—' ?></td>
      <td><?= $r?round($r['note_sur'],1):'—' ?></td>
      <td class="<?= $pct_cls ?>"><?= $pct!==null?$pct.'%':'—' ?></td>
      <td><?php
        if (!$r) echo '—';
        elseif ($reussite) echo '<span class="res-ok">✅ RÉUSSI</span>';
        else echo '<span class="res-ko">❌ AJOURNÉ</span>';
      ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ════════════════════════════════════════════════════════════════
       TABLEAU DE SYNTHÈSE FINALE
  ═════════════════════════════════════════════════════════════════ -->
  <div class="synthese-wrap">
    <div class="synthese-title">
      <i class="fas fa-table-list"></i>
      TABLEAU DE SYNTHÈSE
      — <?= htmlspecialchars($se['nom_session']) ?>
    </div>

    <table class="synth-table">
      <thead>
        <tr>
          <th class="th-l">Indicateur</th>
          <th>Valeur</th>
          <th class="th-l">Indicateur</th>
          <th>Valeur</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="td-l">Type d'examen</span></td>
          <td><strong><?= htmlspecialchars($se['tc'].' — '.$se['tn']) ?></strong></span></td>
          <td class="td-l">Date(s) de session</span></td>
          <td><?= date('d/m/Y',strtotime($se['date_debut'])) ?> → <?= date('d/m/Y',strtotime($se['date_fin'])) ?></span></td>
        </tr>
        <tr>
          <td class="td-l">Nombre de candidats</span></td>
          <td><strong><?= $nb_candidats ?></strong></span></td>
          <td class="td-l">Nombre de questions</span></td>
          <td><?= $nb_q ?></span></td>
        </tr>
        <tr>
          <td class="td-l">Candidats reçus (≥70%)</span></td>
          <td class="pct-ok"><strong><?= $nb_ok ?></strong></span></td>
          <td class="td-l">Candidats ajournés (<70%)</span></td>
          <td class="pct-ko"><strong><?= $nb_exam - $nb_ok ?></strong></span></td>
        </tr>
        <tr class="total-row">
          <td class="td-l">Taux de réussite</span></td>
          <td class="<?= $tx >= SEUIL_GLOBAL ? 'pct-ok' : 'pct-ko' ?>"><strong><?= $tx ?>%</strong></span></td>
          <td class="td-l">Seuil requis</span></td>
          <td><strong><?= SEUIL_GLOBAL ?>%</strong></span></td>
        </tr>
      </tbody>
    </table>

    <!-- Conclusion -->
    <?php
    $concl_cls = $tx >= 80 ? 'ok' : ($tx >= 60 ? 'mid' : 'ko');
    $concl_ico = $tx >= 80 ? '🏆' : ($tx >= 60 ? '📊' : '⚠️');
    $concl_txt = $tx >= 80
      ? 'Session avec un excellent taux de réussite. La majorité des candidats ont satisfait aux exigences de certification.'
      : ($tx >= 60
        ? 'Session avec un taux de réussite moyen. Des améliorations peuvent être envisagées pour les prochaines sessions.'
        : 'Taux de réussite faible. Une révision des contenus de formation et des conditions d\'examen est recommandée.');
    ?>
    <div class="concl-box <?= $concl_cls ?>">
      <div class="concl-ico"><?= $concl_ico ?></div>
      <div>
        <strong>CONCLUSION DE SESSION</strong>
        <p style="margin-top:5px;font-size:.84rem;opacity:.9"><?= $concl_txt ?></p>
        <p style="margin-top:4px;font-size:.8rem;opacity:.75">
          Session : <?= htmlspecialchars($se['nom_session']) ?> |
          <?= $nb_ok ?>/<?= $nb_exam ?> candidat(s) admis (≥<?= SEUIL_GLOBAL ?>%) |
          Taux : <?= $tx ?>%
        </p>
      </div>
    </div>
  </div>

  <div class="foot">
    Document généré le <?= date('d/m/Y à H:i') ?> — Système EXASUR ANAC GABON — Confidentiel
  </div>
</div>
</body>
</html>
<?php $conn->close(); ?>