<!-- Modal Búsqueda Avanzada de Outfits por Prendas -->
<div class="modal fade" id="searchOutfitModal" tabindex="-1" aria-labelledby="searchOutfitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="searchOutfitModalLabel">
                    <i class="fas fa-filter me-2"></i>Filtrar Outfits por Prendas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- Barra superior con contador y modo de búsqueda -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <span class="badge bg-primary rounded-pill fs-6" id="contadorPrendasBusqueda">0 prendas seleccionadas</span>

                    <div class="btn-group btn-group-sm" role="group" aria-label="Modo de coincidencia">
                        <input type="radio" class="btn-check" name="modoFiltroOutfits" id="modoTodasPrendas" value="todas" checked>
                        <label class="btn btn-outline-primary" for="modoTodasPrendas" title="El outfit debe incluir todas las prendas marcadas">Contiene TODAS</label>

                        <input type="radio" class="btn-check" name="modoFiltroOutfits" id="modoAlgunaPrenda" value="alguna">
                        <label class="btn btn-outline-primary" for="modoAlgunaPrenda" title="El outfit debe incluir al menos una de las prendas marcadas">Contiene ALGUNA</label>
                    </div>
                </div>

                <!-- Controles de filtrado de la lista de prendas -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control" id="searchPrendasFilter" placeholder="Buscar por nombre, color o tela...">
                            <button class="btn btn-outline-secondary" type="button" id="limpiarFiltroBusquedaModal" title="Limpiar búsqueda">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtroTipoBusquedaModal">
                            <option value="todos">Todos los tipos</option>
                            <?php
                            $res_tipos_busq = $mysqli_obj->query("SELECT DISTINCT(tipo) FROM prendas ORDER BY tipo ASC");
                            if ($res_tipos_busq) {
                                while ($tb_row = $res_tipos_busq->fetch_assoc()) {
                                    $t_val = htmlspecialchars(strtolower($tb_row['tipo']));
                                    $t_lbl = htmlspecialchars(ucfirst($tb_row['tipo']));
                                    echo "<option value=\"{$t_val}\">{$t_lbl}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <select class="form-select form-select-sm" id="filtroVistaBusquedaModal">
                            <option value="todas" selected>Ver todas</option>
                            <option value="marcadas">Solo marcadas ✓</option>
                            <option value="disponibles">Solo disponibles</option>
                        </select>
                    </div>
                </div>

                <!-- Lista de prendas seleccionables -->
                <div id="listaPrendasBusqueda" style="max-height: 360px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 10px; padding: 10px; background: #fafafa;">
                    <div class="row g-2">
                        <?php
                        $sql_busqueda_prendas = "SELECT id, nombre, tipo, color_principal, tela, estado, foto 
                                                 FROM prendas 
                                                 ORDER BY FIELD(estado, 'disponible', 'en uso', 'sucio', 'lavando'), nombre ASC";
                        $res_busqueda_prendas = $mysqli_obj->query($sql_busqueda_prendas);

                        if ($res_busqueda_prendas && $res_busqueda_prendas->num_rows > 0) {
                            while ($pb = $res_busqueda_prendas->fetch_assoc()) {
                                $img_pb = !empty($pb['foto']) ? htmlspecialchars($pb['foto']) : 'https://via.placeholder.com/50x50?text=Sin+Img';
                                $est_pb = strtolower($pb['estado']);
                                $badge_pb = ($est_pb === 'disponible') ? 'bg-success' : (($est_pb === 'sucio') ? 'bg-danger' : 'bg-warning text-dark');

                                echo '
                                <div class="col-md-6 prenda-search-item"
                                     data-nombre-prenda="' . htmlspecialchars(strtolower($pb['nombre'])) . '"
                                     data-tipo-prenda="' . htmlspecialchars(strtolower($pb['tipo'])) . '"
                                     data-color-prenda="' . htmlspecialchars(strtolower($pb['color_principal'])) . '"
                                     data-tela-prenda="' . htmlspecialchars(strtolower($pb['tela'] ?? '')) . '"
                                     data-estado-prenda="' . $est_pb . '">
                                    
                                    <div class="form-check p-2 rounded bg-white border d-flex align-items-center justify-content-between h-100 card-check-busqueda">
                                        <div class="d-flex align-items-center flex-grow-1 overflow-hidden">
                                            <input class="form-check-input me-2 checkbox-prenda-busqueda flex-shrink-0" type="checkbox" value="' . $pb['id'] . '" id="searchPrenda' . $pb['id'] . '">
                                            <label class="form-check-label d-flex align-items-center w-100 overflow-hidden" for="searchPrenda' . $pb['id'] . '" style="cursor: pointer;">
                                                <img src="' . $img_pb . '" alt="' . htmlspecialchars($pb['nombre']) . '" class="me-2 rounded flex-shrink-0" style="width: 42px; height: 42px; object-fit: cover;">
                                                <div class="text-truncate">
                                                    <strong class="small d-block text-truncate">' . htmlspecialchars($pb['nombre']) . '</strong>
                                                    <small class="text-muted d-block text-truncate" style="font-size: 0.72rem;">' . htmlspecialchars($pb['tipo']) . ' • ' . htmlspecialchars($pb['color_principal']) . '</small>
                                                </div>
                                            </label>
                                        </div>
                                        <span class="badge ' . $badge_pb . ' rounded-pill ms-1 flex-shrink-0" style="font-size: 0.65rem;">' . htmlspecialchars($pb['estado']) . '</span>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '<div class="col-12"><p class="text-muted text-center my-3">No hay prendas registradas.</p></div>';
                        }
                        ?>
                    </div>
                    <div id="sinResultadosBusquedaModal" class="text-center text-muted py-3" style="display: none;">
                        <i class="fas fa-search mb-1"></i>
                        <p class="small mb-0">No se encontraron prendas con ese filtro.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="desmarcarTodasBusquedaBtn">
                    <i class="fas fa-eraser me-1"></i>Desmarcar todas
                </button>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="buscarOutfitsAvanzada()">
                        <i class="fas fa-filter me-1"></i>Aplicar Filtro
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>