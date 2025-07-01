<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario (ya procesados por JS y pegados del JSON de IA)
    $nombre = $_POST['nombre_ia'] ?? '';
    $tipo = $_POST['tipo_ia'] ?? '';
    $color_principal = $_POST['color_principal_ia'] ?? '';
    $tela = $_POST['tela_ia'] ?? '';
    $textura = $_POST['textura_ia'] ?? '';
    $estampado = $_POST['estampado_ia'] ?? '';
    $clima_apropiado = $_POST['clima_apropiado_ia'] ?? 'todo'; // Default si la IA no lo da o no se mapea
    $formalidad = $_POST['formalidad_ia'] ?? 'casual'; // Default si la IA no lo da o no se mapea
    $comentarios = $_POST['comentarios_ia'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($tipo)) {
        $response['message'] = 'Nombre y tipo son campos obligatorios.';
        echo json_encode($response);
        exit;
    }

    $_POST['foto'] ?? null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $nombreOriginal = $_FILES['foto']['name'];
        $rutaTemporal = $_FILES['foto']['tmp_name'];
        $nombreArchivo = uniqid() . '_' . basename($nombreOriginal);
        $rutaDestino = 'uploads/' . $nombreArchivo;

        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
            $foto = $rutaDestino;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
            exit;
        }
    } else {
        $foto = $_POST['imagen'] ?? null; // Si no se subió una nueva imagen, usar la existente
    }

    // Insertar en la base de datos
    $sql = "INSERT INTO prendas (nombre, tipo, color_principal, tela, textura, estampado, clima_apropiado, formalidad, detalles_adicionales, foto, estado, fecha_agregado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";

    if ($stmt = $mysqli_obj->prepare($sql)) {
        // Establecer estado inicial como 'disponible' para nuevas prendas
        $estado = 'disponible';
        $stmt->bind_param(
            "sssssssssss",
            $nombre,
            $tipo,
            $color_principal,
            $tela,
            $textura,
            $estampado,
            $clima_apropiado,
            $formalidad,
            $comentarios,
            $foto,
            $estado
        );

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Prenda guardada exitosamente con descripción de IA.';
            $response['id'] = $mysqli_obj->insert_id;
        } else {
            $response['message'] = 'Error al insertar la prenda: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error al preparar la consulta de inserción: ' . $mysqli_obj->error;
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
// $mysqli_obj->close();
