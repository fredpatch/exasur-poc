<?php
/**
 * attente.php - Page de pause inter-épreuves IF
 * EXASUR / ANAC / candidat / attente.php
 *
 * DIRECTIVE DG :
 *  - Aucun score, aucun pourcentage, aucune note n'est affiché au candidat.
 *  - Notification UNIQUEMENT :
 *      ✅ "Vous avez satisfait aux exigences" → accès à la pratique
 *      ❌ "Vous n'avez pas satisfait aux exigences" → AJOURNÉ
 *  - Tous les chiffres sont réservés à l'administration (admin).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

if (!isset($_SESSION['idcandidat'], $_SESSION['idtype_examen'])
    || intval($_SESSION['idtype_examen']) !== 2) {
    header("Location: ../../index.php"); exit();
}

$idcandidat  = intval($_SESSION['idcandidat']);
$nom_complet = htmlspecialchars($_SESSION['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8');
$code_acces  = htmlspecialchars($_SESSION['code_acces']  ?? '', ENT_QUOTES, 'UTF-8');

/* Récupérer uniquement la décision (pas les chiffres) */
$stmt = $conn->prepare("
    SELECT r.pourcentage, r.reussite_theo, r.reussite
    FROM resultats r
    JOIN session_examen se ON r.id_session = se.id_session
    WHERE r.idcandidat = ?
      AND se.idtype_examen = 2
      AND se.type_session  = 'theorie'
    ORDER BY r.date_fin DESC LIMIT 1
");
$stmt->bind_param("i", $idcandidat);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$res) { header("Location: ../../index.php"); exit(); }

/* Décision côté serveur - jamais envoyée dans le HTML */
$peut_pratique = (floatval($res['pourcentage']) >= 70);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EXASUR - Épreuve Théorique IF - Résultat</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
:root{--blue:#03224c;--blue2:#0a3a6b;--gold:#D4AF37;--green:#16a34a;--red:#dc2626;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Candara','Calibri',sans-serif;
    background:linear-gradient(135deg,var(--blue),var(--blue2));
    min-height:100vh;display:flex;flex-direction:column;
    align-items:center;justify-content:center;padding:20px;
}
.top-band{
    width:100%;max-width:620px;background:rgba(255,255,255,.08);
    border-radius:16px 16px 0 0;border-bottom:3px solid var(--gold);
    padding:14px 24px;display:flex;align-items:center;gap:14px;
}
.top-band img{height:44px;background:#fff;padding:4px 6px;border-radius:8px;}
.top-band .t{color:#fff;font-weight:800;font-size:.9rem;}
.top-band .s{color:rgba(255,255,255,.6);font-size:.75rem;}
.card{
    background:#fff;border-radius:0 0 24px 24px;
    max-width:620px;width:100%;
    box-shadow:0 24px 60px rgba(0,0,0,.4);overflow:hidden;
}
.cand-block{padding:28px 32px 22px;text-align:center;border-bottom:2px solid #f0f0f0;}
.cand-nom  {color:var(--blue);font-size:1.1rem;font-weight:800;margin-bottom:4px;}
.cand-code {color:#9ca3af;font-size:.82rem;margin-bottom:24px;}
/* ── Verdict ── */
.verdict-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;}
.verdict-icon{
    width:110px;height:110px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:3rem;box-shadow:0 8px 28px rgba(0,0,0,.2);
}
.verdict-icon.ok{background:linear-gradient(135deg,var(--green),#15803d);color:#fff;}
.verdict-icon.ko{background:linear-gradient(135deg,#374151,#1f2937);color:#fff;}
.verdict-titre{font-size:1.25rem;font-weight:900;letter-spacing:.4px;}
.verdict-titre.ok{color:var(--green);}
.verdict-titre.ko{color:#374151;}
.verdict-sous{
    font-size:.88rem;color:#4b5563;text-align:center;
    line-height:1.65;max-width:400px;
}
.badge-conf{
    display:inline-flex;align-items:center;gap:7px;
    background:#1e3a5f;color:rgba(255,255,255,.8);
    padding:7px 16px;border-radius:50px;font-size:.74rem;font-weight:600;
    border:1px solid rgba(212,175,55,.3);margin-top:4px;
}
.badge-conf i{color:var(--gold);}
/* ── Info blocs ── */
.info-block{padding:18px 28px;display:flex;flex-direction:column;gap:11px;}
.info-row{
    display:flex;align-items:flex-start;gap:14px;
    background:#f8f9fc;border-radius:12px;padding:13px 15px;
    border-left:4px solid var(--gold);
}
.info-ico{
    width:36px;height:36px;border-radius:50%;
    background:var(--blue);color:var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-size:.85rem;flex-shrink:0;
}
.info-title{font-weight:700;color:var(--blue);font-size:.87rem;margin-bottom:3px;}
.info-body{font-size:.81rem;color:#5a6380;line-height:1.55;}
/* ── Timer ── */
.timer-block{
    background:linear-gradient(135deg,#0f2949,#1a3f6f);
    padding:18px 32px;text-align:center;color:#fff;
}
.timer-label  {font-size:.78rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}
.timer-display{font-size:2.6rem;font-weight:900;font-family:monospace;color:var(--gold);letter-spacing:3px;}
.timer-note   {font-size:.75rem;color:rgba(255,255,255,.5);margin-top:4px;}
/* ── Boutons ── */
.btns-block{padding:18px 28px 26px;display:flex;gap:12px;flex-wrap:wrap;}
.btn-pratique{
    flex:1;background:linear-gradient(135deg,var(--green),#15803d);
    color:#fff;border:none;padding:14px 20px;border-radius:50px;
    font-weight:800;font-size:.95rem;cursor:pointer;font-family:inherit;
    transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-pratique:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(22,163,74,.4);color:#fff;}
.btn-home{
    flex:0 0 auto;background:#e8ecf5;color:var(--blue);
    border:2px solid #c8d0e0;padding:13px 17px;border-radius:50px;
    font-weight:700;font-size:.88rem;cursor:pointer;font-family:inherit;
    transition:all .3s;display:flex;align-items:center;justify-content:center;gap:7px;
    text-decoration:none;
}
.btn-home:hover{background:#dde3f0;color:var(--blue);}
.btn-accueil-full{
    flex:1;background:linear-gradient(135deg,#374151,#1f2937);
    color:#fff;border:none;padding:14px 20px;border-radius:50px;
    font-weight:800;font-size:.95rem;cursor:pointer;font-family:inherit;
    transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-accueil-full:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.3);color:#fff;}
@media(max-width:580px){
    .cand-block,.info-block,.timer-block,.btns-block{padding-left:16px;padding-right:16px;}
    .btns-block{flex-direction:column;}
    .verdict-icon{width:90px;height:90px;font-size:2.4rem;}
}
</style>
</head>
<body>

<!-- Bandeau -->
<div class="top-band">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"
         onerror="this.style.display='none'">
    <div>
        <div class="t">EXASUR - ANAC GABON</div>
        <div class="s">Certification IF - Résultat Épreuve Théorique</div>
    </div>
</div>

<div class="card">

    <!-- Identité + Verdict -->
    <div class="cand-block">
        <div class="cand-nom">
            <i class="fas fa-user" style="color:var(--gold);margin-right:8px;"></i>
            <?= $nom_complet ?>
        </div>
        <div class="cand-code">
            <i class="fas fa-key" style="margin-right:4px;"></i>
            Code : <?= $code_acces ?>
        </div>

        <div class="verdict-wrap">

            <?php if ($peut_pratique): ?>
            <!-- ✅ SATISFAIT -->
            <div class="verdict-icon ok">
                <i class="fas fa-check"></i>
            </div>
            <div class="verdict-titre ok">Exigences satisfaites</div>
            <div class="verdict-sous">
                Vous avez satisfait aux exigences de l'épreuve théorique IF.<br>
                Vous êtes autorisé(e) à passer l'épreuve <strong>Pratique</strong>.
            </div>

            <?php else: ?>
            <!-- ❌ NON SATISFAIT -->
            <div class="verdict-icon ko">
                <i class="fas fa-times"></i>
            </div>
            <div class="verdict-titre ko">Exigences non satisfaites</div>
            <div class="verdict-sous">
                Vous n'avez pas satisfait aux exigences de l'épreuve théorique IF.<br>
                L'accès à l'épreuve pratique n'est pas autorisé pour cette session.
            </div>
            <?php endif; ?>

            <div class="badge-conf">
                <i class="fas fa-lock"></i>
                Résultat confidentiel - transmis à l'administration ANAC GABON
            </div>

        </div>
    </div><!-- /cand-block -->

    <?php if ($peut_pratique): ?>
    <!-- ══════════ CAS ADMIS → pratique ══════════ -->
    <div class="info-block">
        <div class="info-row">
            <div class="info-ico"><i class="fas fa-arrow-right"></i></div>
            <div>
                <div class="info-title">Prochaine étape - Épreuve Pratique IF</div>
                <div class="info-body">
                    Une pause de <strong>15 minutes</strong> est en cours avant le démarrage
                    de l'épreuve pratique.<br>
                    Vous pouvez démarrer dès que vous vous sentez prêt(e).
                </div>
            </div>
        </div>
        <div class="info-row" style="border-left-color:#dc2626;">
            <div class="info-ico" style="background:#dc2626;"><i class="fas fa-clock"></i></div>
            <div>
                <div class="info-title" style="color:#dc2626;">Consignes - Épreuve Pratique (1h30)</div>
                <div class="info-body">
                    Durée totale : <strong>1h30 (90 minutes)</strong>.<br>
                    Analysez chaque image scanner et indiquez si le bagage est conforme ou suspect.<br>
                    <strong>5 infractions</strong> = examen verrouillé automatiquement.<br>
                    Ne changez pas d'onglet et ne quittez pas la fenêtre d'examen.
                </div>
            </div>
        </div>
    </div>

    <div class="timer-block">
        <div class="timer-label"><i class="fas fa-hourglass-half me-2"></i>Pause obligatoire</div>
        <div class="timer-display" id="timerDisplay">15:00</div>
        <div class="timer-note">Vous pouvez démarrer avant la fin de la pause</div>
    </div>

    <div class="btns-block">
        <a href="auth.php?type=2&etape=pratique" class="btn-pratique">
            <i class="fas fa-play-circle"></i>
            Démarrer l'épreuve Pratique (1h30)
        </a>
        <a href="../../index.php" class="btn-home" title="Retour à l'accueil">
            <i class="fas fa-home"></i>
        </a>
    </div>

    <?php else: ?>
    <!-- ══════════ CAS NON ADMIS → accueil ══════════ -->
    <div class="info-block">
        <div class="info-row" style="border-left-color:#dc2626;">
            <div class="info-ico" style="background:#dc2626;"><i class="fas fa-ban"></i></div>
            <div>
                <div class="info-title" style="color:#dc2626;">Accès à la pratique non autorisé</div>
                <div class="info-body">
                    Les exigences requises pour accéder à l'épreuve pratique IF
                    n'ont pas été atteintes lors de cette session théorique.<br><br>
                    Vous êtes <strong>AJOURNÉ(E)</strong> pour cette session.
                    Votre résultat a été transmis à l'administration ANAC GABON.
                </div>
            </div>
        </div>
        <div class="info-row">
            <div class="info-ico"><i class="fas fa-calendar-plus"></i></div>
            <div>
                <div class="info-title">Prochaines étapes</div>
                <div class="info-body">
                    Rapprochez-vous de l'administration ANAC GABON ou de votre
                    service RH pour connaître les modalités de réinscription
                    à une prochaine session de certification IF.
                </div>
            </div>
        </div>
    </div>
    <div class="btns-block">
        <a href="../../index.php" class="btn-accueil-full">
            <i class="fas fa-home me-2"></i>Retour à l'accueil
        </a>
    </div>
    <?php endif; ?>

</div><!-- /card -->

<script>
<?php if ($peut_pratique): ?>
let secondes = 15 * 60;
const el = document.getElementById('timerDisplay');
function pad(n){ return n.toString().padStart(2,'0'); }
const iv = setInterval(function(){
    if (secondes > 0) {
        secondes--;
        el.textContent = pad(Math.floor(secondes/60)) + ':' + pad(secondes%60);
        if (secondes <= 60) el.style.color = '#4ade80';
    } else {
        clearInterval(iv);
        el.textContent = '00:00';
        Swal.fire({
            icon             : 'success',
            title            : 'Pause terminée !',
            text             : 'Vous pouvez maintenant démarrer l\'épreuve pratique IF (1h30).',
            confirmButtonColor: '#16a34a',
            confirmButtonText: '<i class="fas fa-play-circle me-1"></i> Démarrer la pratique',
            allowOutsideClick: false
        }).then(function(){ window.location.href='auth.php?type=2&etape=pratique'; });
    }
}, 1000);
<?php endif; ?>
</script>
</body>
</html>