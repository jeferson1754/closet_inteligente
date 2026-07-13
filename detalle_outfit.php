<?php
include 'bd.php'; // Asegúrate de que tu conexión a la base de datos esté aquí.

// Asegúrate de que el ID del outfit sea pasado por GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirigir o mostrar un error si no se proporciona un ID válido
    header('Location: index.php'); // Redirige a la página principal
    exit;
}

$outfit_id = intval($_GET['id']);
$outfit_details = null;
$prendas_del_outfit = [];
$mensaje_uso = ''; // Para mostrar un mensaje si se acaba de usar el outfit

// Verificar si se acaba de usar el outfit (para mostrar el mensaje)
if (isset($_GET['usado']) && $_GET['usado'] == 'true') {
    $mensaje_uso = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        ¡Outfit registrado como usado para hoy!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
}

// Obtener detalles del outfit principal
$sql_outfit = "SELECT * FROM outfits WHERE id = ?";
if ($stmt_outfit = $mysqli_obj->prepare($sql_outfit)) {
    $stmt_outfit->bind_param("i", $outfit_id);
    $stmt_outfit->execute();
    $result_outfit = $stmt_outfit->get_result();
    $outfit_details = $result_outfit->fetch_assoc();
    $stmt_outfit->close();
}

if (!$outfit_details) {
    echo "Outfit no encontrado.";
    exit;
}

// Obtener los IDs de las prendas asociadas al outfit
$prendas_ids = [];
$sql_prendas_ids = "SELECT prenda_id FROM outfit_prendas WHERE outfit_id = ?";
if ($stmt_prendas_ids = $mysqli_obj->prepare($sql_prendas_ids)) {
    $stmt_prendas_ids->bind_param("i", $outfit_id);
    $stmt_prendas_ids->execute();
    $result_prendas_ids = $stmt_prendas_ids->get_result();
    while ($row = $result_prendas_ids->fetch_assoc()) {
        $prendas_ids[] = $row['prenda_id'];
    }
    $stmt_prendas_ids->close();
}

// Obtener los detalles de las prendas si hay IDs
if (!empty($prendas_ids)) {
    $placeholders = implode(',', array_fill(0, count($prendas_ids), '?'));
    // Asegúrate de incluir 'detalles_adicionales' en la selección de prendas
    $sql_detalles_prendas = "SELECT * FROM prendas WHERE id IN ($placeholders)
    ORDER BY FIELD(LOWER(tipo), $orden_prendas) ASC";

    if ($stmt_detalles = $mysqli_obj->prepare($sql_detalles_prendas)) {
        // --- INICIO DE LA SOLUCIÓN ---
        $types = str_repeat('i', count($prendas_ids)); // Genera el string de tipos (ej. 'iiii')

        // Prepara el array de argumentos para bind_param, donde cada elemento del array $prendas_ids es una REFERENCIA
        $bind_params_array = [$types]; // El primer elemento es el string de tipos
        foreach ($prendas_ids as $key => $value) {
            $bind_params_array[] = &$prendas_ids[$key]; // ¡Pasa cada ID de prenda por referencia!
        }

        // Llama a bind_param usando call_user_func_array con el array de referencias
        call_user_func_array([$stmt_detalles, 'bind_param'], $bind_params_array);
        // --- FIN DE LA SOLUCIÓN ---
        $stmt_detalles->execute();
        $result_detalles_prendas = $stmt_detalles->get_result();
        while ($prenda = $result_detalles_prendas->fetch_assoc()) {
            $prenda['foto'] = !empty($prenda['foto']) ? $prenda['foto'] : 'https://via.placeholder.com/100x100?text=Sin+Imagen';
            $prendas_del_outfit[] = $prenda;
        }
        $stmt_detalles->close();
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outfit: <?php echo htmlspecialchars($outfit_details['nombre']); ?> - Clóset Inteligente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1000px;
            /* Ajustado para la página de detalle */
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
        }

        .btn-success-gradient {
            background: linear-gradient(45deg, #11998e, #38ef7d);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
            color: white;
        }

        .btn-success-gradient:hover {
            color: white;
            filter: brightness(1.05);
        }

        .badge {
            border-radius: 15px;
            padding: 5px 12px;
            font-size: 0.8em;
            margin-right: 5px;
        }

        .prenda-card img {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .clima-calor {
            background-color: #ff6b35;
            color: white;
        }

        .clima-frio {
            background-color: #4a90e2;
            color: white;
        }

        .clima-lluvia {
            background-color: #6c757d;
            color: white;
        }

        .clima-todo {
            background-color: #28a745;
            color: white;
        }

        .outfit-info-container {
            background: #f8f9fa;
            border-radius: 12px;
            margin: 1rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .info-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: inline-block;
            min-width: 200px;
        }

        .info-label {
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
        }

        .comment-card {
            background: white;
            border-left: 4px solid #007bff;
            border-radius: 0 8px 8px 0;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            margin-bottom: -1rem;
            display: flex;
            align-items: center;
        }

        .comment-content {
            font-size: 1rem;
            line-height: 1.6;
            color: #555;
            font-style: italic;
        }

        .no-comments {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        /* Mejoras para los badges */
        .badge {
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: transform 0.2s ease;
        }

        .badge:hover {
            transform: translateY(-1px);
        }

        /* Estilos para diferentes climas (ejemplos) */
        .clima-soleado {
            background: linear-gradient(45deg, #ffc107, #fd7e14) !important;
        }

        .clima-lluvioso {
            background: linear-gradient(45deg, #6c757d, #495057) !important;
        }

        .clima-nublado {
            background: linear-gradient(45deg, #adb5bd, #6c757d) !important;
        }

        .clima-frio {
            background: linear-gradient(45deg, #007bff, #0056b3) !important;
        }

        /* Info */
        .estado-lavando {
            background-color: #6f42c1;
            color: white;
        }

        /* Purple */

        .badge-estado {
            font-size: 0.7em;
            padding: 4px 8px;
            border-radius: 5px;
            margin-left: 5px;
        }

        .uso-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #667eea;
            /* Color principal */
            color: white;
            border-radius: 50%;
            /* Para que sea un círculo */
            width: 30px;
            /* Tamaño del círculo */
            height: 30px;
            /* Tamaño del círculo */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 10;
            /* Asegura que esté por encima de la imagen */
        }

        /* Color si los usos son altos (opcional, para avisar que está cerca del límite) */
        .uso-badge.high-usage {
            background-color: #dc3545;
            /* Rojo */
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .outfit-info-container {
                padding: 1rem;
                margin: 0.5rem 0;
            }

            .info-card {
                min-width: auto;
                width: 100%;
            }

            .comment-card {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="main-container">
            <h1 class="mb-4 text-center"><i class="fas fa-tshirt me-2"></i>Detalles del Outfit</h1>
            <p class="text-center"><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Volver al Clóset</a></p>

            <?php echo $mensaje_uso; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title text-center mb-1"><?php echo htmlspecialchars($outfit_details['nombre']); ?></h2>
                    <!-- Contenedor principal con mejor estructura -->
                    <div class="outfit-info-container">
                        <!-- Badges con mejor espaciado y diseño -->

                        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4 mt-2">
                            <?php if (!empty($outfit_details['contexto'])): ?>
                                <span class="badge bg-primary px-3 py-2 rounded-pill fs-6">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($outfit_details['contexto']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge clima-<?php echo htmlspecialchars($outfit_details['clima_base']); ?> px-3 py-2 rounded-pill fs-6">
                                <i class="fas fa-cloud me-1"></i>
                                <?php echo htmlspecialchars($outfit_details['clima_base']); ?>
                            </span>
                        </div>

                        <!-- Información de fecha con mejor presentación -->
                        <div class="text-center mb-1">
                            <div class="info-card">
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                    <small class="text-muted text-uppercase fw-bold">Último uso</small>
                                </div>
                                <div class="info-value">
                                    <?php
                                    $timestamp = strtotime($outfit_details['fecha_ultimo_uso_outfit']);
                                    $diaEn = date("l", $timestamp); // Día en inglés
                                    $diaEs = $dias[$diaEn]; // Traducción al español

                                    echo $diaEs . ", " . date("d-m-y", $timestamp);
                                    ?>

                                </div>
                            </div>
                        </div>

                        <!-- Comentarios con mejor diseño -->
                        <div class="comments-section">
                            <?php if (!empty($outfit_details['comentarios'])): ?>
                                <div class="comment-card">
                                    <div class="comment-header">
                                        <i class="fas fa-quote-left text-primary me-2"></i>
                                        <span class="comment-label text-muted text-uppercase fw-bold">Comentarios</span>
                                    </div>
                                    <div class="comment-content" style="white-space: pre-line;">
                                        <?php echo htmlspecialchars($outfit_details['comentarios']); ?>
                                    </div>


                                </div>
                            <?php else: ?>
                                <div class="no-comments">
                                    <i class="fas fa-comment-slash text-muted me-2"></i>
                                    <span class="text-muted fst-italic">Sin detalles adicionales para este outfit</span>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>


                    <div class="text-center mt-1 d-flex flex-wrap justify-content-center gap-2">
                        <button class="btn btn-primary" id="usarOutfitBtn" data-id="<?php echo $outfit_id; ?>">
                            <i class="fas fa-calendar-check me-2"></i>Usar este Outfit Hoy
                        </button>
                        <button class="btn btn-success-gradient" id="usarOutfitMananaBtn" data-id="<?php echo $outfit_id; ?>">
                            <i class="fas fa-calendar-plus me-2"></i>Verificar Uso Mañana
                        </button>
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditarOutfit" id="editarOutfitBtn" data-id="<?php echo $outfit_id; ?>">
                            <i class="fas fa-edit me-2"></i>Editar Outfit
                        </button>
                    </div>
                </div>
            </div>

            <h3 class="mb-3 text-center">Prendas de este Outfit</h3>
            <?php if (!empty($prendas_del_outfit)): ?>
                <div class="row">
                    <?php foreach ($prendas_del_outfit as $prenda): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card prenda-card h-100 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 16px;">

                                <?php
                                $uso_badge_class = 'bg-primary';
                                $current_garment_uses = (int)$prenda['usos_esta_semana'];
                                $garment_type_for_function = $prenda['tipo'];

                                // Utiliza la función para obtener el estado de uso
                                $badgeUsageStatus = getUsageLimitStatus($garment_type_for_function, $current_garment_uses);

                                // Aplica un color rojo/alerta si está sobreusada
                                if (!$prenda['uso_ilimitado'] && $badgeUsageStatus['is_overused']) {
                                    $uso_badge_class = 'bg-danger';
                                }

                                // Muestra el contador circular minimalista en la esquina superior derecha
                                if (!$prenda['uso_ilimitado']) {
                                    echo '<span class="badge position-absolute top-0 end-0 m-3 d-flex align-items-center justify-content-center ' . $uso_badge_class . '" 
                        style="width: 24px; height: 24px; border-radius: 50%; font-size: 0.75rem; z-index: 2; box-shadow: 0 2px 6px rgba(0,0,0,0.15); font-weight: 600;">'
                                        . $current_garment_uses .
                                        '</span>';
                                }
                                ?>

                                <div style="height: 180px; overflow: hidden; background-color: #f8f9fa;">
                                    <img src="<?php echo htmlspecialchars($prenda['foto']); ?>" class="w-100 h-100" alt="Imagen de <?php echo htmlspecialchars($prenda['nombre']); ?>" style="object-fit: cover;">
                                </div>

                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="card-title fw-bold text-dark mb-1" style="font-size: 0.95rem; line-height: 1.3; min-height: 2.6em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($prenda['nombre']); ?>
                                    </h6>

                                    <div class="mb-3">
                                        <span class="text-muted d-block" style="font-size: 0.75rem; text-transform: lowercase;">
                                            <?php echo htmlspecialchars($prenda['tipo']); ?> • <?php echo htmlspecialchars($prenda['color_principal']); ?>
                                        </span>
                                        <span class="text-secondary d-block mt-0.5" style="font-size: 0.72rem; letter-spacing: 0.2px;">
                                            <?php echo htmlspecialchars($prenda['tela']); ?><?php echo !empty($prenda['textura']) ? ' • ' . htmlspecialchars($prenda['textura']) : ''; ?>
                                        </span>
                                    </div>

                                    <div class="mb-2">
                                        <?php
                                        $estado = strtolower($prenda['estado']);
                                        $badge_bg = 'bg-secondary';
                                        if ($estado === 'disponible') $badge_bg = 'bg-success-subtle text-success border border-success-subtle';
                                        elseif ($estado === 'sucio') $badge_bg = 'bg-danger-subtle text-danger border border-danger-subtle';
                                        elseif (in_array($estado, ['en uso', 'lavando', 'prestado'])) $badge_bg = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                        ?>
                                        <span class="badge rounded-pill <?php echo $badge_bg; ?> px-2.5 py-1 fw-semibold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                            <?php echo htmlspecialchars($prenda['estado']); ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($prenda['detalles_adicionales'])): ?>
                                        <p class="card-text mt-2 mb-0 pt-2 border-top border-light text-muted fst-italic" style="font-size: 0.75rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars(substr($prenda['detalles_adicionales'], 0, 85))); ?><?php echo (strlen($prenda['detalles_adicionales']) > 85) ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">Este outfit no tiene prendas asociadas.</p>
            <?php endif; ?>

        </div>
    </div>

    <div class="modal fade" id="modalEditarOutfit" tabindex="-1" aria-labelledby="modalEditarOutfitLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow">
                <form action="editar_outfit.php" method="post" id="formEditarOutfit" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalEditarOutfitLabel">Editar Outfit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editOutfitId">
                        <div class="mb-3">
                            <label class="form-label">Nombre del Outfit</label>
                            <input type="text" class="form-control" name="nombre" id="editOutfitNombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contexto</label>
                            <select class="form-select" name="contexto" id="editOutfitContexto" required>
                                <option value="">Seleccionar...</option>
                                <option value="trabajo">Trabajo</option>
                                <option value="universidad">Universidad</option>
                                <option value="evento">Evento</option>
                                <option value="casa">Casa</option>
                                <option value="deporte">Deporte</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clima Base</label>
                            <select class="form-select" name="clima_base" id="editOutfitClimaBase">
                                <?php
                                // Asegúrate de que $climas_outfit esté definida o usa una lista estática
                                $climas_outfit_modal = ['todo' => 'Todo clima', 'calor' => 'Calor', 'frio' => 'Frío', 'lluvia' => 'Lluvia'];
                                foreach ($climas_outfit_modal as $valor => $texto) {
                                    echo "<option value=\"$valor\">" . ucfirst($texto) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comentarios</label>
                            <textarea class="form-control" name="comentarios" id="editOutfitComentarios" rows="3"></textarea>
                        </div>

                        <h6 class="mt-4 mb-3">Prendas del Outfit:</h6>
                        <input type="text" class="form-control mb-2" id="buscarPrendasEditOutfit" placeholder="Buscar prenda por nombre..." onkeyup="filterEditOutfitPrendas()">
                        <div id="listaPrendasEditOutfit" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                            <?php
                            // Consulta para obtener TODAS las prendas disponibles para la selección
                            $sql_all_prendas_edit = "SELECT id, nombre, tipo, color_principal, foto, estado, uso_ilimitado FROM prendas ORDER BY nombre ASC";
                            $result_all_prendas_edit = $mysqli_obj->query($sql_all_prendas_edit);

                            if ($result_all_prendas_edit && $result_all_prendas_edit->num_rows > 0) {
                                while ($prenda_edit = $result_all_prendas_edit->fetch_assoc()) {
                                    $imagen_src_edit = !empty($prenda_edit['foto']) ? htmlspecialchars($prenda_edit['foto']) : 'https://via.placeholder.com/50x50?text=Sin+Imagen';

                                    // Asignar el color y texto del badge de forma dinámica según el estado actual de la prenda
                                    $estado_actual = strtolower($prenda_edit['estado']);
                                    $badge_clase = 'bg-secondary';
                                    $badge_texto = ucfirst($prenda_edit['estado']);

                                    switch ($estado_actual) {
                                        case 'disponible':
                                            $badge_clase = 'bg-success';
                                            $badge_texto = 'Disponible';
                                            break;
                                        case 'sucio':
                                            $badge_clase = 'bg-danger';
                                            $badge_texto = 'Sucio';
                                            break;
                                        case 'en uso':
                                            $badge_clase = 'bg-warning text-dark';
                                            $badge_texto = 'En Uso';
                                            break;
                                        case 'lavando':
                                            $badge_clase = 'badge-estado estado-lavando';
                                            $badge_texto = 'Lavando';
                                            break;
                                        case 'prestado':
                                            $badge_clase = 'bg-dark';
                                            $badge_texto = 'Prestado';
                                            break;
                                    }

                                    // Si es de uso ilimitado, podemos añadirle una etiqueta extra aclaratoria
                                    if ($prenda_edit['uso_ilimitado']) {
                                        $badge_texto .= ' (Ilimitado)';
                                    }

                                    echo '
        <div class="form-check mb-2 prenda-edit-item"
            data-prenda-id="' . $prenda_edit['id'] . '"
            data-prenda-nombre="' . htmlspecialchars(strtolower($prenda_edit['nombre'])) . '"
            data-prenda-tipo="' . htmlspecialchars(strtolower($prenda_edit['tipo'])) . '"
            data-prenda-color="' . htmlspecialchars(strtolower($prenda_edit['color_principal'])) . '"
            data-prenda-estado="' . htmlspecialchars($prenda_edit['estado']) . '">
            <input class="form-check-input prenda-checkbox" type="checkbox" name="prendas[]" value="' . $prenda_edit['id'] . '" id="editPrenda' . $prenda_edit['id'] . '">
            <label class="form-check-label d-flex align-items-center w-100 justify-content-between" for="editPrenda' . $prenda_edit['id'] . '">
                <div class="d-flex align-items-center">
                    <img src="' . $imagen_src_edit . '" alt="' . htmlspecialchars($prenda_edit['nombre']) . '" class="me-2 rounded" style="width: 40px; height: 40px; object-fit: cover;">
                    <div>
                        <strong>' . htmlspecialchars($prenda_edit['nombre']) . '</strong><br>
                        <small class="text-muted">' . htmlspecialchars($prenda_edit['tipo']) . ' • ' . htmlspecialchars($prenda_edit['color_principal']) . '</small>
                    </div>
                </div>
                <span class="badge ' . $badge_clase . ' ms-2 rounded-pill px-2 py-1" style="font-size: 0.75rem;">' . $badge_texto . '</span>
            </label>
        </div>
        ';
                                }
                            } else {
                                echo '<p class="text-muted">No hay prendas en tu clóset.</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const outfitId = document.getElementById('usarOutfitBtn').dataset.id;
            // --- Lógica del Modal de Edición de Outfit ---
            const modalEditarOutfit = document.getElementById('modalEditarOutfit');
            const formEditarOutfit = document.getElementById('formEditarOutfit');
            const editOutfitId = document.getElementById('editOutfitId');
            const editOutfitNombre = document.getElementById('editOutfitNombre');
            const editOutfitContexto = document.getElementById('editOutfitContexto');
            const editOutfitClimaBase = document.getElementById('editOutfitClimaBase');
            const editOutfitComentarios = document.getElementById('editOutfitComentarios');
            const buscarPrendasEditOutfit = document.getElementById('buscarPrendasEditOutfit');
            const listaPrendasEditOutfit = document.getElementById('listaPrendasEditOutfit');
            const allPrendaEditItems = listaPrendasEditOutfit.querySelectorAll('.prenda-edit-item'); // Obtener todas las prendas del modal

            let outfitPrendasActuales = []; // Para guardar los IDs de las prendas que ya están en el outfit


            // Funcionalidad para "Usar este Outfit Hoy"
            document.getElementById('usarOutfitBtn').addEventListener('click', function() {
                Swal.fire({
                    title: '¿Usar Outfit Hoy?',
                    text: `¿Quieres registrar el uso del outfit "${document.querySelector('.card-title').textContent}" y marcarlo como tu outfit del día?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#667eea',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, usar hoy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        function ejecutarRegistroOutfit(outfitId, forceRepetition = false) {
                            // Preparamos el cuerpo de la solicitud
                            let bodyData = `outfit_id=${outfitId}`;
                            if (forceRepetition) {
                                bodyData += `&force_repetition=true`;
                            }

                            fetch('registrar_uso_outfit.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: bodyData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire('¡Registrado!', data.message, 'success')
                                            .then(() => {
                                                // Redirige de nuevo a esta misma página pero con el indicador de "usado"
                                                window.location.href = `detalle_outfit.php?id=${outfitId}&usado=true`;
                                            });
                                    }
                                    // Lógica de validación de repetición (Hoy, Ayer, Semana Pasada)
                                    else if (data.is_repetition) {
                                        Swal.fire({
                                            title: '¿Repetir estilo?',
                                            text: data.message, // El mensaje dirá si fue hoy, ayer o la semana pasada
                                            icon: 'warning',
                                            showCancelButton: true,
                                            html: data.message.replace(/\n/g, '<br>'),
                                            confirmButtonColor: '#667eea',
                                            cancelButtonColor: '#d33',
                                            confirmButtonText: 'Sí, usar de todos modos',
                                            cancelButtonText: 'No, elegir otro'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                // Si confirma, volvemos a llamar a la función pasando forceRepetition como true
                                                ejecutarRegistroOutfit(outfitId, true);
                                            }
                                        });
                                    } else {
                                        Swal.fire('Error', data.message || 'Error desconocido al registrar el uso del outfit', 'error');
                                        if (typeof modal !== 'undefined') modal.hide();
                                    }
                                })
                                .catch(error => {
                                    console.error('Error en la solicitud AJAX:', error);
                                    Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor.', 'error');
                                    if (typeof modal !== 'undefined') modal.hide();
                                });
                        }

                        // Llamada inicial (reemplaza tu bloque fetch anterior con esto)
                        ejecutarRegistroOutfit(outfitId);
                    }
                });
            });


            // Función para filtrar prendas en el modal de edición
            function filterEditOutfitPrendas() {
                document.getElementById("buscarPrendasEditOutfit").addEventListener("input", function() {
                    let filtro = this.value.toLowerCase().trim();
                    let prendas = document.querySelectorAll("#listaPrendasEditOutfit .prenda-edit-item");

                    prendas.forEach(function(prenda) {
                        let nombre = prenda.getAttribute("data-prenda-nombre");
                        let tipo = prenda.getAttribute("data-prenda-tipo");
                        let color = prenda.getAttribute("data-prenda-color");

                        // Mostrar si coincide en nombre, tipo o color
                        if (nombre.includes(filtro) || tipo.includes(filtro) || color.includes(filtro)) {
                            prenda.style.display = "";
                        } else {
                            prenda.style.display = "none";
                        }
                    });
                });
            }



            // Evento que se dispara cuando el modal de edición de outfit se abre
            modalEditarOutfit.addEventListener('show.bs.modal', async function(event) {
                const button = event.relatedTarget; // Botón que disparó el modal
                const outfitId = button.dataset.id; // Obtener el ID del outfit desde el botón

                // Cargar datos del outfit actual para rellenar el formulario
                // Necesitamos un script para obtener los detalles COMPLETOS del outfit, incluyendo sus prendas asociadas
                try {
                    const response = await fetch(`obtener_outfit_completo.php?outfit_id=${outfitId}`); // Nuevo script PHP
                    const outfitData = await response.json();

                    if (outfitData.success) {
                        const outfit = outfitData.outfit;
                        outfitPrendasActuales = outfitData.prendas_asociadas.map(p => p.id); // Guardar IDs de prendas asociadas

                        editOutfitId.value = outfit.id;
                        editOutfitNombre.value = outfit.nombre;
                        editOutfitContexto.value = outfit.contexto;
                        editOutfitClimaBase.value = outfit.clima_base;
                        editOutfitComentarios.value = outfit.comentarios || '';

                        // Marcar los checkboxes de las prendas que ya están en el outfit
                        allPrendaEditItems.forEach(item => {
                            const checkbox = item.querySelector('.prenda-checkbox');
                            if (checkbox) {
                                checkbox.checked = outfitPrendasActuales.includes(parseInt(checkbox.value));
                            }
                        });

                        // Limpiar el buscador del modal al abrirlo
                        buscarPrendasEditOutfit.value = '';
                        filterEditOutfitPrendas(); // Aplicar filtro inicial (mostrar todo)

                    } else {
                        Swal.fire('Error', outfitData.message || 'No se pudieron cargar los datos del outfit.', 'error');
                        modalEditarOutfit.hide(); // Cerrar modal si falla la carga
                    }
                } catch (error) {
                    console.error('Error cargando outfit para edición:', error);
                    Swal.fire('Error de Conexión', 'No se pudieron cargar los datos del outfit para edición.', 'error');
                    modalEditarOutfit.hide();
                }
            });

            // Listener para el formulario de edición (submit)
            formEditarOutfit.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this); // Captura todos los campos del formulario

                // Nuevo: Leer si ya se ha forzado la edición duplicada
                const forceDuplicateEdit = this.dataset.forceDuplicateEdit === 'true';
                if (forceDuplicateEdit) {
                    formData.append('force_duplicate_edit', 'true');
                    this.dataset.forceDuplicateEdit = 'false'; // Resetear el flag después de enviarlo
                }

                Swal.fire({
                    title: 'Guardando cambios...',
                    text: 'Por favor espera.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                try {
                    const response = await fetch('editar_outfit.php', { // Este script guardará los cambios
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    Swal.close();

                    if (data.success) {
                        Swal.fire('¡Actualizado!', data.message, 'success')
                            .then(() => {
                                window.location.reload(); // Recargar la página para ver los cambios
                            });
                    } else if (data.code === 'DUPLICATE_OUTFIT_ON_EDIT') { // NUEVO: Interceptar duplicado al editar
                        Swal.fire({
                            title: 'Combinación de prendas duplicada',
                            html: data.message + '<br><br>¿Deseas guardar los cambios de todas formas? Esto creará un outfit con la misma combinación de prendas que otro.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, guardar cambios',
                            cancelButtonText: 'No, cancelar edición'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Si el usuario confirma, establecer un flag y re-enviar el formulario
                                formEditarOutfit.dataset.forceDuplicateEdit = 'true';
                                formEditarOutfit.requestSubmit(); // Re-envía el formulario
                            } else {
                                Swal.fire('Edición Cancelada', 'Los cambios no han sido guardados.', 'info');
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Error al guardar los cambios.', 'error');
                    }
                } catch (error) {
                    Swal.close();
                    console.error('Error al enviar la solicitud de edición:', error);
                    Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor para guardar los cambios.', 'error');
                }
            });
            // --- FIN Lógica del Modal de Edición de Outfit ---

            // Funcionalidad para "Verificar Uso Mañana" (SOLO VALIDACIÓN, NO REGISTRA)
            // NUEVO: Funcionalidad para "Verificar Uso Mañana" (SOLO VALIDACIÓN)
            document.getElementById('usarOutfitMananaBtn').addEventListener('click', function() {
                const nombreOutfit = document.querySelector('.card-title').textContent;

                Swal.fire({
                    title: 'Verificando prendas...',
                    text: `Comprobando la disponibilidad de "${nombreOutfit}" para mañana sin registrar cambios.`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Enviamos el ID del outfit, fecha_destino como mañana y el flag solo_validar en true
                fetch('registrar_uso_outfit.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `outfit_id=${outfitId}&fecha_destino=manana&solo_validar=true`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close(); // Cerramos el loader

                        if (data.success) {
                            // Si pasó todas las reglas y no hubo conflictos
                            Swal.fire({
                                title: '¡Outfit Disponible!',
                                text: 'Ninguna de las prendas se repite de forma consecutiva para mañana. ¡Puedes usarlo con confianza!',
                                icon: 'success',
                                confirmButtonColor: '#11998e'
                            });
                        } else if (data.is_repetition) {
                            // Si hay un conflicto de repetición, mostramos cuáles son las prendas repetidas
                            Swal.fire({
                                title: 'Conflicto de prendas para mañana',
                                html: data.message.replace(/\n/g, '<br>'), // Hacemos los saltos de línea legibles en HTML
                                icon: 'warning',
                                confirmButtonColor: '#667eea',
                                confirmButtonText: 'Entendido'
                            });
                        } else {
                            // Errores de prendas sucias u otros bloqueos detectados por el backend
                            Swal.fire('Atención', data.message || 'El outfit no está disponible.', 'warning');
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Error en la solicitud de validación:', error);
                        Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor.', 'error');
                    });
            });
        });
    </script>
</body>

</html>