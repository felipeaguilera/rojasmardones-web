<?php
/**
 * contact.php — rojasmardones.com
 * Handles the homepage contact form via authenticated SMTP.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/smtp_config.php';
require_once __DIR__ . '/smtp_send.php';

define('TO_EMAIL', 'hello@rojasmardones.com');
define('TO_NAME',  'Rodrigo Rojas Mardones');
define('SUBJECT',  'New message — rojasmardones.com');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

$name    = clean($_POST['name']    ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$message = clean($_POST['message'] ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid fields']);
    exit;
}

// ── Plain text ────────────────────────────────────────────────────
$plain  = "New message from rojasmardones.com\n";
$plain .= str_repeat('─', 40) . "\n";
$plain .= "From:  {$name}\n";
$plain .= "Email: {$email}\n";
$plain .= "Date:  " . date('d/m/Y H:i') . " UTC\n";
$plain .= str_repeat('─', 40) . "\n\n";
$plain .= $message . "\n\n";
$plain .= "Reply directly to this email to respond.\n";

// ── HTML ──────────────────────────────────────────────────────────
$msgHtml = nl2br(htmlspecialchars($message));
$html  = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>";
$html .= "<body style='font-family:-apple-system,sans-serif;margin:0;background:#f4f4f4;'>";
$html .= "<div style='max-width:560px;margin:28px auto;background:#fff;'>";
$html .= "  <div style='background:#111;padding:22px 24px;'>";
$html .= "    <p style='margin:0 0 4px;font-size:10px;color:#555;letter-spacing:.1em;text-transform:uppercase;'>rojasmardones.com</p>";
$html .= "    <h1 style='margin:0;font-size:18px;font-weight:400;color:#fff;'>New message</h1>";
$html .= "  </div>";
$html .= "  <div style='padding:24px;'>";
$html .= "    <table style='border-collapse:collapse;font-size:13px;margin-bottom:20px;'>";
$html .= "      <tr><td style='color:#999;padding:4px 16px 4px 0;'>From</td><td style='color:#111;font-weight:600;'>" . htmlspecialchars($name) . "</td></tr>";
$html .= "      <tr><td style='color:#999;padding:4px 16px 4px 0;'>Email</td><td><a href='mailto:{$email}' style='color:#111;'>{$email}</a></td></tr>";
$html .= "      <tr><td style='color:#999;padding:4px 16px 4px 0;'>Date</td><td style='color:#999;font-size:11px;'>" . date('d/m/Y H:i') . " UTC</td></tr>";
$html .= "    </table>";
$html .= "    <div style='border-left:3px solid #eee;padding:12px 16px;font-size:14px;line-height:1.7;color:#333;'>{$msgHtml}</div>";
$html .= "    <p style='margin:20px 0 0;font-size:11px;color:#bbb;'>Reply to this email to respond directly to {$name}.</p>";
$html .= "  </div></div></body></html>";

// ── Send via authenticated SMTP ───────────────────────────────────
$sent = smtp_mail(
    TO_EMAIL, TO_NAME,
    SUBJECT,
    $plain, $html,
    $name . ' <' . $email . '>'   // Reply-To = visitor's address
);

echo json_encode(['ok' => $sent]);
