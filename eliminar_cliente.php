<?php
require_once 'auth.php';
require_once 'conexion.php';

$mensaje = "";
$tipo_alerta = "";
$cliente_id = isset($_REQUEST['cliente_id']) ? intval($_REQUEST['cliente_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

// Detectar columna de cédula dinámicamente
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

// Procesar archivado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_eliminar'])) {
    if ($cliente_id > 0) {
        $stmt_s = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_servicios FROM servicios WHERE cliente_id = ?");
        $stmt_s->bind_param("i", $cliente_id);
        $stmt_s->execute();
        $total_servicios = floatval($stmt_s->get_result()->fetch_assoc()['total_servicios']);

        $stmt_p = $conn->prepare("SELECT IFNULL(SUM(monto), 0) AS total_pagos FROM pagos WHERE cliente_id = ?");
        $stmt_p->bind_param("i", $cliente_id);
        $stmt_p->execute();
        $total_pagos = floatval($stmt_p->get_result()->fetch_assoc()['total_pagos']);

        $saldo = $total_servicios - $total_pagos;

        if ($saldo > 0) {
            $mensaje = "No se puede archivar: El cliente tiene una deuda pendiente de $" . number_format($saldo, 2);
            $tipo_alerta = "danger";
        } else {
            $stmt_del = $conn->prepare("UPDATE clientes SET activo = 0 WHERE id = ?");
            $stmt_del->bind_param("i", $cliente_id);

            if ($stmt_del->execute()) {
                $mensaje = "¡Cliente archivado y ocultado exitosamente!";
                $tipo_alerta = "success";
                $cliente_id = 0;
            } else {
                $mensaje = "Error de MySQL: " . $conn->error;
                $tipo_alerta = "danger";
            }
        }
    }
}

// Cargar clientes activos
$sql_clientes = "SELECT id, nombre, telefono, {$campo_cedula} AS cedula_real FROM clientes WHERE activo = 1 ORDER BY nombre ASC";
$res_clientes = $conn->query($sql_clientes);
$array_clientes = [];
if ($res_clientes && $res_clientes->num_rows > 0) {
    while ($row = $res_clientes->fetch_assoc()) {
        $row['ci_display'] = !empty($row['cedula_real']) ? $row['cedula_real'] : $row['id'];
        $array_clientes[] = $row;
    }
}

$cliente_actual = null;
if ($cliente_id > 0) {
    foreach ($array_clientes as $c) {
        if ($c['id'] == $cliente_id) {
            $cliente_actual = $c;
            break;
        }
    }
}

include 'header.php';
?>

<!-- Contenedor del título con margen superior para separarlo del Header -->
<div style="margin-top: 25px; margin-bottom: 16px;">
    <h2 style="margin: 0; font-size: 1.3em;">📦 Archivar / Ocultar Cliente</h2>
    <p style="color: var(--text-muted); font-size: 0.85em; margin-top: 4px;">
        Oculta al cliente manteniendo su historial contable. Solo permitido si no registra deuda pendiente.
    </p>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : 'rgba(220, 53, 69, 0.15)'; ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '⚠️ ') . htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="margin-bottom: 15px;">
        <label style="font-weight: bold; font-size: 0.9em; display: block; margin-bottom: 8px;">Modo de Búsqueda:</label>
        <div style="display: flex; gap: 10px; font-size: 0.85em;">
            <label><input type="radio" name="modo_busqueda" value="lista" checked onclick="cambiarModo('lista')"> Lista</label>
            <label><input type="radio" name="modo_busqueda" value="nombre" onclick="cambiarModo('nombre')"> Nombre</label>
            <label><input type="radio" name="modo_busqueda" value="cedula" onclick="cambiarModo('cedula')"> Cédula</label>
        </div>
    </div>

    <div style="margin-bottom: 15px;">
        <div id="bloque_lista">
            <label>Seleccionar Cliente</label>
            <select class="form-control" onchange="cargarCliente(this.value)">
                <option value="">-- Seleccione un cliente --</option>
                <?php foreach ($array_clientes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $cliente_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nombre']) . " (C.I. " . htmlspecialchars($c['ci_display']) . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="bloque_nombre" style="display: none;">
            <label>Escriba Nombre del Cliente</label>
            <input type="text" class="form-control" placeholder="Ej. Juan Pérez..." list="datalist_nombres" onchange="seleccionarPorNombre(this.value)">
            <datalist id="datalist_nombres">
                <?php foreach ($array_clientes as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['nombre']); ?>">C.I. <?php echo htmlspecialchars($c['ci_display']); ?></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div id="bloque_cedula" style="display: none;">
            <label>Ingrese Cédula del Cliente</label>
            <input type="number" class="form-control" placeholder="Ej. 12345678" oninput="seleccionarPorCedula(this.value)">
        </div>
    </div>

    <?php if ($cliente_actual): ?>
        <div style="border: 1px solid var(--danger); background: rgba(220, 53, 69, 0.05); padding: 15px; border-radius: 8px; margin-top: 15px;">
            <h3 style="margin-top: 0; color: var(--danger); font-size: 1.1em;">¿Confirmar archivado del cliente?</h3>
            <p style="margin: 5px 0; font-size: 0.9em;"><strong>Nombre:</strong> <?php echo htmlspecialchars($cliente_actual['nombre']); ?></p>
            <p style="margin: 5px 0; font-size: 0.9em;"><strong>Cédula:</strong> <?php echo htmlspecialchars($cliente_actual['ci_display']); ?></p>
            <p style="margin: 5px 0 15px 0; font-size: 0.9em;"><strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente_actual['telefono'] ?: 'Sin registro'); ?></p>

            <form method="POST" action="eliminar_cliente.php" onsubmit="return confirm('¿Está seguro de que desea archivar a este cliente?');">
                <input type="hidden" name="cliente_id" value="<?php echo $cliente_actual['id']; ?>">
                <input type="hidden" name="confirmar_eliminar" value="1">
                <button type="submit" class="btn-submit" style="background-color: var(--danger);">📦 Archivar Cliente</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
const clientesBD = <?php echo json_encode($array_clientes); ?>;

function cambiarModo(modo) {
    document.getElementById('bloque_lista').style.display = (modo === 'lista') ? 'block' : 'none';
    document.getElementById('bloque_nombre').style.display = (modo === 'nombre') ? 'block' : 'none';
    document.getElementById('bloque_cedula').style.display = (modo === 'cedula') ? 'block' : 'none';
}

function cargarCliente(id) {
    if (id) window.location.href = 'eliminar_cliente.php?cliente_id=' + id;
}

function seleccionarPorNombre(val) {
    const c = clientesBD.find(item => item.nombre.toLowerCase() === val.toLowerCase());
    if (c) cargarCliente(c.id);
}

function seleccionarPorCedula(val) {
    const c = clientesBD.find(item => item.ci_display == val || item.id == val);
    if (c) cargarCliente(c.id);
}
</script>

</div> <!-- Cierre container -->
</body>
</html>