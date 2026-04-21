<?php
/**
 * get_session_info.php — Infos session pour le réinitialisateur
 * Retourne nb réponses déjà données par le candidat pour une session
 */
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode([]); exit(); }
include '../php/db_connection.php';

$idcandidat = intval($_POST['idcandidat'] ?? 0);
$id_session = intval($_POST['id_session'] ?? 0);

if (!$idcandidat || !$id_session) { echo json_encode(['nb_reponses'=>0]); exit(); }

$nb = $conn->query("SELECT COUNT(*) FROM reponses_candidat WHERE idcandidat=$idcandidat AND id_session=$id_session AND selected_option IS NOT NULL")->fetch_row()[0] ?? 0;

$a_resultat = $conn->query("SELECT id FROM resultats WHERE idcandidat=$idcandidat AND id_session=$id_session LIMIT 1")->num_rows > 0;

header('Content-Type: application/json');
echo json_encode(['nb_reponses' => intval($nb), 'a_resultat' => $a_resultat]);
$conn->close();