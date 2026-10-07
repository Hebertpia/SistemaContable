<?php
// Configurar duración de la sesión a 30 días (30 días * 24 horas * 60 min * 60 seg)
$duracion_sesion = 30 * 24 * 60 * 60; // 2.592.000 segundos

// Definir parámetros de la cookie de sesión ANTES de iniciarla
ini_set('session.gc_maxlifetime', $duracion_sesion);
session_set_cookie_params([
    'lifetime' => $duracion_sesion,
    'path'     => '/',
    'secure'   => false, // Cambiar a 'true' si usas dominio con HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Renovar la cookie en cada interacción activa para extender los 30 días contínuos
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $duracion_sesion, "/");
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>