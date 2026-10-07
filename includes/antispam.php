<?php
/* includes/antispam.php - VERSION 1.3
   IP blocking, rate limiting, disposable-email rejection and event logging.
   Required by the public forms and by track.php.
   Needs the tables in tracking_tables.sql. */

/* Set to true to accept ONLY the big consumer mail providers listed in
   LR_PROVEEDORES_OK below. Off by default and it should stay off: it rejects
   every custom business domain, which is where the paying enquiries come from. */
if (!defined('LR_SOLO_PROVEEDORES_CONOCIDOS')) define('LR_SOLO_PROVEEDORES_CONOCIDOS', false);

/* Max form submissions from one IP inside LR_RATE_MIN minutes. */
if (!defined('LR_RATE_MAX')) define('LR_RATE_MAX', 5);
if (!defined('LR_RATE_MIN')) define('LR_RATE_MIN', 60);

const LR_PROVEEDORES_OK = [
  'gmail.com','googlemail.com','hotmail.com','hotmail.com.ar','outlook.com','outlook.es',
  'live.com','live.com.ar','msn.com','yahoo.com','yahoo.com.ar','ymail.com',
  'proton.me','protonmail.com','pm.me','icloud.com','me.com','aol.com','zoho.com',
];

/* Known throwaway providers. Not exhaustive, no such list ever is. Add to it
   from admin/seguridad.php when you see a pattern. */
const LR_DESECHABLES = [
  '10minutemail.com','20minutemail.com','33mail.com','anonbox.net','armyspy.com',
  'bccto.me','burnermail.io','cuvox.de','dayrep.com','discard.email','dispostable.com',
  'einrot.com','emailondeck.com','emailtemporario.com.br','fakeinbox.com','fakemail.net',
  'fleckens.hu','getairmail.com','getnada.com','grr.la','guerrillamail.biz',
  'guerrillamail.com','guerrillamail.de','guerrillamail.net','guerrillamail.org',
  'guerrillamailblock.com','harakirimail.com','inboxbear.com','inboxkitten.com',
  'jetable.org','mailcatch.com','maildrop.cc','mailexpire.com','mailforspam.com',
  'mailinator.com','mailnesia.com','mailsac.com','mintemail.com','mohmal.com',
  'moakt.com','mytemp.email','nada.email','nowmymail.com','opayq.com','pokemail.net',
  'rhyta.com','sharklasers.com','shitmail.me','sogetthis.com','spam4.me','spamgourmet.com',
  'spambog.com','spamdecoy.net','superrito.com','teleworm.us','temp-mail.io','temp-mail.org',
  'tempail.com','tempinbox.com','tempmail.dev','tempmail.net','tempmailo.com','tempr.email',
  'throwawaymail.com','trashmail.com','trashmail.de','trbvm.com','tmpmail.org','tmpeml.com',
  'vomoto.com','wegwerfemail.de','yopmail.com','yopmail.fr','yopmail.net','zetmail.com',
];

/* Visitor IP as text. Behind Cloudflare or a proxy the forwarded header wins. */
function lrIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP'] as $h) {
        if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) return $_SERVER[$h];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function lrIpBloqueada(?PDO $db, string $ip): bool {
    if (!$db || $ip === '') return false;
    try {
        $q = $db->prepare("SELECT 1 FROM ip_bloqueadas WHERE ip=:i LIMIT 1");
        $q->execute([':i' => $ip]);
        return (bool)$q->fetchColumn();
    } catch (Exception $e) { error_log('[LR ipblock] '.$e->getMessage()); return false; }
}

/* True when this IP is under the submission limit. Fails open if the table
   is missing, so a broken log never blocks a real booking. */
function lrRitmoOk(?PDO $db, string $ip, string $tipo = 'rsvp_ok', ?int $max = null): bool {
    if (!$db || $ip === '') return true;
    $max = $max ?? LR_RATE_MAX;
    try {
        /* LR_RATE_MIN is a constant int, so it is interpolated. A placeholder
           inside INTERVAL is not reliable with server-side prepares, which
           this app uses (EMULATE_PREPARES is off in config.php). */
        $min = (int)LR_RATE_MIN;
        $q = $db->prepare("SELECT COUNT(*) FROM eventos
                           WHERE ip_txt=:i AND tipo=:t AND creado > (NOW() - INTERVAL {$min} MINUTE)");
        $q->execute([':i' => $ip, ':t' => $tipo]);
        return (int)$q->fetchColumn() < $max;
    } catch (Exception $e) { error_log('[LR ritmo] '.$e->getMessage()); return true; }
}

/* Email gate. $motivo comes back filled when the address is rejected. */
function lrEmailAceptable(?string $email, ?string &$motivo = null): bool {
    $motivo = null;
    $email = trim((string)$email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $motivo = 'formato'; return false; }

    $dom = strtolower(substr(strrchr($email, '@'), 1));
    if ($dom === '') { $motivo = 'formato'; return false; }

    if (in_array($dom, LR_DESECHABLES, true)) { $motivo = 'desechable'; return false; }

    // Subdomains of a throwaway provider, e.g. inbox.mailinator.com
    foreach (LR_DESECHABLES as $d) {
        if (substr($dom, -(strlen($d) + 1)) === '.'.$d) { $motivo = 'desechable'; return false; }
    }

    if (LR_SOLO_PROVEEDORES_CONOCIDOS && !in_array($dom, LR_PROVEEDORES_OK, true)) {
        $motivo = 'proveedor';
        return false;
    }

    /* The domain has to actually receive mail. Fails open when the host has
       DNS lookups disabled, which is common on shared plans. */
    if (function_exists('checkdnsrr')) {
        $mx = @checkdnsrr($dom, 'MX');
        $a  = @checkdnsrr($dom, 'A');
        if ($mx === false && $a === false) { $motivo = 'dominio'; return false; }
    }
    return true;
}

function lrMotivoTexto(?string $motivo): string {
    switch ($motivo) {
        case 'formato':     return 'El e-mail no tiene un formato valido.';
        case 'desechable':  return 'No aceptamos correos temporales. Usa tu e-mail habitual.';
        case 'proveedor':   return 'Usa un e-mail de Gmail, Outlook, Yahoo, iCloud o Proton.';
        case 'dominio':     return 'Ese dominio de e-mail no existe o no recibe correo.';
        default:            return 'Revisa el e-mail ingresado.';
    }
}

/* Referral or campaign tag. Read once from ?ref= or ?utm_source= and kept in
   the session so it survives to the moment the visitor actually converts. */
function lrCapturarRef(): ?string {
    /* config.php v2.4 does the capture, before any output. This just reads it.
       Kept as a function so the pages calling it do not need changing. */
    return $_SESSION['lr_ref'] ?? null;
}

/* One row per tracked action. Never throws: tracking must not break a form. */
function lrEvento(?PDO $db, string $tipo, array $d = []): void {
    if (!$db) return;
    $ip = lrIp();
    try {
        $db->prepare(
            "INSERT INTO eventos (tipo,retiro_id,detalle,ref,boton,ip,ip_txt,referer,user_agent)
             VALUES (:tipo,:retiro_id,:detalle,:ref,:boton,:ip,:ip_txt,:referer,:ua)"
        )->execute([
            ':boton'     => isset($d['boton']) ? substr(preg_replace('/[^a-z0-9_-]/','',strtolower((string)$d['boton'])), 0, 24) ?: null : null,
            ':tipo'      => substr($tipo, 0, 40),
            ':retiro_id' => !empty($d['retiro_id']) ? (int)$d['retiro_id'] : null,
            ':detalle'   => isset($d['detalle']) ? substr((string)$d['detalle'], 0, 160) : null,
            ':ref'       => $d['ref'] ?? ($_SESSION['lr_ref'] ?? null),
            ':ip'        => @inet_pton($ip) ?: null,
            ':ip_txt'    => $ip ?: null,
            ':referer'   => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255) ?: null,
            ':ua'        => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);
    } catch (Exception $e) { error_log('[LR evento] '.$e->getMessage()); }
}
