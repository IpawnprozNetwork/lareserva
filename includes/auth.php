<?php
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

function cerrarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    session_destroy();
}
