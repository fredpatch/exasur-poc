<?php
session_start();
header('Content-Type: application/json');
include '../php/db_connection.php';

if (!isset($_SESSION['idcandidat']) || !isset($_SESSION['id_session'])) {
    echo json_encode(['success' => false, 'error' => 'Session invalide']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['question_id']) || !isset($data['selected_option'])) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit();
}

$idcandidat      = intval($_SESSION['idcandidat']);
$id_session      = intval($_SESSION['id_session']);
$question_id     = intval($data['question_id']);
$selected_option = intval($data['selected_option']);

// Mettre à jour la session PHP
$_SESSION['reponses'][$question_id] = $selected_option;

// Mettre à jour ou insérer la réponse en base
$update = $conn->prepare(
    "UPDATE reponses_candidat SET selected_option = ?
     WHERE idcandidat = ? AND question_id = ? AND id_session = ?"
);
$update->bind_param("iiii", $selected_option, $idcandidat, $question_id, $id_session);
$update->execute();

if ($conn->affected_rows == 0) {
    $insert = $conn->prepare(
        "INSERT INTO reponses_candidat (idcandidat, id_session, question_id, selected_option, est_correcte)
         VALUES (?, ?, ?, ?, 0)"
    );
    $insert->bind_param("iiii", $idcandidat, $id_session, $question_id, $selected_option);
    $insert->execute();
}

// Persister l'état des réponses dans progression_candidat
$reponses_json = json_encode($_SESSION['reponses']);
$upd_prog = $conn->prepare(
    "UPDATE progression_candidat SET reponses_json = ? WHERE idcandidat = ? AND id_session = ?"
);
$upd_prog->bind_param("sii", $reponses_json, $idcandidat, $id_session);
$upd_prog->execute();

echo json_encode(['success' => true]);
$conn->close();
?>