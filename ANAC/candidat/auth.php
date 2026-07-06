<?php
/**
 * auth.php - Page d'authentification candidat
 * ANAC GABON - EXASUR
 *
 * CORRECTIONS DG :
 *  1. Durées corrigées partout : AS/INST=2h, IF théorie=2h, IF pratique=1h30
 *  2. Liste sessions : affiche date_fin (et non date_debut deux fois)
 *  3. Nom session nettoyé : suppression des espaces/tirets superflus
 *  4. Modal règlement : durées et seuil corrects par type
 *  5. Seuil affiché : 70% pour AS/IF/INST/SENS (non la valeur brute BDD)
 */
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

$type_personnel = isset($_GET['type']) ? intval($_GET['type']) : 1;
$etape          = isset($_GET['etape']) ? trim($_GET['etape']) : 'theorie';

// ── Type d'examen ────────────────────────────────────────────────────────────
$stmt_type = $conn->prepare("SELECT * FROM type_examen WHERE idtype_examen = ? AND actif = 1");
$stmt_type->bind_param("i", $type_personnel);
$stmt_type->execute();
$type_info = $stmt_type->get_result()->fetch_assoc();
$stmt_type->close();

if (!$type_info) {
    header("Location: ../../index.php");
    exit();
}

$code_type      = $type_info['code'];
$nom_type       = $type_info['nom_fr'];
$a_deux_parties = intval($type_info['a_deux_parties']);

// Seuil affiché : toujours 70 % pour AS/IF/INST/SENS
$seuil_affiche = in_array($type_personnel, [1,2,3,4]) ? 70 : floatval($type_info['seuil_reussite']);

// ── Nettoyage du nom de session ──────────────────────────────────────────────
// Supprime les espaces/tirets multiples et les espacements irréguliers
// Exemple : "IF -    Certification AVSEC" → "IF - Certification AVSEC"
function cleanNomSession(string $nom): string {
    // Remplacer plusieurs espaces consécutifs par un seul
    $nom = preg_replace('/\s{2,}/', ' ', $nom);
    // Normaliser les tirets : espace-tiret(s)-espace → " - "
    $nom = preg_replace('/\s*-+\s*/', ' - ', $nom);
    $nom = preg_replace('/\s*-{2,}\s*/', ' - ', $nom);
    return trim($nom);
}

// ── Récupérer les sessions (avec date_fin) ───────────────────────────────────
function getSessions(mysqli $db, int $idtype, string $type_session): array {
    if ($idtype === 5) {
        $st = $db->prepare(
            "SELECT id_session, nom_session, date_debut, date_fin
             FROM session_examen
             WHERE idtype_examen = 5
               AND idmodule IS NOT NULL
               AND statut IN ('planifiee','en_cours')
             ORDER BY date_debut ASC"
        );
        $st->execute();
    } else {
        $st = $db->prepare(
            "SELECT id_session, nom_session, date_debut, date_fin
             FROM session_examen
             WHERE idtype_examen = ? AND type_session = ? AND statut IN ('planifiee','en_cours')
             ORDER BY date_debut ASC"
        );
        $st->bind_param("is", $idtype, $type_session);
        $st->execute();
    }
    $res  = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $res->free();
    $st->close();
    return $rows;
}

$sessions_theorie  = [];
$sessions_pratique = [];
$sessions_list     = [];
$titre_etape       = '';

if ($a_deux_parties && $type_personnel == 2) {
    $sessions_theorie  = getSessions($conn, 2, 'theorie');
    $sessions_pratique = getSessions($conn, 2, 'pratique');
    $sessions_list     = ($etape === 'pratique') ? $sessions_pratique : $sessions_theorie;
    $titre_etape       = ($etape === 'pratique') ? 'Pratique' : 'Théorie';
} else {
    $sessions_list = getSessions($conn, $type_personnel, 'normal');
}

// ── Libellé durée corrigé (Directive DG) ────────────────────────────────────
// AS=2h, INST=2h, IF théorie=2h, IF pratique=1h30, SENS=1h
if ($type_personnel == 2) {
    $duree_label = ($etape === 'pratique') ? '1h30 Pratique' : '2h Théorie';
} elseif (in_array($type_personnel, [1, 3])) {
    $duree_label = '2h'; // AS, INST
} elseif ($type_personnel == 4) {
    $duree_label = '1h'; // SENS
} else {
    $duree_label = formatDureeAuth(intval($type_info['duree_minutes'] ?? 90));
}

function formatDureeAuth($min) {
    if ($min >= 60) {
        $h = floor($min/60); $m = $min%60;
        return $h.'h'.($m>0 ? str_pad($m,2,'0',STR_PAD_LEFT) : '');
    }
    return $min.'min';
}

// ── Texte durée pour le modal règlement ─────────────────────────────────────
if ($type_personnel == 2) {
    $duree_modal = '2h Théorie + 1h30 Pratique (total 3h30)';
} elseif (in_array($type_personnel, [1, 3])) {
    $duree_modal = '2h (120 minutes)';
} elseif ($type_personnel == 4) {
    $duree_modal = '1h (60 minutes)';
} else {
    $duree_modal = formatDureeAuth(intval($type_info['duree_minutes'] ?? 90));
}

// ── Description de l'examen pour le modal ───────────────────────────────────
$desc_modal = '';
if ($type_personnel == 1) {
    $desc_modal = 'Théorie : 100 questions QCM - 2h - Seuil ≥ 70 %';
} elseif ($type_personnel == 2) {
    $desc_modal = 'Théorie : 100 questions QCM - 2h - Seuil ≥ 70 %<br>Pratique : questions images radiologiques - 1h30 - Seuil cumulé ≥ 70 %';
} elseif ($type_personnel == 3) {
    $desc_modal = 'Théorie : 100 questions QCM - 2h - Seuil ≥ 70 %';
} elseif ($type_personnel == 4) {
    $desc_modal = 'Sensibilisation : 20 questions QCM - 1h - Seuil ≥ 70 %';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANAC – Authentification <?php echo htmlspecialchars($code_type); ?></title>
    <link rel="icon" href="../assets/images/LOGOANAC.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --anac-blue:#03224c; --anac-gold:#D4AF37; }
        body {
            background:linear-gradient(135deg,#f8f9fa,#e9ecef);
            font-family:'Candara',sans-serif;
            min-height:100vh; display:flex; align-items:center; padding:20px;
        }
        .login-container { max-width:540px; margin:0 auto; width:100%; }
        .logo-container  { text-align:center; margin-bottom:25px; }
        .logo-container img {
            max-height:110px; background:#fff; padding:12px;
            border-radius:18px; box-shadow:0 10px 30px rgba(3,34,76,.2);
        }
        .card { border:none; border-radius:28px; box-shadow:0 20px 40px rgba(3,34,76,.25); overflow:hidden; }
        .card-header {
            background:linear-gradient(135deg,var(--anac-blue),#0a3a6b);
            color:#fff; text-align:center; padding:28px 30px 20px;
            border-bottom:4px solid var(--anac-gold);
        }
        .card-header h3 { margin:0; font-weight:700; font-size:1.8rem; }
        .card-body { padding:35px; background:#fff; }
        .form-label { font-weight:600; color:var(--anac-blue); }
        .form-control,.form-select {
            border:2px solid #e0e0e0; border-radius:10px; padding:11px 14px; transition:all .3s;
        }
        .form-control:focus,.form-select:focus {
            border-color:var(--anac-blue); box-shadow:0 0 0 .2rem rgba(3,34,76,.2);
        }
        .etape-selector { display:flex; gap:10px; margin-bottom:22px; }
        .etape-btn {
            flex:1; padding:12px; border-radius:50px; border:2px solid #dee2e6;
            background:#f8f9fa; color:#555; font-weight:600; font-size:.95rem;
            cursor:pointer; transition:all .3s; text-align:center;
        }
        .etape-btn.active {
            background:linear-gradient(135deg,var(--anac-blue),#0a3a6b);
            color:#fff; border-color:var(--anac-gold); box-shadow:0 4px 15px rgba(3,34,76,.3);
        }
        .etape-btn:hover:not(.active) { border-color:var(--anac-blue); color:var(--anac-blue); }
        #autoDetectBadge {
            display:none; background:#d4edda; color:#155724; border:1px solid #c3e6cb;
            border-radius:10px; padding:10px 14px; font-size:.9rem; margin-bottom:14px;
            animation:fadeIn .5s;
        }
        #autoDetectBadge.warning { background:#fff3cd; color:#856404; border-color:#ffeeba; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:none;} }
        .btn-submit {
            background:linear-gradient(135deg,var(--anac-blue),#0a3a6b);
            border:2px solid var(--anac-gold); border-radius:50px; padding:12px;
            font-weight:700; font-size:1.1rem; transition:all .3s; width:100%; color:#fff;
        }
        .btn-submit:hover:not(:disabled) { transform:translateY(-3px); box-shadow:0 10px 25px rgba(3,34,76,.4); color:#fff; }
        .btn-submit:disabled { opacity:.55; cursor:not-allowed; }
        .info-badge {
            background:var(--anac-gold); color:var(--anac-blue); padding:6px 14px;
            border-radius:50px; font-weight:600; display:inline-block; font-size:.85rem;
        }
        .alert-no-session {
            background:#fff3cd; border:1px solid #ffeeba; color:#856404;
            padding:18px; border-radius:12px; text-align:center;
        }
        .breadcrumb { background:transparent; padding:8px 0; }
        .breadcrumb a { color:var(--anac-blue); text-decoration:none; }
        .breadcrumb a:hover { color:var(--anac-gold); }
        .spinner-inline {
            display:none; width:18px; height:18px;
            border:3px solid rgba(3,34,76,.2); border-top-color:var(--anac-blue);
            border-radius:50%; animation:spin .7s linear infinite;
            vertical-align:middle; margin-left:8px;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<div class="container">
<div class="login-container">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Accueil</a></li>
            <li class="breadcrumb-item"><a href="instructions.php?type=<?php echo $type_personnel; ?>">Instructions</a></li>
            <li class="breadcrumb-item active">Authentification</li>
        </ol>
    </nav>

    <div class="logo-container">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON">
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🔐 ACCÈS EXAMEN</h3>
            <p class="mb-0"><?php echo htmlspecialchars($code_type . ' – ' . $nom_type); ?>
            <?php if ($titre_etape): ?>
                <span id="etapeHeaderLabel" style="background:rgba(255,255,255,.2);border-radius:20px;padding:3px 12px;font-size:.85rem;margin-left:8px;">
                    <?php echo htmlspecialchars($titre_etape); ?>
                </span>
            <?php endif; ?>
            </p>
        </div>

        <div class="card-body">
            <div class="text-center mb-4">
                <!-- Durée et seuil corrigés -->
                <span class="info-badge">
                    <i class="fas fa-clock me-1"></i>
                    <span id="dureeLbl"><?php echo htmlspecialchars($duree_label); ?></span>
                </span>
                <span class="info-badge ms-2">
                    <i class="fas fa-check-circle me-1"></i>Seuil : <strong><?php echo $seuil_affiche; ?>%</strong>
                </span>
            </div>

            <?php if ($a_deux_parties && $type_personnel == 2): ?>
            <!-- Sélecteur Théorie / Pratique IF - durées corrigées -->
            <div class="etape-selector" id="etapeSelector">
                <button type="button" class="etape-btn <?php echo $etape !== 'pratique' ? 'active' : ''; ?>"
                        id="btnTheorie" onclick="setEtape('theorie')">
                    <i class="fas fa-book me-2"></i>Théorie (2h)
                </button>
                <button type="button" class="etape-btn <?php echo $etape === 'pratique' ? 'active' : ''; ?>"
                        id="btnPratique" onclick="setEtape('pratique')">
                    <i class="fas fa-images me-2"></i>Pratique (1h30)
                </button>
            </div>
            <div id="autoDetectBadge">
                <i class="fas fa-magic me-2"></i><span id="autoDetectMsg"></span>
            </div>
            <?php endif; ?>

            <?php
            $has_sessions = count($sessions_list) > 0
                         || ($a_deux_parties && (count($sessions_theorie) > 0 || count($sessions_pratique) > 0));
            ?>

            <?php if (!$has_sessions && !$a_deux_parties): ?>
            <div class="alert-no-session">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <h5>Aucune session disponible</h5>
                <p>Il n'y a actuellement aucune session d'examen ouverte pour ce type.</p>
                <small>Contactez l'administration ANAC pour plus d'informations.</small>
            </div>
            <?php else: ?>

            <form action="auth_process.php" method="post" id="loginForm">
                <input type="hidden" name="idtype_examen" value="<?php echo $type_personnel; ?>">
                <?php if ($a_deux_parties && $type_personnel == 2): ?>
                    <input type="hidden" name="etape" id="etapeInput" value="<?php echo htmlspecialchars($etape); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="code_acces" class="form-label">
                        <i class="fas fa-id-card me-2"></i><?php echo __('code_acces'); ?>
                        <?php if ($a_deux_parties && $type_personnel == 2): ?>
                        <span class="spinner-inline" id="codeSpinner"></span>
                        <?php endif; ?>
                    </label>
                    <input type="text" class="form-control" id="code_acces" name="code_acces"
                           required pattern="\d{4}" maxlength="4"
                           placeholder="Ex : 0111"
                           oninput="this.value=this.value.replace(/[^0-9]/g,''); onCodeChange(this.value)"
                           autocomplete="off">
                    <small class="text-muted"><?php echo __('code_sur_convocation'); ?></small>
                </div>

                <div class="mb-3">
                    <label for="mot_de_passe" class="form-label">
                        <i class="fas fa-lock me-2"></i><?php echo __('mot_de_passe'); ?>
                    </label>
                    <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe"
                           required placeholder="Votre mot de passe">
                    <small class="text-muted"><?php echo __('mdp_fourni'); ?></small>
                </div>

                <div class="mb-4">
                    <label for="id_session" class="form-label">
                        <i class="fas fa-calendar-alt me-2"></i><?php echo __('session_examen'); ?>
                    </label>
                    <select class="form-select" id="id_session" name="id_session" required>
                        <option value=""><?php echo __('selectionnez_session'); ?></option>
                        <?php foreach ($sessions_list as $s):
                            // Nettoyer le nom + afficher date_fin (et non date_debut deux fois)
                            $nomClean  = cleanNomSession($s['nom_session']);
                            $dateFin   = date('d/m/Y', strtotime($s['date_fin']));
                        ?>
                            <option value="<?php echo $s['id_session']; ?>">
                                <?php echo htmlspecialchars($nomClean . ' (' . $dateFin . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="noSessionMsg" class="mt-2 text-warning small" style="display:none;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Aucune session disponible pour cette étape. Contactez l'administration.
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                    <label class="form-check-label" for="acceptTerms">
                        <?php echo __('accepte_conditions'); ?>
                        <a href="#" onclick="showTerms(); return false;">(voir le règlement)</a>
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    <i class="fas fa-arrow-right me-2"></i><?php echo __('acceder_examen'); ?>
                </button>
            </form>

            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="instructions.php?type=<?php echo $type_personnel; ?>" class="text-muted">
                    <i class="fas fa-arrow-left me-1"></i><?php echo __('retour_instructions'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// ══════════════════════════════════════════════════════════════════════════════
// Variables PHP → JS
// ══════════════════════════════════════════════════════════════════════════════
const IS_IF = <?php echo ($a_deux_parties && $type_personnel == 2) ? 'true' : 'false'; ?>;

// Sessions avec date_fin pour le rendu JS (IF théorie/pratique dynamique)
const sessionsTheorie  = <?php echo json_encode(array_map(function($s){
    return [
        'id_session'  => $s['id_session'],
        'nom_session' => cleanNomSession($s['nom_session']),
        'date_debut'  => $s['date_debut'],
        'date_fin'    => $s['date_fin'],
    ];
}, $sessions_theorie), JSON_UNESCAPED_UNICODE); ?>;

const sessionsPratique = <?php echo json_encode(array_map(function($s){
    return [
        'id_session'  => $s['id_session'],
        'nom_session' => cleanNomSession($s['nom_session']),
        'date_debut'  => $s['date_debut'],
        'date_fin'    => $s['date_fin'],
    ];
}, $sessions_pratique), JSON_UNESCAPED_UNICODE); ?>;

let currentEtape = '<?php echo htmlspecialchars($etape); ?>';
let detectTimer  = null;

// ── Bouton soumettre ──────────────────────────────────────────────────────────
document.getElementById('acceptTerms').addEventListener('change', function () {
    document.getElementById('submitBtn').disabled = !this.checked;
});

// ── Sélecteur Théorie / Pratique (IF) ────────────────────────────────────────
function setEtape(etape) {
    if (currentEtape === etape) return;
    currentEtape = etape;

    const inp = document.getElementById('etapeInput');
    if (inp) inp.value = etape;

    document.getElementById('btnTheorie') .classList.toggle('active', etape === 'theorie');
    document.getElementById('btnPratique').classList.toggle('active', etape === 'pratique');

    const lbl = document.getElementById('etapeHeaderLabel');
    if (lbl) lbl.textContent = etape === 'pratique' ? 'Pratique' : 'Théorie';

    // Durées corrigées DG
    document.getElementById('dureeLbl').textContent = etape === 'pratique' ? '1h30 Pratique' : '2h Théorie';

    populateSessions(etape);
}

/**
 * populateSessions - remplit le <select> sessions
 * CORRECTION : affiche date_fin (pas date_debut en double) + nom nettoyé
 */
function populateSessions(etape) {
    const sel     = document.getElementById('id_session');
    const list    = etape === 'pratique' ? sessionsPratique : sessionsTheorie;
    const msg     = document.getElementById('noSessionMsg');
    const prevVal = sel.value;

    sel.innerHTML = '<option value="">Sélectionnez une session...</option>';

    if (list.length === 0) {
        if (msg) msg.style.display = 'block';
        return;
    }
    if (msg) msg.style.display = 'none';

    list.forEach(s => {
        // Formater date_fin en dd/mm/yyyy
        const dateFin = new Date(s.date_fin);
        const fmt = dateFin.toLocaleDateString('fr-FR');

        const opt = document.createElement('option');
        opt.value       = s.id_session;
        opt.textContent = s.nom_session + ' (' + fmt + ')';
        if (String(s.id_session) === String(prevVal)) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ══════════════════════════════════════════════════════════════════════════════
// Détection automatique de l'étape candidat IF
// ══════════════════════════════════════════════════════════════════════════════
function onCodeChange(val) {
    if (!IS_IF) return;
    clearTimeout(detectTimer);
    hideAutoDetect();
    if (val.length < 5) return;
    detectTimer = setTimeout(() => detectEtapeCandidat(val), 600);
}

function detectEtapeCandidat(code) {
    if (!IS_IF) return;
    const spinner = document.getElementById('codeSpinner');
    if (spinner) spinner.style.display = 'inline-block';

    fetch('check_if_status.php?code=' + encodeURIComponent(code))
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            if (!data.found) { hideAutoDetect(); return; }

            const badge = document.getElementById('autoDetectBadge');
            const msgEl = document.getElementById('autoDetectMsg');

            if (data.theorie_faite && data.pratique_faite) {
                badge.className = 'warning';
                msgEl.textContent = '⚠️ ' + data.nom + ', vous avez déjà passé les deux épreuves IF.';
                badge.style.display = 'block';
            } else if (data.theorie_faite && !data.pratique_faite) {
                badge.className = '';
                msgEl.textContent = '✅ Bonjour ' + data.nom + ' ! Théorie validée (' + data.note_theorie + '%). Veuillez passer la PRATIQUE.';
                badge.style.display = 'block';
                if (currentEtape !== 'pratique') setEtape('pratique');
            } else {
                if (currentEtape !== 'theorie') {
                    badge.className = '';
                    msgEl.textContent = 'ℹ️ ' + data.nom + ', vous devez d\'abord passer la THÉORIE.';
                    badge.style.display = 'block';
                    setEtape('theorie');
                } else {
                    hideAutoDetect();
                }
            }
        })
        .catch(() => {
            if (spinner) spinner.style.display = 'none';
            hideAutoDetect();
        });
}

function hideAutoDetect() {
    const b = document.getElementById('autoDetectBadge');
    if (b) b.style.display = 'none';
}

// ── Modal règlement - durées et seuil corrects ────────────────────────────────
function showTerms() {
    const typeCode  = '<?php echo $code_type; ?>';
    const duree     = '<?php echo addslashes($duree_modal); ?>';
    const seuil     = '<?php echo $seuil_affiche; ?>';
    const descExtra = '<?php echo addslashes($desc_modal); ?>';

    Swal.fire({
        title: '📋 Règlement de l\'examen ' + typeCode,
        html: `<div style="text-align:left;max-height:380px;overflow-y:auto;font-family:Candara,sans-serif;">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <tr style="background:#f0f4ff;">
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;width:40%;">
                        <i class="fas fa-clock" style="color:#D4AF37;margin-right:6px;"></i>Durée
                    </td>
                    <td style="padding:9px 12px;font-weight:600;">${duree}</td>
                </tr>
                <tr>
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-trophy" style="color:#D4AF37;margin-right:6px;"></i>Seuil de réussite
                    </td>
                    <td style="padding:9px 12px;font-weight:700;color:#16a34a;font-size:1.1rem;">≥ ${seuil}%</td>
                </tr>
                ${descExtra ? `<tr style="background:#f0f4ff;">
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-list-check" style="color:#D4AF37;margin-right:6px;"></i>Épreuves
                    </td>
                    <td style="padding:9px 12px;font-size:.85rem;">${descExtra}</td>
                </tr>` : ''}
                <tr>
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-ban" style="color:#dc2626;margin-right:6px;"></i>Fraude
                    </td>
                    <td style="padding:9px 12px;color:#dc2626;font-weight:600;">Annulation immédiate de l'examen</td>
                </tr>
                <tr style="background:#f0f4ff;">
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-lock" style="color:#D4AF37;margin-right:6px;"></i>Infractions
                    </td>
                    <td style="padding:9px 12px;">5 infractions = <strong>verrouillage automatique</strong></td>
                </tr>
                <tr>
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-arrows-alt-h" style="color:#D4AF37;margin-right:6px;"></i>Navigation
                    </td>
                    <td style="padding:9px 12px;">Libre entre les questions</td>
                </tr>
                <tr style="background:#f0f4ff;">
                    <td style="padding:9px 12px;font-weight:700;color:#03224c;">
                        <i class="fas fa-eye-slash" style="color:#D4AF37;margin-right:6px;"></i>Résultat
                    </td>
                    <td style="padding:9px 12px;font-size:.85rem;">
                        ${typeCode === 'SENS' || typeCode === 'FORM'
                            ? 'Affiché à la fin de l\'examen'
                            : '<strong>Confidentiel</strong> - transmis à l\'administration ANAC uniquement'}
                    </td>
                </tr>
            </table>
            <div style="margin-top:12px;padding:10px 14px;background:#fff8e1;border-left:4px solid #D4AF37;border-radius:6px;font-size:.8rem;color:#78350f;">
                <i class="fas fa-info-circle me-1"></i>
                ANAC GABON - Programme National de Sûreté de l'Aviation Civile (PNSAC)
            </div>
        </div>`,
        icon: 'info',
        confirmButtonColor: '#03224c',
        confirmButtonText: '<i class="fas fa-check me-1"></i>OK, j\'ai compris',
        width: 520
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>