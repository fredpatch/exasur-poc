<?php
/**
 * resultat_module.php — Page intermédiaire FORM
 * Affiche : note du module terminé + récap de toutes les sessions du cours
 * + sessions restantes à passer
 */
include '../php/db_connection.php';
include '../lang/lang_loader.php';
if (session_status()===PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['form_module_result'])) {
    header("Location: ../../index.php"); exit();
}
$d     = $_SESSION['form_module_result'];
$mod   = $d['module_termine'];
$recap = $d['recap_sessions'];
$lang  = $_SESSION['lang'] ?? 'fr';
$nom_conteneur = $d['nom_conteneur'] ?? ''; /* Session FORM parente — titre uniquement */

// Séparer les sessions passées et restantes
// La session conteneur (idmodule IS NULL) est déjà exclue du recap (filtrée dans soumettre_examen.php)
$sessions_done = array_filter($recap, fn($s) => $s['done']);
$sessions_todo = array_filter($recap, fn($s) => !$s['done']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ANAC — Résultat Module <?= $mod['num'] ?></title>
    <link rel="icon" href="../assets/images/LOGOANAC.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root{--b:#03224c;--g:#D4AF37;}
        body{font-family:'Nunito','Candara',sans-serif;background:linear-gradient(135deg,#f0f4ff,#e8ecf5);min-height:100vh;display:flex;align-items:center;padding:20px;}
        .wrap{max-width:720px;margin:0 auto;width:100%;}
        .logo-box{text-align:center;margin-bottom:18px;}
        .logo-box img{max-height:86px;background:#fff;padding:10px 18px;border-radius:16px;box-shadow:0 6px 20px rgba(3,34,76,.15);}
        .card-r{border:none;border-radius:26px;box-shadow:0 20px 50px rgba(3,34,76,.22);overflow:hidden;animation:su .5s ease both;}
        @keyframes su{from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:none;}}
        .hdr{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;padding:22px 26px;border-bottom:4px solid var(--g);}
        .hdr h2{margin:0;font-weight:800;font-size:1.35rem;}
        .hdr .cand{opacity:.82;font-size:.88rem;margin-top:5px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
        .bdy{padding:28px 30px;background:#fff;}
        /* Score du module */
        .mod-result{display:flex;align-items:center;gap:24px;background:linear-gradient(135deg,#f8faff,#eef2fc);border-radius:18px;padding:20px 22px;margin-bottom:22px;border-left:5px solid var(--g);}
        .circle-sm{width:110px;height:110px;border-radius:50%;display:flex;flex-direction:column;justify-content:center;align-items:center;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.22);flex-shrink:0;}
        .cn{font-size:1.9rem;font-weight:900;line-height:1;} .cs{font-size:.8rem;opacity:.88;} .cp{font-size:1.05rem;font-weight:800;margin-top:2px;}
        .mod-info h5{color:var(--b);font-weight:800;margin-bottom:6px;}
        .mod-info .mention{font-size:1.1rem;font-weight:800;}
        /* Barre */
        .bar-w{background:#e8ecf5;border-radius:50px;height:10px;overflow:hidden;margin:4px 0;}
        .bar-f{height:100%;border-radius:50px;transition:width .8s ease;}
        /* Tableau récap */
        .recap-tbl{width:100%;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden;font-size:.86rem;margin:10px 0;}
        .recap-tbl thead th{background:var(--b);color:var(--g);padding:8px 12px;text-align:center;font-weight:700;}
        .recap-tbl thead th:first-child{text-align:left;}
        .recap-tbl tbody td{padding:8px 12px;border-bottom:1px solid #eef0f5;vertical-align:middle;text-align:center;}
        .recap-tbl tbody td:first-child{text-align:left;}
        .recap-tbl tbody tr.current-row td{background:#fffde7;font-weight:700;}
        .recap-tbl tbody tr:last-child td{border-bottom:none;}
        .tok{background:#d1fae5;color:#065f46;border-radius:50px;padding:2px 10px;font-weight:700;font-size:.76rem;}
        .tfail{background:#fee2e2;color:#b91c1c;border-radius:50px;padding:2px 10px;font-weight:700;font-size:.76rem;}
        .tpend{background:#e0f2fe;color:#0369a1;border-radius:50px;padding:2px 10px;font-weight:700;font-size:.76rem;}
        /* Sessions restantes */
        .todo-block{background:#f0f7ff;border:1.5px solid #93c5fd;border-radius:14px;padding:16px 18px;margin-top:16px;}
        .todo-block h6{color:var(--b);font-weight:800;margin-bottom:10px;}
        .todo-item{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px dashed #bfdbfe;font-size:.88rem;}
        .todo-item:last-child{border-bottom:none;}
        .todo-num{width:30px;height:30px;border-radius:50%;background:var(--b);color:var(--g);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;flex-shrink:0;}
        /* Moyenne courante */
        .moy-box{background:linear-gradient(135deg,#fffbef,#fff8e1);border:1.5px solid var(--g);border-radius:14px;padding:14px 18px;margin-top:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
        .moy-label{font-weight:700;color:var(--b);font-size:.9rem;}
        .moy-val{font-size:1.5rem;font-weight:900;}
        /* Bouton retour */
        .btn-a{background:linear-gradient(135deg,var(--b),#0a3a6b);color:#fff;border:2px solid var(--g);padding:10px 26px;border-radius:50px;font-weight:700;font-size:.93rem;text-decoration:none;display:inline-block;margin:5px;transition:all .3s;}
        .btn-a:hover{transform:translateY(-3px);box-shadow:0 8px 18px rgba(3,34,76,.3);color:#fff;}
    </style>
</head>
<body>
<div class="container"><div class="wrap">
<div class="logo-box"><img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC"></div>

<div class="card-r">
<div class="hdr">
    <h2><i class="fas fa-clipboard-check me-2"></i>Résultat — Module <?= $mod['num'] ?></h2>
    <div class="cand">
        <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($d['nom']) ?></span>
        <span><i class="fas fa-key me-1"></i>Code : <strong><?= htmlspecialchars($d['code']) ?></strong></span>
        <span style="background:rgba(212,175,55,.25);padding:3px 12px;border-radius:20px;color:#D4AF37;font-weight:700;">
            <?= htmlspecialchars($d['type_code'].' — '.$d['type_nom']) ?>
        </span>
    </div>
</div>

<div class="bdy">

    <!-- ══ Score du module terminé ══ -->
    <div class="mod-result">
        <div class="circle-sm" style="background:linear-gradient(135deg,<?= $mod['reussite']?'#28a745,#20c997':'#dc3545,#c82333' ?>);">
            <div class="cn"><?= $mod['note'] ?></div>
            <div class="cs">/ <?= $mod['sur'] ?> pts</div>
            <div class="cp"><?= $mod['pct'] ?>%</div>
        </div>
        <div class="mod-info">
            <h5><i class="fas fa-book me-2" style="color:var(--g);"></i>Module <?= $mod['num'] ?> terminé</h5>
            <p style="color:#5a6380;font-size:.9rem;margin-bottom:6px;">
                <?= htmlspecialchars($mod['nom'] ?? 'Module') ?>
            </p>
            <div class="bar-w" style="max-width:250px;">
                <div class="bar-f" style="width:<?= min($mod['pct'],100) ?>%;background:<?= $mod['reussite']?'#28a745':'#dc3545' ?>;"></div>
            </div>
            <div class="mention mt-2" style="color:<?= $mod['reussite']?'#28a745':'#dc3545' ?>">
                <?= $mod['reussite']?'✅ Objectif atteint (≥'.$d['seuil'].'%)':'❌ Objectif non atteint (<'.$d['seuil'].'%)' ?>
            </div>
        </div>
    </div>

    <!-- ══ Récapitulatif de toutes les sessions du cours ══ -->
    <?php if($nom_conteneur): ?>
    <div style="background:linear-gradient(135deg,#fce7f3,#fdf2f8);border:1.5px solid #f9a8d4;border-radius:12px;padding:11px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-graduation-cap" style="color:#9d174d;font-size:1rem;flex-shrink:0;"></i>
        <div>
            <div style="font-size:.72rem;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:1px;">Session de formation</div>
            <div style="font-weight:700;color:#831843;font-size:.88rem;"><?= htmlspecialchars($nom_conteneur) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <h6 style="color:var(--b);font-weight:800;margin-bottom:8px;">
        <i class="fas fa-list-check me-2" style="color:var(--g);"></i>
        Avancement — <?= $d['nb_passees'] ?>/<?= $d['nb_total'] ?> évaluation(s) passée(s)
    </h6>
    <table class="recap-tbl">
        <thead>
            <tr>
                <th>Session / Module</th>
                <th>Note</th>
                <th>Score</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recap as $sess): ?>
        <tr class="<?= $sess['courante']?'current-row':'' ?>">
            <td>
                <?php if ($sess['num_module']): ?>
                    <strong>Module <?= $sess['num_module'] ?></strong>
                    <?php if ($sess['courante']): ?><span style="color:var(--g);font-size:.78rem;"> ← en cours</span><?php endif; ?>
                    <br><small class="text-muted"><?= htmlspecialchars($sess['nom_module']??'') ?></small>
                <?php else: ?>
                    <?= htmlspecialchars($sess['nom_session']) ?>
                <?php endif; ?>
            </td>
            <td>
                <?= $sess['done'] ? $sess['note'].'/'.$sess['sur'].' pts' : '—' ?>
            </td>
            <td>
                <?php if($sess['done']): ?>
                    <?= $sess['pct'] ?>%
                    <div class="bar-w"><div class="bar-f" style="width:<?= min($sess['pct'],100) ?>%;background:<?= $sess['reussite']?'#28a745':'#dc3545' ?>;"></div></div>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if($sess['done']): ?>
                    <?= $sess['reussite']?'<span class="tok">✓ OK</span>':'<span class="tfail">✗</span>' ?>
                <?php else: ?>
                    <span class="tpend">⏳ À venir</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ══ Moyenne courante ou FINALE ══ -->
    <?php if ($d['nb_passees'] > 0):
        $is_final = empty($sessions_todo); /* Plus aucun module à venir → résultat final */
        $moy = $d['moyenne_courante'];
        $seuil = $d['seuil'];
        $reussite_finale = ($is_final && $moy >= $seuil);
        $echec_final     = ($is_final && $moy < $seuil);
    ?>
    <div class="moy-box" style="<?= $is_final?'border-width:2.5px;border-color:'.($reussite_finale?'#28a745':'#dc2626').';background:'.($reussite_finale?'linear-gradient(135deg,#f0fdf4,#dcfce7)':'linear-gradient(135deg,#fff1f2,#fee2e2)').';':'' ?>">
        <div>
            <div class="moy-label" style="color:<?= $is_final?($reussite_finale?'#15803d':'#b91c1c'):'var(--b)' ?>">
                <i class="fas fa-<?= $is_final?'flag-checkered':'calculator' ?> me-2" style="color:<?= $is_final?($reussite_finale?'#28a745':'#dc2626'):'var(--g)' ?>;"></i>
                <?php if($is_final): ?>
                    Résultat FINAL — <?= $d['nb_passees'] ?> module<?= $d['nb_passees']>1?'s':'' ?> évalué<?= $d['nb_passees']>1?'s':'' ?>
                <?php else: ?>
                    Moyenne provisoire (<?= $d['nb_passees'] ?>/<?= $d['nb_total'] ?> module<?= $d['nb_passees']>1?'s':'' ?>)
                <?php endif; ?>
            </div>
            <?php if($is_final): ?>
            <div style="font-size:.88rem;font-weight:700;margin-top:4px;color:<?= $reussite_finale?'#15803d':'#b91c1c' ?>">
                <?= $reussite_finale
                    ? '✅ Formation validée — Moyenne ≥ '.$seuil.'%'
                    : '❌ Formation non validée — Moyenne < '.$seuil.'%' ?>
            </div>
            <?php else: ?>
            <div style="color:#666;font-size:.82rem;margin-top:2px;">
                <?= count($sessions_todo) ?> module<?= count($sessions_todo)>1?'s':'' ?> restant<?= count($sessions_todo)>1?'s':'' ?> — la note finale sera calculée à l'issue de toutes les évaluations.
            </div>
            <?php endif; ?>
        </div>
        <div class="moy-val" style="color:<?= $is_final?($reussite_finale?'#28a745':'#dc2626'):'var(--b)' ?>">
            <?= $moy ?>%
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ Modules restants À VENIR (seulement si pas terminé) ══ -->
    <?php if (!empty($sessions_todo)): ?>
    <div class="todo-block">
        <h6>
            <i class="fas fa-calendar-alt me-2" style="color:#3b82f6;"></i>
            <?= count($sessions_todo) ?> évaluation(s) restante(s) pour ce cours
        </h6>
        <?php foreach ($sessions_todo as $todo): ?>
        <div class="todo-item">
            <div class="todo-num"><?= $todo['num_module']?:'?' ?></div>
            <div>
                <strong>
                    <?= $todo['num_module']?'Module '.$todo['num_module'].' — ':'Session — ' ?>
                    <?= htmlspecialchars($todo['nom_module']??$todo['nom_session']) ?>
                </strong>
                <br><small class="text-muted">
                    <i class="fas fa-clock me-1" style="color:#3b82f6;"></i>
                    En attente de planification par l'administration.
                </small>
            </div>
        </div>
        <?php endforeach; ?>
        <!-- Message clair pour le candidat -->
        <div style="background:#fff;border-radius:9px;padding:10px 13px;margin-top:10px;font-size:.82rem;color:#374151;border:1px solid #bfdbfe;">
            <i class="fas fa-info-circle me-1" style="color:#3b82f6;"></i>
            <strong>Que faire maintenant ?</strong><br>
            Revenez sur la page d'accueil. Quand l'administration planifiera la prochaine évaluation,
            vous recevrez vos identifiants pour vous connecter et passer le module suivant.
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ Évaluation de l'expérience — inline, sans rechargement ══ -->
    <div id="evalBox" style="background:linear-gradient(135deg,#fffbef,#fff8e1);border:1.5px solid var(--g);border-radius:14px;padding:18px 20px;margin-top:18px;text-align:center;">
        <div style="font-weight:800;color:var(--b);font-size:.95rem;margin-bottom:4px;">
            <i class="fas fa-star me-1" style="color:var(--g);"></i>Évaluez votre expérience
        </div>
        <div style="font-size:.82rem;color:#6b7280;margin-bottom:14px;">
            Comment s'est passée cette session d'évaluation ?
        </div>
        <!-- 3 boutons de notation -->
        <div id="ratingBtns" style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
            <button onclick="sendEval('satisfait')" class="eval-btn" id="btn-satisfait"
                style="background:#dcfce7;color:#15803d;border:2px solid #86efac;border-radius:50px;padding:10px 22px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;">
                😊 Satisfait
            </button>
            <button onclick="sendEval('moyen')" class="eval-btn" id="btn-moyen"
                style="background:#fef3c7;color:#92400e;border:2px solid #fcd34d;border-radius:50px;padding:10px 22px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;">
                😐 Moyen
            </button>
            <button onclick="sendEval('insatisfait')" class="eval-btn" id="btn-insatisfait"
                style="background:#fee2e2;color:#b91c1c;border:2px solid #fca5a5;border-radius:50px;padding:10px 22px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;">
                😞 Insatisfait
            </button>
        </div>
        <div id="evalMsg" style="display:none;font-weight:700;font-size:.88rem;margin-top:10px;"></div>
    </div>

    <!-- ══ Bouton retour ══ -->
    <div class="text-center mt-4">
        <a href="../../index.php" class="btn-a">
            <i class="fas fa-home me-2"></i>Retour à l'accueil
        </a>
    </div>

<script>
function sendEval(rating) {
    /* Désactiver tous les boutons pendant l'envoi */
    document.querySelectorAll('.eval-btn').forEach(b => {
        b.disabled = true;
        b.style.opacity = '.5';
        b.style.cursor  = 'not-allowed';
    });
    /* Mettre en évidence le bouton choisi */
    const chosen = document.getElementById('btn-'+rating);
    if (chosen) { chosen.style.opacity='1'; chosen.style.transform='scale(1.08)'; }

    fetch('save_evaluation.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({rating})
    })
    .then(r => r.json())
    .then(d => {
        const icons = {satisfait:'😊',moyen:'😐',insatisfait:'😞'};
        const colors= {satisfait:'#15803d',moyen:'#92400e',insatisfait:'#b91c1c'};
        const msg = document.getElementById('evalMsg');
        msg.style.color   = colors[rating]||'#03224c';
        msg.innerHTML     = icons[rating]+' Merci pour votre retour ! Votre évaluation a été enregistrée.';
        msg.style.display = 'block';
        /* Masquer les boutons */
        document.getElementById('ratingBtns').style.display='none';
    })
    .catch(() => {
        const msg = document.getElementById('evalMsg');
        msg.style.color   = '#dc2626';
        msg.innerHTML     = '❌ Une erreur est survenue. Veuillez réessayer.';
        msg.style.display = 'block';
        /* Réactiver les boutons */
        document.querySelectorAll('.eval-btn').forEach(b=>{ b.disabled=false; b.style.opacity='1'; b.style.cursor='pointer'; b.style.transform=''; });
    });
}
</script>

</div><!-- /bdy -->
</div><!-- /card-r -->

<footer class="text-center mt-4 text-muted small">
    <p>&copy; <?= date('Y') ?> ANAC GABON &ndash; EXASUR</p>
</footer>
</div></div>
</body>
</html>
<?php unset($_SESSION['form_module_result']); if(isset($conn))$conn->close(); ?>