<?php
/**
 * review/submit.php — rojasmardones.com
 * Handles dynamic artwork review form.
 * Works with any number of artworks (no fixed count).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../smtp_config.php';
require_once __DIR__ . '/../smtp_send.php';

define('TO_EMAIL', 'aguilera.felipe@gmail.com');
define('TO_NAME',  'Felipe Aguilera');
define('SUBJECT',  'Revisión de obras — Rodrigo Rojas Mardones');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

function clean(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

// ── Collect dynamic artwork entries ──────────────────────────────
// Fields are named: a_{id}_nombre, a_{id}_tecnica, etc.
// We find all unique IDs by scanning for *_start keys.

$artworks = [];
foreach ($_POST as $key => $value) {
    if (preg_match('/^a_(.+)_start$/', $key, $m)) {
        $id = $m[1];
        $artworks[$id] = [
            'start'    => clean($_POST["a_{$id}_start"]    ?? ''),
            'nombre'   => clean($_POST["a_{$id}_nombre"]   ?? ''),
            'tecnica'  => clean($_POST["a_{$id}_tecnica"]  ?? ''),
            'ano'      => clean($_POST["a_{$id}_ano"]      ?? ''),
            'sitio'    => clean($_POST["a_{$id}_sitio"]    ?? ''),
            'original' => clean($_POST["a_{$id}_original"] ?? ''),
            'print'    => clean($_POST["a_{$id}_print"]    ?? ''),
            'precio'   => clean($_POST["a_{$id}_precio"]   ?? ''),
            'foto'     => clean($_POST["a_{$id}_foto"]     ?? ''),
            'notas'    => clean($_POST["a_{$id}_notas"]    ?? ''),
        ];
    }
}

// Sort by start photo number
uasort($artworks, fn($a, $b) => (int)$a['start'] - (int)$b['start']);
$artworks = array_values($artworks);

// ── Plain text body ───────────────────────────────────────────────
$lines = [];
$lines[] = "REVISIÓN DE OBRAS — RODRIGO ROJAS MARDONES";
$lines[] = "Enviado: " . date('d/m/Y H:i') . " UTC";
$lines[] = "Total obras: " . count($artworks);
$lines[] = str_repeat("─", 56);
$lines[] = "";

foreach ($artworks as $i => $o) {
    $n = $i + 1;
    $lines[] = "OBRA " . str_pad($n, 2, '0', STR_PAD_LEFT) . " — " . strtoupper($o['nombre'] ?: '(sin nombre)');
    $lines[] = str_repeat("·", 38);
    $lines[] = "Desde foto:      IMG_{$o['start']}.JPG";
    $lines[] = "Técnica:         " . ($o['tecnica']  ?: '—');
    $lines[] = "Año:             " . ($o['ano']      ?: '—');
    $lines[] = "En el sitio:     " . ($o['sitio']    ?: '—');
    $lines[] = "Original venta:  " . ($o['original'] ?: '—');
    $lines[] = "Print digital:   " . ($o['print']    ?: '—');
    $lines[] = "Precio:          " . ($o['precio']   ?: '—');
    $lines[] = "Foto destacada:  " . ($o['foto']     ? "IMG_{$o['foto']}.JPG" : '—');
    if ($o['notas']) {
        $lines[] = "Notas:";
        $lines[] = "  " . str_replace("\n", "\n  ", $o['notas']);
    }
    $lines[] = "";
}
$lines[] = str_repeat("─", 56);
$lines[] = "v2.rojasmardones.com/review/";

$plain = implode("\r\n", $lines);

// ── HTML body ─────────────────────────────────────────────────────
$rows = '';
foreach ($artworks as $i => $o) {
    $n     = $i + 1;
    $label = htmlspecialchars($o['nombre'] ?: '(sin nombre)');
    $from  = htmlspecialchars($o['start']);
    $foto  = $o['foto'] ? "IMG_{$o['foto']}.JPG" : '—';
    $notas = $o['notas'] ? nl2br(htmlspecialchars($o['notas'])) : '';

    $sc = $o['sitio']    === 'Sí' ? '#4a8a4a' : ($o['sitio']    === 'No' ? '#666' : '#a07020');
    $oc = $o['original'] === 'Sí' ? '#4a8a4a' : ($o['original'] === 'No' ? '#666' : '#a07020');
    $pc = $o['print']    === 'Sí' ? '#4a8a4a' : ($o['print']    === 'No' ? '#666' : '#a07020');

    $rows .= "
    <tr>
      <td style='padding:18px 20px;border-bottom:1px solid #eee;vertical-align:top;color:#aaa;font-size:11px;font-weight:600;width:32px;'>" . str_pad($n,2,'0',STR_PAD_LEFT) . "</td>
      <td style='padding:18px 20px 18px 0;border-bottom:1px solid #eee;'>
        <p style='margin:0 0 3px;font-size:15px;font-weight:600;color:#111;'>{$label}</p>
        <p style='margin:0 0 12px;font-size:11px;color:#aaa;'>desde IMG_{$from}.JPG</p>
        <table style='border-collapse:collapse;font-size:12px;'>
          <tr>
            <td style='color:#999;padding:2px 14px 2px 0;'>Técnica</td><td style='color:#333;'>" . ($o['tecnica'] ?: '—') . "</td>
            <td style='color:#999;padding:2px 14px 2px 20px;'>Año</td><td style='color:#333;'>" . ($o['ano'] ?: '—') . "</td>
          </tr>
          <tr>
            <td style='color:#999;padding:2px 14px 2px 0;'>En el sitio</td>
            <td style='color:{$sc};font-weight:600;'>" . ($o['sitio'] ?: '—') . "</td>
            <td style='color:#999;padding:2px 14px 2px 20px;'>Original</td>
            <td style='color:{$oc};font-weight:600;'>" . ($o['original'] ?: '—') . "</td>
          </tr>
          <tr>
            <td style='color:#999;padding:2px 14px 2px 0;'>Print digital</td>
            <td style='color:{$pc};font-weight:600;'>" . ($o['print'] ?: '—') . "</td>
            <td style='color:#999;padding:2px 14px 2px 20px;'>Precio</td>
            <td style='color:#333;'>" . ($o['precio'] ?: '—') . "</td>
          </tr>
          <tr>
            <td style='color:#999;padding:2px 14px 2px 0;'>Foto destacada</td>
            <td colspan='3' style='color:#333;'>{$foto}</td>
          </tr>
        </table>
        " . ($notas ? "<p style='margin:10px 0 0;font-size:12px;color:#555;background:#f7f7f7;padding:8px 12px;border-left:2px solid #ddd;'>{$notas}</p>" : "") . "
      </td>
    </tr>";
}

$html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='font-family:-apple-system,sans-serif;margin:0;background:#f4f4f4;'>
<div style='max-width:620px;margin:28px auto;background:#fff;'>
  <div style='background:#111;padding:22px 24px;'>
    <p style='margin:0 0 4px;font-size:10px;color:#555;letter-spacing:.1em;text-transform:uppercase;'>rojasmardones.com</p>
    <h1 style='margin:0;font-size:18px;font-weight:400;color:#fff;'>Revisión de obras</h1>
    <p style='margin:6px 0 0;font-size:12px;color:#555;'>" . count($artworks) . " obras · " . date('d/m/Y H:i') . " UTC</p>
  </div>
  <table style='border-collapse:collapse;width:100%;'>{$rows}</table>
  <div style='padding:14px 20px;background:#fafafa;border-top:1px solid #eee;'>
    <p style='margin:0;font-size:10px;color:#ccc;'>v2.rojasmardones.com/review/</p>
  </div>
</div></body></html>";

// ── Send via authenticated SMTP ───────────────────────────────────
$sent = smtp_mail(TO_EMAIL, TO_NAME, SUBJECT, $plain, $html);

// ── Save resultado.html ───────────────────────────────────────────
$resultUrl = null;
if ($sent) {
    $filename = 'resultado-' . date('Ymd-His') . '.html';
    $saved = file_put_contents(__DIR__ . '/' . $filename, $html);
    if ($saved !== false) {
        $resultUrl = $filename;
    }
}

echo json_encode(['ok' => $sent, 'url' => $resultUrl]);
