<?php
// Initialiser la langue
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Langue par défaut
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fr';
}

// Changer de langue si demandé
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    // Redirection pour nettoyer l'URL
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit();
}

// Charger le fichier de langue correspondant
$lang_file = __DIR__ . '/' . $_SESSION['lang'] . '.php';
if (file_exists($lang_file)) {
    include $lang_file;
} else {
    // Fallback sur français
    include __DIR__ . '/fr.php';
}

// Fonction de traduction
function __($key) {
    global $lang;
    return isset($lang[$key]) ? $lang[$key] : $key;
}
?>