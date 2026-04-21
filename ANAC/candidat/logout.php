<?php
session_start();
include '../php/db_connection.php';
include '../lang/lang_loader.php';

if (isset($_SESSION['idcandidat'])) {
    $update = $conn->prepare("UPDATE candidat SET is_logged_in = 0 WHERE idcandidat = ?");
    $update->bind_param("i", $_SESSION['idcandidat']);
    $update->execute();
}

session_destroy();
header("Location: ../../index.php");
exit();
?>