<?php
require_once 'auth.php';
require_once 'conexion.php';

$mensaje = "";
$tipo_alerta = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula   = isset($_POST['cedula']) ? intval($_POST['cedula']) : 0;
    $nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

    if ($cedula > 0 && !empty($nombre)) {
        $nombre_formateado = mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");

        // 1. Verificar si la cédula ya existe en la BD
        $stmt_check = $conn->prepare("SELECT id, activo FROM clientes WHERE id = ?");
        $stmt_check->bind_param("i", $cedula);
        $stmt_check->execute();
        $res = $stmt_check->get_result();

        if ($res->num_rows > 0) {
            $cliente = $res->fetch_assoc();

            if ($cliente['activo'] == 0) {
                // Reactivación
                $stmt_reactivar = $conn->prepare("UPDATE clientes SET nombre = ?, telefono = ?, activo = 1 WHERE id = ?");
                $stmt_reactivar->bind_param("ssi", $nombre_formateado, $telefono, $cedula);

                if ($stmt_reactivar->execute()) {
                    $mensaje = "🔄 ¡El cliente " . htmlspecialchars($nombre_formateado) . " (C.I. $cedula) estaba archivado y fue reactivado con éxito!";
                    $tipo_alerta = "success";
                } else {
                    $mensaje = "Error al reactivar cliente: " . $conn->error;
                    $tipo_alerta = "danger";
                }
            } else {
                $mensaje = "Error: La Cédula $cedula ya se encuentra registrada y activa en el sistema.";
                $tipo_alerta = "warning";
            }
        } else {
            // 2. Cliente nuevo
            $sql = "INSERT INTO clientes (id, nombre, telefono, activo) VALUES (?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $cedula, $nombre_formateado, $telefono);

            if ($stmt->execute()) {
                $mensaje = "¡Cliente " . htmlspecialchars($nombre_formateado) . " (C.I. $cedula) registrado correctamente!";
                $tipo_alerta = "success";
            } else {
                $mensaje = "Error en MySQL: " . $stmt->error;
                $tipo_alerta = "danger";
            }
        }
    } else {
        $mensaje = "Por favor, complete los campos obligatorios.";
        $tipo_alerta = "warning";
    }
}

include 'header.php';
?>

<div style="margin-bottom: 16px;">
    <h2 style="margin: 0; font-size: 1.3em;">👤 Registrar Nuevo Cliente</h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : (($tipo_alerta === 'warning') ? 'rgba(255, 193, 7, 0.15)' : 'rgba(220, 53, 69, 0.15)'); ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '❌ ') . $mensaje; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="registrar_cliente.php" method="POST">
        <div class="form-group">
            <label>Cédula de Identidad (ID)</label>
            <input type="number" name="cedula" class="form-control" required placeholder="Ej. 12345678">
        </div>

        <div class="form-group">
            <label>Nombre y Apellido</label>
            <input type="text" name="nombre" id="input_nombre" class="form-control" required placeholder="Ej. juan pérez" onblur="formatearNombreCliente(this)">
        </div>

        <div class="form-group">
            <label>Teléfono / Contacto</label>
            <input type="text" name="telefono" class="form-control" placeholder="Ej. 04121234567">
        </div>

        <button type="submit" class="btn-submit" style="margin-top: 10px;">Guardar Cliente</button>
    </form>
</div>

<script>
function formatearNombreCliente(input) {
    if (!input.value) return;
    input.value = input.value
        .toLowerCase()
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
</script>

</div> <!-- Cierre container -->
</body>
</html>