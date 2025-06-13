<!-- Modal Eliminar Prenda -->
<div class="modal fade" id="modalEliminar_<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="labelEliminar_<?php echo $row['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="labelEliminar_<?php echo $row['id']; ?>">
                    <i class="fas fa-trash-alt me-2"></i>Eliminar Prenda
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                </div>

                <div class="text-center mb-4">
                    <h4 class="text-danger mb-3">¿Estás seguro?</h4>
                    <p class="mb-0">Esta acción no se puede deshacer. Se eliminará permanentemente la siguiente prenda:</p>
                </div>

                <!-- Información de la prenda a eliminar -->
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center">
                                <?php if (!empty($row['foto']) && file_exists($row['foto'])): ?>
                                    <img src="<?php echo $row['foto']; ?>" alt="Imagen de la prenda" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex justify-content-center align-items-center rounded" style="width: 100px; height: 100px; margin: 0 auto;">
                                        <i class="fas fa-tshirt fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title text-primary mb-2">
                                    <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                </h5>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">ID:</small><br>
                                        <span class="badge bg-secondary">#<?php echo $row['id']; ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Tipo:</small><br>
                                        <span class="badge bg-info"><?php echo ucfirst($row['tipo']); ?></span>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Color:</small><br>
                                        <span class="badge bg-primary"><?php echo ucfirst($row['color_principal']); ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Estado:</small><br>
                                        <?php
                                        $estadoClass = [
                                            'disponible' => 'bg-success',
                                            'sucio' => 'bg-warning',
                                            'prestado' => 'bg-info'
                                        ];
                                        $clase = $estadoClass[$row['estado']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $clase; ?>"><?php echo ucfirst($row['estado']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-danger mt-3 mb-0" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Atención:</strong> Una vez eliminada, no podrás recuperar esta prenda ni su información.
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="eliminarPrenda(<?php echo $row['id']; ?>)">
                    <i class="fas fa-trash-alt me-1"></i>Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts necesarios -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function eliminarPrenda(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('eliminar_prenda.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            id
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('¡Eliminado!', data.message, 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Error en la solicitud: ' + err, 'error');
                    });
            }
        });
    }


    // Limpiar el modal cuando se cierre
    document.querySelectorAll('[id^="modalEliminar_"]').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            // Limpiar cualquier estado si es necesario
        });
    });
</script>

<!-- Estilos adicionales -->
<style>
    .modal-content {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .modal-header.bg-danger {
        border-bottom: none;
    }

    .modal-footer.bg-light {
        border-top: 1px solid #dee2e6;
    }

    .card {
        transition: all 0.3s ease;
    }

    .badge {
        font-size: 0.75rem;
    }

    .btn {
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .alert {
        border: none;
        border-left: 4px solid #dc3545;
    }
</style>