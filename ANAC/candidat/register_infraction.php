<?php
session_start();
header('Content-Type: application/json');
include '../php/db_connection.php';

if (!isset($_SESSION['idcandidat']) || !isset($_SESSION['id_session'])) {
    echo json_encode(['success' => false, 'infractions' => 0]);
    exit();
}

$data   = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? $data['action'] : 'Infraction détectée';

$action_messages = [
    'Clic droit détecté'    => 'Clic droit interdit',
    'Capture d\'écran'      => 'Capture d\'écran interdite',
    'Copier/Coller'         => 'Copier/Coller interdit',
    'Outils développeur'    => 'Outils de développement interdits',
    'Rafraîchissement'      => 'Rechargement de page interdit',
    'Nouvel onglet'         => 'Nouvel onglet interdit',
    'Changement d\'onglet'  => 'Changement de fenêtre/onglet',
    'Perte de focus'        => 'Sortie de la fenêtre d\'examen',
    'Tentative de copie'    => 'Copie de contenu interdite',
    'Tentative de couper'   => 'Action de couper interdite',
    'Tentative de coller'   => 'Collage de contenu interdit',
];

$action_log  = isset($action_messages[$action]) ? $action_messages[$action] : $action;
$idcandidat  = $_SESSION['idcandidat'];
$id_session  = $_SESSION['id_session'];

// Récupérer le compteur actuel
$sql  = "SELECT infractions FROM progression_candidat WHERE idcandidat = ? AND id_session = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $idcandidat, $id_session);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row         = $result->fetch_assoc();
    $infractions = intval($row['infractions']) + 1;

    $upd = $conn->prepare("UPDATE progression_candidat SET infractions = ? WHERE idcandidat = ? AND id_session = ?");
    $upd->bind_param("iii", $infractions, $idcandidat, $id_session);
    $upd->execute();
} else {
    $infractions = 1;
    $ins = $conn->prepare("INSERT INTO progression_candidat (idcandidat, id_session, infractions) VALUES (?, ?, 1)");
    $ins->bind_param("ii", $idcandidat, $id_session);
    $ins->execute();
}

$_SESSION['infractions'] = $infractions;

// Journalisation
$log_dir = '../logs/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_line = date('Y-m-d H:i:s')
    . " | Candidat ID: $idcandidat"
    . " | Session: $id_session"
    . " | Action: $action_log"
    . " | Total infractions: $infractions\n";
file_put_contents($log_dir . 'infractions.log', $log_line, FILE_APPEND | LOCK_EX);

echo json_encode([
    'success'    => true,
    'infractions' => $infractions
]);
?>