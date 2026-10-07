<?php
// funciones.php

function obtenerCicloActual($cliente_id, $conn) {
    $stmt = $conn->prepare("SELECT IFNULL(MAX(ciclo), 1) AS ciclo_actual FROM servicios WHERE cliente_id = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['ciclo_actual'] ?? 1;
}

function obtenerSaldoCliente($cliente_id, $conn) {
    // Sumar TODOS los servicios históricos del cliente
    $stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_s FROM servicios WHERE cliente_id = ?");
    $stmt_s->bind_param("i", $cliente_id);
    $stmt_s->execute();
    $tot_s = floatval($stmt_s->get_result()->fetch_assoc()['total_s']);

    // Sumar TODOS los pagos históricos del cliente
    $stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_p FROM pagos WHERE cliente_id = ?");
    $stmt_p->bind_param("i", $cliente_id);
    $stmt_p->execute();
    $tot_p = floatval($stmt_p->get_result()->fetch_assoc()['total_p']);

    // Retorna la diferencia real (Positivo = Deuda | Negativo = Saldo a Favor)
    return ($tot_s - $tot_p);
}
?>