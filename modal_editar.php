<div class="modal fade" id="modalEditar_<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="labelEditar_<?php echo $row['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <form action="update_prenda.php" method="post" id="formulario-prenda-<?php echo $row['id']; ?>" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="labelEditar_<?php echo $row['id']; ?>">
                        Editar Prenda #<?php echo $row['id']; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

                    <!-- Información Básica -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class="fas fa-info-circle text-primary"></i>
                            Información Básica
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="<?php echo $row['nombre']; ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tipo</label>
                                <input list="tipos" class="form-control" name="tipo" placeholder="Seleccionar..." value="<?php echo $row['tipo']; ?>">
                                <datalist id='tipos'>
                                    <?php
                                    $queryCargos = "SELECT DISTINCT(tipo) FROM prendas ORDER BY `prendas`.`tipo` ASC";
                                    $resultCargos = mysqli_query($mysqli_obj, $queryCargos);
                                    while ($rowCargos = mysqli_fetch_assoc($resultCargos)) {
                                        echo "<option value='" . $rowCargos['tipo'] . "'></option>";
                                    }
                                    ?>
                                </datalist>

                            </div>
                        </div>
                    </div>

                    <!-- Características -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class="fas fa-palette text-success"></i>
                            Características
                        </h6>
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">Color Principal</label>
                                <input type="text" class="form-control" name="color_principal" value="<?php echo $row['color_principal']; ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tela</label>
                                <input type="text" class="form-control" name="tela" value="<?php echo $row['tela']; ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Textura</label>
                                <input type="text" class="form-control" name="textura" value="<?php echo $row['textura']; ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Estampado</label>
                                <input type="text" class="form-control" name="estampado" value="<?php echo $row['estampado']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Uso y Estado -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class="fas fa-cog text-warning"></i>
                            Uso y Estado
                        </h6>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Clima Apropiado</label>
                                <select class="form-select" name="clima_apropiado" required>
                                    <?php

                                    foreach ($climas as $valor => $texto) {
                                        $selected = ($row['clima_apropiado'] == $valor) ? 'selected' : '';
                                        echo "<option value=\"$valor\" $selected>$texto</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Formalidad</label>
                                <select class="form-select" name="formalidad" required>
                                    <?php

                                    foreach ($formalidades as $f) {
                                        $selected = ($row['formalidad'] == $f) ? 'selected' : '';
                                        echo "<option value=\"$f\" $selected>" . ucfirst($f) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado" required>
                                    <?php

                                    foreach ($estados as $valor => $texto) {
                                        $selected = ($row['estado'] == $valor) ? 'selected' : '';
                                        echo "<option value=\"$valor\" $selected>$texto</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fecha Creado</label>
                                <input type="date" class="form-control" name="fecha_agregado" value="<?php echo $row['fecha_agregado']; ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Usos esta semana</label>
                                <input type="number" class="form-control" name="usos_esta_semana" value="<?php echo (int)$row['usos_esta_semana']; ?>" min="0" required>
                                <small class="form-text text-muted">Cantidad de veces usada esta semana.</small>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="uso_ilimitado" id="usoIlimitado_<?php echo $row['id']; ?>"
                                        <?php echo $row['uso_ilimitado'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="usoIlimitado_<?php echo $row['id']; ?>">
                                        Uso Ilimitado (Excluir de conteo semanal)
                                    </label>
                                </div>
                            </div>


                        </div>
                    </div>
                    <div class="form-section">
                        <div class="mb-3">
                            <label class="form-label">Comentarios</label>
                            <textarea class="form-control" name="comentarios" rows="3"><?php echo htmlspecialchars($row['detalles_adicionales'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <!-- Imagen -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class="fas fa-camera text-info"></i>
                            Imagen de la Prenda
                        </h6>


                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label">Imagen Actual</label>
                                <input type="hidden" name="imagen" value="<?php echo $row['foto']; ?>">
                                <div class="text-center">
                                    <!-- Imagen actual -->
                                    <?php if (!empty($row['foto']) && file_exists($row['foto'])): ?>

                                        <img src="<?php echo $row['foto']; ?>" alt="Imagen de la prenda" class="img-thumbnail mb-2" style="max-width: 200px;">
                                    <?php else: ?>

                                        <div class="img-thumbnail bg-secondary justify-content-center align-items-center text-white" style="width: 200px; height: 200px;margin:0 auto;">
                                            Sin imagen
                                        </div>

                                    <?php endif; ?>
                                </div>

                                <!-- Campo para nueva imagen -->
                                <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewImage(this, 'preview_<?php echo $row['id']; ?>')">

                                <!-- Vista previa -->
                                <div class="mt-2">
                                    <img id="preview_<?php echo $row['id']; ?>" class="image-preview img-thumbnail" style="max-height: 150px; display: none;">
                                </div>
                            </div>
                        </div>

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('formulario-prenda').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const prendaId = formData.get('id'); // Obtener el ID para la validación de duplicados en UPDATE

            // Leer si ya se ha forzado la edición duplicada por nombre
            const forceDuplicateEditName = this.dataset.forceDuplicateEditName === 'true';
            if (forceDuplicateEditName) {
                formData.append('force_duplicate_name', 'true');
                this.dataset.forceDuplicateEditName = 'false'; // Resetear el flag
            }
            sendPrendaRequest(formData, 'update_prenda.php', forceDuplicateEditName);
        });
    });



    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>