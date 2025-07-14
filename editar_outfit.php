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

    if (!$outfit_id || empty($nombre)) {
        $response['message'] = 'ID o nombre del outfit no proporcionado.';
        echo json_encode($response);
        exit;
    }

    $mysqli_obj->begin_transaction(); // Iniciar transacción

    try {
        // 1. Actualizar los datos principales del outfit
        $sql_update_outfit = "UPDATE outfits SET nombre = ?, contexto = ?, clima_base = ?, comentarios = ? WHERE id = ?";
        if ($stmt_update_outfit = $mysqli_obj->prepare($sql_update_outfit)) {
            $stmt_update_outfit->bind_param("ssssi", $nombre, $contexto, $clima_base, $comentarios, $outfit_id);
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
        if (!empty($prendas_seleccionadas_ids)) {
            $sql_insert_associations = "INSERT INTO outfit_prendas (outfit_id, prenda_id) VALUES (?, ?)";
            if ($stmt_insert_associations = $mysqli_obj->prepare($sql_insert_associations)) {
                foreach ($prendas_seleccionadas_ids as $prenda_id) {
                    // Asegúrate de que el prenda_id sea un entero válido
                    $clean_prenda_id = intval($prenda_id);
                    if ($clean_prenda_id > 0) {
                        $stmt_insert_associations->bind_param("ii", $outfit_id, $clean_prenda_id);
                        $stmt_insert_associations->execute();
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
