<?php
session_start();
header('Content-Type: application/json');
include '../php/db_connection.php';

if (!isset($_SESSION['idcandidat']) || !isset($_SESSION['id_session'])) {
    echo json_encode(['success' => false]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$current_index = intval($data['current_index']);
$partie = $data['partie'] ?? 'theorique';

$idcandidat = $_SESSION['idcandidat'];
$id_session = $_SESSION['id_session'];

if ($partie == 'theorique') {
    $update = $conn->prepare("UPDATE progression_candidat SET current_index_theo = ? WHERE idcandidat = ? AND id_session = ?");
} else {
    $update = $conn->prepare("UPDATE progression_candidat SET current_index_pra = ? WHERE idcandidat = ? AND id_session = ?");
}
$update->bind_param("iii", $current_index, $idcandidat, $id_session);
$update->execute();

echo json_encode(['success' => true]);
?>