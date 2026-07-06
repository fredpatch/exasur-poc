<?php
/**
 * _sidebar.php — Navigation admin AIR SECURE ANAC
 * Définir $active_page avant d'inclure.
 *
 * SÉCURITÉ :
 *  - superadmin → voit tous les menus
 *  - admin      → voit UNIQUEMENT les modules où peut_voir = 1
 *                  (table admin_permissions)
 */
if (!isset($active_page)) $active_page = '';

$is_superadmin = (($_SESSION['admin_role'] ?? '') === 'superadmin');

/* ── Charger les permissions de cet admin depuis la BDD ─────── */
$perms_admin = [];
if (!$is_superadmin && isset($_SESSION['admin_id'])) {
    // Connexion si pas déjà ouverte
    if (!isset($conn) || !$conn) {
        include __DIR__ . '/../php/db_connection.php';
    }
    $stmt = $conn->prepare(
        "SELECT module, peut_voir, peut_edit
         FROM admin_permissions
         WHERE idadmin = ? AND peut_voir = 1"
    );
    $stmt->bind_param('i', $_SESSION['admin_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $perms_admin[$row['module']] = $row;
    }
    $stmt->close();
}

/* ── Définition complète des menus ────────────────────────────── */
$menu_all = [
    'dashboard'      => ['icon'=>'fa-tachometer-alt',  'label'=>'Tableau de bord',     'href'=>'dashboard.php'],
    'candidats'      => ['icon'=>'fa-users',            'label'=>'Candidats',           'href'=>'candidats.php'],
    'sessions'       => ['icon'=>'fa-calendar-alt',     'label'=>'Sessions',            'href'=>'sessions.php'],
    'questions'      => ['icon'=>'fa-question-circle',  'label'=>'Questions',           'href'=>'questions.php'],
    'resultats'      => ['icon'=>'fa-chart-bar',        'label'=>'Résultats',           'href'=>'resultats.php'],
    'evaluations'    => ['icon'=>'fa-star',             'label'=>'Évaluations',        'href'=>'evaluations.php'],
    'rapports'       => ['icon'=>'fa-print',            'label'=>'États d\'impression', 'href'=>'rapports.php', 'badge'=>'NEW'],
    'reinitialiser'  => ['icon'=>'fa-undo-alt',         'label'=>'Réinitialiser',       'href'=>'reinitialiser.php'],
    'administrateurs'=> ['icon'=>'fa-user-shield',      'label'=>'Administrateurs',     'href'=>'administrateurs.php', 'badge'=>'SA'],
];

/* ── Filtrer selon le rôle ────────────────────────────────────── */
$menu = [];
foreach ($menu_all as $key => $item) {
    if ($is_superadmin) {
        // Superadmin voit tout
        $menu[$key] = $item;
    } else {
        // Admin : uniquement si peut_voir = 1 dans admin_permissions
        // 'administrateurs' jamais visible pour un simple admin
        if ($key !== 'administrateurs' && isset($perms_admin[$key])) {
            $menu[$key] = $item;
        }
    }
}
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
            <?php
            $badgeBg    = $item['badge'] === 'SA'  ? '#dc2626' : '#FFD700';
            $badgeColor = $item['badge'] === 'SA'  ? '#fff'    : '#03224c';
            $badgeTip   = $item['badge'] === 'SA'  ? 'Super Admin uniquement' : '';
            ?>
            <span style="margin-left:auto;background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;
                         font-size:.6rem;font-weight:800;padding:2px 6px;border-radius:20px;
                         letter-spacing:.3px;" title="<?= $badgeTip ?>">
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