<?php
require_once 'auth.php';
require_once 'conexion.php';

// Obtener todas las tablas de la base de datos
$tablas = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tablas[] = $row[0];
}

$sql_script = "-- Backup de Base de Datos - Sistema Control de Taxi\n";
$sql_script .= "-- Fecha de generación: " . date('Y-m-d H:i:s') . "\n\n";
$sql_script .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tablas as $tabla) {
    // Estructura de la tabla
    $res_create = $conn->query("SHOW CREATE TABLE $tabla");
    $row_create = $res_create->fetch_row();
    $sql_script .= "DROP TABLE IF EXISTS `$tabla`;\n";
    $sql_script .= $row_create[1] . ";\n\n";

    // Datos de la tabla
    $res_data = $conn->query("SELECT * FROM $tabla");
    while ($row_data = $res_data->fetch_assoc()) {
        $sql_script .= "INSERT INTO `$tabla` VALUES(";
        $values = [];
        foreach ($row_data as $val) {
            if ($val === null) {
                $values[] = "NULL";
            } else {
                $values[] = "'" . $conn->real_escape_string($val) . "'";
            }
        }
        $sql_script .= implode(", ", $values) . ");\n";
    }
    $sql_script .= "\n";
}

$sql_script .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Configurar cabeceras de descarga de archivo SQL
$nombre_archivo = "backup_taxi_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Content-Length: ' . strlen($sql_script));

echo $sql_script;
exit;
?>