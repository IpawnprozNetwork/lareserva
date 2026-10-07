<?php
/* track.php - VERSION 1.3
   Beacon endpoint. The page posts a small payload here when a visitor clicks
   something that leaves the site (the WhatsApp button) or opens a retiro.
   Answers 204 with no body so it never slows the click down. */

require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/antispam.php';

http_response_code(204);
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

/* navigator.sendBeacon posts a JSON blob, a plain fetch posts form fields.
   Accept both. */
$in = $_POST;
if (!$in) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $j = json_decode($raw, true);
        if (is_array($j)) $in = $j;
    }
}

$permitidos = ['abrir_retiro','abrir_espacio','click_rsvp','click_wa','click_ig','click_url'];
$tipo = (string)($in['tipo'] ?? '');
if (!in_array($tipo, $permitidos, true)) exit;

$db = getDB();
if (!$db) exit;

$ip = lrIp();
if (lrIpBloqueada($db, $ip)) exit;                       // blocked IPs are not logged
if (!lrRitmoOk($db, $ip, $tipo, 120)) exit;              // and neither is a flood
                                                         // 120/hour per click type: generous for a
                                                         // real browser, useless for a scraper

lrEvento($db, $tipo, [
    'retiro_id' => $in['retiro_id'] ?? null,
    'detalle'   => $in['detalle'] ?? null,
    'boton'     => $in['boton'] ?? null,
]);
exit;
