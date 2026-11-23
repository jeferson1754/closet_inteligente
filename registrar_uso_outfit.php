<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj)

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['outfit_id']) && is_numeric($_POST['outfit_id'])) {
        $outfit_id = intval($_POST['outfit_id']);
        $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';
        $force_dirty_use = isset($_POST['force_dirty_use']) && $_POST['force_dirty_use'] === 'true';
        $force_repetition = isset($_POST['force_repetition']) && $_POST['force_repetition'] === 'true';

        $solo_validar = isset($_POST['solo_validar']) && $_POST['solo_validar'] === 'true';
        $fecha_destino = isset($_POST['fecha_destino']) ? $_POST['fecha_destino'] : 'hoy';

        // Establecer la zona horaria a Santiago para cálculos de fecha/hora precisos
        date_default_timezone_set('America/Santiago');
        $fecha_actual = date('Y-m-d');
        $datetime_actual = date('Y-m-d H:i:s');

        $mysqli_obj->begin_transaction(); // Iniciar transacción

        try {
            // 0. Lógica para reiniciar contadores semanales si es una nueva semana
            // Solo ejecutar el reset real si NO es modo "solo_validar"
            if (!$solo_validar) {
                $start_of_current_week = date('Y-m-d', strtotime('monday this week'));

                $sql_check_resets_and_status = "SELECT id, estado, fecha_ultimo_reset_semanal FROM prendas";
                $result_check_resets_and_status = $mysqli_obj->query($sql_check_resets_and_status);

                if ($result_check_resets_and_status) {
                    while ($prenda_data = $result_check_resets_and_status->fetch_assoc()) {
                        $prenda_id = $prenda_data['id'];
                        $last_reset_date = $prenda_data['fecha_ultimo_reset_semanal'];
                        $current_estado = $prenda_data['estado'];

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
                            $sql_update_on_reset = "UPDATE prendas SET fecha_ultimo_reset_semanal = ?, usos_esta_semana = 0, estado = ? WHERE id = ?";
                            if ($stmt_update_on_reset = $mysqli_obj->prepare($sql_update_on_reset)) {
                                $stmt_update_on_reset->bind_param("ssi", $start_of_current_week, $current_estado, $prenda_id);
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
                    $prendas_ids[] = intval($row['prenda_id']);
                }
                $stmt_get_prendas->close();
            } else {
                throw new Exception("Error al preparar la consulta de prendas del outfit: " . $mysqli_obj->error);
            }

            if (empty($prendas_ids)) {
                throw new Exception("El outfit seleccionado no tiene prendas asociadas.");
            }

            // 2. Validar repetición de prendas en el historial (Hoy/Mañana, Ayer, Semana pasada)
            $aviso_repeticion = "";
            $ids_string = implode(',', $prendas_ids);
            $fecha_base_sql = ($fecha_destino === 'manana') ? "DATE_ADD(CURDATE(), INTERVAL 1 DAY)" : "CURDATE()";

            $sql_historial = "
                SELECT 
                    DATE(h.fecha) as fecha_uso,
                    COUNT(DISTINCT h.prenda_id) as coincidencias,
                    GROUP_CONCAT(DISTINCT CONCAT('- ', p.nombre) SEPARATOR '\n') as prendas_repetidas
                FROM historial_usos h
                JOIN prendas p ON h.prenda_id = p.id
                WHERE h.prenda_id IN ($ids_string)
                AND (
                    DATE(h.fecha) = $fecha_base_sql OR
                    DATE(h.fecha) = DATE_SUB($fecha_base_sql, INTERVAL 1 DAY) OR 
                    DATE(h.fecha) = DATE_SUB($fecha_base_sql, INTERVAL 7 DAY)
                )
                GROUP BY DATE(h.fecha)";

            $res_historial = $mysqli_obj->query($sql_historial);

            if ($res_historial) {
                while ($row_h = $res_historial->fetch_assoc()) {
                    $fecha_uso = $row_h['fecha_uso'];
                    $cant = (int)$row_h['coincidencias'];
                    $lista_prendas = $row_h['prendas_repetidas'];

                    if ($cant >= 3) {
                        if ($fecha_destino === 'manana') {
                            $fecha_objetivo = date('Y-m-d', strtotime('tomorrow'));
                            $fecha_anterior = date('Y-m-d');

                            $texto_objetivo = "Mañana ya usarías estas prendas:\n";
                            $texto_anterior = "Hoy usaste este mismo conjunto de prendas:\n";
                            $texto_pasado   = "El %s pasado usaste estas mismas prendas:\n";
                        } else {
                            $fecha_objetivo = date('Y-m-d');
                            $fecha_anterior = date('Y-m-d', strtotime('-1 day'));

                            $texto_objetivo = "Hoy ya usaste estas prendas:\n";
                            $texto_anterior = "Ayer usaste este mismo conjunto de prendas:\n";
                            $texto_pasado   = "El %s pasado usaste estas mismas prendas:\n";
                        }

                        if ($fecha_uso == $fecha_objetivo) {
                            $aviso_repeticion = $texto_objetivo . $lista_prendas;
                        } elseif ($fecha_uso == $fecha_anterior) {
                            $aviso_repeticion = $texto_anterior . $lista_prendas;
                        } else {
                            $nombre_dia_semana = isset($dias[date('l', strtotime($fecha_uso))]) ? $dias[date('l', strtotime($fecha_uso))] : date('l', strtotime($fecha_uso));
                            $aviso_repeticion = sprintf($texto_pasado, $nombre_dia_semana) . $lista_prendas;
                        }
                        break;
                    }
                }
            }

            if ($aviso_repeticion != "" && !$force_repetition) {
                $mysqli_obj->rollback();
                echo json_encode([
                    'success' => false,
                    'is_repetition' => true,
                    'message' => $aviso_repeticion . "\n ¿Deseas usarlo de todas formas?"
                ]);
                exit;
            }

            // 3. Verificar estado de las prendas del outfit (sucio, lavando, prestado)
            if (!$force_dirty_use) {
                $non_available_prendas_names = [];
                $non_available_states = ['sucio', 'lavando', 'prestado'];

                $placeholders = implode(',', array_fill(0, count($prendas_ids), '?'));
                $sql_check_prendas_status = "SELECT id, nombre, estado, uso_ilimitado FROM prendas WHERE id IN ($placeholders)";

                if ($stmt_check_status = $mysqli_obj->prepare($sql_check_prendas_status)) {
                    $types = str_repeat('i', count($prendas_ids));
                    $bind_params_array = [$types];
                    foreach ($prendas_ids as $key => $value) {
                        $bind_params_array[] = &$prendas_ids[$key];
                    }
                    call_user_func_array([$stmt_check_status, 'bind_param'], $bind_params_array);

                    $stmt_check_status->execute();
                    $result_status = $stmt_check_status->get_result();
                    while ($prenda_status = $result_status->fetch_assoc()) {
                        if (!$prenda_status['uso_ilimitado'] && in_array(strtolower($prenda_status['estado']), $non_available_states)) {
                            $non_available_prendas_names[] = htmlspecialchars($prenda_status['nombre']) . ' (' . htmlspecialchars($prenda_status['estado']) . ')';
                        }
                    }
                    $stmt_check_status->close();
                } else {
                    throw new Exception("Error al preparar verificación de estado de prendas: " . $mysqli_obj->error);
                }

                if (!empty($non_available_prendas_names)) {
                    $mysqli_obj->rollback();
                    $response['success'] = false;
                    $response['message'] = 'El outfit contiene prendas que no están disponibles: ' . implode(', ', $non_available_prendas_names) . '. ¿Deseas usarlo de todos modos?';
                    $response['code'] = 'PRENDAS_NO_DISPONIBLES';
                    echo json_encode($response);
                    exit;
                }
            }

            // 4. INTERCEPCIÓN PARA MODO "SÓLO VALIDAR" (Verificar Uso Mañana)
            // Se ejecuta ANTES de verificar si ya hay un outfit registrado hoy para que no choquen
            if ($solo_validar) {
                $mysqli_obj->rollback();
                $response['success'] = true;
                $response['message'] = 'El outfit está completamente disponible para usarse mañana.';
                echo json_encode($response);
                exit;
            }

            // 5. Verificar si ya hay un outfit registrado para hoy y si se permite sobrescribir
            if (!$force_overwrite) {
                $sql_check_outfit_today = "SELECT id, nombre FROM outfits WHERE fecha_ultimo_uso_outfit = ? AND id != ?";
                if ($stmt_check_outfit = $mysqli_obj->prepare($sql_check_outfit_today)) {
                    $stmt_check_outfit->bind_param("si", $fecha_actual, $outfit_id);
                    $stmt_check_outfit->execute();
                    $result_check_outfit = $stmt_check_outfit->get_result();

                    if ($result_check_outfit->num_rows > 0) {
                        $existing_outfit = $result_check_outfit->fetch_assoc();
                        $stmt_check_outfit->close();
                        $mysqli_obj->rollback();

                        $response['success'] = false;
                        $response['message'] = 'Ya hay un outfit registrado para hoy: "' . htmlspecialchars($existing_outfit['nombre']) . '". ¿Deseas reemplazarlo con este outfit?';
                        $response['code'] = 'ALREADY_USED_TODAY';
                        echo json_encode($response);
                        exit;
                    }
                    $stmt_check_outfit->close();
                } else {
                    throw new Exception("Error al verificar outfit existente para hoy: " . $mysqli_obj->error);
                }
            }

            // 6. Liberar prendas que estaban 'en uso' anteriormente (SOLO cuando se confirma el uso real)
            $sql_liberar_anteriores = "
                UPDATE prendas 
                SET estado = 'disponible', fecha_cambio_estado = ? 
                WHERE estado = 'en uso'";
            if ($stmt_liberar = $mysqli_obj->prepare($sql_liberar_anteriores)) {
                $stmt_liberar->bind_param("s", $datetime_actual);
                $stmt_liberar->execute();
                $stmt_liberar->close();
            } else {
                throw new Exception("Error al liberar prendas en uso anteriores: " . $mysqli_obj->error);
            }

            // 7. Registrar el uso para cada prenda y actualizar su estado y contador semanal
            $sql_log_use = "INSERT INTO historial_usos (fecha, prenda_id) VALUES (?, ?)";
            $sql_update_prenda = "UPDATE prendas SET estado = 'en uso', fecha_cambio_estado = ?, usos_esta_semana = usos_esta_semana + 1 WHERE id = ?";

            foreach ($prendas_ids as $prenda_id) {
                if ($stmt_log = $mysqli_obj->prepare($sql_log_use)) {
                    $stmt_log->bind_param("si", $datetime_actual, $prenda_id);
                    $stmt_log->execute();
                    $stmt_log->close();
                } else {
                    throw new Exception("Error al preparar el registro de uso para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }

                if ($stmt_update = $mysqli_obj->prepare($sql_update_prenda)) {
                    $stmt_update->bind_param("si", $datetime_actual, $prenda_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    throw new Exception("Error al preparar la actualización de estado y contador para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }
            }

            // 8. Actualizar fecha_ultimo_uso_outfit
            $sql_update_outfit_date = "UPDATE outfits SET fecha_ultimo_uso_outfit = CURDATE() WHERE id = ?";
            if ($stmt_update_outfit_date = $mysqli_obj->prepare($sql_update_outfit_date)) {
                $stmt_update_outfit_date->bind_param("i", $outfit_id);
                $stmt_update_outfit_date->execute();
                $stmt_update_outfit_date->close();
            } else {
                throw new Exception("Error al actualizar la fecha de último uso del outfit: " . $mysqli_obj->error);
            }

            $mysqli_obj->commit(); // Confirmar la transacción
            $response['success'] = true;
            $response['message'] = 'Outfit registrado como usado y prendas actualizadas.';
        } catch (Exception $e) {
            $mysqli_obj->rollback();
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['message'] = 'ID de outfit no proporcionado o inválido.';
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>