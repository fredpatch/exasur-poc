<?php
/**
 * instructions.php - Page d'instructions avant examen
 * ANAC GABON - EXASUR
 *
 * DIRECTIVE DG :
 *  - AS  (type 1) : 100 questions, 2h, seuil 70 %, score masqué candidat
 *  - IF  (type 2) : théorie 100 questions, 2h, seuil 70 %, score masqué
 *                   pratique variable (admin choisit N), 1h30, seuil cumulé 70 %
 *  - INST(type 3) : 100 questions, 2h, seuil 70 %, score masqué candidat
 *  - SENS(type 4) : INCHANGÉ - 20 questions, seuil 70 %
 *  - FORM(type 5) : INCHANGÉ
 *  - Pour IF : pratique accessible UNIQUEMENT si théorie ≥ 70 %
 *  - Score visible UNIQUEMENT chez l'administrateur pour AS, IF, INST
 */
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

$type_personnel = isset($_GET['type']) ? intval($_GET['type']) : 1;

/* ── Récupérer les infos du type d'examen ─────────────────────── */
$stmt_type = $conn->prepare("SELECT * FROM type_examen WHERE idtype_examen = ?");
$stmt_type->bind_param("i", $type_personnel);
$stmt_type->execute();
$type_info = $stmt_type->get_result()->fetch_assoc();
$stmt_type->close();

if (!$type_info) {
    header("Location: ../../index.php");
    exit();
}

$nom_type           = $type_info['nom_fr'];
$code_type          = $type_info['code'];
$nb_questions_theo  = intval($type_info['nb_questions_theorique'] ?? 0);
$nb_questions_pra   = intval($type_info['nb_questions_pratique']  ?? 0);
$seuil_bd           = floatval($type_info['seuil_reussite']);
$a_deux_parties     = $type_info['a_deux_parties'];
$duree_minutes      = intval($type_info['duree_minutes'] ?? 120);

/* ── Seuil affiché : toujours 70 % pour AS, IF, INST, SENS ── */
$seuil_affiche = (in_array($type_personnel, [1, 2, 3, 4])) ? 70 : $seuil_bd;

/* ── Score masqué pour le candidat : AS, IF, INST ────────────── */
$score_masque = in_array($type_personnel, [1, 2, 3]);

/* ── Formatage de la durée ────────────────────────────────────── */
function formatDuree($minutes) {
    if ($minutes >= 60) {
        $h   = floor($minutes / 60);
        $min = $minutes % 60;
        return $h . 'h' . ($min > 0 ? str_pad($min, 2, '0', STR_PAD_LEFT) : '');
    }
    return $minutes . ' minutes';
}

/* ── Texte "contient" selon type ─────────────────────────────── */
if ($a_deux_parties && $code_type === 'IF') {
    // IF : théorie 100q 2h + pratique variable 1h30
    $nb_theo_txt = $nb_questions_theo > 0 ? $nb_questions_theo : '100';
    $contenu_txt = "<strong>Théorie : {$nb_theo_txt} questions (2h, seuil ≥ 70 %)&nbsp;+&nbsp;Pratique : questions images radiologiques (1h30, seuil cumulé ≥ 70 %)</strong>";
    $duree_txt   = '<strong>3h30 (2h théorie + 1h30 pratique) avec une pause de 15 minutes</strong>';
} elseif ($a_deux_parties) {
    $nb_theo_txt = $nb_questions_theo > 0 ? $nb_questions_theo : 'variable';
    $nb_pra_txt  = $nb_questions_pra  > 0 ? $nb_questions_pra  : 'variable';
    $contenu_txt = "<strong>{$nb_theo_txt} questions théoriques + {$nb_pra_txt} questions pratiques</strong>";
    $duree_txt   = '<strong>' . formatDuree($duree_minutes) . '</strong>';
} elseif ($code_type === 'SENS') {
    // SENS : 20 questions (inchangé)
    $nb_txt = $nb_questions_theo > 0 ? $nb_questions_theo : 20;
    $contenu_txt = "<strong>{$nb_txt} " . __('questions_choix_unique') . "</strong>";
    $duree_txt   = '<strong>' . formatDuree($duree_minutes) . '</strong>';
} elseif ($code_type === 'FORM') {
    $contenu_txt = "<strong>" . __('questions_choix_unique') . " (par module)</strong>";
    $duree_txt   = '<strong>' . formatDuree($duree_minutes) . '</strong>';
} else {
    // AS, INST : 100 questions, 2h
    $nb_txt = $nb_questions_theo > 0 ? $nb_questions_theo : 100;
    $contenu_txt = "<strong>{$nb_txt} " . __('questions_choix_unique') . "</strong>";
    // Forcer durée 2h pour AS et INST
    $duree_txt = in_array($type_personnel, [1, 3])
        ? '<strong>2h (120 minutes)</strong>'
        : '<strong>' . formatDuree($duree_minutes) . '</strong>';
}

/* ── Vérifier s'il y a des sessions disponibles ──────────────── */
$stmt_check = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM session_examen
    WHERE idtype_examen = ?
      AND statut IN ('planifiee','en_cours')
");
$stmt_check->bind_param("i", $type_personnel);
$stmt_check->execute();
$row_check     = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();
$aucune_session = ($row_check['total'] == 0);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXASUR – <?php echo __('instructions_examen'); ?> <?php echo htmlspecialchars($code_type); ?></title>
    <link rel="icon" href="../assets/images/LOGOANAC.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        /* ── Variables ────────────────────────────────────────── */
        :root {
            --anac-blue:  #03224c;
            --blue-mid:   #0a3a6b;
            --anac-gold:  #D4AF37;
            --gold-light: #f0d060;
            --off-white:  #f4f7fc;
            --text-muted: #6b7a99;
            --radius:     14px;
            --shadow:     0 10px 40px rgba(3,34,76,0.15);
            --transition: 0.3s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: linear-gradient(135deg, var(--off-white) 0%, #e9ecef 100%);
            font-family: 'Candara', 'Calibri', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .btn-home {
            position: fixed; top: 20px; left: 20px;
            background: linear-gradient(135deg, var(--anac-blue) 0%, var(--blue-mid) 100%);
            color: white; border-radius: 50px; padding: 11px 22px;
            text-decoration: none; z-index: 1000;
            border: 1.5px solid var(--anac-gold);
            transition: all var(--transition);
            font-family: inherit; font-size: 0.88rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-home:hover { transform: translateY(-2px); color: var(--anac-gold); }

        .lang-switch {
            position: fixed; top: 20px; right: 20px; z-index: 1000;
            display: flex; gap: 6px;
        }
        .lang-btn {
            padding: 8px 18px; border: 2px solid var(--anac-gold);
            border-radius: 30px; color: white; text-decoration: none;
            background: var(--anac-blue); transition: all var(--transition);
            font-family: inherit; font-weight: 700; font-size: 0.82rem;
        }
        .lang-btn:hover, .lang-btn.active {
            background: var(--anac-gold); color: var(--anac-blue);
        }

        .main-card {
            max-width: 920px;
            margin: 80px auto 0;
            background: white;
            border-radius: var(--radius);
            border-top: 5px solid var(--anac-gold);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-head {
            background: linear-gradient(135deg, var(--anac-blue) 0%, var(--blue-mid) 100%);
            color: white; padding: 28px 32px;
            border-bottom: 3px solid var(--anac-gold);
            text-align: center;
        }
        .card-head h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; }
        .card-head p  { font-size: 0.88rem; opacity: 0.78; margin: 0; }

        .badge-cat {
            display: inline-block;
            background: linear-gradient(135deg, var(--anac-gold), var(--gold-light));
            color: var(--anac-blue); padding: 8px 24px;
            border-radius: 50px; font-size: 1.05rem;
            font-weight: 700; margin: 18px 0 4px;
            letter-spacing: 1px;
        }
        .badge-cat-name {
            display: block; color: rgba(255,255,255,0.85);
            font-size: 0.88rem; margin-top: 4px;
        }

        .card-body { padding: 32px; }

        .no-session-banner {
            background: linear-gradient(135deg, #fff3cd, #ffe08a);
            border: 2px solid #ffc107;
            border-radius: 12px; padding: 16px 22px;
            margin-bottom: 24px;
            display: flex; align-items: center; gap: 16px;
        }
        .no-session-banner i { font-size: 2rem; color: #856404; flex-shrink: 0; }
        .no-session-banner h5 { color: #856404; margin: 0 0 4px; font-weight: 700; font-size: 1rem; }
        .no-session-banner p  { color: #856404; margin: 0; font-size: 0.88rem; }

        .instructions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 32px;
        }
        @media(max-width:600px) { .instructions-grid { grid-template-columns: 1fr; } }

        .instructions-list { list-style: none; padding: 0; margin: 0; }
        .instructions-list li {
            margin-bottom: 10px; padding: 10px 14px;
            border-left: 3px solid transparent;
            background-color: #f8f9fa; border-radius: 8px;
            transition: all var(--transition); font-size: 0.9rem;
            color: #1a1f2e; display: flex; align-items: flex-start; gap: 10px;
        }
        .instructions-list li:hover {
            border-left-color: var(--anac-gold);
            background-color: #f0f3f9;
            padding-left: 18px;
        }
        .instructions-list li i { color: var(--anac-gold); flex-shrink: 0; margin-top: 2px; }

        .info-alert {
            background: rgba(212,175,55,0.1);
            border-left: 5px solid var(--anac-gold);
            padding: 16px 18px; border-radius: 8px; margin-top: 24px;
            display: flex; align-items: flex-start; gap: 14px;
        }
        .info-alert i { color: var(--anac-gold); font-size: 1.5rem; flex-shrink: 0; margin-top: 2px; }
        .info-alert p { margin: 0; font-size: 0.88rem; color: #4b5563; line-height: 1.6; }

        /* Alerte score masqué */
        .alert-masque {
            background: linear-gradient(135deg, #1e3a5f, #0f2040);
            border-left: 5px solid var(--anac-gold);
            padding: 14px 18px; border-radius: 8px; margin-top: 16px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .alert-masque i { color: var(--anac-gold); font-size: 1.2rem; flex-shrink: 0; margin-top: 2px; }
        .alert-masque p { margin: 0; font-size: 0.87rem; color: rgba(255,255,255,0.88); line-height: 1.55; }

        .start-wrap { text-align: center; margin-top: 36px; }
        .btn-start {
            background: linear-gradient(135deg, var(--anac-blue) 0%, var(--blue-mid) 100%);
            border: 2px solid var(--anac-gold); border-radius: 50px;
            font-size: 1.2rem; font-weight: 700; padding: 16px 44px;
            color: white; transition: all var(--transition);
            cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 12px;
            text-decoration: none;
        }
        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(212,175,55,0.45);
            color: white;
        }
        .btn-start:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .page-footer {
            text-align: center; margin: 30px auto 0;
            max-width: 920px; padding-bottom: 20px;
            color: var(--text-muted); font-size: 0.8rem;
        }
        .logo-wrap { text-align: center; margin: 60px auto 20px; }
        .logo-wrap img { max-height: 88px; }
        .logo-wrap h1 {
            color: var(--anac-blue); font-weight: 800;
            font-size: 1.6rem; margin-top: 10px;
        }
        .logo-wrap .sub { color: var(--text-muted); font-size: 0.88rem; margin-top: 4px; }
    </style>
</head>
<body>

    <a href="../../index.php" class="btn-home">
        <i class="fas fa-home"></i> <?php echo __('retour_accueil'); ?>
    </a>

    <div class="lang-switch">
        <a href="?lang=fr&type=<?php echo $type_personnel; ?>"
           class="lang-btn <?php echo $_SESSION['lang']=='fr' ? 'active' : ''; ?>">FR</a>
        <a href="?lang=en&type=<?php echo $type_personnel; ?>"
           class="lang-btn <?php echo $_SESSION['lang']=='en' ? 'active' : ''; ?>">EN</a>
    </div>

    <div class="logo-wrap">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON">
        <h1>EXASUR - <?php echo __('instructions_examen'); ?></h1>
        <div class="sub">ANAC GABON - Direction de la Sûreté &amp; Facilitation</div>
    </div>

    <div style="text-align:center;">
        <span class="badge-cat"><?php echo htmlspecialchars($code_type); ?></span>
        <div class="badge-cat-name"><?php echo htmlspecialchars($nom_type); ?></div>
    </div>

    <div class="main-card">
        <div class="card-head">
            <h2><i class="fas fa-info-circle" style="color:var(--anac-gold);margin-right:10px;"></i>
                <?php echo __('consignes_generales'); ?>
            </h2>
            <p><?php echo __('lisez_attentivement'); ?></p>
        </div>

        <div class="card-body">

            <?php if ($aucune_session): ?>
            <div class="no-session-banner">
                <i class="fas fa-calendar-times"></i>
                <div>
                    <h5><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>
                        <?php echo __('aucune_session_titre'); ?>
                    </h5>
                    <p><?php echo __('aucune_session_message'); ?> <?php echo __('contactez_admin_plus_tard'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="instructions-grid">
                <!-- Colonne 1 -->
                <ul class="instructions-list">
                    <li>
                        <i class="fas fa-list-check"></i>
                        <span>
                            <?php echo __('examen_contient'); ?>
                            <?php echo $contenu_txt; ?>.
                        </span>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span>
                            <?php echo __('disposez_de'); ?>
                            <?php echo $duree_txt; ?>
                            <?php echo __('pour_completer'); ?>.
                        </span>
                    </li>
                    <li>
                        <i class="fas fa-star"></i>
                        <span><?php echo __('bonne_reponse_vaut'); ?> <strong>1 point (barème calculé sur 100)</strong>.</span>
                    </li>
                    <li>
                        <i class="fas fa-play-circle"></i>
                        <span><?php echo __('une_fois_commence'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-book-open"></i>
                        <span><?php echo __('lisez_attentivement_question'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-arrow-left"></i>
                        <span><?php echo __('revenir_en_arriere'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-moon"></i>
                        <span><?php echo __('evitez_veille'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-phone-slash"></i>
                        <span><?php echo __('pas_appels'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-battery-full"></i>
                        <span><?php echo __('batterie_suffisante'); ?></span>
                    </li>
                </ul>

                <!-- Colonne 2 -->
                <ul class="instructions-list">
                    <li>
                        <i class="fas fa-wifi"></i>
                        <span><?php echo __('connexion_stable'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-window-restore"></i>
                        <span><?php echo __('pas_changer_onglet'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span><?php echo __('surveillance_active'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-ban"></i>
                        <span><?php echo __('triche_annulation'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-eye"></i>
                        <span><?php echo __('questions_traitees'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-redo"></i>
                        <span><?php echo __('tentatives_max'); ?> <strong>5</strong>.</span>
                    </li>
                    <li>
                        <i class="fas fa-lock"></i>
                        <span><?php echo __('verrouillage'); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>
                            <?php echo __('seuil_reussite'); ?> :
                            <strong style="color:#D4AF37;font-size:1.1rem;"><?php echo $seuil_affiche; ?>%</strong>
                            <?php if ($code_type === 'IF'): ?>
                            <span style="font-size:0.75rem;color:#666;">(théorie ≥ 70% pour accéder à la pratique ; moyenne finale théorie+pratique ≥ 70%)</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <i class="fas fa-file-pdf"></i>
                        <span>
                            <?php if ($score_masque): ?>
                                <?php echo ($_SESSION['lang']=='fr')
                                    ? 'À la fin de l\'examen, votre résultat sera transmis à votre administration. <strong>Le score n\'est pas affiché au candidat.</strong>'
                                    : 'At the end of the exam, your result will be sent to your administration. <strong>The score is not displayed to the candidate.</strong>'; ?>
                            <?php else: ?>
                                <?php echo __('fin_note_affichee'); ?>
                            <?php endif; ?>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Alerte score masqué (AS, IF, INST) -->
            <?php if ($score_masque): ?>
            <div class="alert-masque">
                <i class="fas fa-eye-slash"></i>
                <p>
                    <?php echo ($_SESSION['lang']=='fr')
                        ? '<strong>Résultat confidentiel :</strong> À la fin de cet examen, votre score ne vous sera PAS affiché. Il sera transmis directement à votre administration. Vous serez informé(e) du résultat (ADMIS / AJOURNÉ) par votre service RH ou votre hiérarchie.'
                        : '<strong>Confidential result:</strong> At the end of this exam, your score will NOT be displayed to you. It will be sent directly to the ANAC GABON administration. You will be informed of the result (PASS / FAIL) by your HR department or management.'; ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Alerte conditions -->
            <div class="info-alert">
                <i class="fas fa-info-circle"></i>
                <div>
                    <p><strong><?php echo __('accepte_conditions'); ?></strong></p>
                    <p style="margin-top:6px;">
                        <?php echo ($_SESSION['lang']=='fr')
                            ? 'En démarrant l\'examen, vous confirmez avoir lu et accepté toutes les consignes ci-dessus. Votre session sera enregistrée et tracée par le système EXASUR.'
                            : 'By starting the exam, you confirm that you have read and accepted all the instructions above. Your session will be recorded and tracked by the EXASUR system.'; ?>
                    </p>
                    <?php if ($a_deux_parties && $code_type === 'IF'): ?>
                    <p style="margin-top:8px;color:#856404;font-weight:600;">
                        <i class="fas fa-exclamation-triangle" style="color:#D4AF37;margin-right:6px;"></i>
                        <?php echo ($_SESSION['lang']=='fr')
                            ? 'Examen IF : vous commencerez par la théorie (2h, seuil 70 %). Si votre note théorique est <strong>≥ 70 %</strong>, vous pourrez passer la partie pratique (1h30) après une pause de 15 minutes. La note finale = moyenne théorie + pratique ≥ 70 % pour être ADMIS.'
                            : 'IF Exam: you will start with the theory part (2h, 70% threshold). If your theory score is <strong>≥ 70%</strong>, you can take the practical part (1h30) after a 15-minute break. Final score = average of theory + practical ≥ 70% to PASS.'; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bouton démarrer -->
            <div class="start-wrap">
                <?php if ($aucune_session): ?>
                    <button class="btn-start" disabled>
                        <i class="fas fa-calendar-times"></i>
                        <?php echo ($_SESSION['lang']=='fr') ? 'Aucune session disponible' : 'No session available'; ?>
                    </button>
                    <p style="margin-top:14px;color:var(--text-muted);font-size:0.88rem;">
                        <a href="../../index.php" style="color:var(--anac-blue);font-weight:600;text-decoration:none;">
                            <i class="fas fa-arrow-left" style="margin-right:4px;"></i>
                            <?php echo __('retour_accueil'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <a href="auth.php?type=<?php echo $type_personnel; ?>" class="btn-start">
                        <i class="fas fa-play-circle"></i>
                        <?php echo __('commencer_authentification'); ?>
                    </a>
                <?php endif; ?>
            </div>

        </div><!-- /card-body -->
    </div><!-- /main-card -->

    <footer class="page-footer">
        &copy; <?php echo date('Y'); ?> ANAC GABON - EXASUR · Direction de la Sûreté &amp; Facilitation · <?php echo __('droits_reserves'); ?>
    </footer>

    <?php if ($aucune_session): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeNom = <?php echo json_encode($code_type . ' - ' . $nom_type); ?>;
        const lang    = <?php echo json_encode($_SESSION['lang']); ?>;
        Swal.fire({
            icon: 'warning',
            title: '📅 ' + (lang==='fr' ? 'Aucune session disponible' : 'No session available'),
            html:
                '<div style="text-align:left;">' +
                    '<p style="font-size:1rem;"><?php echo __('aucune_session_message'); ?></p>' +
                    '<hr style="margin:12px 0;">' +
                    '<p style="color:#856404;">' +
                        '<i class="fas fa-tag" style="margin-right:6px;"></i>' +
                        '<strong>' + typeNom + '</strong>' +
                    '</p>' +
                    '<p style="margin-top:10px;font-size:.88rem;color:#666;">' +
                        '<i class="fas fa-info-circle" style="margin-right:6px;color:#D4AF37;"></i>' +
                        '<?php echo __('contactez_admin_plus_tard'); ?>' +
                    '</p>' +
                '</div>',
            confirmButtonText: '<i class="fas fa-home" style="margin-right:6px;"></i>' +
                (lang==='fr' ? 'Retour à l\'accueil' : 'Back to home'),
            confirmButtonColor: '#03224c',
            showCancelButton: true,
            cancelButtonText: lang==='fr' ? 'Voir les instructions quand même' : 'See instructions anyway',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
        }).then(function (result) {
            if (result.isConfirmed) window.location.href = '../../index.php';
        });
    });
    </script>
    <?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>