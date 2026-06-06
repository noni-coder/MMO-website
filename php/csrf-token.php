<?php
/**
 * ENSEMBLE MMO — Génération du token CSRF
 * Appelé en AJAX au chargement de la page
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/config.php';

// Générer un nouveau token
$_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));

echo json_encode([
    'token' => $_SESSION[CSRF_TOKEN_NAME]
], JSON_UNESCAPED_UNICODE);
