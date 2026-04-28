<?php
/**
 * examen_start.php — EXASUR ANAC GABON
 * EXASUR/ANAC/candidat/examen_start.php
 *
 * CORRECTION DÉFINITIVE :
 *  examen.php     = UNIQUEMENT IF Pratique (images scanner)
 *  examen_qcm.php = AS · IF Théorie · INST · SENS · FORM
 *
 * DURÉES CORRIGÉES :
 *  - AS (type 1) : 90 minutes (1h30)
 *  - IF Théorie (type 2 + type_session='theorie') : 90 minutes (1h30)
 *  - IF Pratique (type 2 + type_session='pratique') : 60 minutes (1h)
 *  - INST (type 3) : 90 minutes (1h30)
 *  - SENS (type 4) : 90 minutes (1h30)
 *  - FORM (type 5) : variable selon configuration
 *
 * Si temp_auth absent → message clair, PAS de boucle de redirections
 * Si résultat fantôme → nettoyage automatique
 * Si résultat complet → afficher résultat
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../php/db_connection.php';
include '../lang/lang_loader.php';

/* ── temp_auth requis ─────────────────────────────────────── */
if (!isset($_SESSION['temp_auth'])) {
    $conn->close();
    page_erreur(
        'Session expirée',
        'Votre session a expiré ou vous avez accès directement à cette page sans vous être authentifié.',
        '../index.php',
        'warning'
    );
    exit();
}

$temp          = $_SESSION['temp_auth'];
$idcandidat    = intval($temp['idcandidat']);
$idtype_examen = intval($temp['idtype_examen']);
$id_session    = intval($temp['id_session']);
$type_session  = $temp['type_session'];

/* ── Résultat complet existant pour cette session ? ────────── */
$cr = $conn->prepare(
    "SELECT id, note_finale, locked FROM resultats
     WHERE idcandidat=? AND id_session=? ORDER BY id DESC LIMIT 1"
);
$cr->bind_param("ii", $idcandidat, $id_session);
$cr->execute();
$res_row = $cr->get_result()->fetch_assoc();
$cr->close();

if ($res_row) {
    $note   = floatval($res_row['note_finale']);
    $locked = intval($res_row['locked']);
    if ($note > 0 || $locked === 1) {
        /* Résultat réel → afficher */
        $_SESSION['idcandidat']    = $idcandidat;
        $_SESSION['id_session']    = $id_session;
        $_SESSION['idtype_examen'] = $idtype_examen;
        $_SESSION['code_acces']    = $temp['code_acces'];
        $_SESSION['nom_complet']   = $temp['nom_complet'];
        $_SESSION['type_session']  = $type_session;
        unset($_SESSION['temp_auth']);
        $conn->close();
        header("Location: resultat.php");
        exit();
    } else {
        /* Résultat fantôme (note=0, non verrouillé) → nettoyer */
        $rid = intval($res_row['id']);
        $conn->query("DELETE FROM resultats WHERE id=$rid");
        $conn->query("DELETE FROM reponses_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session");
        $conn->query("DELETE FROM progression_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session");
    }
}

/* ── Connexion simultanée ─────────────────────────────────── */
$cl = $conn->prepare("SELECT is_logged_in FROM candidat WHERE idcandidat=?");
$cl->bind_param("i", $idcandidat);
$cl->execute();
$cl_row = $cl->get_result()->fetch_assoc();
$cl->close();
if ($cl_row && $cl_row['is_logged_in'] == 1) {
    unset($_SESSION['temp_auth']);
    $conn->close();
    page_erreur(
        'Déjà connecté',
        'Vous êtes déjà connecté sur un autre appareil. Fermez cette session et réessayez dans quelques instants.',
        'auth.php?type=' . $idtype_examen,
        'warning'
    );
    exit();
}

/* ── Marquer connecté ─────────────────────────────────────── */
$mk = $conn->prepare("UPDATE candidat SET is_logged_in=1, last_login=NOW() WHERE idcandidat=?");
$mk->bind_param("i", $idcandidat);
$mk->execute();
$mk->close();

/* ── Type d'examen ────────────────────────────────────────── */
$st = $conn->prepare("SELECT * FROM type_examen WHERE idtype_examen=?");
$st->bind_param("i", $idtype_examen);
$st->execute();
$type_info = $st->get_result()->fetch_assoc();
$st->close();
if (!$type_info) {
    $conn->query("UPDATE candidat SET is_logged_in=0 WHERE idcandidat=$idcandidat");
    unset($_SESSION['temp_auth']);
    $conn->close();
    page_erreur(
        'Type d\'examen introuvable',
        'Le type d\'examen demandé (ID : ' . $idtype_examen . ') est introuvable. Contactez l\'administration ANAC.',
        'auth.php?type=1',
        'error'
    );
    exit();
}
$a_deux_parties = intval($type_info['a_deux_parties']);

/* ── Charger les questions ────────────────────────────────── */
$sql_q = "SELECT q.* FROM question q
          JOIN session_questions sq ON q.id=sq.question_id
          WHERE sq.session_id=?";

/* Filtre IF : théorie ou pratique selon l'épreuve */
if ($a_deux_parties == 1 && $idtype_examen == 2) {
    if ($type_session === 'theorie')  $sql_q .= " AND q.type_question='theorique'";
    if ($type_session === 'pratique') $sql_q .= " AND q.type_question='pratique'";
}
$sql_q .= " ORDER BY sq.ordre ASC, sq.id ASC";

$stmt_q = $conn->prepare($sql_q);
$stmt_q->bind_param("i", $id_session);
$stmt_q->execute();
$all_questions = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_q->close();

/* ── Aucune question ──────────────────────────────────────── */
if (empty($all_questions)) {
    $conn->query("UPDATE candidat SET is_logged_in=0 WHERE idcandidat=$idcandidat");
    unset($_SESSION['temp_auth']);
    $conn->close();
    page_erreur(
        'Session sans questions',
        'Cette session d\'examen ne contient aucune question ('
        . ucfirst($type_session)
        . '). Veuillez contacter l\'administration ANAC pour régulariser cette situation.',
        'auth.php?type=' . $idtype_examen,
        'error'
    );
    exit();
}

/* ── Mélanger les questions ───────────────────────────────── */
shuffle($all_questions);
$questions    = $all_questions;
$nb_questions = count($questions);

/* ── Progression : créer ou reprendre ────────────────────── */
$ps = $conn->prepare(
    "SELECT current_index_theo, current_index_pra, infractions, reponses_json
     FROM progression_candidat WHERE idcandidat=? AND id_session=?"
);
$ps->bind_param("ii", $idcandidat, $id_session);
$ps->execute();
$ps_row = $ps->get_result();

if ($ps_row->num_rows > 0) {
    $prog = $ps_row->fetch_assoc();
    $current_index = ($type_session === 'pratique')
        ? intval($prog['current_index_pra'])
        : intval($prog['current_index_theo']);
    $_SESSION['reponses']    = !empty($prog['reponses_json']) ? json_decode($prog['reponses_json'], true) : [];
    $_SESSION['infractions'] = intval($prog['infractions']);
} else {
    $current_index = 0;
    $_SESSION['reponses']    = [];
    $_SESSION['infractions'] = 0;
    $ordre_json = json_encode($questions);
    if ($type_session === 'pratique') {
        $ip = $conn->prepare(
            "INSERT INTO progression_candidat
             (idcandidat,id_session,current_index_pra,ordre_questions_pra,partie_encours,infractions)
             VALUES(?,?,0,?,'pratique',0)
             ON DUPLICATE KEY UPDATE
             current_index_pra=0,ordre_questions_pra=VALUES(ordre_questions_pra),partie_encours='pratique'"
        );
        $ip->bind_param("iis", $idcandidat, $id_session, $ordre_json);
    } else {
        $ip = $conn->prepare(
            "INSERT INTO progression_candidat
             (idcandidat,id_session,current_index_theo,ordre_questions_theo,partie_encours,infractions)
             VALUES(?,?,0,?,'theorique',0)
             ON DUPLICATE KEY UPDATE
             current_index_theo=0,ordre_questions_theo=VALUES(ordre_questions_theo),partie_encours='theorique'"
        );
        $ip->bind_param("iis", $idcandidat, $id_session, $ordre_json);
    }
    $ip->execute();
    $ip->close();
}
$ps->close();

/* ════════════════════════════════════════════════════════════
   DURÉES CORRIGÉES PAR TYPE D'EXAMEN
   ════════════════════════════════════════════════════════════ */
$duree_min = intval($type_info['duree_minutes'] ?? 90);

// Forcer les durées spécifiques selon le type et l'épreuve
if ($idtype_examen == 2) {
    // IF
    if ($type_session === 'pratique') {
        $duree_min = 60;  // IF Pratique : 1 heure
    } elseif ($type_session === 'theorie') {
        $duree_min = 90;  // IF Théorie : 1h30
    }
} elseif (in_array($idtype_examen, [1, 3, 4])) {
    // AS, INST, SENS : 1h30
    $duree_min = 90;
}
// FORM (type 5) garde la valeur de la BDD

/* ── Session PHP définitive ───────────────────────────────── */
$_SESSION['idcandidat']     = $idcandidat;
$_SESSION['code_acces']     = $temp['code_acces'];
$_SESSION['idtype_examen']  = $idtype_examen;
$_SESSION['id_session']     = $id_session;
$_SESSION['nom_complet']    = $temp['nom_complet'];
$_SESSION['nom_session']    = $temp['nom_session'];
$_SESSION['type_session']   = $type_session;
$_SESSION['current_index']  = $current_index;
$_SESSION['questions']      = $questions;
$_SESSION['nb_questions']   = $nb_questions;
$_SESSION['a_deux_parties'] = $a_deux_parties;
$_SESSION['temps_debut']    = time();
$_SESSION['duree_minutes']  = $duree_min;

/* FORM (type 5) : stocker le module */
if ($idtype_examen == 5) {
    $sm = $conn->prepare(
        "SELECT se.idmodule, mf.nom_module_fr
         FROM session_examen se LEFT JOIN module_formation mf ON se.idmodule=mf.idmodule
         WHERE se.id_session=?"
    );
    if ($sm) {
        $sm->bind_param("i", $id_session); $sm->execute();
        $mr = $sm->get_result()->fetch_assoc(); $sm->close();
        $_SESSION['idmodule_en_cours']   = intval($mr['idmodule'] ?? 0);
        $_SESSION['nom_module_en_cours'] = $mr['nom_module_fr'] ?? '';
    }
}

unset($_SESSION['temp_auth']);
$conn->close();

/* ══════════════════════════════════════════════════════════
   DISPATCH FINAL
   ─────────────────────────────────────────────────────────
   examen.php     = IF pratique (images scanner)
   examen_qcm.php = TOUS les autres (AS, IF théorie, SENS, INST, FORM)
══════════════════════════════════════════════════════════ */
if ($idtype_examen == 2 && $type_session === 'pratique') {
    header("Location: examen.php");
} else {
    header("Location: examen_qcm.php");
}
exit();

/* ════════════════════════════════════════════════════════════
   PAGE D'ERREUR SWAL
════════════════════════════════════════════════════════════ */
function page_erreur(string $titre, string $message, string $retour, string $type = 'error'): void
{
    $t   = json_encode($titre,   JSON_UNESCAPED_UNICODE);
    $m   = json_encode($message, JSON_UNESCAPED_UNICODE);
    $r   = htmlspecialchars($retour, ENT_QUOTES, 'UTF-8');
    $ico = $type;
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EXASUR — Information</title>
    <link rel="icon" href="../assets/images/LOGOANAC.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
    body{
        font-family:'Candara','Calibri',sans-serif;
        background:linear-gradient(135deg,#03224c,#0a3a6b);
        min-height:100vh;display:flex;align-items:center;justify-content:center;
    }
    .sp{width:56px;height:56px;border:5px solid rgba(212,175,55,.25);
        border-top-color:#D4AF37;border-radius:50%;animation:sp 1s linear infinite;}
    @keyframes sp{to{transform:rotate(360deg);}}
    </style>
    </head>
    <body>
    <div class="sp"></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?= $ico ?>',
            title: <?= $t ?>,
            text: <?= $m ?>,
            confirmButtonColor: '#03224c',
            confirmButtonText: '<i class="fas fa-arrow-left" style="margin-right:6px;"></i>Retour',
            allowOutsideClick: false,
            showCloseButton: false
        }).then(function() {
            window.location.href = '<?= $r ?>';
        });
    });
    </script>
    </body>
    </html>
    <?php
}
?>