<?php

include 'bd.php';

header('Content-Type: application/json');

// Validar campos requeridos
$campos = ['nombre', 'tipo', 'color_principal', 'tela', 'textura', 'estampado', 'clima_apropiado', 'formalidad','comentarios'];
foreach ($campos as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "Falta el campo: $campo"]);
        exit;
    }
}

$id_categoria = determinarCategoriaId($_POST['nombre']);


// Procesar imagen si se incluye
$foto = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $nombreOriginal = $_FILES['foto']['name'];
    $rutaTemporal = $_FILES['foto']['tmp_name'];
    $nombreArchivo = uniqid() . '_' . basename($nombreOriginal);
    $rutaDestino = 'uploads/' . $nombreArchivo;

    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true); // Crear carpeta si no existe
    }

    if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
        $foto = $rutaDestino;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
        exit;
    }
}

// Insertar en la base de datos
try {
    $stmt = $pdo->prepare("
        INSERT INTO prendas 
        (nombre, tipo, color_principal, tela, textura, estampado, clima_apropiado, formalidad, estado, foto,
        fecha_agregado, comentarios)
        VALUES (
        :nombre, :tipo, :color_principal, :tela, :textura, :estampado, :clima_apropiado, :formalidad, 'disponible', :foto, :fecha_agregado, :comentarios)
    ");

    $stmt->execute([
        ':nombre' => $_POST['nombre'],
        ':tipo' => $_POST['tipo'],
        ':color_principal' => $_POST['color_principal'],
        ':tela' => $_POST['tela'],
        ':textura' => $_POST['textura'],
        ':estampado' => $_POST['estampado'],
        ':clima_apropiado' => $_POST['clima_apropiado'],
        ':formalidad' => $_POST['formalidad'],
        ':foto' => $foto,
        ':fecha_agregado' => $fecha_actual,
        ':comentarios' => $_POST['comentarios'],
    ]);

    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'foto' => $foto
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
