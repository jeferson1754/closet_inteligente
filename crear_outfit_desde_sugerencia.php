<?php
include 'bd.php'; // Asegúrate de que tu conexión a la base de datos esté aquí.

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_outfit = $_POST['nombre'] ?? '';
    $contexto_outfit = $_POST['contexto'] ?? 'General';
    $clima_base_outfit = $_POST['clima_base'] ?? 'todo';
    $comentarios_outfit = $_POST['comentarios'] ?? '';
    $prendas_ids_raw = $_POST['prendas'] ?? []; // Array de IDs de prendas (potencialmente sin validar)

    if (empty($nombre_outfit) || empty($prendas_ids_raw)) {
        $response['message'] = 'Nombre del outfit o prendas incompletos.';
        echo json_encode($response);
        exit;
    }

    // --- INICIO: Validación de IDs de Prendas ---
    $valid_prendas_ids = [];
    if (!empty($prendas_ids_raw)) {
        // Limpiar y asegurar que sean solo enteros
        $clean_prendas_ids = array_map('intval', $prendas_ids_raw);
        $clean_prendas_ids = array_filter($clean_prendas_ids, function ($id) {
            return $id > 0;
        }); // Asegurar que no sean 0 o negativos

        if (!empty($clean_prendas_ids)) {
            // Construir placeholders para la consulta IN (?)
            $placeholders = implode(',', array_fill(0, count($clean_prendas_ids), '?'));
            $sql_check_prendas = "SELECT id FROM prendas WHERE id IN ($placeholders) AND estado = 'disponible' OR uso_ilimitado = TRUE"; // Opcional: solo disponibles

            if ($stmt_check = $mysqli_obj->prepare($sql_check_prendas)) {
                // Preparar los tipos para bind_param (todos serán 'i' de entero)
                $types = str_repeat('i', count($clean_prendas_ids));
                // Usar call_user_func_array para bind_param con array de argumentos
                // ...
                // ...
                $placeholders = implode(',', array_fill(0, count($clean_prendas_ids), '?'));
                $sql_check_prendas = "SELECT id FROM prendas WHERE id IN ($placeholders) AND estado = 'disponible' OR uso_ilimitado = TRUE";

                if ($stmt_check = $mysqli_obj->prepare($sql_check_prendas)) {
                    // --- INICIO DE LA SOLUCIÓN REPETIDA PERO NECESARIA ---
                    $types = str_repeat('i', count($clean_prendas_ids)); // Genera el string de tipos (ej. 'iiii')

                    // Prepara el array de argumentos para bind_param, donde cada elemento es una REFERENCIA
                    $bind_params_array = [$types]; // El primer elemento es el string de tipos
                    foreach ($clean_prendas_ids as $key => $value) {
                        $bind_params_array[] = &$clean_prendas_ids[$key]; // ¡Pasa cada ID de prenda por referencia!
                    }

                    // Llama a bind_param usando call_user_func_array con el array de referencias
                    call_user_func_array([$stmt_check, 'bind_param'], $bind_params_array);
                    // --- FIN DE LA SOLUCIÓN ---

                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    // ...
                    while ($row = $result_check->fetch_assoc()) {
                        $valid_prendas_ids[] = $row['id']; // Recopilar solo los IDs que realmente existen
                    }
                    $stmt_check->close();
                } else {
                    // Si la preparación de la consulta falla, lanzamos una excepción
                    throw new Exception("Error al preparar la verificación de prendas: " . $mysqli_obj->error);
                }
            }
        }

        if (empty($valid_prendas_ids)) {
            $response['message'] = 'Ninguna de las prendas seleccionadas es válida o está disponible. El outfit no pudo ser creado.';
            echo json_encode($response);
            exit;
        }
        // --- FIN: Validación de IDs de Prendas ---


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

            // 2. Insertar las relaciones en la tabla 'outfit_prendas' usando solo los IDs válidos
            $sql_insert_outfit_prendas = "INSERT INTO outfit_prendas (outfit_id, prenda_id) VALUES (?, ?)";
            if ($stmt_outfit_prendas = $mysqli_obj->prepare($sql_insert_outfit_prendas)) {
                foreach ($valid_prendas_ids as $prenda_id) { // Usamos $valid_prendas_ids
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
            $response['prendas_ids'] = $valid_prendas_ids; // <- Aquí agregas los IDs válidos

        } catch (Exception $e) {
            $mysqli_obj->rollback(); // Revertir la transacción en caso de error
            $response['message'] = 'Error en la transacción: ' . $e->getMessage();
        }
    } // <-- Esta llave cierra el if ($_SERVER['REQUEST_METHOD'] === 'POST')
    else {
        $response['message'] = 'Método de solicitud no permitido.';
    }
}
// $mysqli_obj->close(); // Cierra la conexión si tu bd.php no la cierra automáticamente
echo json_encode($response);
// Fin del script