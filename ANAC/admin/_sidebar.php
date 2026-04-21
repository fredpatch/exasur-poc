<?php
/**
 * _sidebar.php — Navigation admin AIR SECURE ANAC
 * Définir $active_page avant d'inclure
 */
if (!isset($active_page)) $active_page = '';
$menu = [
    'dashboard'     => ['icon'=>'fa-tachometer-alt',    'label'=>'Tableau de bord',      'href'=>'dashboard.php'],
    'candidats'     => ['icon'=>'fa-users',              'label'=>'Candidats',            'href'=>'candidats.php'],
    'sessions'      => ['icon'=>'fa-calendar-alt',       'label'=>'Sessions',             'href'=>'sessions.php'],
    'questions'     => ['icon'=>'fa-question-circle',    'label'=>'Questions',            'href'=>'questions.php'],
    'resultats'     => ['icon'=>'fa-chart-bar',          'label'=>'Résultats',            'href'=>'resultats.php'],
    'evaluations'   => ['icon'=>'fa-star',               'label'=>'Évaluations',         'href'=>'evaluations.php'],
    'rapports'      => ['icon'=>'fa-file-chart-column',  'label'=>'États d\'impression',  'href'=>'rapports.php', 'badge'=>'NEW'],
    'reinitialiser' => ['icon'=>'fa-undo-alt',           'label'=>'Réinitialiser',        'href'=>'reinitialiser.php'],
];
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/Logo-ANAC-CERTIFICATION.png" alt="ANAC" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <span class="brand-title">AIR SECURE</span>
            <span class="brand-sub">Administration</span>
        </div>
    </div>
    <div class="sidebar-admin-info">
        <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
        <div class="admin-name-wrap">
            <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_nom']??'Admin') ?></span>
            <span class="admin-role"><?= ucfirst($_SESSION['admin_role']??'admin') ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $key => $item): ?>
        <a href="<?= $item['href'] ?>" class="sidebar-link <?= $active_page===$key?'active':'' ?>">
            <span class="link-icon"><i class="fas <?= $item['icon'] ?>"></i></span>
            <span class="link-label"><?= $item['label'] ?></span>
            <?php if (!empty($item['badge'])): ?>
            <span style="margin-left:auto;background:#FFD700;color:#03224c;font-size:.6rem;font-weight:800;
                         padding:2px 6px;border-radius:20px;letter-spacing:.3px;">
                <?= $item['badge'] ?>
            </span>
            <?php endif; ?>
            <?php if ($active_page===$key): ?><span class="link-indicator"></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a>
        <p class="sidebar-copy">© <?= date('Y') ?> ANAC GABON</p>
    </div>
</aside>