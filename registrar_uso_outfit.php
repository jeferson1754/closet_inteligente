<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj o $pdo)

header('Content-Type: application/json'); // Indica que la respuesta es JSON

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['outfit_id']) && is_numeric($_POST['outfit_id'])) {
        $outfit_id = intval($_POST['outfit_id']);

        $mysqli_obj->begin_transaction(); // Iniciar transacción

        try {
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

            // 2. Registrar el uso para cada prenda y actualizar su estado
            $sql_log_use = "INSERT INTO historial_usos (prenda_id, fecha) VALUES (?, ?)";
            $sql_update_estado = "UPDATE prendas SET estado = 'en uso' WHERE id = ?"; // O 'no disponible', 'usada', etc.

            foreach ($prendas_ids as $prenda_id) {
                // Registrar uso en historial_usos
                if ($stmt_log = $mysqli_obj->prepare($sql_log_use)) {
                    $stmt_log->bind_param("is", $prenda_id, $fecha_actual);
                    $stmt_log->execute();
                    $stmt_log->close();
                } else {
                    throw new Exception("Error al preparar el registro de uso para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
                }

                // Actualizar estado de la prenda
                if ($stmt_update = $mysqli_obj->prepare($sql_update_estado)) {
                    $stmt_update->bind_param("i", $prenda_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    throw new Exception("Error al preparar la actualización de estado para prenda ID " . $prenda_id . ": " . $mysqli_obj->error);
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
