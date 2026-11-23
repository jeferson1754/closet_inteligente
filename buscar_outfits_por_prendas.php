<?php
include 'bd.php';

header('Content-Type: application/json');

$response = ['success' => false, 'outfits' => [], 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prendas_raw = isset($_POST['prendas']) && is_array($_POST['prendas']) ? $_POST['prendas'] : [];
    $modo = (isset($_POST['modo']) && $_POST['modo'] === 'alguna') ? 'alguna' : 'todas';

    // Sanitizar IDs de prendas a enteros positivos únicos
    $prendas_ids = array_values(array_unique(array_filter(array_map('intval', $prendas_raw), function ($id) {
        return $id > 0;
    })));

    if (empty($prendas_ids)) {
        $response['message'] = 'No se seleccionaron prendas para filtrar.';
        echo json_encode($response);
        exit;
    }

    $cantidad_requerida = count($prendas_ids);
    $placeholders = implode(',', array_fill(0, $cantidad_requerida, '?'));

    // Si es 'todas', exigimos que el outfit contenga cada una de las prendas seleccionadas
    $having_clause = ($modo === 'todas')
        ? "HAVING COUNT(DISTINCT CASE WHEN op.prenda_id IN ($placeholders) THEN op.prenda_id END) = ?"
        : "HAVING COUNT(DISTINCT CASE WHEN op.prenda_id IN ($placeholders) THEN op.prenda_id END) >= 1";

    $sql = "
        SELECT 
            o.id,
            o.nombre,
            o.contexto,
            o.clima_base,
            o.fecha_ultimo_uso_outfit,
            COUNT(DISTINCT op.prenda_id) AS total_prendas,
            GROUP_CONCAT(DISTINCT op.prenda_id) AS prendas_ids
        FROM outfits o
        JOIN outfit_prendas op ON o.id = op.outfit_id
        GROUP BY o.id, o.nombre, o.contexto, o.clima_base, o.fecha_ultimo_uso_outfit
        {$having_clause}
        ORDER BY 
            o.fecha_ultimo_uso_outfit IS NULL DESC,
            o.fecha_ultimo_uso_outfit DESC,
            o.id DESC
    ";

    if ($stmt = $mysqli_obj->prepare($sql)) {
        if ($modo === 'todas') {
            $types = str_repeat('i', $cantidad_requerida) . 'i';
            $params = array_merge($prendas_ids, [$cantidad_requerida]);
        } else {
            $types = str_repeat('i', $cantidad_requerida);
            $params = $prendas_ids;
        }

        // Vincular parámetros dinámicamente por referencia
        $bind_array = [$types];
        foreach ($params as $key => $val) {
            $bind_array[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_array);

        $stmt->execute();
        $result = $stmt->get_result();

        $outfits = [];
        while ($row = $result->fetch_assoc()) {
            $ids_arr = $row['prendas_ids'] ? explode(',', $row['prendas_ids']) : [];
            $row['data_prendas_json'] = json_encode($ids_arr);
            $row['clase_estado'] = empty($row['fecha_ultimo_uso_outfit']) ? 'outfit-nuevo' : '';
            $outfits[] = $row;
        }

        $stmt->close();
        $response['success'] = true;
        $response['outfits'] = $outfits;
    } else {
        $response['message'] = 'Error al preparar consulta de búsqueda: ' . $mysqli_obj->error;
    }
} else {
    $response['message'] = 'Método no permitido.';
}

echo json_encode($response);
?>