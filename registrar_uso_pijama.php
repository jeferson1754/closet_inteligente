<?php
include 'bd.php';

header('Content-Type: application/json');
date_default_timezone_set('America/Santiago');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $force_dirty_use  = isset($_POST['force_dirty_use']) && $_POST['force_dirty_use'] === 'true';
    $force_repeat_day = isset($_POST['force_repeat_day']) && $_POST['force_repeat_day'] === 'true';

    $fecha_actual      = date('Y-m-d');
    $datetime_actual   = date('Y-m-d H:i:s');
    $start_of_week     = date('Y-m-d', strtotime('monday this week'));

    $mysqli_obj->begin_transaction();

    try {
        // 1. Verificar estado del pijama actual y si ya se usó hoy
        $res_meta = $mysqli_obj->query("SELECT fecha_ultimo_uso FROM pijama_actual WHERE id = 1");
        $meta = $res_meta ? $res_meta->fetch_assoc() : null;

        if ($meta && $meta['fecha_ultimo_uso'] === $fecha_actual && !$force_repeat_day) {
            $mysqli_obj->rollback();
            $response['success'] = false;
            $response['code'] = 'PIJAMA_ALREADY_USED_TODAY';
            $response['message'] = 'Ya registraste el uso de tu pijama hoy. ¿Deseas volver a sumar un uso de todas formas?';
            echo json_encode($response);
            exit;
        }

        // 2. Obtener las prendas configuradas en el pijama actual
        $sql_prendas = "SELECT p.id, p.nombre, p.estado, p.uso_ilimitado, p.fecha_ultimo_reset_semanal 
                        FROM pijama_prendas pp
                        JOIN prendas p ON pp.prenda_id = p.id";
        $res_prendas = $mysqli_obj->query($sql_prendas);

        $prendas_pijama = [];
        if ($res_prendas) {
            while ($row = $res_prendas->fetch_assoc()) {
                $prendas_pijama[] = $row;
            }
        }

        if (empty($prendas_pijama)) {
            throw new Exception("No tienes prendas asignadas como pijama. Usa el botón 'Cambiar' para configurarlas.");
        }

        // 3. Verificar si alguna prenda está sucia/lavando/prestada (si no viene forzado)
        if (!$force_dirty_use) {
            $non_available_states = ['sucio', 'lavando', 'prestado'];
            $no_disponibles = [];

            foreach ($prendas_pijama as $prenda) {
                if (!$prenda['uso_ilimitado'] && in_array(strtolower($prenda['estado']), $non_available_states)) {
                    $no_disponibles[] = htmlspecialchars($prenda['nombre']) . ' (' . htmlspecialchars($prenda['estado']) . ')';
                }
            }

            if (!empty($no_disponibles)) {
                $mysqli_obj->rollback();
                $response['success'] = false;
                $response['code'] = 'PRENDAS_NO_DISPONIBLES';
                $response['message'] = 'Tu pijama tiene prendas no disponibles: ' . implode(', ', $no_disponibles) . '. ¿Deseas usarlo de todos modos?';
                echo json_encode($response);
                exit;
            }
        }

        // 4. Liberar prendas de día que estaban 'en uso' a 'disponible'
        $sql_liberar = "UPDATE prendas SET estado = 'disponible', fecha_cambio_estado = ? WHERE estado = 'en uso'";
        if ($stmt_liberar = $mysqli_obj->prepare($sql_liberar)) {
            $stmt_liberar->bind_param("s", $datetime_actual);
            $stmt_liberar->execute();
            $stmt_liberar->close();
        } else {
            throw new Exception("Error al actualizar estado de prendas anteriores: " . $mysqli_obj->error);
        }

        // 5. Registrar uso en historial y actualizar cada prenda del pijama
        $sql_log = "INSERT INTO historial_usos (fecha, prenda_id) VALUES (?, ?)";
        $stmt_log = $mysqli_obj->prepare($sql_log);

        $sql_update_prenda = "UPDATE prendas 
                              SET estado = 'en uso', 
                                  fecha_cambio_estado = ?, 
                                  usos_esta_semana = ?, 
                                  fecha_ultimo_reset_semanal = ? 
                              WHERE id = ?";
        $stmt_update = $mysqli_obj->prepare($sql_update_prenda);

        if (!$stmt_log || !$stmt_update) {
            throw new Exception("Error al preparar consultas de actualización: " . $mysqli_obj->error);
        }

        foreach ($prendas_pijama as $prenda) {
            $id = intval($prenda['id']);
            $last_reset = $prenda['fecha_ultimo_reset_semanal'];

            // Verificar si corresponde reiniciar contador semanal antes de sumar +1
            $es_nueva_semana = ($last_reset === null) || ($start_of_week > date('Y-m-d', strtotime($last_reset . ' monday this week')));
            
            // Obtenemos usos actuales si no es nueva semana
            if ($es_nueva_semana) {
                $nuevos_usos = 1;
            } else {
                $res_u = $mysqli_obj->query("SELECT usos_esta_semana FROM prendas WHERE id = {$id}");
                $row_u = $res_u->fetch_assoc();
                $nuevos_usos = intval($row_u['usos_esta_semana']) + 1;
            }

            // Insertar historial
            $stmt_log->bind_param("si", $datetime_actual, $id);
            $stmt_log->execute();

            // Actualizar prenda
            $stmt_update->bind_param("sisi", $datetime_actual, $nuevos_usos, $start_of_week, $id);
            $stmt_update->execute();
        }

        $stmt_log->close();
        $stmt_update->close();

        // 6. Marcar fecha_ultimo_uso en pijama_actual
        $sql_meta_up = "UPDATE pijama_actual SET fecha_ultimo_uso = ? WHERE id = 1";
        if ($stmt_meta_up = $mysqli_obj->prepare($sql_meta_up)) {
            $stmt_meta_up->bind_param("s", $fecha_actual);
            $stmt_meta_up->execute();
            $stmt_meta_up->close();
        }

        $mysqli_obj->commit();
        $response['success'] = true;
        $response['message'] = '¡Pijama registrado para hoy! Las prendas pasaron a estado "en uso".';
    } catch (Exception $e) {
        $mysqli_obj->rollback();
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
?>