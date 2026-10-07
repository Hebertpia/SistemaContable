<?php
require_once 'auth.php';
require_once 'conexion.php';

header('Content-Type: application/json');

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;

if ($cliente_id > 0) {
    // Sumar todos los servicios y pagos históricos
    $stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_s FROM servicios WHERE cliente_id = ?");
    $stmt_s->bind_param("i", $cliente_id);
    $stmt_s->execute();
    $tot_s = $stmt_s->get_result()->fetch_assoc()['total_s'];

    $stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_p FROM pagos WHERE cliente_id = ?");
    $stmt_p->bind_param("i", $cliente_id);
    $stmt_p->execute();
    $tot_p = $stmt_p->get_result()->fetch_assoc()['total_p'];

    $diferencia = floatval($tot_s - $tot_p);

    if ($diferencia < 0) {
        echo json_encode(['deuda' => 0, 'saldo_favor' => abs($diferencia)]);
    } else {
        echo json_encode(['deuda' => $diferencia, 'saldo_favor' => 0]);
    }
} else {
    echo json_encode(['deuda' => 0, 'saldo_favor' => 0]);
}
?>