<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'outfit' => null, 'prendas_asociadas' => []];

if (isset($_GET['outfit_id']) && is_numeric($_GET['outfit_id'])) {
    $outfit_id = intval($_GET['outfit_id']);

    // 1. Obtener detalles del outfit
    $sql_outfit = "SELECT id, nombre, contexto, clima_base, comentarios FROM outfits WHERE id = ?";
    if ($stmt_outfit = $mysqli_obj->prepare($sql_outfit)) {
        $stmt_outfit->bind_param("i", $outfit_id);
        $stmt_outfit->execute();
        $result_outfit = $stmt_outfit->get_result();
        $outfit_data = $result_outfit->fetch_assoc();
        $stmt_outfit->close();

        if ($outfit_data) {
            $response['outfit'] = $outfit_data;
            $response['success'] = true;
        } else {
            $response['message'] = 'Outfit no encontrado.';
            echo json_encode($response);
            exit;
        }
    } else {
        $response['message'] = 'Error al preparar la consulta del outfit: ' . $mysqli_obj->error;
        echo json_encode($response);
        exit;
    }

    // 2. Obtener IDs de las prendas asociadas a este outfit
    $sql_prendas_asociadas = "SELECT prenda_id FROM outfit_prendas WHERE outfit_id = ?";
    if ($stmt_prendas_asociadas = $mysqli_obj->prepare($sql_prendas_asociadas)) {
        $stmt_prendas_asociadas->bind_param("i", $outfit_id);
        $stmt_prendas_asociadas->execute();
        $result_prendas_asociadas = $stmt_prendas_asociadas->get_result();

        while ($row = $result_prendas_asociadas->fetch_assoc()) {
            $response['prendas_asociadas'][] = ['id' => $row['prenda_id']]; // Solo necesitamos el ID aquí
        }
        $stmt_prendas_asociadas->close();
    } else {
        $response['message'] = 'Error al preparar la consulta de prendas asociadas: ' . $mysqli_obj->error;
        // No salimos, solo registramos el error, el outfit ya fue encontrado.
    }
} else {
    $response['message'] = 'ID de outfit no proporcionado o inválido.';
}

echo json_encode($response);
// $mysqli_obj->close();
