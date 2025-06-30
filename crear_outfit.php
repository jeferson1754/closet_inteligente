<?php
// crear_outfit.php
include 'bd.php';

try {
    $nombre = $_POST['nombre'];
    $contexto = $_POST['contexto'];
    $clima_base = $_POST['clima_base'];
    $prendas = $_POST['prendas'] ?? [];
    $comentarios = $_POST['comentarios'];

    // Insertar outfit
    $stmt = $mysqli_obj->prepare("INSERT INTO outfits (nombre, contexto, clima_base, comentarios) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $contexto, $clima_base, $comentarios);
    $stmt->execute();
    $id_outfit = $stmt->insert_id;

    // Insertar relación outfit-prenda (si hay)
    foreach ($prendas as $id_prenda) {
        $stmt2 = $mysqli_obj->prepare("INSERT INTO outfit_prendas (outfit_id, prenda_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $id_outfit, $id_prenda);
        $stmt2->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Outfit guardado con éxito.',
        'id' => $id_outfit,
        'prendas' => $prendas
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
