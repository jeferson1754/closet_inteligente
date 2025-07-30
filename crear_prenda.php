<?php
// crear_prenda.php - Usando PDO
include 'bd.php'; // Asegúrate de que este archivo contenga tu conexión PDO, ej: $pdo = new PDO(...)

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recuperar datos del POST
    $nombre = $_POST['nombre'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $color_principal = $_POST['color_principal'] ?? '';
    $tela = $_POST['tela'] ?? '';
    $textura = $_POST['textura'] ?? '';
    $estampado = $_POST['estampado'] ?? '';
    $clima_apropiado = $_POST['clima_apropiado'] ?? 'todo';
    $formalidad = $_POST['formalidad'] ?? 'casual';
    $comentarios = $_POST['comentarios'] ?? '';

    // Nuevo parámetro para forzar la creación de un nombre duplicado
    $force_duplicate_name = isset($_POST['force_duplicate_name']) && $_POST['force_duplicate_name'] === 'true';

    if (empty($nombre) || empty($tipo)) {
        $response['message'] = 'Nombre y tipo de prenda son obligatorios.';
        echo json_encode($response);
        exit;
    }

    // Manejo de la foto
    $foto_path = null; // Usar null si no hay foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/'; // Asegúrate de que esta carpeta exista y tenga permisos
        $fileName = uniqid() . '-' . basename($_FILES['foto']['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = array('jpg', 'png', 'jpeg', 'gif');

        if (in_array($fileExtension, $allowedTypes)) {
            $destPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destPath)) {
                $foto_path = $destPath;
            } else {
                $response['message'] = 'Error al subir la imagen.';
                echo json_encode($response);
                exit;
            }
        } else {
            $response['message'] = 'Tipo de archivo de imagen no permitido.';
            echo json_encode($response);
            exit;
        }
    }

    // Fecha actual para campos de fecha
    date_default_timezone_set('America/Santiago'); // Ajusta a tu zona horaria
    $fecha_actual = date('Y-m-d'); // Para fecha_agregado

    // Iniciar transacción PDO
    $pdo->beginTransaction();

    try {
        // --- Verificar nombre duplicado (AJUSTADO PARA PDO) ---
        $sql_check_name = "SELECT id FROM prendas WHERE nombre = :nombre";
        if (!$force_duplicate_name) {
            $stmt_check = $pdo->prepare($sql_check_name);
            $stmt_check->bindParam(':nombre', $nombre);
            $stmt_check->execute();

            if ($stmt_check->rowCount() > 0) { // rowCount() para saber si hay filas
                $response['success'] = false;
                $response['message'] = 'Ya existe una prenda con el nombre "' . htmlspecialchars($nombre) . '". ¿Deseas guardarla de todas formas?';
                $response['code'] = 'DUPLICATE_PRENDA_NAME';
                echo json_encode($response);
                $pdo->rollBack(); // Revertir transacción si hay duplicado y no se fuerza
                exit;
            }
        }

        // --- Insertar la prenda (AJUSTADO PARA PDO Y TODAS LAS COLUMNAS) ---
        // Asegúrate de que las columnas aquí coincidan EXACTAMENTE con tu tabla `prendas`
        // y que `comentarios` es el nombre de la columna para comentarios.
        $sql_insert = "
            INSERT INTO prendas (
                nombre, tipo, color_principal, tela, textura, estampado,
                clima_apropiado, formalidad, estado, foto, fecha_agregado,
                detalles_adicionales, usos_esta_semana, fecha_ultimo_reset_semanal, uso_ilimitado
            ) VALUES (
                :nombre, :tipo, :color_principal, :tela, :textura, :estampado,
                :clima_apropiado, :formalidad, :estado, :foto, :fecha_agregado,
                :comentarios, :usos_esta_semana, :fecha_ultimo_reset_semanal, :uso_ilimitado
            )
        ";

        $stmt = $pdo->prepare($sql_insert);

        // Bindear los parámetros (nombre de placeholder => valor)
        $estado_default = 'disponible';
        $usos_esta_semana_default = 0;
        $fecha_ultimo_reset_semanal_default = null; // NULL al inicio, se actualiza al primer uso
        $uso_ilimitado_default = 0; // 0 para FALSE

        $stmt->execute([
            ':nombre' => $nombre,
            ':tipo' => $tipo,
            ':color_principal' => $color_principal,
            ':tela' => $tela,
            ':textura' => $textura,
            ':estampado' => $estampado,
            ':clima_apropiado' => $clima_apropiado,
            ':formalidad' => $formalidad,
            ':estado' => $estado_default,
            ':foto' => $foto_path,
            ':fecha_agregado' => $fecha_actual,
            ':comentarios' => $comentarios, // Usar :comentarios si la columna es 'comentarios'
            ':usos_esta_semana' => $usos_esta_semana_default,
            ':fecha_ultimo_reset_semanal' => $fecha_ultimo_reset_semanal_default,
            ':uso_ilimitado' => $uso_ilimitado_default,
        ]);

        $response['success'] = true;
        $response['message'] = 'Prenda agregada exitosamente.';
        $response['id'] = $pdo->lastInsertId(); // Obtener el último ID insertado con PDO

        $pdo->commit(); // Confirmar la transacción

    } catch (PDOException $e) { // Capturar excepciones específicas de PDO
        $pdo->rollBack();
        $response['message'] = 'Error de base de datos: ' . $e->getMessage();
        // Puedes loguear $e->getCode() para más detalle del error SQL
    } catch (Exception $e) { // Capturar otras excepciones
        $pdo->rollBack();
        $response['message'] = 'Error general: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

echo json_encode($response);
// No es necesario $pdo->close() ya que PDO se cierra automáticamente al finalizar el script
