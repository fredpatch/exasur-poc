<?php
/**
 * print_results.php- Page d'impression des résultats filtrés
 * EXASUR / ANAC / admin / print_results.php
 *
 * Accepte les mêmes paramètres GET que resultats.php
 * Génère une page HTML propre, optimisée pour l'impression.
 * Sécurité : admin_id requis en session.
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Filtres (même logique que resultats.php) ──────────────────── */
$f_type  = intval($_GET['f_type']  ?? 0);
$f_deb   = $conn->real_escape_string($_GET['f_deb']  ?? '');
$f_fin   = $conn->real_escape_string($_GET['f_fin']  ?? '');
$f_res   = isset($_GET['f_res'])  && $_GET['f_res']  !== '' ? intval($_GET['f_res']) : null;
$f_srch  = $conn->real_escape_string($_GET['f_srch'] ?? '');
$f_sess  = intval($_GET['f_sess'] ?? 0);
$f_ts    = $conn->real_escape_string($_GET['f_ts']   ?? '');

$w_type  = $f_type  ? "AND se.idtype_examen = $f_type" : '';
$w_deb   = $f_deb   ? "AND se.date_debut >= '$f_deb'"  : '';
$w_fin   = $f_fin   ? "AND se.date_fin   <= '$f_fin'"   : '';
$w_sess2 = $f_sess  ? "AND se.id_session  = $f_sess"   : '';
$w_ts2   = $f_ts    ? "AND se.type_session = '$f_ts'"   : '';
$w_srch  = $f_srch  ? "AND (s.nomstagiaire LIKE '%$f_srch%'
                         OR s.prenomstagiaire LIKE '%$f_srch%'
                         OR c.code_acces LIKE '%$f_srch%')" : '';

/* ── Requête UNION identique à resultats.php ──────────────────── */
$sql = "
    SELECT
        r.id_session, r.idcandidat, c.code_acces,
        s.nomstagiaire, s.prenomstagiaire, o.nomorga,
        se.nom_session, se.date_debut AS sess_debut, se.date_fin AS sess_fin,
        se.type_session, te.code AS tc, te.nom_fr AS tn,
        mf.nom_module_fr, mf.numero_module,
        r.id AS res_id, r.note_finale, r.note_sur,
        r.pourcentage, r.reussite, r.moyenne_if,
        r.locked, r.reason, r.date_fin AS date_resultat
    FROM resultats r
    JOIN candidat c           ON r.idcandidat     = c.idcandidat
    JOIN si_anac.stagiaire s  ON c.idstagiaire    = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga     = o.idorga
    JOIN session_examen se    ON r.id_session      = se.id_session
    JOIN type_examen te       ON se.idtype_examen = te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule  = mf.idmodule
    WHERE 1=1 $w_type $w_deb $w_fin $w_sess2 $w_ts2 $w_srch

    UNION

    SELECT
        cs.id_session, cs.idcandidat, c.code_acces,
        s.nomstagiaire, s.prenomstagiaire, o.nomorga,
        se.nom_session, se.date_debut AS sess_debut, se.date_fin AS sess_fin,
        se.type_session, te.code AS tc, te.nom_fr AS tn,
        mf.nom_module_fr, mf.numero_module,
        NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL
    FROM candidat_session cs
    JOIN candidat c           ON cs.idcandidat    = c.idcandidat
    JOIN si_anac.stagiaire s  ON c.idstagiaire    = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga     = o.idorga
    JOIN session_examen se    ON cs.id_session    = se.id_session
    JOIN type_examen te       ON se.idtype_examen = te.idtype_examen
    LEFT JOIN module_formation mf ON se.idmodule  = mf.idmodule
    LEFT JOIN resultats r     ON r.idcandidat = cs.idcandidat AND r.id_session = cs.id_session
    WHERE cs.habilite = 1 AND r.id IS NULL
      $w_type $w_deb $w_fin $w_sess2 $w_ts2 $w_srch

    ORDER BY sess_debut DESC, tc, nomstagiaire, prenomstagiaire
";

$res_q = $conn->query($sql);
$rows  = [];
if ($res_q) while ($row = $res_q->fetch_assoc()) $rows[] = $row;

/* Filtre résultat côté PHP */
if ($f_res !== null) {
    if ($f_res == 2) {
        $rows = array_filter($rows, fn($r) => $r['res_id'] === null);
    } else {
        $rows = array_filter($rows, fn($r) => $r['res_id'] !== null && intval($r['reussite']) === $f_res);
    }
    $rows = array_values($rows);
}

/* KPIs */
$nb_total   = count($rows);
$nb_passes  = count(array_filter($rows, fn($r) => $r['res_id'] !== null));
$nb_attente = $nb_total - $nb_passes;
$nb_ok      = count(array_filter($rows, fn($r) => $r['res_id'] !== null && floatval($r['pourcentage']) >= 70));
$nb_ko      = $nb_passes - $nb_ok;
$tx         = $nb_passes > 0 ? round($nb_ok / $nb_passes * 100, 1) : 0;

/* Récupérer le nom du type pour l'entête */
$nom_type = '';
if ($f_type) {
    $tr = $conn->query("SELECT code,nom_fr FROM type_examen WHERE idtype_examen=$f_type");
    if ($tr && $t = $tr->fetch_assoc()) $nom_type = $t['code'].' - '.$t['nom_fr'];
}
$nom_session_label = '';
if ($f_sess) {
    $sr = $conn->query("SELECT nom_session FROM session_examen WHERE id_session=$f_sess");
    if ($sr && $ss = $sr->fetch_assoc()) $nom_session_label = $ss['nom_session'];
}

$conn->close();

/* ── Labels de filtre pour l'en-tête ── */
$filtre_parts = [];
if ($nom_type)          $filtre_parts[] = 'Type : '.$nom_type;
if ($f_ts === 'theorie')  $filtre_parts[] = 'Épreuve : Théorie';
if ($f_ts === 'pratique') $filtre_parts[] = 'Épreuve : Pratique';
if ($f_deb)             $filtre_parts[] = 'Du : '.date('d/m/Y',strtotime($f_deb));
if ($f_fin)             $filtre_parts[] = 'Au : '.date('d/m/Y',strtotime($f_fin));
if ($nom_session_label) $filtre_parts[] = 'Session : '.$nom_session_label;
if ($f_srch)            $filtre_parts[] = 'Recherche : '.$f_srch;
if ($f_res === 1)       $filtre_parts[] = 'Résultat : Réussis';
if ($f_res === 0)       $filtre_parts[] = 'Résultat : Échecs';
if ($f_res === 2)       $filtre_parts[] = 'Résultat : En attente';
$filtre_label = $filtre_parts ? implode(' | ', $filtre_parts) : 'Tous les résultats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Impression Résultats- EXASUR ANAC GABON</title>
<style>
/* ── Reset impression ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Candara', 'Calibri', Arial, sans-serif;
    font-size: 11pt;
    color: #222;
    background: white;
    padding: 12mm 14mm;
}

/* ── En-tête ── */
.print-header {
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: 3px solid #D4AF37;
    padding-bottom: 10px;
    margin-bottom: 14px;
}
.print-header img { height: 54px; }
.header-text h1 { font-size: 13pt; color: #03224c; font-weight: 800; }
.header-text p  { font-size: 9pt;  color: #6b7280; margin-top: 2px; }

/* ── Ligne filtres ── */
.filtre-bar {
    background: #f0f4fa;
    border-left: 4px solid #03224c;
    padding: 6px 12px;
    margin-bottom: 10px;
    font-size: 9pt;
    color: #374151;
    border-radius: 0 6px 6px 0;
}
.filtre-bar strong { color: #03224c; }

/* ── KPIs ── */
.kpis {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}
.kpi {
    flex: 1;
    border: 1.5px solid #e5e7eb;
    border-radius: 6px;
    padding: 6px 10px;
    text-align: center;
}
.kpi .val { font-size: 15pt; font-weight: 800; }
.kpi .lbl { font-size: 7pt; color: #9ca3af; text-transform: uppercase; margin-top: 1px; }

/* ── Tableau ── */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5pt;
}
thead tr {
    background: #03224c !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
thead th {
    color: white;
    padding: 6px 8px;
    text-align: left;
    font-weight: 700;
    font-size: 8.5pt;
}
tbody tr:nth-child(even) {
    background: #f8faff;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
tbody tr.row-attente {
    background: #fffbeb !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    opacity: 0.85;
}
tbody td {
    padding: 5px 8px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

/* ── Badges ── */
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 8pt;
    font-weight: 700;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.badge-ok      { background: #d1fae5; color: #065f46; }
.badge-ko      { background: #fee2e2; color: #991b1b; }
.badge-attente { background: #fff8e1; color: #92400e; }
.badge-type    { background: #dbeafe; color: #1e40af; }
.badge-if      { background: #d1fae5; color: #065f46; }
.badge-inst    { background: #fef3c7; color: #92400e; }
.badge-sens    { background: #ede9fe; color: #5b21b6; }
.badge-form    { background: #fce7f3; color: #9d174d; }

.code-badge {
    background: #03224c;
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 8pt;
    font-weight: 700;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ── Score bar ── */
.score-bar {
    display: inline-block;
    width: 50px;
    height: 5px;
    background: #f0f0f0;
    border-radius: 3px;
    vertical-align: middle;
    margin-right: 4px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.score-fill { height: 100%; border-radius: 3px; }

/* ── Pied de page ── */
.print-footer {
    margin-top: 16px;
    border-top: 1px solid #e5e7eb;
    padding-top: 6px;
    display: flex;
    justify-content: space-between;
    font-size: 8pt;
    color: #9ca3af;
}

/* ── Bouton imprimer (masqué à l'impression) ── */
.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #03224c;
    color: white;
    border: none;
    padding: 10px 22px;
    border-radius: 50px;
    font-family: inherit;
    font-weight: 700;
    font-size: 11pt;
    cursor: pointer;
    margin-bottom: 14px;
    transition: all .2s;
}
.btn-print:hover { background: #0a3a6b; transform: translateY(-1px); }
.btn-close {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e8ecf5;
    color: #03224c;
    border: 2px solid #c8d0e0;
    padding: 10px 22px;
    border-radius: 50px;
    font-family: inherit;
    font-weight: 700;
    font-size: 11pt;
    cursor: pointer;
    margin-bottom: 14px;
    margin-left: 8px;
    transition: all .2s;
}

@media print {
    .no-print { display: none !important; }
    body { padding: 8mm 10mm; }
    .btn-print, .btn-close { display: none !important; }
}
</style>
</head>

<body>

<!-- Boutons d'action (masqués à l'impression) -->
<div class="no-print" style="margin-bottom:12px;">
    <button class="btn-print" onclick="window.print()">
        🖨️ &nbsp;Imprimer ce document
    </button>
    <button class="btn-close" onclick="window.close()">
        ✕ &nbsp;Fermer
    </button>
</div>

<!-- En-tête -->
<div class="print-header">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON"
         onerror="this.style.display='none'">
    <div class="header-text">
        <h1>EXASUR- ANAC GABON &nbsp;·&nbsp; Résultats d'examens</h1>
        <p>Imprimé le <?= date('d/m/Y à H:i') ?> par <?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Admin') ?></p>
    </div>
</div>

<!-- Filtres actifs -->
<div class="filtre-bar">
    <strong>Filtres :</strong> <?= htmlspecialchars($filtre_label) ?>
    &nbsp;·&nbsp; <strong><?= $nb_total ?></strong> ligne(s)
</div>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi">
        <div class="val" style="color:#374151;"><?= $nb_total ?></div>
        <div class="lbl">Total inscrits</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#0ea5e9;"><?= $nb_attente ?></div>
        <div class="lbl">En attente</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#16a34a;"><?= $nb_ok ?></div>
        <div class="lbl">Réussis (≥70%)</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#dc2626;"><?= $nb_ko ?></div>
        <div class="lbl">Échecs (&lt;70%)</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#7c3aed;"><?= $tx ?>%</div>
        <div class="lbl">Taux réussite</div>
    </div>
</div>

<!-- Tableau résultats -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Candidat</th>
            <th>Code</th>
            <th>Organisme</th>
            <th>Type</th>
            <th>Épreuve</th>
            <th>Session</th>
            <th>Dates session</th>
            <th>Note</th>
            <th>Score %</th>
            <th>Résultat</th>
            <th>Date examen</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $rang = 0;
    foreach ($rows as $r):
        $rang++;
        $a_compose = ($r['res_id'] !== null);
        $p         = $a_compose ? round(floatval($r['pourcentage']), 1) : null;
        $reussi    = $a_compose && ($p >= 70.0);
        $col_score = $a_compose ? ($reussi ? '#16a34a' : '#dc2626') : '#9ca3af';
        $tc        = $r['tc'];
        $ts        = $r['type_session'] ?? 'normal';
        $row_cls   = !$a_compose ? 'row-attente' : '';
        $badge_tc  = match($tc) {
            'IF'   => 'badge-if',
            'INST' => 'badge-inst',
            'SENS' => 'badge-sens',
            'FORM' => 'badge-form',
            default=> 'badge-type',
        };
    ?>
    <tr class="<?= $row_cls ?>">
        <td style="color:#9ca3af;font-size:8pt;"><?= $rang ?></td>
        <td>
            <strong><?= htmlspecialchars($r['nomstagiaire'].' '.$r['prenomstagiaire']) ?></strong>
        </td>
        <td><span class="code-badge"><?= $r['code_acces'] ?></span></td>
        <td style="font-size:8pt;color:#6b7280;"><?= htmlspecialchars($r['nomorga'] ?? '') ?></td>
        <td><span class="badge <?= $badge_tc ?>"><?= $tc ?></span></td>
        <td style="font-size:8pt;">
            <?php if ($ts === 'theorie')  echo '📖 Théorie';
            elseif ($ts === 'pratique') echo '🖼️ Pratique';
            else echo 'Normal'; ?>
        </td>
        <td style="font-size:8pt;max-width:120px;overflow:hidden;">
            <?= htmlspecialchars(mb_substr($r['nom_session'],0,35)) ?>
            <?php if ($r['nom_module_fr']): ?>
            <br><span style="color:#9ca3af;font-size:7pt;">Mod.<?= $r['numero_module'] ?></span>
            <?php endif; ?>
        </td>
        <td style="font-size:8pt;white-space:nowrap;color:#6b7280;">
            <?= date('d/m/Y',strtotime($r['sess_debut'])) ?><br>
            → <?= date('d/m/Y',strtotime($r['sess_fin'])) ?>
        </td>
        <td style="font-size:9pt;">
            <?php if ($a_compose): ?>
            <strong><?= round($r['note_finale'],1) ?></strong>
            <span style="color:#9ca3af;font-size:7.5pt;">/<?= round($r['note_sur'],1) ?>pts</span>
            <?php else: ?>
            <span style="color:#9ca3af;">—</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($a_compose): ?>
            <span class="score-bar">
                <span class="score-fill" style="width:<?= min($p,100) ?>%;background:<?= $col_score ?>;"></span>
            </span>
            <strong style="color:<?= $col_score ?>;font-size:9pt;"><?= $p ?>%</strong>
            <?php if (!empty($r['moyenne_if'])): ?>
            <br><span style="font-size:7pt;color:#6b7280;">
                Moy.IF: <strong style="color:<?= $r['moyenne_if']>=70?'#16a34a':'#dc2626' ?>">
                    <?= round($r['moyenne_if'],1) ?>%
                </strong>
            </span>
            <?php endif; ?>
            <?php else: ?><span style="color:#9ca3af;">—</span><?php endif; ?>
        </td>
        <td>
            <?php if (!$a_compose): ?>
            <span class="badge badge-attente">⏳ Planifié</span>
            <?php elseif ($reussi): ?>
            <span class="badge badge-ok">✔ RÉUSSI</span>
            <?php else: ?>
            <span class="badge badge-ko">✘ ÉCHEC</span>
            <?php endif; ?>
            <?php if ($a_compose && $r['locked']): ?>
            <br><span style="font-size:7pt;color:#dc2626;">🔒 Verrouillé</span>
            <?php endif; ?>
        </td>
        <td style="font-size:8pt;white-space:nowrap;">
            <?php if ($a_compose && $r['date_resultat']): ?>
            <?= date('d/m/Y',strtotime($r['date_resultat'])) ?><br>
            <span style="color:#9ca3af;"><?= date('H:i',strtotime($r['date_resultat'])) ?></span>
            <?php else: ?><span style="color:#9ca3af;">—</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
    <tr><td colspan="12" style="text-align:center;padding:20px;color:#9ca3af;">
        Aucun résultat pour ces critères.
    </td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Pied de page -->
<div class="print-footer">
    <span>ANAC GABON- EXASUR- Document confidentiel</span>
    <span><?= htmlspecialchars($filtre_label) ?></span>
    <span>Total : <?= $nb_total ?> ligne(s)</span>
</div>

<script>
// Auto-print si paramètre autoprint=1
<?php if (!empty($_GET['autoprint'])): ?>
window.addEventListener('load', function() { setTimeout(function(){ window.print(); }, 800); });
<?php endif; ?>
</script>
</body>
</html>