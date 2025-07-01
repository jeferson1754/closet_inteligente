<?php
include 'bd.php'; // conexión a la base de datos

// Consulta para obtener prendas disponibles
$sql = "SELECT * FROM prendas";
$result = $mysqli_obj->query($sql);
$result_prendas = $mysqli_obj->query($sql);

// Consulta para obtener prendas disponibles y con menos de 3 usos esta semana
$prendas_para_sugerencia_ia = [];

// Consulta para contar usos de cada prenda en la semana actual
$sql_usos_semana = "
    SELECT
        prenda_id,
        COUNT(*) AS usos_esta_semana
    FROM
        historial_usos
    WHERE
        fecha  >= ? AND fecha  < ?
    GROUP BY
        prenda_id
";

$stmt_usos = $mysqli_obj->prepare($sql_usos_semana);
$stmt_usos->bind_param("ss", $current_week_start, $current_week_end);
$stmt_usos->execute();
$result_usos = $stmt_usos->get_result();

$usos_semanales = [];
while ($row_uso = $result_usos->fetch_assoc()) {
    $usos_semanales[$row_uso['prenda_id']] = $row_uso['usos_esta_semana'];
}
$stmt_usos->close();

// Consulta para obtener prendas disponibles
$sql_prendas_disponibles = "SELECT id, nombre, tipo, color_principal FROM prendas WHERE estado = 'disponible'";
$result_disponibles = $mysqli_obj->query($sql_prendas_disponibles);

if ($result_disponibles) {
    while ($prenda_disp = $result_disponibles->fetch_assoc()) {
        $prenda_id = $prenda_disp['id'];
        $usos = $usos_semanales[$prenda_id] ?? 0; // Obtener usos o 0 si no hay registro

        // Si la prenda tiene menos de 3 usos esta semana, la añadimos a la lista
        if ($usos < 3) {
            $prendas_para_sugerencia_ia[] = [
                'id' => $prenda_disp['id'],
                'nombre' => $prenda_disp['nombre'],
                'tipo' => $prenda_disp['tipo'],
                'color' => $prenda_disp['color_principal'],
                'usos_esta_semana' => $usos
            ];
        }
    }
}

// Convertir el array PHP a JSON para pasarlo a JavaScript
$json_prendas_para_sugerencia_ia = json_encode($prendas_para_sugerencia_ia);

// Consulta para obtener prendas disponibles para el formulario de outfit
$sql = "SELECT id, nombre, tipo, color_principal, foto FROM prendas WHERE estado = 'disponible'";
$result_for_outfit_form = $mysqli_obj->query($sql);



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


// --- INICIO: Clima actual y pronóstico para mañana ---
// --- INICIO: Clima actual y pronóstico para mañana ---
$city = $_GET['city'] ?? 'Santiago';
define('API_KEY', '22524bcc23b8c0635c013a41f40f6a4c');
define('API_BASE_URL', 'https://api.openweathermap.org/data/2.5/');
define('UNITS', 'metric');
define('LANG', 'es');

function fetch_api_data($url)
{
    $response = @file_get_contents($url);
    return $response ? json_decode($response, true) : null;
}
// --- Clima Actual ---
$api_url_current = API_BASE_URL . "weather?q=" . urlencode($city) . "&appid=" . API_KEY . "&units=" . UNITS . "&lang=" . LANG;
$data_current = fetch_api_data($api_url_current);

// Datos actuales
$temp_c = $data_current['main']['temp'] ?? '';
$weather_desc = ucfirst($data_current['weather'][0]['description'] ?? 'No disponible');
$icon_code = $data_current['weather'][0]['icon'] ?? '';
$icon_url = $icon_code ? "https://openweathermap.org/img/wn/{$icon_code}@2x.png" : '';

$forecast_data = [];

if (!empty($data_current['coord']['lat']) && !empty($data_current['coord']['lon'])) {
    $lat = $data_current['coord']['lat'];
    $lon = $data_current['coord']['lon'];

    // --- Pronóstico para mañana ---
    $api_url_forecast = API_BASE_URL . "forecast?lat={$lat}&lon={$lon}&appid=" . API_KEY . "&units=" . UNITS . "&lang=" . LANG;
    $data_forecast = fetch_api_data($api_url_forecast);

    if (!empty($data_forecast['list'])) {
        $tomorrow = (new DateTime('tomorrow', new DateTimeZone('America/Santiago')))->format('Y-m-d');

        // Horarios objetivo
        $target_schedule = [
            '5 AM'  => ['05'],
            '5 PM'  => ['17'],
            '10 PM' => ['22', '23', '21'], // más flexible
        ];

        foreach ($target_schedule as $label => $hours) {
            $found = false;

            foreach ($hours as $target_hour) {
                foreach ($data_forecast['list'] as $item) {
                    if (empty($item['dt_txt'])) continue;

                    $dt = new DateTime($item['dt_txt'], new DateTimeZone('UTC'));
                    $dt->setTimezone(new DateTimeZone('America/Santiago'));

                    if ($dt->format('Y-m-d') === $tomorrow && $dt->format('H') === $target_hour) {
                        $forecast_data[] = [
                            'label' => $label,
                            'time'  => $dt->format('g A'),
                            'temp'  => round($item['main']['temp']),
                            'desc'  => $item['weather'][0]['description'] ?? 'No disponible',
                        ];
                        $found = true;
                        break 2; // salir de ambos bucles
                    }
                }
            }

            if (!$found) {
                $forecast_data[] = [
                    'label' => $label,
                    'time'  => null,
                    'temp'  => null,
                    'desc'  => 'No disponible',
                ];
            }
        }
    }
}

// --- Exportar JSON del pronóstico ---
$json_forecast_data = json_encode($forecast_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// --- FIN: Clima actual y pronóstico para mañana ---

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clóset Inteligente</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap JS + Popper -->
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
                    <?php
                    $total_prendas = $pdo->query("SELECT COUNT(*) FROM prendas")->fetchColumn() ?? 0;
                    $total_disponibles = $pdo->query("SELECT COUNT(*) FROM prendas WHERE estado = 'disponible'")->fetchColumn();
                    $total_outfits = $pdo->query("SELECT COUNT(*) FROM outfits")->fetchColumn();
                    ?>
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3><?= $total_prendas ?></h3>
                                <p>Prendas Totales</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3><?= $total_outfits ?></h3>
                                <p>Outfits Creados</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card stats-card">
                                <h3><?= $total_disponibles ?></h3>
                                <p>Disponibles</p>
                            </div>
                        </div>
                        <?php



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
                                    <h5><i class="fas fa-calendar-day me-2"></i>Outfit del Día</h5>
                                </div>
                                <div class="card-body" id="outfitDelDia">
                                    <?php
                                    // Consulta para obtener el outfit marcado como "del día"
                                    $sql_outfit_dia = "SELECT id, nombre, contexto, clima_base FROM outfits WHERE es_outfit_del_dia = TRUE LIMIT 1";
                                    $result_outfit_dia = $mysqli_obj->query($sql_outfit_dia);
                                    $outfit_del_dia = $result_outfit_dia->fetch_assoc();

                                    if ($outfit_del_dia) {
                                        echo '
                                        <div class="suggestion-card text-center">
                                            <h6>' . htmlspecialchars($outfit_del_dia['nombre']) . '</h6>
                                            <p>Este es el outfit que seleccionaste para hoy.</p>
                                            <div class="mt-2">
                                                <span class="badge bg-primary">' . htmlspecialchars($outfit_del_dia['contexto']) . '</span>
                                                <span class="badge clima-' . htmlspecialchars($outfit_del_dia['clima_base']) . '">' . htmlspecialchars($outfit_del_dia['clima_base']) . '</span>
                                            </div>
                              
                                            <a href="detalle_outfit.php?id=' . $outfit_del_dia['id'] . '" class="btn btn-light btn-sm mt-3">
                                                    <i class="fas fa-eye me-1"></i>Ver Detalles
                                             </a>
                                        </div>';
                                    } else {
                                        echo '<div class="text-center text-muted">
                                                <i class="fas fa-tshirt fa-3x mb-3"></i>
                                                <p>¡Aún no has seleccionado tu outfit del día!</p>
                                                <p><small>Usa la opción "Usar hoy" en la pestaña de Outfits.</small></p>
                                              </div>';
                                    }
                                    ?>
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
                                        <div class="mb-3">
                                            <label class="form-label">Comentarios</label>
                                            <textarea class="form-control" name="comentarios" rows="3"></textarea>
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
                                            <div class="col-md-6 mb-3 prenda-item" data-nombre-prenda="<?php echo htmlspecialchars(strtolower($row['nombre'])); ?>">
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
                                            <label class="form-label">Comentarios</label>
                                            <textarea class="form-control" name="comentarios" rows="3"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Seleccionar Prendas</label>
                                            <?php
                                            $sql = "SELECT id, nombre, tipo, color_principal FROM prendas WHERE estado = 'disponible'";
                                            $result = $mysqli_obj->query($sql);

                                            if ($result && $result->num_rows > 0) {
                                                while ($prenda = $result->fetch_assoc()) {
                                                    echo '
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="prendas[]" value="' . $prenda['id'] . '" id="prenda' . $prenda['id'] . '">
                                                <label class="form-check-label" for="prenda' . $prenda['id'] . '">
                                                    <strong>' . htmlspecialchars($prenda['nombre']) . '</strong><br>
                                                    <small class="text-muted">' . htmlspecialchars($prenda['tipo']) . ' • ' . htmlspecialchars($prenda['color_principal']) . '</small>
                                                </label>
                                            </div>
                                        ';
                                                }
                                            } else {
                                                echo '<p class="text-muted">No hay prendas disponibles</p>';
                                            }
                                            ?>
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
                                <div class="card-body row">
                                    <?php

                                    // Consulta que obtiene outfits y la cantidad de prendas asociadas
                                    $sql = "
                                   SELECT o.id, o.nombre, o.contexto, o.clima_base, COUNT(ot.prenda_id) AS total_prendas, GROUP_CONCAT(ot.prenda_id) AS prendas_ids FROM outfits o LEFT JOIN outfit_prendas ot ON o.id = ot.outfit_id GROUP BY o.id, o.nombre, o.contexto, o.clima_base ORDER BY o.id DESC;
                                ";


                                    $result = $mysqli_obj->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($outfit = $result->fetch_assoc()) {
                                            $prendas_ids = $outfit['prendas_ids'] ? explode(',', $outfit['prendas_ids']) : [];
                                            $data_prendas = htmlspecialchars(json_encode($prendas_ids));

                                            echo '
        <div class="col-md-6 mb-3">
            <div class="card outfit-card" data-outfit-id="' . $outfit['id'] . '" data-prendas=\'' . $data_prendas . '\'>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title">' . htmlspecialchars($outfit['nombre']) . '</h6>
                            <p class="card-text">
                                <small class="text-muted">' . htmlspecialchars($outfit['contexto']) . ' • ' . htmlspecialchars($outfit['clima_base']) . '</small><br>
                                <small>' . $outfit['total_prendas'] . ' prendas</small>
                            </p>
                        </div>
                   <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item ver-detalles" href="#" data-id="' . $outfit['id'] . '" data-action="ver">
                                    <i class="fas fa-eye me-2"></i>Ver detalles
                                </a>
                                
                            </li>
                            <li>
                                <a class="dropdown-item usar-outfit" href="#" data-id="' . $outfit['id'] . '" data-action="usar">
                                    <i class="fas fa-play me-2"></i>Usar hoy
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item eliminar-outfit" href="#" data-id="' . $outfit['id'] . '" data-action="eliminar">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </a>
                            </li>
                        </ul>
                    </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-primary">' . htmlspecialchars($outfit['contexto']) . '</span>
                        <span class="badge clima-' . htmlspecialchars($outfit['clima_base']) . '">' . htmlspecialchars($outfit['clima_base']) . '</span>
                    </div>
                </div>
            </div>
        </div>';
                                        }
                                    } else {
                                        echo '<div class="col-12"><p class="text-muted text-center">No hay outfits creados aún</p></div>';
                                    }
                                    ?>
                                    <!-- Modal único -->
                                    <div class="modal fade" id="modalAccionOutfit" tabindex="-1" aria-labelledby="modalAccionOutfitLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="modalAccionOutfitLabel">Título modal</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Contenido modal...
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                    <button type="button" class="btn btn-primary" id="btnConfirmarAccion">Confirmar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

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
                                            <label class="form-label d-block">Contexto</label>
                                            <div class="d-flex flex-wrap gap-2">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="contextoTrabajo" name="contexto[]" value="trabajo">
                                                    <label class="form-check-label" for="contextoTrabajo">Trabajo</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="contextoUniversidad" name="contexto[]" value="universidad">
                                                    <label class="form-check-label" for="contextoUniversidad">Universidad</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="contextoEvento" name="contexto[]" value="evento">
                                                    <label class="form-check-label" for="contextoEvento">Evento</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="contextoCasa" name="contexto[]" value="casa">
                                                    <label class="form-check-label" for="contextoCasa">Casa</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="contextoDeporte" name="contexto[]" value="deporte">
                                                    <label class="form-check-label" for="contextoDeporte">Deporte</label>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Selecciona uno o más contextos.</small>
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
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-robot me-2"></i>Interacción con IA para Sugerencias</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-section">
                                        <h6>Prompt para la IA:</h6>
                                        <div class="input-group mb-3">
                                            <textarea id="aiPrompt" class="form-control" rows="8" readonly placeholder="El prompt para la IA aparecerá aquí..."></textarea>
                                            <button class="btn btn-outline-secondary" type="button" id="copyPromptBtn">
                                                <i class="fas fa-copy me-2"></i>Copiar
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-primary w-100 mb-3" onclick="generarPrompt()">
                                            <i class="fas fa-magic me-2"></i>Generar Prompt para IA
                                        </button>
                                    </div>

                                    <div class="form-section mt-4">
                                        <h6>Respuesta de la IA:</h6>
                                        <div class="input-group mb-3">
                                            <textarea id="aiResponse" class="form-control" rows="8" placeholder="Pega aquí la respuesta de la IA..."></textarea>
                                            <button class="btn btn-outline-secondary" type="button" id="processResponseBtn">
                                                <i class="fas fa-check-circle me-2"></i>Procesar Respuesta
                                            </button>
                                        </div>
                                        <div id="processedSuggestion" class="mt-3">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-magic fa-3x mb-3"></i>
                                                <p>Pega la respuesta de la IA y haz clic en "Procesar" para ver tu sugerencia.</p>
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
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap JS Bundle (incluye Popper) al final del <body> -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
                            comentarios: formData.get('comentarios'),
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

        document.addEventListener('DOMContentLoaded', function() {

            // Agregar nuevo outfit
            const formOutfit = document.getElementById('formOutfit');
            if (formOutfit) {
                formOutfit.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const form = e.target;
                    const formData = new FormData(form);

                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }


                    fetch('crear_outfit.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const outfit = {
                                    id: data.id || Date.now(),
                                    nombre: formData.get('nombre'),
                                    contexto: formData.get('contexto'),
                                    clima_base: formData.get('clima_base'),
                                    comentarios: formData.get('comentarios'),
                                    prendas: data.prendas || []
                                };

                                Swal.fire('Outfit creado exitosamente', data.message, 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error al crear el outfit', data.message || 'Error desconocido', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error en la solicitud AJAX:', error);
                            Swal.fire('Ocurrió un error al enviar el formulario', '', 'error');
                        });
                });
            }

        });




        document.addEventListener('DOMContentLoaded', () => {
            const modalElement = document.getElementById('modalAccionOutfit');
            const modal = new bootstrap.Modal(modalElement);
            const titulo = modalElement.querySelector('.modal-title');
            const cuerpo = modalElement.querySelector('.modal-body');
            const btnConfirmar = modalElement.querySelector('#btnConfirmarAccion');

            let outfitIdActual = null;
            let accionActual = null;

            document.querySelectorAll('.dropdown-menu a').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();

                    outfitIdActual = link.getAttribute('data-id');
                    accionActual = link.getAttribute('data-action');

                    switch (accionActual) {
                        case 'ver':
                            titulo.textContent = 'Detalles del Outfit';
                            cuerpo.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Cargando prendas...</p></div>'; // Mensaje de carga
                            btnConfirmar.style.display = 'none';

                            fetch(`obtener_prendas_outfit.php?outfit_id=${outfitIdActual}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        let contentHtml = '';

                                        // Detalles del Outfit (nuevo)
                                        if (data.outfit_details) {
                                            contentHtml += `
                                            <div class="mb-3 text-center">
                                                <h4>${data.outfit_details.nombre}</h4>
                                                <p><strong>Contexto:</strong> ${data.outfit_details.contexto} | <strong>Clima:</strong> ${data.outfit_details.clima_base}</p>
                                                ${data.outfit_details.comentarios ? `<p class="alert alert-info"><strong>Comentarios:</strong> ${data.outfit_details.comentarios}</p>` : ''}
                                                <hr>
                                            </div>
                                        `;
                                        }

                                        // Prendas del Outfit
                                        if (data.prendas.length > 0) {
                                            contentHtml += '<h6 class="mb-3">Prendas que componen este outfit:</h6><div class="row">';
                                            data.prendas.forEach(prenda => {
                                                contentHtml += `
                                                <div class="col-6 col-md-4 mb-3">
                                                    <div class="card h-100">
                                                        <img src="${prenda.foto}" class="card-img-top" alt="${prenda.nombre}" style="height: 120px; object-fit: cover;">
                                                        <div class="card-body p-2">
                                                            <h6 class="card-title mb-0">${prenda.nombre}</h6>
                                                            <small class="text-muted">${prenda.tipo} - ${prenda.color_principal}</small>
                                                            ${prenda.comentarios ? `<p class="card-text"><small class="text-muted"><em>"${prenda.comentarios.substring(0, 50)}..."</em></small></p>` : ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
                                            });
                                            contentHtml += '</div>';
                                        } else {
                                            contentHtml += `<p class="text-muted text-center">${data.message || 'No se encontraron prendas para este outfit.'}</p>`;
                                        }
                                        cuerpo.innerHTML = contentHtml;

                                    } else {
                                        cuerpo.innerHTML = `<p class="text-danger text-center">${data.message || 'Error al obtener los detalles del outfit.'}</p>`;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error al obtener los detalles del outfit:', error);
                                    cuerpo.innerHTML = '<p class="text-danger text-center">Error al cargar los detalles del outfit.</p>';
                                });
                            break;
                        case 'usar':
                            titulo.textContent = 'Usar Outfit #' + outfitIdActual;
                            cuerpo.innerHTML = `<p>¿Quieres usar el outfit <strong>${outfitIdActual}</strong> hoy?</p>`;
                            btnConfirmar.style.display = 'inline-block';
                            btnConfirmar.textContent = 'Usar ahora';
                            break;
                        case 'eliminar':
                            titulo.textContent = 'Eliminar Outfit #' + outfitIdActual;
                            cuerpo.innerHTML = `<p>¿Estás seguro que deseas eliminar el outfit <strong>${outfitIdActual}</strong>? Esta acción no se puede deshacer.</p>`;
                            btnConfirmar.style.display = 'inline-block';
                            btnConfirmar.textContent = 'Eliminar';
                            break;
                    }

                    modal.show();
                });
            });

            btnConfirmar.addEventListener('click', () => {
                if (!outfitIdActual || !accionActual) return;

                // Aquí colocas la lógica para confirmar la acción:
                if (accionActual === 'usar') {
                    // Lógica para registrar el uso del outfit y actualizar el estado de las prendas
                    fetch('registrar_uso_outfit.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `outfit_id=${outfitIdActual}` // Envía el ID del outfit
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Outfit Usado', data.message, 'success')
                                    .then(() => {
                                        modal.hide(); // Oculta el modal
                                        location.reload(); // Recarga la página para mostrar los cambios (ej. en el dashboard y el estado de prendas)
                                    });
                            } else {
                                Swal.fire('Error', data.message || 'Error desconocido al registrar el uso del outfit', 'error');
                                modal.hide();
                            }
                        })
                        .catch(error => {
                            console.error('Error en la solicitud AJAX de uso de outfit:', error);
                            Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor para registrar el uso del outfit.', 'error');
                            modal.hide();
                        });
                } else if (accionActual === 'eliminar') {
                    // Lógica para ELIMINAR el outfit en la base de datos
                    fetch('eliminar_outfit.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `outfit_id=${outfitIdActual}` // Envía el ID del outfit
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Eliminado', data.message, 'success')
                                    .then(() => {
                                        modal.hide(); // Oculta el modal
                                        location.reload(); // Recarga la página para mostrar los cambios
                                    });
                            } else {
                                Swal.fire('Error', data.message || 'Error desconocido al eliminar el outfit', 'error');
                                modal.hide();
                            }
                        })
                        .catch(error => {
                            console.error('Error en la solicitud AJAX de eliminación:', error);
                            Swal.fire('Error de conexión', 'No se pudo comunicar con el servidor para eliminar el outfit.', 'error');
                            modal.hide();
                        });
                }

                modal.hide();
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

        // Función para filtrar prendas en tiempo real
        function filtrarPrendas() {
            const searchTerm = document.getElementById('buscarPrendas').value.toLowerCase(); // Obtener el texto de búsqueda en minúsculas
            const prendaItems = document.querySelectorAll('.prenda-item'); // Seleccionar todas las tarjetas de prendas

            prendaItems.forEach(item => {
                const nombrePrenda = item.getAttribute('data-nombre-prenda'); // Obtener el nombre de la prenda del atributo data-

                if (nombrePrenda.includes(searchTerm)) {
                    item.style.display = ''; // Mostrar la tarjeta si el nombre coincide
                } else {
                    item.style.display = 'none'; // Ocultar la tarjeta si no coincide
                }
            });
        }

        const availableFilteredPrendas = <?php echo $json_prendas_para_sugerencia_ia; ?>;
        const tomorrowForecast = <?php echo $json_forecast_data; ?>; // NUEVA LÍNEA

        // Nueva función para generar el prompt para la IA
        // Nueva función para generar el prompt para la IA
        function generarPrompt() {
            // Obtener todos los valores seleccionados del selector múltiple de contexto
            // Reemplaza la obtención del contexto del select por la de los checkboxes
            const selectedContexts = Array.from(document.querySelectorAll('#formSugerencia input[name="contexto[]"]:checked'))
                .map(checkbox => checkbox.value);

            // Formatear los contextos para el prompt
            let contextoForIA = '';
            if (selectedContexts.length > 0) {
                // Unir los contextos con " y " para una frase más natural
                contextoForIA = selectedContexts.map(c => c.charAt(0).toUpperCase() + c.slice(1)).join(' y ');
            } else {
                // Si por alguna razón no se selecciona nada, dar un valor por defecto
                // Aunque el 'required' del HTML implica que al menos uno debe ser seleccionado.
                contextoForIA = 'General';
            }
            const espacio_mochila = document.querySelector('#formSugerencia select[name="espacio_mochila"]').value;
            const muda_extra = document.getElementById('mudaExtra').checked;

            let prendasListForIA = '';
            if (availableFilteredPrendas.length > 0) {
                prendasListForIA = '\n\nAquí tienes una lista de las prendas disponibles en mi clóset que tienen menos de 3 usos esta semana. Por favor, prioriza estas prendas en tu sugerencia:\n';
                availableFilteredPrendas.forEach(prenda => {
                    prendasListForIA += `- ${prenda.nombre} (${prenda.tipo}, ${prenda.color})\n`;
                });
                prendasListForIA += '\n';
            } else {
                prendasListForIA = '\n\nNo tengo prendas disponibles con menos de 3 usos esta semana. Por favor, sugiere un outfit general basado en las condiciones.\n';
            }

            let tomorrowForecastForIA = '';
            if (tomorrowForecast.length > 0) {
                tomorrowForecastForIA = '\nPronóstico del clima para mañana (<?= $fecha_manana ?>):\n';
                tomorrowForecast.forEach(forecast => {
                    if (forecast.time) { // Si hay datos para esta hora
                        tomorrowForecastForIA += `- ${forecast.label}: ${forecast.temp}°C, ${forecast.desc}\n`;
                    } else { // Si no se encontró el pronóstico para esta hora específica
                        tomorrowForecastForIA += `- ${forecast.label}: Clima no disponible\n`;
                    }
                });
                tomorrowForecastForIA;
            } else {
                tomorrowForecastForIA = '\n\nNo se pudo obtener el pronóstico del clima para mañana. Considera un clima general para la temporada.\n';
            }

            let prompt = `\nComo experto en moda y estilismo, necesito una sugerencia de outfit detallada para las siguientes condiciones. Me gustaría que la ropa sugerida sea cómoda, adecuada para los cambios de temperatura y el pronóstico detallado. Por favor, sé creativo y proporciona la información de forma estructurada para que pueda ser parseada fácilmente.

            Condiciones para el outfit:
            - Contexto: ${contextoForIA}
            - Espacio en mochila disponible: ${espacio_mochila.charAt(0).toUpperCase() + espacio_mochila.slice(1)}
            - ¿Posibilidad de llevar muda extra?: ${muda_extra ? 'Sí' : 'No'}
            ${tomorrowForecastForIA}
            ${prendasListForIA}

              Tu respuesta debe tener el siguiente formato JSON estricto: **un array de objetos, donde cada objeto representa un outfit sugerido.** Proporciona **3 ideas de outfit distintas** basadas en las condiciones dadas. Sin texto adicional antes ni después del JSON. Cada outfit en el array debe tener las propiedades "titulo", "descripcion", "prendas_sugeridas" (un array de strings concisos) y "tips_adicionales".

            [
              {
                "titulo": "Un título atractivo para el Outfit 1",
                "descripcion": "Una descripción breve y convincente del Outfit 1, explicando por qué es adecuado y cómo se adapta al clima y la comodidad.",
                "prendas_sugeridas": [
                  "Tipo de prenda 1 (ej. Camiseta térmica, Jeans con forro, Botas impermeables)",
                  "Tipo de prenda 2",
                  "Tipo de prenda 3"
                ],
                "tips_adicionales": "Un consejo de estilo o practicidad relacionado con el Outfit 1 (ej. 'No olvides una bufanda ligera para la mañana fría.')"
              },
              {
                "titulo": "Un título atractivo para el Outfit 2",
                "descripcion": "Una descripción breve y convincente del Outfit 2.",
                "prendas_sugeridas": [
                  "Prenda A", "Prenda B"
                ],
                "tips_adicionales": "Tip para Outfit 2."
              },
              {
                "titulo": "Un título atractivo para el Outfit 3",
                "descripcion": "Una descripción breve y convincente del Outfit 3.",
                "prendas_sugeridas": [
                  "Prenda X", "Prenda Y"
                ],
                "tips_adicionales": "Tip para Outfit 3."
              }
            ]`;


            const aiPromptElement = document.getElementById('aiPrompt');
            aiPromptElement.value = prompt;

            aiPromptElement.select();
            aiPromptElement.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(aiPromptElement.value);
                Swal.fire('¡Copiado!', 'El prompt ha sido copiado al portapapeles.', 'success');
            } catch (err) {
                console.error('No se pudo copiar el prompt al portapapeles:', err);
                Swal.fire('Error', 'No se pudo copiar el prompt automáticamente. Por favor, cópialo manualmente.', 'error');
            }
        }

        // Event listener para el botón de copiar prompt
        document.getElementById('copyPromptBtn').addEventListener('click', function() {
            const aiPromptElement = document.getElementById('aiPrompt');
            aiPromptElement.select();
            aiPromptElement.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(aiPromptElement.value);
                Swal.fire('¡Copiado!', 'El prompt ha sido copiado al portapapeles.', 'success');
            } catch (err) {
                console.error('No se pudo copiar el prompt al portapapeles:', err);
                Swal.fire('Error', 'No se pudo copiar el prompt automáticamente. Por favor, cópialo manualmente.', 'error');
            }
        });



        // Función para procesar la respuesta de la IA
        document.getElementById('processResponseBtn').addEventListener('click', function() {
            const aiResponseText = document.getElementById('aiResponse').value;
            const processedSuggestionDiv = document.getElementById('processedSuggestion');

            try {
                const suggestions = JSON.parse(aiResponseText); // Ahora esperamos un array

                if (!Array.isArray(suggestions) || suggestions.length === 0) {
                    throw new Error('La respuesta de la IA no es un array de outfits o está vacía.');
                }

                let allOutfitsHtml = '';
                suggestions.forEach((suggestion, index) => { // Iterar sobre cada sugerencia en el array
                    if (suggestion && suggestion.titulo && suggestion.descripcion && suggestion.prendas_sugeridas && Array.isArray(suggestion.prendas_sugeridas) && suggestion.tips_adicionales) {
                        let prendasListHtml = suggestion.prendas_sugeridas.map(prenda => `<li>${htmlspecialchars(prenda)}</li>`).join('');

                        // Construir el HTML para cada sugerencia de outfit
                        allOutfitsHtml += `
                            <div class="suggestion-card mb-4 p-3">
                                <h6>Idea de Outfit ${index + 1}: ${htmlspecialchars(suggestion.titulo)}</h6>
                                <p>${htmlspecialchars(suggestion.descripcion)}</p>
                                <div class="mb-2">
                                    <strong>Prendas sugeridas:</strong>
                                    <ul class="mb-2">
                                        ${prendasListHtml}
                                    </ul>
                                </div>
                                <div class="alert alert-light mb-2">
                                    <i class="fas fa-lightbulb me-2"></i><strong>Tip:</strong> ${htmlspecialchars(suggestion.tips_adicionales)}
                                </div>
                                <div class="text-center mt-3">
                                    <button class="btn btn-success btn-sm crear-outfit-sugerido"
                                            data-index="${index}"
                                            data-outfit-data='${JSON.stringify(suggestion)}'> <i class="fas fa-plus-circle me-2"></i>Crear este Outfit
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        allOutfitsHtml += `
                            <div class="alert alert-warning mb-4" role="alert">
                                <h4>Advertencia: Una de las ideas de outfit no tiene el formato correcto.</h4>
                                <p>Por favor, revisa el formato JSON de cada outfit dentro del array.</p>
                                <pre>${htmlspecialchars(JSON.stringify(suggestion, null, 2))}</pre>
                            </div>
                        `;
                    }
                });

                processedSuggestionDiv.innerHTML = allOutfitsHtml;
                Swal.fire('¡Sugerencias Listas!', 'La respuesta de la IA ha sido procesada. Se generaron varias ideas.', 'success');

                // --- NUEVA LÓGICA: Añadir event listeners a los nuevos botones "Crear este Outfit" ---
                document.querySelectorAll('.crear-outfit-sugerido').forEach(button => {
                    button.addEventListener('click', function() {
                        const outfitData = JSON.parse(this.dataset.outfitData); // Parsear los datos del outfit
                        createOutfitFromSuggestion(outfitData);
                    });
                });
                // --- FIN NUEVA LÓGICA ---

            } catch (e) {
                console.error('Error al procesar la respuesta de la IA:', e);
                processedSuggestionDiv.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <h4>Error al procesar la respuesta:</h4>
                        <p>${e.message}</p>
                        <p>Asegúrate de que la IA devuelva un **ARRAY de objetos JSON** exactamente como se pide en el prompt.</p>
                        <p>Respuesta recibida: <pre>${htmlspecialchars(aiResponseText)}</pre></p>
                    </div>
                `;
                Swal.fire('Error', 'No se pudo procesar la respuesta. Revisa el formato.', 'error');
            }
        });

        // Helper function para escapar HTML y evitar XSS al mostrar la respuesta
        function htmlspecialchars(str) {
            let div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // Función para crear un outfit en la DB a partir de una sugerencia de la IA
        async function createOutfitFromSuggestion(suggestion) {
            Swal.fire({
                title: 'Crear Outfit Sugerido',
                html: `¿Quieres crear el outfit "${htmlspecialchars(suggestion.titulo)}" en tu clóset?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745', // Color verde
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Sí, Crear Outfit'
            }).then(async (result) => { // Usamos 'async' aquí porque dentro usaremos 'await'
                if (result.isConfirmed) {
                    // --- MOVER LA DECLARACIÓN DE formData AQUÍ ---
                    const formData = new FormData(); // Declara formData al inicio del bloque isConfirmed

                    const outfitName = suggestion.titulo;
                    const outfitDescription = suggestion.descripcion;
                    // Note: outfitContext was previously a selector, let's make sure to get the actual selected values
                    const outfitContextSelect = document.querySelector('#formSugerencia select[name="contexto[]"]'); // If it's still a select
                    const outfitContextCheckboxes = document.querySelectorAll('#formSugerencia input[name="contexto[]"]:checked'); // If it's checkboxes

                    // Determine which 'contexts' to use based on your current HTML structure
                    let contextsToUse = [];
                    if (outfitContextCheckboxes.length > 0) { // If using checkboxes
                        contextsToUse = Array.from(outfitContextCheckboxes).map(cb => cb.value);
                    } else if (outfitContextSelect && outfitContextSelect.selectedOptions) { // If still using a multiple select
                        contextsToUse = Array.from(outfitContextSelect.selectedOptions).map(option => option.value);
                    }

                    const outfitComments = suggestion.tips_adicionales + "\n\n" + suggestion.descripcion;

                    // --- Mapear nombres de prendas sugeridas a IDs de prendas existentes ---
                    const selectedPrendaIds = [];
                    const unmatchedPrendas = [];

                    suggestion.prendas_sugeridas.forEach(aiSuggestedPrenda => {
                        const foundPrenda = availableFilteredPrendas.find(p =>
                            aiSuggestedPrenda.toLowerCase().includes(p.nombre.toLowerCase()) ||
                            aiSuggestedPrenda.toLowerCase().includes(p.tipo.toLowerCase())
                        );

                        if (foundPrenda) {
                            selectedPrendaIds.push(foundPrenda.id);
                        } else {
                            unmatchedPrendas.push(aiSuggestedPrenda);
                        }
                    });

                    if (selectedPrendaIds.length === 0) {
                        Swal.fire('Atención', 'No se pudieron encontrar prendas existentes en tu clóset para esta sugerencia. El outfit no se puede crear.', 'warning');
                        return;
                    }

                    // Append data to formData
                    formData.append('nombre', outfitName);
                    // Append only the first context, or 'General'
                    formData.append('contexto', contextsToUse.length > 0 ? contextsToUse[0] : 'General');
                    formData.append('clima_base', 'todo'); // Default or derived from forecast if needed
                    formData.append('comentarios', outfitComments);
                    selectedPrendaIds.forEach(id => {
                        formData.append('prendas[]', id);
                    });

                    // Mensaje de advertencia si hay prendas que no se encontraron
                    if (unmatchedPrendas.length > 0) {
                        const confirmProceed = await Swal.fire({
                            title: 'Algunas prendas no se encontraron',
                            html: `La IA sugirió las siguientes prendas que no se pudieron mapear a tu clóset: <strong>${unmatchedPrendas.join(', ')}</strong>.<br>¿Deseas crear el outfit solo con las prendas encontradas?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, crear de todos modos',
                            cancelButtonText: 'No, cancelar'
                        });
                        if (!confirmProceed.isConfirmed) {
                            return;
                        }
                    }

                    // Enviar los datos al backend
                    fetch('crear_outfit_desde_sugerencia.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Outfit Creado!', data.message, 'success')
                                    .then(() => {
                                        location.reload();
                                    });
                            } else {
                                Swal.fire('Error', data.message || 'Error desconocido al crear el outfit.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error al enviar la solicitud:', error);
                            Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor para crear el outfit.', 'error');
                        });
                }
            });
        }// Función para crear un outfit en la DB a partir de una sugerencia de la IA
        async function createOutfitFromSuggestion(suggestion) {
            Swal.fire({
                title: 'Crear Outfit Sugerido',
                html: `¿Quieres crear el outfit "${htmlspecialchars(suggestion.titulo)}" en tu clóset?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745', // Color verde
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Sí, Crear Outfit'
            }).then(async (result) => { // Usamos 'async' aquí porque dentro usaremos 'await'
                if (result.isConfirmed) {
                    // --- MOVER LA DECLARACIÓN DE formData AQUÍ ---
                    const formData = new FormData(); // Declara formData al inicio del bloque isConfirmed

                    const outfitName = suggestion.titulo;
                    const outfitDescription = suggestion.descripcion;
                    // Note: outfitContext was previously a selector, let's make sure to get the actual selected values
                    const outfitContextSelect = document.querySelector('#formSugerencia select[name="contexto[]"]'); // If it's still a select
                    const outfitContextCheckboxes = document.querySelectorAll('#formSugerencia input[name="contexto[]"]:checked'); // If it's checkboxes

                    // Determine which 'contexts' to use based on your current HTML structure
                    let contextsToUse = [];
                    if (outfitContextCheckboxes.length > 0) { // If using checkboxes
                        contextsToUse = Array.from(outfitContextCheckboxes).map(cb => cb.value);
                    } else if (outfitContextSelect && outfitContextSelect.selectedOptions) { // If still using a multiple select
                        contextsToUse = Array.from(outfitContextSelect.selectedOptions).map(option => option.value);
                    }
                    
                    const outfitComments = suggestion.tips_adicionales + "\n\n" + suggestion.descripcion;

                    // --- Mapear nombres de prendas sugeridas a IDs de prendas existentes ---
                    const selectedPrendaIds = [];
                    const unmatchedPrendas = [];

                    suggestion.prendas_sugeridas.forEach(aiSuggestedPrenda => {
                        const foundPrenda = availableFilteredPrendas.find(p =>
                            aiSuggestedPrenda.toLowerCase().includes(p.nombre.toLowerCase()) ||
                            aiSuggestedPrenda.toLowerCase().includes(p.tipo.toLowerCase())
                        );

                        if (foundPrenda) {
                            selectedPrendaIds.push(foundPrenda.id);
                        } else {
                            unmatchedPrendas.push(aiSuggestedPrenda);
                        }
                    });

                    if (selectedPrendaIds.length === 0) {
                        Swal.fire('Atención', 'No se pudieron encontrar prendas existentes en tu clóset para esta sugerencia. El outfit no se puede crear.', 'warning');
                        return;
                    }

                    // Append data to formData
                    formData.append('nombre', outfitName);
                    // Append only the first context, or 'General'
                    formData.append('contexto', contextsToUse.length > 0 ? contextsToUse[0] : 'General');
                    formData.append('clima_base', 'todo'); // Default or derived from forecast if needed
                    formData.append('comentarios', outfitComments);
                    selectedPrendaIds.forEach(id => {
                        formData.append('prendas[]', id);
                    });

                    // Mensaje de advertencia si hay prendas que no se encontraron
                    if (unmatchedPrendas.length > 0) {
                        const confirmProceed = await Swal.fire({
                            title: 'Algunas prendas no se encontraron',
                            html: `La IA sugirió las siguientes prendas que no se pudieron mapear a tu clóset: <strong>${unmatchedPrendas.join(', ')}</strong>.<br>¿Deseas crear el outfit solo con las prendas encontradas?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, crear de todos modos',
                            cancelButtonText: 'No, cancelar'
                        });
                        if (!confirmProceed.isConfirmed) {
                            return;
                        }
                    }

                    // Enviar los datos al backend
                    fetch('crear_outfit_desde_sugerencia.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Outfit Creado!', data.message, 'success')
                                .then(() => {
                                    location.reload();
                                });
                        } else {
                            Swal.fire('Error', data.message || 'Error desconocido al crear el outfit.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error al enviar la solicitud:', error);
                        Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor para crear el outfit.', 'error');
                    });
                }
            });
        }
    </script>
</body>

</html>