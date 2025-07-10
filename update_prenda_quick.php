<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenda_id = $_POST['id'] ?? null;
    $new_estado = $_POST['estado'] ?? null;
    $fecha_ultimo_lavado = $_POST['fecha_ultimo_lavado'] ?? null;

    if (!$prenda_id) {
        $response['message'] = 'ID de prenda no proporcionado.';
        echo json_encode($response);
        exit;
    }

    $sql_parts = [];
    $bind_types = '';
    $bind_params_values = []; // Array para almacenar los valores de los parámetros (no referencias aquí todavía)

    // Solo añadir a la consulta si el valor no es null
    if ($new_estado !== null) {
        $sql_parts[] = "estado = ?";
        $bind_types .= 's'; // 's' para string (estado)
        $bind_params_values[] = $new_estado;
    }

    if ($new_estado == 'disponible') {
        $sql_parts[] = "usos_esta_semana = 0";
    }

    if ($fecha_ultimo_lavado !== null) {
        $sql_parts[] = "fecha_ultimo_lavado = ?";
        $bind_types .= 's'; // 's' para string (fecha)
        $bind_params_values[] = $fecha_ultimo_lavado;
    }

    if (empty($sql_parts)) {
        $response['message'] = 'No se proporcionaron datos para actualizar.';
        echo json_encode($response);
        exit;
    }

    // Añadir la condición WHERE id = ? al final de la consulta y sus parámetros
    $sql_update_query = "UPDATE prendas SET " . implode(', ', $sql_parts) . " WHERE id = ?";
    $bind_types .= 'i'; // 'i' para integer (ID de la prenda)
    $bind_params_values[] = $prenda_id; // Añadir el ID al final de los valores

    if ($stmt = $mysqli_obj->prepare($sql_update_query)) {
        // --- INICIO DE LA SOLUCIÓN REVISADA DE BIND_PARAM ---
        // Crear un array temporal para call_user_func_array, asegurando referencias
        $tmp_bind_params = [];
        $tmp_bind_params[] = $bind_types; // El primer elemento es el string de tipos

        // Asegurarse de que cada valor se pasa por referencia
        foreach ($bind_params_values as $key => $value) {
            $tmp_bind_params[] = &$bind_params_values[$key];
        }

        // Llamar a bind_param
        call_user_func_array([$stmt, 'bind_param'], $tmp_bind_params);
        // --- FIN DE LA SOLUCIÓN REVISADA DE BIND_PARAM ---

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Prenda actualizada correctamente.';
            } else {
                $response['success'] = true;
                $response['message'] = 'No se realizaron cambios en la prenda.';
            }
        } else {
            $response['message'] = 'Error al ejecutar la actualización: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error al preparar la consulta: ' . $mysqli_obj->error;
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
