<?php
/**
 * index.php — Page d'accueil EXASUR
 * ANAC GABON — Direction de la Sûreté et de la Facilitation
 * MODIFICATIONS : Seuil affiché 70% pour AS (type1), IF (type2), INST (type3)
 */
session_start();
include 'ANAC/lang/lang_loader.php';

// ── Connexion BDD pour récupérer sessions disponibles ────────────────
include 'ANAC/php/db_connection.php';

// Récupérer les sessions disponibles par type (pour les cartes examen)
$sessions_par_type = [];
$res_sess = $conn->query("SELECT te.idtype_examen, te.code, te.nom_fr, te.nb_questions_theorique,
           te.nb_questions_pratique, te.seuil_reussite, te.a_deux_parties,
           te.duree_minutes,
           COUNT(se.id_session) AS nb_sessions_actives
    FROM type_examen te
    LEFT JOIN session_examen se ON se.idtype_examen = te.idtype_examen
        AND se.statut IN ('planifiee','en_cours')
    GROUP BY te.idtype_examen
    ORDER BY te.idtype_examen
");
while ($row = $res_sess->fetch_assoc()) {
    $sessions_par_type[$row['idtype_examen']] = $row;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXASUR : Examens de Sûreté de l'Aviation Civile | ANAC GABON</title>
    <link rel="icon" href="ANAC/assets/images/LOGOANAC.PNG" type="image/png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        /* ══════════════════════════════════════════════════
           VARIABLES CSS & RESET
        ══════════════════════════════════════════════════ */
        :root {
            --blue-deep:    #03224c;
            --blue-mid:     #0a3a6b;
            --blue-light:   #1a5fa8;
            --gold:         #D4AF37;
            --gold-light:   #f0d060;
            --white:        #ffffff;
            --off-white:    #f4f7fc;
            --text-dark:    #1a1a2e;
            --text-muted:   #6b7a99;
            --shadow-card:  0 20px 60px rgba(3,34,76,0.12);
            --radius-lg:    18px;
            --radius-xl:    28px;
            --transition:   0.3s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Candara', 'Calibri', sans-serif;
            background: var(--off-white);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* ══════════════════════════════════════════════════
           NAVBAR
        ══════════════════════════════════════════════════ */
        .anac-navbar {
            background: var(--blue-deep);
            border-bottom: 3px solid var(--gold);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 24px rgba(3,34,76,0.35);
        }
        .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 24px;
            flex-wrap: wrap;
            gap: 10px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }
        .logo-img {
            height: 58px;
            border-radius: 8px;
            background: white;
            padding: 4px 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transition: transform var(--transition);
        }
        .logo-img:hover { transform: scale(1.04); }
        .logo-texts { line-height: 1.2; }
        .logo-title {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .logo-sub {
            color: var(--gold);
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .nav-link-item {
            color: #ffffff !important;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 50px;
            transition: background var(--transition), color var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            background: transparent;
            border: none;
            font-family: inherit;
            letter-spacing: 0.3px;
        }
        .nav-link-item:hover {
            background: rgba(212,175,55,0.18);
            color: var(--gold) !important;
        }
        .nav-link-item.admin-btn {
            background: var(--gold);
            color: var(--blue-deep) !important;
            font-weight: 700;
            margin-left: 6px;
        }
        .nav-link-item.admin-btn:hover {
            background: var(--gold-light);
            color: var(--blue-deep) !important;
        }
        .lang-switch { display: flex; gap: 6px; align-items: center; margin-right: 8px; }
        .lang-btn {
            background: transparent;
            border: 1.5px solid rgba(212,175,55,0.5);
            color: #ffffff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.25s;
            letter-spacing: 1px;
        }
        .lang-btn:hover, .lang-btn.active {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--blue-deep);
        }
        .mobile-toggle {
            display: none;
            background: none;
            border: 1.5px solid rgba(255,255,255,0.4);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
        }
        @media(max-width:768px) {
            .mobile-toggle { display: block; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                background: var(--blue-mid);
                padding: 12px;
                border-radius: 12px;
                gap: 4px;
            }
            .nav-links.open { display: flex; }
        }

        /* ══════════════════════════════════════════════════
           HERO
        ══════════════════════════════════════════════════ */
        .hero {
            position: relative;
            min-height: 580px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: var(--blue-deep);
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 70% 30%, rgba(10,58,107,0.9) 0%, transparent 70%),
                radial-gradient(ellipse 60% 80% at 20% 80%, rgba(212,175,55,0.12) 0%, transparent 60%),
                linear-gradient(135deg, #020d1f 0%, #03224c 50%, #0a3a6b 100%);
        }
        .hero-plane {
            position: absolute; top: 18%; left: -120px;
            font-size: 2.5rem; opacity: 0.15;
            animation: flyPlane 12s linear infinite;
        }
        .hero-plane2 {
            position: absolute; top: 62%; left: -80px;
            font-size: 1.6rem; opacity: 0.09;
            animation: flyPlane 18s linear 5s infinite;
        }
        @keyframes flyPlane { from { left: -150px; } to { left: 110%; } }
        .hero-circle {
            position: absolute; border-radius: 50%;
            border: 1px solid rgba(212,175,55,0.15);
        }
        .hc1 { width:500px; height:500px; top:-120px; right:-100px; }
        .hc2 { width:300px; height:300px; bottom:-60px; left:-60px; border-color:rgba(212,175,55,0.08); }
        .hc3 { width:180px; height:180px; top:30%; right:18%; border-color:rgba(212,175,55,0.2); animation:rotateSlow 20s linear infinite; }
        @keyframes rotateSlow { to { transform: rotate(360deg); } }
        .hero-accent {
            position:absolute; bottom:0; left:0; width:100%; height:4px;
            background:linear-gradient(90deg,transparent,var(--gold),transparent);
        }
        .hero-content {
            position: relative; z-index: 2;
            text-align: center; padding: 60px 20px;
            max-width: 900px;
            animation: fadeUp 0.8s ease both;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(212,175,55,0.15);
            border: 1px solid rgba(212,175,55,0.4);
            color: var(--gold);
            padding: 6px 20px; border-radius: 40px;
            font-size: 0.78rem; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            margin-bottom: 24px;
        }
        .hero-title {
            font-size: clamp(3rem, 9vw, 6.5rem);
            font-weight: 800; color: white;
            letter-spacing: 6px; text-transform: uppercase;
            line-height: 1; margin-bottom: 10px;
        }
        .hero-title-accent {
            display: block;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: clamp(0.9rem, 2.5vw, 1.3rem);
            letter-spacing: 8px; font-weight: 400; margin-bottom: 28px;
        }
        .hero-subtitle {
            color: rgba(255,255,255,0.78);
            font-size: clamp(0.95rem, 2vw, 1.15rem);
            line-height: 1.7; max-width: 680px;
            margin: 0 auto 40px; font-weight: 400;
        }
        .hero-actions {
            display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;
        }
        .btn-primary-hero {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
            color: var(--blue-deep); border: none;
            padding: 16px 44px; border-radius: 50px;
            font-size: 1rem; font-weight: 800;
            cursor: pointer; transition: all var(--transition);
            box-shadow: 0 8px 30px rgba(212,175,55,0.4);
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none; font-family: inherit;
        }
        .btn-primary-hero:hover {
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 16px 40px rgba(212,175,55,0.55);
            color: var(--blue-deep);
        }
        .btn-secondary-hero {
            background: transparent; color: white;
            border: 2px solid rgba(255,255,255,0.35);
            padding: 14px 36px; border-radius: 50px;
            font-size: 0.96rem; font-weight: 600;
            cursor: pointer; transition: all var(--transition);
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none;
        }
        .btn-secondary-hero:hover {
            border-color: var(--gold); color: var(--gold);
            background: rgba(212,175,55,0.06);
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(30px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ══════════════════════════════════════════════════
           BARRE DE STATS
        ══════════════════════════════════════════════════ */
        .stats-bar {
            background: white;
            box-shadow: 0 4px 24px rgba(3,34,76,0.09);
            border-bottom: 3px solid var(--gold);
        }
        .stats-inner {
            display: flex; align-items: stretch;
            justify-content: space-around; flex-wrap: wrap;
            max-width: 1400px; margin: 0 auto;
        }
        .stat-item {
            text-align: center; padding: 20px 28px; flex: 1;
            min-width: 100px;
            border-right: 1px solid rgba(3,34,76,0.07);
            transition: background 0.2s;
        }
        .stat-item:last-child { border-right: none; }
        .stat-item:hover { background: var(--off-white); }
        .stat-num {
            font-size: 1.9rem; font-weight: 800;
            color: var(--blue-deep); line-height: 1;
        }
        .stat-num span { color: var(--gold); }
        .stat-label {
            font-size: 0.72rem; color: var(--text-muted);
            font-weight: 600; text-transform: uppercase;
            letter-spacing: 1px; margin-top: 4px;
        }

        /* ══════════════════════════════════════════════════
           SECTIONS TITRES
        ══════════════════════════════════════════════════ */
        .container { max-width: 1300px; margin: 0 auto; padding: 0 20px; }
        .sec-title { text-align: center; margin-bottom: 52px; }
        .sec-label {
            display: inline-block;
            background: linear-gradient(135deg, var(--blue-deep), var(--blue-mid));
            color: var(--gold); font-size: 0.72rem; font-weight: 700;
            letter-spacing: 3px; text-transform: uppercase;
            padding: 5px 18px; border-radius: 20px; margin-bottom: 14px;
        }
        .sec-h2 {
            font-size: clamp(1.7rem, 4vw, 2.5rem);
            font-weight: 800; color: var(--blue-deep);
            line-height: 1.2; margin-bottom: 14px;
        }
        .sec-line {
            width: 60px; height: 4px;
            background: linear-gradient(90deg, var(--blue-deep), var(--gold));
            border-radius: 2px; margin: 0 auto;
        }

        /* ══════════════════════════════════════════════════
           CARTES EXAMENS
        ══════════════════════════════════════════════════ */
        .categories-section {
            padding: 80px 0; background: white; position: relative;
        }
        .categories-section::before {
            content: ''; position: absolute; top:0; left:0; right:0; height:4px;
            background: linear-gradient(90deg, var(--blue-deep), var(--gold), var(--blue-deep));
        }
        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .cat-card {
            background: white; border-radius: var(--radius-xl);
            overflow: hidden;
            border: 1px solid rgba(3,34,76,0.08);
            box-shadow: 0 8px 30px rgba(3,34,76,0.08);
            cursor: pointer;
            transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex; flex-direction: column;
        }
        .cat-card:hover {
            transform: translateY(-12px) scale(1.01);
            box-shadow: 0 30px 60px rgba(3,34,76,0.2);
            border-color: var(--gold);
        }
        .cat-top {
            background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-mid) 100%);
            padding: 28px 20px 20px; text-align: center;
            border-bottom: 3px solid var(--gold);
        }
        .cat-code {
            display: inline-block;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--blue-deep);
            font-size: 1.4rem; font-weight: 900;
            padding: 10px 22px; border-radius: 12px;
            letter-spacing: 3px; margin-bottom: 12px;
            box-shadow: 0 4px 16px rgba(212,175,55,0.4);
        }
        .cat-full-name {
            color: rgba(255,255,255,0.9);
            font-size: 0.82rem; font-weight: 600;
            letter-spacing: 0.5px; margin-top: 6px;
        }
        .cat-icon-wrap {
            width: 56px; height: 56px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 12px auto 0; font-size: 1.5rem; color: white;
            border: 2px solid rgba(212,175,55,0.4);
            transition: background var(--transition);
        }
        .cat-card:hover .cat-icon-wrap { background: rgba(212,175,55,0.15); }
        .cat-body {
            padding: 22px 20px; flex: 1; display: flex; flex-direction: column;
        }
        .cat-name {
            font-size: 1.05rem; font-weight: 700;
            color: var(--blue-deep); margin-bottom: 8px;
        }
        .cat-desc {
            font-size: 0.85rem; color: var(--text-muted);
            line-height: 1.6; flex: 1; margin-bottom: 14px;
        }
        .cat-meta {
            display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .meta-tag {
            background: rgba(3,34,76,0.06);
            color: var(--blue-mid); font-size: 0.72rem;
            font-weight: 700; padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex; align-items: center; gap: 4px;
            border: 1px solid rgba(3,34,76,0.09);
        }
        .meta-tag.gold {
            background: rgba(212,175,55,0.12);
            color: #8a6e0a; border-color: rgba(212,175,55,0.25);
        }
        .meta-tag.no-sess {
            background: #fff3cd; color: #856404;
            border-color: #ffc107;
        }
        .meta-tag.info {
            background: #e0f2fe; color: #0369a1;
            border-color: #bae6fd;
        }
        .btn-cat {
            background: linear-gradient(135deg, var(--blue-deep), var(--blue-mid));
            color: white; border: none;
            padding: 12px; border-radius: 12px;
            font-size: 0.88rem; font-weight: 700;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
            gap: 8px; transition: all var(--transition); cursor: pointer;
            border: 1px solid rgba(212,175,55,0.3); font-family: inherit;
        }
        .btn-cat:hover { background: var(--gold); color: var(--blue-deep); }
        .btn-cat.disabled {
            background: #e5e7eb; color: #9ca3af;
            cursor: not-allowed; border-color: #e5e7eb;
        }
        .btn-cat.disabled:hover { background: #e5e7eb; color: #9ca3af; transform: none; }

        /* Carte CTA "Consulter note" */
        .cta-note-card {
            background: linear-gradient(135deg, var(--blue-deep) 0%, #0a3a6b 100%);
            border-radius: var(--radius-xl); padding: 36px 24px;
            text-align: center; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            border: 1px solid rgba(212,175,55,0.3);
            box-shadow: var(--shadow-card);
        }

        /* ══════════════════════════════════════════════════
           FEATURES / ATOUTS
        ══════════════════════════════════════════════════ */
        .features-section {
            padding: 80px 0;
            background: var(--blue-deep);
            position: relative; overflow: hidden;
        }
        .features-section::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse 80% 60% at 80% 50%, rgba(10,58,107,0.8) 0%, transparent 70%);
        }
        .features-section .sec-h2 { color: white; }
        .features-section .sec-label { background: rgba(212,175,55,0.2); }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px; position: relative; z-index: 1;
        }
        .feat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg); padding: 36px 28px;
            text-align: center; transition: all var(--transition);
            position: relative; overflow: hidden;
        }
        .feat-card::before {
            content: ''; position: absolute; top:0; left:0; right:0; height:3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            transform: scaleX(0); transition: transform var(--transition);
        }
        .feat-card:hover { background: rgba(255,255,255,0.09); transform: translateY(-6px); }
        .feat-card:hover::before { transform: scaleX(1); }
        .feat-icon {
            width: 72px; height: 72px;
            background: rgba(212,175,55,0.15);
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 1.6rem; color: var(--gold);
            border: 2px solid rgba(212,175,55,0.3);
        }
        .feat-title { color: white; font-weight: 700; font-size: 1.05rem; margin-bottom: 10px; }
        .feat-desc { color: rgba(255,255,255,0.62); font-size: 0.88rem; line-height: 1.6; }

        /* ══════════════════════════════════════════════════
           FOOTER
        ══════════════════════════════════════════════════ */
        footer {
            background: var(--blue-deep);
            color: rgba(255,255,255,0.7);
            padding: 60px 0 30px;
            border-top: 3px solid var(--gold);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 40px;
        }
        @media(max-width:768px) {
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 24px; }
        }
        @media(max-width:480px) {
            .footer-grid { grid-template-columns: 1fr; }
        }
        .footer-logo-img {
            height: 52px; background: white;
            padding: 6px 8px; border-radius: 8px; margin-bottom: 14px; display: block;
        }
        .footer-brand {
            color: white; font-size: 1.3rem;
            font-weight: 800; letter-spacing: 3px; margin-bottom: 4px;
        }
        .footer-tagline { color: var(--gold); font-size: 0.78rem; margin-bottom: 12px; }
        .footer-text { font-size: 0.83rem; line-height: 1.7; }
        .footer-heading {
            color: var(--gold); font-weight: 700;
            font-size: 0.8rem; letter-spacing: 2px;
            text-transform: uppercase; margin-bottom: 14px;
        }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 8px; }
        .footer-links a {
            color: rgba(255,255,255,0.65); text-decoration: none;
            font-size: 0.85rem; transition: color var(--transition);
        }
        .footer-links a:hover { color: var(--gold); }
        .contact-item {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 10px; font-size: 0.85rem;
        }
        .contact-icon {
            width: 32px; height: 32px;
            background: rgba(212,175,55,0.15);
            border-radius: 8px; display: flex;
            align-items: center; justify-content: center;
            color: var(--gold); font-size: 0.85rem; flex-shrink: 0;
        }
        .social-row { display: flex; gap: 10px; margin-top: 14px; }
        .social-btn {
            width: 38px; height: 38px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            color: rgba(255,255,255,0.6); text-decoration: none;
            transition: all var(--transition); font-size: 0.9rem;
        }
        .social-btn:hover { background: var(--gold); color: var(--blue-deep); }
        .footer-bottom {
            margin-top: 40px; padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex; justify-content: space-between;
            flex-wrap: wrap; gap: 10px;
            font-size: 0.78rem;
        }
        .footer-copy span { color: var(--gold); }

        /* ══════════════════════════════════════════════════
           SCROLL REVEAL
        ══════════════════════════════════════════════════ */
        .reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ══════════════════════════════════════════════════
           MODAL À PROPOS (CSS natif sans Bootstrap)
        ══════════════════════════════════════════════════ */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.6); z-index: 9999;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: white; border-radius: var(--radius-xl);
            max-width: 700px; width: 100%;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:scale(0.92); } to { opacity:1; transform:scale(1); } }
        .modal-header {
            background: linear-gradient(135deg, var(--blue-deep), var(--blue-mid));
            padding: 20px 24px;
            border-bottom: 3px solid var(--gold);
            display: flex; align-items: center; justify-content: space-between;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        .modal-header h5 { color: white; font-weight: 700; font-size: 1.05rem; margin: 0; }
        .modal-close {
            background: rgba(255,255,255,0.15); border: none;
            color: white; width: 32px; height: 32px;
            border-radius: 50%; cursor: pointer; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: background var(--transition);
        }
        .modal-close:hover { background: var(--gold); color: var(--blue-deep); }
        .modal-body { padding: 28px 24px; }
        .modal-body h6 {
            color: var(--blue-deep); font-weight: 700;
            font-size: 0.95rem; margin: 18px 0 8px;
            display: flex; align-items: center; gap: 8px;
        }
        .modal-body h6:first-child { margin-top: 0; }
        .modal-body p { color: #4b5563; font-size: 0.9rem; line-height: 1.7; margin-bottom: 8px; }
        .about-exam-list {
            list-style: none; padding: 0; margin: 0;
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        }
        .about-exam-list li {
            background: var(--off-white);
            border-left: 3px solid var(--gold);
            padding: 10px 14px; border-radius: 8px;
            font-size: 0.85rem; color: var(--blue-deep); font-weight: 600;
        }
        @media(max-width:480px) { .about-exam-list { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════════
     NAVBAR
══════════════════════════════════════════════════ -->
<nav class="anac-navbar">
    <div class="navbar-inner">
        <!-- Logo -->
        <a href="index.php" class="logo-wrap">
            <img src="ANAC/assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC GABON" class="logo-img">
            <div class="logo-texts">
                <div class="logo-title">EXASUR</div>
                <div class="logo-sub">ANAC GABON — Direction de la Sûreté et de la Facilitation</div>
            </div>
        </a>

        <!-- Partie droite -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <!-- Langue -->
            <div class="lang-switch">
                <a href="?lang=fr" class="lang-btn <?php echo $_SESSION['lang']=='fr' ? 'active' : ''; ?>">FR</a>
                <a href="?lang=en" class="lang-btn <?php echo $_SESSION['lang']=='en' ? 'active' : ''; ?>">EN</a>
            </div>
            <!-- Bouton hamburger mobile -->
            <button class="mobile-toggle" onclick="toggleNav()"><i class="fas fa-bars"></i></button>
            <!-- Liens nav -->
            <div class="nav-links" id="navLinks">
                <a href="index.php" class="nav-link-item">
                    <i class="fas fa-home"></i> <?php echo __('accueil'); ?>
                </a>
                <button class="nav-link-item" onclick="openModal('aboutModal')">
                    <i class="fas fa-info-circle"></i> <?php echo __('a_propos'); ?>
                </button>
                <button class="nav-link-item" onclick="openModal('contactModal')">
                    <i class="fas fa-envelope"></i> <?php echo __('contact'); ?>
                </button>
                <a  href="ANAC/admin/login.php" class="nav-link-item admin-btn">
                    <i class="fas fa-user-shield"></i> <?php echo __('administration'); ?>
                </a>
            </div>
        </div>
    </div>
</nav>


<!-- ══════════════════════════════════════════════════
     HERO
══════════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-circle hc1"></div>
    <div class="hero-circle hc2"></div>
    <div class="hero-circle hc3"></div>
    <div class="hero-plane">✈</div>
    <div class="hero-plane2">✈</div>
    <div class="hero-accent"></div>

    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-shield-alt"></i>
            <?php echo ($_SESSION['lang']=='fr') ? 'Plateforme officielle d\'Examens AVSEC-FAL — ANAC GABON' : 'Official AVSEC Exam Platform — ANAC GABON'; ?>
        </div>
        <h1 class="hero-title">
            EXASUR
            <span class="hero-title-accent">ANAC · GABON</span>
        </h1>
        <p class="hero-subtitle">
            <?php echo ($_SESSION['lang']=='fr')
                ? 'Plateforme officielle d\'examens de certification et d\'évaluation du personnel de Sûreté et de Facilitation de l\'Aviation Civile en République Gabonaise.'
                : 'Official examination and assessment platform for Civil Aviation Security and Facilitation personnel in Gabon.'; ?>
        </p>
        <div class="hero-actions">
            <button class="btn-primary-hero" onclick="checkNote()">
                <i class="fas fa-search"></i>
                <?php echo __('consulter_note'); ?>
            </button>
            <a href="#categories" class="btn-secondary-hero">
                <i class="fas fa-layer-group"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Voir les examens' : 'View exams'; ?>
            </a>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════
     BARRE STATISTIQUES
══════════════════════════════════════════════════ -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="stat-num">5<span>+</span></div>
            <div class="stat-label"><?php echo ($_SESSION['lang']=='fr') ? 'Types d\'examen' : 'Exam types'; ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">70-80<span>%</span></div>
            <div class="stat-label"><?php echo ($_SESSION['lang']=='fr') ? 'Seuil de validation' : 'Pass threshold'; ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">50<span>Q</span></div>
            <div class="stat-label"><?php echo ($_SESSION['lang']=='fr') ? 'Questions par épreuve' : 'Questions per exam'; ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num">100<span>%</span></div>
            <div class="stat-label"><?php echo ($_SESSION['lang']=='fr') ? 'Résultats immédiats' : 'Instant results'; ?></div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><span>✈</span></div>
            <div class="stat-label">AVSEC — FAL</div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════
     EXAMENS DISPONIBLES (affiché par défaut)
══════════════════════════════════════════════════ -->
<section class="categories-section" id="categories">
    <div class="container">
        <div class="sec-title reveal">
            <div class="sec-label"><?php echo ($_SESSION['lang']=='fr') ? 'Examens disponibles' : 'Available exams'; ?></div>
            <h2 class="sec-h2"><?php echo __('categories_personnel'); ?></h2>
            <div class="sec-line"></div>
        </div>

        <div class="exams-grid">

            <?php
            // ── Configuration des cartes examen ──────────────────────
            $exam_config = [
                1 => ['icon'=>'fas fa-shield-alt',          'color'=>'#1e40af'],
                2 => ['icon'=>'fas fa-search',              'color'=>'#065f46'],
                3 => ['icon'=>'fas fa-chalkboard-teacher',  'color'=>'#92400e'],
                4 => ['icon'=>'fas fa-exclamation-triangle','color'=>'#5b21b6'],
                5 => ['icon'=>'fas fa-graduation-cap',      'color'=>'#9d174d'],
            ];

            foreach ($sessions_par_type as $idtype => $info):
                $cfg      = $exam_config[$idtype] ?? ['icon'=>'fas fa-file-alt','color'=>'#374151'];
                $has_sess = $info['nb_sessions_actives'] > 0;
                $link     = "ANAC/candidat/instructions.php?type={$idtype}";

                // Durée affichée
                if ($info['a_deux_parties'] && $info['code'] == 'IF') {
                    $duree_str = '2h (Th.+Prat.)';
                } else {
                    $dur = intval($info['duree_minutes']);
                    $duree_str = $dur >= 60 ? floor($dur/60).'h'.($dur%60?str_pad($dur%60,2,'0',STR_PAD_LEFT):'') : $dur.'min';
                }

                // Seuil affiché : 70% pour AS (1), IF (2), INST (3)
                $seuil_original = $info['seuil_reussite'];
                if (in_array($idtype, [1, 2, 3])) {
                    $seuil_affiche = 70;
                } else {
                    $seuil_affiche = $seuil_original;
                }

                // Message info pour AS et INST (score masqué)
                $info_message = '';
                if (in_array($idtype, [1, 3]) && !$info['a_deux_parties']) {
                    $info_message = '<span class="meta-tag info"><i class="fas fa-envelope"></i> Résultat confidentiel</span>';
                }
            ?>
            <!-- Carte examen : <?php echo $info['code']; ?> -->
            <div class="reveal">
                <div class="cat-card" onclick="<?php echo $has_sess ? "window.location.href='$link'" : "noSession('{$info['code']}')"; ?>">
                    <div class="cat-top">
                        <div class="cat-code"><?php echo htmlspecialchars($info['code']); ?></div>
                        <div class="cat-full-name"><?php echo htmlspecialchars($info['nom_fr']); ?></div>
                        <div class="cat-icon-wrap"><i class="<?php echo $cfg['icon']; ?>"></i></div>
                    </div>
                    <div class="cat-body">
                        <div class="cat-name"><?php echo htmlspecialchars($info['nom_fr']); ?></div>
                        <div class="cat-desc">
                            <?php
                            // Description par code — correspondance exacte avec les clés de fr.php
                            $descs = [
                                'AS'   => __('agent_surete_desc'),
                                'IF'   => __('agent_if_desc'),
                                'INST' => __('instructeur_desc'),
                                'SENS' => __('sensibilisation_desc'),
                                'FORM' => __('formation_desc'),
                            ];
                            echo htmlspecialchars($descs[$info['code']] ?? __('formation_desc'));
                            ?>
                        </div>
                        <div class="cat-meta">
                            <span class="meta-tag"><i class="fas fa-clock"></i> <?php echo $duree_str; ?></span>
                            <?php if ($info['a_deux_parties'] && $info['code']=='IF'): ?>
                            <span class="meta-tag"><i class="fas fa-layer-group"></i> Th.+Prat.</span>
                            <?php else: ?>
                            <span class="meta-tag"><i class="fas fa-question-circle"></i> <?php echo $info['nb_questions_theorique']; ?> Q</span>
                            <?php endif; ?>
                            <span class="meta-tag gold"><i class="fas fa-check-circle"></i> <?php echo $seuil_affiche; ?>%</span>
                            <?php echo $info_message; ?>
                            <?php if (!$has_sess): ?>
                            <span class="meta-tag no-sess"><i class="fas fa-calendar-times"></i> Aucune session</span>
                            <?php else: ?>
                            <span class="meta-tag" style="background:#dcfce7;color:#166534;border-color:#bbf7d0;"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Session active</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_sess): ?>
                        <a class="btn-cat" href="<?php echo $link; ?>" onclick="event.stopPropagation();">
                            <?php echo __('voir_instructions'); ?> <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <button class="btn-cat disabled" onclick="event.stopPropagation(); noSession('<?php echo $info['code']; ?>')">
                            <i class="fas fa-calendar-times"></i> Aucune session disponible
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Carte CTA "Consulter ma note" -->
            <div class="reveal">
                <div class="cta-note-card">
                    <div style="font-size:3rem;margin-bottom:16px;">📋</div>
                    <h4 style="color:white;font-weight:800;margin-bottom:12px;font-size:1.15rem;">
                        <?php echo ($_SESSION['lang']=='fr') ? 'Consulter votre note' : 'Check your score'; ?>
                    </h4>
                    <p style="color:rgba(255,255,255,0.65);font-size:0.88rem;margin-bottom:24px;line-height:1.6;">
                        <?php echo ($_SESSION['lang']=='fr')
                            ? 'Retrouvez vos résultats avec votre code d\'accès (4 chiffres) et les dates de votre session.'
                            : 'Find your results with your 4-digit access code and session dates.'; ?>
                    </p>
                    <button class="btn-primary-hero" onclick="checkNote()" style="font-size:0.9rem;padding:12px 26px;animation:none;box-shadow:0 6px 20px rgba(212,175,55,0.4);">
                        <i class="fas fa-search"></i> <?php echo __('consulter_note'); ?>
                    </button>
                </div>
            </div>

        </div><!-- /exams-grid -->
    </div>
</section>


<!-- ══════════════════════════════════════════════════
     ATOUTS DE LA PLATEFORME
══════════════════════════════════════════════════ -->
<section class="features-section">
    <div class="container" style="position:relative;z-index:1;">
        <div class="sec-title reveal" style="margin-bottom:50px;">
            <div class="sec-label"><?php echo ($_SESSION['lang']=='fr') ? 'Pourquoi EXASUR ?' : 'Why EXASUR?'; ?></div>
            <h2 class="sec-h2"><?php echo ($_SESSION['lang']=='fr') ? 'Une plateforme pensée pour vous' : 'A platform designed for you'; ?></h2>
            <div class="sec-line" style="background:linear-gradient(90deg,var(--gold),var(--gold-light));"></div>
        </div>

        <div class="features-grid">
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="feat-title"><?php echo __('securite_max'); ?></div>
                    <div class="feat-desc"><?php echo __('securite_desc'); ?></div>
                </div>
            </div>
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-bolt"></i></div>
                    <div class="feat-title"><?php echo __('resultats_immediats'); ?></div>
                    <div class="feat-desc"><?php echo __('resultats_desc'); ?></div>
                </div>
            </div>
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-sync-alt"></i></div>
                    <div class="feat-title"><?php echo __('navigation_libre'); ?></div>
                    <div class="feat-desc"><?php echo __('navigation_desc'); ?></div>
                </div>
            </div>
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-certificate"></i></div>
                    <div class="feat-title"><?php echo ($_SESSION['lang']=='fr') ? 'Résultat officiel' : 'Official result'; ?></div>
                    <div class="feat-desc"><?php echo ($_SESSION['lang']=='fr') ? 'Résultats reconnus par l\'ANAC GABON.' : 'Results recognized by ANAC GABON.'; ?></div>
                </div>
            </div>
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="feat-title"><?php echo ($_SESSION['lang']=='fr') ? 'Accessible' : 'Access'; ?></div>
                    <div class="feat-desc"><?php echo ($_SESSION['lang']=='fr') ? 'Compatible ordinateur, tablette et mobile.' : 'Compatible with desktop, tablet and mobile.'; ?></div>
                </div>
            </div>
            <div class="reveal">
                <div class="feat-card">
                    <div class="feat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="feat-title"><?php echo ($_SESSION['lang']=='fr') ? 'Suivi & Statistiques' : 'Tracking & Statistics'; ?></div>
                    <div class="feat-desc"><?php echo ($_SESSION['lang']=='fr') ? 'Tableau de bord administrateur avec statistiques complètes et rapports imprimables.' : 'Admin dashboard with full statistics and printable reports.'; ?></div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════ -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <!-- Marque -->
            <div>
                <img src="ANAC/assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" class="footer-logo-img">
                <div class="footer-brand">EXASUR</div>
                <div class="footer-tagline">ANAC GABON —  Direction de la Sûreté et de la Facilitation</div>
                <p class="footer-text">
                    BP 2212 · Libreville, Gabon<br>
                    <?php echo ($_SESSION['lang']=='fr') ? 'Zone aéroportuaire' : 'Airport zone'; ?>
                </p>
            </div>

            <!-- Liens utiles -->
            <div>
                <div class="footer-heading"><?php echo __('liens_utiles'); ?></div>
                <ul class="footer-links">
                    <li><a href="#categories"><?php echo __('categories_personnel'); ?></a></li>
                    <li><a href="#" onclick="openModal('aboutModal');return false;"><?php echo __('a_propos'); ?></a></li>
                    <li><a href="#" onclick="openModal('contactModal');return false;"><?php echo __('contact'); ?></a></li>
                    <li><a href="ANAC/admin/login.php"><?php echo __('administration'); ?></a></li>
                </ul>
            </div>

            <!-- Examens -->
            <div>
                <div class="footer-heading"><?php echo ($_SESSION['lang']=='fr') ? 'Examens' : 'Exams'; ?></div>
                <ul class="footer-links">
                    <li><a href="ANAC/candidat/instructions.php?type=1">AS — <?php echo __('agent_surete'); ?></a></li>
                    <li><a href="ANAC/candidat/instructions.php?type=2">IF — <?php echo __('agent_if'); ?></a></li>
                    <li><a href="ANAC/candidat/instructions.php?type=3">INST — <?php echo __('instructeur'); ?></a></li>
                    <li><a href="ANAC/candidat/instructions.php?type=4">SENS — <?php echo __('sensibilisation'); ?></a></li>
                    <li><a href="ANAC/candidat/instructions.php?type=5">FORM — <?php echo __('formation'); ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <div class="footer-heading"><?php echo __('contact'); ?></div>
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-phone"></i></span>
                    <div style="font-size:0.84rem;">(+241) 11 44 56 54 / 58</div>
                </div>
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-envelope"></i></span>
                    <div style="font-size:0.84rem;">contact@anac-gabon.com</div>
                </div>
                <div class="contact-item">
                    <span class="contact-icon"><i class="fas fa-headset"></i></span>
                    <div style="font-size:0.84rem;">rufin.mbadinga@anac-gabon.com</div>
                </div>
                <div class="footer-heading" style="margin-top:20px;margin-bottom:10px;"><?php echo __('suivez_nous'); ?></div>
                <div class="social-row">
                    <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copy">© <?php echo date('Y'); ?> <span>ANAC GABON</span>. <?php echo __('droits_reserves'); ?></div>
            <div class="footer-copy">EXASUR · <span>Direction de la Sûreté et de la Facilitation</span></div>
        </div>
    </div>
</footer>


<!-- ══════════════════════════════════════════════════
     MODAL : À PROPOS EXASUR
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="aboutModal" onclick="closeModalOutside(event,'aboutModal')">
    <div class="modal-box">
        <div class="modal-header">
            <h5><i class="fas fa-info-circle" style="color:var(--gold);margin-right:10px;"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'À propos — EXASUR' : 'About — EXASUR'; ?>
            </h5>
            <button class="modal-close" onclick="closeModal('aboutModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <h6><i class="fas fa-shield-alt" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Qu\'est-ce qu\'EXASUR ?' : 'What is EXASUR?'; ?>
            </h6>
            <p><?php echo ($_SESSION['lang']=='fr')
                ? 'EXASUR est la plateforme officielle de l\'ANAC GABON dédiée aux examens de certification et d\'évaluation du personnel de Sûreté et de Facilitation de l\'Aviation Civile en République Gabonaise. Elle permet le passage des examens en ligne, de manière sécurisée, transparente et automatisée.'
                : 'EXASUR is the official ANAC GABON platform for certification and assessment of Civil Aviation Security and Facilitation personnel in Gabon. It enables secure, transparent, and automated online examinations.';
            ?></p>

            <h6><i class="fas fa-bullseye" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Objectif' : 'Purpose'; ?>
            </h6>
            <p><?php echo ($_SESSION['lang']=='fr')
                ? 'Moderniser et automatiser le processus d\'évaluation du personnel AVSEC-FAL — en remplaçant le système manuel traditionnel par une solution numérique fiable, permettant aux agents de sûreté, agents d\'inspection filtrage, instructeurs et autres personnels de passer leurs examens de certification en ligne, en toute sécurité.'
                : 'To modernize and automate the AVSEC-FAL personnel assessment process — replacing the traditional manual system with a reliable digital solution that allows security agents, inspection filtration agents, instructors and other personnel to take their certification exams online, securely.';
            ?></p>

            <h6><i class="fas fa-list-check" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Examens disponibles sur EXASUR' : 'Exams available on EXASUR'; ?>
            </h6>
            <ul class="about-exam-list">
                <li><i class="fas fa-shield-alt" style="color:var(--blue-light);margin-right:6px;"></i>
                    AS — <?php echo __('agent_surete'); ?>
                </li>
                <li><i class="fas fa-search" style="color:#065f46;margin-right:6px;"></i>
                    IF — <?php echo __('agent_if'); ?>
                </li>
                <li><i class="fas fa-chalkboard-teacher" style="color:#92400e;margin-right:6px;"></i>
                    INST — <?php echo __('instructeur'); ?>
                </li>
                <li><i class="fas fa-exclamation-triangle" style="color:#5b21b6;margin-right:6px;"></i>
                    SENS — <?php echo __('sensibilisation'); ?>
                </li>
                <li><i class="fas fa-graduation-cap" style="color:#9d174d;margin-right:6px;"></i>
                    FORM — <?php echo __('formation'); ?>
                </li>
            </ul>

            <h6 style="margin-top:18px;"><i class="fas fa-lock" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Sécurité & Intégrité' : 'Security & Integrity'; ?>
            </h6>
            <p><?php echo ($_SESSION['lang']=='fr')
                ? 'Système anti-fraude intégré : surveillance active des changements d\'onglet, verrouillage automatique après 5 infractions, résultats immédiats à la fin de l\'épreuve et traçabilité complète de chaque session d\'examen.'
                : 'Built-in anti-fraud system: active monitoring of tab changes, automatic lock after 5 violations, immediate results at the end of the exam, and full traceability of each exam session.';
            ?></p>

            <h6><i class="fas fa-award" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Mentions de résultat' : 'Result mentions'; ?>
            </h6>
            <p>
                <strong style="color:#16a34a;">✅ VALIDÉ</strong> —
                <?php echo ($_SESSION['lang']=='fr') ? 'Le candidat a atteint le seuil requis. Son examen est validé.' : 'The candidate has met the required threshold. Exam is validated.'; ?>
                <br>
                <strong style="color:#dc2626;">⛔ AJOURNÉ</strong> —
                <?php echo ($_SESSION['lang']=='fr') ? 'Le candidat n\'a pas atteint le seuil. Il est ajourné et pourra repasser l\'examen.' : 'The candidate did not meet the threshold. They are deferred and may retake the exam.'; ?>
            </p>

            <p style="margin-top:16px;font-style:italic;color:var(--text-muted);font-size:0.82rem;">
                <i class="fas fa-info-circle" style="margin-right:6px;color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr')
                    ? 'Pour toute assistance technique ou question, contactez l\'administration ANAC GABON — Direction de la Sûreté et de la Facilitation.'
                    : 'For any technical assistance or questions, contact ANAC GABON administration — Directorate of Security and Facilitation.'; ?>
            </p>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL : CONTACT
══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="contactModal" onclick="closeModalOutside(event,'contactModal')">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h5><i class="fas fa-envelope" style="color:var(--gold);margin-right:10px;"></i> <?php echo __('contact'); ?></h5>
            <button class="modal-close" onclick="closeModal('contactModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <h6><i class="fas fa-building" style="color:var(--gold);"></i> ANAC GABON</h6>
            <p>BP 2212 — Libreville / Gabon<br>
            <?php echo ($_SESSION['lang']=='fr') ? 'Bureaux : Zone aéroportuaire' : 'Offices: Airport zone'; ?></p>

            <h6><i class="fas fa-phone" style="color:var(--gold);"></i> Téléphone</h6>
            <p>(+241) 11 44 56 54 / 58</p>

            <h6><i class="fas fa-envelope" style="color:var(--gold);"></i> Email</h6>
            <p>contact@anac-gabon.com</p>

            <h6><i class="fas fa-headset" style="color:var(--gold);"></i>
                <?php echo ($_SESSION['lang']=='fr') ? 'Support technique EXASUR' : 'EXASUR technical support'; ?>
            </h6>
            <p>rufin.mbadinga@anac-gabon.com</p>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════ -->
<script>
    /* ── Toggle nav mobile ───────────────────────────────────── */
    function toggleNav() {
        document.getElementById('navLinks').classList.toggle('open');
    }

    /* ── Modals CSS natifs ───────────────────────────────────── */
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = '';
    }
    function closeModalOutside(e, id) {
        if (e.target.classList.contains('modal-overlay')) closeModal(id);
    }
    /* Touche Échap */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show')
                    .forEach(m => closeModal(m.id));
        }
    });

    /* ── Alerte session indisponible ─────────────────────────── */
    function noSession(code) {
        Swal.fire({
            icon: 'info',
            title: '📅 Aucune session disponible',
            html: `<p>Aucune session d\'examen <strong>${code}</strong> n\'est actuellement planifiée.</p>
                   <p style="color:#6b7a99;font-size:.9rem;margin-top:8px;">Veuillez contacter l\'administration ANAC ou revenir plus tard.</p>`,
            confirmButtonColor: '#03224c',
            confirmButtonText: '<i class="fas fa-home me-2"></i>Retour à l\'accueil',
        });
    }

    /* ── Scroll reveal ───────────────────────────────────────── */
    const revealObs = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('visible'), i * 80);
                revealObs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

    /* ══════════════════════════════════════════════════════════
       CONSULTER MA NOTE
       — Code 4 chiffres (codeserv AGFAC-DU)
       — Filtre par date début/fin de session + type examen
    ══════════════════════════════════════════════════════════ */
    function checkNote() {
        const lang = '<?php echo $_SESSION['lang']; ?>';
        const fr   = lang === 'fr';

        Swal.fire({
            title: '📋 ' + (fr ? 'Consulter ma note' : 'Check my score'),
            background: '#ffffff',
            width: 520,
            html: `
                <div style="text-align:left;font-family:'Candara','Calibri',sans-serif;">

                    <div style="margin-bottom:14px;">
                        <label style="font-weight:700;color:#03224c;display:block;margin-bottom:6px;font-size:.9rem;">
                            <i class="fas fa-id-badge" style="color:#D4AF37;margin-right:6px;"></i>
                            ${fr ? 'Code d\'accès (4 chiffres)' : 'Access code (4 digits)'}
                        </label>
                        <input type="text" id="checkCode" class="swal2-input"
                            placeholder="${fr ? 'Votre code à 4 chiffres' : 'Your 4-digit code'}"
                            maxlength="4" pattern="\\d{4}" inputmode="numeric"
                            style="width:100%;border-radius:12px;border:2px solid #e0e4ef;">
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="font-weight:700;color:#03224c;display:block;margin-bottom:6px;font-size:.9rem;">
                            <i class="fas fa-file-alt" style="color:#D4AF37;margin-right:6px;"></i>
                            ${fr ? 'Type d\'examen' : 'Exam type'}
                        </label>
                        <select id="checkType" class="swal2-input"
                            style="width:100%;border-radius:12px;border:2px solid #e0e4ef;">
                            <option value="">${fr ? '-- Choisir le type --' : '-- Choose type --'}</option>
                            <option value="1">AS — ${fr ? 'Agent de Sûreté' : 'Security Agent'}</option>
                            <option value="2">IF — ${fr ? 'Agent Inspection Filtrage' : 'Inspection Filtration Agent'}</option>
                            <option value="3">INST — ${fr ? 'Instructeur AVSEC' : 'AVSEC Instructor'}</option>
                            <option value="4">SENS — ${fr ? 'Sensibilisation Sûreté' : 'Security Awareness'}</option>
                            <option value="5">FORM — ${fr ? 'Évaluation de Formation' : 'Training Assessment'}</option>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:4px;">
                        <div>
                            <label style="font-weight:700;color:#03224c;display:block;margin-bottom:6px;font-size:.9rem;">
                                <i class="fas fa-calendar-alt" style="color:#D4AF37;margin-right:6px;"></i>
                                ${fr ? 'Date début session' : 'Session start date'}
                            </label>
                            <input type="date" id="checkDateDeb"
                                style="width:100%;border-radius:12px;border:2px solid #e0e4ef;padding:10px 14px;font-family:inherit;font-size:.9rem;">
                        </div>
                        <div>
                            <label style="font-weight:700;color:#03224c;display:block;margin-bottom:6px;font-size:.9rem;">
                                <i class="fas fa-calendar-check" style="color:#D4AF37;margin-right:6px;"></i>
                                ${fr ? 'Date fin session' : 'Session end date'}
                            </label>
                            <input type="date" id="checkDateFin"
                                style="width:100%;border-radius:12px;border:2px solid #e0e4ef;padding:10px 14px;font-family:inherit;font-size:.9rem;">
                        </div>
                    </div>
                    <p style="font-size:.76rem;color:#9ca3af;margin-top:6px;">
                        <i class="fas fa-info-circle" style="color:#D4AF37;margin-right:4px;"></i>
                        ${fr ? 'Les dates sont optionnelles (affine la recherche si vous avez passé plusieurs examens).' : 'Dates are optional (refines search if you took multiple exams).'}
                    </p>
                </div>`,
            showCancelButton: true,
            confirmButtonText: `<i class="fas fa-search"></i> ${fr ? 'Vérifier' : 'Check'}`,
            confirmButtonColor: '#03224c',
            cancelButtonColor: '#6c757d',
            cancelButtonText: fr ? 'Annuler' : 'Cancel',
            preConfirm: () => {
                const code    = document.getElementById('checkCode').value.trim();
                const type    = document.getElementById('checkType').value;
                const dateDeb = document.getElementById('checkDateDeb').value;
                const dateFin = document.getElementById('checkDateFin').value;

                if (!code) {
                    Swal.showValidationMessage(fr ? 'Veuillez entrer votre code d\'accès.' : 'Please enter your access code.');
                    return false;
                }
                if (!/^\d{4}$/.test(code)) {
                    Swal.showValidationMessage(fr ? 'Le code doit comporter exactement 4 chiffres.' : 'Code must be exactly 4 digits.');
                    return false;
                }
                if (!type) {
                    Swal.showValidationMessage(fr ? 'Veuillez sélectionner le type d\'examen.' : 'Please select exam type.');
                    return false;
                }
                return { code, type, date_deb: dateDeb, date_fin: dateFin };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                /* Afficher loader */
                Swal.fire({
                    title: fr ? 'Recherche en cours…' : 'Searching…',
                    html: `<i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#03224c;"></i>`,
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                fetch('ANAC/candidat/check_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(result.value)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        /* Mention VALIDÉ ou AJOURNÉ */
                        const valide  = data.reussite;
                        const mention = valide
                            ? (fr ? '✅ VALIDÉ' : '✅ VALIDATED')
                            : (fr ? '⛔ AJOURNÉ' : '⛔ DEFERRED');
                        const color   = valide ? '#16a34a' : '#dc2626';
                        const bg      = valide ? '#dcfce7' : '#fee2e2';

                        Swal.fire({
                            title: mention,
                            html: `
                                <div style="text-align:left;font-family:'Candara','Calibri',sans-serif;">
                                    <p style="font-size:1.1rem;font-weight:700;color:#03224c;margin-bottom:4px;">
                                        ${data.nom} ${data.prenom}
                                    </p>
                                    <p style="color:#9ca3af;font-size:.85rem;margin-bottom:12px;">
                                        ${fr ? 'Code' : 'Code'}: <strong>${data.code}</strong>
                                        ${data.session ? ' · Session : ' + data.session : ''}
                                    </p>
                                    <hr style="margin:12px 0;">
                                    <div style="text-align:center;padding:20px;background:${bg};border-radius:14px;margin-bottom:14px;">
                                        <div style="font-size:3rem;font-weight:900;color:${color};line-height:1;">
                                            ${data.note}
                                            <span style="font-size:1.4rem;color:#9ca3af;">/ ${data.note_sur}</span>
                                        </div>
                                        <div style="font-size:2rem;font-weight:800;color:${color};margin:4px 0;">
                                            ${data.pourcentage}%
                                        </div>
                                        <div style="font-size:1.4rem;margin-top:6px;font-weight:700;color:${color};">
                                            ${mention}
                                        </div>
                                    </div>
                                    <p style="font-size:.8rem;color:#9ca3af;text-align:center;">
                                        <i class="fas fa-calendar me-1"></i>${data.date}
                                    </p>
                                </div>`,
                            icon: valide ? 'success' : 'error',
                            confirmButtonColor: '#03224c',
                            confirmButtonText: fr ? 'Fermer' : 'Close',
                            background: '#ffffff'
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: fr ? 'Note non disponible' : 'Score not available',
                            text: data.message || (fr ? 'Aucun résultat trouvé pour ces critères.' : 'No result found for these criteria.'),
                            confirmButtonColor: '#03224c'
                        });
                    }
                })
                .catch(() => Swal.fire({
                    icon: 'error',
                    title: fr ? 'Erreur' : 'Error',
                    text: fr ? 'Impossible de vérifier la note. Réessayez.' : 'Unable to check score. Please retry.',
                    confirmButtonColor: '#03224c'
                }));
            }
        });
    }
</script>
</body>
</html>
<?php $conn->close(); ?>