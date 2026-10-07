<?php
require_once 'auth.php';
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['servicio_id']) ? intval($_POST['servicio_id']) : 0;
    $concepto = isset($_POST['concepto']) ? trim($_POST['concepto']) : '';
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0.0;
    $fecha_registro = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';

    // Convertir el formato datetime-local (YYYY-MM-DDTHH:MM) al formato de MySQL (YYYY-MM-DD HH:MM:ss)
    if (!empty($fecha_registro)) {
        $fecha_registro = str_replace('T', ' ', $fecha_registro);
        if (strlen($fecha_registro) == 16) {
            $fecha_registro .= ':00';
        }
    }

    if ($id > 0 && !empty($concepto) && $monto >= 0 && !empty($fecha_registro)) {
        $stmt = $conn->prepare("UPDATE servicios SET concepto = ?, monto = ?, fecha_registro = ? WHERE id = ?");
        $stmt->bind_param("sdsi", $concepto, $monto, $fecha_registro, $id);
        $stmt->execute();
    }
}
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;