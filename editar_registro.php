<?php
require_once 'auth.php';
require_once 'conexion.php';

$mensaje = "";
$tipo_alerta = "";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : ''; 

if ($id <= 0 || !in_array($tipo, ['servicio', 'pago'])) {
    header("Location: reportes.php");
    exit();
}

// Cargar datos actuales del registro
if ($tipo === 'pago') {
    $stmt = $conn->prepare("SELECT p.id, p.monto, p.metodo_pago, p.cliente_id, c.nombre, p.ciclo 
                            FROM pagos p 
                            JOIN clientes c ON p.cliente_id = c.id 
                            WHERE p.id = ?");
} else {
    $stmt = $conn->prepare("SELECT s.id, s.monto, s.concepto, s.cliente_id, c.nombre, s.ciclo 
                            FROM servicios s 
                            JOIN clientes c ON s.cliente_id = c.id 
                            WHERE s.id = ?");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$registro = $stmt->get_result()->fetch_assoc();

if (!$registro) {
    header("Location: reportes.php");
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = floatval($_POST['monto']);
    $cliente_id = $registro['cliente_id'];
    $ciclo_actual = $registro['ciclo'];

    if ($tipo === 'pago') {
        $metodo = trim($_POST['metodo_pago']);
        $stmt_up = $conn->prepare("UPDATE pagos SET monto = ?, metodo_pago = ? WHERE id = ?");
        $stmt_up->bind_param("dsi", $monto, $metodo, $id);
    } else {
        $concepto = trim($_POST['concepto']);
        $stmt_up = $conn->prepare("UPDATE servicios SET monto = ?, concepto = ? WHERE id = ?");
        $stmt_up->bind_param("dsi", $monto, $concepto, $id);
    }

    if ($stmt_up->execute()) {
        // Recalcular saldo del ciclo tras editar
        $stmt_sum_s = $conn->prepare("SELECT IFNULL(SUM(monto),0) AS total_s FROM servicios WHERE cliente_id = ? AND ciclo = ?");
        $stmt_sum_s->bind_param("ii", $cliente_id, $ciclo_actual);
        $stmt_sum_s->execute();
        $tot_s = $stmt_sum_s->get_result()->fetch_assoc()['total_s'];

        $stmt_sum_p = $conn->prepare("SELECT IFNULL(SUM(monto),0) AS total_p FROM pagos WHERE cliente_id = ? AND ciclo = ?");
        $stmt_sum_p->bind_param("ii", $cliente_id, $ciclo_actual);
        $stmt_sum_p->execute();
        $tot_p = $stmt_sum_p->get_result()->fetch_assoc()['total_p'];

        $diferencia = $tot_s - $tot_p;

        if ($diferencia <= 0 && $tot_s > 0) {
            $stmt_next_check = $conn->prepare("SELECT COUNT(*) AS total FROM servicios WHERE cliente_id = ? AND ciclo = ?");
            $next_ciclo = $ciclo_actual + 1;
            $stmt_next_check->bind_param("ii", $cliente_id, $next_ciclo);
            $stmt_next_check->execute();
            $existe_siguiente = $stmt_next_check->get_result()->fetch_assoc()['total'];

            if ($existe_siguiente == 0) {
                $stmt_next = $conn->prepare("INSERT INTO servicios (cliente_id, concepto, monto, ciclo) VALUES (?, 'INICIO DE NUEVO CICLO', 0, ?)");
                $stmt_next->bind_param("ii", $cliente_id, $next_ciclo);
                $stmt_next->execute();
            }
        }

        header("Location: reportes.php?msg=actualizado");
        exit();
    } else {
        $mensaje = "Error al actualizar el registro: " . $conn->error;
        $tipo_alerta = "danger";
    }
}

include 'header.php';
?>

<div style="margin-bottom: 16px;">
    <h2 style="margin: 0; font-size: 1.3em;">✏️ Editar <?php echo ($tipo === 'pago') ? 'Pago / Abono' : 'Servicio / Cargo'; ?></h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: rgba(220, 53, 69, 0.15); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        ❌ <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-body); border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9em;">
        <strong>Cliente:</strong> <?php echo htmlspecialchars($registro['nombre']); ?><br>
        <strong>Ciclo Activo:</strong> N° <?php echo htmlspecialchars($registro['ciclo']); ?>
    </div>

    <form method="POST">
        <?php if ($tipo === 'pago'): ?>
            <div class="form-group">
                <label>Monto Abonado ($)</label>
                <input type="number" step="0.01" name="monto" class="form-control" value="<?php echo htmlspecialchars($registro['monto']); ?>" required>
            </div>

            <div class="form-group">
                <label>Método de Pago</label>
                <select name="metodo_pago" class="form-control">
                    <option value="Pago Móvil" <?php echo ($registro['metodo_pago'] === 'Pago Móvil') ? 'selected' : ''; ?>>Pago Móvil</option>
                    <option value="Efectivo USD" <?php echo ($registro['metodo_pago'] === 'Efectivo USD') ? 'selected' : ''; ?>>Efectivo USD</option>
                    <option value="Efectivo Bs" <?php echo ($registro['metodo_pago'] === 'Efectivo Bs') ? 'selected' : ''; ?>>Efectivo Bs</option>
                    <option value="Transferencia Bancaria" <?php echo ($registro['metodo_pago'] === 'Transferencia Bancaria') ? 'selected' : ''; ?>>Transferencia Bancaria</option>
                    <option value="Otro" <?php echo ($registro['metodo_pago'] === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                </select>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label>Descripción / Concepto</label>
                <input type="text" name="concepto" class="form-control" value="<?php echo htmlspecialchars($registro['concepto']); ?>" required>
            </div>

            <div class="form-group">
                <label>Monto a Cargar ($)</label>
                <input type="number" step="0.01" name="monto" class="form-control" value="<?php echo htmlspecialchars($registro['monto']); ?>" required>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="reportes.php" class="btn-action" style="flex: 1; text-align: center; text-decoration: none; line-height: 2.2;">Cancelar</a>
            <button type="submit" class="btn-submit" style="flex: 1;">Guardar Cambios</button>
        </div>
    </form>
</div>

</div> <!-- Cierre container -->
</body>
</html>