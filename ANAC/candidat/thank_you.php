<?php
/**
 * thank_you.php — Page de remerciement pour AS, IF Théorie, INST
 * Affiche : message de confirmation, résultat transmis à l'administration
 * + formulaire d'évaluation de l'expérience
 */
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

// Vérifier qu'on vient bien de soumettre un examen
if (!isset($_SESSION['exam_termine'])) {
    header("Location: ../../index.php");
    exit();
}

$exam = $_SESSION['exam_termine'];
$idcandidat = $_SESSION['idcandidat'] ?? 0;
$deja_evalue = false;

// Vérifier si le candidat a déjà évalué
if ($idcandidat) {
    $check = $conn->prepare("SELECT id FROM evaluations WHERE idcandidat = ?");
    $check->bind_param("i", $idcandidat);
    $check->execute();
    $deja_evalue = ($check->get_result()->num_rows > 0);
    $check->close();
}

// Récupérer le nom de la session
$nom_session = $_SESSION['nom_session'] ?? '';

// Nettoyer la session pour éviter les doublons
unset($_SESSION['exam_termine']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXASUR — Merci - <?php echo htmlspecialchars($exam['type_code']); ?></title>
    <link rel="icon" href="../assets/images/LOGOANAC.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --b: #03224c;
            --g: #D4AF37;
        }
        body {
            background: linear-gradient(135deg, #f0f4ff, #e8ecf5);
            font-family: 'Candara', 'Calibri', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .thank-container {
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
        }
        .logo-box {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-box img {
            max-height: 90px;
            background: #fff;
            padding: 10px 18px;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(3,34,76,.18);
        }
        .card-thank {
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 50px rgba(3,34,76,.22);
            overflow: hidden;
            animation: fadeInUp 0.6s ease both;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-header {
            background: linear-gradient(135deg, var(--b), #0a3a6b);
            color: white;
            text-align: center;
            padding: 28px 24px;
            border-bottom: 4px solid var(--g);
        }
        .card-header .icon {
            font-size: 4rem;
            color: var(--g);
            margin-bottom: 10px;
        }
        .card-header h2 {
            font-weight: 800;
            font-size: 1.6rem;
            margin-bottom: 6px;
        }
        .card-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        .card-body {
            padding: 32px 30px;
        }
        .info-candidat {
            background: #f4f7fc;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            border-left: 5px solid var(--g);
        }
        .info-candidat p {
            margin: 6px 0;
            font-size: 0.9rem;
            color: #2c3e50;
        }
        .info-candidat strong {
            color: var(--b);
        }
        .message-box {
            text-align: center;
            margin-bottom: 28px;
            padding: 20px;
            background: #e8f5e9;
            border-radius: 16px;
            border: 1px solid #a5d6a7;
        }
        .message-box i {
            font-size: 2.5rem;
            color: #2e7d32;
            margin-bottom: 12px;
            display: block;
        }
        .message-box h4 {
            color: #1b5e20;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .message-box p {
            color: #2e3b4e;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .eval-box {
            background: linear-gradient(135deg, #fff8e1, #ffefc0);
            border-radius: 18px;
            padding: 22px 20px;
            text-align: center;
            border: 2px solid var(--g);
            margin-bottom: 24px;
        }
        .eval-box h5 {
            font-weight: 800;
            color: var(--b);
            margin-bottom: 6px;
        }
        .eval-box p {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 16px;
        }
        .rating-btns {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .rating-btn {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-family: 'Candara', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .rating-btn.satisfait {
            background: #28a745;
            color: white;
            border: 2px solid #28a745;
        }
        .rating-btn.satisfait:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40,167,69,.4);
        }
        .rating-btn.moyen {
            background: #ffc107;
            color: #856404;
            border: 2px solid #ffc107;
        }
        .rating-btn.moyen:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255,193,7,.4);
        }
        .rating-btn.insatisfait {
            background: #dc3545;
            color: white;
            border: 2px solid #dc3545;
        }
        .rating-btn.insatisfait:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(220,53,69,.4);
        }
        .rating-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .eval-message {
            margin-top: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: none;
        }
        .btn-home {
            background: linear-gradient(135deg, var(--b), #0a3a6b);
            color: white;
            border: 2px solid var(--g);
            padding: 12px 32px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            transition: all 0.3s;
            font-family: 'Candara', sans-serif;
        }
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(3,34,76,.3);
            color: white;
        }
        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="thank-container">
    <div class="logo-box">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON">
    </div>

    <div class="card-thank">
        <div class="card-header">
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>MERCI</h2>
            <p><?php echo htmlspecialchars($exam['type_code'] . ' — ' . $exam['type_nom']); ?></p>
        </div>

        <div class="card-body">
            <div class="info-candidat">
                <p><i class="fas fa-user me-2" style="color:var(--g);"></i> <strong><?php echo htmlspecialchars($exam['nom']); ?></strong></p>
                <p><i class="fas fa-key me-2" style="color:var(--g);"></i> Code d'accès : <strong><?php echo htmlspecialchars($exam['code']); ?></strong></p>
                <?php if ($nom_session): ?>
                <p><i class="fas fa-calendar-alt me-2" style="color:var(--g);"></i> Session : <strong><?php echo htmlspecialchars($nom_session); ?></strong></p>
                <?php endif; ?>
            </div>

            <div class="message-box">
                <i class="fas fa-envelope-open-text"></i>
                <h4>Votre examen est terminé</h4>
                <p>
                    Merci d'avoir complété l'épreuve de Certification d'Agent de sûreté.<br><br>
                    <strong>Votre résultat a été enregistré et sera transmis à votre administration.</strong><br>
                
                </p>
            </div>

            <!-- Évaluation de l'expérience -->
            <div class="eval-box" id="evalBox">
                <h5><i class="fas fa-star me-1" style="color:var(--g);"></i> Évaluez votre expérience</h5>
                <p>Votre avis nous aide à améliorer la plateforme EXASUR</p>
                
                <div id="ratingButtons" class="rating-btns">
                    <button class="rating-btn satisfait" onclick="sendEvaluation('satisfait')">
                        <i class="fas fa-smile"></i> Satisfait
                    </button>
                    <button class="rating-btn moyen" onclick="sendEvaluation('moyen')">
                        <i class="fas fa-meh"></i> Moyen
                    </button>
                    <button class="rating-btn insatisfait" onclick="sendEvaluation('insatisfait')">
                        <i class="fas fa-frown"></i> Insatisfait
                    </button>
                </div>
                <div id="evalMessage" class="eval-message"></div>
            </div>

            <!-- Bouton retour accueil -->
            <div class="text-center mt-3">
                <a href="../../index.php" class="btn-home">
                    <i class="fas fa-home me-2"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <div class="footer-note">
        &copy; <?php echo date('Y'); ?> ANAC GABON — EXASUR · Direction de la Sûreté & Facilitation
    </div>
</div>

<script>
<?php if ($deja_evalue): ?>
// L'utilisateur a déjà évalué → masquer les boutons
document.getElementById('ratingButtons').style.display = 'none';
const msgDiv = document.getElementById('evalMessage');
msgDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Merci, vous avez déjà évalué votre expérience.';
msgDiv.style.display = 'block';
msgDiv.style.color = '#2e7d32';
<?php endif; ?>

function sendEvaluation(rating) {
    const btns = document.querySelectorAll('.rating-btn');
    btns.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
    });
    
    fetch('save_evaluation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rating: rating })
    })
    .then(response => response.json())
    .then(data => {
        const msgDiv = document.getElementById('evalMessage');
        if (data.success) {
            msgDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Merci pour votre évaluation !';
            msgDiv.style.color = '#2e7d32';
            msgDiv.style.display = 'block';
            document.getElementById('ratingButtons').style.display = 'none';
        } else {
            msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Une erreur est survenue. Veuillez réessayer.';
            msgDiv.style.color = '#dc3545';
            msgDiv.style.display = 'block';
            btns.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        }
    })
    .catch(error => {
        const msgDiv = document.getElementById('evalMessage');
        msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Erreur de connexion. Veuillez réessayer.';
        msgDiv.style.color = '#dc3545';
        msgDiv.style.display = 'block';
        btns.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        });
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>