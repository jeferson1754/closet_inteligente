<?php
include 'bd.php'; // conexión a la base de datos

// Consulta para obtener prendas disponibles
$sql = "SELECT * FROM prendas";
$result = $mysqli_obj->query($sql);
$result_prendas = $mysqli_obj->query($sql);



// Consulta para obtener prendas disponibles
$sql2 = "SELECT * FROM `historial_usos`";
$result2 = $mysqli_obj->query($sql2);


$city = $_GET['city'] ?? 'Santiago';
$api_key = "22524bcc23b8c0635c013a41f40f6a4c";
$api_url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=$api_key&units=metric&lang=es";


// Obtener datos del clima
$response = @file_get_contents($api_url);
if (!$response) {
    echo json_encode([]);
    exit;
}

$data = json_decode($response, true);

// Verificar respuesta válida
if (!isset($data['main']['temp']) || !isset($data['weather'][0])) {
    echo json_encode([]);
    exit;
}

$temp_c = $data['main']['temp'];
$weather_api_term = $data['weather'][0]['description'];
$weather_desc = ucfirst($weather_api_term);
$icon_code = $data['weather'][0]['icon'];
$icon_url = "https://openweathermap.org/img/wn/{$icon_code}@2x.png";

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clóset Inteligente</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap JS Bundle (incluye Popper) al final del <body> -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
            max-width: 1200px;
        }

        .nav-pills .nav-link {
            border-radius: 25px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        body:not(.modal-open) .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .prenda-card {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4f8 100%);
            border-left: 4px solid #667eea;
        }

        .outfit-card {
            background: linear-gradient(135deg, #fff8f0 0%, #f0f8e8 100%);
            border-left: 4px solid #764ba2;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }


        .badge {
            border-radius: 15px;
            padding: 5px 12px;
            font-size: 0.8em;
        }

        .weather-widget {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .stats-card {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            text-align: center;
            padding: 25px;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .image-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .suggestion-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
        }

        .clima-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            margin: 2px;
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

        .formalidad-casual {
            background-color: #17a2b8;
            color: white;
        }

        .formalidad-semi-formal {
            background-color: #ffc107;
            color: black;
        }

        .formalidad-formal {
            background-color: #6f42c1;
            color: white;
        }

        .tab-pane {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
        }

        .prenda-card img {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #e9ecef;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header -->
            <div class="bg-primary text-white p-4">
                <h1 class="mb-0"><i class="fas fa-tshirt me-2"></i>Clóset Inteligente</h1>
                <p class="mb-0">Tu asistente personal de moda</p>
            </div>

            <!-- Navigation -->
            <div class="p-3">
                <ul class="nav nav-pills justify-content-center" id="mainTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="pill" href="#dashboard">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#prendas">
                            <i class="fas fa-tshirt me-2"></i>Prendas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#outfits">
                            <i class="fas fa-palette me-2"></i>Outfits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="pill" href="#sugerencias">
                            <i class="fas fa-magic me-2"></i>Sugerencias
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Content -->
            <div class="tab-content p-4">
                <!-- Dashboard -->
                <div class="tab-pane fade show active" id="dashboard">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3 id="totalPrendas">0</h3>
                                <p>Prendas Totales</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3 id="totalOutfits">0</h3>
                                <p>Outfits Creados</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3 id="prendasDisponibles">0</h3>
                                <p>Disponibles</p>
                            </div>
                        </div>
                        <?php


                        if (isset($data['current'])) {
                            $weather_desc_raw = $data['current']['weather_descriptions'][0] ?? "No disponible";
                            $weather_desc = $translations[$weather_desc_raw] ?? $weather_desc_raw; // Aplica traducción si existe
                            $temp_c = $data['current']['temperature'] ?? "";
                            $icon_url = $data['current']['weather_icons'][0] ?? "";
                        }
                        ?>



                        <div class="col-md-3 mb-4">
                            <div class="weather-widget">
                                <?php if ($icon_url): ?>
                                    <img src="<?= htmlspecialchars($icon_url) ?>" alt="Icono clima" style="width:40px; height:40px;">
                                <?php else: ?>
                                    <i class="fas fa-sun fa-2x mb-2"></i>
                                <?php endif; ?>
                                <h5>Clima Hoy</h5>
                                <small id="climaActual"><?= htmlspecialchars($weather_desc) ?><?= $temp_c !== "" ? ", " . htmlspecialchars($temp_c) . "°C" : "" ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-clock me-2"></i>Prendas Más Usadas</h5>
                                </div>
                                <div class="card-body" id="prendasMasUsadas">
                                    <?php

                                    // Consulta para obtener las prendas más usadas
                                    $query = "
                                           SELECT p.nombre, COUNT(h.id) AS usos FROM historial_usos h INNER JOIN prendas p ON h.prenda_id = p.id GROUP BY h.prenda_id ORDER BY usos DESC LIMIT 5;
                                        ";

                                    $resultado = $mysqli_proc->query($query);

                                    if ($resultado && $resultado->num_rows > 0) {
                                        echo '<ul class="list-group list-group-flush">';
                                        while ($fila = $resultado->fetch_assoc()) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo htmlspecialchars($fila['nombre']);
                                            echo '<span class="badge bg-primary rounded-pill">' . (int)$fila['usos'] . '</span>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<p class="text-muted">No hay datos de uso disponibles</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-lightbulb me-2"></i>Sugerencia del Día</h5>
                                </div>
                                <div class="card-body" id="sugerenciaDia">
                                    <div class="suggestion-card">
                                        <h6>Outfit Recomendado</h6>
                                        <p>Basado en el clima actual, te recomendamos un look casual y cómodo.</p>
                                        <button class="btn btn-light btn-sm" onclick="generarSugerencia()">
                                            <i class="fas fa-refresh me-1"></i>Nueva Sugerencia
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Prendas -->
                <div class="tab-pane fade" id="prendas">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-plus-circle me-2"></i>Agregar Prenda</h5>
                                </div>
                                <div class="card-body">
                                    <form id="formPrenda">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre</label>
                                            <input type="text" class="form-control" name="nombre" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo</label>
                                            <select class="form-select" name="tipo" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="camisa">Camisa</option>
                                                <option value="camiseta">Camiseta</option>
                                                <option value="pantalon">Pantalón</option>
                                                <option value="falda">Falda</option>
                                                <option value="vestido">Vestido</option>
                                                <option value="chaqueta">Chaqueta</option>
                                                <option value="abrigo">Abrigo</option>
                                                <option value="zapatos">Zapatos</option>
                                                <option value="accesorios">Accesorios</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Color Principal</label>
                                            <input type="text" class="form-control" name="color_principal">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tela</label>
                                            <input type="text" class="form-control" name="tela">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Textura</label>
                                            <input type="text" class="form-control" name="textura">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Estampado</label>
                                            <input type="text" class="form-control" name="estampado">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Clima Apropiado</label>
                                            <select class="form-select" name="clima_apropiado">
                                                <option value="todo">Todo clima</option>
                                                <option value="calor">Calor</option>
                                                <option value="frio">Frío</option>
                                                <option value="lluvia">Lluvia</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Formalidad</label>
                                            <select class="form-select" name="formalidad">
                                                <option value="casual">Casual</option>
                                                <option value="semi-formal">Semi-formal</option>
                                                <option value="formal">Formal</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Foto</label>
                                            <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewImage(this, 'previewPrenda')">
                                            <div class="mt-2">
                                                <img id="previewPrenda" class="image-preview" style="display: none;">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-2"></i>Guardar Prenda
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-list me-2"></i>Mi Clóset</h5>
                                    <div>
                                        <input type="text" class="form-control d-inline-block" style="width: 200px;"
                                            placeholder="Buscar prendas..." id="buscarPrendas" onkeyup="filtrarPrendas()">
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="loading" id="loadingPrendas">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                                        <p>Cargando prendas...</p>
                                    </div>

                                    <div class="row">
                                        <?php while ($row = $result_prendas->fetch_assoc()): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card prenda-card">
                                                    <!-- Imagen de la prenda o fondo gris si no hay -->
                                                    <?php if (!empty($row['foto'])): ?>
                                                        <img src="<?php echo $row['foto']; ?>" class="card-img-top" alt="Imagen de <?php echo $row['nombre']; ?>" style="object-fit: cover; max-height: 200px;">
                                                    <?php else: ?>
                                                        <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary text-white" style="height: 200px;">
                                                            <span>Sin imagen</span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="card-title"><?php echo $row['nombre']; ?></h6>
                                                                <p class="card-text">
                                                                    <small class="text-muted"><?php echo $row['tipo']; ?> • <?php echo $row['color_principal']; ?></small><br>
                                                                    <small><?php echo $row['tela']; ?> • <?php echo $row['textura']; ?></small>
                                                                </p>


                                                            </div>
                                                        </div>

                                                        <div class="mt-2">
                                                            <span class="badge clima-<?php echo $row['clima_apropiado']; ?>"><?php echo $row['clima_apropiado']; ?></span>
                                                            <span class="badge formalidad-<?php echo $row['formalidad']; ?>"><?php echo $row['formalidad']; ?></span>
                                                            <span class="badge bg-<?php echo $row['estado'] === 'disponible' ? 'success' : 'warning'; ?>"><?php echo $row['estado']; ?></span>
                                                        </div>
                                                        <div class="text-center mt-2">
                                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEditar_<?php echo $row['id']; ?>">
                                                                Editar
                                                            </button>
                                                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar_<?php echo $row['id']; ?>">
                                                                Eliminar
                                                            </button>
                                                        </div>

                                                    </div>
                                                </div>


                                            </div>

                                            <!-- Modal simple por prenda -->

                                        <?php
                                            include('modal_editar.php');
                                            include('modal_eliminar.php');
                                        endwhile; ?>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outfits -->
                <div class="tab-pane fade" id="outfits">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-plus-circle me-2"></i>Crear Outfit</h5>
                                </div>
                                <div class="card-body">
                                    <form id="formOutfit">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del Outfit</label>
                                            <input type="text" class="form-control" name="nombre" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Contexto</label>
                                            <select class="form-select" name="contexto" required>
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
                                            <select class="form-select" name="clima_base">
                                                <option value="todo">Todo clima</option>
                                                <option value="calor">Calor</option>
                                                <option value="frio">Frío</option>
                                                <option value="lluvia">Lluvia</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Seleccionar Prendas</label>

                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-2"></i>Crear Outfit
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-palette me-2"></i>Mis Outfits</h5>
                                </div>
                                <div class="card-body">


                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sugerencias -->
                <div class="tab-pane fade" id="sugerencias">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-cog me-2"></i>Configurar Sugerencia</h5>
                                </div>
                                <div class="card-body">
                                    <form id="formSugerencia">
                                        <div class="mb-3">
                                            <label class="form-label">Contexto</label>
                                            <select class="form-select" name="contexto" required>
                                                <option value="trabajo">Trabajo</option>
                                                <option value="universidad">Universidad</option>
                                                <option value="evento">Evento</option>
                                                <option value="casa">Casa</option>
                                                <option value="deporte">Deporte</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Clima Esperado</label>
                                            <select class="form-select" name="clima">
                                                <option value="calor">Calor</option>
                                                <option value="frio">Frío</option>
                                                <option value="lluvia">Lluvia</option>
                                                <option value="todo">Variable</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Espacio en Mochila</label>
                                            <select class="form-select" name="espacio_mochila">
                                                <option value="limitado">Limitado</option>
                                                <option value="normal">Normal</option>
                                                <option value="amplio">Amplio</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="muda_extra" id="mudaExtra">
                                                <label class="form-check-label" for="mudaExtra">
                                                    ¿Puedo llevar muda extra?
                                                </label>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-primary w-100" onclick="generarSugerenciaPersonalizada()">
                                            <i class="fas fa-magic me-2"></i>Generar Sugerencia
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-lightbulb me-2"></i>Sugerencias Personalizadas</h5>
                                </div>
                                <div class="card-body">

                                    <div id="sugerenciasPersonalizadas">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-magic fa-3x mb-3"></i>
                                            <p>Configura los parámetros y genera tu sugerencia personalizada</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        // Agregar prenda
        document.getElementById('formPrenda').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            // Enviar datos al servidor mediante AJAX
            fetch('crear_prenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json()) // Asegúrate de que tu backend devuelva JSON
                .then(data => {
                    if (data.success) {
                        // Puedes usar los datos del servidor o los del formulario, según necesites
                        const prenda = {
                            id: data.id || Date.now(), // Usa el ID que devuelva el backend o uno temporal
                            nombre: formData.get('nombre'),
                            tipo: formData.get('tipo'),
                            color_principal: formData.get('color_principal'),
                            tela: formData.get('tela'),
                            textura: formData.get('textura'),
                            estampado: formData.get('estampado'),
                            clima_apropiado: formData.get('clima_apropiado'),
                            formalidad: formData.get('formalidad'),
                            estado: 'disponible',
                            foto: null // Aquí puedes gestionar la foto si también se guarda
                        };

                        document.getElementById('previewPrenda').style.display = 'none';

                        Swal.fire('Prenda agregada exitosamente', data.message, 'success')
                            .then(() => location.reload());
                    } else {

                        Swal.fire('Error al agregar la prenda: ', (data.message || 'Error desconocido'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error en la solicitud AJAX:', error);
                    Swal.fire('Ocurrió un error al enviar el formulario', 'error');
                });
        });




        function generarSugerencia(ciudad = 'Santiago') {
            fetch('obtener_sugerencias.php?city=' + encodeURIComponent(ciudad))
                .then(res => res.json())
                .then(sugerenciasDesdePHP => {
                    if (!Array.isArray(sugerenciasDesdePHP) || sugerenciasDesdePHP.length === 0) {
                        document.getElementById('sugerenciaDia').innerHTML = '<p class="text-muted">No hay sugerencias disponibles</p>';
                        return;
                    }

                    const sugerenciaAleatoria = sugerenciasDesdePHP[Math.floor(Math.random() * sugerenciasDesdePHP.length)];

                    const html = `
                <div class="suggestion-card">
                    <h6>${sugerenciaAleatoria.titulo}</h6>
                    <p>${sugerenciaAleatoria.descripcion}</p>
                    <div class="mb-2">
                        <strong>Prendas sugeridas:</strong>
                        <ul class="mb-2">
                            ${sugerenciaAleatoria.prendas.map(prenda => `<li>${prenda.trim()}</li>`).join('')}
                        </ul>
                    </div>
                    <div class="alert alert-light mb-0">
                        <i class="fas fa-lightbulb me-2"></i><strong>Tip:</strong> ${sugerenciaAleatoria.tips}
                    </div>
                </div>
            `;

                    document.getElementById('sugerenciaDia').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error al obtener sugerencias:', error);
                    document.getElementById('sugerenciaDia').innerHTML = '<p class="text-danger">Error al cargar sugerencias</p>';
                });
        }

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
</body>

</html>