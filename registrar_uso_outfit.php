<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj)

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['outfit_id']) && is_numeric($_POST['outfit_id'])) {
        $outfit_id = intval($_POST['outfit_id']);
        $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true'; // Nuevo parámetro
        $force_dirty_use = isset($_POST['force_dirty_use']) && $_POST['force_dirty_use'] === 'true'; // NUEVO: Para forzar uso con prendas sucias

        $sql_update_prendas = "
        UPDATE prendas p
        JOIN outfit_prendas op ON op.prenda_id = p.id
        SET p.estado = 'disponible'
        WHERE p.estado = 'en uso'";

        $mysqli_obj->query($sql_update_prendas);


        // Establecer la zona horaria a Santiago para cálculos de fecha/hora precisos
        date_default_timezone_set('America/Santiago');

        $mysqli_obj->begin_transaction(); // Iniciar transacción

        try {
            // 0. Lógica para reiniciar contadores semanales si es una nueva semana
            // Obtenemos la fecha de hoy, inicio de la semana actual
            $today = date('Y-m-d');
            $start_of_current_week = date('Y-m-d', strtotime('monday this week'));

            // Obtener las prendas, su estado actual, usos_esta_semana, tipo y última fecha de reset semanal
            $sql_check_resets_and_status = "SELECT id, tipo, estado, usos_esta_semana, uso_ilimitado, fecha_ultimo_reset_semanal FROM prendas";
            $result_check_resets_and_status = $mysqli_obj->query($sql_check_resets_and_status);

            if ($result_check_resets_and_status) {
                while ($prenda_data = $result_check_resets_and_status->fetch_assoc()) {
                    $prenda_id = $prenda_data['id'];
                    $last_reset_date = $prenda_data['fecha_ultimo_reset_semanal'];
                    $current_estado = $prenda_data['estado'];
                    $current_usos = $prenda_data['usos_esta_semana'];
                    $prenda_tipo = $prenda_data['tipo'];
                    $uso_ilimitado = $prenda_data['uso_ilimitado'];

                    $reset_needed = false;
                    if ($last_reset_date === null) {
                        $reset_needed = true;
                    } else {
                        $start_of_last_recorded_week = date('Y-m-d', strtotime($last_reset_date . ' monday this week'));
                        if ($start_of_current_week > $start_of_last_recorded_week) {
                            $reset_needed = true; // Es una nueva semana
                        }
                    }

                    if ($reset_needed) {
                        $new_estado_after_reset = $current_estado; // Por defecto, mantener el estado

                        // Lógica para cambiar estado solo si no es de uso ilimitado
                        if (!$uso_ilimitado) {
                            $usageStatus = getUsageLimitStatus($prenda_tipo, $current_usos);

                            // Si la prenda estaba en uso (y no fue "limpiada" o reseteada antes) O sus usos superaron el límite
                            if ($current_estado === 'en uso' || $current_usos >= $usageStatus['max_uses']) {
                                $new_estado_after_reset = 'sucio';
                            }
                            // Si no fue usada y sus usos no superaron el límite, podríamos considerar 'disponible'
                            // Pero la lógica de actualizar_estados_diarios.php ya lo gestiona diariamente.
                            // Aquí nos enfocamos en marcar como 'sucio' si la semana pasada se "agotó" su uso.
                        }

                        // Actualizar usos_esta_semana a 0, fecha_ultimo_reset_semanal a inicio de semana actual
                        // Y actualizar el estado si la lógica anterior lo ha determinado.
                        $sql_update_on_reset = "UPDATE prendas SET usos_esta_semana = 0, fecha_ultimo_reset_semanal = ?, estado = ? WHERE id = ?";
                        if ($stmt_update_on_reset = $mysqli_obj->prepare($sql_update_on_reset)) {
                            $stmt_update_on_reset->bind_param("ssi", $start_of_current_week, $new_estado_after_reset, $prenda_id);
                            $stmt_update_on_reset->execute();
                            $stmt_update_on_reset->close();
                        } else {
                            throw new Exception("Error al preparar actualización de prenda al inicio de nueva semana para ID " . $prenda_id . ": " . $mysqli_obj->error);
                        }
                    }
                }
            } else {
                throw new Exception("Error al verificar prendas para reinicio semanal: " . $mysqli_obj->error);
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

            // --- NUEVA LÓGICA: Verificar estado de las prendas del outfit antes de usarlo ---
            // Solo si NO se ha forzado el uso con prendas sucias
            if (!$force_dirty_use) {
                $non_available_prendas_names = [];
                $non_available_states = ['sucio', 'lavando', 'prestado']; // Estados que impiden el uso

                // Construir placeholders para la consulta IN
                $placeholders = implode(',', array_fill(0, count($prendas_ids), '?'));
                $sql_check_prendas_status = "SELECT id, nombre, estado, uso_ilimitado FROM prendas WHERE id IN ($placeholders)";

                if ($stmt_check_status = $mysqli_obj->prepare($sql_check_prendas_status)) {
                    $types = str_repeat('i', count($prendas_ids));
                    // Crear un array de referencias para bind_param
                    $bind_params_array = [$types];
                    foreach ($prendas_ids as $key => $value) {
                        $bind_params_array[] = &$prendas_ids[$key];
                    }
                    call_user_func_array([$stmt_check_status, 'bind_param'], $bind_params_array);

                    $stmt_check_status->execute();
                    $result_status = $stmt_check_status->get_result();
                    while ($prenda_status = $result_status->fetch_assoc()) {
                        // Si la prenda no es de uso ilimitado y está en un estado no disponible
                        if (!$prenda_status['uso_ilimitado'] && in_array($prenda_status['estado'], $non_available_states)) {
                            $non_available_prendas_names[] = htmlspecialchars($prenda_status['nombre']) . ' (' . htmlspecialchars($prenda_status['estado']) . ')';
                        }
                    }
                    $stmt_check_status->close();
                } else {
                    throw new Exception("Error al preparar verificación de estado de prendas: " . $mysqli_obj->error);
                }

                if (!empty($non_available_prendas_names)) {
                    $response['success'] = false;
                    $response['message'] = 'El outfit contiene prendas que no están disponibles: ' . implode(', ', $non_available_prendas_names) . '. ¿Deseas usarlo de todos modos?';
                    $response['code'] = 'PRENDAS_NO_DISPONIBLES'; // Código para que el frontend lo interprete
                    echo json_encode($response);
                    $mysqli_obj->rollback(); // Revertir cualquier cambio antes de pedir confirmación
                    exit; // Detener la ejecución
                }
            }
            // --- FIN NUEVA LÓGICA ---

            //--- NUEVA LÓGICA: Verificar si ya hay un outfit para hoy y si se permite sobrescribir ---
            if (!$force_overwrite) {
                $sql_check_outfit_today = "SELECT id, nombre FROM outfits WHERE fecha_ultimo_uso_outfit = ? AND id != ?";
                if ($stmt_check_outfit = $mysqli_obj->prepare($sql_check_outfit_today)) {
                    $stmt_check_outfit->bind_param("si", $fecha_actual, $outfit_id); // Excluir el outfit actual si ya se usó hoy
                    $stmt_check_outfit->execute();
                    $result_check_outfit = $stmt_check_outfit->get_result();

                    if ($result_check_outfit->num_rows > 0) {
                        $existing_outfit = $result_check_outfit->fetch_assoc();
                        $response['success'] = false;
                        $response['message'] = 'Ya hay un outfit registrado para hoy: "' . htmlspecialchars($existing_outfit['nombre']) . '". ¿Deseas reemplazarlo con este outfit?';
                        $response['code'] = 'ALREADY_USED_TODAY'; // Código para que el frontend lo interprete
                        echo json_encode($response);
                        $stmt_check_outfit->close();
                        $mysqli_obj->rollback(); // No commit si se necesita confirmación
                        exit; // Detener la ejecución aquí, el frontend pedirá confirmación
                    }
                    $stmt_check_outfit->close();
                } else {
                    throw new Exception("Error al verificar outfit existente para hoy: " . $mysqli_obj->error);
                }
            }
            // --- FIN NUEVA LÓGICA ---

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
            }

            // --- NUEVA LÓGICA: Actualizar fecha_ultimo_uso_outfit ---
            $sql_update_outfit_date = "UPDATE outfits SET fecha_ultimo_uso_outfit = CURDATE() WHERE id = ?";
            if ($stmt_update_outfit_date = $mysqli_obj->prepare($sql_update_outfit_date)) {
                $stmt_update_outfit_date->bind_param("i", $outfit_id);
                $stmt_update_outfit_date->execute();
                $stmt_update_outfit_date->close();
            } else {
                throw new Exception("Error al actualizar la fecha de último uso del outfit: " . $mysqli_obj->error);
            }
            // --- FIN NUEVA LÓGICA ---


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
