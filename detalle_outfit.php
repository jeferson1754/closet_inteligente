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
$sql_outfit = "SELECT id, nombre, contexto, clima_base, comentarios FROM outfits WHERE id = ?";
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
    $sql_detalles_prendas = "SELECT id, nombre, tipo, color_principal, tela, foto, textura, detalles_adicionales FROM prendas WHERE id IN ($placeholders)";

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
                    <h2 class="card-title text-center mb-3"><?php echo htmlspecialchars($outfit_details['nombre']); ?></h2>
                    <div class="d-flex justify-content-center mb-3">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($outfit_details['contexto']); ?></span>
                        <span class="badge clima-<?php echo htmlspecialchars($outfit_details['clima_base']); ?>"><?php echo htmlspecialchars($outfit_details['clima_base']); ?></span>
                    </div>

                    <p class="text-center lead">
                        <?php if (!empty($outfit_details['detalles_adicionales'])): ?>
                            <i class="fas fa-comment me-2"></i>"<?php echo nl2br(htmlspecialchars($outfit_details['detalles_adicionales'])); ?>"
                        <?php else: ?>
                            <span class="text-muted">Sin detalles adicionales para este outfit.</span>
                        <?php endif; ?>
                    </p>

                    <div class="text-center mt-4">
                        <button class="btn btn-primary me-2" id="usarOutfitBtn" data-id="<?php echo $outfit_id; ?>">
                            <i class="fas fa-calendar-check me-2"></i>Usar este Outfit Hoy
                        </button>
                        <button class="btn btn-outline-secondary" id="editarOutfitBtn" data-id="<?php echo $outfit_id; ?>">
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
                            <div class="card prenda-card h-100">
                                <img src="<?php echo htmlspecialchars($prenda['foto']); ?>" class="card-img-top" alt="Imagen de <?php echo htmlspecialchars($prenda['nombre']); ?>" style="object-fit: cover; max-height: 180px;">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($prenda['nombre']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars($prenda['tipo']); ?> • <?php echo htmlspecialchars($prenda['color_principal']); ?></small><br>
                                        <small><?php echo htmlspecialchars($prenda['tela']); ?> • <?php echo htmlspecialchars($prenda['textura']); ?></small>
                                    </p>
                                    <?php if (!empty($prenda['detalles_adicionales'])): ?>
                                        <p class="card-text"><small class="text-muted"><em>"<?php echo nl2br(htmlspecialchars(substr($prenda['detalles_adicionales'], 0, 100))); ?><?php echo (strlen($prenda['detalles_adicionales']) > 100) ? '...' : ''; ?>"</em></small></p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const outfitId = document.getElementById('usarOutfitBtn').dataset.id;

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
                        fetch('registrar_uso_outfit.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `outfit_id=${outfitId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('¡Registrado!', data.message, 'success')
                                        .then(() => {
                                            // Redirige de nuevo a esta misma página pero con el indicador de "usado"
                                            window.location.href = `detalle_outfit.php?id=${outfitId}&usado=true`;
                                        });
                                } else {
                                    Swal.fire('Error', data.message || 'Error desconocido al registrar el uso del outfit', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error en la solicitud AJAX de uso de outfit:', error);
                                Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor para registrar el uso del outfit.', 'error');
                            });
                    }
                });
            });

            // Funcionalidad para "Editar Outfit" (Aquí se abriría un modal o se redirigiría a otra página)
            document.getElementById('editarOutfitBtn').addEventListener('click', function() {
                // Aquí podrías redirigir a una página de edición:
                // window.location.href = `editar_outfit.php?id=${outfitId}`;

                // O, si prefieres un modal de edición (que tendrías que definir en esta página o incluir):
                Swal.fire({
                    title: 'Editar Outfit',
                    html: `
                        <form id="formEditarOutfit" class="text-start">
                            <input type="hidden" name="id" value="${outfitId}">
                            <div class="mb-3">
                                <label for="editNombre" class="form-label">Nombre del Outfit</label>
                                <input type="text" class="form-control" id="editNombre" name="nombre" value="<?php echo htmlspecialchars($outfit_details['nombre']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="editContexto" class="form-label">Contexto</label>
                                <select class="form-select" id="editContexto" name="contexto" required>
                                    <option value="trabajo" <?php echo ($outfit_details['contexto'] == 'trabajo') ? 'selected' : ''; ?>>Trabajo</option>
                                    <option value="universidad" <?php echo ($outfit_details['contexto'] == 'universidad') ? 'selected' : ''; ?>>Universidad</option>
                                    <option value="evento" <?php echo ($outfit_details['contexto'] == 'evento') ? 'selected' : ''; ?>>Evento</option>
                                    <option value="casa" <?php echo ($outfit_details['contexto'] == 'casa') ? 'selected' : ''; ?>>Casa</option>
                                    <option value="deporte" <?php echo ($outfit_details['contexto'] == 'deporte') ? 'selected' : ''; ?>>Deporte</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editClimaBase" class="form-label">Clima Base</label>
                                <select class="form-select" id="editClimaBase" name="clima_base">
                                    <option value="todo" <?php echo ($outfit_details['clima_base'] == 'todo') ? 'selected' : ''; ?>>Todo clima</option>
                                    <option value="calor" <?php echo ($outfit_details['clima_base'] == 'calor') ? 'selected' : ''; ?>>Calor</option>
                                    <option value="frio" <?php echo ($outfit_details['clima_base'] == 'frio') ? 'selected' : ''; ?>>Frío</option>
                                    <option value="lluvia" <?php echo ($outfit_details['clima_base'] == 'lluvia') ? 'selected' : ''; ?>>Lluvia</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editComentarios" class="form-label">Comentarios</label>
                                <textarea class="form-control" id="editComentarios" name="comentarios" rows="3"><?php echo htmlspecialchars($outfit_details['comentarios'] ?? ''); ?></textarea>
                            </div>
                            </form>
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar Cambios',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const form = document.getElementById('formEditarOutfit');
                        const formData = new FormData(form);
                        // Convertir formData a objeto para enviar como JSON si tu API espera JSON,
                        // o dejarlo como FormData si tu API espera application/x-www-form-urlencoded
                        const data = {};
                        formData.forEach((value, key) => (data[key] = value));

                        return fetch('editar_outfit.php', { // Tendrás que crear este script 'editar_outfit.php'
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json', // o 'application/x-www-form-urlencoded'
                                },
                                body: JSON.stringify(data), // o new URLSearchParams(formData)
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(response.statusText)
                                }
                                return response.json()
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`Request failed: ${error}`);
                            });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value && result.value.success) {
                            Swal.fire('Guardado!', result.value.message, 'success')
                                .then(() => window.location.reload()); // Recargar la página para ver los cambios
                        } else {
                            Swal.fire('Error', result.value ? result.value.message : 'Error desconocido al guardar.', 'error');
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>