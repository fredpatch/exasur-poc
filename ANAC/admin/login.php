<?php
/**
 * login.php — Connexion administrateur AIR SECURE ANAC GABON
 * Couleurs officielles ANAC : Bleu #03224c / Or #D4AF37
 * Design moderne avec lien accueil + toggle mot de passe
 */
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php"); exit();
}
include '../php/db_connection.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code_acces'] ?? '');
    $pwd  = $_POST['password'] ?? '';
    if ($code && $pwd) {
        $s = $conn->prepare("SELECT * FROM administrateurs WHERE code_acces=? AND actif=1");
        $s->bind_param("s", $code); $s->execute();
        $admin = $s->get_result()->fetch_assoc(); $s->close();
        if ($admin && password_verify($pwd, $admin['mot_de_passe'])) {
            $_SESSION['admin_id']   = $admin['idadmin'];
            $_SESSION['admin_nom']  = $admin['prenom'].' '.$admin['nom'];
            $_SESSION['admin_role'] = $admin['role'];
            $conn->close();
            header("Location: dashboard.php"); exit();
        }
        $error = "Code d'accès ou mot de passe incorrect.";
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration EXASUR — ANAC GABON</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   VARIABLES ANAC OFFICIELLES
═══════════════════════════════════════════════════ */
:root {
    --anac-blue:      #03224c;
    --anac-blue-mid:  #0a3a6b;
    --anac-blue-lite: #e8eef7;
    --anac-gold:      #D4AF37;
    --anac-gold-dark: #b8963a;
    --anac-gold-lite: #fef9e7;
}

/* ═══════════════════════════════════════════════════
   RESET / BASE
═══════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Outfit', 'Candara', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
    background: var(--anac-blue);
    display: flex;
    flex-direction: column;
}

/* ═══════════════════════════════════════════════════
   FOND ANIMÉ (BLEU ANAC UNIQUEMENT — zéro orange)
═══════════════════════════════════════════════════ */
.bg-scene {
    position: fixed; inset: 0; z-index: 0;
    background: linear-gradient(160deg, #03224c 0%, #061d40 45%, #0a2b58 100%);
    overflow: hidden;
}

/* Grille de lignes légères */
.bg-scene::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(212,175,55,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(212,175,55,.06) 1px, transparent 1px);
    background-size: 56px 56px;
}

/* Halo or bas gauche */
.bg-scene::after {
    content: '';
    position: absolute;
    width: 800px; height: 800px;
    bottom: -300px; left: -200px;
    background: radial-gradient(circle, rgba(212,175,55,.07) 0%, transparent 65%);
    border-radius: 50%;
}

/* Halo bleu clair haut droit */
.halo-top {
    position: absolute;
    width: 700px; height: 700px;
    top: -250px; right: -200px;
    background: radial-gradient(circle, rgba(10,58,107,.7) 0%, transparent 65%);
    border-radius: 50%;
}

/* Avion décoratif grand fond */
.plane-deco {
    position: absolute;
    bottom: 8%; right: 4%;
    font-size: 220px;
    opacity: .025;
    color: var(--anac-gold);
    transform: rotate(-20deg);
    pointer-events: none;
    user-select: none;
    line-height: 1;
}

/* Petites particules */
.particle {
    position: absolute;
    width: 3px; height: 3px;
    border-radius: 50%;
    background: var(--anac-gold);
    opacity: 0;
    animation: floatUp linear infinite;
}
@keyframes floatUp {
    0%   { opacity: 0; transform: translateY(0) scale(1); }
    20%  { opacity: .5; }
    80%  { opacity: .3; }
    100% { opacity: 0; transform: translateY(-80px) scale(.4); }
}

/* ═══════════════════════════════════════════════════
   BARRE NAVIGATION HAUT
═══════════════════════════════════════════════════ */
.top-nav {
    position: relative; z-index: 100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 32px;
    background: rgba(3, 34, 76, .85);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(212,175,55,.2);
}
.nav-brand {
    display: flex; align-items: center; gap: 12px;
    text-decoration: none;
}
.nav-logo {
    height: 40px;
    background: white;
    border-radius: 8px;
    padding: 4px 8px;
    object-fit: contain;
}
.nav-brand-text .line1 {
    display: block;
    color: white; font-weight: 800; font-size: .95rem; line-height: 1.1;
}
.nav-brand-text .line2 {
    display: block;
    color: var(--anac-gold); font-size: .68rem; font-weight: 500;
    text-transform: uppercase; letter-spacing: 1.5px;
}
.nav-home-link {
    display: flex; align-items: center; gap: 7px;
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: .82rem; font-weight: 500;
    padding: 7px 16px;
    border: 1px solid rgba(212,175,55,.3);
    border-radius: 25px;
    transition: all .25s;
}
.nav-home-link i { color: var(--anac-gold); font-size: .8rem; }
.nav-home-link:hover {
    background: rgba(212,175,55,.12);
    color: white;
    border-color: var(--anac-gold);
}

/* ═══════════════════════════════════════════════════
   ZONE CENTRALE
═══════════════════════════════════════════════════ */
.login-main {
    position: relative; z-index: 10;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

/* ═══════════════════════════════════════════════════
   CARD DE CONNEXION
═══════════════════════════════════════════════════ */
.login-card {
    width: 100%;
    max-width: 460px;
    background: rgba(255,255,255,.97);
    border-radius: 20px;
    overflow: hidden;
    box-shadow:
        0 40px 80px rgba(0,0,0,.45),
        0 0 0 1px rgba(212,175,55,.25);
    animation: cardIn .6s cubic-bezier(.22,.68,0,1.2) both;
}
@keyframes cardIn {
    from { opacity: 0; transform: translateY(32px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Header card ─────────────────────────────────── */
.card-head {
    background: linear-gradient(150deg, var(--anac-blue) 0%, var(--anac-blue-mid) 100%);
    padding: 32px 36px 28px;
    text-align: center;
    border-bottom: 4px solid var(--anac-gold);
    position: relative;
    overflow: hidden;
}
/* Motif décoratif dans le header */
.card-head::before {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    border: 30px solid rgba(212,175,55,.08);
    border-radius: 50%;
    top: -80px; right: -60px;
}
.card-head::after {
    content: '';
    position: absolute;
    width: 120px; height: 120px;
    border: 20px solid rgba(212,175,55,.06);
    border-radius: 50%;
    bottom: -50px; left: -30px;
}
.head-logo-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 14px;
    padding: 10px 18px;
    margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
    position: relative; z-index: 1;
}
.head-logo { height: 52px; object-fit: contain; }
.card-head h1 {
    color: white; font-size: 1.3rem; font-weight: 800;
    margin-bottom: 4px; letter-spacing: .5px;
    position: relative; z-index: 1;
}
.card-head .head-sub {
    color: var(--anac-gold); font-size: .78rem; font-weight: 500;
    letter-spacing: 1px; text-transform: uppercase;
    position: relative; z-index: 1;
}

/* Badges types d'examen */
.exam-badges {
    display: flex; justify-content: center; gap: 6px;
    margin-top: 14px; flex-wrap: wrap;
    position: relative; z-index: 1;
}
.exam-badge {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(212,175,55,.3);
    color: rgba(255,255,255,.85);
    padding: 3px 10px; border-radius: 20px;
    font-size: .68rem; font-weight: 600;
    letter-spacing: .3px;
}
.exam-badge.gold { background: rgba(212,175,55,.2); border-color: var(--anac-gold); color: var(--anac-gold); }

/* ── Body card ───────────────────────────────────── */
.card-body { padding: 32px 36px 28px; }

/* Alerte erreur */
.alert-err {
    background: #fff1f2;
    border-left: 4px solid #dc2626;
    color: #991b1b;
    padding: 11px 14px;
    border-radius: 0 10px 10px 0;
    font-size: .85rem;
    margin-bottom: 22px;
    display: flex; align-items: center; gap: 10px;
    animation: shake .4s ease;
}
@keyframes shake {
    0%,100%{transform:translateX(0);}
    20%{transform:translateX(-6px);}
    40%{transform:translateX(6px);}
    60%{transform:translateX(-4px);}
    80%{transform:translateX(4px);}
}
.alert-err i { font-size: 1rem; flex-shrink: 0; }

/* Groupes de champs */
.field-group { margin-bottom: 20px; }
.field-label {
    display: block;
    font-size: .72rem; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: var(--anac-blue);
    margin-bottom: 7px;
}
.field-wrap {
    position: relative;
}
.field-wrap .f-icon {
    position: absolute;
    left: 14px; top: 50%; transform: translateY(-50%);
    color: #9ca3af; font-size: .88rem;
    pointer-events: none;
    transition: color .2s;
}
.field-input {
    width: 100%;
    padding: 12px 44px 12px 42px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-family: 'Outfit', sans-serif;
    font-size: .93rem;
    color: var(--anac-blue);
    background: white;
    transition: border-color .25s, box-shadow .25s;
    outline: none;
}
.field-input::placeholder { color: #c4c8d0; }
.field-input:focus {
    border-color: var(--anac-blue);
    box-shadow: 0 0 0 4px rgba(3,34,76,.09);
}
.field-input:focus + .f-icon, .field-wrap:focus-within .f-icon { color: var(--anac-blue); }

/* Bouton toggle mot de passe */
.toggle-pw {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #9ca3af; font-size: .88rem; padding: 4px;
    border-radius: 6px; transition: color .2s, background .2s;
    display: flex; align-items: center;
}
.toggle-pw:hover { color: var(--anac-blue); background: #f3f4f6; }

/* Bouton se connecter */
.btn-login {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--anac-blue) 0%, var(--anac-blue-mid) 100%);
    color: white; border: none; border-radius: 12px;
    font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 700;
    cursor: pointer; letter-spacing: .3px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    transition: all .3s;
    position: relative; overflow: hidden;
    margin-top: 8px;
}
.btn-login::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(212,175,55,.15), rgba(212,175,55,.05));
    opacity: 0; transition: opacity .3s;
}
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(3,34,76,.35); }
.btn-login:hover::before { opacity: 1; }
.btn-login:active { transform: translateY(0); }
.btn-login .btn-icon { font-size: .95rem; }

/* Séparateur */
.divider {
    display: flex; align-items: center; gap: 12px;
    margin: 22px 0;
    color: #c4c8d0; font-size: .75rem;
}
.divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: #e5e7eb;
}

/* Lien retour accueil (dans la card) */
.home-link-card {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    color: var(--anac-blue-mid); text-decoration: none;
    font-size: .84rem; font-weight: 600;
    padding: 10px;
    border: 1.5px solid var(--anac-blue-lite);
    border-radius: 12px;
    background: var(--anac-blue-lite);
    transition: all .25s;
}
.home-link-card i { color: var(--anac-gold); font-size: .82rem; }
.home-link-card:hover {
    background: var(--anac-blue);
    color: white; border-color: var(--anac-blue);
}
.home-link-card:hover i { color: var(--anac-gold); }

/* Pied de card */
.card-foot {
    background: linear-gradient(135deg, var(--anac-blue), var(--anac-blue-mid));
    padding: 14px 36px;
    text-align: center;
    border-top: 2px solid rgba(212,175,55,.2);
}
.card-foot p { color: rgba(255,255,255,.55); font-size: .72rem; }
.card-foot p span { color: var(--anac-gold); font-weight: 600; }

/* ═══════════════════════════════════════════════════
   PIED DE PAGE
═══════════════════════════════════════════════════ */
.site-footer {
    position: relative; z-index: 10;
    text-align: center;
    padding: 14px;
    color: rgba(255,255,255,.3);
    font-size: .72rem;
    border-top: 1px solid rgba(212,175,55,.1);
    background: rgba(3,34,76,.6);
}
.site-footer a { color: var(--anac-gold); text-decoration: none; }
.site-footer a:hover { text-decoration: underline; }

/* ═══════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════ */
@media (max-width: 480px) {
    .top-nav { padding: 12px 16px; }
    .card-head, .card-body { padding-left: 22px; padding-right: 22px; }
    .card-foot { padding-left: 22px; padding-right: 22px; }
    .login-main { padding: 20px 12px; }
    .nav-brand-text .line1 { font-size: .82rem; }
}
</style>
</head>
<body>

<!-- ═══ Fond animé ═══ -->
<div class="bg-scene">
  <div class="halo-top"></div>
  <div class="plane-deco">✈</div>
  <!-- Particules flottantes -->
  <div class="particle" style="left:15%;bottom:0;animation-duration:5s;animation-delay:0s;"></div>
  <div class="particle" style="left:30%;bottom:0;animation-duration:7s;animation-delay:1.2s;"></div>
  <div class="particle" style="left:52%;bottom:0;animation-duration:6s;animation-delay:.4s;"></div>
  <div class="particle" style="left:68%;bottom:0;animation-duration:8s;animation-delay:2s;"></div>
  <div class="particle" style="left:82%;bottom:0;animation-duration:5.5s;animation-delay:.8s;"></div>
  <div class="particle" style="left:90%;bottom:0;animation-duration:6.5s;animation-delay:1.8s;"></div>
</div>

<!-- ═══ Barre de navigation ═══ -->
<nav class="top-nav">
  <a href="../index.php" class="nav-brand" title="Accueil AIR SECURE">
    <img src="../assets/images/Logo-ANAC-CERTIFICATION.png"
         alt="ANAC" class="nav-logo" onerror="this.style.display='none'">
    <div class="nav-brand-text">
      <span class="line1">EXASUR</span>
      <span class="line2">ANAC GABON</span>
    </div>
  </a>
  <a href="../../index.php" class="nav-home-link">
    <i class="fas fa-home"></i>
    Retour au site
  </a>
</nav>

<!-- ═══ Contenu principal ═══ -->
<main class="login-main">
  <div class="login-card">

    <!-- Header card -->
    <div class="card-head">
      <div class="head-logo-wrap">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png"
             alt="ANAC" class="head-logo"
             onerror="this.src='../assets/images/LOGO_ANAC.png'">
      </div>
      <h1>Administration</h1>
      <div class="head-sub">Direction de la Sûreté et de la Facilitation</div>
      <div class="exam-badges">
        <span class="exam-badge gold">AIR SECURE</span>
        <span class="exam-badge">AS</span>
        <span class="exam-badge">IF</span>
        <span class="exam-badge">INST</span>
        <span class="exam-badge">SENS</span>
        <span class="exam-badge">FORM</span>
      </div>
    </div>

    <!-- Body card -->
    <div class="card-body">

      <?php if ($error): ?>
      <div class="alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>

        <!-- Code d'accès -->
        <div class="field-group">
          <label class="field-label" for="code_acces">
            <i class="fas fa-id-badge" style="margin-right:5px;color:var(--anac-gold)"></i>
            Code d'accès administrateur
          </label>
          <div class="field-wrap">
            <i class="fas fa-id-card f-icon"></i>
            <input type="text" id="code_acces" name="code_acces"
                   class="field-input" placeholder="Ex : 0111"
                   autocomplete="username" required spellcheck="false"
                   value="<?= htmlspecialchars($_POST['code_acces'] ?? '') ?>">
          </div>
        </div>

        <!-- Mot de passe -->
        <div class="field-group">
          <label class="field-label" for="password">
            <i class="fas fa-lock" style="margin-right:5px;color:var(--anac-gold)"></i>
            Mot de passe
          </label>
          <div class="field-wrap">
            <i class="fas fa-lock f-icon"></i>
            <input type="password" id="password" name="password"
                   class="field-input" placeholder="••••••••••"
                   autocomplete="current-password" required
                   style="padding-right:46px">
            <button type="button" class="toggle-pw" id="togglePwd"
                    title="Afficher / masquer le mot de passe"
                    onclick="togglePassword()">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <!-- Bouton connexion -->
        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt btn-icon"></i>
          Se connecter
        </button>

      </form>

      <!-- Séparateur -->
      <div class="divider">ou</div>

      <!-- Lien retour accueil -->
      <a href="../../index.php" class="home-link-card">
        <i class="fas fa-globe"></i>
        Retour à l'accueil du site
      </a>

    </div><!-- /card-body -->

    <!-- Pied de card -->
    <div class="card-foot">
      <p>Accès réservé aux administrateurs habilités</p>
      <p style="margin-top:4px">
        <span>ANAC GABON</span> 
      </p>
    </div>

  </div><!-- /login-card -->
</main>

<!-- ═══ Footer ═══ -->
<footer class="site-footer">
  &copy; <?= date('Y') ?> <a href="../index.php">ANAC GABON</a>
  &nbsp;·&nbsp; Direction de la Sûreté et de la Facilitation de l'Aviation Civile
  &nbsp;·&nbsp; Système d'examen EXASUR
</footer>

<!-- ═══ Scripts ═══ -->
<script>
function togglePassword() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type  = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        pwd.type  = 'password';
        icon.className = 'fas fa-eye';
    }
    pwd.focus();
}

// Focus automatique sur le premier champ vide
window.addEventListener('DOMContentLoaded', () => {
    const code = document.getElementById('code_acces');
    const pwd  = document.getElementById('password');
    if (!code.value) { code.focus(); }
    else { pwd.focus(); }
});
</script>
</body>
</html>