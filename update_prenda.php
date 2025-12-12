<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Actualizar Prenda</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <?php
    include 'bd.php'; // Asegúrate de que este archivo define $pdo

    function alerta($alertTitle, $alertText, $alertType, $redireccion)
    {
        echo '
    <script>
        Swal.fire({
            title: ' . json_encode($alertTitle) . ',
            text: ' . json_encode($alertText) . ',
            icon: "' . htmlspecialchars($alertType) . '",
            confirmButtonText: "OK",
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "' . htmlspecialchars($redireccion) . '";
            }
        });
    </script>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Obtener y sanitizar datos
        $id = $_POST['id'] ?? null;
        $nombre = $_POST['nombre'] ?? '';
        $tipo = $_POST['tipo'] ?? '';
        $color_principal = $_POST['color_principal'] ?? '';
        $tela = $_POST['tela'] ?? '';
        $textura = $_POST['textura'] ?? '';
        $estampado = $_POST['estampado'] ?? '';
        $clima_apropiado = $_POST['clima_apropiado'] ?? 'todo';
        $formalidad = $_POST['formalidad'] ?? 'casual';
        $estado = $_POST['estado'] ?? 'disponible';
        $fecha_agregado = $_POST['fecha_agregado'] ?? null;
        $comentarios = $_POST['comentarios'] ?? '';
        $uso_ilimitado = ($_POST['uso_ilimitado'] ?? 'off') === 'on' ? 1 : 0;
        $usos_esta_semana = intval($_POST['usos_esta_semana'] ?? 0);
        $usos_base = intval($_POST['usos_base'] ?? 0);
        $force_duplicate_name = ($_POST['force_duplicate_name'] ?? 'false') === 'true';

        if (!$id || empty($nombre) || empty($tipo)) {
            alerta('¡Error!', 'ID, nombre y tipo de prenda son obligatorios.', 'error', 'index.php');
            exit;
        }

        // 2. Manejo de imagen
        $foto_actual_db = $_POST['imagen'] ?? null;
        $newFotoPath = $foto_actual_db;

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedTypes)) {
                $nombreArchivo = uniqid() . '_' . basename($_FILES['foto']['name']);
                $rutaDestino = $uploadDir . $nombreArchivo;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino)) {
                    $newFotoPath = $rutaDestino;
                } else {
                    alerta('¡Error!', 'Error al mover la nueva imagen subida.', 'error', 'index.php');
                    exit;
                }
            } else {
                alerta('¡Error!', 'Tipo de archivo de imagen no permitido.', 'error', 'index.php');
                exit;
            }
        }

        $pdo->beginTransaction();

        try {
            if (!$force_duplicate_name) {
                $sql_check_name = "SELECT id FROM prendas WHERE nombre = :nombre AND id != :id";
                $stmt_check = $pdo->prepare($sql_check_name);
                $stmt_check->execute([':nombre' => $nombre, ':id' => $id]);

                if ($stmt_check->rowCount() > 0) {
                    $pdo->rollBack();
                    alerta('¡Aviso!', 'Ya existe otra prenda con el mismo nombre: "' . $nombre . '". Por favor, elige un nombre diferente.', 'info', 'index.php');
                    exit;
                }
            }

            $sql_update = "
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
                detalles_adicionales = :comentarios,
                uso_ilimitado = :uso_ilimitado,
                usos_esta_semana = :usos_esta_semana
            WHERE id = :id
        ";

            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([
                ':nombre' => $nombre,
                ':tipo' => $tipo,
                ':color_principal' => $color_principal,
                ':tela' => $tela,
                ':textura' => $textura,
                ':estampado' => $estampado,
                ':clima_apropiado' => $clima_apropiado,
                ':formalidad' => $formalidad,
                ':estado' => $estado,
                ':foto' => $newFotoPath,
                ':fecha_agregado' => $fecha_agregado,
                ':comentarios' => $comentarios,
                ':uso_ilimitado' => $uso_ilimitado,
                ':usos_esta_semana' => $usos_esta_semana,
                ':id' => $id
            ]);

            if ($usos_esta_semana > $usos_base) {
                // Si el uso de base cambian se incrementa el uso
                $sql_log_use = "INSERT INTO historial_usos (prenda_id, fecha) VALUES (?, ?)"; // Eliminado outfit_id
                $stmt_log = $pdo->prepare($sql_log_use);
                $stmt_log->execute([$id, $fecha_actual]);
            }

            $pdo->commit();
            alerta('¡Éxito!', 'Prenda actualizada correctamente.', 'success', 'index.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            alerta('¡Error!', 'Error de base de datos: ' . $e->getMessage(), 'error', 'index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            alerta('¡Error!', 'Error general: ' . $e->getMessage(), 'error', 'index.php');
            exit;
        }
    } else {
        alerta('¡Error!', 'Método de solicitud no permitido.', 'error', 'index.php');
        exit;
    }
    ?>

</body>

</html>