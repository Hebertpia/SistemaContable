<?php
require_once 'auth.php';
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['servicio_id']) ? intval($_POST['servicio_id']) : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM servicios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;