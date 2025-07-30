<?php
include 'bd.php'; // Tu conexión a la base de datos

header('Content-Type: text/plain'); // Para ver la salida directamente en el navegador

echo "Iniciando backfill de prendas_combinadas_hash...\n\n";

$mysqli_obj->begin_transaction(); // Iniciar transacción para seguridad

try {
    // 1. Obtener todos los outfits que tienen prendas_combinadas_hash NULO
    $sql_get_null_outfits = "SELECT id FROM outfits WHERE prendas_combinadas_hash IS NULL";
    $result_null_outfits = $mysqli_obj->query($sql_get_null_outfits);

    if (!$result_null_outfits) {
        throw new Exception("Error al obtener outfits con hash nulo: " . $mysqli_obj->error);
    }

    $outfits_to_update = [];
    while ($row = $result_null_outfits->fetch_assoc()) {
        $outfits_to_update[] = $row['id'];
    }

    if (empty($outfits_to_update)) {
        echo "No se encontraron outfits con prendas_combinadas_hash nulo. ¡La base de datos ya está al día!\n";
        $mysqli_obj->commit();
        exit;
    }

    echo "Se encontraron " . count($outfits_to_update) . " outfits para actualizar.\n\n";

    $updated_count = 0;

    // 2. Para cada outfit, obtener sus prendas y calcular el hash
    foreach ($outfits_to_update as $outfit_id) {
        $sql_get_prendas = "SELECT prenda_id FROM outfit_prendas WHERE outfit_id = ? ORDER BY prenda_id ASC";
        if ($stmt_get_prendas = $mysqli_obj->prepare($sql_get_prendas)) {
            $stmt_get_prendas->bind_param("i", $outfit_id);
            $stmt_get_prendas->execute();
            $result_prendas = $stmt_get_prendas->get_result();

            $prendas_ids = [];
            while ($row_prenda = $result_prendas->fetch_assoc()) {
                $prendas_ids[] = $row_prenda['prenda_id'];
            }
            $stmt_get_prendas->close();

            if (!empty($prendas_ids)) {
                $prendas_combinadas_hash = md5(implode(',', $prendas_ids));

                // 3. Actualizar el outfit con el nuevo hash
                $sql_update_outfit_hash = "UPDATE outfits SET prendas_combinadas_hash = ? WHERE id = ?";
                if ($stmt_update_hash = $mysqli_obj->prepare($sql_update_outfit_hash)) {
                    $stmt_update_hash->bind_param("si", $prendas_combinadas_hash, $outfit_id);
                    $stmt_update_hash->execute();
                    $stmt_update_hash->close();
                    $updated_count++;
                    echo " - Outfit ID " . $outfit_id . " actualizado con hash: " . $prendas_combinadas_hash . "\n";
                } else {
                    echo " - Error al preparar la actualización de hash para Outfit ID " . $outfit_id . ": " . $mysqli_obj->error . "\n";
                }
            } else {
                // Manejar outfits sin prendas asociadas si es posible
                // Si un outfit no tiene prendas, su hash también sería un md5('')
                $prendas_combinadas_hash = md5(''); // Hash para outfit vacío
                $sql_update_outfit_hash = "UPDATE outfits SET prendas_combinadas_hash = ? WHERE id = ?";
                if ($stmt_update_hash = $mysqli_obj->prepare($sql_update_outfit_hash)) {
                    $stmt_update_hash->bind_param("si", $prendas_combinadas_hash, $outfit_id);
                    $stmt_update_hash->execute();
                    $stmt_update_hash->close();
                    $updated_count++;
                    echo " - Outfit ID " . $outfit_id . " (sin prendas) actualizado con hash de vacío: " . $prendas_combinadas_hash . "\n";
                }
            }
        } else {
            echo " - Error al preparar la consulta de prendas para Outfit ID " . $outfit_id . ": " . $mysqli_obj->error . "\n";
        }
    }

    $mysqli_obj->commit(); // Confirmar todos los cambios
    echo "\nBackfill completado. Total de outfits actualizados: " . $updated_count . ".\n";
} catch (Exception $e) {
    $mysqli_obj->rollback(); // Revertir todo si hay un error
    echo "\n¡ERROR CRÍTICO DURANTE EL BACKFILL!\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "La transacción ha sido revertida. Por favor, revisa el error y vuelve a intentar.\n";
} finally {
    // $mysqli_obj->close(); // Cerrar la conexión si bd.php no lo hace automáticamente al final del script
}
