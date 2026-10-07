<?php
require_once 'auth.php';
require_once 'conexion.php';

$id = intval($_GET['id'] ?? 0);
$mensaje = '';

if ($id <= 0) {
    header("Location: clientes.php");
    exit;
}

// Detección dinámica del nombre de la columna de la cédula
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = preg_replace('/[^0-9]/', '', $_POST['cedula'] ?? '');
    $telefono = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
    $nombre_raw = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/u', '', $_POST['nombre'] ?? '');
    $nombre = mb_convert_case(trim($nombre_raw), MB_CASE_TITLE, "UTF-8");

    if (!empty($nombre)) {
        if ($campo_cedula !== 'id') {
            $sql_upd = "UPDATE clientes SET {$campo_cedula} = ?, nombre = ?, telefono = ? WHERE id = ?";
            $stmt = $conn->prepare($sql_upd);
            $stmt->bind_param("sssi", $cedula, $nombre, $telefono, $id);
        } else {
            $sql_upd = "UPDATE clientes SET nombre = ?, telefono = ? WHERE id = ?";
            $stmt = $conn->prepare($sql_upd);
            $stmt->bind_param("ssi", $nombre, $telefono, $id);
        }

        if ($stmt->execute()) {
            header("Location: clientes.php");
            exit;
        } else {
            $mensaje = "Error al actualizar: " . $conn->error;
        }
    } else {
        $mensaje = "El nombre es obligatorio.";
    }
}

// Obtener datos del cliente actual
$sql_c = "SELECT *, {$campo_cedula} AS cedula_real FROM clientes WHERE id = ?";
$stmt = $conn->prepare($sql_c);
$stmt->bind_param("i", $id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    header("Location: clientes.php");
    exit;
}

$ci_actual = !empty($cliente['cedula_real']) ? $cliente['cedula_real'] : '';

// Incluir la cabecera DESPUÉS de cualquier redirección o procesamiento con header()
include 'header.php';
?>

<div style="margin-bottom: 16px;">
    <h2 style="margin: 0; font-size: 1.3em;">✏️ Editar Cliente</h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: rgba(220, 53, 69, 0.15); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        ❌ <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="editar_cliente.php?id=<?php echo $id; ?>" method="POST">
        <div class="form-group">
            <label>Cédula / ID (Solo Números)</label>
            <input type="text" name="cedula" class="form-control" value="<?php echo htmlspecialchars($ci_actual); ?>" inputmode="numeric" oninput="validarNumeros(this)">
        </div>

        <div class="form-group">
            <label>Nombre Completo (Solo Letras)</label>
            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required style="text-transform: capitalize;" oninput="validarLetras(this)">
        </div>

        <div class="form-group">
            <label>Teléfono (Solo Números)</label>
            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" inputmode="tel" oninput="validarNumeros(this)">
        </div>

        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <a href="clientes.php" class="btn-action" style="flex: 1; text-align: center; text-decoration: none;">Cancelar</a>
            <button type="submit" class="btn-submit" style="flex: 1;">Actualizar</button>
        </div>
    </form>
</div>

<script>
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