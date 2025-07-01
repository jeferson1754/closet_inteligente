<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'reglas_especificas' => '', 'message' => ''];

// Asumimos que solo hay un registro de configuración (id=1)
$sql = "SELECT reglas FROM configuracion_sugerencias LIMIT 1";
$result = $mysqli_obj->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['success'] = true;
        $response['reglas_especificas'] = $row['reglas'];
    } else {
        $response['success'] = true; // No es un error si no hay reglas, solo están vacías
        $response['message'] = 'No se encontraron reglas guardadas.';
    }
} else {
    $response['message'] = 'Error al consultar la base de datos: ' . $mysqli_obj->error;
}

echo json_encode($response);
// $mysqli_obj->close();
