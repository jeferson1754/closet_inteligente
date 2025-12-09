<div class="modal fade" id="searchOutfitModal" tabindex="-1" aria-labelledby="searchOutfitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="searchOutfitModalLabel">Filtrar Outfits por Prendas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Selecciona las prendas que **debe contener** el Outfit:</p>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                    <input type="text" class="form-control" id="searchPrendasFilter" placeholder="Buscar prenda..." onkeyup="filterPrendasForSearch()">
                </div>
                <div id="listaPrendasBusqueda" class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($prendas_para_sugerencia_compras as $prenda): ?>
                        <label class="list-group-item align-items-center prenda-search-item"
                            data-nombre-prenda="<?= htmlspecialchars(strtolower($prenda['nombre'])) ?>"
                            data-tipo-prenda="<?= htmlspecialchars(strtolower($prenda['tipo'])) ?>">
                            <input class="form-check-input me-3" type="checkbox" value="<?= $prenda['id'] ?>" aria-label="...">
                            <i class="fas fa-tag me-2 text-primary"></i>
                            <?= htmlspecialchars($prenda['nombre']) ?>
                            <span class="badge bg-secondary ms-auto"><?= htmlspecialchars($prenda['tipo']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="buscarOutfitsAvanzada()" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Aplicar Filtro
                </button>
            </div>
        </div>
    </div>
</div>