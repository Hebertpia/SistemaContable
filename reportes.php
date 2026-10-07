<?php
require_once 'auth.php';
require_once 'conexion.php';

date_default_timezone_set('America/Caracas');

// Manejo de filtros rápidos
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';

if ($filtro === 'dia') {
    $fecha_inicio = date('Y-m-d');
    $fecha_fin    = date('Y-m-d');
} elseif ($filtro === 'semana') {
    $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
    $fecha_fin    = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filtro === 'mes') {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin    = date('Y-m-t');
} elseif ($filtro === 'trimestre') {
    $mes_actual = date('n');
    $trimestre_inicio = floor(($mes_actual - 1) / 3) * 3 + 1;
    $fecha_inicio = date('Y-' . sprintf('%02d', $trimestre_inicio) . '-01');
    $fecha_fin    = date('Y-m-t', strtotime($fecha_inicio . ' +2 months'));
} else {
    // Por defecto se asigna el rango de "Esta Semana"
    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('monday this week'));
    $fecha_fin    = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d', strtotime('sunday this week'));
}

// 1. Ganancia Total
$stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_servicios FROM servicios WHERE DATE(fecha_registro) BETWEEN ? AND ?");
$stmt_s->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_s->execute();
$total_servicios = floatval($stmt_s->get_result()->fetch_assoc()['total_servicios']);

// 2. Monto Ya Pagado
$stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_pagos FROM pagos WHERE DATE(fecha_registro) BETWEEN ? AND ?");
$stmt_p->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_p->execute();
$total_pagos = floatval($stmt_p->get_result()->fetch_assoc()['total_pagos']);

// 3. Monto Pendiente por Cobrar
$por_cobrar = $total_servicios - $total_pagos;
$por_cobrar_val = ($por_cobrar > 0) ? $por_cobrar : 0;

// Cargar listado detallado
$stmt_list = $conn->prepare("SELECT p.id, p.monto, p.metodo_pago, p.fecha_registro, p.ciclo, c.nombre AS cliente_nombre 
                             FROM pagos p 
                             JOIN clientes c ON p.cliente_id = c.id 
                             WHERE DATE(p.fecha_registro) BETWEEN ? AND ? 
                             ORDER BY p.fecha_registro DESC");
$stmt_list->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt_list->execute();
$res_pagos = $stmt_list->get_result();

include 'header.php';
?>

<!-- Importar Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Estilos para optimizar el buscador móvil y el contenedor del gráfico -->
<style>
.filtro-fechas-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.filtro-fila-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.filtro-campo {
    width: 100%;
    min-width: 0;
}
.filtro-campo input[type="date"] {
    font-size: 0.8em;
    padding: 6px 4px;
    width: 100%;
    box-sizing: border-box;
}
.filtro-btn {
    width: 100%;
    padding: 10px;
    font-size: 0.9em;
}

/* En PC se acomodan en línea horizontal */
@media (min-width: 768px) {
    .filtro-fechas-container {
        flex-direction: row;
        align-items: flex-end;
    }
    .filtro-fila-inputs {
        display: contents;
    }
    .filtro-campo {
        flex: 1;
    }
    .filtro-campo input[type="date"] {
        font-size: 0.95em;
        padding: 8px;
    }
    .filtro-btn {
        flex: 0 0 130px;
        width: auto;
    }
}
</style>

<!-- Separación con respecto al Header -->
<div style="margin: 24px 0 20px 0;">
    <h2 style="margin: 0; font-size: 1.3em;">📊 Reporte de Ingresos</h2>
</div>

<!-- Filtros Rápidos -->
<div style="margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="reportes.php?filtro=dia" class="btn-action" style="padding: 8px 12px; font-size: 0.85em; <?php echo ($filtro==='dia')?'background: var(--primary); color: white;':''; ?>">Hoy</a>
    <a href="reportes.php?filtro=semana" class="btn-action" style="padding: 8px 12px; font-size: 0.85em; <?php echo ($filtro==='semana' || $filtro==='')?'background: var(--primary); color: white;':''; ?>">Esta Semana</a>
    <a href="reportes.php?filtro=mes" class="btn-action" style="padding: 8px 12px; font-size: 0.85em; <?php echo ($filtro==='mes')?'background: var(--primary); color: white;':''; ?>">Este Mes</a>
    <a href="reportes.php?filtro=trimestre" class="btn-action" style="padding: 8px 12px; font-size: 0.85em; <?php echo ($filtro==='trimestre')?'background: var(--primary); color: white;':''; ?>">Trimestre</a>
</div>

<!-- Filtro Rango Personalizado -->
<div class="card">
    <form method="GET" action="reportes.php">
        <div class="filtro-fechas-container">
            <div class="filtro-fila-inputs">
                <div class="filtro-campo">
                    <label style="font-size: 0.75em; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 3px;">Desde</label>
                    <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" class="form-control" required>
                </div>
                <div class="filtro-campo">
                    <label style="font-size: 0.75em; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 3px;">Hasta</label>
                    <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn-submit filtro-btn">Buscar</button>
        </div>
    </form>
</div>

<!-- Métricas del Rango -->
<div class="metrics-grid" style="margin-bottom: 20px;">
    <div class="metric-box">
        <label>Ganancia</label>
        <span style="color: var(--primary);">$<?php echo number_format($total_servicios, 2); ?></span>
    </div>
    <div class="metric-box">
        <label>Cobrado</label>
        <span style="color: var(--success);">$<?php echo number_format($total_pagos, 2); ?></span>
    </div>
    <div class="metric-box">
        <label>Pendiente</label>
        <span style="color: <?php echo ($por_cobrar > 0) ? 'var(--danger)' : 'var(--text-muted)'; ?>;">
            $<?php echo number_format($por_cobrar_val, 2); ?>
        </span>
    </div>
</div>

<!-- Gráfico Redondo (Dona) -->
<div class="card" style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: center;">
    <h3 style="margin-top: 0; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; width: 100%; text-align: left;">Distribución de Cobros</h3>
    <div style="width: 100%; max-width: 280px; padding: 10px 0;">
        <canvas id="graficoCobros"></canvas>
    </div>
</div>

<!-- Lista de Pagos Recibidos -->
<div class="card">
    <h3 style="margin-top: 0; font-size: 1.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Detalle de Pagos Recibidos</h3>
    <?php if ($res_pagos->num_rows > 0): ?>
        <?php while ($pago = $res_pagos->fetch_assoc()): ?>
            <div class="activity-item">
                <div>
                    <strong style="display: block; font-size: 0.95em;"><?php echo htmlspecialchars($pago['cliente_nombre']); ?></strong>
                    <span style="font-size: 0.78em; color: var(--text-muted);">
                        📅 <?php echo date('d/m/Y h:i A', strtotime($pago['fecha_registro'])); ?> | 💳 <?php echo htmlspecialchars($pago['metodo_pago']); ?> (Ciclo <?php echo $pago['ciclo']; ?>)
                    </span>
                </div>
                <div style="text-align: right;">
                    <span style="font-weight: bold; color: var(--success); font-size: 1em;">+$<?php echo number_format($pago['monto'], 2); ?></span>
                    <br>
                    <a href="editar_registro.php?id=<?php echo $pago['id']; ?>&tipo=pago" style="font-size: 0.8em; color: var(--primary); text-decoration: none;">✏️ Editar</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9em; margin: 15px 0;">No se encontraron pagos registrados en este periodo.</p>
    <?php endif; ?>
</div>

</div> <!-- Cierre container -->

<!-- Script para inicializar el gráfico con Chart.js -->
<script>
const ctx = document.getElementById('graficoCobros').getContext('2d');
const graficoCobros = new Chart(ctx, {
    type: 'doughnut', // Gráfico tipo dona (redondo moderno)
    data: {
        labels: ['Cobrado', 'Pendiente'],
        datasets: [{
            data: [<?php echo $total_pagos; ?>, <?php echo $por_cobrar_val; ?>],
            backgroundColor: [
                '#2ecc71', // Verde para lo cobrado (var(--success))
                '#e74c3c'  // Rojo/Naranja para lo pendiente (var(--danger))
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-color') || '#ccc',
                    font: {
                        size: 12
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>