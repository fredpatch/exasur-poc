<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

$f_sess = intval($_GET['f_session']??0);
$f_type = intval($_GET['f_type']??0);
$where  = "WHERE 1=1";
if($f_sess) $where.=" AND r.id_session=$f_sess";
if($f_type) $where.=" AND r.idtype_examen=$f_type";

$res = $conn->query("
    SELECT r.*,s.nomstagiaire,s.prenomstagiaire,c.code_acces,se.nom_session,te.code AS tc,te.nom_fr AS tn
    FROM resultats r JOIN candidat c ON r.idcandidat=c.idcandidat
    JOIN si_anac.stagiaire s ON c.idstagiaire=s.idstagiaire
    JOIN session_examen se ON r.id_session=se.id_session
    JOIN type_examen te ON r.idtype_examen=te.idtype_examen
    $where ORDER BY r.date_fin DESC
");
$tot=$conn->query("SELECT COUNT(*) FROM resultats $where")->fetch_row()[0];
$ok =$conn->query("SELECT COUNT(*) FROM resultats $where AND reussite=1")->fetch_row()[0];
$tx =$tot>0?round($ok/$tot*100,1):0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><title>Résultats — ANAC GABON</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<style>
*{font-family:'Candara',Arial,sans-serif;box-sizing:border-box;}
body{background:#f5f5f5;padding:18px;}
.wrap{border:2px solid #03224c;background:white;max-width:1100px;margin:0 auto;padding:24px;}
.no-print{text-align:center;margin-bottom:16px;}
.no-print button{margin:0 5px;padding:7px 18px;border:none;border-radius:7px;font-family:inherit;font-weight:600;cursor:pointer;}
.btn-p{background:#03224c;color:white;} .btn-c{background:#6b7280;color:white;}
.header-img{width:100%;max-height:80px;object-fit:contain;margin-bottom:12px;}
h2{color:#03224c;font-size:1.3rem;font-weight:800;text-align:center;margin-bottom:12px;}
.stats{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap;}
.sb{flex:1;min-width:90px;background:#f8faff;border-radius:7px;padding:10px;text-align:center;border-left:3px solid #03224c;}
.sv{font-size:1.4rem;font-weight:800;color:#03224c;} .sl{font-size:.7rem;color:#9ca3af;font-weight:600;}
table{width:100%;border-collapse:collapse;font-size:.78rem;}
th{background:#03224c;color:#D4AF37;padding:6px 8px;text-align:left;}
td{border:1px solid #ddd;padding:5px 7px;}tr:nth-child(even) td{background:#f9f9f9;}
.ok{color:#16a34a;font-weight:700;} .ko{color:#dc2626;font-weight:700;}
.foot{margin-top:14px;text-align:center;color:#9ca3af;font-size:.72rem;font-style:italic;}
@media print{.no-print{display:none!important;}body{background:white;padding:0;}.wrap{box-shadow:none;}}
</style>
</head>
<body>
<div class="no-print">
  <button class="btn-p" onclick="window.print()">🖨️ Imprimer</button>
  <button class="btn-c" onclick="window.close()">✕ Fermer</button>
</div>
<div class="wrap">
  <img src="../assets/images/banierenteanac.png" alt="ANAC" class="header-img" onerror="this.style.display='none'">
  <h2>RAPPORT DES RÉSULTATS D'EXAMENS — AVSEC-FAL ANAC GABON</h2>
  <div class="stats">
    <div class="sb"><div class="sv"><?= $tot ?></div><div class="sl">TOTAL</div></div>
    <div class="sb" style="border-left-color:#16a34a"><div class="sv" style="color:#16a34a"><?= $ok ?></div><div class="sl">RÉUSSIS</div></div>
    <div class="sb" style="border-left-color:#dc2626"><div class="sv" style="color:#dc2626"><?= $tot-$ok ?></div><div class="sl">ÉCHECS</div></div>
    <div class="sb" style="border-left-color:#D4AF37"><div class="sv" style="color:#D4AF37"><?= $tx ?>%</div><div class="sl">TAUX RÉUSSITE</div></div>
  </div>
  <table>
    <thead><tr><th>#</th><th>Candidat</th><th>Code</th><th>Type</th><th>Session</th><th>Note</th><th>Score</th><th>Résultat</th><th>Date</th></tr></thead>
    <tbody>
    <?php $n=1; while($r=$res->fetch_assoc()): $p=round($r['pourcentage'],1); ?>
    <tr>
      <td><?= $n++ ?></td>
      <td><strong><?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?></strong></td>
      <td><?= $r['code_acces'] ?></td>
      <td><?= $r['tc'] ?></td>
      <td style="font-size:.75rem;"><?= htmlspecialchars($r['nom_session']) ?></td>
      <td><?= round($r['note_finale'],1) ?>/<?= round($r['note_sur'],1) ?></td>
      <td><?= $p ?>%</td>
      <td><?= $r['reussite']?'<span class="ok">✅ RÉUSSI</span>':'<span class="ko">❌ ÉCHEC</span>' ?></td>
      <td style="font-size:.74rem;"><?= date('d/m/Y',strtotime($r['date_fin'])) ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <div class="foot">Rapport généré le <?= date('d/m/Y à H:i') ?> — Système AIR SECURE ANAC GABON — Confidentiel</div>
</div>
</body></html>
<?php $conn->close(); ?>