<?php
/**
 * print_candidat.php — DOSSIER COMPLET DE RÉSULTATS — EXASUR ANAC GABON
 * ══════════════════════════════════════════════════════════════════════
 * Imprime l'intégralité des examens passés par un candidat :
 *  - Sommaire interactif avec ancres
 *  - Fiche identité candidat + statistiques globales
 *  - Pour chaque examen (du plus récent au plus ancien) :
 *      · Bandeau examen (type, session, date, résultat)
 *      · Récapitulatif (note, score, barre de progression)
 *      · Tableau détaillé des questions / réponses
 *      · Pour FORM  : un bloc par module
 *      · Pour IF    : synthèse théorie / pratique
 *  - Pied de page sécurisé
 *
 * SÉCURITÉ :
 *  - intval() sur tous les GET
 *  - Requêtes préparées via bind_param()
 *  - htmlspecialchars() sur toutes les sorties HTML
 */

session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Paramètre ── */
$id = intval($_GET['id'] ?? 0);
if (!$id) die("Paramètre ID manquant.");

/* ════════════════════════════════════════════
   DONNÉES CANDIDAT
════════════════════════════════════════════ */
$stmt = $conn->prepare("
    SELECT c.*, s.nomstagiaire, s.prenomstagiaire, s.emailstagiaire,
           s.telstagiaire, s.postestagiaire, s.sexestagiaire,
           s.datenaiss, s.nationalite, o.nomorga
    FROM candidat c
    JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
    LEFT JOIN si_anac.organisme o ON s.idorga = o.idorga
    WHERE c.idcandidat = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) die("Candidat introuvable.");

/* ════════════════════════════════════════════
   STATISTIQUES GLOBALES
════════════════════════════════════════════ */
$stmt = $conn->prepare("SELECT COUNT(*) FROM resultats WHERE idcandidat=? AND note_finale>0");
$stmt->bind_param('i', $id); $stmt->execute();
$nb_total = intval($stmt->get_result()->fetch_row()[0]); $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM resultats WHERE idcandidat=? AND reussite=1");
$stmt->bind_param('i', $id); $stmt->execute();
$nb_ok = intval($stmt->get_result()->fetch_row()[0]); $stmt->close();

$nb_ko = $nb_total - $nb_ok;
$taux  = $nb_total > 0 ? round($nb_ok / $nb_total * 100, 1) : 0;

/* ════════════════════════════════════════════
   TOUS LES RÉSULTATS (plus récent en premier)
════════════════════════════════════════════ */
$stmt = $conn->prepare("
    SELECT r.*, se.nom_session, se.idtype_examen AS ite,
           se.idtypeformation, se.type_session,
           te.nom_fr AS type_nom, te.code AS type_code,
           te.seuil_reussite
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    JOIN type_examen te   ON r.idtype_examen = te.idtype_examen
    WHERE r.idcandidat = ? AND r.note_finale > 0
    ORDER BY r.date_fin DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$rows_result = $stmt->get_result();
$stmt->close();

$examens = [];
while ($row = $rows_result->fetch_assoc()) {
    $examens[] = $row;
}

/* ════════════════════════════════════════════
   POUR CHAQUE EXAMEN : charger réponses / modules
════════════════════════════════════════════ */
foreach ($examens as &$ex) {
    $ite     = intval($ex['ite']);
    $id_sess = intval($ex['id_session']);

    if ($ite == 5) {
        /* ── FORM : charger les modules ── */
        $itf = intval($ex['idtypeformation'] ?? 0);
        $ex['modules']    = [];
        $ex['synth_form'] = null;
        $ex['form_cont']  = null;

        if ($itf) {
            $s2 = $conn->prepare("
                SELECT nom_session, date_debut, date_fin
                FROM session_examen
                WHERE idtype_examen=5 AND idtypeformation=? AND idmodule IS NULL
                ORDER BY id_session DESC LIMIT 1
            ");
            $s2->bind_param('i', $itf); $s2->execute();
            $ex['form_cont'] = $s2->get_result()->fetch_assoc();
            $s2->close();

            $s2 = $conn->prepare("
                SELECT r2.*, se.id_session AS sid, se.nom_session AS sess_nom,
                       mf.nom_module_fr, mf.numero_module
                FROM resultats r2
                JOIN session_examen se ON r2.id_session = se.id_session
                LEFT JOIN module_formation mf ON se.idmodule = mf.idmodule
                WHERE r2.idcandidat=? AND se.idtype_examen=5 AND se.idtypeformation=?
                  AND se.idmodule IS NOT NULL
                ORDER BY mf.numero_module ASC, r2.date_fin ASC
            ");
            $s2->bind_param('ii', $id, $itf); $s2->execute();
            $mods_res = $s2->get_result(); $s2->close();

            $tot = 0; $nb = 0;
            while ($mod = $mods_res->fetch_assoc()) {
                $sid_m = intval($mod['sid']);
                $s3 = $conn->prepare("
                    SELECT q.*, rc.selected_option, rc.est_correcte
                    FROM reponses_candidat rc
                    JOIN question q ON rc.question_id=q.id
                    WHERE rc.idcandidat=? AND rc.id_session=?
                    ORDER BY q.id
                ");
                $s3->bind_param('ii', $id, $sid_m); $s3->execute();
                $rep_arr = [];
                $r2 = $s3->get_result();
                while ($rr = $r2->fetch_assoc()) $rep_arr[] = $rr;
                $s3->close();
                $mod['reponses'] = $rep_arr;
                $ex['modules'][] = $mod;
                $tot += floatval($mod['pourcentage']); $nb++;
            }
            if ($nb > 0) {
                $moy = round($tot / $nb, 1);
                $ex['synth_form'] = ['moy'=>$moy,'reussite'=>($moy>=70),'nb'=>$nb];
            }
        }

    } elseif ($ite == 2) {
        /* ── IF : réponses + synthèse théorie/pratique ── */
        $s2 = $conn->prepare("
            SELECT q.*, rc.selected_option, rc.est_correcte
            FROM reponses_candidat rc
            JOIN question q ON rc.question_id=q.id
            WHERE rc.idcandidat=? AND rc.id_session=?
            ORDER BY q.id
        ");
        $s2->bind_param('ii', $id, $id_sess); $s2->execute();
        $rep_arr = [];
        $r2 = $s2->get_result();
        while ($rr = $r2->fetch_assoc()) $rep_arr[] = $rr;
        $s2->close();
        $ex['reponses'] = $rep_arr;

        $s2 = $conn->prepare("
            SELECT r2.note_finale, r2.note_sur, r2.pourcentage, r2.reussite,
                   se.type_session, se.nom_session
            FROM resultats r2
            JOIN session_examen se ON r2.id_session=se.id_session
            WHERE r2.idcandidat=? AND se.idtype_examen=2 AND r2.note_finale>0
            ORDER BY r2.date_fin ASC
        ");
        $s2->bind_param('i', $id); $s2->execute();
        $if_res = $s2->get_result(); $s2->close();
        $theo = null; $prat = null;
        while ($rif = $if_res->fetch_assoc()) {
            if ($rif['type_session']==='theorie'  && !$theo) $theo = $rif;
            if ($rif['type_session']==='pratique' && !$prat) $prat = $rif;
        }
        if ($theo || $prat) {
            $moy_if = ($theo && $prat)
                ? round((floatval($theo['pourcentage'])+floatval($prat['pourcentage']))/2,1)
                : null;
            $ex['synth_if'] = [
                'theo'     => $theo,
                'prat'     => $prat,
                'moy'      => $moy_if,
                'reussite' => ($moy_if!==null && $moy_if>=80
                               && $theo && $theo['reussite']
                               && $prat && $prat['reussite']),
            ];
        }

    } else {
        /* ── AS / INST / SENS ── */
        $s2 = $conn->prepare("
            SELECT q.*, rc.selected_option, rc.est_correcte
            FROM reponses_candidat rc
            JOIN question q ON rc.question_id=q.id
            WHERE rc.idcandidat=? AND rc.id_session=?
            ORDER BY q.id
        ");
        $s2->bind_param('ii', $id, $id_sess); $s2->execute();
        $rep_arr = [];
        $r2 = $s2->get_result();
        while ($rr = $r2->fetch_assoc()) $rep_arr[] = $rr;
        $s2->close();
        $ex['reponses'] = $rep_arr;
    }
}
unset($ex);

/* ════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════ */
function optVal(array $rep, int $n): ?string {
    $k = 'option'.$n.'_fr';
    return (isset($rep[$k]) && $rep[$k] !== '') ? $rep[$k] : null;
}
function barCol(float $p, float $s = 70): string {
    if ($p >= $s)           return '#16a34a';
    if ($p >= $s * 0.875)   return '#d97706';
    return '#dc2626';
}
function typeStyle(string $code): string {
    return ['AS'=>'background:#dbeafe;color:#1e40af;','IF'=>'background:#d1fae5;color:#065f46;',
            'INST'=>'background:#fef3c7;color:#92400e;','SENS'=>'background:#ede9fe;color:#5b21b6;',
            'FORM'=>'background:#fce7f3;color:#9d174d;'][$code] ?? 'background:#f3f4f6;color:#374151;';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dossier Résultats — <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════
   VARIABLES & RESET
══════════════════════════════════════════════ */
:root{
    --blue:#03224c; --blue-mid:#0a3a6b; --blue-lt:#e8eef8;
    --gold:#D4AF37; --gold-lt:#fdf8e7;
    --green:#16a34a; --green-bg:#f0fdf4;
    --red:#dc2626;   --red-bg:#fff1f2;
    --orange:#d97706; --orange-bg:#fffbeb;
    --grey:#6b7280;  --border:#dde3ec;
    --shadow:0 4px 20px rgba(3,34,76,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:13px;}
body{font-family:'Candara','Calibri','Segoe UI',sans-serif;background:#eef2f8;
     padding:20px 12px 40px;color:#1e293b;line-height:1.5;}

/* ── Actions bar ── */
.actions-bar{display:flex;justify-content:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.btn-action{display:inline-flex;align-items:center;gap:7px;padding:9px 22px;border:none;
    border-radius:8px;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;
    transition:all .22s ease;box-shadow:0 2px 8px rgba(0,0,0,.14);text-decoration:none;}
.btn-print{background:var(--blue);color:#fff;}
.btn-print:hover{background:var(--blue-mid);transform:translateY(-1px);}
.btn-close{background:var(--grey);color:#fff;}
.btn-close:hover{background:#4b5563;transform:translateY(-1px);}

/* ── Document ── */
.doc{background:#fff;max-width:1060px;margin:0 auto;border-radius:14px;
     border:2px solid var(--blue);box-shadow:var(--shadow);overflow:hidden;}

/* ── En-tête ── */
.doc-head{background:linear-gradient(135deg,var(--blue) 0%,var(--blue-mid) 100%);
    padding:0 32px;display:flex;flex-direction:column;align-items:center;}
.doc-head img{width:100%;max-height:88px;object-fit:contain;margin:14px 0 0;}
.doc-title-bar{width:100%;text-align:center;padding:12px 0 15px;
    border-top:1px solid rgba(212,175,55,.3);margin-top:10px;}
.doc-title{font-size:1.25rem;font-weight:800;color:var(--gold);
    letter-spacing:.5px;text-transform:uppercase;}
.doc-subtitle{font-size:.78rem;color:rgba(255,255,255,.7);margin-top:3px;letter-spacing:.3px;}

/* ── Corps ── */
.doc-body{padding:26px 30px;}

/* ── Carte candidat ── */
.cand-card{border:1.5px solid var(--border);border-left:5px solid var(--blue);
    border-radius:10px;background:var(--blue-lt);padding:16px 20px;
    margin-bottom:22px;display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;}
.cand-avatar{width:58px;height:58px;background:linear-gradient(135deg,var(--blue),var(--blue-mid));
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    color:var(--gold);font-size:1.4rem;flex-shrink:0;}
.cand-info{flex:1;}
.cand-name{font-size:1.1rem;font-weight:800;color:var(--blue);margin-bottom:4px;}
.cand-code{display:inline-block;background:var(--blue);color:#fff;
    padding:2px 12px;border-radius:50px;font-weight:800;font-size:.76rem;margin-bottom:8px;}
.cand-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:5px 18px;}
.cf .lbl{font-weight:700;color:var(--blue);font-size:.68rem;text-transform:uppercase;
    letter-spacing:.4px;display:block;margin-bottom:1px;}
.cf .val{font-size:.78rem;color:#334155;}

/* ── Stats strip ── */
.stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;}
.sbox{border-radius:10px;padding:12px 13px;text-align:center;
    border:1.5px solid var(--border);position:relative;overflow:hidden;}
.sbox::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--c,var(--blue));}
.sbox .sv{font-size:1.3rem;font-weight:800;color:var(--c,var(--blue));line-height:1.1;}
.sbox .sl{font-size:.67rem;font-weight:700;color:var(--grey);text-transform:uppercase;
    letter-spacing:.4px;margin-top:3px;}

/* ── Sommaire ── */
.sommaire{border:2px solid var(--blue);border-radius:12px;overflow:hidden;
    margin-bottom:28px;box-shadow:0 2px 10px rgba(3,34,76,.08);}
.som-head{background:linear-gradient(135deg,var(--blue),var(--blue-mid));
    padding:10px 18px;display:flex;align-items:center;gap:9px;}
.som-head i{color:var(--gold);}
.som-head h3{margin:0;font-size:.84rem;font-weight:800;color:#fff;
    text-transform:uppercase;letter-spacing:.4px;}
.som-body{padding:12px 16px;}
table.stom{width:100%;border-collapse:collapse;}
table.stom thead th{font-size:.67rem;font-weight:800;color:var(--blue);
    text-transform:uppercase;letter-spacing:.4px;padding:5px 8px;
    border-bottom:2px solid var(--border);text-align:left;}
table.stom tbody tr{transition:background .15s;}
table.stom tbody tr:hover{background:var(--blue-lt);}
table.stom tbody td{padding:6px 8px;border-bottom:1px solid var(--border);
    font-size:.78rem;vertical-align:middle;}
table.stom tbody tr:last-child td{border-bottom:none;}
.som-num{width:26px;height:26px;background:var(--blue);color:#fff;border-radius:50%;
    display:inline-flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.7rem;}
.som-link{color:var(--blue);font-weight:700;text-decoration:none;transition:color .15s;}
.som-link:hover{color:var(--gold);text-decoration:underline;}

/* ── Séparateur ── */
.sep{display:flex;align-items:center;gap:9px;margin:10px 0 13px;}
.sep span{font-size:.69rem;font-weight:800;color:var(--blue);
    text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
.sep::before,.sep::after{content:'';flex:1;height:1.5px;background:var(--border);}

/* ── Badges universels ── */
.type-badge{display:inline-block;padding:2px 9px;border-radius:50px;font-weight:800;font-size:.68rem;}
.badge-ok{background:var(--green-bg);color:var(--green);padding:2px 9px;border-radius:50px;
    font-weight:800;font-size:.72rem;white-space:nowrap;}
.badge-ko{background:var(--red-bg);color:var(--red);padding:2px 9px;border-radius:50px;
    font-weight:800;font-size:.72rem;white-space:nowrap;}
.badge-warn{background:var(--orange-bg);color:var(--orange);padding:2px 9px;border-radius:50px;
    font-weight:800;font-size:.72rem;white-space:nowrap;}
.mini-bar{height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden;max-width:80px;}
.mini-fill{height:100%;border-radius:3px;}

/* ══════════════════════════════════════════════
   BLOC EXAMEN
══════════════════════════════════════════════ */
.exam-block{border:2px solid var(--blue);border-radius:13px;overflow:hidden;
    margin-bottom:36px;box-shadow:var(--shadow);}
.exam-banner{background:linear-gradient(135deg,var(--blue) 0%,var(--blue-mid) 100%);
    padding:13px 20px;display:flex;align-items:center;
    justify-content:space-between;flex-wrap:wrap;gap:10px;}
.exam-banner-L{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.exam-idx{width:34px;height:34px;background:var(--gold);color:var(--blue);border-radius:50%;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;flex-shrink:0;}
.exam-banner h3{font-size:.9rem;font-weight:800;color:#fff;margin:0;}
.exam-banner p{font-size:.72rem;color:rgba(255,255,255,.65);margin:2px 0 0;}
.exam-banner-R{display:flex;align-items:center;gap:10px;}
.score-chip{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
    border-radius:8px;padding:6px 13px;text-align:center;}
.score-chip .sv{font-size:1.05rem;font-weight:800;color:var(--gold);}
.score-chip .sl{font-size:.63rem;color:rgba(255,255,255,.65);}
.exam-body{padding:17px 20px;}

/* ── Récapitulatif ── */
.recap-card{border:1.5px solid var(--border);border-left:4px solid var(--blue);
    border-radius:10px;background:var(--blue-lt);padding:14px 17px;margin-bottom:17px;}
.recap-title{font-size:.69rem;font-weight:800;color:var(--blue);text-transform:uppercase;
    letter-spacing:.5px;margin-bottom:11px;display:flex;align-items:center;gap:6px;}
.recap-title i{color:var(--gold);}
.recap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
    gap:9px;margin-bottom:11px;}
.rstat{background:#fff;border:1.5px solid var(--border);border-radius:8px;
    padding:9px 11px;text-align:center;position:relative;overflow:hidden;}
.rstat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--rc,var(--blue));}
.rstat .rl{font-size:.64rem;font-weight:700;color:var(--grey);text-transform:uppercase;letter-spacing:.4px;}
.rstat .rv{font-size:1.15rem;font-weight:800;color:var(--rc,var(--blue));line-height:1.2;margin-top:2px;}
.rstat .rsub{font-size:.63rem;color:var(--grey);margin-top:1px;}
.prog-track{height:9px;background:#e5e7eb;border-radius:5px;overflow:hidden;}
.prog-fill{height:100%;border-radius:5px;}
.prog-lbl{display:flex;justify-content:space-between;font-size:.63rem;color:var(--grey);margin-top:2px;}

/* ── Tableau questions ── */
.tbl-wrap{overflow-x:auto;border-radius:9px;border:1.5px solid var(--border);margin-top:4px;}
table.qtbl{width:100%;border-collapse:collapse;font-size:.75rem;}
table.qtbl thead tr{background:linear-gradient(135deg,var(--blue),var(--blue-mid));}
table.qtbl thead th{color:var(--gold);padding:9px;text-align:left;font-weight:800;
    font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
table.qtbl tbody tr.r-ok{background:#f0fdf4;}
table.qtbl tbody tr.r-ko{background:#fff1f2;}
table.qtbl td{padding:7px 9px;vertical-align:top;border-bottom:1px solid var(--border);}
table.qtbl tbody tr:last-child td{border-bottom:none;}
.qnum{width:28px;height:28px;background:var(--blue);color:#fff;border-radius:50%;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.72rem;}
.qfr{font-weight:700;color:#1e293b;}
.qen{font-style:italic;color:var(--grey);font-size:.74em;margin-top:3px;}
.qsep{border-top:1px dashed #d1d5db;margin:4px 0;}
.opt-item{font-size:.73rem;margin:1px 0;}
.optn{display:inline-block;width:15px;height:15px;background:var(--blue-lt);
    border:1px solid var(--border);border-radius:3px;text-align:center;
    font-weight:700;font-size:.62rem;line-height:15px;margin-right:3px;color:var(--blue);}
.ans-ok{font-weight:700;color:var(--green);}
.ans-ko{font-weight:700;color:var(--red);}
.res-ico{text-align:center;font-size:1rem;}

/* ── Modules FORM ── */
.mod-block{border:1.5px solid var(--border);border-radius:10px;overflow:hidden;
    margin-bottom:16px;}
.mod-head{background:linear-gradient(135deg,var(--blue-mid),#144e8c);
    padding:9px 14px;display:flex;align-items:center;gap:9px;}
.mod-num{width:28px;height:28px;background:var(--gold);color:var(--blue);border-radius:50%;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;flex-shrink:0;}
.mod-head h4{flex:1;color:#fff;font-size:.82rem;font-weight:800;margin:0;}
.mod-head .mscore{font-size:.78rem;font-weight:800;color:var(--gold);white-space:nowrap;}
.mod-body{padding:11px 14px;}
.mod-recap{background:var(--blue-lt);border-left:3px solid var(--blue);border-radius:7px;
    padding:8px 12px;margin-bottom:11px;display:flex;flex-wrap:wrap;
    gap:7px 18px;align-items:center;font-size:.75rem;}
.mod-recap strong{color:var(--blue);}

/* ── Synthèse IF / FORM ── */
.synth-block{margin-top:14px;border:2px solid var(--blue);border-radius:11px;overflow:hidden;}
.synth-head{background:linear-gradient(135deg,var(--blue),var(--blue-mid));
    padding:9px 15px;display:flex;align-items:center;gap:8px;}
.synth-head i{color:var(--gold);}
.synth-head h4{margin:0;font-size:.82rem;font-weight:800;color:#fff;flex:1;}
.synth-head .savg{font-size:.92rem;font-weight:800;color:var(--gold);}
table.stbl{width:100%;border-collapse:collapse;}
table.stbl thead th{background:var(--blue-lt);color:var(--blue);padding:7px 11px;
    font-weight:800;font-size:.69rem;text-transform:uppercase;letter-spacing:.4px;
    border-bottom:2px solid var(--border);text-align:center;}
table.stbl thead th:first-child{text-align:left;}
table.stbl tbody td{padding:7px 11px;text-align:center;border-bottom:1px solid var(--border);
    font-size:.76rem;}
table.stbl tbody td:first-child{text-align:left;font-weight:600;}
table.stbl tbody tr:last-child td{border-bottom:none;}
table.stbl .trow td{background:#f4f7fc;font-weight:800;}
.sfoot-ok{background:var(--green-bg);padding:8px 15px;font-size:.74rem;font-weight:700;
    color:#15803d;display:flex;align-items:center;gap:6px;}
.sfoot-ko{background:var(--red-bg);padding:8px 15px;font-size:.74rem;font-weight:700;
    color:#b91c1c;display:flex;align-items:center;gap:6px;}

/* ── Pied de page ── */
.doc-foot{margin-top:26px;padding-top:13px;border-top:1px dashed var(--border);
    text-align:center;font-size:.68rem;color:var(--grey);font-style:italic;line-height:1.8;}

/* ── État vide ── */
.empty-state{text-align:center;padding:50px 20px;color:var(--grey);}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:12px;color:#c4cdd9;}

/* ══════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════ */
@media(max-width:640px){
    body{padding:8px 4px 30px;}
    .doc-body{padding:13px 11px;}
    .stats-strip{grid-template-columns:repeat(2,1fr);}
    .cand-grid{grid-template-columns:1fr 1fr;}
    .recap-grid{grid-template-columns:1fr 1fr;}
    .exam-banner{flex-direction:column;align-items:flex-start;}
}

/* ══════════════════════════════════════════════
   IMPRESSION
══════════════════════════════════════════════ */
@media print{
    .actions-bar{display:none!important;}
    body{background:white;padding:0;font-size:11px;}
    .doc{border-radius:0;box-shadow:none;max-width:100%;border:2px solid var(--blue);}
    .exam-block{break-inside:avoid;margin-bottom:18px;}
    .mod-block,.synth-block,.sommaire{break-inside:avoid;}
    .exam-block+.exam-block{break-before:page;}
    a{color:inherit;text-decoration:none;}
}
</style>
</head>
<body>

<!-- ══ BARRE D'ACTIONS ══ -->
<div class="actions-bar">
    <button class="btn-action btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer / Enregistrer PDF
    </button>
    <button class="btn-action btn-close" onclick="window.close()">
        <i class="fas fa-times"></i> Fermer
    </button>
</div>

<div class="doc">

    <!-- ══ EN-TÊTE ══ -->
    <div class="doc-head">
        <img src="../assets/images/banierenteanac.png" alt="ANAC GABON"
             onerror="this.style.display='none'">
        <div class="doc-title-bar">
            <div class="doc-title">
                <i class="fas fa-folder-open" style="color:var(--gold);margin-right:8px;"></i>
                Dossier Complet de Résultats
            </div>
            <div class="doc-subtitle">
                Complete Results File — EXASUR / AVSEC-FAL · ANAC GABON
            </div>
        </div>
    </div>

    <!-- ══ CORPS ══ -->
    <div class="doc-body">

    <?php if (empty($examens)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>Aucun examen enregistré pour ce candidat.</p>
        <p style="font-size:.8rem;margin-top:5px;">
            <strong><?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?></strong>
            — Code : <strong><?= htmlspecialchars($c['code_acces'] ?? '—') ?></strong>
        </p>
    </div>
    <?php else: ?>

    <!-- ════════════════════════════════
         CARTE CANDIDAT
    ════════════════════════════════ -->
    <div class="cand-card">
        <div class="cand-avatar"><i class="fas fa-user"></i></div>
        <div class="cand-info">
            <div class="cand-name">
                <?= htmlspecialchars($c['nomstagiaire'].' '.$c['prenomstagiaire']) ?>
            </div>
            <span class="cand-code"><?= htmlspecialchars($c['code_acces'] ?? '—') ?></span>
            <div class="cand-grid">
                <div class="cf"><span class="lbl">Organisme</span>
                    <span class="val"><?= htmlspecialchars($c['nomorga'] ?? '—') ?></span></div>
                <div class="cf"><span class="lbl">Poste</span>
                    <span class="val"><?= htmlspecialchars($c['postestagiaire'] ?? '—') ?></span></div>
                <div class="cf"><span class="lbl">Email</span>
                    <span class="val"><?= htmlspecialchars($c['emailstagiaire'] ?? '—') ?></span></div>
                <div class="cf"><span class="lbl">Téléphone</span>
                    <span class="val"><?= htmlspecialchars($c['telstagiaire'] ?? '—') ?></span></div>
                <div class="cf"><span class="lbl">Nationalité</span>
                    <span class="val"><?= htmlspecialchars($c['nationalite'] ?? '—') ?></span></div>
                <div class="cf"><span class="lbl">Date de naissance</span>
                    <span class="val"><?= $c['datenaiss'] ? date('d/m/Y', strtotime($c['datenaiss'])) : '—' ?></span></div>
                <div class="cf"><span class="lbl">Dossier généré le</span>
                    <span class="val"><strong><?= date('d/m/Y à H:i') ?></strong></span></div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════
         STATISTIQUES GLOBALES
    ════════════════════════════════ -->
    <div class="stats-strip">
        <div class="sbox" style="--c:#2563eb;">
            <div style="font-size:1.4rem;color:#2563eb;margin-bottom:3px;">
                <i class="fas fa-file-alt"></i></div>
            <div class="sv"><?= $nb_total ?></div>
            <div class="sl">Examens passés</div>
        </div>
        <div class="sbox" style="--c:var(--green);">
            <div style="font-size:1.4rem;color:var(--green);margin-bottom:3px;">
                <i class="fas fa-check-circle"></i></div>
            <div class="sv"><?= $nb_ok ?></div>
            <div class="sl">Réussites</div>
        </div>
        <div class="sbox" style="--c:var(--red);">
            <div style="font-size:1.4rem;color:var(--red);margin-bottom:3px;">
                <i class="fas fa-times-circle"></i></div>
            <div class="sv"><?= $nb_ko ?></div>
            <div class="sl">Échecs</div>
        </div>
        <div class="sbox" style="--c:var(--gold);">
            <div style="font-size:1.4rem;color:var(--gold);margin-bottom:3px;">
                <i class="fas fa-percent"></i></div>
            <div class="sv"><?= $taux ?>%</div>
            <div class="sl">Taux de réussite</div>
        </div>
    </div>

    <!-- ════════════════════════════════
         SOMMAIRE
    ════════════════════════════════ -->
    <div class="sommaire">
        <div class="som-head">
            <i class="fas fa-list-ul"></i>
            <h3>Sommaire — <?= count($examens) ?> examen(s)</h3>
        </div>
        <div class="som-body">
            <table class="stom">
                <thead>
                    <tr>
                        <th style="width:38px;">#</th>
                        <th>Type</th>
                        <th>Session / Formation</th>
                        <th>Partie</th>
                        <th>Date</th>
                        <th>Note</th>
                        <th>Score</th>
                        <th>Résultat</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($examens as $idx => $ex):
                    $num   = $idx + 1;
                    $pv    = round(floatval($ex['pourcentage']), 1);
                    $seuil = floatval($ex['seuil_reussite'] ?? 70);
                    $col   = barCol($pv, $seuil);
                    $is_theo = ($ex['type_code']==='IF' && $ex['type_session']==='theorie');
                    if ($is_theo && $pv>=70 && $pv<80)
                        $rbadge = '<span class="badge-warn">⚠️ Pratique auto.</span>';
                    elseif ($ex['reussite'])
                        $rbadge = '<span class="badge-ok">✅ Réussi</span>';
                    else
                        $rbadge = '<span class="badge-ko">❌ Échec</span>';
                    $pt = '';
                    if ($ex['type_session']==='theorie')  $pt = '📖 Théorie';
                    elseif ($ex['type_session']==='pratique') $pt = '🖼️ Pratique';
                ?>
                <tr>
                    <td><span class="som-num"><?= $num ?></span></td>
                    <td>
                        <span class="type-badge" style="<?= typeStyle($ex['type_code']) ?>">
                            <?= htmlspecialchars($ex['type_code']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="#exam-<?= $num ?>" class="som-link">
                            <?= htmlspecialchars($ex['nom_session']) ?>
                        </a>
                    </td>
                    <td style="font-size:.74rem;color:var(--grey);"><?= $pt ?: '—' ?></td>
                    <td style="font-size:.74rem;color:var(--grey);white-space:nowrap;">
                        <?= date('d/m/Y', strtotime($ex['date_fin'])) ?>
                    </td>
                    <td style="font-weight:700;font-size:.76rem;color:var(--blue);white-space:nowrap;">
                        <?= round($ex['note_finale'],1) ?>/<?= round($ex['note_sur'],1) ?> pts
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:5px;">
                            <span style="font-weight:700;font-size:.74rem;color:<?= $col ?>;">
                                <?= $pv ?>%
                            </span>
                            <div class="mini-bar" style="width:60px;">
                                <div class="mini-fill"
                                     style="width:<?= min($pv,100) ?>%;background:<?= $col ?>;"></div>
                            </div>
                        </div>
                    </td>
                    <td><?= $rbadge ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ════════════════════════════════════════════
         BOUCLE EXAMENS
    ════════════════════════════════════════════ -->
    <?php foreach ($examens as $idx => $ex):
        $num     = $idx + 1;
        $ite     = intval($ex['ite']);
        $pv      = round(floatval($ex['pourcentage']), 1);
        $seuil   = floatval($ex['seuil_reussite'] ?? 70);
        $col     = barCol($pv, $seuil);
        $is_ok   = intval($ex['reussite']);
        $ts      = $ex['type_session'] ?? '';
        $is_theo = ($ex['type_code']==='IF' && $ts==='theorie');

        /* Badge résultat */
        if ($is_theo && $pv>=70 && $pv<80)
            $rbadge = '<span class="badge-warn" style="font-size:.76rem;">⚠️ Pratique autorisée</span>';
        elseif ($is_ok)
            $rbadge = '<span class="badge-ok" style="font-size:.76rem;">✅ RÉUSSI / PASS</span>';
        else
            $rbadge = '<span class="badge-ko" style="font-size:.76rem;">❌ ÉCHEC / FAIL</span>';

        /* Chip partie */
        $pc = '';
        if ($ts==='theorie')   $pc='<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:20px;font-size:.67rem;font-weight:700;">📖 Théorie</span>';
        elseif($ts==='pratique') $pc='<span style="background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:20px;font-size:.67rem;font-weight:700;">🖼️ Pratique</span>';
    ?>
    <!-- ═══ EXAMEN #<?= $num ?> ═══ -->
    <div class="exam-block" id="exam-<?= $num ?>">

        <!-- ── Bandeau ── -->
        <div class="exam-banner">
            <div class="exam-banner-L">
                <div class="exam-idx"><?= $num ?></div>
                <div>
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="type-badge" style="<?= typeStyle($ex['type_code']) ?>">
                            <?= htmlspecialchars($ex['type_code']) ?>
                        </span>
                        <?= $pc ?> <?= $rbadge ?>
                    </div>
                    <h3><?= htmlspecialchars($ex['nom_session']) ?></h3>
                    <p>
                        <?= htmlspecialchars($ex['type_nom']) ?>
                        &nbsp;·&nbsp;
                        <?= date('d/m/Y à H:i', strtotime($ex['date_fin'])) ?>
                        <?php if (!empty($ex['locked']) && $ex['locked']): ?>
                        &nbsp;·&nbsp;
                        <span style="color:#fca5a5;font-weight:700;">
                            <i class="fas fa-lock"></i>
                            Verrouillé — <?= htmlspecialchars($ex['reason'] ?? '') ?>
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="exam-banner-R">
                <div class="score-chip">
                    <div class="sv"><?= $pv ?>%</div>
                    <div class="sl">Score</div>
                </div>
                <div class="score-chip">
                    <div class="sv" style="font-size:.92rem;">
                        <?= round($ex['note_finale'],1) ?>
                        <span style="font-size:.58rem;color:rgba(255,255,255,.6);">
                            /<?= round($ex['note_sur'],1) ?>
                        </span>
                    </div>
                    <div class="sl">Points</div>
                </div>
            </div>
        </div>

        <!-- ── Corps examen ── -->
        <div class="exam-body">

            <!-- ══ RÉCAPITULATIF (avant le tableau) ══ -->
            <div class="recap-card">
                <div class="recap-title">
                    <i class="fas fa-chart-bar"></i>
                    Récapitulatif / Summary
                </div>
                <div class="recap-grid">
                    <!-- Note -->
                    <div class="rstat" style="--rc:var(--blue);">
                        <div class="rl"><i class="fas fa-star" style="color:var(--gold);margin-right:2px;"></i>Note</div>
                        <div class="rv">
                            <?= round($ex['note_finale'],1) ?>
                            <span style="font-size:.58rem;font-weight:600;color:var(--grey);">
                                /<?= round($ex['note_sur'],1) ?>
                            </span>
                        </div>
                        <div class="rsub">Points</div>
                    </div>
                    <!-- Score % -->
                    <div class="rstat" style="--rc:<?= $col ?>;">
                        <div class="rl"><i class="fas fa-percent" style="margin-right:2px;"></i>Score</div>
                        <div class="rv" style="--rc:<?= $col ?>;"><?= $pv ?>%</div>
                        <div class="rsub">Seuil ≥ <?= $seuil ?>%</div>
                    </div>
                    <?php if (!empty($ex['note_theorique']) && $ex['note_theorique'] > 0): ?>
                    <div class="rstat" style="--rc:<?= ($ex['reussite_theo']??0)?'var(--green)':'var(--red)' ?>;">
                        <div class="rl"><i class="fas fa-book" style="margin-right:2px;"></i>Théorie</div>
                        <div class="rv"><?= round($ex['note_theorique'],1) ?> pts</div>
                        <div class="rsub"><?= ($ex['reussite_theo']??0)?'✅ Validé':'❌ Insuffisant' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ex['note_pratique']) && $ex['note_pratique'] > 0): ?>
                    <div class="rstat" style="--rc:<?= ($ex['reussite_prat']??0)?'var(--green)':'var(--red)' ?>;">
                        <div class="rl"><i class="fas fa-eye" style="margin-right:2px;"></i>Pratique</div>
                        <div class="rv"><?= round($ex['note_pratique'],1) ?> pts</div>
                        <div class="rsub"><?= ($ex['reussite_prat']??0)?'✅ Validé':'❌ Insuffisant' ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ex['moyenne_if']) && $ex['moyenne_if'] > 0): ?>
                    <div class="rstat" style="--rc:<?= ($ex['moyenne_if']>=80)?'var(--green)':'var(--red)' ?>;">
                        <div class="rl"><i class="fas fa-calculator" style="margin-right:2px;"></i>Moy. IF</div>
                        <div class="rv"><?= round($ex['moyenne_if'],1) ?>%</div>
                        <div class="rsub">Théorie + Pratique</div>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Barre de progression -->
                <div class="prog-track">
                    <div class="prog-fill"
                         style="width:<?= min($pv,100) ?>%;background:<?= $col ?>;"></div>
                </div>
                <div class="prog-lbl">
                    <span>0%</span>
                    <span style="color:<?= $col ?>;font-weight:700;">
                        <?= $pv ?>% — Seuil : <?= $seuil ?>%
                    </span>
                    <span>100%</span>
                </div>
                <?php if ($is_theo): ?>
                <div style="font-size:.7rem;color:var(--grey);margin-top:6px;background:var(--orange-bg);
                     padding:5px 10px;border-radius:6px;border-left:3px solid var(--orange);">
                    <i class="fas fa-info-circle" style="color:var(--orange);margin-right:4px;"></i>
                    IF Théorie : seuil d'accès pratique = 70% · seuil de réussite finale IF = 80%
                </div>
                <?php endif; ?>
            </div>

            <?php
            /* ══════════════════════════════════════════════
               FORM (type 5)
            ══════════════════════════════════════════════ */
            if ($ite == 5 && !empty($ex['modules'])): ?>

            <div class="sep">
                <span><i class="fas fa-layer-group" style="color:var(--gold);margin-right:5px;"></i>
                    Détail par module (<?= count($ex['modules']) ?>)</span>
            </div>

            <?php foreach ($ex['modules'] as $mod):
                $mp  = round(floatval($mod['pourcentage']), 1);
                $mok = intval($mod['reussite']);
                $mc  = $mok ? 'var(--green)' : 'var(--red)';
            ?>
            <div class="mod-block">
                <div class="mod-head">
                    <div class="mod-num"><?= intval($mod['numero_module']) ?></div>
                    <h4><?= htmlspecialchars($mod['nom_module_fr'] ?? 'Module '.$mod['numero_module']) ?></h4>
                    <div class="mscore">
                        <?= round($mod['note_finale'],1) ?>/<?= round($mod['note_sur'],1) ?> pts
                        · <?= $mp ?>% <?= $mok?'✅':'❌' ?>
                    </div>
                </div>
                <div class="mod-body">
                    <!-- Récap module AVANT tableau -->
                    <div class="mod-recap">
                        <div>
                            <i class="fas fa-star" style="color:var(--gold);margin-right:3px;"></i>
                            <strong>Note :</strong>
                            <?= round($mod['note_finale'],1) ?>/<?= round($mod['note_sur'],1) ?> pts
                        </div>
                        <div>
                            <i class="fas fa-percent" style="color:var(--blue);margin-right:3px;"></i>
                            <strong>Score :</strong>
                            <span style="color:<?= $mc ?>;font-weight:800;"><?= $mp ?>%</span>
                        </div>
                        <div>
                            <i class="fas fa-gavel" style="color:var(--grey);margin-right:3px;"></i>
                            <strong>Décision :</strong>
                            <span style="color:<?= $mc ?>;font-weight:800;">
                                <?= $mok?'✅ Validé':'❌ Non validé' ?>
                            </span>
                            <span style="font-size:.7rem;color:var(--grey);"> (seuil ≥ 70%)</span>
                        </div>
                        <div style="flex:1;min-width:90px;">
                            <div class="prog-track" style="height:5px;">
                                <div class="prog-fill"
                                     style="width:<?= min($mp,100) ?>%;background:<?= barCol($mp) ?>;"></div>
                            </div>
                        </div>
                        <small style="color:var(--grey);">
                            <?= htmlspecialchars($mod['sess_nom']) ?> —
                            <?= date('d/m/Y', strtotime($mod['date_fin'])) ?>
                        </small>
                    </div>

                    <?php if (!empty($mod['reponses'])): ?>
                    <div class="sep" style="margin:8px 0 9px;">
                        <span style="font-size:.65rem;">
                            <i class="fas fa-list-ol" style="color:var(--gold);margin-right:4px;"></i>
                            Questions (<?= count($mod['reponses']) ?>)
                        </span>
                    </div>
                    <div class="tbl-wrap">
                    <table class="qtbl">
                        <thead><tr>
                            <th style="width:38px;">#</th>
                            <th>Question (FR / EN)</th>
                            <th style="min-width:120px;">Options</th>
                            <th style="min-width:95px;">Réponse candidat</th>
                            <th style="min-width:95px;">Bonne réponse</th>
                            <th style="width:44px;text-align:center;">Rés.</th>
                        </tr></thead>
                        <tbody>
                        <?php $n=1; foreach ($mod['reponses'] as $rep):
                            $sel=$rep['selected_option']; $cor=$rep['correct_option'];
                            $sf=$sel?(optVal($rep,$sel)??'Non répondue'):'Non répondue';
                            $cf=$cor?(optVal($rep,$cor)??'—'):'—';
                        ?>
                        <tr class="<?= $rep['est_correcte']?'r-ok':'r-ko' ?>">
                            <td><div class="qnum"><?= $n++ ?></div></td>
                            <td>
                                <div class="qfr"><?= htmlspecialchars($rep['question_text_fr']) ?></div>
                                <?php if ($rep['type_question']==='pratique' && !empty($rep['images'])):
                                    $imgs=json_decode($rep['images'],true)??[]; ?>
                                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
                                    <?php foreach(array_slice($imgs,0,3) as $img): ?>
                                    <img src="../assets/images/<?= htmlspecialchars($img) ?>"
                                         style="height:46px;border-radius:4px;object-fit:cover;">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="qsep"></div>
                                <div class="qen"><?= htmlspecialchars($rep['question_text_en']) ?></div>
                            </td>
                            <td>
                                <?php for($op=1;$op<=4;$op++): $ot=optVal($rep,$op); if(!$ot) continue; ?>
                                <div class="opt-item">
                                    <span class="optn"><?= $op ?></span>
                                    <?= htmlspecialchars($ot) ?>
                                </div>
                                <?php endfor; ?>
                            </td>
                            <td><div class="<?= $rep['est_correcte']?'ans-ok':'ans-ko' ?>">
                                <?= htmlspecialchars($sf) ?></div></td>
                            <td><div class="ans-ok"><?= htmlspecialchars($cf) ?></div></td>
                            <td class="res-ico">
                                <?= $rep['est_correcte']
                                    ?'<i class="fas fa-check-circle" style="color:var(--green);"></i>'
                                    :'<i class="fas fa-times-circle" style="color:var(--red);"></i>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--grey);font-style:italic;font-size:.76rem;padding:7px 0;">
                        Aucune réponse enregistrée pour ce module.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; /* fin modules */ ?>

            <!-- Synthèse FORM -->
            <?php if (!empty($ex['synth_form'])): $sf2=$ex['synth_form']; ?>
            <div class="synth-block">
                <div class="synth-head">
                    <i class="fas fa-graduation-cap"></i>
                    <h4>Synthèse FORM — <?= $sf2['nb'] ?> module(s)</h4>
                    <div class="savg"><?= $sf2['moy'] ?>% de moyenne</div>
                </div>
                <table class="stbl">
                    <thead><tr>
                        <th>Module</th><th>Note</th><th>Score</th><th>Seuil</th><th>Décision</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($ex['modules'] as $mod):
                        $mp2=round(floatval($mod['pourcentage']),1);
                        $mo2=intval($mod['reussite']); ?>
                    <tr>
                        <td>
                            <span style="background:var(--blue);color:#fff;padding:1px 7px;
                                border-radius:50px;font-size:.67rem;font-weight:800;margin-right:4px;">
                                <?= intval($mod['numero_module']) ?>
                            </span>
                            <?= htmlspecialchars($mod['nom_module_fr']??'Module '.$mod['numero_module']) ?>
                        </td>
                        <td><?= round($mod['note_finale'],1) ?>/<?= round($mod['note_sur'],1) ?> pts</td>
                        <td>
                            <?= $mp2 ?>%
                            <div class="mini-bar" style="margin:3px auto 0;">
                                <div class="mini-fill"
                                     style="width:<?= min($mp2,100) ?>%;background:<?= $mo2?'var(--green)':'var(--red)' ?>;"></div>
                            </div>
                        </td>
                        <td>≥ 70%</td>
                        <td><?= $mo2?'<span class="badge-ok">✅ Validé</span>'
                                   :'<span class="badge-ko">❌ Insuffisant</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="trow">
                        <td><i class="fas fa-calculator" style="color:var(--gold);margin-right:5px;"></i>
                            MOYENNE GÉNÉRALE</td>
                        <td>—</td>
                        <td><strong style="color:<?= $sf2['reussite']?'var(--green)':'var(--red)' ?>;">
                            <?= $sf2['moy'] ?>%</strong></td>
                        <td>≥ 70%</td>
                        <td><?= $sf2['reussite']
                            ?'<span class="badge-ok">✅ VALIDÉE</span>'
                            :'<span class="badge-ko">❌ NON VALIDÉE</span>' ?></td>
                    </tr>
                    </tbody>
                </table>
                <div class="<?= $sf2['reussite']?'sfoot-ok':'sfoot-ko' ?>">
                    <i class="fas fa-<?= $sf2['reussite']?'check-circle':'times-circle' ?>"></i>
                    <?= $sf2['reussite']
                        ?'Formation VALIDÉE — Moyenne '.$sf2['moy'].'% ≥ 70% sur '.$sf2['nb'].' module(s).'
                        :'Formation NON VALIDÉE — Moyenne '.$sf2['moy'].'% inférieure au seuil de 70% requis.' ?>
                </div>
            </div>
            <?php endif; /* fin synth_form */ ?>

            <?php
            /* ══════════════════════════════════════════════
               AS / INST / SENS / IF — réponses directes
            ══════════════════════════════════════════════ */
            elseif (!empty($ex['reponses'])): ?>

            <div class="sep">
                <span>
                    <i class="fas fa-list-ol" style="color:var(--gold);margin-right:5px;"></i>
                    Détail des questions (<?= count($ex['reponses']) ?>)
                </span>
            </div>
            <div class="tbl-wrap">
            <table class="qtbl">
                <thead><tr>
                    <th style="width:38px;">#</th>
                    <th>Question (FR / EN)</th>
                    <th style="min-width:130px;">Options</th>
                    <th style="min-width:105px;">Réponse candidat</th>
                    <th style="min-width:105px;">Bonne réponse</th>
                    <th style="width:44px;text-align:center;">Rés.</th>
                </tr></thead>
                <tbody>
                <?php $n=1; foreach ($ex['reponses'] as $rep):
                    $sel=$rep['selected_option']; $cor=$rep['correct_option'];
                    $sf=$sel?(optVal($rep,$sel)??'Non répondue'):'Non répondue';
                    $cf=$cor?(optVal($rep,$cor)??'—'):'—';
                ?>
                <tr class="<?= $rep['est_correcte']?'r-ok':'r-ko' ?>">
                    <td><div class="qnum"><?= $n++ ?></div></td>
                    <td>
                        <div class="qfr"><?= htmlspecialchars($rep['question_text_fr']) ?></div>
                        <?php if ($rep['type_question']==='pratique' && !empty($rep['images'])):
                            $imgs=json_decode($rep['images'],true)??[]; ?>
                        <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:5px;">
                            <?php foreach(array_slice($imgs,0,3) as $img): ?>
                            <img src="../assets/images/<?= htmlspecialchars($img) ?>"
                                 style="height:48px;border-radius:4px;object-fit:cover;border:1px solid #ddd;">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="qsep"></div>
                        <div class="qen"><?= htmlspecialchars($rep['question_text_en']) ?></div>
                    </td>
                    <td>
                        <?php for($op=1;$op<=4;$op++): $ot=optVal($rep,$op); if(!$ot) continue; ?>
                        <div class="opt-item">
                            <span class="optn"><?= $op ?></span>
                            <?= htmlspecialchars($ot) ?>
                        </div>
                        <?php endfor; ?>
                    </td>
                    <td><div class="<?= $rep['est_correcte']?'ans-ok':'ans-ko' ?>">
                        <?= htmlspecialchars($sf) ?></div></td>
                    <td><div class="ans-ok"><?= htmlspecialchars($cf) ?></div></td>
                    <td class="res-ico">
                        <?= $rep['est_correcte']
                            ?'<i class="fas fa-check-circle" style="color:var(--green);"></i>'
                            :'<i class="fas fa-times-circle" style="color:var(--red);"></i>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Synthèse IF si disponible -->
            <?php if (!empty($ex['synth_if'])): $si=$ex['synth_if']; ?>
            <div class="synth-block">
                <div class="synth-head">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Synthèse — Certification IF</h4>
                    <div class="savg">
                        <?= ($si['moy']!==null)?$si['moy'].'% moyenne':'' ?>
                    </div>
                </div>
                <table class="stbl">
                    <thead><tr>
                        <th>Épreuve</th><th>Note</th><th>Score</th><th>Seuil</th><th>Décision</th>
                    </tr></thead>
                    <tbody>
                    <tr>
                        <td><i class="fas fa-book" style="color:#1e40af;margin-right:5px;"></i>
                            <strong>Théorie</strong></td>
                        <?php if ($si['theo']): $t=$si['theo']; $pt=round($t['pourcentage'],1); ?>
                        <td><?= round($t['note_finale'],1) ?>/<?= round($t['note_sur'],1) ?> pts</td>
                        <td><?= $pt ?>%
                            <div class="mini-bar" style="margin:3px auto 0;">
                                <div class="mini-fill" style="width:<?= min($pt,100) ?>%;
                                     background:<?= $t['reussite']?'var(--green)':'var(--red)' ?>;"></div>
                            </div></td>
                        <td>≥ 80%</td>
                        <td><?= $t['reussite']?'<span class="badge-ok">✅ Validée</span>'
                                              :'<span class="badge-ko">❌ Insuffisant</span>' ?></td>
                        <?php else: ?><td colspan="4" style="color:var(--grey);font-style:italic;">Non passée</td><?php endif; ?>
                    </tr>
                    <tr>
                        <td><i class="fas fa-eye" style="color:#9d174d;margin-right:5px;"></i>
                            <strong>Pratique</strong></td>
                        <?php if ($si['prat']): $p=$si['prat']; $pp=round($p['pourcentage'],1); ?>
                        <td><?= round($p['note_finale'],1) ?>/<?= round($p['note_sur'],1) ?> pts</td>
                        <td><?= $pp ?>%
                            <div class="mini-bar" style="margin:3px auto 0;">
                                <div class="mini-fill" style="width:<?= min($pp,100) ?>%;
                                     background:<?= $p['reussite']?'var(--green)':'var(--red)' ?>;"></div>
                            </div></td>
                        <td>≥ 80%</td>
                        <td><?= $p['reussite']?'<span class="badge-ok">✅ Validée</span>'
                                              :'<span class="badge-ko">❌ Insuffisant</span>' ?></td>
                        <?php else: ?><td colspan="4" style="color:var(--grey);font-style:italic;">Non passée</td><?php endif; ?>
                    </tr>
                    <tr class="trow">
                        <td><i class="fas fa-calculator" style="color:var(--gold);margin-right:5px;"></i>
                            MOYENNE IF</td>
                        <td colspan="2">
                            <strong style="color:<?= $si['reussite']?'var(--green)':'var(--red)' ?>;font-size:.92rem;">
                                <?= ($si['moy']!==null)?$si['moy'].'%':'—' ?>
                            </strong>
                        </td>
                        <td>≥ 80%</td>
                        <td><?= $si['reussite']
                            ?'<span class="badge-ok">✅ CERTIFIÉ(E)</span>'
                            :'<span class="badge-ko">❌ NON CERTIFIÉ(E)</span>' ?></td>
                    </tr>
                    </tbody>
                </table>
                <div class="<?= $si['reussite']?'sfoot-ok':'sfoot-ko' ?>">
                    <i class="fas fa-<?= $si['reussite']?'check-circle':'times-circle' ?>"></i>
                    <?= $si['reussite']
                        ?'Certification IF VALIDÉE — Moyenne '.$si['moy'].'% ≥ 80% (théorie et pratique validées).'
                        :'Certification IF NON VALIDÉE — Moyenne '.($si['moy']??'—').'% inférieure au seuil de 80% requis.' ?>
                </div>
            </div>
            <?php endif; /* fin synth_if */ ?>

            <?php elseif ($ite != 5): ?>
            <div style="text-align:center;padding:18px;color:var(--grey);font-style:italic;
                 border:1.5px dashed var(--border);border-radius:9px;font-size:.78rem;">
                <i class="fas fa-inbox" style="font-size:1.3rem;display:block;margin-bottom:5px;"></i>
                Aucune réponse enregistrée pour cet examen.
            </div>
            <?php endif; ?>

        </div><!-- /.exam-body -->
    </div><!-- /.exam-block -->
    <?php endforeach; /* fin boucle examens */ ?>

    <?php endif; /* fin if empty $examens */ ?>

    <!-- ── Pied de page ── -->
    <div class="doc-foot">
        <i class="fas fa-shield-halved" style="color:var(--gold);margin-right:5px;"></i>
        Dossier généré depuis le système <strong>EXASUR</strong> — ANAC GABON
        le <?= date('d/m/Y à H:i') ?><br>
        Direction de la Sûreté et de la Facilitation de l'Aviation Civile
        &nbsp;·&nbsp; <em>Document confidentiel — ne pas diffuser</em>
    </div>

    </div><!-- /.doc-body -->
</div><!-- /.doc -->

</body>
</html>
<?php $conn->close(); ?>