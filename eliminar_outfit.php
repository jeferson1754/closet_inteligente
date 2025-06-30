<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj o $pdo)

header('Content-Type: application/json'); // Indica que la respuesta es JSON

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['outfit_id']) && is_numeric($_POST['outfit_id'])) {
        $outfit_id = intval($_POST['outfit_id']);

        // Iniciar transacción (opcional, pero buena práctica para operaciones relacionadas)
        $mysqli_obj->begin_transaction();

        try {
            // 1. Eliminar entradas relacionadas en outfit_prendas
            $sql_delete_prendas = "DELETE FROM outfit_prendas WHERE outfit_id = ?";
            if ($stmt_prendas = $mysqli_obj->prepare($sql_delete_prendas)) {
                $stmt_prendas->bind_param("i", $outfit_id);
                $stmt_prendas->execute();
                $stmt_prendas->close();
            } else {
                throw new Exception("Error al preparar la eliminación de prendas del outfit: " . $mysqli_obj->error);
            }

            // 2. Eliminar el outfit principal
            $sql_delete_outfit = "DELETE FROM outfits WHERE id = ?";
            if ($stmt_outfit = $mysqli_obj->prepare($sql_delete_outfit)) {
                $stmt_outfit->bind_param("i", $outfit_id);
                $stmt_outfit->execute();

                if ($stmt_outfit->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Outfit eliminado correctamente.';
                    $mysqli_obj->commit(); // Confirmar la transacción
                } else {
                    throw new Exception("No se encontró el outfit con ID " . $outfit_id . " o no se pudo eliminar.");
                }
                $stmt_outfit->close();
            } else {
                throw new Exception("Error al preparar la eliminación del outfit: " . $mysqli_obj->error);
            }
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
