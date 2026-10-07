<?php
require_once 'auth.php';
require_once 'conexion.php';
require_once 'funciones.php';

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;

// Verificar que el cliente exista
$stmt_c = $conn->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
$stmt_c->bind_param("i", $cliente_id);
$stmt_c->execute();
$cliente = $stmt_c->get_result()->fetch_assoc();

if (!$cliente) {
    header("Location: clientes.php");
    exit();
}

// Cargar Servicios del Cliente
$stmt_serv = $conn->prepare("SELECT id, concepto, monto, ciclo, fecha_registro FROM servicios WHERE cliente_id = ? ORDER BY fecha_registro DESC");
$stmt_serv->bind_param("i", $cliente_id);
$stmt_serv->execute();
$res_servicios = $stmt_serv->get_result();

// Cargar Pagos del Cliente
$stmt_pag = $conn->prepare("SELECT id, monto, metodo_pago, ciclo, fecha_registro FROM pagos WHERE cliente_id = ? ORDER BY fecha_registro DESC");
$stmt_pag->bind_param("i", $cliente_id);
$stmt_pag->execute();
$res_pagos = $stmt_pag->get_result();

// Obtener ciclo actual y saldos directamente
$stmt_ciclo = $conn->prepare("SELECT IFNULL(MAX(ciclo), 1) AS ciclo_actual FROM servicios WHERE cliente_id = ?");
$stmt_ciclo->bind_param("i", $cliente_id);
$stmt_ciclo->execute();
$ciclo_actual = $stmt_ciclo->get_result()->fetch_assoc()['ciclo_actual'] ?? 1;

// Totales del ciclo actual
$stmt_tot_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_s FROM servicios WHERE cliente_id = ? AND ciclo = ?");
$stmt_tot_s->bind_param("ii", $cliente_id, $ciclo_actual);
$stmt_tot_s->execute();
$tot_serv = floatval($stmt_tot_s->get_result()->fetch_assoc()['total_s']);

$stmt_tot_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_p FROM pagos WHERE cliente_id = ? AND ciclo = ?");
$stmt_tot_p->bind_param("ii", $cliente_id, $ciclo_actual);
$stmt_tot_p->execute();
$tot_pag = floatval($stmt_tot_p->get_result()->fetch_assoc()['total_p']);

$diferencia = $tot_serv - $tot_pag;
$deuda_pendiente = ($diferencia > 0) ? $diferencia : 0;
$saldo_a_favor = ($diferencia < 0) ? abs($diferencia) : 0;

include 'header.php';
?>

<div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="margin: 0; font-size: 1.2em;"><?php echo htmlspecialchars($cliente['nombre']); ?></h2>
        <span style="font-size: 0.8em; color: var(--text-muted);">C.I. <?php echo $cliente['id']; ?> | Tel: <?php echo htmlspecialchars($cliente['telefono'] ?: 'N/A'); ?></span>
    </div>
    <a href="estado_cuenta.php?cliente_id=<?php echo $cliente_id; ?>" class="btn-action" style="padding: 6px 12px; font-size: 0.8em; background: var(--primary); color: white; border: none; text-decoration: none;">📄 Cuenta</a>
</div>

<!-- Resumen Metrico -->
<div class="metrics-grid" style="margin-bottom: 16px;">
    <div class="metric-box">
        <label>Ciclo Actual</label>
        <span>N° <?php echo $ciclo_actual; ?></span>
    </div>
    <div class="metric-box" style="grid-column: span 2;">
        <label>Estado del Ciclo</label>
        <?php if ($deuda_pendiente > 0): ?>
            <span style="color: var(--danger);">$<?php echo number_format($deuda_pendiente, 2); ?> Pendiente</span>
        <?php elseif ($saldo_a_favor > 0): ?>
            <span style="color: var(--success);">$<?php echo number_format($saldo_a_favor, 2); ?> a Favor</span>
        <?php else: ?>
            <span style="color: var(--success);">$0.00 (Al día)</span>
        <?php endif; ?>
    </div>
</div>

<!-- Sección 1: Historial de Servicios / Trabajos -->
<div class="card">
    <h3 style="margin-top: 0; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">📋 Servicios / Trabajos Cargados</h3>
    <?php if ($res_servicios->num_rows > 0): ?>
        <?php while ($s = $res_servicios->fetch_assoc()): ?>
            <div class="activity-item">
                <div>
                    <strong style="display: block; font-size: 0.9em;"><?php echo htmlspecialchars($s['concepto']); ?></strong>
                    <span style="font-size: 0.75em; color: var(--text-muted);">
                        📅 <?php echo date('d/m/Y h:i A', strtotime($s['fecha_registro'])); ?> | Ciclo <?php echo $s['ciclo']; ?>
                    </span>
                </div>
                <div style="text-align: right;">
                    <span style="font-weight: bold; font-size: 0.95em;">$<?php echo number_format($s['monto'], 2); ?></span>
                    <br>
                    <a href="editar_registro.php?id=<?php echo $s['id']; ?>&tipo=servicio" style="font-size: 0.78em; color: var(--primary); text-decoration: none;">✏️ Editar</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.88em; margin: 10px 0;">No posee servicios registrados.</p>
    <?php endif; ?>
</div>

<!-- Sección 2: Historial de Pagos Realizados -->
<div class="card">
    <h3 style="margin-top: 0; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">💵 Pagos Recibidos</h3>
    <?php if ($res_pagos->num_rows > 0): ?>
        <?php while ($p = $res_pagos->fetch_assoc()): ?>
            <div class="activity-item">
                <div>
                    <strong style="display: block; font-size: 0.9em;">💳 <?php echo htmlspecialchars($p['metodo_pago']); ?></strong>
                    <span style="font-size: 0.75em; color: var(--text-muted);">
                        📅 <?php echo date('d/m/Y h:i A', strtotime($p['fecha_registro'])); ?> | Ciclo <?php echo $p['ciclo']; ?>
                    </span>
                </div>
                <div style="text-align: right;">
                    <span style="font-weight: bold; color: var(--success); font-size: 0.95em;">+$<?php echo number_format($p['monto'], 2); ?></span>
                    <br>
                    <a href="editar_registro.php?id=<?php echo $p['id']; ?>&tipo=pago" style="font-size: 0.78em; color: var(--primary); text-decoration: none;">✏️ Editar</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.88em; margin: 10px 0;">No se han registrado pagos para este cliente.</p>
    <?php endif; ?>
</div>

</div> <!-- Cierre container -->
</body>
</html>