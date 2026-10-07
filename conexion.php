<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Caracas');

$host = "sql313.byethost16.com";
$user = "b16_42761514";
$pass = "Contable2026"; // Coloca aquí la clave con la que entras al vPanel
$db   = "b16_42761514_contable";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>