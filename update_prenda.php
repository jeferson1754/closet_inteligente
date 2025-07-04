<?php


include 'bd.php';
header('Content-Type: application/json');

$campos = ['nombre', 'tipo', 'color_principal', 'tela', 'textura', 'estampado', 'clima_apropiado', 'estado', 'formalidad', 'fecha_agregado', 'id'];
foreach ($campos as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "Falta el campo: $campo"]);
        exit;
    }
}

$id_categoria = determinarCategoriaId($_POST['nombre']);

$_POST['foto'] ?? null;

// NUEVA LÍNEA: Recibir el valor del checkbox uso_ilimitado
$uso_ilimitado = isset($_POST['uso_ilimitado']) ? 1 : 0; // Si está marcado, será 1 (TRUE); si no, 0 (FALSE
// NUEVA LÍNEA: Recibir el valor de usos_esta_semana
$usos_esta_semana = intval($_POST['usos_esta_semana'] ?? 0);

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

try {
    $stmt = $pdo->prepare("
        UPDATE prendas SET
            nombre = :nombre,
            tipo = :tipo,
            color_principal = :color_principal,
            tela = :tela,
            textura = :textura,
            estampado = :estampado,
            clima_apropiado = :clima_apropiado,
            formalidad = :formalidad,
            estado = :estado,
            foto = :foto,
            fecha_agregado = :fecha_agregado,
            detalles_adicionales = :detalles_adicionales,
            uso_ilimitado = :uso_ilimitado,
            usos_esta_semana = :usos_esta_semana
        WHERE id = :id
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
        ':estado' => $_POST['estado'],
        ':foto' => $foto,
        ':fecha_agregado' => $_POST['fecha_agregado'],
        ':detalles_adicionales' => $_POST['comentarios'],
        ':uso_ilimitado' => $uso_ilimitado, // NUEVA LÍNEA: Guardar el valor del checkbox
        ':usos_esta_semana' => $usos_esta_semana,
        ':id' => $_POST['id'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Prenda actualizada correctamente']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}

// Si la petición NO es AJAX, redirigir a index.php
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: index.php');
    exit;
}
