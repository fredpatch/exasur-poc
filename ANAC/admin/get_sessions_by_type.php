<?php
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); echo json_encode([]); exit(); }
include '../php/db_connection.php';
$idtype = intval($_POST['idtype']??0);
$r = $conn->query("SELECT id_session,nom_session FROM session_examen WHERE idtype_examen=$idtype AND statut IN('planifiee','en_cours') ORDER BY date_debut DESC");
$res=[];
while($s=$r->fetch_assoc()) $res[]=$s;
echo json_encode($res);
$conn->close();