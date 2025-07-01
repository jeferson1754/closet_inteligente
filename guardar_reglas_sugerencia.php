<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reglas_especificas = $_POST['reglas_especificas'] ?? '';

    // Asumimos que solo hay un registro de configuración (id=1)
    // Si manejas usuarios, necesitarías una columna user_id y filtrar por ella.
    $sql_check_exist = "SELECT id FROM configuracion_sugerencias LIMIT 1";
    $result_check = $mysqli_obj->query($sql_check_exist);

    if ($result_check && $result_check->num_rows > 0) {
        // Si ya existe un registro, actualízalo
        $sql = "UPDATE configuracion_sugerencias SET reglas = ?";
        if ($stmt = $mysqli_obj->prepare($sql)) {
            $stmt->bind_param("s", $reglas_especificas);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Reglas actualizadas exitosamente.';
            } else {
                $response['message'] = 'Error al actualizar las reglas: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la actualización de reglas: ' . $mysqli_obj->error;
        }
    } else {
        // Si no existe, inserta un nuevo registro
        $sql = "INSERT INTO configuracion_sugerencias (reglas) VALUES (?)";
        if ($stmt = $mysqli_obj->prepare($sql)) {
            $stmt->bind_param("s", $reglas_especificas);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Reglas guardadas exitosamente.';
            } else {
                $response['message'] = 'Error al guardar las reglas: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la inserción de reglas: ' . $mysqli_obj->error;
        }
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
// $mysqli_obj->close();
