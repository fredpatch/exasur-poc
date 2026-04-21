<?php
/**
 * translate_proxy.php — Proxy de traduction FR → EN
 * EXASUR ANAC GABON — admin/translate_proxy.php
 *
 * Utilise l'API GRATUITE MyMemory (https://mymemory.translated.net)
 *  - Aucune clé API requise pour usage basique
 *  - Limite : ~100 requêtes/jour par IP en mode anonyme
 *  - Suffisant pour un usage admin interne
 *
 * Requête : POST JSON { "text": "texte à traduire", "from": "fr", "to": "en" }
 * Réponse : JSON { "status": "success", "translation": "texte traduit" }
 *           ou   { "status": "error",   "message": "..." }
 */

// ── Sécurité : accès réservé aux admins connectés ─────────────────────────────
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé.']);
    exit();
}

// ── En-têtes CORS + JSON ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Lecture des données POST JSON ─────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// ── Validation des entrées ────────────────────────────────────────────────────
$texte  = trim($data['text']  ?? '');
$from   = trim($data['from']  ?? 'fr');
$to     = trim($data['to']    ?? 'en');

// Vérifications basiques
if (empty($texte)) {
    echo json_encode(['status' => 'error', 'message' => 'Texte vide, traduction ignorée.']);
    exit();
}

// Sécurité : limiter la taille du texte (1 500 caractères max)
if (mb_strlen($texte) > 1500) {
    echo json_encode(['status' => 'error', 'message' => 'Texte trop long (max 1 500 caractères).']);
    exit();
}

// Sécurité : autoriser uniquement les langues connues
$langues_autorisees = ['fr', 'en', 'es', 'de', 'pt', 'ar'];
if (!in_array($from, $langues_autorisees) || !in_array($to, $langues_autorisees)) {
    echo json_encode(['status' => 'error', 'message' => 'Langue non supportée.']);
    exit();
}

// ── Appel à l'API MyMemory (gratuite, sans clé) ───────────────────────────────
$paire_langues = urlencode($from) . '|' . urlencode($to);
$texte_encode  = urlencode($texte);

// URL de l'API MyMemory
$url = "https://api.mymemory.translated.net/get?q={$texte_encode}&langpair={$paire_langues}";

// Contexte HTTP avec timeout
$contexte = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'timeout'         => 8,   // 8 secondes max
        'ignore_errors'   => true,
        'header'          => "User-Agent: EXASUR-ANAC-Admin/1.0\r\n",
    ]
]);

// Exécution de la requête
$reponse_brut = @file_get_contents($url, false, $contexte);

// ── Gestion des erreurs réseau ────────────────────────────────────────────────
if ($reponse_brut === false) {
    // Fallback : retourner le texte original sans traduction
    echo json_encode([
        'status'      => 'error',
        'message'     => 'Service de traduction indisponible. Réseau ou API inaccessible.',
        'fallback'    => $texte,
    ]);
    exit();
}

// ── Décodage de la réponse JSON de MyMemory ───────────────────────────────────
$reponse_json = json_decode($reponse_brut, true);

if (
    !$reponse_json ||
    !isset($reponse_json['responseStatus']) ||
    $reponse_json['responseStatus'] !== 200
) {
    // Quota dépassé ou autre erreur MyMemory
    $message_erreur = $reponse_json['responseDetails'] ?? 'Quota de traduction atteint ou erreur API.';
    echo json_encode([
        'status'  => 'error',
        'message' => $message_erreur,
    ]);
    exit();
}

// ── Extraction de la traduction ────────────────────────────────────────────────
$traduction = $reponse_json['responseData']['translatedText'] ?? '';

if (empty($traduction)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune traduction retournée.']);
    exit();
}

// ── Succès : retourner la traduction ──────────────────────────────────────────
echo json_encode([
    'status'      => 'success',
    'translation' => $traduction,
    'original'    => $texte,
    'from'        => $from,
    'to'          => $to,
]);