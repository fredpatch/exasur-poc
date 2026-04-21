<?php
session_start();
include 'lang/lang_loader.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur - ANAC</title>
    <link rel="icon" href="assets/images/Logo-ANAC-CERTIFICATION.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --anac-blue: #03224c; --anac-gold: #D4AF37; }
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Candara', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-message {
            background: white;
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(3,34,76,0.3);
            border-top: 5px solid #dc3545;
        }
        .btn-home {
            background: linear-gradient(135deg, var(--anac-blue) 0%, #0a3a6b 100%);
            color: white;
            border: 2px solid var(--anac-gold);
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(3,34,76,0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-message">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="mb-4" style="color: var(--anac-blue);">Oups ! Une erreur est survenue</h2>
            <p class="lead"><?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Erreur inconnue.'; ?></p>
            <a href="../../index.php" class="btn-home"><i class="fas fa-home me-2"></i>Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>