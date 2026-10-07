<?php
require_once 'auth.php';
require_once 'conexion.php';

date_default_timezone_set('America/Caracas');

$mensaje = "";
$tipo_alerta = "";
$cliente_seleccionado_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;

// Token de seguridad contra envíos duplicados en la sesión
if (empty($_SESSION['token_pago'])) {
    $_SESSION['token_pago'] = bin2hex(random_bytes(32));
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
    $token_enviado = isset($_POST['token_pago']) ? $_POST['token_pago'] : '';
    
    if (empty($token_enviado) || !hash_equals($_SESSION['token_pago'], $token_enviado)) {
        $mensaje = "Acción no permitida o el formulario ya fue procesado anteriormente.";
        $tipo_alerta = "warning";
    } else {
        // Invalidar el token inmediatamente para evitar reenvíos con F5 o doble clic
        unset($_SESSION['token_pago']);

        $cliente_id  = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $monto       = isset($_POST['monto']) ? floatval($_POST['monto']) : 0.0;
        $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : 'Pago móvil';
        
        // Capturar la fecha personalizada o usar la actual si está vacía
        $fecha_input = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';
        $fecha_registro = !empty($fecha_input) ? $fecha_input . ':00' : date('Y-m-d H:i:s');

        if ($cliente_id > 0 && $monto > 0) {
            $stmt_ciclo = $conn->prepare("SELECT IFNULL(MAX(ciclo), 1) AS ciclo_actual FROM servicios WHERE cliente_id = ?");
            $stmt_ciclo->bind_param("i", $cliente_id);
            $stmt_ciclo->execute();
            $res_ciclo = $stmt_ciclo->get_result()->fetch_assoc();
            $ciclo_activo = $res_ciclo['ciclo_actual'];

            $stmt_insert = $conn->prepare("INSERT INTO pagos (cliente_id, monto, metodo_pago, ciclo, fecha_registro) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("idsis", $cliente_id, $monto, $metodo_pago, $ciclo_activo, $fecha_registro);

            if ($stmt_insert->execute()) {
                $stmt_sum_s = $conn->prepare("SELECT SUM(monto) AS total_s FROM servicios WHERE cliente_id = ? AND ciclo = ?");
                $stmt_sum_s->bind_param("ii", $cliente_id, $ciclo_activo);
                $stmt_sum_s->execute();
                $tot_s = $stmt_sum_s->get_result()->fetch_assoc()['total_s'] ?? 0;

                $stmt_sum_p = $conn->prepare("SELECT SUM(monto) AS total_p FROM pagos WHERE cliente_id = ? AND ciclo = ?");
                $stmt_sum_p->bind_param("ii", $cliente_id, $ciclo_activo);
                $stmt_sum_p->execute();
                $tot_p = $stmt_sum_p->get_result()->fetch_assoc()['total_p'] ?? 0;

                $diferencia = $tot_s - $tot_p;

                if ($diferencia <= 0 && $tot_s > 0) {
                    $nuevo_ciclo = $ciclo_activo + 1;
                    $stmt_next = $conn->prepare("INSERT INTO servicios (cliente_id, concepto, monto, ciclo, fecha_registro) VALUES (?, 'INICIO DE NUEVO CICLO', 0, ?, ?)");
                    $stmt_next->bind_param("iis", $cliente_id, $nuevo_ciclo, $fecha_registro);
                    $stmt_next->execute();

                    if ($diferencia < 0) {
                        $favor = abs($diferencia);
                        $mensaje = "¡Pago registrado! Deuda saldada con un saldo a favor de $" . number_format($favor, 2) . ". Se renovó el ciclo contable.";
                    } else {
                        $mensaje = "¡Pago registrado con éxito! Cuenta en $0.00 y nuevo ciclo iniciado.";
                    }
                } else {
                    $mensaje = "¡Pago registrado con éxito! Saldo pendiente: $" . number_format($diferencia, 2);
                }
                $tipo_alerta = "success";
            } else {
                $mensaje = "Error al registrar pago: " . $conn->error;
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Seleccione un cliente e ingrese un monto válido mayor a cero.";
            $tipo_alerta = "warning";
        }
    }
}

// Generar un nuevo token fresco para la vista actual si no existe
if (empty($_SESSION['token_pago'])) {
    $_SESSION['token_pago'] = bin2hex(random_bytes(32));
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
    <h2 style="margin: 0; font-size: 1.3em; color: var(--text-main);">💵 Registrar Pago Recibido</h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : (($tipo_alerta === 'warning') ? 'rgba(255, 193, 7, 0.15)' : 'rgba(220, 53, 69, 0.15)'); ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '❌ ') . htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="registrar_pago.php" method="POST" id="formPago">
        <!-- Token anti-duplicidad -->
        <input type="hidden" name="token_pago" value="<?php echo $_SESSION['token_pago']; ?>">
        <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo $cliente_seleccionado_id; ?>">
        <input type="hidden" id="nombre_cliente_seleccionado" value="">

        <div class="form-group">
            <label>Buscar / Seleccionar Cliente</label>
            <input type="text" id="buscar_cliente" class="form-control" placeholder="🔍 Escriba nombre o cédula..." style="margin-bottom: 8px;" onkeyup="filtrarClientes()">
            
            <div class="custom-select-container">
                <div class="custom-select-list" id="lista_clientes_custom">
                    <?php foreach ($clientes_lista as $c): 
                        $ci_disp = !empty($c['cedula_real']) ? $c['cedula_real'] : $c['id'];
                        $texto_mostrar = htmlspecialchars($c['nombre']) . " — C.I. " . htmlspecialchars($ci_disp);
                        $es_seleccionado = ($c['id'] == $cliente_seleccionado_id);
                    ?>
                        <div class="custom-select-item <?php echo $es_seleccionado ? 'selected' : ''; ?>" 
                             data-id="<?php echo $c['id']; ?>" 
                             data-nombre="<?php echo $texto_mostrar; ?>"
                             onclick="seleccionarCliente(this, <?php echo $c['id']; ?>, '<?php echo addslashes($texto_mostrar); ?>')">
                            <?php echo $texto_mostrar; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="infoDeuda" style="margin-bottom: 15px; padding: 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.88em; color: var(--text-main);">
            Deuda actual: <strong>Seleccione un cliente</strong>
        </div>

        <div class="form-group">
            <label>Fecha y Hora del Pago</label>
            <input type="datetime-local" name="fecha_registro" class="form-control" value="<?php echo $fecha_por_defecto; ?>" required>
        </div>

        <div class="form-group">
            <label>Monto Recibido ($)</label>
            <input type="number" step="0.01" name="monto" id="montoPago" class="form-control" placeholder="0.00" required>
        </div>

        <div class="form-group">
            <label>Método de Pago</label>
            <select name="metodo_pago" id="metodo_pago" class="form-control">
                <option value="Pago móvil" selected>Pago móvil</option>
                <option value="Efectivo">Efectivo</option>
                <option value="USDT">USDT</option>
                <option value="Zelle">Zelle</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Otro">Otro</option>
            </select>
        </div>

        <button type="button" id="btnGuardarPago" class="btn-submit" onclick="prepararConfirmacion()" style="margin-top: 10px; background-color: var(--success, #28a745);">Guardar Pago</button>
    </form>
</div>

<!-- Modal de Confirmación -->
<div class="modal-backdrop" id="modalConfirmar" style="display: none;">
    <div class="modal-content" style="background: var(--bg-card); color: var(--text-main);">
        <h3 style="margin-top: 0; border-bottom: 2px solid var(--success); padding-bottom: 8px; color: var(--text-main);">⚠️ Confirmar Pago</h3>
        <p style="color: var(--text-muted); font-size: 0.9em;">Verifique los detalles antes de guardar:</p>
        
        <div style="background: var(--bg-card); padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid var(--border-color); font-size: 0.9em; line-height: 1.6; color: var(--text-main);">
            <strong>Cliente:</strong> <span id="modal_txt_cliente"></span><br>
            <strong>Monto Aportado:</strong> $<span id="modal_txt_monto"></span><br>
            <strong>Método de Pago:</strong> <span id="modal_txt_metodo"></span><br>
            <strong>Deuda Previa:</strong> $<span id="modal_txt_deuda"></span>
        </div>

        <div id="modal_alerta_favor" style="display: none; background: rgba(220, 53, 69, 0.15); color: var(--danger, #dc3545); padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.85em; border: 1px solid rgba(220, 53, 69, 0.3);">
            ℹ️ El monto ingresado supera la deuda. Generará un saldo a favor de $<span id="modal_txt_favor"></span> y reiniciará el ciclo.
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
            <button type="button" class="btn-action" onclick="cerrarModal()" style="flex: 1;">Cancelar</button>
            <button type="button" id="btnConfirmarEnvioPago" class="btn-submit" onclick="procesarEnvio()" style="flex: 1; background-color: var(--success, #28a745);">Sí, Registrar</button>
        </div>
    </div>
</div>

<script>
let deudaClienteActual = 0;

function seleccionarCliente(elemento, id, nombre) {
    const items = document.querySelectorAll('.custom-select-item');
    items.forEach(item => item.classList.remove('selected'));

    elemento.classList.add('selected');
    document.getElementById('cliente_id').value = id;
    document.getElementById('nombre_cliente_seleccionado').value = nombre;

    obtenerDeudaActual();
}

function filtrarClientes() {
    const input = document.getElementById('buscar_cliente').value.toLowerCase();
    const items = document.querySelectorAll('.custom-select-item');

    items.forEach(item => {
        const txt = item.getAttribute('data-nombre').toLowerCase();
        item.style.display = txt.includes(input) ? "block" : "none";
    });
}

function obtenerDeudaActual() {
    const clienteId = document.getElementById('cliente_id').value;
    const infoDiv = document.getElementById('infoDeuda');

    if (!clienteId || clienteId === '0') {
        infoDiv.innerHTML = "Deuda actual: <strong>Seleccione un cliente</strong>";
        deudaClienteActual = 0;
        return;
    }

    fetch('obtener_deuda.php?cliente_id=' + clienteId)
        .then(response => response.json())
        .then(data => {
            deudaClienteActual = parseFloat(data.deuda) || 0;
            infoDiv.innerHTML = "Deuda pendiente actual: <strong style='color: var(--danger);'>$" + deudaClienteActual.toFixed(2) + "</strong>";
        })
        .catch(() => {
            infoDiv.innerHTML = "Deuda pendiente actual: <strong>No disponible</strong>";
            deudaClienteActual = 0;
        });
}

function prepararConfirmacion() {
    const clienteId = document.getElementById('cliente_id').value;
    const monto = parseFloat(document.getElementById('montoPago').value) || 0;
    const metodo = document.getElementById('metodo_pago').value;
    const nombreCliente = document.getElementById('nombre_cliente_seleccionado').value;

    if (!clienteId || clienteId === '0') {
        alert('Por favor seleccione un cliente de la lista.');
        return;
    }
    if (monto <= 0) {
        alert('Por favor ingrese un monto válido mayor a cero.');
        return;
    }

    document.getElementById('modal_txt_cliente').innerText = nombreCliente || "Cliente seleccionado";
    document.getElementById('modal_txt_monto').innerText = monto.toFixed(2);
    document.getElementById('modal_txt_metodo').innerText = metodo;
    document.getElementById('modal_txt_deuda').innerText = deudaClienteActual.toFixed(2);

    const bloqueFavor = document.getElementById('modal_alerta_favor');
    if (monto > deudaClienteActual) {
        const saldoFavor = monto - deudaClienteActual;
        document.getElementById('modal_txt_favor').innerText = saldoFavor.toFixed(2);
        bloqueFavor.style.display = 'block';
    } else {
        bloqueFavor.style.display = 'none';
    }

    document.getElementById('modalConfirmar').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalConfirmar').style.display = 'none';
}

function procesarEnvio() {
    const btn = document.getElementById('btnConfirmarEnvioPago');
    btn.disabled = true;
    btn.innerText = "Guardando...";
    document.getElementById('formPago').submit();
}

window.onload = function() {
    const selectedItem = document.querySelector('.custom-select-item.selected');
    if (selectedItem) {
        document.getElementById('nombre_cliente_seleccionado').value = selectedItem.getAttribute('data-nombre');
    }
    if (document.getElementById('cliente_id').value && document.getElementById('cliente_id').value !== '0') {
        obtenerDeudaActual();
    }
};
</script>

</div> <!-- Cierre container -->
</body>
</html>