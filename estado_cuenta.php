<?php
require_once 'auth.php';
require_once 'conexion.php';

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;

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

// Consulta únicamente los clientes activos
$sql_c = "SELECT id, nombre, {$campo_cedula} AS cedula_real FROM clientes WHERE activo = 1 ORDER BY nombre ASC";
$res_c = $conn->query($sql_c);
$clientes = [];
if ($res_c) {
    while ($row = $res_c->fetch_assoc()) {
        $clientes[] = $row;
    }
}

// Obtener métodos de pago existentes en la base de datos para el selector
$metodos_pago_disponibles = ['Pago móvil', 'Efectivo', 'USDT', 'Zelle', 'Transferencia', 'Otro'];
$res_mp = $conn->query("SELECT DISTINCT metodo_pago FROM pagos WHERE metodo_pago IS NOT NULL AND metodo_pago != ''");
if ($res_mp) {
    while ($row_mp = $res_mp->fetch_assoc()) {
        if (!in_array($row_mp['metodo_pago'], $metodos_pago_disponibles)) {
            $metodos_pago_disponibles[] = $row_mp['metodo_pago'];
        }
    }
}

$datos_cliente = null;
$servicios = [];
$pagos = [];
$total_servicios = 0;
$total_pagos = 0;
$ciclo_activo = 1;

if ($cliente_id > 0) {
    $stmt_cli = $conn->prepare("SELECT id, nombre, telefono, {$campo_cedula} AS cedula_real FROM clientes WHERE id = ? AND activo = 1");
    $stmt_cli->bind_param("i", $cliente_id);
    $stmt_cli->execute();
    $datos_cliente = $stmt_cli->get_result()->fetch_assoc();

    if ($datos_cliente) {
        // Obtener servicios
        $stmt_s = $conn->prepare("SELECT id, concepto, monto, fecha_registro, ciclo FROM servicios WHERE cliente_id = ? ORDER BY fecha_registro ASC");
        $stmt_s->bind_param("i", $cliente_id);
        $stmt_s->execute();
        $res_s = $stmt_s->get_result();
        while ($r = $res_s->fetch_assoc()) {
            $servicios[] = $r;
            $total_servicios += floatval($r['monto']);
            if (isset($r['ciclo']) && $r['ciclo'] > 0) {
                $ciclo_activo = $r['ciclo'];
            }
        }

        // Obtener pagos
        $stmt_p = $conn->prepare("SELECT id, monto, metodo_pago, fecha_registro, ciclo FROM pagos WHERE cliente_id = ? ORDER BY fecha_registro ASC");
        $stmt_p->bind_param("i", $cliente_id);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        while ($r = $res_p->fetch_assoc()) {
            $pagos[] = $r;
            $total_pagos += floatval($r['monto']);
            if (isset($r['ciclo']) && $r['ciclo'] > 0) {
                $ciclo_activo = $r['ciclo'];
            }
        }
    }
}

$diferencia = $total_servicios - $total_pagos;

// Cargar la última plantilla guardada ordenando por ID descendente
$plantilla_msj = "Hola {nombre}, te saludo cordialmente. Te comparto el recordatorio de tu saldo pendiente actual de \${monto} correspondiente al Ciclo {ciclo}. Quedo atento, ¡muchas gracias!";
$res_cfg = $conn->query("SELECT valor FROM configuracion WHERE clave IN ('plantilla_whatsapp', 'mensaje_whatsapp') AND valor IS NOT NULL AND valor != '' ORDER BY id DESC LIMIT 1");
if ($res_cfg && $res_cfg->num_rows > 0) {
    $val = $res_cfg->fetch_assoc()['valor'];
    if (!empty($val)) {
        $plantilla_msj = $val;
    }
}

// Construir enlace de WhatsApp
$url_whatsapp = "";
if ($datos_cliente && !empty($datos_cliente['telefono'])) {
    $telefono_limpio = preg_replace('/[^0-9]/', '', $datos_cliente['telefono']);

    if (strpos($telefono_limpio, '0') === 0) {
        $telefono_limpio = '58' . substr($telefono_limpio, 1);
    } elseif (strlen($telefono_limpio) === 10 && strpos($telefono_limpio, '58') !== 0) {
        $telefono_limpio = '58' . $telefono_limpio;
    }

    $monto_formateado = number_format(abs($diferencia), 2);

    $mensaje_final = str_replace(
        ['{nombre}', '{cliente}', '{monto}', '{deuda}', '{ciclo}'],
        [$datos_cliente['nombre'], $datos_cliente['nombre'], $monto_formateado, $monto_formateado, $ciclo_activo],
        $plantilla_msj
    );

    $url_whatsapp = "https://wa.me/" . $telefono_limpio . "?text=" . urlencode($mensaje_final);
}

include 'header.php';
?>

<!-- Encabezado con distancia (margin-top) respecto al Header -->
<div style="margin-top: 25px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="margin: 0; font-size: 1.2em;">📄 Estado de Cuenta</h2>
    <a href="clientes.php" class="btn-action" style="padding: 6px 12px; font-size: 0.8em;">👥 Volver</a>
</div>

<!-- Selector de Cliente -->
<div class="card" style="margin-bottom: 16px;">
    <form action="estado_cuenta.php" method="GET">
        <div class="form-group" style="margin-bottom: 0;">
            <label>Seleccionar Cliente:</label>
            <select name="cliente_id" onchange="this.form.submit()" class="form-control" style="margin-top: 5px;">
                <option value="">-- Seleccione un cliente --</option>
                <?php foreach ($clientes as $c): 
                    $ci_select = !empty($c['cedula_real']) ? $c['cedula_real'] : $c['id'];
                ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $cliente_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nombre']) . " — C.I. " . htmlspecialchars($ci_select); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($datos_cliente): 
    $ci_display = !empty($datos_cliente['cedula_real']) ? $datos_cliente['cedula_real'] : $datos_cliente['id'];
?>
    <div class="card" style="margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 14px;">
            <div>
                <strong style="font-size: 1.1em; display: block; color: var(--text-main);"><?php echo htmlspecialchars($datos_cliente['nombre']); ?></strong>
                <small style="color: var(--text-muted); display: block; margin-top: 2px;">
                    🆔 C.I.: <?php echo htmlspecialchars($ci_display); ?> | 📞 Tel: <?php echo htmlspecialchars($datos_cliente['telefono'] ?: 'N/A'); ?>
                </small>
            </div>

            <?php if (!empty($url_whatsapp)): ?>
                <a href="<?php echo $url_whatsapp; ?>" target="_blank" style="background: #25D366; color: white; padding: 8px 12px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 0.85em; display: inline-flex; align-items: center; gap: 6px;">
                    📲 Recordar por WhatsApp
                </a>
            <?php endif; ?>
        </div>

        <!-- Servicios / Trabajos Realizados -->
        <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 1em; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">📋 Servicios / Trabajos Realizados</h3>
        <?php if (!empty($servicios)): ?>
            <?php foreach ($servicios as $s): 
                $fecha_input = date('Y-m-d\TH:i', strtotime($s['fecha_registro']));
            ?>
                <div style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 6px;">
                        <strong style="font-size: 0.9em; word-break: break-word; color: var(--text-main);"><?php echo htmlspecialchars($s['concepto']); ?></strong>
                        <span style="font-weight: bold; font-size: 0.95em; white-space: nowrap; color: var(--text-main);">$<?php echo number_format($s['monto'], 2); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.75em; border-top: 1px dashed var(--border-color); padding-top: 6px;">
                        <span style="color: var(--text-muted);">
                            📅 <?php echo date('d/m/Y h:i A', strtotime($s['fecha_registro'])); ?>
                        </span>
                        
                        <div style="display: flex; gap: 12px;">
                            <button type="button" onclick="abrirModalEditar(<?php echo $s['id']; ?>, '<?php echo addslashes($s['concepto']); ?>', <?php echo $s['monto']; ?>, '<?php echo $fecha_input; ?>')" style="background:none; border:none; color:var(--primary, #007bff); cursor:pointer; padding:0; font-weight: bold; font-size: 1em;">✏️ Editar</button>
                            <button type="button" onclick="abrirModalEliminar(<?php echo $s['id']; ?>, '<?php echo addslashes($s['concepto']); ?>', <?php echo $s['monto']; ?>)" style="background:none; border:none; color:var(--danger, #dc3545); cursor:pointer; padding:0; font-weight: bold; font-size: 1em;">🗑️ Eliminar</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.85em; margin: 10px 0;">No posee servicios registrados.</p>
        <?php endif; ?>

        <!-- Pagos y Abonos Recibidos -->
        <h3 style="margin-top: 12px; margin-bottom: 8px; font-size: 1em; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">💵 Pagos y Abonos Recibidos</h3>
        <?php if (!empty($pagos)): ?>
            <?php foreach ($pagos as $p): 
                $fecha_pago_input = date('Y-m-d\TH:i', strtotime($p['fecha_registro']));
            ?>
                <div style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 6px;">
                        <strong style="font-size: 0.9em; word-break: break-word; color: var(--text-main);">💳 <?php echo htmlspecialchars($p['metodo_pago']); ?></strong>
                        <span style="font-weight: bold; color: var(--success); font-size: 0.95em; white-space: nowrap;">+$<?php echo number_format($p['monto'], 2); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.75em; border-top: 1px dashed var(--border-color); padding-top: 6px;">
                        <span style="color: var(--text-muted);">
                            📅 <?php echo date('d/m/Y h:i A', strtotime($p['fecha_registro'])); ?>
                        </span>
                        
                        <div style="display: flex; gap: 12px;">
                            <button type="button" onclick="abrirModalEditarPago(<?php echo $p['id']; ?>, '<?php echo addslashes($p['metodo_pago']); ?>', <?php echo $p['monto']; ?>, '<?php echo $fecha_pago_input; ?>')" style="background:none; border:none; color:var(--primary, #007bff); cursor:pointer; padding:0; font-weight: bold; font-size: 1em;">✏️ Editar</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.85em; margin: 10px 0;">No posee pagos registrados.</p>
        <?php endif; ?>

        <!-- Balance / Totales -->
        <div style="margin-top: 16px; padding: 12px; border-radius: 8px; text-align: right; background: <?php echo ($diferencia < 0) ? 'rgba(40, 167, 69, 0.15)' : (($diferencia > 0) ? 'rgba(220, 53, 69, 0.15)' : 'rgba(108, 117, 125, 0.15)'); ?>;">
            <p style="margin: 3px 0; font-size: 0.88em; color: var(--text-main);">Total Trabajos: <strong>$<?php echo number_format($total_servicios, 2); ?></strong></p>
            <p style="margin: 3px 0; font-size: 0.88em; color: var(--text-main);">Total Abonado: <strong>$<?php echo number_format($total_pagos, 2); ?></strong></p>
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 8px 0;">
            <?php if ($diferencia < 0): ?>
                <h3 style="margin: 5px 0; color: var(--success); font-size: 1.1em;">🎁 SALDO A FAVOR: $<?php echo number_format(abs($diferencia), 2); ?></h3>
            <?php elseif ($diferencia > 0): ?>
                <h3 style="margin: 5px 0; color: var(--danger); font-size: 1.1em;">⚠️ SALDO PENDIENTE: $<?php echo number_format($diferencia, 2); ?></h3>
            <?php else: ?>
                <h3 style="margin: 5px 0; color: var(--text-main); font-size: 1.1em;">✅ AL DÍA ($0.00)</h3>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

</div> <!-- Cierre container -->

<!-- ========================================== -->
<!-- VENTANAS EMERGENTES (MODALES) DE CONFIRMACIÓN -->
<!-- ========================================== -->

<!-- Modal: Editar Servicio -->
<div class="modal-backdrop" id="modalEditarServicio" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; padding: 15px;">
    <div class="modal-content" style="background: var(--bg-card, #fff); color: var(--text-main, #333); padding: 20px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <h3 style="margin-top: 0; border-bottom: 2px solid var(--primary, #007bff); padding-bottom: 8px; font-size: 1.1em;">✏️ Editar Servicio</h3>
        <p style="color: var(--text-muted, #666); font-size: 0.85em; margin-bottom: 15px;">Modifica los detalles del servicio:</p>
        
        <form action="editar_servicio.php" method="POST" onsubmit="return confirmarCambiosServicio(event)">
            <input type="hidden" name="servicio_id" id="edit_servicio_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Concepto:</label>
                <input type="text" name="concepto" id="edit_concepto" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Monto ($):</label>
                <input type="number" step="0.01" name="monto" id="edit_monto" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Fecha y Hora:</label>
                <input type="datetime-local" name="fecha_registro" id="edit_fecha" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="cerrarModalEditar()" style="flex: 1; padding: 8px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn-submit" style="flex: 1; padding: 8px; background: var(--primary, #007bff); color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Pago -->
<div class="modal-backdrop" id="modalEditarPago" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; padding: 15px;">
    <div class="modal-content" style="background: var(--bg-card, #fff); color: var(--text-main, #333); padding: 20px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <h3 style="margin-top: 0; border-bottom: 2px solid var(--primary, #007bff); padding-bottom: 8px; font-size: 1.1em;">✏️ Editar Pago</h3>
        <p style="color: var(--text-muted, #666); font-size: 0.85em; margin-bottom: 15px;">Modifica los detalles del pago recibido:</p>
        
        <form action="editar_pago.php" method="POST" onsubmit="return confirmarCambiosPago(event)">
            <input type="hidden" name="pago_id" id="edit_pago_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Método de Pago:</label>
                <select name="metodo_pago" id="edit_metodo_pago" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                    <option value="">-- Seleccione un método --</option>
                    <?php foreach ($metodos_pago_disponibles as $metodo): ?>
                        <option value="<?php echo htmlspecialchars($metodo); ?>"><?php echo htmlspecialchars($metodo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Monto ($):</label>
                <input type="number" step="0.01" name="monto" id="edit_pago_monto" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-size: 0.85em; font-weight: bold; display: block; margin-bottom: 4px;">Fecha y Hora:</label>
                <input type="datetime-local" name="fecha_registro" id="edit_pago_fecha" class="form-control" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="cerrarModalEditarPago()" style="flex: 1; padding: 8px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn-submit" style="flex: 1; padding: 8px; background: var(--primary, #007bff); color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Servicio -->
<div class="modal-backdrop" id="modalEliminarServicio" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; padding: 15px;">
    <div class="modal-content" style="background: var(--bg-card, #fff); color: var(--text-main, #333); padding: 20px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <h3 style="margin-top: 0; border-bottom: 2px solid var(--danger, #dc3545); padding-bottom: 8px; font-size: 1.1em; color: var(--danger, #dc3545);">⚠️ Eliminar Servicio</h3>
        <p style="color: var(--text-muted, #666); font-size: 0.85em; margin-bottom: 12px;">¿Estás seguro de que deseas eliminar este servicio por completo? Esta acción no se puede deshacer.</p>
        
        <div style="background: rgba(0,0,0,0.03); padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid var(--border-color, #ddd); font-size: 0.85em; line-height: 1.5;">
            <strong>Concepto:</strong> <span id="del_txt_concepto"></span><br>
            <strong>Monto:</strong> $<span id="del_txt_monto"></span>
        </div>

        <form action="eliminar_servicio.php" method="POST">
            <input type="hidden" name="servicio_id" id="del_servicio_id">
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="button" class="btn-action" onclick="cerrarModalEliminar()" style="flex: 1; padding: 8px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn-submit" style="flex: 1; padding: 8px; background: var(--danger, #dc3545); color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Sí, Eliminar</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS DE CONTROL DE MODALES -->
<script>
function abrirModalEditar(id, concepto, monto, fecha) {
    document.getElementById('edit_servicio_id').value = id;
    document.getElementById('edit_concepto').value = concepto;
    document.getElementById('edit_monto').value = monto;
    document.getElementById('edit_fecha').value = fecha;
    document.getElementById('modalEditarServicio').style.display = 'flex';
}

function cerrarModalEditar() {
    document.getElementById('modalEditarServicio').style.display = 'none';
}

function abrirModalEditarPago(id, metodo, monto, fecha) {
    document.getElementById('edit_pago_id').value = id;
    document.getElementById('edit_metodo_pago').value = metodo;
    document.getElementById('edit_pago_monto').value = monto;
    document.getElementById('edit_pago_fecha').value = fecha;
    document.getElementById('modalEditarPago').style.display = 'flex';
}

function cerrarModalEditarPago() {
    document.getElementById('modalEditarPago').style.display = 'none';
}

function abrirModalEliminar(id, concepto, monto) {
    document.getElementById('del_servicio_id').value = id;
    document.getElementById('del_txt_concepto').innerText = concepto;
    document.getElementById('del_txt_monto').innerText = monto.toFixed(2);
    document.getElementById('modalEliminarServicio').style.display = 'flex';
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminarServicio').style.display = 'none';
}

function confirmarCambiosServicio(event) {
    const seguro = confirm("¿Estás seguro de que deseas guardar los cambios realizados en este servicio?");
    if (!seguro) {
        event.preventDefault();
    }
    return seguro;
}

function confirmarCambiosPago(event) {
    const seguro = confirm("¿Estás seguro de que deseas guardar los cambios realizados en este pago?");
    if (!seguro) {
        event.preventDefault();
    }
    return seguro;
}
</script>

</body>
</html>