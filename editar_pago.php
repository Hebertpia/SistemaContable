<?php
require_once 'auth.php';
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['pago_id']) ? intval($_POST['pago_id']) : 0;
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0.0;
    $fecha_registro = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';

    if ($id > 0 && !empty($metodo_pago) && $monto >= 0 && !empty($fecha_registro)) {
        $stmt = $conn->prepare("UPDATE pagos SET metodo_pago = ?, monto = ?, fecha_registro = ? WHERE id = ?");
        $stmt->bind_param("sdsi", $metodo_pago, $monto, $fecha_registro, $id);
        $stmt->execute();
    }
}

// Redirigir de vuelta al estado de cuenta del cliente actual
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'clientes.php';
header("Location: " . $redirect);
exit;