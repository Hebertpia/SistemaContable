<?php
require_once 'conexion.php';

// Capturar parámetros por GET o POST
$cliente_id = isset($_REQUEST['cliente_id']) ? intval($_REQUEST['cliente_id']) : 0;
$ciclos_seleccionados = isset($_POST['ciclos_pdf']) ? $_POST['ciclos_pdf'] : [];

// Inspección dinámica de columnas en la tabla clientes
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

// CASO 1: Interfaz de Selección
if (empty($ciclos_seleccionados)) {
    include 'header.php';
    
    // Filtrar solo clientes activos (activo = 1) para omitir archivados
    $query_clientes = "SELECT DISTINCT c.id, c.nombre, c.telefono, c.{$campo_cedula} AS cedula_real 
                      FROM clientes c 
                      INNER JOIN servicios s ON c.id = s.cliente_id 
                      WHERE c.activo = 1 
                      ORDER BY c.nombre ASC, c.id ASC";
    $result_clientes = $conn->query($query_clientes);
    ?>

    <div style="max-width: 600px; margin: 30px auto; padding: 20px; background: var(--bg-card, #fff); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid var(--border-color, #e9ecef); color: var(--text-main, #333);">
        <h2 style="color: var(--text-main, #333); margin-top: 0;">📄 Exportar Estado de Cuenta PDF</h2>
        
        <form method="GET" action="exportar_pdf_ciclos.php" style="margin-bottom: 20px;">
            <label style="color: var(--text-main, #333);"><strong>1. Selecciona un Cliente:</strong></label>
            <select name="cliente_id" onchange="this.form.submit()" class="form-control" style="width:100%; padding: 10px; margin-top: 8px; margin-bottom: 15px; border-radius: 6px; background: var(--bg-card, #fff); color: var(--text-main, #333); border: 1px solid var(--border-color, #ccc);">
                <option value="">-- Seleccionar Cliente --</option>
                <?php if ($result_clientes && $result_clientes->num_rows > 0): ?>
                    <?php while ($c = $result_clientes->fetch_assoc()): 
                        $ci_display = !empty($c['cedula_real']) ? $c['cedula_real'] : $c['id'];
                    ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($cliente_id === (int)$c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre']); ?> (C.I: <?php echo htmlspecialchars($ci_display); ?>) [ID: <?php echo $c['id']; ?>]
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </form>

        <?php if ($cliente_id > 0): 
            $stmt_ciclos = $conn->prepare("SELECT DISTINCT ciclo FROM servicios WHERE cliente_id = ? ORDER BY ciclo DESC");
            $stmt_ciclos->bind_param("i", $cliente_id);
            $stmt_ciclos->execute();
            $res_ciclos = $stmt_ciclos->get_result();
        ?>
            <form method="POST" action="exportar_pdf_ciclos.php" target="_blank">
                <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">
                
                <label style="color: var(--text-main, #333);"><strong>2. Selecciona los Ciclos a Exportar:</strong></label>
                <div style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 6px; margin: 10px 0; border: 1px solid var(--border-color, #e9ecef);">
                    <?php if ($res_ciclos && $res_ciclos->num_rows > 0): ?>
                        <?php while ($row = $res_ciclos->fetch_assoc()): ?>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer; color: var(--text-main, #333);">
                                <input type="checkbox" name="ciclos_pdf[]" value="<?php echo $row['ciclo']; ?>" checked style="accent-color: #0d6efd;">
                                Ciclo N° <?php echo $row['ciclo']; ?>
                            </label>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="margin:0; color: var(--danger, #dc3545);">Este cliente no posee ciclos de servicios registrados.</p>
                    <?php endif; ?>
                </div>

                <?php if ($res_ciclos && $res_ciclos->num_rows > 0): ?>
                    <button type="submit" class="btn-submit" style="width: 100%; padding: 12px; font-weight: bold; cursor: pointer; font-size: 1em;">
                        📄 Generar Vista de Impresión / PDF
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    </div> </body> </html>
    <?php
    exit;
}
// CASO 2: Generación del Reporte PDF
$stmt_c = $conn->prepare("SELECT nombre, telefono, {$campo_cedula} AS cedula_real FROM clientes WHERE id = ?");
$stmt_c->bind_param("i", $cliente_id);
$stmt_c->execute();
$info_cliente = $stmt_c->get_result()->fetch_assoc();
$cedula_final = !empty($info_cliente['cedula_real']) ? $info_cliente['cedula_real'] : $cliente_id;

// Función auxiliar para escanear columnas automáticamente
function obtenerCamposTabla($conn, $tabla) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM $tabla");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = strtolower($row['Field']);
        }
    }
    
    $campo_fecha = "''";
    foreach ($cols as $col) {
        if (strpos($col, 'fecha') !== false || strpos($col, 'date') !== false || strpos($col, 'created') !== false || strpos($col, 'time') !== false) {
            $campo_fecha = $col;
            break;
        }
    }

    $campo_desc = "''";
    foreach ($cols as $col) {
        if (strpos($col, 'desc') !== false || strpos($col, 'concepto') !== false || strpos($col, 'detalle') !== false || strpos($col, 'metodo') !== false || strpos($col, 'observ') !== false) {
            $campo_desc = $col;
            break;
        }
    }

    return ['fecha' => $campo_fecha, 'desc' => $campo_desc];
}

$serv_cfg = obtenerCamposTabla($conn, 'servicios');
$pagos_cfg = obtenerCamposTabla($conn, 'pagos');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado_Cuenta_CI_<?php echo htmlspecialchars($cedula_final); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; margin: 25px; background: #fff; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .cliente-info { background: #f8f8f8; padding: 12px; border: 1px solid #ddd; margin-bottom: 20px; border-radius: 4px; }
        .ciclo-bloque { margin-bottom: 30px; page-break-inside: avoid; }
        .ciclo-titulo { background: #0f172a; color: #fff; padding: 6px 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; }
        .monto { text-align: right; }
        .resumen { text-align: right; font-weight: bold; margin-top: 6px; font-size: 1.05em; }
        .btn-imprimir { background: #27ae60; color: white; padding: 10px 18px; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: bold; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" class="btn-imprimir">📄 Descargar / Guardar PDF</button>
    </div>

    <div class="header">
        <h2 style="margin: 0;">ESTADO DE CUENTA DETALLADO</h2>
        <p style="margin: 5px 0 0 0;">Fecha de Emisión: <?php echo date("d/m/Y h:i A"); ?></p>
    </div>

    <div class="cliente-info">
        <strong>Cliente:</strong> <?php echo htmlspecialchars($info_cliente['nombre'] ?? 'N/A'); ?><br>
        <strong>Cédula de Identidad:</strong> <?php echo htmlspecialchars($cedula_final); ?><br>
        <strong>Teléfono:</strong> <?php echo htmlspecialchars($info_cliente['telefono'] ?? 'N/A'); ?>
    </div>

    <?php foreach ($ciclos_seleccionados as $ciclo): 
        $c_num = intval($ciclo);

        $sql_s = "SELECT monto, {$serv_cfg['desc']} AS descripcion_reg, {$serv_cfg['fecha']} AS fecha_reg FROM servicios WHERE cliente_id = ? AND ciclo = ? AND monto > 0";
        if ($serv_cfg['fecha'] !== "''") { $sql_s .= " ORDER BY {$serv_cfg['fecha']} ASC"; }
        
        $stmt_s = $conn->prepare($sql_s);
        $stmt_s->bind_param("ii", $cliente_id, $c_num);
        $stmt_s->execute();
        $servicios = $stmt_s->get_result();

        $sql_p = "SELECT monto, {$pagos_cfg['desc']} AS descripcion_reg, {$pagos_cfg['fecha']} AS fecha_reg FROM pagos WHERE cliente_id = ? AND ciclo = ? AND monto > 0";
        if ($pagos_cfg['fecha'] !== "''") { $sql_p .= " ORDER BY {$pagos_cfg['fecha']} ASC"; }

        $stmt_p = $conn->prepare($sql_p);
        $stmt_p->bind_param("ii", $cliente_id, $c_num);
        $stmt_p->execute();
        $pagos = $stmt_p->get_result();

        $tot_serv = 0;
        $tot_pag = 0;
    ?>
        <div class="ciclo-bloque">
            <div class="ciclo-titulo">CICLO DE CUENTA N° <?php echo $c_num; ?></div>

            <p style="margin: 10px 0 2px 0; font-weight: bold;">Carreras / Servicios Realizados:</p>
            <table>
                <thead>
                    <tr>
                        <th width="25%">Fecha/Hora</th>
                        <th width="55%">Descripción / Concepto</th>
                        <th width="20%" class="monto">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($servicios && $servicios->num_rows > 0): ?>
                        <?php while ($s = $servicios->fetch_assoc()): 
                            $tot_serv += $s['monto'];
                            $time = strtotime($s['fecha_reg']);
                            $fecha_f = ($time && $s['fecha_reg'] !== '0000-00-00 00:00:00') ? date("d/m/Y h:i A", $time) : 'N/A';
                            $desc_f = !empty($s['descripcion_reg']) ? $s['descripcion_reg'] : 'Carrera / Servicio';
                        ?>
                            <tr>
                                <td><?php echo $fecha_f; ?></td>
                                <td><?php echo htmlspecialchars($desc_f); ?></td>
                                <td class="monto">$<?php echo number_format($s['monto'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">Sin carreras registradas en este ciclo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin: 10px 0 2px 0; font-weight: bold;">Abonos / Pagos Realizados:</p>
            <table>
                <thead>
                    <tr>
                        <th width="25%">Fecha/Hora</th>
                        <th width="55%">Detalle / Método</th>
                        <th width="20%" class="monto">Abonado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pagos && $pagos->num_rows > 0): ?>
                        <?php while ($p = $pagos->fetch_assoc()): 
                            $tot_pag += $p['monto'];
                            $time_p = strtotime($p['fecha_reg']);
                            $fecha_p_f = ($time_p && $p['fecha_reg'] !== '0000-00-00 00:00:00') ? date("d/m/Y h:i A", $time_p) : 'N/A';
                            $desc_p_f = !empty($p['descripcion_reg']) ? $p['descripcion_reg'] : 'Abono a cuenta';
                        ?>
                            <tr>
                                <td><?php echo $fecha_p_f; ?></td>
                                <td><?php echo htmlspecialchars($desc_p_f); ?></td>
                                <td class="monto">+$<?php echo number_format($p['monto'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">Sin abonos registrados en este ciclo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="resumen">
                Total Carreras: $<?php echo number_format($tot_serv, 2); ?> | Total Abonos: $<?php echo number_format($tot_pag, 2); ?> | <strong>Saldo Restante: $<?php echo number_format($tot_serv - $tot_pag, 2); ?></strong>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>