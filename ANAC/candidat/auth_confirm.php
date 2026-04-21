<?php
session_start();
include '../lang/lang_loader.php';

if (!isset($_SESSION['idpersoaero'])) {
    header("Location: auth.php");
    exit();
}
header("Location: examen.php");
exit();
?>