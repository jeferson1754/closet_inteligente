<?php
// cargar_outfits.php

include 'bd.php'; // Incluye tu conexión a la BD
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offset']) && isset($_POST['limit'])) {

    $offset = (int)$_POST['offset'];
    $limit = (int)$_POST['limit'];

    $ordenamiento_sql = "
ORDER BY
o.fecha_ultimo_uso_outfit IS NULL DESC,
o.fecha_ultimo_uso_outfit DESC,
o.id DESC
";

    // Consulta para obtener el siguiente lote
    $sql = "
SELECT
o.id, o.nombre, o.contexto, o.clima_base,
COUNT(ot.prenda_id) AS total_prendas,
GROUP_CONCAT(ot.prenda_id) AS prendas_ids,
o.fecha_ultimo_uso_outfit
FROM outfits o
LEFT JOIN outfit_prendas ot ON o.id = ot.outfit_id
GROUP BY o.id, o.nombre, o.contexto, o.clima_base
{$ordenamiento_sql}
LIMIT {$limit}
OFFSET {$offset}
";

    $result = $mysqli_obj->query($sql);
    $outfits_data = [];

    if ($result) {
        while ($outfit = $result->fetch_assoc()) {
            // Preparar la data igual que en index.php
            $prendas_ids = $outfit['prendas_ids'] ? explode(',', $outfit['prendas_ids']) : [];
            $outfit['data_prendas_json'] = htmlspecialchars(json_encode($prendas_ids));

            // Lógica del estado visual (NUEVO vs. USADO)
            if (empty($outfit['fecha_ultimo_uso_outfit'])) {
                $outfit['clase_estado'] = 'outfit-nuevo';
                $outfit['etiqueta_estado'] = '<span class="badge bg-success ms-2"><i class="fas fa-magic me-1"></i> NUEVO</span>';
            } else {
                $outfit['clase_estado'] = 'outfit-usado';
                $fecha_uso_formato = date('d/m/Y', strtotime($outfit['fecha_ultimo_uso_outfit']));
                $outfit['etiqueta_estado'] = '<span class="badge bg-secondary ms-2"><i class="fas fa-clock me-1"></i> Usado: ' . $fecha_uso_formato . '</span>';
            }

            $outfits_data[] = $outfit;
        }
    }

    echo json_encode([
        'success' => true,
        'outfits' => $outfits_data,
        'count' => count($outfits_data)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
}
