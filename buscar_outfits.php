<?php
// buscar_outfits.php - Maneja la lógica de la búsqueda avanzada de outfits

// 1. Incluir la conexión a la base de datos (asumiendo que bd.php contiene la conexión PDO $pdo)
include 'bd.php';

// 2. Definir el encabezado para devolver una respuesta JSON
header('Content-Type: application/json');

// 3. Verificar si la solicitud es válida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prenda_ids'])) {

    $prenda_ids = $_POST['prenda_ids'];

    if (empty($prenda_ids)) {
        echo json_encode(['success' => true, 'outfits' => [], 'message' => 'No se seleccionaron prendas.']);
        exit;
    }

    // Convertir la lista de IDs de string (ej: "1,5,10") a un array de enteros
    $id_list = array_map('intval', explode(',', $prenda_ids));

    // Contar cuántos IDs únicos hay para la cláusula HAVING (la clave de esta búsqueda)
    $num_prendas_a_buscar = count($id_list);

    // Preparar los marcadores de posición para la consulta (ej: ?, ?, ?)
    $placeholders = implode(',', array_fill(0, $num_prendas_a_buscar, '?'));

    try {
        // 4. Query SQL Avanzada: 
        // Esta consulta busca los outfits que contienen TODAS las prendas seleccionadas.
        $sql = "
            SELECT
                o.id,
                o.nombre,
                o.contexto,
                o.clima_base
            FROM
                outfits o
            JOIN
                outfit_prendas otp ON o.id= otp.outfit_id
            WHERE
                otp.prenda_id IN ($placeholders)
            GROUP BY
                o.id, o.nombre, o.contexto, o.clima_base
            HAVING
                COUNT(DISTINCT otp.prenda_id) = ?
        ";

        // Preparar la declaración
        $stmt = $pdo->prepare($sql);

        // Combinar los IDs de las prendas y el número de prendas en un solo array para bindeo
        $params = array_merge($id_list, [$num_prendas_a_buscar]);

        // Ejecutar la declaración
        $stmt->execute($params);

        // Obtener todos los resultados
        $outfits_filtrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Devolver la respuesta en formato JSON
        echo json_encode([
            'success' => true,
            'outfits' => $outfits_filtrados,
            'message' => 'Búsqueda avanzada exitosa.'
        ]);
    } catch (PDOException $e) {
        // Manejo de errores
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} else {
    // Solicitud no válida
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida o datos faltantes.']);
}
