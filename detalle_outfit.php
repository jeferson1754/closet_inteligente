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
                        <?php if (!empty($outfit_details['comentarios'])): ?>
                            <i class="fas fa-comment me-2"></i>"<?php echo nl2br(htmlspecialchars($outfit_details['comentarios'])); ?>"
                        <?php else: ?>
                            <span class="text-muted">Sin detalles adicionales para este outfit.</span>
                        <?php endif; ?>
                    </p>

                    <div class="text-center mt-4">
                        <button class="btn btn-primary me-2" id="usarOutfitBtn" data-id="<?php echo $outfit_id; ?>">
                            <i class="fas fa-calendar-check me-2"></i>Usar este Outfit Hoy
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
                            // Incluye el estado para la lógica de visualización si es necesario
                            $sql_all_prendas_edit = "SELECT id, nombre, tipo, color_principal, foto, estado, uso_ilimitado FROM prendas ORDER BY nombre ASC";
                            $result_all_prendas_edit = $mysqli_obj->query($sql_all_prendas_edit);

                            if ($result_all_prendas_edit && $result_all_prendas_edit->num_rows > 0) {
                                while ($prenda_edit = $result_all_prendas_edit->fetch_assoc()) {
                                    $imagen_src_edit = !empty($prenda_edit['foto']) ? htmlspecialchars($prenda_edit['foto']) : 'https://via.placeholder.com/50x50?text=Sin+Imagen';
                                    echo '
                                <div class="form-check mb-2 prenda-edit-item"
                                    data-prenda-id="' . $prenda_edit['id'] . '"
                                    data-prenda-nombre="' . htmlspecialchars(strtolower($prenda_edit['nombre'])) . '"
                                    data-prenda-tipo="' . htmlspecialchars(strtolower($prenda_edit['tipo'])) . '"
                                    data-prenda-color="' . htmlspecialchars(strtolower($prenda_edit['color_principal'])) . '"
                                    data-prenda-estado="' . htmlspecialchars($prenda_edit['estado']) . '">
                                    <input class="form-check-input prenda-checkbox" type="checkbox" name="prendas[]" value="' . $prenda_edit['id'] . '" id="editPrenda' . $prenda_edit['id'] . '">
                                    <label class="form-check-label d-flex align-items-center" for="editPrenda' . $prenda_edit['id'] . '">
                                        <img src="' . $imagen_src_edit . '" alt="' . htmlspecialchars($prenda_edit['nombre']) . '" class="me-2 rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                        <div>
                                            <strong>' . htmlspecialchars($prenda_edit['nombre']) . '</strong><br>
                                            <small class="text-muted">' . htmlspecialchars($prenda_edit['tipo']) . ' • ' . htmlspecialchars($prenda_edit['color_principal']) . '</small>
                                            ';
                                    // Añadir un badge de estado si la prenda no está disponible y no es de uso ilimitado
                                    $nonAvailableStates = ['sucio', 'en uso', 'prestado', 'Lavando'];
                                    if (in_array($prenda_edit['estado'], $nonAvailableStates) && !$prenda_edit['uso_ilimitado']) {
                                        echo '<span class="badge badge-sm rounded-pill bg-danger ms-2">No disp.</span>';
                                    }
                                    echo '
                                        </div>
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


            // Función para filtrar prendas en el modal de edición
            function filterEditOutfitPrendas() {
                const searchTerm = buscarPrendasEditOutfit.value.toLowerCase().trim();

                allPrendaEditItems.forEach(item => {
                    const prendaNombre = item.dataset.prendaNombre;
                    const prendaTipo = item.dataset.prendaTipo;
                    const prendaColor = item.dataset.prendaColor;
                    const prendaText = `${prendaNombre} ${prendaTipo} ${prendaColor}`;

                    if (searchTerm === '' || prendaText.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
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
        });
    </script>
</body>

</html>