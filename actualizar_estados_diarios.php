<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj)

header('Content-Type: application/json'); // Indica que la respuesta es JSON

$response = ['success' => false, 'message' => ''];


if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    date_default_timezone_set('America/Santiago');
    $today_date = date('Y-m-d'); // La fecha de hoy

    $mysqli_obj->begin_transaction();

    try {
        // --- NUEVA LÓGICA PRINCIPAL: Actualizar estados según uso diario y uso acumulado ---

        // 1. Obtener IDs de prendas que fueron usadas HOY
        $used_today_ids = [];
        $sql_get_used_today = "SELECT DISTINCT prenda_id FROM historial_usos WHERE DATE(fecha) = ?";
        if ($stmt_get_used = $mysqli_obj->prepare($sql_get_used_today)) {
            $stmt_get_used->bind_param("s", $today_date);
            $stmt_get_used->execute();
            $result_used_today = $stmt_get_used->get_result();
            while ($row_used = $result_used_today->fetch_assoc()) {
                $used_today_ids[] = $row_used['prenda_id'];
            }
            $stmt_get_used->close();
        } else {
            throw new Exception("Error al obtener IDs de prendas usadas hoy: " . $mysqli_obj->error);
        }

        // 2. Obtener TODAS las prendas (excepto las ilimitadas que no se gestionan automáticamente)
        // para decidir su nuevo estado.
        $sql_get_all_prendas = "SELECT id, tipo, estado, usos_esta_semana, uso_ilimitado FROM prendas WHERE uso_ilimitado = FALSE";
        $result_all_prendas = $mysqli_obj->query($sql_get_all_prendas);

        $updates_queue = []; // Array para almacenar las actualizaciones a realizar

        if ($result_all_prendas) {
            while ($prenda = $result_all_prendas->fetch_assoc()) {
                $prenda_id = $prenda['id'];
                $prenda_tipo = $prenda['tipo'];
                $current_estado = $prenda['estado'];
                $current_usos_semana = $prenda['usos_esta_semana'];
                $new_estado = $current_estado; // Por defecto, el estado no cambia

                $was_used_today = in_array($prenda_id, $used_today_ids);

                // Solo las prendas de uso_ilimitado no son gestionadas automáticamente
                if ($prenda['uso_ilimitado']) {
                    $new_estado = $current_estado; // No cambiar estado automáticamente
                } else {
                    // Obtener los límites de uso para esta prenda
                    $usageStatus = getUsageLimitStatus($prenda_tipo, $current_usos_semana);

                    if ($current_usos_semana > $usageStatus['max_uses']) {
                        // Si los usos son IGUALES O MAYORES que el límite, la prenda pasa a 'sucio'
                        $new_estado = 'sucio';
                    }
                }

                // Si el estado necesita ser cambiado, lo añadimos a la cola de actualizaciones
                if ($new_estado !== $current_estado) {
                    $updates_queue[$prenda_id] = $new_estado;
                }
            }
        } else {
            throw new Exception("Error al obtener todas las prendas: " . $mysqli_obj->error);
        }

        // 3. Ejecutar las actualizaciones masivas
        $rows_updated_count = 0;
        if (!empty($updates_queue)) {
            $sql_update_single_prenda = "UPDATE prendas SET estado = ? WHERE id = ?";
            if ($stmt_update = $mysqli_obj->prepare($sql_update_single_prenda)) {
                foreach ($updates_queue as $id_to_update => $new_state_value) {
                    $stmt_update->bind_param("si", $new_state_value, $id_to_update);
                    $stmt_update->execute();
                    $rows_updated_count += $stmt_update->affected_rows;
                }
                $stmt_update->close();
            } else {
                throw new Exception("Error al preparar la actualización final de estados: " . $mysqli_obj->error);
            }
        }

        $mysqli_obj->commit();
        $response['success'] = true;
        $response['message'] = "Estados de prendas actualizados. Total de prendas actualizadas: $rows_updated_count.";
    } catch (Exception $e) {
        $mysqli_obj->rollback();
        $response['message'] = "Error en la transacción de actualización de estados: " . $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
