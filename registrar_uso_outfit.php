<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj)

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['outfit_id']) && is_numeric($_POST['outfit_id'])) {
        $outfit_id = intval($_POST['outfit_id']);

        // Establecer la zona horaria a Santiago para cálculos de fecha/hora precisos
        date_default_timezone_set('America/Santiago');

        $mysqli_obj->begin_transaction(); // Iniciar transacción

        try {
            // 0. Lógica para reiniciar contadores semanales si es una nueva semana
            // Obtenemos la fecha de hoy, inicio de la semana actual
            $today = date('Y-m-d');
            $start_of_current_week = date('Y-m-d', strtotime('monday this week'));

            // Consulta para obtener las prendas y su última fecha de reset semanal
            $sql_check_resets = "SELECT id, fecha_ultimo_reset_semanal FROM prendas";
            $result_check_resets = $mysqli_obj->query($sql_check_resets);

            if ($result_check_resets) {
                while ($prenda_reset = $result_check_resets->fetch_assoc()) {
                    $prenda_id_to_check = $prenda_reset['id'];
                    $last_reset_date = $prenda_reset['fecha_ultimo_reset_semanal'];

                    $reset_needed = false;
                    if ($last_reset_date === null) {
                        // Si nunca se ha reseteado, se considera que necesita un reset para establecer la fecha
                        $reset_needed = true;
                    } else {
                        $start_of_last_recorded_week = date('Y-m-d', strtotime($last_reset_date . ' monday this week'));
                        if ($start_of_current_week > $start_of_last_recorded_week) {
                            // Si el inicio de la semana actual es posterior al inicio de la semana del último reset, reiniciar
                            $reset_needed = true;
                        }
                    }

                    if ($reset_needed) {
                        $sql_reset_prenda_counter = "UPDATE prendas SET usos_esta_semana = 0, fecha_ultimo_reset_semanal = ? WHERE id = ?";
                        if ($stmt_reset = $mysqli_obj->prepare($sql_reset_prenda_counter)) {
                            $stmt_reset->bind_param("si", $start_of_current_week, $prenda_id_to_check);
                            $stmt_reset->execute();
                            $stmt_reset->close();
                        } else {
                            throw new Exception("Error al preparar reinicio de contador semanal para prenda " . $prenda_id_to_check . ": " . $mysqli_obj->error);
                        }
                    }
                }
            } else {
                throw new Exception("Error al verificar contadores semanales de prendas: " . $mysqli_obj->error);
            }
            // FIN: Lógica para reiniciar contadores semanales

            // 1. Obtener los IDs de las prendas asociadas a este outfit
            $sql_get_prendas = "SELECT prenda_id FROM outfit_prendas WHERE outfit_id = ?";
            if ($stmt_get_prendas = $mysqli_obj->prepare($sql_get_prendas)) {
                $stmt_get_prendas->bind_param("i", $outfit_id);
                $stmt_get_prendas->execute();
                $result_prendas = $stmt_get_prendas->get_result();

                $prendas_ids = [];
                while ($row = $result_prendas->fetch_assoc()) {
                    $prendas_ids[] = $row['prenda_id'];
                }
                $stmt_get_prendas->close();
            } else {
                throw new Exception("Error al preparar la consulta de prendas del outfit: " . $mysqli_obj->error);
            }

            if (empty($prendas_ids)) {
                throw new Exception("El outfit seleccionado no tiene prendas asociadas.");
            }

            // 2. Registrar el uso para cada prenda y actualizar su estado y contador semanal
            $sql_log_use = "INSERT INTO historial_usos (prenda_id, fecha) VALUES (?, NOW())"; // Eliminado outfit_id
            $sql_update_prenda = "UPDATE prendas SET estado = 'en uso', usos_esta_semana = usos_esta_semana + 1 WHERE id = ?"; // Incrementar contador

            foreach ($prendas_ids as $prenda_id) {
                // Registrar uso en historial_usos
                if ($stmt_log = $mysqli_obj->prepare($sql_log_use)) {
                    $stmt_log->bind_param("i", $prenda_id);
                    $stmt_log->execute();
                    $stmt_log->close();
                } else {
                    throw new Exception("Error al preparar el registro de uso para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }

                // Actualizar estado y contador semanal de la prenda
                if ($stmt_update = $mysqli_obj->prepare($sql_update_prenda)) {
                    $stmt_update->bind_param("i", $prenda_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    throw new Exception("Error al preparar la actualización de estado y contador para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }

                // Actualizar estado y contador semanal de la prenda
                if ($stmt_update = $mysqli_obj->prepare($sql_update_prenda)) {
                    $stmt_update->bind_param("i", $prenda_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    throw new Exception("Error al preparar la actualización de estado y contador para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }
            }

            // 3. Desmarcar cualquier otro outfit como "del día"
            $sql_unmark_others = "UPDATE outfits SET es_outfit_del_dia = FALSE WHERE es_outfit_del_dia = TRUE";
            if ($stmt_unmark = $mysqli_obj->prepare($sql_unmark_others)) {
                $stmt_unmark->execute();
                $stmt_unmark->close();
            } else {
                throw new Exception("Error al desmarcar outfits anteriores como del día: " . $mysqli_obj->error);
            }

            // 4. Marcar el outfit actual como "del día"
            $sql_mark_current = "UPDATE outfits SET es_outfit_del_dia = TRUE WHERE id = ?";
            if ($stmt_mark = $mysqli_obj->prepare($sql_mark_current)) {
                $stmt_mark->bind_param("i", $outfit_id);
                $stmt_mark->execute();
                $stmt_mark->close();
            } else {
                throw new Exception("Error al marcar el outfit actual como del día: " . $mysqli_obj->error);
            }

            $mysqli_obj->commit(); // Confirmar la transacción
            $response['success'] = true;
            $response['message'] = 'Outfit registrado como usado y prendas actualizadas.';
        } catch (Exception $e) {
            $mysqli_obj->rollback(); // Revertir la transacción en caso de error
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['message'] = 'ID de outfit no proporcionado o inválido.';
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);

// Cierra la conexión a la base de datos si es necesario (depende de tu bd.php)
// $mysqli_obj->close();
