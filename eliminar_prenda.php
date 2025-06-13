<?php

include 'bd.php';
header('Content-Type: application/json');

// Verificar que se haya recibido el ID
if (empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Falta el ID de la prenda']);
    exit;
}

$id = $_POST['id'];

try {
    // Opcional: Obtener el nombre del archivo de imagen actual para eliminarlo (si deseas eliminar la imagen también)
    $stmtImg = $pdo->prepare("SELECT foto FROM prendas WHERE id = :id");
    $stmtImg->execute([':id' => $id]);
    $foto = $stmtImg->fetchColumn();

    // Eliminar prenda de la base de datos
    $stmt = $pdo->prepare("DELETE FROM prendas WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Si existía una imagen y el archivo existe, eliminarla
    if ($foto && file_exists($foto)) {
        unlink($foto);
    }

    echo json_encode(['success' => true, 'message' => 'Prenda eliminada correctamente']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
