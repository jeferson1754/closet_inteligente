<?php
include 'bd.php'; // Tu conexión a la base de datos


// Consulta para obtener TODAS las prendas con sus datos relevantes para la gestión rápida
// Incluye usos_esta_semana, estado, uso_ilimitado, foto y fecha_ultimo_lavado
$estado = $_GET['estado'] ?? '';

if ($estado == 'todos') {
    $sql_prendas_gestion = "SELECT * FROM prendas ORDER BY FIELD(estado, 'en uso', 'sucio', 'lavando', 'disponible'), usos_esta_semana DESC;";
} else {
    $sql_prendas_gestion = "SELECT * FROM prendas WHERE estado = ? ORDER BY usos_esta_semana ASC";
}

$stmt = $mysqli_obj->prepare($sql_prendas_gestion);
if ($estado !== 'todos') {
    $stmt->bind_param("s", $estado);
}
$stmt->execute();
$result_prendas_gestion = $stmt->get_result();
if (!$result_prendas_gestion) {
    die("Error en la consulta: " . $mysqli_obj->error);
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Rápida de Prendas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin: 20px auto;
            max-width: 700px;
            /* Ancho optimizado para móvil/tableta */
            padding: 25px;
        }

        .prenda-item-gestion {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: relative;
            transition: all 0.2s ease-in-out;
            flex-direction: column;
            /* Las secciones internas de la prenda (info y acciones) se apilarán */
            align-items: flex-start;
            /* Alinear contenido al inicio si se apilan */
            gap: 15px;
            text-align: center;
        }

        .prenda-item-gestion:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .prenda-item-gestion img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            margin: 0 auto;
            border: 1px solid #ddd;
        }

        /* Ajuste para la sección de información superior, para que imagen y texto queden en fila */
        .prenda-info-top {
            /* Nueva clase para el contenedor de imagen y texto */
            display: flex;
            align-items: center;
            flex-grow: 1;
            /* Permitir que ocupe espacio */
            width: 100%;
            /* Ocupar todo el ancho disponible */
        }

        .prenda-info {
            /* Mantener estilos del texto de la prenda */
            flex-grow: 1;
            margin: 0 auto;
        }

        .prenda-actions {
            display: flex;
            /* Para que los botones estén en fila */
            flex-wrap: wrap;
            /* Permitir que los botones salten de línea si no hay espacio */
            gap: 5px;
            /* Espacio entre los botones */
            justify-content: center;
            /* Alinear los botones al inicio */
            width: 100%;
            /* Ocupar todo el ancho disponible */
            border-top: 1px dashed #e9ecef;
            /* Separador visual */
            padding-top: 10px;
            margin: 0 auto;
            /* Un poco de margen extra */
        }

        .prenda-actions .btn {
            flex-grow: 1;
            /* Los botones se estirarán para ocupar el espacio */
            max-width: 120px;
            /* Limitar el ancho máximo para que no sean demasiado grandes */
            font-size: 0.85em;
            padding: 8px 5px;
            /* Ajustar padding para más espacio vertical en botones */
            text-align: center;
            /* Centrar texto */
        }

        /* Ajuste para la sección de último lavado, para que también se alinee bien */
        .last-wash-section {
            /* Mantener display: flex, align-items: center, gap: 10px */
            /* Asegurar que ocupe el 100% para la línea divisoria */
            border-top: none;
            /* La línea divisoria la pondremos en prenda-actions ahora */
            padding-top: 0;
            justify-content: center;
            align-items: center;
            font-size: 0.85em;
            width: 100%;
            /* Un poco más pequeño */
        }

        .last-wash-section input[type="date"] {
            font-size: 0.85em;
            max-width: 140px;
            /* Limitar ancho del input de fecha en móvil */
        }

        /* Badge de usos */
        .uso-badge-gestion {
            position: absolute;
            top: 5px;
            left: 5px;
            background-color: #667eea;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75em;
            font-weight: bold;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            z-index: 2;
        }

        .uso-badge-gestion.high-usage {
            background-color: #dc3545;
        }

        /* Estilos para estado */
        .estado-disponible {
            background-color: #28a745;
            color: white;
        }

        /* Success */
        .estado-sucio {
            background-color: #dc3545;
            color: white;
        }

        /* Danger */
        .estado-en-uso {
            background-color: #ffc107;
            color: black;
        }

        /* Warning */
        .estado-prestado {
            background-color: #17a2b8;
            color: white;
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

        /* Campo de fecha */
        .last-wash-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e9ecef;
            font-size: 0.9em;
        }

        .last-wash-section input[type="date"] {
            flex-grow: 1;
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }

        .last-wash-section .btn {
            padding: 5px 10px;
            font-size: 0.8em;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="main-container">
            <h2 class="text-center mb-4"><i class="fas fa-tshirt me-2"></i>Gestión Rápida de Prendas</h2>
            <p class="text-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Volver al Clóset</a>
            </p>

            <div class="d-flex flex-column flex-md-row justify-content-center mb-4 gap-3">
                <div class="btn-group" role="group" aria-label="Filtro por estado de prenda">
                    <input type="radio" class="btn-check filter-radio" name="filterState" id="filterAll" value="todos" autocomplete="off" checked>
                    <a href="?estado=todos" class="btn btn-outline-primary">Todos</a>

                    <input type="radio" class="btn-check filter-radio" name="filterState" id="filterDisponible" value="disponible" autocomplete="off">
                    <a href="?estado=disponible" name="filterState" id="filterDisponible" class="btn btn-outline-success">Disponible</a>

                    <input type="radio" class="btn-check filter-radio" name="filterState" id="filterSucio" value="sucio" autocomplete="off">
                    <a href="?estado=sucio" class="btn btn-outline-danger">Sucio</a>

                    <input type="radio" class="btn-check filter-radio" name="filterState" id="filterLavando" value="lavando" autocomplete="off">
                    <a href="?estado=lavando" class="btn btn-outline-warning">Lavando</a>

                </div>
            </div>

            <input type="text" class="form-control" id="searchPrendaInput" placeholder="Buscar prenda por nombre..." style="width: 100%;margin-bottom: 10px;">

            <div id="prendasList">
                <?php if ($result_prendas_gestion && $result_prendas_gestion->num_rows > 0): ?>
                    <?php while ($prenda = $result_prendas_gestion->fetch_assoc()): ?>
                        <div class="prenda-item-gestion"
                            data-prenda-id="<?= $prenda['id'] ?>"
                            data-prenda-estado="<?= htmlspecialchars($prenda['estado']) ?>"
                            data-prenda-nombre="<?= htmlspecialchars(strtolower($prenda['nombre'])) ?>"
                            data-prenda-tipo="<?= htmlspecialchars(strtolower($prenda['tipo'])) ?>"
                            data-prenda-color="<?= htmlspecialchars(strtolower($prenda['color_principal'])) ?>">

                            <?php
                            // Lógica para el badge de usos
                            $uso_badge_class = '';
                            $current_garment_uses = (int)$prenda['usos_esta_semana'];
                            $garment_type_for_function = $prenda['tipo'];
                            $badgeUsageStatus = getUsageLimitStatus($garment_type_for_function, $current_garment_uses);

                            if (!$prenda['uso_ilimitado'] && $badgeUsageStatus['is_overused']) {
                                $uso_badge_class = ' high-usage';
                            }
                            if (!$prenda['uso_ilimitado']) {
                                echo '<span class="uso-badge-gestion' . $uso_badge_class . '">' . $current_garment_uses . '</span>';
                            }

                            $imagen_src = !empty($prenda['foto']) ? htmlspecialchars($prenda['foto']) : 'https://via.placeholder.com/70x70?text=Sin+Imagen';
                            ?>

                            <div class="prenda-info-top"> <img src="<?= $imagen_src ?>" alt="<?= htmlspecialchars($prenda['nombre']) ?>">

                                <div class="prenda-info">
                                    <strong><?= htmlspecialchars($prenda['nombre']) ?></strong>
                                    <span class="badge badge-estado estado-<?= str_replace(' ', '-', strtolower($prenda['estado'])) ?>">
                                        <?= htmlspecialchars(ucfirst($prenda['estado'])) ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars(ucfirst($prenda['tipo'])) ?> • <?= htmlspecialchars($prenda['color_principal']) ?></small>
                                    <br>
                                    <?php if ($prenda['uso_ilimitado']): ?>
                                        <small class="text-info"><i class="fas fa-infinity me-1"></i>Uso Ilimitado</small>
                                    <?php endif; ?>

                                </div>


                            </div>

                            <div class="last-wash-section">
                                <span>Últ. lavado:</span>
                                <input type="date" class="form-control form-control-sm wash-date-input"
                                    id="washDate_<?= $prenda['id'] ?>"
                                    value="<?= $prenda['fecha_ultimo_lavado'] ?>"
                                    data-original-date="<?= $prenda['fecha_ultimo_lavado'] ?>">
                                <button class="btn btn-sm btn-outline-primary mark-washed-btn" data-prenda-id="<?= $prenda['id'] ?>">
                                    <i class="fas fa-check-circle"></i> Recibida Hoy
                                </button>
                            </div>
                            <div class="prenda-actions">
                                <button class="btn btn-success btn-sm change-state-btn" data-prenda-id="<?= $prenda['id'] ?>" data-new-state="disponible">Disponible</button>
                                <button class="btn btn-danger btn-sm change-state-btn" data-prenda-id="<?= $prenda['id'] ?>" data-new-state="sucio">Sucio</button>
                                <button class="btn btn-warning btn-sm change-state-btn" data-prenda-id="<?= $prenda['id'] ?>" data-new-state="lavando">Lavar</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().split('T')[0];
            const prendasListContainer = document.getElementById('prendasList');
            const filterRadios = document.querySelectorAll('input[name="filterState"]');
            const searchInput = document.getElementById('searchPrendaInput'); // Tu buscador

            // Obtener TODOS los items de prenda una sola vez al cargar la página
            // Esto es crucial porque el filtro se hará en el cliente.
            const allPrendaItems = document.querySelectorAll('.prenda-item-gestion');

            let currentFilterState = getFilterFromUrl(); // Variable para mantener el filtro de estado actual
            let currentSearchTerm = ''; // Variable para mantener el término de búsqueda actual

            // --- Lógica para aplicar el filtro (AHORA COMBINA ESTADO Y BÚSQUEDA) ---
            function applyFilter() {
                const filterValue = currentFilterState;
                const searchTerm = currentSearchTerm.toLowerCase().trim();

                let visibleCount = 0;

                allPrendaItems.forEach(item => { // Iterar sobre TODAS las prendas cargadas
                    // --- NUEVO: Reinicializar la visibilidad en cada iteración ---
                    item.style.display = 'flex'; // Asumir que la prenda es visible por defecto al inicio del ciclo
                    // --- FIN NUEVO ---

                    const prendaEstado = item.dataset.prendaEstado;
                    const prendaNombre = item.dataset.prendaNombre;
                    const prendaTipo = item.dataset.prendaTipo;
                    const prendaColor = item.dataset.prendaColor;

                    const prendaText = `${prendaNombre} ${prendaTipo} ${prendaColor}`;

                    const matchesState = (filterValue === 'todos' || prendaEstado === filterValue);
                    const matchesSearch = (searchTerm === '' || prendaText.includes(searchTerm));

                    if (matchesState && matchesSearch) {
                        item.style.display = 'flex'; // Asegurar que se muestre
                        visibleCount++;
                    } else {
                        item.style.display = 'none'; // Ocultar si no coincide con ambos filtros
                    }
                });

                if (visibleCount === 0) {
                    // Eliminar el mensaje de "no resultados" existente si ya está en el DOM
                    const existingNoResults = document.getElementById('noResultsMessage');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                    // Añadir el mensaje de no resultados solo si no hay prendas visibles
                    const noResultsDiv = document.createElement('div');
                    noResultsDiv.id = 'noResultsMessage';
                    noResultsDiv.innerHTML = '<i class="fas fa-box-open fa-3x mt-4 mb-3 text-muted"></i><p class="text-muted">No se encontraron prendas que coincidan con los filtros.</p>';
                    noResultsDiv.classList.add('text-center'); // Centrar el mensaje
                    prendasListContainer.appendChild(noResultsDiv);
                } else {
                    // Si hay prendas visibles, asegurarse de que el mensaje de "no resultados" no esté presente
                    const existingNoResults = document.getElementById('noResultsMessage');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                }
            }

            // --- Persistencia del filtro de estado en la URL (sin cambios) ---
            function getFilterFromUrl() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('estado') || 'todos'; // Por defecto 'todos'
            }

            function setFilterInUrl(filterValue) {
                const url = new URL(window.location.href);
                url.searchParams.set('estado', filterValue);
                // No cambiamos el 'search' en la URL, ya que se filtra en cliente
                window.history.pushState({
                    path: url.href
                }, '', url.href);
            }

            // --- Inicializar y escuchar cambios en los filtros ---
            currentFilterState = getFilterFromUrl(); // Obtener el filtro de estado inicial

            // Marcar el radio button correcto al cargar la página
            const initialRadioId = `filter${currentFilterState.charAt(0).toUpperCase() + currentFilterState.slice(1)}`;
            const initialRadioElement = document.getElementById(initialRadioId);

            if (initialRadioElement) {
                initialRadioElement.checked = true;
            } else {
                document.getElementById('filterAll').checked = true;
                currentFilterState = 'todos';
                setFilterInUrl('todos'); // Actualizar la URL si el filtro inicial fue inválido
            }

            // Obtener el término de búsqueda inicial (si se usa persistencia por URL para el buscador, sino, se inicializa vacío)
            const urlSearchParam = new URLSearchParams(window.location.search).get('search');
            if (urlSearchParam) {
                currentSearchTerm = urlSearchParam;
                searchInput.value = currentSearchTerm;
            }

            // Aplicar los filtros iniciales (estado + búsqueda)
            applyFilter();

            // Escuchar cambios en los botones de radio (filtro de estado)
            filterRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    currentFilterState = this.value;
                    applyFilter(); // Disparar filtro de cliente
                    setFilterInUrl(currentFilterState); // Actualizar URL
                });
            });

            // Listener para el campo de búsqueda (con debounce)
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentSearchTerm = this.value; // Actualizar el término de búsqueda
                    applyFilter(); // Disparar filtro de cliente con el nuevo término
                }, 300); // Esperar 300ms después de escribir
            });

            // --- Listeners de acción de prendas (sin cambios importantes) ---
            // Usamos delegación de eventos para botones generados dinámicamente o persistentes
            prendasListContainer.addEventListener('click', async (event) => {
                const changeStateButton = event.target.closest('.change-state-btn');
                if (changeStateButton) {
                    const prendaId = changeStateButton.dataset.prendaId;
                    const newState = changeStateButton.dataset.newState;
                    await updatePrendaState(prendaId, newState);
                    return;
                }

                const markWashedButton = event.target.closest('.mark-washed-btn');
                if (markWashedButton) {
                    const prendaId = markWashedButton.dataset.prendaId;
                    const washDateInput = document.getElementById(`washDate_${prendaId}`);
                    if (washDateInput) washDateInput.value = today;
                    await updatePrendaState(prendaId, 'disponible', today, true);
                    return;
                }
            });

            prendasListContainer.addEventListener('change', async (event) => {
                const washDateInput = event.target.closest('.wash-date-input');
                if (washDateInput) {
                    const prendaId = washDateInput.id.split('_')[1];
                    const newWashDate = washDateInput.value;
                    const originalWashDate = washDateInput.dataset.originalDate;

                    if (newWashDate === originalWashDate) {
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Actualizar fecha de lavado',
                        html: `¿Quieres actualizar la fecha de lavado de esta prenda a <strong>${newWashDate}</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, solo fecha',
                        cancelButtonText: 'No, cancelar',
                        showDenyButton: true,
                        denyButtonText: 'Sí y marcar disponible',
                        confirmButtonColor: '#0d6efd',
                        denyButtonColor: '#28a745'
                    });

                    if (result.isConfirmed) {
                        updatePrendaState(prendaId, null, newWashDate);
                    } else if (result.isDenied) {
                        updatePrendaState(prendaId, 'disponible', newWashDate);
                    } else {
                        washDateInput.value = originalWashDate;
                    }
                }
            });


            // --- Función principal para actualizar estado y fecha de lavado ---
            async function updatePrendaState(prendaId, newState = null, newWashDate = null, resetUses = false) {
                const formData = new FormData();
                formData.append('id', prendaId);
                if (newState) {
                    formData.append('estado', newState);
                }
                if (newWashDate) {
                    formData.append('fecha_ultimo_lavado', newWashDate);
                }
                if (resetUses) {
                    formData.append('reset_uses', 'true');
                }

                try {
                    const response = await fetch('update_prenda_quick.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    Swal.close();

                    if (data.success) {
                        Swal.fire('¡Actualizado!', data.message, 'success')
                            .then(() => {
                                // Después de actualizar, RE-APLICAR LOS FILTROS
                                // Esto es clave para que la prenda se oculte/muestre según su nuevo estado
                                applyFilter();
                            });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                } catch (error) {
                    Swal.close();
                    console.error('Error al actualizar prenda:', error);
                    Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor.', 'error');
                }
            }
        });
    </script>
</body>

</html>