<?php
include 'bd.php'; // Asegúrate de que este archivo contiene tu conexión a la base de datos ($mysqli_obj o $pdo)

header('Content-Type: application/json'); // Indica que la respuesta es JSON

if (isset($_GET['outfit_id']) && is_numeric($_GET['outfit_id'])) {
    $outfit_id = intval($_GET['outfit_id']);

    // Consulta para obtener los IDs de las prendas asociadas al outfit
    $sql_prendas_ids = "SELECT prenda_id FROM outfit_prendas WHERE outfit_id = ?";

    // Preparar la consulta
    if ($stmt = $mysqli_obj->prepare($sql_prendas_ids)) {
        $stmt->bind_param("i", $outfit_id); // "i" indica que es un entero
        $stmt->execute();
        $result_prendas_ids = $stmt->get_result();

        $prendas_ids = [];
        while ($row = $result_prendas_ids->fetch_assoc()) {
            $prendas_ids[] = $row['prenda_id'];
        }
        $stmt->close();

        if (!empty($prendas_ids)) {
            // Consulta para obtener los detalles de las prendas
            $placeholders = implode(',', array_fill(0, count($prendas_ids), '?'));
            // En obtener_prendas_outfit.php, en la consulta sql_detalles_prendas:
            $sql_detalles_prendas = "SELECT id, nombre, tipo, color_principal, foto, comentarios FROM prendas WHERE id IN ($placeholders)";
            // Añadido 'comentarios'

            if ($stmt_detalles = $mysqli_obj->prepare($sql_detalles_prendas)) {
                // --- INICIO DE LA MODIFICACIÓN CLAVE ---
                // Crear string de tipos para bind_param (todos serán 'i' de entero)
                $types = str_repeat('i', count($prendas_ids));

                // Crear un array de referencias para bind_param
                $bind_names = [$types];
                foreach ($prendas_ids as $key => $value) {
                    $bind_names[] = &$prendas_ids[$key]; // Pasar por referencia
                }

                // Usar call_user_func_array con el array de referencias
                call_user_func_array([$stmt_detalles, 'bind_param'], $bind_names);
                // --- FIN DE LA MODIFICACIÓN CLAVE ---

                $stmt_detalles->execute();
                $result_detalles_prendas = $stmt_detalles->get_result();

                $prendas = [];
                while ($prenda = $result_detalles_prendas->fetch_assoc()) {
                    // Si no hay foto, puedes asignar una URL de placeholder
                    $prenda['foto'] = !empty($prenda['foto']) ? $prenda['foto'] : 'https://via.placeholder.com/100x100?text=Sin+Imagen';
                    $prendas[] = $prenda;
                }
                $stmt_detalles->close();
                if (!empty($prendas_ids)) {
                    // ... (código existente para obtener detalles de prendas) ...

                    // Nueva consulta para obtener los detalles del outfit
                    $sql_outfit_details = "SELECT nombre, contexto, clima_base, comentarios FROM outfits WHERE id = ?";
                    if ($stmt_outfit_details = $mysqli_obj->prepare($sql_outfit_details)) {
                        $stmt_outfit_details->bind_param("i", $outfit_id);
                        $stmt_outfit_details->execute();
                        $result_outfit_details = $stmt_outfit_details->get_result();
                        $outfit_details = $result_outfit_details->fetch_assoc();
                        $stmt_outfit_details->close();
                    } else {
                        throw new Exception("Error al obtener detalles del outfit: " . $mysqli_obj->error);
                    }

                    echo json_encode([
                        'success' => true,
                        'prendas' => $prendas,
                        'outfit_details' => $outfit_details // Añadimos los detalles del outfit aquí
                    ]);
                } else {
                    // ... (código existente si no hay prendas) ...
                    // Aun así, intenta obtener los detalles del outfit para mostrarlos
                    $sql_outfit_details = "SELECT nombre, contexto, clima_base, comentarios FROM outfits WHERE id = ?";
                    if ($stmt_outfit_details = $mysqli_obj->prepare($sql_outfit_details)) {
                        $stmt_outfit_details->bind_param("i", $outfit_id);
                        $stmt_outfit_details->execute();
                        $result_outfit_details = $stmt_outfit_details->get_result();
                        $outfit_details = $result_outfit_details->fetch_assoc();
                        $stmt_outfit_details->close();
                    } else {
                        // Si falla incluso la obtención del outfit, maneja el error.
                        echo json_encode(['success' => false, 'message' => 'Error al obtener detalles del outfit: ' . $mysqli_obj->error]);
                        exit;
                    }

                    echo json_encode(['success' => true, 'prendas' => [], 'outfit_details' => $outfit_details, 'message' => 'No se encontraron prendas para este outfit.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de detalles de prendas: ' . $mysqli_obj->error]);
            }
        } else {
            echo json_encode(['success' => true, 'prendas' => [], 'message' => 'No se encontraron prendas para este outfit.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de IDs de prendas: ' . $mysqli_obj->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de outfit no proporcionado o inválido.']);
}

// Cierra la conexión a la base de datos si es necesario (depende de tu bd.php)
// $mysqli_obj->close();
