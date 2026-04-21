<?php
session_start();
header('Content-Type: application/json');
include '../php/db_connection.php';

if (!isset($_SESSION['idcandidat'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$idcandidat = $_SESSION['idcandidat'];
$rating = $data['rating'];

$check = $conn->prepare("SELECT id FROM evaluations WHERE idcandidat = ?");
$check->bind_param("i", $idcandidat);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $update = $conn->prepare("UPDATE evaluations SET rating = ?, created_at = NOW() WHERE idcandidat = ?");
    $update->bind_param("si", $rating, $idcandidat);
    $update->execute();
} else {
    $insert = $conn->prepare("INSERT INTO evaluations (idcandidat, rating) VALUES (?, ?)");
    $insert->bind_param("is", $idcandidat, $rating);
    $insert->execute();
}

echo json_encode(['success' => true]);
?>