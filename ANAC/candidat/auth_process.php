<?php
/**
 * auth_process.php — EXASUR ANAC GABON
 * REMPLACE : EXASUR/ANAC/candidat/auth_process.php
 *
 * CORRECTION UNIQUE ET DÉFINITIVE DU BOUTON "OUI C'EST MOI" :
 *
 * ANCIEN CODE (ligne 354 original) :
 *   <button onclick="window.location.href=\'examen_start.php\'">
 *   → Les \' dans echo '...' PHP génèrent \' dans le HTML
 *   → Le navigateur reçoit onclick="window.location.href=\'examen_start.php\'"
 *   → ERREUR JS SILENCIEUSE → le bouton ne fait RIEN
 *
 * NOUVEAU CODE :
 *   <a href="examen_start.php" class="btn-ok">
 *   → Lien HTML pur, ZÉRO JavaScript
 *   → Fonctionne à 100% dans tous les navigateurs
 *   → La page de confirmation est générée via heredoc PHP
 *     (évite tous les problèmes d'apostrophes)
 *
 * LOGIQUE MÉTIER :
 *   - Habilitation : candidat_session (S1) + faire_formation/session_formation
 *     avec dates (S2) + faire_formation sans dates (S3 fallback)
 *   - Un candidat planifié IF ne peut pas passer AS (vérification stricte)
 *   - Multi-session : vérification "déjà passé" limitée à id_session courant
 *   - IF pratique : vérification score théorie ≥ 70%
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../php/db_connection.php';
include '../lang/lang_loader.php';

/* ════════════════════════════════════════════════════════════
   LECTURE POST
════════════════════════════════════════════════════════════ */
$code_acces    = isset($_POST['code_acces'])    ? intval($_POST['code_acces'])     : 0;
$mot_de_passe  = isset($_POST['mot_de_passe'])  ? trim($_POST['mot_de_passe'])    : '';
$idtype_examen = isset($_POST['idtype_examen']) ? intval($_POST['idtype_examen']) : 1;
$id_session    = isset($_POST['id_session'])    ? intval($_POST['id_session'])     : 0;

/* ── Type d'examen ─────────────────────────────────────── */
$stmt = $conn->prepare("SELECT * FROM type_examen WHERE idtype_examen = ?");
$stmt->bind_param("i", $idtype_examen);
$stmt->execute();
$type_info = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$type_info) {
    popup("error","Erreur","Type d'examen introuvable.","auth.php?type=1","#03224c");
    exit();
}
$a_deux_parties = intval($type_info['a_deux_parties']);

/* ── Session ───────────────────────────────────────────── */
if ($id_session <= 0) {
    popup("error","Session manquante","Veuillez selectionner une session.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
/*
 * CORRECTION BUG FORM : la session FORM peut avoir statut 'terminee'
 * mais des modules futurs encore accessibles.
 * → On charge TOUS les statuts et on vérifie manuellement ensuite.
 * → Inclure 'statut' dans le SELECT pour éviter le Warning ligne 254.
 */
$cs = $conn->prepare(
    "SELECT id_session, type_session, nom_session, date_debut, date_fin, statut
     FROM session_examen
     WHERE id_session = ? AND idtype_examen = ?"
);
$cs->bind_param("ii", $id_session, $idtype_examen);
$cs->execute();
$sess_res = $cs->get_result();
if ($sess_res->num_rows === 0) {
    popup("error","Session invalide","Cette session est introuvable.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
$session_data  = $sess_res->fetch_assoc();
$type_session  = $session_data['type_session']  ?? 'normal';
$sess_date_deb = $session_data['date_debut']    ?? date('Y-m-d');
$sess_date_fin = $session_data['date_fin']      ?? date('Y-m-d');
$sess_statut   = $session_data['statut']        ?? 'planifiee';
$cs->close();

/* Bloquer si annulée (mais pas terminée pour FORM — les modules peuvent encore être futurs) */
if ($sess_statut === 'annulee') {
    popup("error","Session annulee","Cette session a ete annulee.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
/* Pour les sessions non-FORM : bloquer si terminée */
if ($idtype_examen != 5 && $sess_statut === 'terminee') {
    popup("error","Session terminee","Cette session est terminee et fermee.","auth.php?type=$idtype_examen","#03224c");
    exit();
}

/* ── Candidat ──────────────────────────────────────────── */
$sc = $conn->prepare(
    "SELECT c.*, s.nomstagiaire, s.prenomstagiaire
     FROM candidat c
     JOIN si_anac.stagiaire s ON c.idstagiaire = s.idstagiaire
     WHERE c.code_acces = ?"
);
$sc->bind_param("i", $code_acces);
$sc->execute();
$res = $sc->get_result();
if ($res->num_rows === 0) {
    popup("error","Acces refuse","Code d'acces incorrect ou candidat introuvable.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
$candidat = $res->fetch_assoc();
$sc->close();

$idcandidat  = $candidat['idcandidat'];
$idstagiaire = $candidat['idstagiaire'];
$nom_complet = trim($candidat['nomstagiaire'] . ' ' . $candidat['prenomstagiaire']);

/* ── Mot de passe ──────────────────────────────────────── */
if (!password_verify($mot_de_passe, $candidat['mot_de_passe'])) {
    popup("error","Acces refuse","Mot de passe incorrect.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
if ($candidat['bloque'] == 1) {
    popup("error","Compte bloque","Votre compte est bloque. Contactez l'administration.","auth.php?type=$idtype_examen","#03224c");
    exit();
}
if ($candidat['is_logged_in'] == 1) {
    popup("warning","Deja connecte","Vous etes deja connecte sur un autre appareil.","auth.php?type=$idtype_examen","#D4AF37");
    exit();
}

/* ════════════════════════════════════════════════════════════
   HABILITATION

   FORM (idtype_examen=5) : RÈGLE STRICTE
   ► Seul S1 est utilisé : le candidat doit être dans candidat_session
     pour la session d'ÉVALUATION choisie (idmodule IS NOT NULL).
   ► S2 et S3 (faire_formation) sont EXCLUS pour FORM :
     être dans la formation conteneur ne suffit PAS.
     Exemple : candidat 2237 est dans la session conteneur AGFAC-DU
     mais n'a pas été sélectionné pour le Module 8 → REFUSÉ.

   Autres types (AS, IF, INST, SENS) :
   S1 : candidat_session (table directe admin)
   S2 : faire_formation + session_formation avec dates
   S3 : faire_formation sans contrainte dates (fallback)

   RÈGLE IF STRICTE : un candidat ayant déjà complété les 2 épreuves IF
   (théorie + pratique avec résultat) ne peut PAS repasser un nouvel IF
   sur une session différente — il doit s'inscrire à une nouvelle session.
════════════════════════════════════════════════════════════ */
$habilite = false;

/* ── S1 : candidat_session (OBLIGATOIRE pour FORM, prioritaire pour les autres) ── */
$sh = $conn->prepare("SELECT id FROM candidat_session WHERE idcandidat = ? AND id_session = ? AND habilite = 1");
$sh->bind_param("ii", $idcandidat, $id_session);
$sh->execute();
$habilite = ($sh->get_result()->num_rows > 0);
$sh->close();

/* ── Pour FORM : S1 est la SEULE stratégie — PAS de S2/S3 ── */
if ($idtype_examen == 5 && !$habilite) {
    $conn->close();
    popupHtml(
        "error",
        "Accès non autorisé",
        "<p>Vous n'êtes pas inscrit(e) à cette évaluation de module.</p>
         <div style='background:#fff1f2;border-left:4px solid #dc2626;border-radius:8px;padding:12px 14px;margin:10px 0;'>
           <p style='color:#b91c1c;font-weight:700;'>Motif : votre profil n'a pas été sélectionné pour ce module spécifique.</p>
           <p style='color:#666;font-size:.88rem;margin-top:6px;'>
             Même si vous faites partie d'une session de formation, seuls les stagiaires 
             explicitement affectés à ce module d'évaluation sont autorisés à le passer.
           </p>
         </div>
         <p style='color:#666;font-size:.9rem;'>Contactez l'administration ANAC pour vérifier votre affectation.</p>",
        "auth.php?type=5", "#03224c"
    );
    exit();
}

/* ── S2 et S3 : uniquement pour les types NON-FORM ── */
if ($idtype_examen != 5) {

    /* S2 : avec dates session_formation */
    if (!$habilite) {
        $sf2 = $conn->prepare("
            SELECT ff.idfaireform
            FROM si_anac.faire_formation ff
            JOIN si_anac.session_formation sf ON ff.idsessionform = sf.idsessionform
            WHERE ff.idstagiaire = ?
              AND ff.statut != 'Maintien competences'
              AND CASE
                    WHEN ff.statut = 'Inspection Filtrage'       THEN 2
                    WHEN ff.statut = 'Certification Instructeur' THEN 3
                    WHEN ff.statut = 'Sensibilisation'           THEN 4
                    WHEN ff.statut = 'Formation'                 THEN 5
                    ELSE 1
                  END = ?
              AND sf.datedebusession <= ?
              AND sf.datefinsession  >= ?
            LIMIT 1
        ");
        if ($sf2) {
            $sf2->bind_param("iiss", $idstagiaire, $idtype_examen, $sess_date_fin, $sess_date_deb);
            $sf2->execute();
            $habilite = ($sf2->get_result()->num_rows > 0);
            $sf2->close();
        }
    }

    /* S3 : fallback sans dates */
    if (!$habilite) {
        $sf3 = $conn->prepare("
            SELECT ff.idfaireform
            FROM si_anac.faire_formation ff
            WHERE ff.idstagiaire = ?
              AND ff.statut != 'Maintien competences'
              AND CASE
                    WHEN ff.statut = 'Inspection Filtrage'       THEN 2
                    WHEN ff.statut = 'Certification Instructeur' THEN 3
                    WHEN ff.statut = 'Sensibilisation'           THEN 4
                    WHEN ff.statut = 'Formation'                 THEN 5
                    ELSE 1
                  END = ?
            LIMIT 1
        ");
        if ($sf3) {
            $sf3->bind_param("ii", $idstagiaire, $idtype_examen);
            $sf3->execute();
            $habilite = ($sf3->get_result()->num_rows > 0);
            $sf3->close();
        }
    }

}

if (!$habilite) {
    $conn->close();
    $tn = htmlspecialchars($type_info['nom_fr'], ENT_QUOTES, 'UTF-8');
    popupHtml(
        "error",
        "Acces non autorise",
        "<p>Vous n'etes pas habilite(e) a passer l'examen <strong>{$tn}</strong>.</p>
         <p style='margin-top:8px;color:#666;font-size:.9rem;'>
         Seuls les candidats planifies pour cette session sont autorises.<br>
         Contactez l'administration ANAC.</p>",
        "auth.php?type=$idtype_examen",
        "#03224c"
    );
    exit();
}

/* ════════════════════════════════════════════════════════════
   DÉJÀ PASSÉ CETTE SESSION ?
   Vérification stricte sur note_finale > 0 OU locked = 1
   (permet multi-session : 2025 vs 2026 = sessions différentes)
════════════════════════════════════════════════════════════ */
$sdp = $conn->prepare(
    "SELECT pourcentage, reussite, note_finale, locked, date_fin
     FROM resultats
     WHERE idcandidat = ? AND id_session = ?
     ORDER BY date_fin DESC LIMIT 1"
);
$sdp->bind_param("ii", $idcandidat, $id_session);
$sdp->execute();
$deja = $sdp->get_result()->fetch_assoc();
$sdp->close();

if ($deja && (floatval($deja['note_finale']) > 0 || intval($deja['locked']) === 1)) {
    $pct   = round(floatval($deja['pourcentage']), 1);
    $reuss = intval($deja['reussite']);
    $dated = date('d/m/Y H:i', strtotime($deja['date_fin']));
    $c     = $reuss ? '#16a34a' : '#dc2626';
    $men   = $reuss ? 'VALIDE'  : 'AJOURNE';
    $conn->close();
    popupHtml(
        "info","Examen deja passe",
        "<p>Vous avez deja passe cet examen pour cette session.</p>
         <div style='background:#f4f7fc;border-radius:12px;padding:14px;margin:10px 0;'>
           <p>Score : <strong style='color:{$c};font-size:1.1rem;'>{$pct}%</strong></p>
           <p>Mention : <strong style='color:{$c};'>{$men}</strong></p>
           <p style='color:#9ca3af;font-size:.85rem;'>Le {$dated}</p>
         </div>
         <p style='color:#666;font-size:.9rem;'>Pour repasser, inscrivez-vous a une nouvelle session ANAC.</p>",
        "auth.php?type=$idtype_examen", "#03224c"
    );
    exit();
}

/* ── IF : deux épreuves déjà faites (résultats complets) ? ── */
if ($a_deux_parties == 1 && $idtype_examen == 2) {
    $s2 = $conn->prepare(
        "SELECT se.type_session, r.pourcentage, r.reussite
         FROM resultats r
         JOIN session_examen se ON r.id_session = se.id_session
         WHERE r.idcandidat = ? AND se.idtype_examen = 2 AND r.note_finale > 0
         ORDER BY r.date_fin DESC"
    );
    $s2->bind_param("i", $idcandidat);
    $s2->execute();
    $rows_if = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
    $s2->close();

    $has_theo = false; $has_prat = false;
    $pct_theo = 0;    $pct_prat = 0; $reuss_if = 0;
    foreach ($rows_if as $row) {
        if ($row['type_session'] === 'theorie'  && !$has_theo) { $has_theo = true; $pct_theo = round(floatval($row['pourcentage']),1); }
        if ($row['type_session'] === 'pratique' && !$has_prat) { $has_prat = true; $pct_prat = round(floatval($row['pourcentage']),1); $reuss_if = intval($row['reussite']); }
    }

    /*
     * CORRECTION : bloquer si les 2 épreuves sont complètes,
     * quelle que soit l'épreuve demandée (théorie OU pratique).
     * Avant : seulement bloqué si type_session==='theorie' → un candidat
     * pouvait tenter de repasser la pratique même après avoir tout complété.
     */
    if ($has_theo && $has_prat) {
        $moy = round(($pct_theo+$pct_prat)/2,1);
        $cr  = $reuss_if ? '#16a34a' : '#dc2626';
        $vd  = $reuss_if ? 'VALIDÉ'  : 'AJOURNÉ';
        $conn->close();
        popupHtml(
            "info","Certification IF déjà complétée",
            "<p><strong>Vous avez déjà effectué les deux épreuves de la certification IF.</strong></p>
             <div style='background:#f4f7fc;border-radius:12px;padding:14px;margin:10px 0;'>
               <p>Théorie : <strong>{$pct_theo}%</strong></p>
               <p>Pratique : <strong>{$pct_prat}%</strong></p>
               <p style='border-top:1px solid #ddd;padding-top:8px;'>
                 Moyenne IF : <strong style='color:{$cr};font-size:1.1rem;'>{$moy}%</strong>
                 &mdash; <strong style='color:{$cr};'>{$vd}</strong>
               </p>
             </div>
             <p style='color:#666;font-size:.88rem;'>Votre certification est terminée. Pour repasser, contactez l'administration ANAC.</p>",
            "auth.php?type=2", "#03224c"
        );
        exit();
    }
}

/* ── IF Pratique : théorie OBLIGATOIRE avant pratique ─────
   RÈGLE : pour accéder à la pratique, le candidat DOIT avoir
   une théorie validée (≥70%) dans la MÊME session ou dans une
   session de même idtypeformation. Cela s'applique même si le
   candidat a déjà fait un IF complet dans le passé — pour une
   nouvelle session, il repart de la théorie.
────────────────────────────────────────────────────────── */
$pct_theorie_ok = null;
if ($a_deux_parties == 1 && $idtype_examen == 2 && $type_session === 'pratique') {

    /* Chercher une théorie passée liée à la MÊME session (même idtypeformation) */
    $sth = $conn->prepare(
        "SELECT r.pourcentage, r.note_finale
         FROM resultats r
         JOIN session_examen se_prat ON se_prat.id_session = ?
         JOIN session_examen se_theo ON r.id_session = se_theo.id_session
         WHERE r.idcandidat = ?
           AND se_theo.idtype_examen = 2
           AND se_theo.type_session = 'theorie'
           AND r.note_finale > 0
           AND (
               /* Même session parente : même idtypeformation */
               se_theo.idtypeformation = se_prat.idtypeformation
               OR
               /* Théorie de la même période (dates proches ±90j) */
               ABS(DATEDIFF(se_theo.date_debut, se_prat.date_debut)) <= 90
           )
         ORDER BY r.date_fin DESC LIMIT 1"
    );
    $sth->bind_param("ii", $id_session, $idcandidat);
    $sth->execute();
    $theo_row = $sth->get_result()->fetch_assoc();
    $sth->close();

    if (!$theo_row) {
        $conn->close();
        popupHtml(
            "error","Théorie requise",
            "<p><strong>Vous devez passer la partie théorie IF avant la partie pratique.</strong></p>
             <div style='background:#fff1f2;border-left:4px solid #dc2626;border-radius:8px;padding:12px 14px;margin:10px 0;'>
               <p style='color:#b91c1c;font-size:.9rem;'>
                 <i class='fas fa-info-circle me-1'></i>
                 Même si vous avez déjà passé un examen IF dans le passé,
                 chaque nouvelle session IF exige de recommencer par la théorie.
               </p>
             </div>
             <p style='color:#555;font-size:.9rem;'>Connectez-vous sur la session théorie IF correspondante.</p>",
            "auth.php?type=2", "#03224c"
        );
        exit();
    }

    $pct_theorie_ok = round(floatval($theo_row['pourcentage']), 1);
    if ($pct_theorie_ok < 70) {
        $conn->close();
        popupHtml(
            "error","Score théorique insuffisant",
            "<div style='background:#fee2e2;border-radius:12px;padding:14px;margin-bottom:12px;border-left:4px solid #dc2626;'>
               <p style='font-weight:700;color:#991b1b;'>Score théorie : <strong>{$pct_theorie_ok}%</strong></p>
               <p style='color:#991b1b;font-size:.9rem;'>Seuil minimum requis : <strong>70%</strong></p>
             </div>
             <p style='color:#555;font-size:.9rem;'>Accès à la pratique refusé. Vous êtes ajourné(e).</p>",
            "../index.php", "#03224c"
        );
        exit();
    }
}

/* ════════════════════════════════════════════════════════════
   STOCKER temp_auth
════════════════════════════════════════════════════════════ */
$_SESSION['temp_auth'] = [
    'idcandidat'    => $idcandidat,
    'code_acces'    => $code_acces,
    'idtype_examen' => $idtype_examen,
    'id_session'    => $id_session,
    'nom_complet'   => $nom_complet,
    'nom_session'   => $session_data['nom_session'],
    'type_session'  => $type_session,
    'pct_theorie'   => $pct_theorie_ok,
];

$conn->close();

/* ════════════════════════════════════════════════════════════
   PAGE CONFIRMATION IDENTITÉ
   !! CORRECTION DU BOUTON : <a href="examen_start.php"> !!
   Heredoc PHP = aucun problème d'apostrophe ou de \' dans JS
════════════════════════════════════════════════════════════ */
$fullName = htmlspecialchars($nom_complet,                 ENT_QUOTES, 'UTF-8');
$typeNom  = htmlspecialchars($type_info['nom_fr'],         ENT_QUOTES, 'UTF-8');
$nomSess  = htmlspecialchars($session_data['nom_session'], ENT_QUOTES, 'UTF-8');
$epreuve  = ($type_session === 'theorie')  ? 'Epreuve Theorique'
          : (($type_session === 'pratique') ? 'Epreuve Pratique' : 'Examen');

echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirmation - EXASUR ANAC</title>
<link rel="icon" href="../assets/images/LOGOANAC.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Candara','Calibri',sans-serif;
    background:linear-gradient(135deg,#03224c,#0a2b4a);
    min-height:100vh;display:flex;align-items:center;
    justify-content:center;padding:20px;
}
.box{
    background:#fff;border-radius:28px;padding:40px 36px;
    max-width:480px;width:100%;
    box-shadow:0 30px 60px rgba(0,0,0,.4);
    border-top:5px solid #D4AF37;
    animation:up .5s ease both;
}
@keyframes up{from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:none;}}
.logo{width:84px;display:block;margin:0 auto 18px;border-radius:10px;
      padding:6px;background:#fff;box-shadow:0 4px 14px rgba(3,34,76,.2);}
h2{color:#03224c;font-weight:800;font-size:1.15rem;text-align:center;margin-bottom:20px;}
.info-card{background:#f4f7fc;border-radius:14px;padding:16px 18px;
           margin-bottom:18px;border-left:5px solid #D4AF37;}
.info-row{display:flex;align-items:center;gap:12px;
          padding:9px 0;border-bottom:1px solid #e5eaf3;}
.info-row:last-child{border-bottom:none;}
.ico{width:36px;height:36px;background:#03224c;color:#D4AF37;border-radius:50%;
     display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0;}
.lbl{font-weight:600;color:#6b7a90;font-size:.7rem;display:block;margin-bottom:1px;}
.val{font-weight:800;color:#03224c;font-size:.88rem;}
.warn{background:#fffbef;border:1.5px solid #f0d060;border-radius:12px;
      padding:10px 14px;font-size:.8rem;color:#7a5800;margin-bottom:18px;
      display:flex;align-items:center;gap:9px;}
.btns{display:flex;gap:12px;}
.btn-ok{
    flex:1;background:linear-gradient(135deg,#16a34a,#15803d);
    color:#fff !important;text-decoration:none !important;
    padding:15px 8px;border-radius:50px;font-weight:800;font-size:.95rem;
    transition:all .3s;font-family:'Candara','Calibri',sans-serif;
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn-ok:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(22,163,74,.4);}
.btn-no{
    flex:1;background:linear-gradient(135deg,#dc2626,#b91c1c);
    color:#fff !important;text-decoration:none !important;
    padding:15px 8px;border-radius:50px;font-weight:800;font-size:.95rem;
    transition:all .3s;font-family:'Candara','Calibri',sans-serif;
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn-no:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(220,38,38,.4);}
</style>
</head>
<body>
<div class="box">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON"
         class="logo" onerror="this.style.display='none'">
    <h2>
        <i class="fas fa-user-shield" style="color:#D4AF37;margin-right:8px;"></i>
        Confirmation d'identite - EXASUR
    </h2>
    <div class="info-card">
        <div class="info-row">
            <div class="ico"><i class="fas fa-user"></i></div>
            <div><span class="lbl">Nom complet</span><span class="val">{$fullName}</span></div>
        </div>
        <div class="info-row">
            <div class="ico"><i class="fas fa-key"></i></div>
            <div><span class="lbl">Code d'acces</span><span class="val">{$code_acces}</span></div>
        </div>
        <div class="info-row">
            <div class="ico"><i class="fas fa-clipboard-list"></i></div>
            <div><span class="lbl">Type d'examen</span><span class="val">{$typeNom}</span></div>
        </div>
        <div class="info-row">
            <div class="ico"><i class="fas fa-calendar-alt"></i></div>
            <div><span class="lbl">Session</span><span class="val">{$nomSess}</span></div>
        </div>
        <div class="info-row">
            <div class="ico"><i class="fas fa-layer-group"></i></div>
            <div><span class="lbl">Epreuve</span><span class="val">{$epreuve}</span></div>
        </div>
    </div>
    <div class="warn">
        <i class="fas fa-exclamation-triangle" style="color:#D4AF37;font-size:.9rem;flex-shrink:0;"></i>
        <span>Verifiez que ces informations sont correctes avant de confirmer.</span>
    </div>
    <div class="btns">
        <a href="examen_start.php" class="btn-ok">
            <i class="fas fa-check-circle"></i>
            OUI, C'EST MOI
        </a>
        <a href="auth.php?type={$idtype_examen}" class="btn-no">
            <i class="fas fa-times-circle"></i>
            NON
        </a>
    </div>
</div>
</body>
</html>
HTML;

/* ════════════════════════════════════════════════════════════
   FONCTIONS POPUP
════════════════════════════════════════════════════════════ */
function popup(string $type, string $titre, string $msg, string $url, string $col): void
{
    $t = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($msg,   ENT_QUOTES, 'UTF-8');
    $r = htmlspecialchars($url,   ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>body{background:#03224c;font-family:'Candara',sans-serif;}</style>
</head><body>
<script>
document.addEventListener("DOMContentLoaded",function(){
    Swal.fire({icon:"{$type}",title:"{$t}",text:"{$m}",
               confirmButtonColor:"{$col}",allowOutsideClick:false})
        .then(function(){window.location.href="{$r}";});
});
</script>
</body></html>
HTML;
}

function popupHtml(string $type, string $titre, string $htmlMsg, string $url, string $col): void
{
    $t  = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    $r  = htmlspecialchars($url,   ENT_QUOTES, 'UTF-8');
    $js = json_encode($htmlMsg, JSON_HEX_TAG | JSON_HEX_AMP);
    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>body{background:#03224c;font-family:'Candara',sans-serif;}</style>
</head><body>
<script>
var h={$js};
document.addEventListener("DOMContentLoaded",function(){
    Swal.fire({icon:"{$type}",title:"{$t}",html:h,
               confirmButtonColor:"{$col}",allowOutsideClick:false})
        .then(function(){window.location.href="{$r}";});
});
</script>
</body></html>
HTML;
}
?>