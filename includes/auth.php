<?php
/* includes/auth.php - VERSION 1.1 */
require_once __DIR__.'/config.php';

function sesionIniciada(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['lr_uid']);
}

function exigirSesion(): void {
    if (!sesionIniciada()) redirigir('login.php');
}

function usuarioActual(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'id'     => $_SESSION['lr_uid']    ?? null,
        'nombre' => $_SESSION['lr_nombre'] ?? '',
        'rol'    => $_SESSION['lr_rol']    ?? '',
    ];
}

/* v1.1: rol superadmin.
   Encima de admin. Admin maneja contenido y consultas; superadmin ademas ve
   los datos personales en crudo: direcciones IP, user agents, el log de
   eventos. Johnny no necesita eso para trabajar, y la Ley 25.326 espera que
   el acceso a datos personales este limitado a quien lo necesita.
   Un superadmin es tambien admin: donde el codigo pide 'admin', pasa. */
function esSuperadmin(): bool {
    return (($_SESSION['lr_rol'] ?? '') === 'superadmin');
}

function esAdmin(): bool {
    return in_array($_SESSION['lr_rol'] ?? '', ['admin', 'superadmin'], true);
}

/* Para una pantalla entera. No redirige al login: el usuario YA tiene sesion
   valida, lo que le falta es nivel. Decirle "inicia sesion" seria mentirle. */
function exigirSuperadmin(): void {
    exigirSesion();
    if (!esSuperadmin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es-AR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Sin acceso</title><style>'
           . 'body{background:#0a1628;color:#e8f0f7;font-family:system-ui,sans-serif;'
           . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}'
           . 'div{max-width:420px;text-align:center}h1{font-size:1.4rem;margin:0 0 10px}'
           . 'p{color:#7a9ab8;line-height:1.7;font-size:.95rem}a{color:#00c9a7}</style></head><body><div>'
           . '<h1>Esta pantalla no esta disponible</h1>'
           . '<p>Tu usuario no tiene acceso a esta seccion. No es un error: '
           . 'requiere un nivel de permiso distinto.</p>'
           . '<p><a href="index.php">Volver al panel</a></p>'
           . '</div></body></html>';
        exit;
    }
}

function cerrarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
}
