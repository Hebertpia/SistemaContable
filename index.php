<?php
// Desactivar la caché del navegador para asegurar datos en tiempo real
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'auth.php';
require_once 'conexion.php';

// Ajuste de zona horaria para Venezuela
date_default_timezone_set('America/Caracas');

include 'header.php';

// Definición del rango de tiempo para el día actual
$inicio_dia = date('Y-m-d 00:00:00');
$fin_dia    = date('Y-m-d 23:59:59');

// 1. Total Servicios de hoy
$stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total FROM servicios WHERE fecha_registro BETWEEN ? AND ?");
$stmt_s->bind_param("ss", $inicio_dia, $fin_dia);
$stmt_s->execute();
$servicios_hoy = floatval($stmt_s->get_result()->fetch_assoc()['total']);

// 2. Total Cobrado de hoy
$stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total FROM pagos WHERE fecha_registro BETWEEN ? AND ?");
$stmt_p->bind_param("ss", $inicio_dia, $fin_dia);
$stmt_p->execute();
$pagos_hoy = floatval($stmt_p->get_result()->fetch_assoc()['total']);

// 3. Pendiente del día
$pendiente_hoy = $servicios_hoy - $pagos_hoy;

// 4. Últimos pagos registrados del día
$stmt_pagos = $conn->prepare("SELECT p.monto, p.metodo_pago, p.fecha_registro, c.nombre 
                              FROM pagos p 
                              JOIN clientes c ON p.cliente_id = c.id 
                              WHERE p.fecha_registro BETWEEN ? AND ? 
                              ORDER BY p.fecha_registro DESC LIMIT 5");
$stmt_pagos->bind_param("ss", $inicio_dia, $fin_dia);
$stmt_pagos->execute();
$res_recientes = $stmt_pagos->get_result();
?>

<!-- Separación corregida con respecto al Header -->
<div style="margin: 24px 0 20px 0;">
    <h2 style="margin: 0; font-size: 1.3em;">👋 ¡Hola, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h2>
    <small style="color: var(--text-muted);">Resumen contable de hoy (<?php echo date('d/m/Y'); ?>)</small>
</div>

<!-- Tarjeta de Balances -->
<div class="card">
    <span style="font-size: 0.8em; font-weight: bold; color: var(--text-muted);">BALANCE DEL DÍA</span>
    <div class="metrics-grid">
        <div class="metric-box">
            <label>Servicios</label>
            <span style="color: var(--primary);">$<?php echo number_format($servicios_hoy, 2); ?></span>
        </div>
        <div class="metric-box">
            <label>Cobrado</label>
            <span style="color: var(--success);">$<?php echo number_format($pagos_hoy, 2); ?></span>
        </div>
        <div class="metric-box">
            <label>Pendiente</label>
            <span style="color: <?php echo ($pendiente_hoy > 0) ? 'var(--danger)' : 'var(--text-main)'; ?>;">
                $<?php echo number_format(($pendiente_hoy > 0 ? $pendiente_hoy : 0), 2); ?>
            </span>
        </div>
    </div>
</div>

<!-- Acciones Rápidas -->
<div class="actions-grid">
    <a href="registrar_servicio.php" class="btn-action">📝 Nuevo Servicio</a>
    <a href="registrar_pago.php" class="btn-action">💵 Registrar Pago</a>
    <a href="clientes.php" class="btn-action">👥 Clientes</a>
    <a href="reportes.php" class="btn-action">📊 Reportes</a>
</div>

<!-- ÚLTIMOS PAGOS -->
<div class="card">
    <span style="font-size: 0.8em; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 8px;">ÚLTIMOS PAGOS RECIBIDOS</span>
    
    <?php if ($res_recientes && $res_recientes->num_rows > 0): ?>
        <?php while ($r = $res_recientes->fetch_assoc()): ?>
            <div class="activity-item">
                <div>
                    <strong style="font-size: 0.9em; display: block;"><?php echo htmlspecialchars($r['nombre']); ?></strong>
                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($r['metodo_pago']); ?> · <?php echo date('h:i A', strtotime($r['fecha_registro'])); ?></small>
                </div>
                <span style="color: var(--success); font-weight: bold; font-size: 0.95em;">+$<?php echo number_format($r['monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.88em; margin: 10px 0;">No hay pagos registrados el día de hoy.</p>
    <?php endif; ?>
</div>

</div> <!-- Cierre del container abierto en header.php -->
</body>
</html>