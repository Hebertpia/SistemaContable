<?php
require_once 'auth.php';
require_once 'conexion.php';
include 'header.php';

$mensaje = '';
$tipo_alerta = '';

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

// Procesar Registro de Nuevo Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $cedula = preg_replace('/[^0-9]/', '', $_POST['cedula'] ?? '');
    $telefono = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
    
    $nombre_raw = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/u', '', $_POST['nombre'] ?? '');
    $nombre = mb_convert_case(trim($nombre_raw), MB_CASE_TITLE, "UTF-8");

    if (!empty($nombre)) {
        if ($campo_cedula !== 'id') {
            $sql_ins = "INSERT INTO clientes ({$campo_cedula}, nombre, telefono) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql_ins);
            $stmt->bind_param("sss", $cedula, $nombre, $telefono);
        } else {
            $sql_ins = "INSERT INTO clientes (nombre, telefono) VALUES (?, ?)";
            $stmt = $conn->prepare($sql_ins);
            $stmt->bind_param("ss", $nombre, $telefono);
        }

        if ($stmt->execute()) {
            $mensaje = "Cliente registrado con éxito.";
            $tipo_alerta = "success";
        } else {
            $mensaje = "Error en base de datos: " . $conn->error;
            $tipo_alerta = "danger";
        }
    } else {
        $mensaje = "El nombre del cliente es obligatorio y solo debe contener letras.";
        $tipo_alerta = "warning";
    }
}

// Obtener lista de clientes activos
$sql = "SELECT c.id, c.nombre, c.telefono, c.{$campo_cedula} AS cedula_real,
        IFNULL((SELECT SUM(monto) FROM servicios WHERE cliente_id = c.id), 0) - 
        IFNULL((SELECT SUM(monto) FROM pagos WHERE cliente_id = c.id), 0) AS saldo_pendiente
        FROM clientes c 
        WHERE c.activo = 1 
        ORDER BY c.nombre ASC";
$res_clientes = $conn->query($sql);
?>

<!-- Separación corregida con respecto al Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin: 24px 0 20px 0;">
    <h2 style="margin: 0; font-size: 1.3em;">👥 Clientes</h2>
    <button class="btn-submit" onclick="abrirModalCliente()" style="width: auto; padding: 8px 14px; font-size: 0.88em;">+ Nuevo</button>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : 'rgba(220, 53, 69, 0.15)'; ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '❌ ') . htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<!-- Buscador Rápido -->
<div class="form-group">
    <input type="text" id="inputBuscar" class="form-control" placeholder="🔍 Buscar por nombre o cédula..." onkeyup="filtrarClientes()">
</div>

<!-- Lista de Clientes -->
<div id="listaClientes">
    <?php if ($res_clientes && $res_clientes->num_rows > 0): ?>
        <?php while ($c = $res_clientes->fetch_assoc()): 
            $saldo = floatval($c['saldo_pendiente']);
            $ci_display = !empty($c['cedula_real']) ? $c['cedula_real'] : $c['id'];
        ?>
            <div class="card cliente-card" data-buscar="<?php echo strtolower(htmlspecialchars($c['nombre'] . ' ' . $ci_display)); ?>">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <strong style="font-size: 1.05em; display: block; color: var(--text-main);"><?php echo htmlspecialchars($c['nombre']); ?></strong>
                        <small style="color: var(--text-muted); display: block; margin-top: 2px;">
                            🆔 C.I.: <?php echo htmlspecialchars($ci_display); ?>
                        </small>
                        <small style="color: var(--text-muted); display: block; margin-top: 2px;">
                            📞 <?php echo !empty($c['telefono']) ? htmlspecialchars($c['telefono']) : 'Sin teléfono'; ?>
                        </small>
                    </div>
                    <span style="font-size: 0.85em; font-weight: bold; padding: 4px 8px; border-radius: 6px; background: <?php echo ($saldo > 0) ? 'rgba(220, 53, 69, 0.15)' : 'rgba(40, 167, 69, 0.15)'; ?>; color: <?php echo ($saldo > 0) ? 'var(--danger)' : 'var(--success)'; ?>;">
                        <?php echo ($saldo > 0) ? 'Deuda: $' . number_format($saldo, 2) : 'Al día'; ?>
                    </span>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                    <a href="estado_cuenta.php?cliente_id=<?php echo $c['id']; ?>" class="btn-action" style="flex: 1; padding: 8px; font-size: 0.8em; text-align: center;">📋 Historial</a>
                    <a href="editar_cliente.php?id=<?php echo $c['id']; ?>" class="btn-action" style="flex: 1; padding: 8px; font-size: 0.8em; text-align: center;">✏️ Editar</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted);">No hay clientes activos registrados.</p>
    <?php endif; ?>
</div>

<!-- Modal para Crear Cliente -->
<div class="modal-backdrop" id="modalCliente" style="display: none;">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;">➕ Registrar Cliente</h3>
            <button onclick="cerrarModalCliente()" style="background: none; border: none; font-size: 1.2em; color: var(--text-muted); cursor: pointer;">✕</button>
        </div>

        <form action="clientes.php" method="POST">
            <input type="hidden" name="action" value="crear">
            
            <div class="form-group">
                <label>Cédula / ID (Solo Números)</label>
                <input type="text" name="cedula" class="form-control" inputmode="numeric" placeholder="Ej. 12345678" oninput="validarNumeros(this)">
            </div>

            <div class="form-group">
                <label>Nombre y Apellido * (Solo Letras)</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej. Juan Perez" style="text-transform: capitalize;" oninput="validarLetras(this)">
            </div>

            <div class="form-group">
                <label>Teléfono (Solo Números)</label>
                <input type="text" name="telefono" class="form-control" inputmode="tel" placeholder="Ej. 04121234567" oninput="validarNumeros(this)">
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" onclick="cerrarModalCliente()" class="btn-action" style="flex: 1;">Cancelar</button>
                <button type="submit" class="btn-submit" style="flex: 1;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalCliente() {
        document.getElementById('modalCliente').style.display = 'flex';
    }

    function cerrarModalCliente() {
        document.getElementById('modalCliente').style.display = 'none';
    }

    function filtrarClientes() {
        const query = document.getElementById('inputBuscar').value.toLowerCase();
        const cards = document.querySelectorAll('.cliente-card');

        cards.forEach(card => {
            const data = card.getAttribute('data-buscar');
            card.style.display = data.includes(query) ? 'block' : 'none';
        });
    }

    function validarNumeros(input) {
        input.value = input.value.replace(/[^0-9]/g, '');
    }

    function validarLetras(input) {
        input.value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
    }
</script>

</div> <!-- Cierre container -->
</body>
</html>