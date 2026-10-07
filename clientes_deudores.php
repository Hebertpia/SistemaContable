<?php
require_once 'auth.php';
require_once 'conexion.php';

// Cargar la última plantilla guardada ordenando por ID descendente
$plantilla_base = "Hola {nombre}, le recordamos que presenta un saldo pendiente de \${monto}. Por favor comunicarse para coordinar el pago. ¡Muchas gracias!";
$res_cfg = $conn->query("SELECT valor FROM configuracion WHERE clave IN ('plantilla_whatsapp', 'mensaje_whatsapp') AND valor IS NOT NULL AND valor != '' ORDER BY id DESC LIMIT 1");
if ($res_cfg && $res_cfg->num_rows > 0) {
    $val = $res_cfg->fetch_assoc()['valor'];
    if (!empty($val)) {
        $plantilla_base = $val;
    }
}

// Inspeccionar dinámicamente la columna de cédula en la tabla 'clientes'
$res_c_cols = $conn->query("SHOW COLUMNS FROM clientes");
$campo_cedula = 'id';
if ($res_c_cols) {
    while ($row = $res_c_cols->fetch_assoc()) {
        $col_f = strtolower($row['Field']);
        if ($col_f === 'cedula' || $col_f === 'ci' || $col_f === 'dni') {
            $campo_cedula = $col_f;
            break;
        }
    }
}

// Consultar clientes activos incluyendo cédula real
$sql_clientes = "SELECT id, nombre, telefono, {$campo_cedula} AS cedula_real FROM clientes WHERE activo = 1 ORDER BY nombre ASC";
$res_clientes = $conn->query($sql_clientes);

$deudores = [];
$total_general_deuda = 0.0;

if ($res_clientes && $res_clientes->num_rows > 0) {
    while ($c = $res_clientes->fetch_assoc()) {
        $c_id = $c['id'];

        // Obtener el ciclo actual
        $stmt_ciclo = $conn->prepare("SELECT IFNULL(MAX(ciclo), 1) AS ciclo_actual FROM servicios WHERE cliente_id = ?");
        $stmt_ciclo->bind_param("i", $c_id);
        $stmt_ciclo->execute();
        $ciclo_actual = $stmt_ciclo->get_result()->fetch_assoc()['ciclo_actual'] ?? 1;

        // Total servicios del ciclo actual
        $stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_s FROM servicios WHERE cliente_id = ? AND ciclo = ?");
        $stmt_s->bind_param("ii", $c_id, $ciclo_actual);
        $stmt_s->execute();
        $tot_s = floatval($stmt_s->get_result()->fetch_assoc()['total_s']);

        // Total pagos del ciclo actual
        $stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_p FROM pagos WHERE cliente_id = ? AND ciclo = ?");
        $stmt_p->bind_param("ii", $c_id, $ciclo_actual);
        $stmt_p->execute();
        $tot_p = floatval($stmt_p->get_result()->fetch_assoc()['total_p']);

        $deuda = $tot_s - $tot_p;

        if ($deuda > 0) {
            $c['deuda'] = $deuda;
            $c['ciclo'] = $ciclo_actual;

            // Formatear enlace a WhatsApp
            $tel_limpio = preg_replace('/[^0-9]/', '', $c['telefono']);
            if (!empty($tel_limpio)) {
                if (substr($tel_limpio, 0, 1) === '0') {
                    $tel_limpio = '58' . substr($tel_limpio, 1);
                } elseif (strlen($tel_limpio) === 10 && strpos($tel_limpio, '58') !== 0) {
                    $tel_limpio = '58' . $tel_limpio;
                }

                $monto_fmt = number_format($deuda, 2);

                // Reemplazo flexible de variables {nombre}, {cliente}, {monto}, {deuda}, {ciclo}
                $txt = str_replace(
                    ['{nombre}', '{cliente}', '{monto}', '{deuda}', '{ciclo}'],
                    [$c['nombre'], $c['nombre'], $monto_fmt, $monto_fmt, $ciclo_actual],
                    $plantilla_base
                );
                $c['ws_url'] = "https://wa.me/" . $tel_limpio . "?text=" . urlencode($txt);
            } else {
                $c['ws_url'] = null;
            }

            $deudores[] = $c;
            $total_general_deuda += $deuda;
        }
    }
}

include 'header.php';
?>

<!-- Separación corregida con respecto al Header -->
<div style="margin: 24px 0 20px 0;">
    <h2 style="margin: 0; font-size: 1.3em;">⚠️ Clientes con Deuda Pendiente</h2>
</div>

<!-- Tarjeta Métricas -->
<div class="metrics-grid" style="margin-bottom: 16px;">
    <div class="metric-box" style="grid-column: span 3; background: var(--bg-card); padding: 16px;">
        <label>Total Deuda por Cobrar</label>
        <span style="font-size: 1.5em; color: var(--danger); font-weight: bold;">$<?php echo number_format($total_general_deuda, 2); ?></span>
        <div style="font-size: 0.8em; color: var(--text-muted); margin-top: 4px;"><?php echo count($deudores); ?> cliente(s) en mora</div>
    </div>
</div>

<div class="card">
    <!-- Buscador en tiempo real -->
    <div class="form-group" style="margin-bottom: 12px;">
        <input type="text" id="buscar_deudor" class="form-control" placeholder="🔍 Buscar cliente en mora..." onkeyup="filtrarDeudores()">
    </div>

    <div id="lista_deudores">
        <?php if (!empty($deudores)): ?>
            <?php foreach ($deudores as $d): 
                $ci_display = !empty($d['cedula_real']) ? $d['cedula_real'] : $d['id'];
            ?>
                <div class="activity-item item-deudor">
                    <div>
                        <strong class="nombre-deudor" style="display: block; font-size: 0.95em; color: var(--text-main);"><?php echo htmlspecialchars($d['nombre']); ?></strong>
                        <span style="font-size: 0.78em; color: var(--text-muted);">
                            🆔 C.I.: <?php echo htmlspecialchars($ci_display); ?> | 📞 Tel: <?php echo htmlspecialchars($d['telefono'] ?: 'N/A'); ?> | Ciclo <?php echo $d['ciclo']; ?>
                        </span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-weight: bold; color: var(--danger); font-size: 1em; display: block; margin-bottom: 6px;">
                            $<?php echo number_format($d['deuda'], 2); ?>
                        </span>
                        <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                            <?php if ($d['ws_url']): ?>
                                <a href="<?php echo $d['ws_url']; ?>" target="_blank" class="btn-action" style="padding: 4px 8px; font-size: 0.75em; background: #25D366; color: white; border: none; text-decoration: none;">📲 Ws</a>
                            <?php endif; ?>
                            <a href="estado_cuenta.php?cliente_id=<?php echo $d['id']; ?>" class="btn-action" style="padding: 4px 8px; font-size: 0.75em; text-decoration: none;">📄 Cuenta</a>
                            <a href="registrar_pago.php?cliente_id=<?php echo $d['id']; ?>" class="btn-action" style="padding: 4px 8px; font-size: 0.75em; background: var(--success); color: white; border: none; text-decoration: none;">💵 Cobrar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.9em; margin: 15px 0;">🎉 ¡Excelente! No hay clientes con deudas pendientes en este momento.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function filtrarDeudores() {
    const filter = document.getElementById('buscar_deudor').value.toLowerCase();
    const items = document.querySelectorAll('.item-deudor');

    items.forEach(item => {
        const nombre = item.querySelector('.nombre-deudor').innerText.toLowerCase();
        item.style.display = nombre.includes(filter) ? 'flex' : 'none';
    });
}
</script>

</div> <!-- Cierre container -->
</body>
</html>