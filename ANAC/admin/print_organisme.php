<?php
/**
 * print_organisme.php — Rapport d'une entité/organisme
 * BDD : quiz_app_du + si_anac
 * 
 * SEUIL UNIQUE : Score ≥ 70% = RÉUSSI / VALIDÉ
 *                Score < 70% = ÉCHEC / AJOURNÉ
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$idorga = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$idorga) die("ID organisme manquant.");

define('SEUIL_GLOBAL', 70);

// Infos organisme (depuis si_anac)
$orga = $conn->query("SELECT * FROM si_anac.organisme WHERE idorga=$idorga")->fetch_assoc();
if (!$orga) die("Organisme introuvable.");

// Candidats de cet organisme avec leurs résultats
$resultats_q = $conn->query("
    SELECT
        c.idcandidat,
        c.code_acces,
        s.nomstagiaire,
        s.prenomstagiaire,
        s.postestagiaire,
        o.nomorga,
        te.code AS tc,
        te.nom_fr AS tn,
        r.id_session,
        r.note_finale,
        r.note_sur,
        r.pourcentage,
        r.reussite,
        r.note_theorique,
        r.note_pratique,
        r.moyenne_if,
        r.locked,
        r.reason,
        r.date_fin,
        se.nom_session,
        se.type_session
    FROM si_anac.stagiaire s
    JOIN candidat c ON c.idstagiaire = s.idstagiaire
    LEFT JOIN resultats r ON r.idcandidat = c.idcandidat
    LEFT JOIN session_examen se ON se.id_session = r.id_session
    LEFT JOIN type_examen te ON te.idtype_examen = r.idtype_examen
    LEFT JOIN si_anac.organisme o ON o.idorga = s.idorga
    WHERE s.idorga = $idorga
    ORDER BY te.code, s.nomstagiaire, s.prenomstagiaire, r.date_fin DESC
");

$rows = $resultats_q->fetch_all(MYSQLI_ASSOC);

$total_candidats = $conn->query("
    SELECT COUNT(DISTINCT c.idcandidat)
    FROM si_anac.stagiaire s JOIN candidat c ON c.idstagiaire=s.idstagiaire
    WHERE s.idorga=$idorga
")->fetch_row()[0];

// Recalculer les statistiques selon seuil 70%
$total_examens = 0;
$total_reussites = 0;
foreach ($rows as $r) {
    if ($r['id_session'] !== null) {
        $total_examens++;
        // Recalculer réussite selon seuil 70%
        if (floatval($r['pourcentage']) >= SEUIL_GLOBAL) {
            $total_reussites++;
        }
    }
}
$taux = $total_examens > 0 ? round($total_reussites / $total_examens * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport — <?= htmlspecialchars($orga['nomorga']) ?></title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<style>
*{font-family:'Candara',Arial,sans-serif;box-sizing:border-box;margin:0;padding:0;}
body{background:#f0f3f9;padding:20px;font-size:13px;}

.no-print{text-align:center;margin-bottom:20px;display:flex;gap:10px;justify-content:center;}
.btn-print{background:#03224c;color:white;border:none;padding:10px 28px;border-radius:25px;
           font-size:14px;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}
.btn-close-win{background:#6b7280;color:white;border:none;padding:10px 24px;
               border-radius:25px;font-size:14px;cursor:pointer;}

.document{border:2px solid #03224c;background:white;max-width:1100px;
          margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,.15);}

/* En-tête */
.doc-header{border-bottom:3px solid #D4AF37;padding:0;}
.doc-header img{width:100%;max-height:90px;object-fit:contain;display:block;}
.doc-title-bar{background:#03224c;color:white;text-align:center;padding:14px 20px;}
.doc-title-bar h1{font-size:18px;font-weight:bold;letter-spacing:1px;margin-bottom:4px;}
.doc-title-bar h2{font-size:14px;font-weight:normal;color:#D4AF37;}

/* Infos organisme */
.orga-info{background:#e8eef7;padding:16px 24px;border-bottom:2px solid #D4AF37;}
.orga-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;}
.orga-field{font-size:12px;}
.orga-field strong{color:#03224c;display:block;font-size:10px;text-transform:uppercase;letter-spacing:.5px;}
.orga-field span{color:#1a1f2e;}

/* Stats bar */
.stats-bar{display:flex;gap:0;border-bottom:2px solid #dee2e6;}
.stat-box{flex:1;text-align:center;padding:14px 8px;border-right:1px solid #dee2e6;}
.stat-box:last-child{border-right:none;}
.stat-box .val{font-size:22px;font-weight:800;color:#03224c;}
.stat-box .lbl{font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
.stat-box.gold .val{color:#b45309;}
.stat-box.green .val{color:#166534;}
.stat-box.red .val{color:#991b1b;}

/* Corps */
.doc-body{padding:20px 24px;}
.type-section{margin-bottom:28px;}
.type-header{background:#03224c;color:white;padding:8px 14px;font-size:12px;font-weight:bold;
             letter-spacing:.5px;border-radius:4px 4px 0 0;display:flex;align-items:center;gap:8px;}
.type-header .badge-tp{background:#D4AF37;color:#03224c;padding:2px 10px;
                       border-radius:12px;font-size:10px;font-weight:800;}

table{width:100%;border-collapse:collapse;}
thead th{background:#0a3a6b;color:white;padding:7px 10px;text-align:left;
         font-size:11px;font-weight:600;letter-spacing:.3px;}
tbody tr{border-bottom:1px solid #e5e7eb;}
tbody tr:nth-child(even){background:#f8fafc;}
tbody td{padding:7px 10px;font-size:12px;vertical-align:middle;}
.rang-1{background:#fef9c3!important;font-weight:bold;}
.rang-2{background:#f1f5f9!important;}
.rang-3{background:#fff7ed!important;}

.badge-ok{background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.badge-ko{background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.badge-na{background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;}
.no-result{color:#9ca3af;font-style:italic;font-size:11px;}

/* Type pills */
.tp-AS{background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.tp-IF{background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.tp-INST{background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.tp-SENS{background:#ede9fe;color:#5b21b6;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}
.tp-FORM{background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;}

/* Pied de page */
.doc-footer{background:#f8fafc;border-top:2px solid #D4AF37;padding:12px 24px;
            display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#6b7280;}
.doc-footer img{height:28px;}

@media print{
  .no-print{display:none!important;}
  body{background:white;padding:0;}
  .document{box-shadow:none;border:2px solid #03224c;}
  table{page-break-inside:auto;}tr{page-break-inside:avoid;}
  .type-section{page-break-inside:avoid;}
  .doc-title-bar,.stats-bar,.type-header{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn-print" onclick="window.print()">🖨️ Imprimer / Sauvegarder en PDF</button>
  <button class="btn-close-win" onclick="window.close()">✕ Fermer</button>
</div>

<div class="document">

  <!-- En-tête -->
  <div class="doc-header">
    <img src="../assets/images/banierenteanac.png" alt="ANAC" onerror="this.style.display='none'">
    <div class="doc-title-bar">
      <h1>RAPPORT DE RÉSULTATS PAR ENTITÉ</h1>
      <h2><?= htmlspecialchars(
            ($orga['trigrorganisme']?'['.$orga['trigrorganisme'].'] ':'').
            strtoupper($orga['nomorga'])
          ) ?></h2>
    </div>
  </div>

  <!-- Infos organisme -->
  <div class="orga-info">
    <div style="font-size:11px;font-weight:700;color:#03224c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">
      📋 Informations de l'entité
    </div>
    <div class="orga-grid">
      <div class="orga-field">
        <strong>Dénomination</strong>
        <span><?= htmlspecialchars($orga['nomorga']) ?></span>
      </div>
      <?php if (!empty($orga['trigrorganisme'])): ?>
      <div class="orga-field">
        <strong>Trigramme</strong>
        <span><?= htmlspecialchars($orga['trigrorganisme']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($orga['typeorga'])): ?>
      <div class="orga-field">
        <strong>Type</strong>
        <span><?= htmlspecialchars($orga['typeorga']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($orga['ville_org'])): ?>
      <div class="orga-field">
        <strong>Ville</strong>
        <span><?= htmlspecialchars($orga['ville_org']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($orga['emailorga'])): ?>
      <div class="orga-field">
        <strong>Email</strong>
        <span><?= htmlspecialchars($orga['emailorga']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($orga['telorga'])): ?>
      <div class="orga-field">
        <strong>Téléphone</strong>
        <span><?= htmlspecialchars($orga['telorga']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats bar -->
  <div class="stats-bar">
    <div class="stat-box">
      <div class="val"><?= $total_candidats ?></div>
      <div class="lbl">Candidats</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $total_examens ?></div>
      <div class="lbl">Examens passés</div>
    </div>
    <div class="stat-box green">
      <div class="val"><?= $total_reussites ?></div>
      <div class="lbl">Reçus (≥70%)</div>
    </div>
    <div class="stat-box red">
      <div class="val"><?= $total_examens - $total_reussites ?></div>
      <div class="lbl">Échecs (<70%)</div>
    </div>
    <div class="stat-box gold">
      <div class="val"><?= $taux ?>%</div>
      <div class="lbl">Taux de réussite</div>
    </div>
  </div>

  <!-- Corps du rapport -->
  <div class="doc-body">
    <?php
    // Grouper par type d'examen
    $byType = [];
    foreach ($rows as $row) {
        $key = $row['tc'] ?: 'Non affecté';
        $byType[$key][] = $row;
    }

    if (empty($byType)):
    ?>
    <div style="text-align:center;padding:40px;color:#9ca3af">
      <div style="font-size:32px;margin-bottom:12px">📭</div>
      <div>Aucun résultat trouvé pour les candidats de cet organisme.</div>
    </div>
    <?php else: foreach ($byType as $typeCode => $typeRows):
      // Trier par note décroissante
      usort($typeRows, fn($a,$b)=>($b['pourcentage']??-1)<=>($a['pourcentage']??-1));
      $typeName = $typeRows[0]['tn'] ?? $typeCode;
    ?>

    <div class="type-section">
      <div class="type-header">
        <span class="badge-tp"><?= htmlspecialchars($typeCode) ?></span>
        <?= htmlspecialchars($typeName) ?>
        <span style="margin-left:auto;opacity:.75;font-size:11px"><?= count($typeRows) ?> candidat(s)</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Rang</th>
            <th>Nom & Prénom</th>
            <th>Code</th>
            <th>Session</th>
            <?php if($typeCode==='IF'): ?>
            <th>Théorie</th>
            <th>Pratique</th>
            <th>Moy. IF</th>
            <?php else: ?>
            <th>Note obtenue</th>
            <?php endif; ?>
            <th>Score</th>
            <th>Résultat (seuil 70%)</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $rang=1;
        foreach ($typeRows as $r):
          $rc='';
          if($r['pourcentage']!==null){
            if($rang==1) $rc='rang-1';
            elseif($rang==2) $rc='rang-2';
            elseif($rang==3) $rc='rang-3';
          }
          // Recalculer réussite selon seuil 70%
          $pct = $r['pourcentage'] !== null ? floatval($r['pourcentage']) : null;
          $reussite_calc = ($pct !== null && $pct >= SEUIL_GLOBAL);
        ?>
        <tr class="<?= $rc ?>">
          <td style="text-align:center;font-weight:700;color:#03224c">
            <?php
            if($r['pourcentage']!==null){
              $medals=['🥇','🥈','🥉'];
              echo isset($medals[$rang-1])?$medals[$rang-1]:$rang;
            } else echo '—';
            ?>
          </td>
          <td style="font-weight:600"><?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?></td>
          <td style="font-family:monospace;color:#0369a1"><?= htmlspecialchars($r['code_acces']) ?></td>
          <td style="font-size:11px"><?= $r['nom_session']?htmlspecialchars($r['nom_session']):'<span class="no-result">—</span>' ?>
            <?php if($r['type_session']==='theorie'): ?>
            <span style="font-size:.68rem;background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:10px;">📖Th.</span>
            <?php elseif($r['type_session']==='pratique'): ?>
            <span style="font-size:.68rem;background:#fce7f3;color:#9d174d;padding:1px 6px;border-radius:10px;">🖼️Pr.</span>
            <?php endif; ?>
          </td>
          <?php if($typeCode==='IF'): ?>
          <td><?= $r['note_theorique']!==null?round($r['note_theorique'],1).'pts':' <span class="no-result">—</span>' ?></td>
          <td><?= $r['note_pratique']!==null?round($r['note_pratique'],1).'pts':'<span class="no-result">—</span>' ?></td>
          <td style="font-weight:700;color:#03224c">
            <?php 
            $moy_if = null;
            if ($r['moyenne_if'] !== null) {
                $moy_if = round($r['moyenne_if'],1);
            } elseif ($r['note_theorique'] !== null && $r['note_pratique'] !== null) {
                $moy_if = round((floatval($r['note_theorique']) + floatval($r['note_pratique'])) / 2, 1);
            }
            echo $moy_if !== null ? $moy_if.'%' : '<span class="no-result">—</span>';
            ?>
           </span></td>
          <?php else: ?>
          <td style="font-weight:700;color:#03224c">
            <?= $r['note_finale']!==null ? round($r['note_finale'],1).'/'.round($r['note_sur'],1).' pts' : '<span class="no-result">Non passé</span>' ?>
           </span></td>
          <?php endif; ?>
          <td><?= $r['pourcentage']!==null?round($r['pourcentage'],1).'%':'—' ?></td>
          <td>
            <?php if($r['pourcentage']===null): ?>
            <span class="badge-na">—</span>
            <?php elseif($reussite_calc): ?>
            <span class="badge-ok">✓ RÉUSSI (≥70%)</span>
            <?php else: ?>
            <span class="badge-ko">✗ ÉCHEC (<70%)</span>
            <?php endif; ?>
            <?php if($r['locked']&&$r['reason']): ?>
            <div style="font-size:10px;color:#dc2626;margin-top:2px">⚠ <?= htmlspecialchars($r['reason']) ?></div>
            <?php endif; ?>
           </span></span></td>
          <td style="color:#6b7280;font-size:11px">
            <?= $r['date_fin']?date('d/m/Y H:i',strtotime($r['date_fin'])):'—' ?>
           </span></td>
         </tr>
        <?php if($r['pourcentage']!==null) $rang++; endforeach; ?>
        </tbody>
      </table>

      <!-- Résumé du groupe avec seuil 70% -->
      <?php
      $nb_p = 0;
      $nb_ok = 0;
      foreach ($typeRows as $r) {
          if ($r['pourcentage'] !== null) {
              $nb_p++;
              if (floatval($r['pourcentage']) >= SEUIL_GLOBAL) {
                  $nb_ok++;
              }
          }
      }
      $taux_groupe = $nb_p > 0 ? round($nb_ok / $nb_p * 100, 1) : 0;
      ?>
      <div style="background:#f8fafc;padding:8px 14px;border:1px solid #e5e7eb;border-top:none;font-size:11px;color:#6b7280;display:flex;gap:20px">
        <span>Passés : <strong><?= $nb_p ?></strong></span>
        <span style="color:#166534">Reçus (≥70%) : <strong><?= $nb_ok ?></strong></span>
        <span style="color:#991b1b">Échecs (<70%) : <strong><?= $nb_p-$nb_ok ?></strong></span>
        <?php if($nb_p>0): ?>
        <span style="color:#b45309">Taux : <strong><?= $taux_groupe ?>%</strong></span>
        <?php endif; ?>
      </div>
    </div>

    <?php endforeach; endif; ?>

  </div><!-- /doc-body -->

  <!-- Pied de page -->
  <div class="doc-footer">
    <div>
      <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"
           style="height:28px;vertical-align:middle;margin-right:8px" onerror="this.style.display='none'">
      <strong style="color:#03224c">ANAC GABON</strong> — Système EXASUR
    </div>
    <div>Rapport généré le <?= date('d/m/Y à H:i') ?></div>
    <div style="font-size:10px;color:#9ca3af">Confidentiel</div>
  </div>

</div><!-- /document -->
</body>
</html>
<?php $conn->close(); ?>