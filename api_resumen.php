<?php

header('Content-Type: application/json; charset=utf-8');

include 'bd.php';

/* ==========================================
   SEGURIDAD
========================================== */

$token_correcto = 'MiClaveSuperSecreta123';

if (
    !isset($_GET['token']) ||
    $_GET['token'] !== $token_correcto
) {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'mensaje' => 'Acceso denegado'
    ]);

    exit;
}

/* ==========================================
   FECHA DE HOY
========================================== */

$hoy = date('Y-m-d');

/* ==========================================
   USOS REGISTRADOS HOY
========================================== */

$sql = "
SELECT
    hu.id,
    hu.fecha,
    hu.contexto,
    hu.clima,
    p.id AS prenda_id,
    p.nombre,
    p.tipo,
    p.color_principal
FROM historial_usos hu
INNER JOIN prendas p
    ON hu.prenda_id = p.id
WHERE DATE(hu.fecha) = CURDATE()
ORDER BY hu.fecha DESC
";

$resultado = $mysqli_obj->query($sql);

$usos_hoy = [];

while ($fila = $resultado->fetch_assoc()) {
    $usos_hoy[] = $fila;
}

/* ==========================================
   PRENDAS UTILIZADAS HOY (SIN DUPLICADOS)
========================================== */

$sql_prendas = "
SELECT DISTINCT
    p.id,
    p.nombre,
    p.tipo,
    p.color_principal
FROM historial_usos hu
INNER JOIN prendas p
    ON hu.prenda_id = p.id
WHERE DATE(hu.fecha) = CURDATE()
";

$resultado_prendas = $mysqli_obj->query($sql_prendas);

$prendas_hoy = [];

while ($fila = $resultado_prendas->fetch_assoc()) {
    $prendas_hoy[] = $fila;
}

/* ==========================================
   RESPUESTA JSON
========================================== */

echo json_encode([
    'success' => true,
    'fecha' => $hoy,

    'resumen' => [
        'total_usos_hoy' => count($usos_hoy),
        'total_prendas_usadas_hoy' => count($prendas_hoy)
    ],

    'prendas_usadas_hoy' => $prendas_hoy,

    'historial_hoy' => $usos_hoy

], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$mysqli_obj->close();
