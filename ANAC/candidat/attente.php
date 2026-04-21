<?php
/**
 * attente.php — Page de pause inter-épreuves IF
 * EXASUR/ANAC/candidat/attente.php
 *
 * CORRECTION BUG :
 *   L'ancienne vérification bloquait l'accès car elle testait
 *   type_session === 'pratique' alors qu'on arrive depuis la théorie
 *   (type_session = 'theorie').
 *   Fix : on vérifie juste que idtype_examen = 2 (IF)
 *   + on lit le résultat théorie depuis la BDD.
 *
 *   Si score >= 70% : affiche pause + bouton démarrer pratique
 *   Si score < 70%  : affiche résultat ajourné + bouton retour
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../php/db_connection.php';
include '../lang/lang_loader.php';

/* ── Sécurité : seul IF est concerné ─────────────────────── */
if (!isset($_SESSION['idcandidat'], $_SESSION['idtype_examen'])
    || intval($_SESSION['idtype_examen']) !== 2) {
    header("Location: ../../index.php"); exit();
}

$idcandidat  = intval($_SESSION['idcandidat']);
$nom_complet = htmlspecialchars($_SESSION['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8');
$code_acces  = htmlspecialchars($_SESSION['code_acces']  ?? '', ENT_QUOTES, 'UTF-8');
$id_session  = intval($_SESSION['id_session'] ?? 0);

/* ── Récupérer le dernier résultat théorie IF ─────────────── */
$stmt = $conn->prepare("
    SELECT r.note_finale, r.note_sur, r.pourcentage, r.reussite_theo, r.reussite, r.date_fin
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    WHERE r.idcandidat = ?
      AND se.idtype_examen = 2
      AND se.type_session = 'theorie'
      AND r.note_finale > 0
    ORDER BY r.date_fin DESC LIMIT 1
");
$stmt->bind_param("i", $idcandidat);
$stmt->execute();
$res_theo = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$res_theo) {
    /* Pas de résultat théorie → retour accueil */
    header("Location: ../../index.php"); exit();
}

$pct_theo     = round(floatval($res_theo['pourcentage']), 1);
$note_fin     = number_format(floatval($res_theo['note_finale']), 1);
$note_sur     = number_format(floatval($res_theo['note_sur']), 1);
$reussite_the = intval($res_theo['reussite_theo'] ?? $res_theo['reussite']);
$prenom       = explode(' ', trim($nom_complet))[0] ?: $nom_complet;
$peut_pratique = ($pct_theo >= 70);

/* Mention selon le score */
if ($reussite_the && $pct_theo >= 80) {
    $mention    = 'VALIDÉ — Excellent (≥80%)';
    $col        = '#16a34a';
    $bg         = '#dcfce7';
    $ico        = 'fa-trophy';
} elseif ($pct_theo >= 70) {
    $mention    = 'Théorie OK — Pratique autorisée (≥70%)';
    $col        = '#d97706';
    $bg         = '#fef3c7';
    $ico        = 'fa-check-circle';
} else {
    $mention    = 'Théorie insuffisante — Score < 70%';
    $col        = '#dc2626';
    $bg         = '#fee2e2';
    $ico        = 'fa-times-circle';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>EXASUR — Résultat Théorie IF</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
:root{--blue:#03224c;--blue-mid:#0a3a6b;--gold:#D4AF37;--green:#16a34a;--red:#dc2626;--bg:#f0f4fa;}
*{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Candara','Calibri',sans-serif;
    background:linear-gradient(135deg,var(--blue),var(--blue-mid));
    min-height:100vh;display:flex;flex-direction:column;align-items:center;
    justify-content:center;padding:20px;
}

/* Header bande */
.top-band{
    width:100%;max-width:680px;
    background:rgba(255,255,255,.08);
    border-radius:16px 16px 0 0;
    border-bottom:3px solid var(--gold);
    padding:14px 24px;
    display:flex;align-items:center;gap:14px;
    margin-bottom:0;
}
.top-band img{height:44px;background:#fff;padding:4px 6px;border-radius:8px;}
.top-band .t{color:#fff;font-weight:800;font-size:.9rem;}
.top-band .s{color:rgba(255,255,255,.6);font-size:.75rem;}

/* Carte principale */
.card{
    background:#fff;border-radius:0 0 24px 24px;
    max-width:680px;width:100%;
    box-shadow:0 24px 60px rgba(0,0,0,.4);
    overflow:hidden;
}

/* Bloc score */
.score-block{
    padding:32px 36px 24px;
    text-align:center;
    border-bottom:2px solid #f0f0f0;
}
.score-nom{color:var(--blue);font-size:1.1rem;font-weight:800;margin-bottom:4px;}
.score-code{color:#9ca3af;font-size:.82rem;margin-bottom:20px;}
.circle-score{
    width:130px;height:130px;border-radius:50%;
    display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
    color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.25);margin-bottom:16px;
    font-family:'Candara','Calibri',sans-serif;
}
.circle-pct{font-size:2.2rem;font-weight:900;line-height:1;}
.circle-lbl{font-size:.75rem;opacity:.85;margin-top:2px;}
.circle-note{font-size:.9rem;font-weight:700;margin-top:3px;}
.mention-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 20px;border-radius:50px;font-weight:800;font-size:.88rem;
    border:2px solid currentColor;
}
.bar-wrap{background:#e8ecf5;border-radius:50px;height:10px;margin:14px auto;max-width:300px;overflow:hidden;}
.bar-fill{height:100%;border-radius:50px;transition:width 1.2s ease;}

/* Bloc info */
.info-block{padding:20px 36px;display:flex;flex-direction:column;gap:12px;}
.info-row{
    display:flex;align-items:flex-start;gap:14px;
    background:#f8f9fc;border-radius:12px;padding:14px 16px;
    border-left:4px solid var(--gold);
}
.info-ico{
    width:36px;height:36px;border-radius:50%;
    background:var(--blue);color:var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-size:.85rem;flex-shrink:0;
}
.info-title{font-weight:700;color:var(--blue);font-size:.88rem;margin-bottom:2px;}
.info-body{font-size:.82rem;color:#5a6380;line-height:1.5;}

/* Minuteur */
.timer-block{
    background:linear-gradient(135deg,#0f2949,#1a3f6f);
    padding:18px 36px;text-align:center;color:#fff;
}
.timer-label{font-size:.78rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}
.timer-display{font-size:2.6rem;font-weight:900;font-family:monospace;color:var(--gold);letter-spacing:3px;}
.timer-note{font-size:.75rem;color:rgba(255,255,255,.5);margin-top:4px;}

/* Boutons */
.btns-block{padding:20px 36px 28px;display:flex;gap:12px;flex-wrap:wrap;}
.btn-pratique{
    flex:1;background:linear-gradient(135deg,var(--green),#15803d);
    color:#fff;border:none;padding:14px 20px;border-radius:50px;
    font-weight:800;font-size:.95rem;cursor:pointer;font-family:inherit;
    transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-pratique:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(22,163,74,.4);color:#fff;}
.btn-pratique:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.btn-accueil{
    flex:0 0 auto;background:#e8ecf5;color:var(--blue);border:2px solid #c8d0e0;
    padding:14px 20px;border-radius:50px;font-weight:700;font-size:.88rem;
    cursor:pointer;font-family:inherit;transition:all .3s;
    display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-accueil:hover{background:#dde3f0;color:var(--blue);}
.btn-ajourne{
    flex:1;background:linear-gradient(135deg,var(--red),#b91c1c);
    color:#fff;border:none;padding:14px 20px;border-radius:50px;
    font-weight:800;font-size:.95rem;cursor:pointer;font-family:inherit;
    transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-ajourne:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(220,38,38,.4);color:#fff;}

@media(max-width:580px){
    .score-block,.info-block,.timer-block,.btns-block{padding-left:20px;padding-right:20px;}
    .btns-block{flex-direction:column;}
}
</style>
</head>
<body>

<!-- Bande header -->
<div class="top-band">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"
         onerror="this.style.display='none'">
    <div>
        <div class="t">EXASUR — ANAC GABON</div>
        <div class="s">Certification IF — Résultat Épreuve Théorique</div>
    </div>
</div>

<div class="card">

    <!-- Score -->
    <div class="score-block">
        <div class="score-nom"><i class="fas fa-user me-2" style="color:var(--gold);"></i><?= $nom_complet ?></div>
        <div class="score-code"><i class="fas fa-key me-1"></i> Code : <?= $code_acces ?></div>

        <div class="circle-score" style="background:linear-gradient(135deg,<?= $peut_pratique ? '#16a34a,#15803d' : '#dc2626,#b91c1c' ?>);">
            <span class="circle-pct"><?= $pct_theo ?>%</span>
            <span class="circle-lbl">Score théorie</span>
            <span class="circle-note"><?= $note_fin ?> / <?= $note_sur ?></span>
        </div>

        <div class="bar-wrap">
            <div class="bar-fill" id="barFill"
                 style="width:0%;background:<?= $peut_pratique ? 'linear-gradient(90deg,var(--green),#22c55e)' : 'linear-gradient(90deg,var(--red),#f87171)' ?>;"></div>
        </div>

        <div class="mention-badge" style="color:<?= $col ?>;background:<?= $bg ?>;">
            <i class="fas <?= $ico ?>"></i>
            <?= htmlspecialchars($mention) ?>
        </div>
    </div>

    <?php if ($peut_pratique): ?>
    <!-- === CAS : SCORE >= 70% → peut faire la pratique === -->

    <div class="info-block">
        <div class="info-row">
            <div class="info-ico"><i class="fas fa-info-circle"></i></div>
            <div>
                <div class="info-title">Vous pouvez passer l'épreuve Pratique IF</div>
                <div class="info-body">
                    Votre score théorique de <strong><?= $pct_theo ?>%</strong> vous permet d'accéder
                    à l'épreuve pratique (images scanner radiologique).
                    Un minuteur de pause de <strong>15 minutes</strong> est en cours.
                    Vous pouvez démarrer dès que vous êtes prêt(e).
                </div>
            </div>
        </div>
        <div class="info-row" style="border-left-color:#dc2626;">
            <div class="info-ico" style="background:#dc2626;"><i class="fas fa-eye"></i></div>
            <div>
                <div class="info-title" style="color:#dc2626;">Rappel — Épreuve Pratique</div>
                <div class="info-body">
                    Vous aurez <strong>45 secondes par image</strong> pour analyser le scanner
                    et indiquer si le bagage est clair ou suspect (avec catégorie de menace).
                    <strong>5 infractions</strong> = examen verrouillé.
                </div>
            </div>
        </div>
    </div>

    <!-- Minuteur 15 min -->
    <div class="timer-block">
        <div class="timer-label"><i class="fas fa-hourglass-half me-2"></i>Pause obligatoire</div>
        <div class="timer-display" id="timerDisplay">15:00</div>
        <div class="timer-note">Vous pouvez démarrer avant la fin de la pause</div>
    </div>

    <div class="btns-block">
        <a href="auth.php?type=2&etape=pratique" class="btn-pratique" id="btnPratique">
            <i class="fas fa-play-circle"></i>
            Démarrer l'épreuve Pratique
        </a>
        <a href="../../index.php" class="btn-accueil">
            <i class="fas fa-home"></i>
        </a>
    </div>

    <?php else: ?>
    <!-- === CAS : SCORE < 70% → ajourné === -->

    <div class="info-block">
        <div class="info-row" style="border-left-color:#dc2626;">
            <div class="info-ico" style="background:#dc2626;"><i class="fas fa-times-circle"></i></div>
            <div>
                <div class="info-title" style="color:#dc2626;">Accès à la pratique refusé</div>
                <div class="info-body">
                    Votre score de <strong><?= $pct_theo ?>%</strong> est inférieur au seuil requis
                    de <strong>70%</strong> pour accéder à l'épreuve pratique IF.
                    <br><br>
                    Vous êtes <strong>ajourné(e)</strong> à cette session.
                    Vous pourrez vous réinscrire à une prochaine session IF auprès de
                    l'administration ANAC GABON.
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-ico"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <div class="info-title">Prochaines étapes</div>
                <div class="info-body">
                    Contactez l'administration ANAC pour vous inscrire à une prochaine
                    session de certification IF. Révisez les modules théoriques avant
                    de vous représenter.
                </div>
            </div>
        </div>
    </div>

    <div class="btns-block">
        <a href="../../index.php" class="btn-ajourne">
            <i class="fas fa-home me-2"></i>
            Retour à l'accueil
        </a>
    </div>

    <?php endif; ?>

</div><!-- /card -->

<script>
/* ── Anime la barre de score ──────────────────────────────── */
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('barFill').style.width = '<?= min($pct_theo, 100) ?>%';
    }, 300);
});

<?php if ($peut_pratique): ?>
/* ── Minuteur 15 min ──────────────────────────────────────── */
let secondes = 15 * 60;
const elTimer = document.getElementById('timerDisplay');

function majTimer() {
    const m = Math.floor(secondes / 60);
    const s = secondes % 60;
    elTimer.textContent = pad(m) + ':' + pad(s);
    if (secondes <= 60) {
        elTimer.style.color = '#16a34a';
    }
}

function pad(n) { return n.toString().padStart(2, '0'); }

const iv = setInterval(function() {
    if (secondes > 0) {
        secondes--;
        majTimer();
    } else {
        clearInterval(iv);
        elTimer.textContent = '00:00';
        /* Notification quand la pause est terminée */
        Swal.fire({
            icon: 'success',
            title: '✅ Pause terminée !',
            text: 'Vous pouvez maintenant démarrer l\'épreuve pratique IF.',
            confirmButtonColor: '#16a34a',
            confirmButtonText: '<i class="fas fa-play-circle me-1"></i> Démarrer la pratique',
            allowOutsideClick: false
        }).then(function() {
            window.location.href = 'auth.php?type=2&etape=pratique';
        });
    }
}, 1000);
<?php endif; ?>
</script>

</body>
</html>