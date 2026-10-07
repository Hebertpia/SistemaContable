<?php
require_once 'auth.php';
require_once 'conexion.php';

$mensaje = "";
$tipo_alerta = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantilla = isset($_POST['plantilla_mensaje']) ? trim($_POST['plantilla_mensaje']) : '';

    if (!empty($plantilla)) {
        $stmt = $conn->prepare("INSERT INTO configuracion (clave, valor) VALUES ('plantilla_whatsapp', ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->bind_param("ss", $plantilla, $plantilla);

        if ($stmt->execute()) {
            $mensaje = "¡Plantilla de WhatsApp actualizada correctamente!";
            $tipo_alerta = "success";
        } else {
            $mensaje = "Error al guardar en la base de datos: " . $conn->error;
            $tipo_alerta = "danger";
        }
    } else {
        $mensaje = "El mensaje no puede estar vacío.";
        $tipo_alerta = "warning";
    }
}

// Cargar plantilla actual
$query = "SELECT valor FROM configuracion WHERE clave = 'plantilla_whatsapp' ORDER BY id DESC LIMIT 1";
$res = $conn->query($query);
$plantilla_actual = "";

if ($res && $res->num_rows > 0) {
    $plantilla_actual = $res->fetch_assoc()['valor'];
} else {
    $plantilla_actual = "Hola {nombre}, le recordamos que presenta un saldo pendiente de ${monto}. Por favor comunicarse para coordinar el pago. ¡Muchas gracias!";
}

include 'header.php';
?>

<!-- Contenedor del título con margen superior para separarlo del Header -->
<div style="margin-top: 25px; margin-bottom: 16px;">
    <h2 style="margin: 0; font-size: 1.3em;">💬 Plantilla WhatsApp</h2>
</div>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?php echo ($tipo_alerta === 'success') ? 'rgba(40, 167, 69, 0.15)' : (($tipo_alerta === 'warning') ? 'rgba(255, 193, 7, 0.15)' : 'rgba(220, 53, 69, 0.15)'); ?>; color: var(--<?php echo $tipo_alerta; ?>); padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.88em;">
        <?php echo ($tipo_alerta === 'success' ? '✅ ' : '❌ ') . htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="config_mensaje.php" method="POST">
        <div class="form-group">
            <label>Mensaje Predeterminado para Clientes Deudores</label>
            <textarea name="plantilla_mensaje" rows="6" class="form-control" style="resize: vertical; line-height: 1.5;" required><?php echo htmlspecialchars($plantilla_actual); ?></textarea>
        </div>

        <div style="background: var(--bg-main); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 15px; font-size: 0.85em; color: var(--text-muted);">
            <strong style="color: var(--text-main);">📌 Variables automáticas disponibles:</strong>
            <ul style="margin: 6px 0 0 0; padding-left: 18px;">
                <li><code>{nombre}</code>: Se reemplazará por el nombre del cliente.</li>
                <li><code>{monto}</code>: Se reemplazará por la deuda pendiente actual.</li>
            </ul>
        </div>

        <button type="submit" class="btn-submit">Guardar Configuración</button>
    </form>
</div>

</div> <!-- Cierre container -->
</body>
</html>