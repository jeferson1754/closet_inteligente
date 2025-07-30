<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outfit_id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $contexto = $_POST['contexto'] ?? '';
    $clima_base = $_POST['clima_base'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';
    $prendas_seleccionadas_ids = $_POST['prendas'] ?? []; // IDs de prendas seleccionadas (puede ser un array vacío si desmarcó todo)

    // Nuevo parámetro para forzar la creación de un outfit duplicado al editar
    // Usar el operador de coalescencia nula para asegurar que siempre esté definida
    $force_duplicate_edit = ($_POST['force_duplicate_edit'] ?? 'false') === 'true';

    if (!$outfit_id || empty($nombre)) {
        $response['message'] = 'ID o nombre del outfit no proporcionado.';
        echo json_encode($response);
        exit;
    }

    if (empty($prendas_seleccionadas_ids)) {
        $response['message'] = 'El outfit debe contener al menos una prenda.';
        echo json_encode($response);
        exit;
    }


    $mysqli_obj->begin_transaction(); // Iniciar transacción

    try {
        // --- NUEVA LÓGICA: Verificar duplicados por combinación de prendas al EDITAR ---
        sort($prendas_seleccionadas_ids); // Ordenar para una comparación consistente
        $nueva_combinacion_hash = md5(implode(',', $prendas_seleccionadas_ids));

        $sql_check_duplicate = "
            SELECT id, nombre
            FROM outfits
            WHERE prendas_combinadas_hash = ? AND id != ?
            LIMIT 1;
        ";

        if (!$force_duplicate_edit) { // Solo verificar si no se ha forzado la edición duplicada
            if ($stmt_duplicate = $mysqli_obj->prepare($sql_check_duplicate)) {
                $stmt_duplicate->bind_param("si", $nueva_combinacion_hash, $outfit_id); // Excluir el outfit que estamos editando
                $stmt_duplicate->execute();
                $result_duplicate = $stmt_duplicate->get_result();

                if ($result_duplicate->num_rows > 0) {
                    $existing_outfit = $result_duplicate->fetch_assoc();
                    $response['success'] = false;
                    $response['message'] = 'La nueva combinación de prendas ya existe en el outfit: "' . htmlspecialchars($existing_outfit['nombre']) . '". ¿Deseas guardar los cambios de todas formas?';
                    $response['code'] = 'DUPLICATE_OUTFIT_ON_EDIT'; // Código especial para el frontend
                    echo json_encode($response);
                    $stmt_duplicate->close();
                    $mysqli_obj->rollback(); // Revertir si hay algo en la transacción antes de pedir confirmación
                    exit; // Detener la ejecución para que el frontend pida confirmación
                }
                $stmt_duplicate->close();
            } else {
                throw new Exception("Error al preparar la verificación de duplicados al editar: " . $mysqli_obj->error);
            }
        }
        // --- FIN NUEVA LÓGICA ---

        // 1. Actualizar los datos principales del outfit
        $sql_update_outfit = "UPDATE outfits SET nombre = ?, contexto = ?, clima_base = ?, comentarios = ?, prendas_combinadas_hash = ? WHERE id = ?";
        if ($stmt_update_outfit = $mysqli_obj->prepare($sql_update_outfit)) {
            $stmt_update_outfit->bind_param("sssssi", $nombre, $contexto, $clima_base, $comentarios, $nueva_combinacion_hash, $outfit_id);
            $stmt_update_outfit->execute();
            $stmt_update_outfit->close();
        } else {
            throw new Exception("Error al preparar la actualización del outfit: " . $mysqli_obj->error);
        }


        // 2. Eliminar todas las asociaciones de prendas existentes para este outfit
        $sql_delete_associations = "DELETE FROM outfit_prendas WHERE outfit_id = ?";
        if ($stmt_delete_associations = $mysqli_obj->prepare($sql_delete_associations)) {
            $stmt_delete_associations->bind_param("i", $outfit_id);
            $stmt_delete_associations->execute();
            $stmt_delete_associations->close();
        } else {
            throw new Exception("Error al preparar la eliminación de asociaciones de prendas: " . $mysqli_obj->error);
        }

        // 3. Insertar las nuevas asociaciones de prendas
        // 3. Insertar las nuevas asociaciones de prendas
        if (!empty($prendas_seleccionadas_ids)) {
            $sql_insert_associations = "INSERT INTO outfit_prendas (outfit_id, prenda_id) VALUES (?, ?)";
            if ($stmt_insert_associations = $mysqli_obj->prepare($sql_insert_associations)) {
                foreach ($prendas_seleccionadas_ids as $prenda_id) {
                    $clean_prenda_id = intval($prenda_id);
                    if ($clean_prenda_id > 0) {
                        // Asegúrate de que $stmt_insert_associations no sea null aquí
                        if (!$stmt_insert_associations->bind_param("ii", $outfit_id, $clean_prenda_id)) {
                            throw new Exception("Error al bindear parámetros para prenda ID " . $clean_prenda_id . ": " . $stmt_insert_associations->error);
                        }
                        if (!$stmt_insert_associations->execute()) {
                            throw new Exception("Error al ejecutar inserción para prenda ID " . $clean_prenda_id . ": " . $stmt_insert_associations->error);
                        }
                    }
                }
                $stmt_insert_associations->close();
            } else {
                throw new Exception("Error al preparar la inserción de nuevas asociaciones de prendas: " . $mysqli_obj->error);
            }
        }

        $mysqli_obj->commit(); // Confirmar la transacción
        $response['success'] = true;
        $response['message'] = 'Outfit actualizado exitosamente.';
    } catch (Exception $e) {
        $mysqli_obj->rollback(); // Revertir la transacción en caso de error
        $response['message'] = 'Error al actualizar el outfit: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
// $mysqli_obj->close();
