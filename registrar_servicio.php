<?php
require_once 'auth.php';
require_once 'conexion.php';

date_default_timezone_set('America/Caracas');

$mensaje = "";
$tipo_alerta = "";

// Token de seguridad contra envíos duplicados en la sesión
if (empty($_SESSION['token_servicio'])) {
    $_SESSION['token_servicio'] = bin2hex(random_bytes(32));
}

// Inspección dinámica de la columna de cédula
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

// Cargar clientes activos
$sql_clientes = "SELECT id, nombre, {$campo_cedula} AS cedula_real FROM clientes WHERE activo = 1 ORDER BY nombre ASC";
$res_clientes = $conn->query($sql_clientes);
$clientes_lista = [];
if ($res_clientes) {
    while ($row = $res_clientes->fetch_assoc()) {
        $clientes_lista[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación de seguridad contra reenvíos duplicados
    $token_enviado = isset($_POST['token_servicio']) ? $_POST['token_servicio'] : '';
    
    if (empty($token_enviado) || !hash_equals($_SESSION['token_servicio'], $token_enviado)) {
        $mensaje = "Acción no permitida o el formulario ya fue procesado anteriormente.";
        $tipo_alerta = "warning";
    } else {
        // Invalidar el token inmediatamente para evitar reenvíos
        unset($_SESSION['token_servicio']);

        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $concepto   = isset($_POST['concepto']) ? trim($_POST['concepto']) : '';
        $monto      = isset($_POST['monto']) ? floatval($_POST['monto']) : 0.0;
        
        // Capturar la fecha personalizada o usar la actual si está vacía
        $fecha_input = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';
        $fecha_registro = !empty($fecha_input) ? $fecha_input . ':00' : date('Y-m-d H:i:s');

        if ($cliente_id > 0 && !empty($concepto) && $monto > 0) {
            $stmt_ciclo = $conn->prepare("SELECT IFNULL(MAX(ciclo), 1) AS ciclo_actual FROM servicios WHERE cliente_id = ?");
            $stmt_ciclo->bind_param("i", $cliente_id);
            $stmt_ciclo->execute();
            
            $res_ciclo = $stmt_ciclo->get_result()->fetch_assoc();
            $ciclo_activo = $res_ciclo ? $res_ciclo['ciclo_actual'] : 1;

            $stmt_insert = $conn->prepare("INSERT INTO servicios (cliente_id, concepto, monto, ciclo, fecha_registro) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("isdis", $cliente_id, $concepto, $monto, $ciclo_activo, $fecha_registro);

            if ($stmt_insert->execute()) {
                $mensaje = "¡Trabajo / servicio registrado con éxito!";
                $tipo_alerta = "success";
            } else {
                $mensaje = "Error al registrar: " . $conn->error;
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Por favor complete todos los campos obligatorios.";
            $tipo_alerta = "warning";
        }
    }
}

// Generar un nuevo token fresco para la vista actual si no existe
if (empty($_SESSION['token_servicio'])) {
    $_SESSION['token_servicio'] = bin2hex(random_bytes(32));
}

// Fecha por defecto para el input (formato YYYY-MM-DDTHH:MM)
$fecha_por_defecto = date('Y-m-d\TH:i');

include 'header.php';
?>

<style>
.custom-select-container {
    position: relative;
    width: 100%;
}
.custom-select-list {
    max-height: 180px;
    overflow-y: auto;
    background: transparent;
    border: 1px solid var(--border-color, #334155);
    border-radius: 8px;
    margin-top: 6px;
}
.custom-select-item {
    padding: 10px 12px;
    color: var(--text-main, #ffffff);
    background: transparent;
    cursor: pointer;
    font-size: 0.9em;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: background 0.2s ease, color 0.2s ease;
}
.custom-select-item:last-child {
    border-bottom: none;
}
.custom-select-item:hover {
    background: rgba(255, 255, 255, 0.06);
}
.custom-select-item.selected {
    background: var(--primary, #007bff) !important;
    color: #ffffff !important;
    font-weight: bold;
}
/* Solución para evitar desbordamiento del input de fecha en pantallas pequeñas */
input[type="datetime-local"].form-control {
    font-size: 0.85em;
    padding: 10px 8px;
    box-sizing: border-box;
    max-width: 100%;
}
</style>

<div style="margin: 24px 0 20px 0;">
    <h2 style="margin: 0; font-size: 1.3em; color: var(--text-main);">📝 Registrar Trabajo / Servicio</h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : (($tipo_alerta === 'warning') ? 'rgba(255, 193, 7, 0.15)' : 'rgba(220, 53, 69, 0.15)'); ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '❌ ') . htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="registrar_servicio.php" method="POST" id="formServicio">
        <!-- Token anti-duplicidad -->
        <input type="hidden" name="token_servicio" value="<?php echo $_SESSION['token_servicio']; ?>">
        <input type="hidden" name="cliente_id" id="cliente_id" value="0">
        <input type="hidden" id="nombre_cliente_seleccionado" value="">

        <div class="form-group">
            <label>Buscar / Seleccionar Cliente</label>
            <input type="text" id="buscar_cliente" class="form-control" placeholder="🔍 Escriba nombre o cédula..." style="margin-bottom: 8px;" onkeyup="filtrarClientes()">
            
            <div class="custom-select-container">
                <div class="custom-select-list" id="lista_clientes_custom">
                    <?php foreach ($clientes_lista as $c): 
                        $ci_disp = !empty($c['cedula_real']) ? $c['cedula_real'] : $c['id'];
                        $texto_mostrar = htmlspecialchars($c['nombre']) . " — C.I. " . htmlspecialchars($ci_disp);
                    ?>
                        <div class="custom-select-item" 
                             data-id="<?php echo $c['id']; ?>" 
                             data-nombre="<?php echo $texto_mostrar; ?>"
                             onclick="seleccionarCliente(this, <?php echo $c['id']; ?>, '<?php echo addslashes($texto_mostrar); ?>')">
                            <?php echo $texto_mostrar; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="infoSaldo" style="margin-bottom: 15px; padding: 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.88em; color: var(--text-main);">
            Estado del cliente: <strong>Seleccione un cliente</strong>
        </div>

        <div class="form-group">
            <label>Fecha y Hora del Servicio</label>
            <input type="datetime-local" name="fecha_registro" class="form-control" value="<?php echo $fecha_por_defecto; ?>" required>
        </div>

        <div class="form-group">
            <label>Descripción / Concepto del Servicio</label>
            <input type="text" name="concepto" id="conceptoServicio" class="form-control" placeholder="Ej. Carrera Nocturna, Mantenimiento..." required>
        </div>

        <div class="form-group">
            <label>Monto a Cobrar ($)</label>
            <input type="number" step="0.01" name="monto" id="montoServicio" class="form-control" placeholder="0.00" required>
        </div>

        <button type="button" class="btn-submit" onclick="prepararConfirmacion()" style="margin-top: 10px;">Guardar Servicio</button>
    </form>
</div>

<!-- Modal de Confirmación -->
<div class="modal-backdrop" id="modalConfirmar" style="display: none;">
    <div class="modal-content" style="background: var(--bg-card); color: var(--text-main);">
        <h3 style="margin-top: 0; border-bottom: 2px solid var(--primary); padding-bottom: 8px;">⚠️ Confirmar Nuevo Servicio</h3>
        <p style="color: var(--text-muted); font-size: 0.9em;">Verifique los detalles antes de guardar:</p>
        
        <div style="background: var(--bg-card); padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid var(--border-color); font-size: 0.9em; line-height: 1.6; color: var(--text-main);">
            <strong>Cliente:</strong> <span id="modal_txt_cliente"></span><br>
            <strong>Concepto:</strong> <span id="modal_txt_concepto"></span><br>
            <strong>Monto a Cargar:</strong> $<span id="modal_txt_monto"></span>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
            <button type="button" class="btn-action" onclick="cerrarModal()" style="flex: 1;">Cancelar</button>
            <button type="button" id="btnConfirmarEnvioServicio" class="btn-submit" onclick="procesarEnvio()" style="flex: 1;">Sí, Guardar</button>
        </div>
    </div>
</div>

<script>
function seleccionarCliente(elemento, id, nombre) {
    const items = document.querySelectorAll('.custom-select-item');
    items.forEach(item => item.classList.remove('selected'));

    elemento.classList.add('selected');
    document.getElementById('cliente_id').value = id;
    document.getElementById('nombre_cliente_seleccionado').value = nombre;

    verificarSaldoFavor();
}

function filtrarClientes() {
    const input = document.getElementById('buscar_cliente').value.toLowerCase();
    const items = document.querySelectorAll('.custom-select-item');

    items.forEach(item => {
        const txt = item.getAttribute('data-nombre').toLowerCase();
        item.style.display = txt.includes(input) ? "block" : "none";
    });
}

function verificarSaldoFavor() {
    const clienteId = document.getElementById('cliente_id').value;
    const infoDiv = document.getElementById('infoSaldo');

    if (!clienteId || clienteId === '0') {
        infoDiv.innerHTML = "Estado del cliente: <strong>Seleccione un cliente</strong>";
        return;
    }

    fetch('obtener_deuda.php?cliente_id=' + clienteId)
        .then(res => res.json())
        .then(data => {
            const deuda = parseFloat(data.deuda) || 0;
            const saldoFavor = parseFloat(data.saldo_favor) || 0;

            if (saldoFavor > 0) {
                infoDiv.innerHTML = "🎁 Saldo a favor disponible: <strong style='color: var(--success);'>$" + saldoFavor.toFixed(2) + "</strong> (se descontará en el total).";
            } else if (deuda > 0) {
                infoDiv.innerHTML = "⚠️ Deuda previa pendiente: <strong style='color: var(--danger);'>$" + deuda.toFixed(2) + "</strong>";
            } else {
                infoDiv.innerHTML = "✅ Cliente al día ($0.00 pendiente).";
            }
        })
        .catch(() => {
            infoDiv.innerHTML = "Estado del cliente: <strong>No disponible</strong>";
        });
}

function prepararConfirmacion() {
    const clienteId = document.getElementById('cliente_id').value;
    const concepto = document.getElementById('conceptoServicio').value.trim();
    const monto = parseFloat(document.getElementById('montoServicio').value) || 0;
    const nombreCliente = document.getElementById('nombre_cliente_seleccionado').value;

    if (!clienteId || clienteId === '0') {
        alert('Por favor seleccione un cliente de la lista.');
        return;
    }
    if (!concepto) {
        alert('Por favor ingrese el concepto del servicio.');
        return;
    }
    if (monto <= 0) {
        alert('Por favor ingrese un monto válido mayor a cero.');
        return;
    }

    document.getElementById('modal_txt_cliente').innerText = nombreCliente;
    document.getElementById('modal_txt_concepto').innerText = concepto;
    document.getElementById('modal_txt_monto').innerText = monto.toFixed(2);

    document.getElementById('modalConfirmar').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalConfirmar').style.display = 'none';
}

function procesarEnvio() {
    const btn = document.getElementById('btnConfirmarEnvioServicio');
    btn.disabled = true;
    btn.innerText = "Guardando...";
    document.getElementById('formServicio').submit();
}
</script>

</div> <!-- Cierre container -->
</body>
</html>