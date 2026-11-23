<?php
include 'bd.php';

header('Content-Type: application/json');
date_default_timezone_set('America/Santiago');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prendas = isset($_POST['prendas']) && is_array($_POST['prendas']) ? $_POST['prendas'] : [];
    
    // Filtrar y sanitizar IDs válidos
    $prendas_ids = array_unique(array_filter(array_map('intval', $prendas), function ($id) {
        return $id > 0;
    }));

    if (empty($prendas_ids)) {
        $response['message'] = 'Debes seleccionar al menos una prenda para armar tu pijama.';
        echo json_encode($response);
        exit;
    }

    $datetime_actual = date('Y-m-d H:i:s');
    $mysqli_obj->begin_transaction();

    try {
        // 1. Limpiar las prendas del pijama anterior
        if (!$mysqli_obj->query("DELETE FROM pijama_prendas")) {
            throw new Exception("Error al limpiar las prendas anteriores del pijama: " . $mysqli_obj->error);
        }

        // 2. Insertar las nuevas prendas seleccionadas
        $sql_insert = "INSERT INTO pijama_prendas (prenda_id) VALUES (?)";
        if ($stmt_insert = $mysqli_obj->prepare($sql_insert)) {
            foreach ($prendas_ids as $prenda_id) {
                $stmt_insert->bind_param("i", $prenda_id);
                if (!$stmt_insert->execute()) {
                    throw new Exception("Error al asignar prenda ID {$prenda_id}: " . $stmt_insert->error);
                }
            }
            $stmt_insert->close();
        } else {
            throw new Exception("Error al preparar inserción de pijama: " . $mysqli_obj->error);
        }

        // 3. Actualizar registro maestro (reiniciando fecha_ultimo_uso para el nuevo conjunto)
        $sql_update_meta = "INSERT INTO pijama_actual (id, fecha_ultimo_uso, fecha_actualizacion) 
                            VALUES (1, NULL, ?) 
                            ON DUPLICATE KEY UPDATE fecha_ultimo_uso = NULL, fecha_actualizacion = VALUES(fecha_actualizacion)";
        if ($stmt_meta = $mysqli_obj->prepare($sql_update_meta)) {
            $stmt_meta->bind_param("s", $datetime_actual);
            $stmt_meta->execute();
            $stmt_meta->close();
        } else {
            throw new Exception("Error al actualizar estado del pijama: " . $mysqli_obj->error);
        }

        $mysqli_obj->commit();
        $response['success'] = true;
        $response['message'] = 'Pijama actualizado correctamente.';
    } catch (Exception $e) {
        $mysqli_obj->rollback();
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>