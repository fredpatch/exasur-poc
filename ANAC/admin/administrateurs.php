<?php
/**
 * administrateurs.php — Gestion des administrateurs EXASUR ANAC
 * admin/administrateurs.php
 *
 * - CRUD complet (créer, modifier, désactiver)
 * - Affectation des modules par admin (habilitations)
 * - superadmin = accès total à tout
 * - admin = accès restreint aux modules assignés
 *
 * Modules disponibles :
 *   dashboard | candidats | sessions | questions
 *   resultats | evaluations | reinitialiser | administrateurs
 *
 * Sécurité : seul le superadmin peut accéder à cette page
 */
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
include '../php/db_connection.php';

/* ── Seul le superadmin a accès ───────────────────────────── */
$me = $conn->query("SELECT role FROM administrateurs WHERE idadmin={$_SESSION['admin_id']}")->fetch_assoc();
if (!$me || $me['role'] !== 'superadmin') {
    header("Location: dashboard.php"); exit();
}

$msg = '';

/* ── MODULES disponibles ──────────────────────────────────── */
$MODULES = [
    'dashboard'      => ['icon' => 'fa-tachometer-alt', 'label' => 'Tableau de bord'],
    'candidats'      => ['icon' => 'fa-users',           'label' => 'Candidats'],
    'sessions'       => ['icon' => 'fa-calendar-alt',    'label' => 'Sessions'],
    'questions'      => ['icon' => 'fa-question-circle', 'label' => 'Questions'],
    'resultats'      => ['icon' => 'fa-chart-bar',       'label' => 'Résultats'],
    'evaluations'    => ['icon' => 'fa-star',             'label' => 'Évaluations'],
    'reinitialiser'  => ['icon' => 'fa-undo-alt',         'label' => 'Réinitialiser'],
    'administrateurs'=> ['icon' => 'fa-user-shield',      'label' => 'Administrateurs'],
];

/* ── POST : Créer / Modifier admin ───────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $idadmin  = intval($_POST['idadmin'] ?? 0);

    if ($action === 'save') {
        $nom       = trim($_POST['nom']        ?? '');
        $prenom    = trim($_POST['prenom']     ?? '');
        $code      = strtoupper(trim($_POST['code_acces'] ?? ''));
        $email     = trim($_POST['email']      ?? '');
        $role      = in_array($_POST['role']??'', ['superadmin','admin']) ? $_POST['role'] : 'admin';
        $mdp_plain = trim($_POST['mot_de_passe'] ?? '');
        $perms     = $_POST['perms'] ?? []; // array de modules cochés
        $peut_edit = $_POST['peut_edit'] ?? [];

        if (!$nom || !$prenom || !$code) {
            $msg = 'error:Nom, prénom et code sont obligatoires.';
        } else {
            if ($idadmin) {
                /* ── Modifier ── */
                if ($mdp_plain) {
                    $mdp_hash = password_hash($mdp_plain, PASSWORD_DEFAULT);
                    $s = $conn->prepare("UPDATE administrateurs SET nom=?,prenom=?,code_acces=?,email=?,role=?,mot_de_passe=?,actif=1 WHERE idadmin=?");
                    $s->bind_param("ssssssi",$nom,$prenom,$code,$email,$role,$mdp_hash,$idadmin);
                } else {
                    $s = $conn->prepare("UPDATE administrateurs SET nom=?,prenom=?,code_acces=?,email=?,role=? WHERE idadmin=?");
                    $s->bind_param("sssssi",$nom,$prenom,$code,$email,$role,$idadmin);
                }
                $s->execute(); $s->close();
                $msg = "ok:Admin mis à jour avec succès.";
            } else {
                /* ── Créer ── */
                if (!$mdp_plain) { $msg = 'error:Le mot de passe est requis pour un nouvel admin.'; }
                else {
                    $mdp_hash = password_hash($mdp_plain, PASSWORD_DEFAULT);
                    $chk = $conn->prepare("SELECT idadmin FROM administrateurs WHERE code_acces=?");
                    $chk->bind_param("s",$code); $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $msg = 'error:Ce code d\'accès est déjà utilisé.';
                    } else {
                        $chk->close();
                        $s = $conn->prepare("INSERT INTO administrateurs (nom,prenom,code_acces,email,mot_de_passe,role,actif) VALUES (?,?,?,?,?,?,1)");
                        $s->bind_param("ssssss",$nom,$prenom,$code,$email,$mdp_hash,$role);
                        $s->execute(); $idadmin = $conn->insert_id; $s->close();
                        $msg = "ok:Nouvel administrateur créé.";
                    }
                }
            }

            /* ── Sauvegarder les permissions (seulement si admin, pas superadmin) ── */
            if ($idadmin && $role !== 'superadmin' && !str_starts_with($msg,'error')) {
                $conn->query("DELETE FROM admin_permissions WHERE idadmin=$idadmin");
                foreach ($MODULES as $mod => $info) {
                    $pv = in_array($mod, $perms) ? 1 : 0;
                    $pe = ($pv && in_array($mod, $peut_edit)) ? 1 : 0;
                    $ins = $conn->prepare("INSERT INTO admin_permissions (idadmin,module,peut_voir,peut_edit) VALUES (?,?,?,?)");
                    $ins->bind_param("isii",$idadmin,$mod,$pv,$pe); $ins->execute(); $ins->close();
                }
            }
        }
    }

    if ($action === 'toggle') {
        if ($idadmin != $_SESSION['admin_id']) { // pas se désactiver soi-même
            $actif = intval($_POST['actif'] ?? 0);
            $conn->query("UPDATE administrateurs SET actif=$actif WHERE idadmin=$idadmin");
            $msg = "ok:Statut mis à jour.";
        }
    }
}

/* ── Liste des admins ─────────────────────────────────────── */
$admins = $conn->query("SELECT * FROM administrateurs ORDER BY role DESC, nom ASC");

/* ── Permissions de chaque admin ─────────────────────────── */
$all_perms = [];
$pr = $conn->query("SELECT idadmin, module, peut_voir, peut_edit FROM admin_permissions");
while ($p = $pr->fetch_assoc()) {
    $all_perms[$p['idadmin']][$p['module']] = $p;
}

$active_page = 'administrateurs';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administrateurs — EXASUR ANAC</title>
<link rel="icon" href="../assets/images/faviconLOGOANAC.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="admin_shared.css">
<style>
.role-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:50px;font-size:.75rem;font-weight:700;}
.rb-super{background:linear-gradient(135deg,var(--gold),#f0c040);color:var(--blue);}
.rb-admin{background:#dbeafe;color:#1e40af;}
.perms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:10px;}
.perm-card{
    background:#f8faff;border:1.5px solid #e0e7f0;border-radius:10px;
    padding:10px 14px;display:flex;flex-direction:column;gap:6px;
}
.perm-card.active{border-color:var(--blue);background:#f0f4ff;}
.perm-card .perm-title{font-weight:700;color:var(--blue);font-size:.85rem;display:flex;align-items:center;gap:7px;}
.perm-checkboxes{display:flex;gap:12px;font-size:.78rem;}
.perm-check{display:flex;align-items:center;gap:5px;cursor:pointer;}
.perm-check input{accent-color:var(--blue);cursor:pointer;}
.modal-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.55);z-index:2000;
    align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.show{display:flex;}
.modal-box{
    background:#fff;border-radius:20px;
    max-width:660px;width:100%;
    max-height:90vh;overflow-y:auto;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.modal-head{
    background:linear-gradient(135deg,var(--blue),#0a3a6b);
    color:#fff;padding:18px 24px;border-radius:20px 20px 0 0;
    display:flex;align-items:center;justify-content:space-between;
}
.modal-body{padding:24px;}
.modal-foot{padding:14px 24px;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end;}
.f-label{font-size:.78rem;font-weight:700;color:var(--blue);display:block;margin-bottom:4px;}
.f-inp{
    width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;
    font-family:inherit;font-size:.88rem;transition:border-color .2s;
}
.f-inp:focus{outline:none;border-color:var(--blue);}
.perms-section{background:#f4f7fc;border-radius:12px;padding:14px;margin-top:8px;}
.perms-section h6{color:var(--blue);font-weight:800;margin-bottom:10px;font-size:.88rem;}
</style>
</head>
<body>
<div class="admin-layout">
<?php include '_sidebar.php'; ?>
<main class="admin-main">

<div class="admin-topbar">
    <button class="sidebar-toggle" id="st"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><i class="fas fa-user-shield me-2"></i>Administrateurs</div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn-anac" style="font-size:.84rem;" onclick="openModal(0)">
            <i class="fas fa-plus me-1"></i>Nouvel admin
        </button>
    </div>
</div>

<div class="admin-content">

<!-- Info superadmin -->
<div class="card-admin mb-4" style="border-left:4px solid var(--gold)">
    <div class="card-admin-body">
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
            <div style="width:46px;height:46px;background:#f9f0c4;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-crown" style="color:var(--gold);font-size:1.1rem;"></i>
            </div>
            <div>
                <div style="font-weight:700;color:var(--blue);margin-bottom:4px;">Règles d'habilitations</div>
                <ul style="margin:0;padding-left:18px;font-size:.86rem;color:#5a6380;line-height:1.7;">
                    <li><strong>Superadmin</strong> : accès total à tous les modules, impossible à restreindre</li>
                    <li><strong>Admin</strong> : accès restreint aux modules sélectionnés uniquement</li>
                    <li>Chaque module peut être en mode <strong>Lecture</strong> (voir) ou <strong>Écriture</strong> (modifier)</li>
                    <li>Un admin sans permission sur un module ne le voit pas dans le menu</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Tableau admins -->
<div class="card-admin">
    <div class="card-admin-header">
        <i class="fas fa-user-shield me-2"></i><h5>Liste des administrateurs</h5>
        <span class="badge-count ms-2"><?= $admins->num_rows ?></span>
    </div>
    <div class="card-admin-body p-0">
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Nom / Prénom</th>
                    <th>Code</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Habilitations</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($adm = $admins->fetch_assoc()):
                $perms_adm = $all_perms[$adm['idadmin']] ?? [];
                $is_super  = ($adm['role'] === 'superadmin');
                $is_me     = ($adm['idadmin'] === intval($_SESSION['admin_id']));
            ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:var(--blue);">
                        <?= htmlspecialchars($adm['nom'].' '.$adm['prenom']) ?>
                        <?php if ($is_me): ?>
                        <span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:50px;font-size:.7rem;margin-left:4px;">Vous</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.75rem;color:#9ca3af;">Créé le <?= date('d/m/Y', strtotime($adm['created_at'])) ?></div>
                </td>
                <td>
                    <code style="background:#f4f7fc;padding:3px 8px;border-radius:6px;font-weight:700;color:var(--blue);">
                        <?= htmlspecialchars($adm['code_acces']) ?>
                    </code>
                </td>
                <td style="font-size:.82rem;"><?= htmlspecialchars($adm['email']??'—') ?></td>
                <td>
                    <?php if ($is_super): ?>
                    <span class="role-badge rb-super"><i class="fas fa-crown"></i> Superadmin</span>
                    <?php else: ?>
                    <span class="role-badge rb-admin"><i class="fas fa-user"></i> Admin</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_super): ?>
                    <span style="font-size:.78rem;color:#16a34a;font-weight:600;">
                        <i class="fas fa-infinity me-1"></i>Accès total
                    </span>
                    <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ($MODULES as $mod => $info):
                            $p = $perms_adm[$mod] ?? ['peut_voir'=>0,'peut_edit'=>0];
                            if (!$p['peut_voir']) continue;
                        ?>
                        <span style="background:#f0f4ff;color:var(--blue);border:1px solid #d0daf0;
                              padding:2px 7px;border-radius:6px;font-size:.7rem;font-weight:700;"
                              title="<?= $p['peut_edit'] ? 'Lecture + Écriture' : 'Lecture seule' ?>">
                            <i class="fas <?= $info['icon'] ?> me-1" style="font-size:.65rem;"></i>
                            <?= $info['label'] ?>
                            <?= $p['peut_edit'] ? '✏️' : '👁' ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if (empty(array_filter($perms_adm, fn($p)=>$p['peut_voir']))): ?>
                        <span style="font-size:.75rem;color:#9ca3af;">Aucune habilitation</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($adm['actif']): ?>
                    <span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:50px;font-size:.73rem;font-weight:700;">
                        <i class="fas fa-check me-1"></i>Actif
                    </span>
                    <?php else: ?>
                    <span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:50px;font-size:.73rem;font-weight:700;">
                        <i class="fas fa-times me-1"></i>Inactif
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn-icon" onclick="openModal(<?= $adm['idadmin'] ?>)" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if (!$is_me): ?>
                        <form method="POST" style="display:inline;" onsubmit="return false;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="idadmin" value="<?= $adm['idadmin'] ?>">
                            <input type="hidden" name="actif" value="<?= $adm['actif'] ? 0 : 1 ?>">
                            <button type="button" class="btn-icon"
                                    style="background:<?= $adm['actif'] ? '#fee2e2' : '#dcfce7' ?>;"
                                    onclick="toggleAdmin(<?= $adm['idadmin'] ?>, <?= $adm['actif'] ?>, '<?= htmlspecialchars($adm['nom'].' '.$adm['prenom']) ?>')"
                                    title="<?= $adm['actif'] ? 'Désactiver' : 'Activer' ?>">
                                <i class="fas <?= $adm['actif'] ? 'fa-ban' : 'fa-check' ?>"
                                   style="color:<?= $adm['actif'] ? '#dc2626' : '#16a34a' ?>;"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</main>
</div>

<!-- ══ MODAL CRÉER / MODIFIER ══ -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div style="font-weight:800;font-size:1rem;" id="modalTitle">Nouvel administrateur</div>
                <div style="font-size:.76rem;opacity:.7;margin-top:2px;">EXASUR — ANAC GABON</div>
            </div>
            <button onclick="closeModal()" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="adminForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="idadmin" id="fIdAdmin" value="0">
            <div class="modal-body">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="f-label">Nom *</label>
                        <input type="text" name="nom" id="fNom" class="f-inp" required placeholder="DUPONT">
                    </div>
                    <div class="col-md-6">
                        <label class="f-label">Prénom *</label>
                        <input type="text" name="prenom" id="fPrenom" class="f-inp" required placeholder="Jean">
                    </div>
                    <div class="col-md-4">
                        <label class="f-label">Code d'accès *</label>
                        <input type="text" name="code_acces" id="fCode" class="f-inp" required placeholder="ADM003">
                    </div>
                    <div class="col-md-4">
                        <label class="f-label">Email</label>
                        <input type="email" name="email" id="fEmail" class="f-inp" placeholder="admin@anac.ga">
                    </div>
                    <div class="col-md-4">
                        <label class="f-label">Rôle *</label>
                        <select name="role" id="fRole" class="f-inp" onchange="togglePermsSection()">
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="f-label">Mot de passe <span id="mdpNote" style="color:#9ca3af;font-weight:400;">(laisser vide pour ne pas changer)</span></label>
                        <input type="password" name="mot_de_passe" id="fMdp" class="f-inp" placeholder="••••••••" autocomplete="new-password">
                    </div>
                </div>

                <!-- Section habilitations (masquée pour superadmin) -->
                <div class="perms-section" id="permsSection">
                    <h6><i class="fas fa-lock me-2" style="color:var(--gold);"></i>Habilitations par module</h6>
                    <div class="perms-grid" id="permsGrid">
                        <?php foreach ($MODULES as $mod => $info): ?>
                        <div class="perm-card" id="pcard-<?= $mod ?>">
                            <div class="perm-title">
                                <i class="fas <?= $info['icon'] ?>" style="color:var(--gold);font-size:.85rem;"></i>
                                <?= $info['label'] ?>
                            </div>
                            <div class="perm-checkboxes">
                                <label class="perm-check">
                                    <input type="checkbox" name="perms[]" value="<?= $mod ?>"
                                           id="pv-<?= $mod ?>"
                                           onchange="updatePermCard('<?= $mod ?>')">
                                    <span>Voir</span>
                                </label>
                                <label class="perm-check">
                                    <input type="checkbox" name="peut_edit[]" value="<?= $mod ?>"
                                           id="pe-<?= $mod ?>" disabled>
                                    <span style="color:#9ca3af;">Modifier</span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:10px;font-size:.75rem;color:#6b7280;">
                        <i class="fas fa-info-circle me-1" style="color:var(--gold);"></i>
                        Cochez "Voir" pour autoriser l'accès au module. Cochez "Modifier" pour autoriser les actions d'écriture.
                    </div>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" onclick="closeModal()"
                        style="background:#e8ecf5;color:var(--blue);border:none;padding:10px 22px;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;">
                    Annuler
                </button>
                <button type="submit" class="btn-anac" style="padding:10px 22px;">
                    <i class="fas fa-save me-1"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('st').addEventListener('click', () =>
    document.getElementById('adminSidebar').classList.toggle('open'));

/* Données admins + permissions pour pré-remplir le modal */
const ADMINS_DATA = <?= json_encode(
    array_reduce(
        iterator_to_array((function() use ($conn, $all_perms) {
            $conn->query("SELECT * FROM administrateurs ORDER BY role DESC, nom ASC"); // already fetched
            return new ArrayIterator([]);
        })()),
        fn($c, $i) => $c,
        []
    )
) ?>;

const ALL_PERMS = <?= json_encode($all_perms) ?>;
const ADMINS_RAW = <?= json_encode((function() use ($conn) {
    $r = $conn->query("SELECT idadmin,nom,prenom,code_acces,email,role,actif FROM administrateurs");
    $arr = [];
    while ($row = $r->fetch_assoc()) $arr[$row['idadmin']] = $row;
    return $arr;
})()) ?>;

/* ── Ouvrir le modal ──────────────────────────────────────── */
function openModal(idadmin) {
    const isNew = !idadmin;
    document.getElementById('modalTitle').textContent = isNew
        ? 'Nouvel administrateur' : 'Modifier l\'administrateur';
    document.getElementById('fIdAdmin').value = idadmin;
    document.getElementById('mdpNote').style.display = isNew ? 'none' : 'inline';

    /* Réinitialiser */
    document.getElementById('adminForm').reset();
    document.querySelectorAll('.perm-card').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('[id^="pe-"]').forEach(c => { c.checked=false; c.disabled=true; });
    document.getElementById('fIdAdmin').value = idadmin;

    if (!isNew && ADMINS_RAW[idadmin]) {
        const a = ADMINS_RAW[idadmin];
        document.getElementById('fNom').value    = a.nom;
        document.getElementById('fPrenom').value = a.prenom;
        document.getElementById('fCode').value   = a.code_acces;
        document.getElementById('fEmail').value  = a.email || '';
        document.getElementById('fRole').value   = a.role;

        /* Pré-remplir permissions */
        const perms = ALL_PERMS[idadmin] || {};
        for (const [mod, p] of Object.entries(perms)) {
            const pvEl = document.getElementById('pv-' + mod);
            const peEl = document.getElementById('pe-' + mod);
            if (pvEl) {
                pvEl.checked = !!parseInt(p.peut_voir);
                if (pvEl.checked && peEl) { peEl.disabled = false; peEl.checked = !!parseInt(p.peut_edit); }
                updatePermCard(mod);
            }
        }
    }

    togglePermsSection();
    document.getElementById('modalOverlay').classList.add('show');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('show');
}

/* ── Afficher/masquer section permissions selon rôle ───────── */
function togglePermsSection() {
    const role    = document.getElementById('fRole').value;
    const section = document.getElementById('permsSection');
    section.style.display = (role === 'superadmin') ? 'none' : 'block';
}

/* ── Activer "Modifier" si "Voir" est coché ────────────────── */
function updatePermCard(mod) {
    const pv   = document.getElementById('pv-' + mod);
    const pe   = document.getElementById('pe-' + mod);
    const card = document.getElementById('pcard-' + mod);
    if (!pv) return;
    card.classList.toggle('active', pv.checked);
    if (pe) {
        pe.disabled = !pv.checked;
        if (!pv.checked) pe.checked = false;
    }
}

/* ── Toggle actif/inactif ─────────────────────────────────── */
function toggleAdmin(idadmin, actif, nom) {
    const action = actif ? 'désactiver' : 'activer';
    Swal.fire({
        title: 'Confirmer',
        html: `<p style="font-family:Candara,sans-serif;">Voulez-vous <strong>${action}</strong> le compte de <strong>${nom}</strong> ?</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: actif ? '#dc2626' : '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Oui',
        cancelButtonText: 'Annuler'
    }).then(r => {
        if (r.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="idadmin" value="${idadmin}">
                <input type="hidden" name="actif" value="${actif ? 0 : 1}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

/* ── Fermer en cliquant en dehors ─────────────────────────── */
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* ── Notifications ────────────────────────────────────────── */
<?php if (str_starts_with($msg, 'ok:')): ?>
Swal.fire({icon:'success',title:'✅ Enregistré',text:'<?= addslashes(substr($msg,3)) ?>',
    confirmButtonColor:'#03224c',timer:3000,timerProgressBar:true});
<?php elseif (str_starts_with($msg, 'error:')): ?>
Swal.fire({icon:'error',title:'Erreur',text:'<?= addslashes(substr($msg,6)) ?>',confirmButtonColor:'#dc2626'});
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>