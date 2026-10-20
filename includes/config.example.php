<?php
/* ADVERTENCIA
   Este es el EJEMPLO. Copialo a includes/config.php y completa los valores
   reales ahi. config.php esta en .gitignore y nunca se sube al repositorio.

   DB_PASS es la password de MySQL, NO un hash bcrypt. Este archivo tenia un
   hash bcrypt puesto ahi y eso no conecta con nada: el hash vive en la tabla
   usuarios y es para el login del panel, no para la base de datos.
*/
/* ================================================================
   La Reserva Naturaleza Arte  ·  lareserva.net.ar
   config.php - must be required first on every page
   ================================================================ */

define('SITE_NAME',    'La Reserva');
define('SITE_LEGAL',   'La Reserva Naturaleza Arte');
define('SITE_TAGLINE', 'Naturaleza y arte para encuentros con sentido');
define('SITE_DOMAIN',  'lareserva.net.ar');
define('SITE_URL',     'https://lareserva.net.ar');

define('SITE_EMAIL',   'johnny@lareserva.net.ar');
/* Copia de cada consulta a una segunda casilla. Dejar '' para no mandar
   copia. Ojo: mail() no es confiable aca, esto es comodidad y no reemplaza
   mirar admin/consultas.php. */
define('SITE_EMAIL_COPIA', '');
define('SITE_WA',      '5491133629514');
define('SITE_WA_LABEL','+54 9 11 3362-9514');
define('SITE_IG',      'lareservanaturalezaarte');
define('SITE_CIUDAD',  'Capilla del Señor, Buenos Aires');
define('SITE_HECTAREAS', 250);

/* Coordenadas aproximadas de Capilla del Señor.
   Reemplazar por las del predio antes de publicar el mapa. */
define('SITE_LAT', -34.2939);
define('SITE_LNG', -59.1064);

/* ── Base de datos (cPanel → MySQL Databases) ──────────────── */
define('DB_HOST', 'localhost');
define('DB_NAME', 'lareserv_admin');
define('DB_USER', 'lareserv_charliee');
define('DB_PASS', 'PONER_AQUI_LA_PASSWORD_DE_MYSQL');

/* El sitio funciona sin base de datos: si la conexión falla,
   getDB() devuelve null y cada página usa su contenido de respaldo. */
function getDB(): ?PDO {
    static $pdo = null, $failed = false;
    if ($failed) return null;
    if ($pdo !== null) return $pdo;
    /* Guardia contra el error clasico: pegar un hash bcrypt en DB_PASS.
       El hash vive en la tabla usuarios y es para el login del panel.
       Aca va la password de MySQL, en texto plano. */
    if (str_starts_with(DB_PASS, '$2y$') || DB_PASS === 'PONER_AQUI_LA_PASSWORD_DE_MYSQL') return null;
    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]
        );
    } catch (PDOException $e) {
        error_log('[LR DB] '.$e->getMessage());
        $failed = true;
        return null;
    }
    return $pdo;
}

/* ── Helpers ───────────────────────────────────────────────── */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function limpiar(?string $s): string { return htmlspecialchars(strip_tags(trim((string)$s)), ENT_QUOTES, 'UTF-8'); }
function redirigir(string $u): void { header('Location: '.$u); exit; }

/** "13 · 14 y 15 de marzo 2026" a partir de dos fechas ISO */
function rangoFecha(?string $ini, ?string $fin): string {
    if (!$ini) return '';
    static $m = [1=>'enero','febrero','marzo','abril','mayo','junio','julio',
                 'agosto','septiembre','octubre','noviembre','diciembre'];
    $a = new DateTime($ini);
    if (!$fin || $fin === $ini) return $a->format('j').' de '.$m[(int)$a->format('n')].' '.$a->format('Y');
    $b = new DateTime($fin);
    if ($a->format('n') === $b->format('n')) {
        return $a->format('j').' al '.$b->format('j').' de '.$m[(int)$b->format('n')].' '.$b->format('Y');
    }
    return $a->format('j').' de '.$m[(int)$a->format('n')].' al '.$b->format('j').' de '.$m[(int)$b->format('n')].' '.$b->format('Y');
}

function waLink(string $texto = ''): string {
    return 'https://wa.me/'.SITE_WA.($texto ? '?text='.rawurlencode($texto) : '');
}

/* Token CSRF para los formularios */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfOk(?string $t): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

@ini_set('display_errors', '0');   // poner en '1' sólo mientras desarrollás
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');
