<?php
/**
 * ENSEMBLE MMO — Traitement du formulaire de contact
 * 
 * Sécurité intégrée :
 *  - Validation e-mail (syntaxe + DNS MX)
 *  - Champ honeypot anti-bot
 *  - Token CSRF
 *  - Rate-limiting par IP (session)
 *  - Nettoyage XSS de toutes les entrées
 *  - Réponse automatique à l'expéditeur
 */

// ─── INIT ───────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SmtpMailer.php';

// ─── CORS (même domaine uniquement) ─────────────────────
$allowed_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
// Ajustez si besoin pour votre domaine exact
header('Access-Control-Allow-Origin: ' . $allowed_origin);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── VÉRIFICATION MÉTHODE ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Méthode non autorisée.');
}

// ─── RÉCUPÉRER LES DONNÉES ─────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback: form data classique
    $input = $_POST;
}

$name    = clean($input['name'] ?? '');
$email   = clean($input['email'] ?? '');
$subject = clean($input['subject'] ?? '');
$message = clean($input['message'] ?? '');
$honeypot = trim($input[HONEYPOT_FIELD] ?? '');
$token   = trim($input['csrf_token'] ?? '');

// ─── 1. HONEYPOT CHECK ─────────────────────────────────
if (!empty($honeypot)) {
    // Bot détecté — on fait semblant que tout va bien
    respond(200, 'Message envoyé avec succès !');
}

// ─── 2. CSRF TOKEN ──────────────────────────────────────
if (empty($token) || !isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
    respond(403, 'Session expirée. Veuillez rafraîchir la page et réessayer.');
}

// ─── 3. RATE LIMITING ───────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();

if (!isset($_SESSION['mmo_rate'])) {
    $_SESSION['mmo_rate'] = ['last' => 0, 'count' => 0, 'hour_start' => $now];
}

$rate = &$_SESSION['mmo_rate'];

// Reset compteur horaire
if ($now - $rate['hour_start'] > 3600) {
    $rate['count'] = 0;
    $rate['hour_start'] = $now;
}

// Trop rapide ?
if ($now - $rate['last'] < RATE_LIMIT_SECONDS) {
    $wait = RATE_LIMIT_SECONDS - ($now - $rate['last']);
    respond(429, "Merci de patienter {$wait} secondes avant de renvoyer un message.");
}

// Trop d'envois ?
if ($rate['count'] >= MAX_SUBMISSIONS_PER_HOUR) {
    respond(429, 'Vous avez atteint le nombre maximum de messages par heure. Réessayez plus tard.');
}

// ─── 4. VALIDATION DES CHAMPS ───────────────────────────
$errors = [];

if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Le nom doit contenir entre 2 et 100 caractères.';
}

if (empty($email)) {
    $errors[] = 'L\'adresse e-mail est requise.';
} elseif (!validateEmail($email)) {
    $errors[] = 'L\'adresse e-mail n\'est pas valide ou n\'existe pas.';
}

$allowed_subjects = ['rejoindre', 'concert', 'enregistrement', 'autre'];
if (empty($subject) || !in_array($subject, $allowed_subjects)) {
    $errors[] = 'Veuillez sélectionner un objet valide.';
}

if (empty($message) || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    $errors[] = 'Le message doit contenir entre 10 et 5000 caractères.';
}

if (!empty($errors)) {
    respond(422, implode(' ', $errors));
}

// ─── 5. PRÉPARER LE CONTENU ────────────────────────────
$subject_labels = [
    'rejoindre'      => '🎵 Rejoindre l\'orchestre',
    'concert'        => '🎬 Organiser un concert / événement',
    'enregistrement' => '🎙️ Enregistrement studio',
    'autre'          => '📩 Autre demande',
];

$subject_label = $subject_labels[$subject] ?? $subject;

$email_subject = "[Ensemble MMO] {$subject_label} — de {$name}";

$email_body_text = <<<EOT
═══════════════════════════════════════
NOUVEAU MESSAGE — ENSEMBLE MMO
═══════════════════════════════════════

De : {$name}
E-mail : {$email}
Objet : {$subject_label}
Date : {$date_fr}

───────────────────────────────────────
MESSAGE :
───────────────────────────────────────

{$message}

───────────────────────────────────────
IP : {$ip}
EOT;

$date_fr = strftime('%d/%m/%Y à %H:%M') ?: date('d/m/Y H:i');

$email_body_text = "═══════════════════════════════════════\n";
$email_body_text .= "NOUVEAU MESSAGE — ENSEMBLE MMO\n";
$email_body_text .= "═══════════════════════════════════════\n\n";
$email_body_text .= "De : {$name}\n";
$email_body_text .= "E-mail : {$email}\n";
$email_body_text .= "Objet : {$subject_label}\n";
$email_body_text .= "Date : {$date_fr}\n\n";
$email_body_text .= "───────────────────────────────────────\n";
$email_body_text .= "MESSAGE :\n";
$email_body_text .= "───────────────────────────────────────\n\n";
$email_body_text .= "{$message}\n\n";
$email_body_text .= "───────────────────────────────────────\n";
$email_body_text .= "IP : {$ip}\n";

$email_body_html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0a0c;font-family:'Segoe UI',Tahoma,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0c;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#17171c;border-radius:16px;overflow:hidden;border:1px solid #2a2a32;">
    <tr><td style="background:linear-gradient(135deg,#E8708E,#F0A0B8);padding:24px 32px;">
        <h1 style="margin:0;color:#fff;font-size:20px;font-weight:600;">📩 Nouveau message</h1>
        <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">{$subject_label}</p>
    </td></tr>
    <tr><td style="padding:32px;">
        <table width="100%" cellpadding="8" cellspacing="0">
            <tr>
                <td style="color:#8888a0;font-size:13px;width:80px;vertical-align:top;">De</td>
                <td style="color:#e8e8f0;font-size:15px;"><strong>{$name}</strong></td>
            </tr>
            <tr>
                <td style="color:#8888a0;font-size:13px;vertical-align:top;">E-mail</td>
                <td><a href="mailto:{$email}" style="color:#F0A0B8;font-size:15px;">{$email}</a></td>
            </tr>
            <tr>
                <td style="color:#8888a0;font-size:13px;vertical-align:top;">Date</td>
                <td style="color:#e8e8f0;font-size:15px;">{$date_fr}</td>
            </tr>
        </table>
        <hr style="border:none;border-top:1px solid #2a2a32;margin:20px 0;">
        <div style="color:#e8e8f0;font-size:15px;line-height:1.7;white-space:pre-wrap;">{$message}</div>
    </td></tr>
    <tr><td style="padding:16px 32px;background:#111115;border-top:1px solid #2a2a32;">
        <p style="margin:0;color:#555;font-size:11px;">IP: {$ip} — Ensemble MMO</p>
    </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

// ─── 6. ENVOYER LE MESSAGE PRINCIPAL ────────────────────
$mailer = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS);

$sent = $mailer->send(
    SMTP_FROM_EMAIL,
    $name . ' via Ensemble MMO',
    RECIPIENT_EMAIL,
    RECIPIENT_NAME,
    $email_subject,
    $email_body_text,
    $email_body_html
);

if (!$sent) {
    error_log('MMO Mail Error: ' . implode(' | ', $mailer->getLog()));
    respond(500, 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer ou nous contacter directement par téléphone.');
}

// ─── 7. ENVOYER LA RÉPONSE AUTOMATIQUE ──────────────────
$autoReplyHtml = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0a0c;font-family:'Segoe UI',Tahoma,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0c;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#17171c;border-radius:16px;overflow:hidden;border:1px solid #2a2a32;">
    <tr><td style="background:linear-gradient(135deg,#E8708E,#F0A0B8);padding:30px 32px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:24px;font-weight:600;">🎵 Ensemble MMO</h1>
        <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">Massive Multiplayer Orchestra</p>
    </td></tr>
    <tr><td style="padding:40px 32px;text-align:center;">
        <h2 style="color:#e8e8f0;font-size:20px;margin:0 0 20px;">Bonjour {$name} !</h2>
        <p style="color:#8888a0;font-size:15px;line-height:1.8;margin:0 0 16px;">
            Merci de nous avoir contactés !<br>
            Nous vous répondons dans les <strong style="color:#F0A0B8;">24h</strong> !
        </p>
        <p style="color:#e8e8f0;font-size:18px;margin:24px 0;">
            À très vite ! 😉
        </p>
        <hr style="border:none;border-top:1px solid #2a2a32;margin:24px 0;">
        <p style="color:#F0A0B8;font-size:15px;font-weight:600;margin:0;">
            Ensemble MMO — LA Team 😉
        </p>
    </td></tr>
    <tr><td style="padding:20px 32px;background:#111115;border-top:1px solid #2a2a32;text-align:center;">
        <p style="color:#555;font-size:11px;margin:0 0 8px;">
            Ceci est un message automatique — merci de ne pas y répondre directement.
        </p>
        <p style="color:#555;font-size:11px;margin:0;">
            Pour toute question urgente : <a href="tel:+33621007735" style="color:#F0A0B8;">06 21 00 77 35</a>
        </p>
        <p style="margin:12px 0 0;">
            <a href="https://www.instagram.com/ensemble_mmo/" style="color:#8888a0;font-size:12px;margin:0 8px;">Instagram</a>
            <a href="https://www.facebook.com/profile.php?id=61565895601263" style="color:#8888a0;font-size:12px;margin:0 8px;">Facebook</a>
        </p>
    </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

$autoMailer = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS);
$autoSent = $autoMailer->send(
    SMTP_FROM_EMAIL,
    SMTP_FROM_NAME,
    $email,
    $name,
    AUTO_REPLY_SUBJECT,
    AUTO_REPLY_MESSAGE,
    $autoReplyHtml
);

if (!$autoSent) {
    error_log('MMO Auto-Reply Error: ' . implode(' | ', $autoMailer->getLog()));
    // On ne bloque pas — le message principal est déjà envoyé
}

// ─── 8. MISE À JOUR DU RATE LIMIT ──────────────────────
$rate['last'] = $now;
$rate['count']++;

// Régénérer le token CSRF
$_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));

respond(200, 'Message envoyé avec succès ! Vous allez recevoir une confirmation par e-mail.', [
    'new_csrf' => $_SESSION[CSRF_TOKEN_NAME]
]);

// ═══════════════════════════════════════════════════════════
// FONCTIONS UTILITAIRES
// ═══════════════════════════════════════════════════════════

/**
 * Valide une adresse e-mail (syntaxe + vérification DNS MX)
 */
function validateEmail(string $email): bool
{
    // 1. Syntaxe
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // 2. Longueur raisonnable
    if (mb_strlen($email) > 254) {
        return false;
    }

    // 3. Vérification DNS MX (le domaine accepte-t-il des e-mails ?)
    $domain = substr($email, strrpos($email, '@') + 1);
    
    // Vérifier qu'il y a un enregistrement MX ou au moins un A record
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return false;
    }

    // 4. Blacklist des domaines jetables courants
    $disposable = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email',
        'yopmail.com', 'sharklasers.com', 'trashmail.com', 'fakeinbox.com',
        'maildrop.cc', 'dispostable.com', 'getnada.com', 'temp-mail.org',
        'guerrillamailblock.com', 'grr.la', 'mailnesia.com', '10minutemail.com',
        'mohmal.com', 'emailondeck.com', 'tempail.com', 'burnermail.io',
        'crazymailing.com', 'discard.email', 'discardmail.com', 'jetable.org',
    ];

    if (in_array(strtolower($domain), $disposable)) {
        return false;
    }

    return true;
}

/**
 * Nettoie une chaîne (anti-XSS)
 */
function clean(string $str): string
{
    $str = trim($str);
    $str = stripslashes($str);
    $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $str;
}

/**
 * Réponse JSON standardisée
 */
function respond(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => ($code >= 200 && $code < 300),
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
