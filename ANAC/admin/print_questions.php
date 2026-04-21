<?php
/**
 * print_questions.php — Banque de Questions EXASUR · ANAC GABON
 * ═══════════════════════════════════════════════════════════════
 * Génère un document PDF/impression de la banque de questions
 * selon les mêmes filtres que questions.php :
 *   - f_type  : idtype_examen (AS, IF, INST, SENS, FORM…)
 *   - f_tq    : type_question (theorique | pratique | '')
 *   - f_q     : texte de recherche FR/EN
 *   - f_sess  : id_session (questions liées à une session)
 *   - f_deb   : date ajout du (YYYY-MM-DD)
 *   - f_fin   : date ajout au (YYYY-MM-DD)
 *
 * SÉCURITÉ : intval() + real_escape_string() sur toutes les entrées
 *            htmlspecialchars() sur toutes les sorties HTML
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ════════════════════════════════════════════
   RÉCUPÉRATION & SÉCURISATION DES FILTRES
════════════════════════════════════════════ */
$f_type = intval($_GET['f_type'] ?? 0);
$f_tq   = in_array($_GET['f_tq'] ?? '', ['theorique','pratique']) ? $_GET['f_tq'] : '';
$f_q    = $conn->real_escape_string(trim($_GET['f_q']   ?? ''));
$f_sess = intval($_GET['f_sess'] ?? 0);
$f_deb  = $conn->real_escape_string($_GET['f_deb'] ?? '');
$f_fin  = $conn->real_escape_string($_GET['f_fin'] ?? '');

/* ════════════════════════════════════════════
   CONSTRUCTION DU WHERE
════════════════════════════════════════════ */
$where = "WHERE 1=1";
if ($f_type) $where .= " AND q.idtype_examen = $f_type";
if ($f_tq)   $where .= " AND q.type_question = '$f_tq'";
if ($f_q)    $where .= " AND (q.question_text_fr LIKE '%$f_q%' OR q.question_text_en LIKE '%$f_q%')";
if ($f_sess) $where .= " AND q.id IN (SELECT question_id FROM session_questions WHERE session_id=$f_sess)";
if ($f_deb)  $where .= " AND q.created_at >= '$f_deb 00:00:00'";
if ($f_fin)  $where .= " AND q.created_at <= '$f_fin 23:59:59'";

/* ════════════════════════════════════════════
   REQUÊTE PRINCIPALE
════════════════════════════════════════════ */
$rows = $conn->query("
    SELECT q.*, te.nom_fr AS type_nom, te.code AS type_code
    FROM question q
    LEFT JOIN type_examen te ON q.idtype_examen = te.idtype_examen
    $where
    ORDER BY q.idtype_examen ASC, q.type_question DESC, q.id ASC
")->fetch_all(MYSQLI_ASSOC);

$total = count($rows);

/* ════════════════════════════════════════════
   MÉTADONNÉES FILTRES (pour affichage)
════════════════════════════════════════════ */
$type_label = 'Toutes les questions';
$type_code_label = '';
if ($f_type) {
    $tr = $conn->query("SELECT nom_fr, code FROM type_examen WHERE idtype_examen=$f_type")->fetch_assoc();
    if ($tr) { $type_label = $tr['code'].' — '.$tr['nom_fr']; $type_code_label = $tr['code']; }
}

$sess_label = '';
if ($f_sess) {
    $sr = $conn->query("SELECT nom_session, date_debut, date_fin FROM session_examen WHERE id_session=$f_sess")->fetch_assoc();
    if ($sr) $sess_label = $sr['nom_session'].' ('.date('d/m/Y',strtotime($sr['date_debut'])).' → '.date('d/m/Y',strtotime($sr['date_fin'])).')';
}

/* Construction du résumé des filtres */
$filtres_actifs = [];
if ($f_tq)   $filtres_actifs[] = ($f_tq==='theorique') ? '📝 Théoriques uniquement' : '🖼️ Pratiques uniquement';
if ($f_q)    $filtres_actifs[] = 'Recherche : "'.$f_q.'"';
if ($sess_label) $filtres_actifs[] = 'Session : '.$sess_label;
if ($f_deb)  $filtres_actifs[] = 'Du '.date('d/m/Y',strtotime($f_deb));
if ($f_fin)  $filtres_actifs[] = 'Au '.date('d/m/Y',strtotime($f_fin));

/* Traitements image (pour label dans les options) */
$CATS_SUSPECT = [
    1 => 'Armes à feu et armes à feu factices',
    2 => 'Armes tranchantes et objets pointus',
    3 => 'Instruments contondants',
    4 => 'Matières explosives et substances inflammables',
    5 => 'Substances chimiques et toxiques',
];

/* Lettres options */
$LETTERS = ['A','B','C','D'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Banque de Questions — EXASUR ANAC GABON</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ════════════════════════════════════════════════
   VARIABLES & RESET
════════════════════════════════════════════════ */
:root {
    --blue:     #03224c;
    --blue-mid: #0a3a6b;
    --blue-lt:  #e8eef8;
    --gold:     #D4AF37;
    --gold-lt:  #fdf8e7;
    --green:    #16a34a;
    --green-bg: #f0fdf4;
    --red:      #dc2626;
    --grey:     #6b7280;
    --border:   #dde3ec;
    --shadow:   0 4px 24px rgba(3,34,76,.13);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 13px; }
body {
    font-family: 'Candara', 'Calibri', 'Segoe UI', sans-serif;
    background: #eef2f8;
    color: #1e293b;
    line-height: 1.5;
}

/* ════════════════════════════════════════════════
   BARRE D'ACTIONS (masquée à l'impression)
════════════════════════════════════════════════ */
.actions-bar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #fff;
    padding: 10px 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 12px rgba(3,34,76,.12);
    border-bottom: 3px solid var(--gold);
}
.actions-bar .ab-title {
    font-weight: 800;
    color: var(--blue);
    font-size: .95rem;
    display: flex;
    align-items: center;
    gap: 7px;
}
.actions-bar .ab-title i { color: var(--gold); }
.actions-bar .ab-sub {
    font-size: .74rem;
    color: var(--grey);
    margin-top: 2px;
}
.ab-count {
    background: var(--blue-lt);
    color: var(--blue);
    padding: 3px 14px;
    border-radius: 20px;
    font-weight: 800;
    font-size: .8rem;
    border: 1.5px solid var(--border);
}
.ab-right { margin-left: auto; display: flex; gap: 9px; align-items: center; }
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 20px;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: .84rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .22s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.btn-print { background: var(--blue); color: #fff; }
.btn-print:hover { background: var(--blue-mid); transform: translateY(-1px); }
.btn-close { background: var(--grey); color: #fff; }
.btn-close:hover { background: #4b5563; transform: translateY(-1px); }

/* Filtre chips dans la barre */
.filter-chips {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
}
.fchip {
    background: var(--gold-lt);
    border: 1.5px solid #f0d060;
    color: #7c5a00;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: .71rem;
    font-weight: 700;
}
.fchip i { margin-right: 3px; color: var(--gold); }

/* ════════════════════════════════════════════════
   DOCUMENT PRINCIPAL
════════════════════════════════════════════════ */
.doc {
    background: #fff;
    max-width: 980px;
    margin: 20px auto 40px;
    border-radius: 14px;
    border: 2px solid var(--blue);
    box-shadow: var(--shadow);
    overflow: hidden;
}

/* ── En-tête ── */
.doc-head {
    background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
    padding: 0 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.doc-head img {
    width: 100%;
    max-height: 86px;
    object-fit: contain;
    margin: 13px 0 0;
}
.doc-title-bar {
    width: 100%;
    text-align: center;
    padding: 11px 0 14px;
    border-top: 1px solid rgba(212,175,55,.3);
    margin-top: 10px;
}
.doc-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: .5px;
}
.doc-subtitle {
    font-size: .76rem;
    color: rgba(255,255,255,.7);
    margin-top: 3px;
}
/* Bandeau méta-infos */
.doc-meta {
    background: rgba(255,255,255,.08);
    border-top: 1px solid rgba(255,255,255,.15);
    padding: 9px 28px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    font-size: .75rem;
    color: rgba(255,255,255,.8);
}
.doc-meta span { display: flex; align-items: center; gap: 5px; }
.doc-meta i { color: var(--gold); }
.doc-meta strong { color: #fff; }

/* ── Corps ── */
.doc-body { padding: 22px 28px; }

/* ════════════════════════════════════════════════
   STATISTIQUES RAPIDES
════════════════════════════════════════════════ */
.kpi-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 22px;
}
.kpi-box {
    border-radius: 10px;
    border: 1.5px solid var(--border);
    padding: 11px 13px;
    text-align: center;
    position: relative;
    overflow: hidden;
    background: var(--blue-lt);
}
.kpi-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--c, var(--blue));
}
.kpi-box .kv { font-size: 1.3rem; font-weight: 800; color: var(--c, var(--blue)); line-height: 1.1; }
.kpi-box .kl { font-size: .66rem; font-weight: 700; color: var(--grey); text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

/* ════════════════════════════════════════════════
   SECTION PAR TYPE D'EXAMEN
════════════════════════════════════════════════ */
.section-hdr {
    background: linear-gradient(135deg, var(--blue), var(--blue-mid));
    color: var(--gold);
    padding: 10px 18px;
    border-radius: 9px;
    font-weight: 800;
    font-size: .9rem;
    margin: 24px 0 14px;
    display: flex;
    align-items: center;
    gap: 9px;
    letter-spacing: .2px;
}
.section-hdr .shdr-count {
    margin-left: auto;
    background: rgba(212,175,55,.2);
    border: 1px solid rgba(212,175,55,.4);
    color: var(--gold);
    padding: 2px 10px;
    border-radius: 50px;
    font-size: .74rem;
}

/* ════════════════════════════════════════════════
   CARTE QUESTION
════════════════════════════════════════════════ */
.q-card {
    border: 1.5px solid var(--border);
    border-radius: 11px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 6px rgba(3,34,76,.06);
    break-inside: avoid;
}

/* En-tête carte */
.q-card-head {
    background: linear-gradient(90deg, #f8faff, #fff);
    border-bottom: 1.5px solid var(--border);
    padding: 9px 15px;
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
}
.q-num {
    width: 28px; height: 28px;
    background: var(--blue);
    color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    font-size: .76rem;
    flex-shrink: 0;
}
.q-type-chip {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 50px;
    font-weight: 800;
    font-size: .69rem;
}
.chip-AS   { background: #dbeafe; color: #1e40af; }
.chip-IF   { background: #d1fae5; color: #065f46; }
.chip-INST { background: #fef3c7; color: #92400e; }
.chip-SENS { background: #ede9fe; color: #5b21b6; }
.chip-FORM { background: #fce7f3; color: #9d174d; }
.chip-theo { background: #dbeafe; color: #1e40af; }
.chip-prat { background: #fce7f3; color: #9d174d; }
.q-bareme {
    background: var(--gold-lt);
    border: 1.5px solid #f0d060;
    color: #7c5a00;
    padding: 2px 9px;
    border-radius: 50px;
    font-size: .69rem;
    font-weight: 800;
}
.q-id { margin-left: auto; font-size: .7rem; color: var(--grey); font-weight: 600; }

/* Textes FR / EN */
.q-texts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-bottom: 1px solid var(--border);
}
.q-col {
    padding: 11px 15px;
}
.q-col:first-child { border-right: 1px solid var(--border); }
.q-lang {
    font-size: .66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
    color: var(--grey);
}
.q-col:first-child .q-lang { color: #2563eb; }
.q-col:last-child  .q-lang { color: #16a34a; }
.q-text { font-weight: 700; font-size: .86rem; line-height: 1.5; color: #111827; }

/* Images pratique */
.q-images {
    padding: 9px 15px;
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-start;
}
.q-img {
    height: 90px;
    border-radius: 7px;
    object-fit: cover;
    border: 1.5px solid var(--border);
}
.q-img-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}
.q-img-trait {
    font-size: .64rem;
    color: #6366f1;
    font-weight: 700;
    background: #eef2ff;
    padding: 1px 6px;
    border-radius: 20px;
}

/* Options */
.q-options { padding: 9px 15px; }
.opt-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-radius: 7px;
    overflow: hidden;
    border: 1.5px solid var(--border);
    margin-bottom: 6px;
}
.opt-row.is-correct {
    border-color: var(--green);
    box-shadow: 0 0 0 2px rgba(22,163,74,.12);
}
.opt-cell {
    padding: 7px 11px;
    display: flex;
    align-items: flex-start;
    gap: 7px;
    font-size: .8rem;
    line-height: 1.4;
}
.opt-cell:first-child { border-right: 1px solid var(--border); }
.opt-row.is-correct .opt-cell { background: var(--green-bg); }
.opt-row.is-correct .opt-cell:first-child { border-right-color: rgba(22,163,74,.25); }
.opt-letter {
    width: 19px; height: 19px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 800;
    font-size: .7rem;
    flex-shrink: 0;
    background: #f3f4f6;
    color: var(--blue);
}
.opt-row.is-correct .opt-letter { background: var(--green); color: #fff; }
.opt-row.is-correct .opt-text { color: #15803d; font-weight: 700; }

/* Bonne réponse (corrigé) */
.answer-key {
    background: var(--blue-lt);
    border-top: 2px solid var(--blue);
    padding: 9px 15px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
    font-size: .78rem;
}
.ak-label {
    font-weight: 800;
    color: var(--blue);
    text-transform: uppercase;
    letter-spacing: .4px;
    font-size: .69rem;
    display: flex;
    align-items: center;
    gap: 5px;
}
.ak-badge {
    background: var(--blue);
    color: #fff;
    padding: 2px 12px;
    border-radius: 20px;
    font-weight: 800;
    font-size: .78rem;
}
.ak-text { color: var(--green); font-weight: 700; }

/* Corrigé IF pratique */
.ak-prat-ok { background: #dcfce7; color: #15803d; border: 1.5px solid #bbf7d0; padding: 3px 12px; border-radius: 50px; font-weight: 800; font-size: .76rem; }
.ak-prat-ko { background: #fee2e2; color: var(--red);   border: 1.5px solid #fecaca; padding: 3px 12px; border-radius: 50px; font-weight: 800; font-size: .76rem; }

/* ════════════════════════════════════════════════
   ÉTAT VIDE
════════════════════════════════════════════════ */
.empty-state {
    text-align: center;
    padding: 56px 20px;
    color: var(--grey);
}
.empty-state i { font-size: 2.6rem; display: block; margin-bottom: 12px; color: #c4cdd9; }

/* ════════════════════════════════════════════════
   PIED DE PAGE
════════════════════════════════════════════════ */
.doc-foot {
    background: var(--blue);
    color: rgba(255,255,255,.7);
    text-align: center;
    padding: 11px 20px;
    font-size: .73rem;
    border-top: 3px solid var(--gold);
    line-height: 1.7;
}
.doc-foot strong { color: var(--gold); }

/* ════════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════════ */
@media (max-width: 640px) {
    .q-texts { grid-template-columns: 1fr; }
    .q-col:first-child { border-right: none; border-bottom: 1px solid var(--border); }
    .opt-row { grid-template-columns: 1fr; }
    .opt-cell:first-child { border-right: none; border-bottom: 1px solid var(--border); }
    .kpi-strip { grid-template-columns: repeat(2,1fr); }
}

/* ════════════════════════════════════════════════
   IMPRESSION
════════════════════════════════════════════════ */
@media print {
    .actions-bar { display: none !important; }
    body { background: #fff; font-size: 11px; }
    .doc { border-radius: 0; box-shadow: none; margin: 0; max-width: 100%; border: 2px solid var(--blue); }
    .doc-head, .q-card-head, .q-num, .opt-row.is-correct,
    .answer-key, .doc-foot, .section-hdr {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .q-card { break-inside: avoid; border: 1px solid #ccc; margin-bottom: 12px; }
    .kpi-strip { break-inside: avoid; }
    .section-hdr { break-after: avoid; }
    .q-img { max-height: 72px; }
}
</style>
</head>
<body>

<!-- ══ BARRE D'ACTIONS ══ -->
<div class="actions-bar">
    <div>
        <div class="ab-title">
            <i class="fas fa-print"></i>
            Banque de Questions — Aperçu avant impression
        </div>
        <div class="ab-sub">
            <?= htmlspecialchars($type_label) ?>
            <?php if (!empty($filtres_actifs)): ?>
            — <?= htmlspecialchars(implode(' · ', $filtres_actifs)) ?>
            <?php endif; ?>
        </div>
    </div>
    <span class="ab-count">
        <i class="fas fa-list" style="margin-right:4px;"></i>
        <?= $total ?> question<?= $total > 1 ? 's' : '' ?>
    </span>
    <?php if (!empty($filtres_actifs)): ?>
    <div class="filter-chips">
        <?php foreach ($filtres_actifs as $fc): ?>
        <span class="fchip"><i class="fas fa-filter"></i><?= htmlspecialchars($fc) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="ab-right">
        <button class="btn-action btn-print" onclick="window.print()">
            <i class="fas fa-file-pdf"></i> Imprimer / Enregistrer PDF
        </button>
        <button class="btn-action btn-close" onclick="window.close()">
            <i class="fas fa-times"></i> Fermer
        </button>
    </div>
</div>

<!-- ══ DOCUMENT ══ -->
<div class="doc">

    <!-- ── En-tête ── -->
    <div class="doc-head">
        <img src="../assets/images/banierenteanac.png" alt="ANAC GABON"
             onerror="this.style.display='none'">
        <div class="doc-title-bar">
            <div class="doc-title">
                <i class="fas fa-database" style="margin-right:8px;color:var(--gold);"></i>
                Banque de Questions — EXASUR
            </div>
            <div class="doc-subtitle">
                Questions Bank / AVSEC-FAL · ANAC GABON · Bilingue FR / EN
            </div>
        </div>
    </div>

    <!-- ── Méta-infos ── -->
    <div class="doc-meta">
        <span>
            <i class="fas fa-layer-group"></i>
            Catégorie : <strong><?= htmlspecialchars($type_label) ?></strong>
        </span>
        <?php if ($f_tq): ?>
        <span>
            <i class="fas fa-tag"></i>
            Type : <strong><?= $f_tq==='theorique' ? '📝 Théorique' : '🖼️ Pratique' ?></strong>
        </span>
        <?php endif; ?>
        <?php if ($sess_label): ?>
        <span>
            <i class="fas fa-calendar-alt"></i>
            Session : <strong><?= htmlspecialchars($sess_label) ?></strong>
        </span>
        <?php endif; ?>
        <?php if ($f_q): ?>
        <span>
            <i class="fas fa-search"></i>
            Filtre texte : <strong>"<?= htmlspecialchars($f_q) ?>"</strong>
        </span>
        <?php endif; ?>
        <?php if ($f_deb || $f_fin): ?>
        <span>
            <i class="fas fa-calendar"></i>
            Période :
            <strong>
                <?= $f_deb ? 'du '.date('d/m/Y',strtotime($f_deb)) : '' ?>
                <?= $f_fin ? ' au '.date('d/m/Y',strtotime($f_fin)) : '' ?>
            </strong>
        </span>
        <?php endif; ?>
        <span style="margin-left:auto;">
            <i class="fas fa-clock"></i>
            Édité le : <strong><?= date('d/m/Y à H:i') ?></strong>
        </span>
    </div>

    <!-- ── Corps ── -->
    <div class="doc-body">

        <!-- KPIs -->
        <?php
        /* Compter par type de question */
        $cnt_theo = count(array_filter($rows, fn($r) => $r['type_question']==='theorique'));
        $cnt_prat = count(array_filter($rows, fn($r) => $r['type_question']==='pratique'));
        $total_pts = array_sum(array_column($rows, 'bareme'));
        /* Types distincts */
        $types_distincts = array_unique(array_column($rows, 'type_code'));
        ?>
        <div class="kpi-strip">
            <div class="kpi-box" style="--c:var(--blue);">
                <div class="kv"><?= $total ?></div>
                <div class="kl"><i class="fas fa-list" style="margin-right:3px;"></i>Total</div>
            </div>
            <div class="kpi-box" style="--c:#1e40af;">
                <div class="kv" style="--c:#1e40af;"><?= $cnt_theo ?></div>
                <div class="kl">📝 Théoriques</div>
            </div>
            <div class="kpi-box" style="--c:#9d174d;">
                <div class="kv" style="--c:#9d174d;"><?= $cnt_prat ?></div>
                <div class="kl">🖼️ Pratiques</div>
            </div>
            <div class="kpi-box" style="--c:var(--gold);">
                <div class="kv" style="--c:#92400e;"><?= $total_pts ?></div>
                <div class="kl"><i class="fas fa-star" style="margin-right:3px;color:var(--gold);"></i>Total pts</div>
            </div>
            <div class="kpi-box" style="--c:#7c3aed;">
                <div class="kv" style="--c:#7c3aed;"><?= count($types_distincts) ?></div>
                <div class="kl"><i class="fas fa-shield-alt" style="margin-right:3px;"></i>Type(s)</div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p style="font-size:.9rem;font-weight:600;">Aucune question ne correspond aux filtres sélectionnés.</p>
            <p style="font-size:.78rem;margin-top:6px;color:#9ca3af;">
                Vérifiez les critères appliqués et réessayez.
            </p>
        </div>
        <?php else:
        /* ═══════════════════════════════════════════════════════════
           BOUCLE QUESTIONS avec section-header par type
        ═══════════════════════════════════════════════════════════ */
        $prev_type = '';
        $type_counter = []; /* compte par type pour le header */
        foreach ($rows as $r) {
            $tc = $r['type_code'] ?? '—';
            $type_counter[$tc] = ($type_counter[$tc] ?? 0) + 1;
        }

        $idx_global = 0;
        $idx_in_section = 0;
        $current_type = '';

        foreach ($rows as $q):
            $tc        = $q['type_code'] ?? '—';
            $is_prat   = ($q['type_question'] === 'pratique');
            $co        = intval($q['correct_option']);
            $imgs      = !empty($q['images']) ? (json_decode($q['images'],true)??[]) : [];
            $traits    = !empty($q['images_traitements']) ? (json_decode($q['images_traitements'],true)??[]) : [];
            $idx_global++;

            /* ── Section header au changement de type ── */
            if ($tc !== $current_type && !$f_type):
                $current_type = $tc;
                $idx_in_section = 0;
            ?>
            <div class="section-hdr">
                <i class="fas fa-shield-alt"></i>
                <?= htmlspecialchars($tc.' — '.($q['type_nom']??'')) ?>
                <span class="shdr-count">
                    <?= $type_counter[$tc] ?> question<?= ($type_counter[$tc]>1)?'s':'' ?>
                </span>
            </div>
            <?php endif;
            $idx_in_section++;
        ?>

        <!-- ═══ Question #<?= $idx_global ?> ═══ -->
        <div class="q-card">

            <!-- En-tête carte -->
            <div class="q-card-head">
                <div class="q-num"><?= $idx_global ?></div>
                <span class="q-type-chip chip-<?= htmlspecialchars($tc) ?>">
                    <?= htmlspecialchars($tc) ?>
                </span>
                <?= $is_prat
                    ? '<span class="q-type-chip chip-prat">🖼️ Pratique IF</span>'
                    : '<span class="q-type-chip chip-theo">📝 Théorique</span>' ?>
                <?php if ($q['bareme']): ?>
                <span class="q-bareme">
                    <i class="fas fa-star" style="color:var(--gold);font-size:.65rem;margin-right:2px;"></i>
                    <?= htmlspecialchars($q['bareme']) ?> pt<?= ($q['bareme']>1)?'s':'' ?>
                </span>
                <?php endif; ?>
                <span class="q-id">ID #<?= intval($q['id']) ?></span>
            </div>

            <!-- Textes FR / EN -->
            <div class="q-texts">
                <div class="q-col">
                    <div class="q-lang">🇫🇷 Français</div>
                    <div class="q-text"><?= htmlspecialchars($q['question_text_fr']) ?></div>
                </div>
                <div class="q-col">
                    <div class="q-lang">🇬🇧 English</div>
                    <div class="q-text"><?= htmlspecialchars($q['question_text_en'] ?? '') ?></div>
                </div>
            </div>

            <!-- Images (IF Pratique) -->
            <?php if ($is_prat && !empty($imgs)): ?>
            <div class="q-images">
                <?php foreach ($imgs as $img):
                    $trait_label = $traits[$img] ?? 'normal'; ?>
                <div class="q-img-label">
                    <img src="../assets/images/<?= htmlspecialchars($img) ?>"
                         class="q-img"
                         alt="image scanner"
                         onerror="this.style.opacity='.3'">
                    <span class="q-img-trait">
                        <i class="fas fa-palette" style="margin-right:2px;"></i>
                        <?= htmlspecialchars($trait_label) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Options (questions théoriques) -->
            <?php if (!$is_prat):
                /* Compter les options disponibles */
                $opts_fr = [$q['option1_fr'],$q['option2_fr'],$q['option3_fr'],$q['option4_fr']];
                $opts_en = [$q['option1_en'],$q['option2_en'],$q['option3_en'],$q['option4_en']];
                $nb_opts = 0;
                for ($n=0; $n<4; $n++) {
                    if (!empty($opts_fr[$n]) || !empty($opts_en[$n])) $nb_opts = $n+1;
                }
            ?>
            <div class="q-options">
                <?php for ($n=0; $n<$nb_opts; $n++):
                    $is_correct = (($n+1) === $co);
                ?>
                <div class="opt-row <?= $is_correct ? 'is-correct' : '' ?>">
                    <div class="opt-cell">
                        <span class="opt-letter"><?= $LETTERS[$n] ?></span>
                        <span class="opt-text"><?= htmlspecialchars($opts_fr[$n] ?? '—') ?></span>
                    </div>
                    <div class="opt-cell">
                        <span class="opt-letter"><?= $LETTERS[$n] ?></span>
                        <span class="opt-text"><?= htmlspecialchars($opts_en[$n] ?? '—') ?></span>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Corrigé théorique -->
            <div class="answer-key">
                <span class="ak-label">
                    <i class="fas fa-key" style="color:var(--gold);"></i>
                    Bonne réponse :
                </span>
                <span class="ak-badge">
                    Option <?= $co ?> — <?= $LETTERS[$co-1] ?? '?' ?>
                </span>
                <span class="ak-text">
                    FR : <?= htmlspecialchars(mb_substr($opts_fr[$co-1]??'—', 0, 80)) ?>
                </span>
                <?php if (!empty($opts_en[$co-1])): ?>
                <span style="color:var(--green);font-size:.75rem;">
                    · EN : <?= htmlspecialchars(mb_substr($opts_en[$co-1], 0, 60)) ?>
                </span>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:.7rem;color:var(--grey);">
                    Barème : <strong><?= htmlspecialchars($q['bareme']??'—') ?> pt<?= ($q['bareme']>1)?'s':'' ?></strong>
                </span>
            </div>

            <?php else: /* ── Corrigé IF PRATIQUE ── */
                if ($co === 1) {
                    $rep_label  = '🟢 Bagage CLAIR';
                    $rep_class  = 'ak-prat-ok';
                    $cat_detail = 'Aucun objet prohibé détecté';
                } else {
                    $cat_lib    = $q['option3_fr'] ?? ($CATS_SUSPECT[$co-1] ?? '');
                    $rep_label  = '🔴 Bagage SUSPECT';
                    $rep_class  = 'ak-prat-ko';
                    $cat_detail = $cat_lib ? 'Catégorie : '.$cat_lib : 'Objet dangereux identifié';
                }
            ?>
            <!-- Options pratique -->
            <div class="q-options">
                <div class="opt-row <?= ($co===1)?'is-correct':'' ?>" style="margin-bottom:6px;">
                    <div class="opt-cell" style="grid-column:1/-1;<?= ($co!==1)?'':'background:var(--green-bg);' ?>">
                        <span class="opt-letter" style="<?= ($co===1)?'background:var(--green);color:#fff;':'' ?>">A</span>
                        <span class="opt-text" style="<?= ($co===1)?'color:#15803d;font-weight:700;':'' ?>">
                            🟢 Bagage CLAIR — No prohibited item
                        </span>
                    </div>
                </div>
                <div class="opt-row <?= ($co!==1)?'is-correct':'' ?>">
                    <div class="opt-cell" style="grid-column:1/-1;<?= ($co===1)?'':'background:var(--green-bg);' ?>">
                        <span class="opt-letter" style="<?= ($co!==1)?'background:var(--green);color:#fff;':'' ?>">B</span>
                        <span class="opt-text" style="<?= ($co!==1)?'color:#15803d;font-weight:700;':'' ?>">
                            🔴 Bagage SUSPECT — Prohibited item detected
                            <?php if ($co!==1 && !empty($q['option3_fr'])): ?>
                            <br><span style="font-size:.75rem;color:var(--blue);font-weight:600;margin-left:26px;">
                                → <?= htmlspecialchars($q['option3_fr']) ?>
                            </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Corrigé pratique -->
            <div class="answer-key">
                <span class="ak-label">
                    <i class="fas fa-key" style="color:var(--gold);"></i>
                    Bonne réponse :
                </span>
                <span class="<?= $rep_class ?>">
                    <?= htmlspecialchars($rep_label) ?>
                </span>
                <span style="font-size:.78rem;color:var(--grey);">
                    <?= htmlspecialchars($cat_detail) ?>
                </span>
                <span style="margin-left:auto;font-size:.7rem;color:var(--grey);">
                    Barème : <strong><?= htmlspecialchars($q['bareme']??'—') ?> pt<?= ($q['bareme']>1)?'s':'' ?></strong>
                </span>
            </div>

            <?php endif; /* fin pratique/theorique */ ?>

        </div><!-- /.q-card -->
        <?php endforeach; /* fin foreach rows */ ?>
        <?php endif; /* fin if empty rows */ ?>

    </div><!-- /.doc-body -->

    <!-- ── Pied de page ── -->
    <div class="doc-foot">
        Document généré depuis le système <strong>EXASUR</strong> — ANAC GABON
        le <?= date('d/m/Y à H:i') ?>
        &nbsp;·&nbsp;
        <strong><?= $total ?></strong> question<?= $total>1?'s':'' ?>
        — <?= htmlspecialchars($type_label) ?>
        <?php if (!empty($filtres_actifs)): ?>
        &nbsp;·&nbsp; <?= htmlspecialchars(implode(' · ', $filtres_actifs)) ?>
        <?php endif; ?>
        <br>
        <em>Direction de la Sûreté et de la Facilitation de l'Aviation Civile
        · Document confidentiel — ANAC GABON</em>
    </div>

</div><!-- /.doc -->

</body>
</html>
<?php $conn->close(); ?>