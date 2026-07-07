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
   FECHA
========================================== */

date_default_timezone_set('America/Santiago');

$hoy = date('Y-m-d');

/* ==========================================
   PRENDAS QUE PASARON A SUCIO HOY
========================================== */

$sql_sucias = "
SELECT
    id,
    nombre,
    tipo,
    color_principal,
    fecha_cambio_estado
FROM prendas
WHERE estado='sucio' 
AND uso_ilimitado = 0
AND DATE(fecha_cambio_estado)=CURDATE()
ORDER BY fecha_cambio_estado DESC
";

$resultado_sucias = $mysqli_obj->query($sql_sucias);

$prendas_sucias_hoy = [];

while ($fila = $resultado_sucias->fetch_assoc()) {
    $prendas_sucias_hoy[] = $fila;
}

/* ==========================================
   HISTORIAL DE USOS DE HOY
========================================== */

$sql_usos = "
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
ON hu.prenda_id=p.id
WHERE DATE(hu.fecha)=CURDATE()
ORDER BY hu.fecha DESC
";

$resultado_usos = $mysqli_obj->query($sql_usos);

$historial_hoy = [];

while ($fila = $resultado_usos->fetch_assoc()) {
    $historial_hoy[] = $fila;
}

/* ==========================================
   PRENDAS UTILIZADAS HOY (SIN REPETIR)
========================================== */

$sql_prendas = "
SELECT DISTINCT
    p.id,
    p.nombre,
    p.tipo,
    p.color_principal
FROM historial_usos hu
INNER JOIN prendas p
ON hu.prenda_id=p.id
WHERE DATE(hu.fecha)=CURDATE()
";

$resultado_prendas = $mysqli_obj->query($sql_prendas);

$prendas_hoy = [];

while ($fila = $resultado_prendas->fetch_assoc()) {
    $prendas_hoy[] = $fila;
}

/* ==========================================
   RESUMEN DE ESTADOS ACTUALES
========================================== */

$sql_estados = "
SELECT
    estado,
    COUNT(*) total
FROM prendas
GROUP BY estado
";

$resultado_estados = $mysqli_obj->query($sql_estados);

$resumen_estados = [
    'disponible' => 0,
    'en uso' => 0,
    'sucio' => 0,
    'lavando' => 0,
    'prestado' => 0
];

while ($fila = $resultado_estados->fetch_assoc()) {

    $estado = strtolower($fila['estado']);

    $resumen_estados[$estado] = (int)$fila['total'];
}

/* ==========================================
   RESPUESTA JSON
========================================== */

echo json_encode([

    'success' => true,

    'fecha' => $hoy,

    'resumen' => [

        'total_usos_hoy' => count($historial_hoy),

        'total_prendas_usadas_hoy' => count($prendas_hoy),

        'prendas_pasaron_a_sucio_hoy' => count($prendas_sucias_hoy),

        'estados_actuales' => $resumen_estados

    ],

    'prendas_sucias_hoy' => $prendas_sucias_hoy,

    'prendas_usadas_hoy' => $prendas_hoy,

    'historial_hoy' => $historial_hoy

], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$mysqli_obj->close();
