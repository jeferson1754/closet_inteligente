<?php
include 'bd.php'; // Asegúrate de que tu conexión a la base de datos esté aquí.

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_outfit = $_POST['nombre'] ?? '';
    $contexto_outfit = $_POST['contexto'] ?? 'General'; // Usar el primer contexto si viene un array, o default
    $clima_base_outfit = $_POST['clima_base'] ?? 'todo';
    $comentarios_outfit = $_POST['comentarios'] ?? '';
    $prendas_ids = $_POST['prendas'] ?? []; // Array de IDs de prendas

    if (empty($nombre_outfit) || empty($prendas_ids)) {
        $response['message'] = 'Nombre del outfit o prendas incompletos.';
        echo json_encode($response);
        exit;
    }

    $mysqli_obj->begin_transaction(); // Iniciar transacción

    try {
        // 1. Insertar el nuevo outfit en la tabla 'outfits'
        $sql_insert_outfit = "INSERT INTO outfits (nombre, contexto, clima_base, comentarios) VALUES (?, ?, ?, ?)";
        if ($stmt_outfit = $mysqli_obj->prepare($sql_insert_outfit)) {
            $stmt_outfit->bind_param("ssss", $nombre_outfit, $contexto_outfit, $clima_base_outfit, $comentarios_outfit);
            $stmt_outfit->execute();
            $new_outfit_id = $mysqli_obj->insert_id; // Obtener el ID del outfit recién insertado
            $stmt_outfit->close();

            if (!$new_outfit_id) {
                throw new Exception("Error al obtener el ID del outfit recién creado.");
            }
        } else {
            throw new Exception("Error al preparar la inserción del outfit: " . $mysqli_obj->error);
        }

        // 2. Insertar las relaciones en la tabla 'outfit_prendas'
        $sql_insert_outfit_prendas = "INSERT INTO outfit_prendas (outfit_id, prenda_id) VALUES (?, ?)";
        if ($stmt_outfit_prendas = $mysqli_obj->prepare($sql_insert_outfit_prendas)) {
            foreach ($prendas_ids as $prenda_id) {
                $stmt_outfit_prendas->bind_param("ii", $new_outfit_id, $prenda_id);
                $stmt_outfit_prendas->execute();
            }
            $stmt_outfit_prendas->close();
        } else {
            throw new Exception("Error al preparar la inserción de prendas al outfit: " . $mysqli_obj->error);
        }

        $mysqli_obj->commit(); // Confirmar la transacción
        $response['success'] = true;
        $response['message'] = 'Outfit "' . htmlspecialchars($nombre_outfit) . '" creado exitosamente a partir de la sugerencia.';
    } catch (Exception $e) {
        $mysqli_obj->rollback(); // Revertir la transacción en caso de error
        $response['message'] = 'Error en la transacción: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);

// Cierra la conexión a la base de datos si es necesario
// $mysqli_obj->close();
