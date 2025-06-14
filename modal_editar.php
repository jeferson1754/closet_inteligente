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
                                <select class="form-select" name="tipo" required>
                                    <option value="">Seleccionar...</option>
                                    <?php
                                    $tipos = ['camisa', 'camiseta', 'pantalon', 'falda', 'vestido', 'chaqueta', 'abrigo', 'zapatos', 'accesorios'];
                                    foreach ($tipos as $tipo) {
                                        $selected = ($row['tipo'] == $tipo) ? 'selected' : '';
                                        echo "<option value=\"$tipo\" $selected>" . ucfirst($tipo) . "</option>";
                                    }
                                    ?>
                                </select>
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
                                    $climas = ['todo' => 'Todo clima', 'calor' => 'Calor', 'frio' => 'Frío', 'lluvia' => 'Lluvia'];
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
                                    $formalidades = ['casual', 'semi-formal', 'formal'];
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
                                    <option value="disponible" <?php echo $row['estado'] == 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="sucio" <?php echo $row['estado'] == 'sucio' ? 'selected' : ''; ?>>Sucio</option>
                                    <option value="prestado" <?php echo $row['estado'] == 'prestado' ? 'selected' : ''; ?>>Prestado</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fecha Creado</label>
                                <input type="date" class="form-control" name="fecha_agregado" value="<?php echo $row['fecha_agregado']; ?>">
                            </div>
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
            const formData = new FormData(form);

            fetch('update_prenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Actualizado!', data.message, 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Error en la solicitud: ' + err, 'error');
                });
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