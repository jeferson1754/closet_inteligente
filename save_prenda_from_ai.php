<?php
include 'bd.php';

header('Content-Type: application/json');
date_default_timezone_set('America/Santiago');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = $_POST['nombre'] ?? [];
    $tipos = $_POST['tipo'] ?? [];
    $colores = $_POST['color_principal'] ?? [];
    $telas = $_POST['tela'] ?? [];
    $texturas = $_POST['textura'] ?? [];
    $estampados = $_POST['estampado'] ?? [];
    $climas = $_POST['clima_apropiado'] ?? [];
    $formalidades = $_POST['formalidad'] ?? [];
    $comentarios = $_POST['comentarios'] ?? [];
    $fotos = $_FILES['fotos'] ?? null;

    $total = count($nombres);

    if ($total === 0 || !$fotos || empty($fotos['name'][0])) {
        $response['message'] = 'No se recibieron prendas o imágenes válidas para guardar.';
        echo json_encode($response);
        exit;
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $fecha_agregado = date('Y-m-d H:i:s');
    $guardadas = 0;
    $errores = [];

    $mysqli_obj->begin_transaction();

    try {
        $sql_insert = "INSERT INTO prendas (
            nombre, tipo, color_principal, tela, textura, estampado, 
            clima_apropiado, formalidad, detalles_adicionales, foto, 
            estado, usos_esta_semana, fecha_agregado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', 0, ?)";

        $stmt = $mysqli_obj->prepare($sql_insert);
        if (!$stmt) {
            throw new Exception("Error al preparar consulta SQL: " . $mysqli_obj->error);
        }

        for ($i = 0; $i < $total; $i++) {
            $nombre = trim($nombres[$i] ?? '');
            $tipo = trim($tipos[$i] ?? '');

            if ($nombre === '' || $tipo === '') {
                $errores[] = "Prenda #" . ($i + 1) . ": Nombre y tipo son obligatorios.";
                continue;
            }

            $color = trim($colores[$i] ?? '');
            $tela = trim($telas[$i] ?? '');
            $textura = trim($texturas[$i] ?? '');
            $estampado = trim($estampados[$i] ?? '');
            $clima = trim($climas[$i] ?? 'todo');
            $formalidad = trim($formalidades[$i] ?? 'casual');
            $comentario = trim($comentarios[$i] ?? '');

            // Procesar la foto individual de la posición $i
            $ruta_foto = '';
            if (isset($fotos['error'][$i]) && $fotos['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($fotos['name'][$i], PATHINFO_EXTENSION);
                $nuevo_nombre = uniqid('prenda_') . '_' . $i . '.' . $ext;
                $destino = $upload_dir . $nuevo_nombre;

                if (move_uploaded_file($fotos['tmp_name'][$i], $destino)) {
                    $ruta_foto = $destino;
                } else {
                    throw new Exception("No se pudo subir la imagen de la prenda: " . htmlspecialchars($nombre));
                }
            } else {
                throw new Exception("Falta la imagen válida para la prenda #" . ($i + 1));
            }

            $stmt->bind_param(
                "sssssssssss",
                $nombre,
                $tipo,
                $color,
                $tela,
                $textura,
                $estampado,
                $clima,
                $formalidad,
                $comentario,
                $ruta_foto,
                $fecha_agregado
            );

            if ($stmt->execute()) {
                $guardadas++;
            } else {
                throw new Exception("Error al guardar '{$nombre}': " . $stmt->error);
            }
        }

        $stmt->close();

        if ($guardadas === 0) {
            throw new Exception("No se guardó ninguna prenda. " . implode(' ', $errores));
        }

        $mysqli_obj->commit();
        $response['success'] = true;
        $response['message'] = "Se guardaron {$guardadas} prendas de forma individual en tu clóset.";
    } catch (Exception $e) {
        $mysqli_obj->rollback();
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Método no permitido.';
}

echo json_encode($response);
?>