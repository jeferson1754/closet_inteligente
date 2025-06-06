<?php
include 'bd.php'; // conexión a la base de datos

// Consulta para obtener prendas disponibles
$sql = "SELECT id,nombre,tipo, color_principal,tela,textura,estampado, clima_apropiado,formalidad,estado,foto FROM prendas";
$result = $mysqli_obj->query($sql);

$prendas = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Convertir null en null real para JSON (si es necesario)
        $row['foto'] = $row['foto'] ?? null;
        $prendas[] = $row;
    }
} else {
    die("Error en la consulta: " . $mysqli_obj->error);
}

// Consulta para obtener prendas disponibles
$sql2 = "SELECT * FROM `historial_usos`";
$result2 = $mysqli_obj->query($sql2);

$historial = [];

if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        // Convertir null en null real para JSON (si es necesario)
        $historial[] = $row;
    }
} else {
    die("Error en la consulta: " . $mysqli_obj->error);
}

// Consulta para obtener prendas disponibles
$sql2 = "SELECT * FROM `historial_usos`";
$result2 = $mysqli_obj->query($sql2);

$historial = [];

if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        // Convertir null en null real para JSON (si es necesario)
        $historial[] = $row;
    }
} else {
    die("Error en la consulta: " . $mysqli_obj->error);
}



$city = $_POST['city'] ?? 'Santiago';
$api_key = "54f6b317a9161bd48049210b917f2329";
$api_url = "http://api.weatherstack.com/current?access_key=$api_key&query=" . urlencode($city);


// Traducciones del clima
$translations = [
    'Sunny' => 'Soleado',
    'Partly cloudy' => 'Parcialmente nublado',
    'Cloudy' => 'Nublado',
    'Rainy' => 'Lluvia',
    'Windy' => 'Viento',
    'Clear' => 'Despejado',
    'Overcast' => 'Cubierto',
    'Showers' => 'Chubascos',
    'Thunderstorm' => 'Tormenta eléctrica',
    'Mist' => 'Niebla',
    'Fog' => 'Niebla espesa',
    'Snow' => 'Nieve',
    'Hail' => 'Granizo',
    'Patches Of Fog' => 'Parche de niebla',
    'Blizzard' => 'Tormenta de nieve',
    'Sleet' => 'Aguacero congelado',
    'Drizzle' => 'Llovizna',
    'Freezing rain' => 'Lluvia helada',
    'Tornado' => 'Tornado',
    'Hurricane' => 'Huracán',
    'Drought' => 'Sequía',
    'Dust' => 'Polvo',
    'Sandstorm' => 'Tormenta de arena',
    'Ice' => 'Hielo',
    'Squall' => 'Ráfaga',
    'Tropical storm' => 'Tormenta tropical',
    'Cold' => 'Frío',
    'Hot' => 'Caluroso',
    'Storm' => 'Tormenta',
    'Heatwave' => 'Ola de calor',
    'Light Snow' => 'Nieve ligera',
    'Light Rain, Mist' => 'Lluvia ligera, niebla',
    'Light Rain' => 'Lluvia ligera',
    'Light Rain Shower' => 'Lluvia ligera',
    'Haze' => 'Bruma'
];

// Petición a la API
$response = @file_get_contents($api_url);
if (!$response) {
    echo "<div class='alert alert-danger'>No se pudo obtener el clima actual.</div>";
    exit;
}

$data = json_decode($response, true);
if (!isset($data['current']['weather_descriptions'][0])) {
    echo "<div class='alert alert-warning'>No se encontró información meteorológica.</div>";
    exit;
}

// Clima actual
$weather_api_term = $data['current']['weather_descriptions'][0] ?? 'No disponible';
$weather_desc = $translations[$weather_api_term] ?? $weather_api_term;
$temp_c = $data['current']['temperature'] ?? 'N/A';
$icon_url = $data['current']['weather_icons'][0] ?? '';

// Clasificación del clima para filtrado de ropa
$clima_categoria = '';
$desc_minuscula = strtolower($weather_desc);

if (strpos($desc_minuscula, 'lluvia') !== false) {
    $clima_categoria = 'lluvia';
} else {
    if ($temp_c < 20) {
        $clima_categoria = 'frio';
    } else {
        $clima_categoria = 'calor';
    }
}

$sql = "SELECT * FROM prendas WHERE estado='disponible' AND clima_apropiado IN (?,'todo') ";
$stmt = $mysqli_obj->prepare($sql);

if ($stmt === false) {
    die("Error en la preparación de la consulta: " . $mysqli_obj->error);
}

$stmt->bind_param("s", $clima_categoria);
$stmt->execute();
$result = $stmt->get_result();

$prendas_sugeridas = [
    'parte_superior' => [],
    'parte_inferior' => [],
    'calzado' => [],
    'accesorio' => [],
];

while ($row = $result->fetch_assoc()) {
    $nombre = strtolower($row['nombre']);

    if (str_contains($nombre, 'jeans') || str_contains($nombre, 'pantalón') || str_contains($nombre, 'falda') || str_contains($nombre, 'short')) {
        $prendas_sugeridas['parte_inferior'][] = $row['nombre'];
    } elseif (str_contains($nombre, 'zapatilla') || str_contains($nombre, 'zapato') || str_contains($nombre, 'bota') || str_contains($nombre, 'sandalia')) {
        $prendas_sugeridas['calzado'][] = $row['nombre'];
    } elseif (str_contains($nombre, 'polera') || str_contains($nombre, 'camisa') || str_contains($nombre, 'blusa') || str_contains($nombre, 'chaqueta') || str_contains($nombre, 'abrigo')) {
        $prendas_sugeridas['parte_superior'][] = $row['nombre'];
    } else {
        $prendas_sugeridas['accesorio'][] = $row['nombre'];
    }
}

// Seleccionar una prenda de cada tipo (si hay)
$sugerencia = [];
foreach ($prendas_sugeridas as $tipo => $items) {
    if (!empty($items)) {
        $sugerencia[$tipo] = $items[array_rand($items)];
    }
}

// Supongamos que ya obtuviste las prendas según el clima y disponibilidad
$sugerencias = [];

if (!empty($sugerencia)) {
    $sugerencias[] = [
        "titulo" => "Outfit sugerido según clima",
        "descripcion" => "Basado en tus prendas disponibles y el clima actual",
        "prendas" => array_values($sugerencia),
        "tips" => "Recuerda revisar el clima antes de salir"
    ];
}


?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clóset Inteligente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1200px;
            overflow: hidden;
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

        .card:hover {
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
                                    <div id="listaPrendas" class="row">
                                        <!-- Las prendas se cargarán aquí dinámicamente -->
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
                                            <div id="selectorPrendas" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                <!-- Las prendas disponibles se cargarán aquí -->
                                            </div>
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
                                    <div class="loading" id="loadingOutfits">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                                        <p>Cargando outfits...</p>
                                    </div>
                                    <div id="listaOutfits" class="row">
                                        <!-- Los outfits se cargarán aquí dinámicamente -->
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
                                    <div class="loading" id="loadingSugerencias">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                                        <p>Generando sugerencias...</p>
                                    </div>
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

    <!-- Modales -->
    <div class="modal fade" id="modalEditarPrenda" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Prenda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarPrenda">
                        <input type="hidden" name="id">
                        <!-- Campos similares al formulario de agregar -->
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="disponible">Disponible</option>
                                <option value="sucio">Sucio</option>
                                <option value="prestado">Prestado</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEdicionPrenda()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <?php
    echo "<script>const sugerenciasDesdePHP = " . json_encode($sugerencias, JSON_UNESCAPED_UNICODE) . ";</script>"; ?>

    <script>
        // Simulación de base de datos en memoria
        const prendas = <?php echo json_encode($prendas, JSON_UNESCAPED_UNICODE); ?>;
        let outfits = [];
        let historialUsos = <?php echo json_encode($historial, JSON_UNESCAPED_UNICODE); ?>;
        let idCounter = 1;


        // Inicializar aplicación
        document.addEventListener('DOMContentLoaded', function() {
            actualizarDashboard();
            cargarPrendas();
            cargarOutfits();
            cargarSelectorPrendas();
        });


        function actualizarDashboard() {
            document.getElementById('totalPrendas').textContent = prendas.length;
            document.getElementById('totalOutfits').textContent = outfits.length;
            document.getElementById('prendasDisponibles').textContent =
                prendas.filter(p => p.estado === 'disponible').length;
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

        // Agregar prenda
        document.getElementById('formPrenda').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const prenda = {
                id: idCounter++,
                nombre: formData.get('nombre'),
                tipo: formData.get('tipo'),
                color_principal: formData.get('color_principal'),
                tela: formData.get('tela'),
                textura: formData.get('textura'),
                estampado: formData.get('estampado'),
                clima_apropiado: formData.get('clima_apropiado'),
                formalidad: formData.get('formalidad'),
                estado: 'disponible',
                foto: null // En implementación real, manejar upload de imagen
            };

            prendas.push(prenda);
            actualizarDashboard();
            cargarPrendas();
            cargarSelectorPrendas();
            e.target.reset();
            document.getElementById('previewPrenda').style.display = 'none';

            // Mostrar mensaje de éxito
            mostrarAlerta('Prenda agregada exitosamente', 'success');
        });

        function cargarPrendas() {
            const lista = document.getElementById('listaPrendas');
            lista.innerHTML = '';

            prendas.forEach(prenda => {
                const prendaCard = `
                    <div class="col-md-6 mb-3">
                        <div class="card prenda-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">${prenda.nombre}</h6>
                                        <p class="card-text">
                                            <small class="text-muted">${prenda.tipo} • ${prenda.color_principal}</small><br>
                                            <small>${prenda.tela} • ${prenda.textura}</small>
                                        </p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editarPrenda(${prenda.id})">
                                                <i class="fas fa-edit me-2"></i>Editar
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="eliminarPrenda(${prenda.id})">
                                                <i class="fas fa-trash me-2"></i>Eliminar
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="marcarUsoPrenda(${prenda.id})">
                                                <i class="fas fa-check me-2"></i>Marcar como usado
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge clima-${prenda.clima_apropiado}">${prenda.clima_apropiado}</span>
                                    <span class="badge formalidad-${prenda.formalidad}">${prenda.formalidad}</span>
                                    <span class="badge bg-${prenda.estado === 'disponible' ? 'success' : 'warning'}">${prenda.estado}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                lista.innerHTML += prendaCard;
            });
        }

        function cargarSelectorPrendas() {
            const selector = document.getElementById('selectorPrendas');
            selector.innerHTML = '';

            const prendasDisponibles = prendas.filter(p => p.estado === 'disponible');

            if (prendasDisponibles.length === 0) {
                selector.innerHTML = '<p class="text-muted">No hay prendas disponibles</p>';
                return;
            }

            prendasDisponibles.forEach(prenda => {
                const checkbox = `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="${prenda.id}" id="prenda${prenda.id}">
                        <label class="form-check-label" for="prenda${prenda.id}">
                            <strong>${prenda.nombre}</strong><br>
                            <small class="text-muted">${prenda.tipo} • ${prenda.color_principal}</small>
                        </label>
                    </div>
                `;
                selector.innerHTML += checkbox;
            });
        }

        function cargarOutfits() {
            const lista = document.getElementById('listaOutfits');
            lista.innerHTML = '';

            if (outfits.length === 0) {
                lista.innerHTML = '<div class="col-12"><p class="text-muted text-center">No hay outfits creados aún</p></div>';
                return;
            }

            outfits.forEach(outfit => {
                const outfitCard = `
                    <div class="col-md-6 mb-3">
                        <div class="card outfit-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">${outfit.nombre}</h6>
                                        <p class="card-text">
                                            <small class="text-muted">${outfit.contexto} • ${outfit.clima_base}</small><br>
                                            <small>${outfit.prendas.length} prendas</small>
                                        </p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="verOutfit(${outfit.id})">
                                                <i class="fas fa-eye me-2"></i>Ver detalles
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="usarOutfit(${outfit.id})">
                                                <i class="fas fa-play me-2"></i>Usar hoy
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="eliminarOutfit(${outfit.id})">
                                                <i class="fas fa-trash me-2"></i>Eliminar
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-primary">${outfit.contexto}</span>
                                    <span class="badge clima-${outfit.clima_base}">${outfit.clima_base}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                lista.innerHTML += outfitCard;
            });
        }

        // Crear outfit
        document.getElementById('formOutfit').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const prendasSeleccionadas = Array.from(document.querySelectorAll('#selectorPrendas input:checked'))
                .map(input => parseInt(input.value));

            if (prendasSeleccionadas.length === 0) {
                mostrarAlerta('Debes seleccionar al menos una prenda', 'warning');
                return;
            }

            const outfit = {
                id: idCounter++,
                nombre: formData.get('nombre'),
                contexto: formData.get('contexto'),
                clima_base: formData.get('clima_base'),
                prendas: prendasSeleccionadas,
                fecha_creado: new Date()
            };

            outfits.push(outfit);
            actualizarDashboard();
            cargarOutfits();
            e.target.reset();
            document.querySelectorAll('#selectorPrendas input:checked').forEach(input => {
                input.checked = false;
            });

            mostrarAlerta('Outfit creado exitosamente', 'success');
        });

        function filtrarPrendas() {
            const busqueda = document.getElementById('buscarPrendas').value.toLowerCase();
            const tarjetas = document.querySelectorAll('#listaPrendas .col-md-6');

            tarjetas.forEach(tarjeta => {
                const texto = tarjeta.textContent.toLowerCase();
                tarjeta.style.display = texto.includes(busqueda) ? 'block' : 'none';
            });
        }

        function editarPrenda(id) {
            const prenda = prendas.find(p => p.id === id);
            if (prenda) {
                // Llenar formulario de edición
                const form = document.getElementById('formEditarPrenda');
                form.querySelector('[name="id"]').value = prenda.id;
                form.querySelector('[name="nombre"]').value = prenda.nombre;
                form.querySelector('[name="estado"]').value = prenda.estado;

                const modal = new bootstrap.Modal(document.getElementById('modalEditarPrenda'));
                modal.show();
            }
        }

        function guardarEdicionPrenda() {
            const form = document.getElementById('formEditarPrenda');
            const formData = new FormData(form);
            const id = parseInt(formData.get('id'));

            const prenda = prendas.find(p => p.id === id);
            if (prenda) {
                prenda.nombre = formData.get('nombre');
                prenda.estado = formData.get('estado');

                cargarPrendas();
                actualizarDashboard();

                const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarPrenda'));
                modal.hide();

                mostrarAlerta('Prenda actualizada exitosamente', 'success');
            }
        }

        function eliminarPrenda(id) {
            if (confirm('¿Estás seguro de que quieres eliminar esta prenda?')) {
                prendas = prendas.filter(p => p.id !== id);
                cargarPrendas();
                cargarSelectorPrendas();
                actualizarDashboard();
                mostrarAlerta('Prenda eliminada exitosamente', 'success');
            }
        }

        function marcarUsoPrenda(id) {
            const hoy = new Date().toISOString().split('T')[0];
            historialUsos.push({
                id: idCounter++,
                fecha: hoy,
                prenda_id: id,
                contexto: 'universidad', // Por defecto
                clima: 'todo'
            });
            mostrarAlerta('Uso registrado exitosamente', 'success');
        }

        function eliminarOutfit(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este outfit?')) {
                outfits = outfits.filter(o => o.id !== id);
                cargarOutfits();
                actualizarDashboard();
                mostrarAlerta('Outfit eliminado exitosamente', 'success');
            }
        }

        function usarOutfit(id) {
            const outfit = outfits.find(o => o.id === id);
            if (outfit) {
                const hoy = new Date().toISOString().split('T')[0];
                outfit.prendas.forEach(prendaId => {
                    historialUsos.push({
                        id: idCounter++,
                        fecha: hoy,
                        prenda_id: prendaId,
                        contexto: outfit.contexto,
                        clima: outfit.clima_base
                    });
                });
                mostrarAlerta(`Outfit "${outfit.nombre}" usado exitosamente`, 'success');
            }
        }

        function verOutfit(id) {
            const outfit = outfits.find(o => o.id === id);
            if (outfit) {
                const prendasOutfit = prendas.filter(p => outfit.prendas.includes(p.id));
                let detalles = `<h6>Outfit: ${outfit.nombre}</h6>`;
                detalles += `<p><strong>Contexto:</strong> ${outfit.contexto}</p>`;
                detalles += `<p><strong>Clima:</strong> ${outfit.clima_base}</p>`;
                detalles += '<p><strong>Prendas:</strong></p><ul>';

                prendasOutfit.forEach(prenda => {
                    detalles += `<li>${prenda.nombre} (${prenda.tipo})</li>`;
                });
                detalles += '</ul>';

                mostrarAlerta(detalles, 'info', 'Detalles del Outfit');
            }
        }

        function generarSugerencia() {
            // Validación robusta de sugerenciasDesdePHP
            if (
                typeof sugerenciasDesdePHP === 'undefined' ||
                !Array.isArray(sugerenciasDesdePHP) ||
                sugerenciasDesdePHP.length === 0
            ) {
                console.warn('sugerenciasDesdePHP no está definido o está vacío:', sugerenciasDesdePHP);
                document.getElementById('sugerenciaDia').innerHTML = '<p class="text-muted">No hay sugerencias disponibles</p>';
                return;
            }

            console.log('Sugerencias disponibles:', sugerenciasDesdePHP);

            const sugerenciaAleatoria = sugerenciasDesdePHP[Math.floor(Math.random() * sugerenciasDesdePHP.length)];

            if (!sugerenciaAleatoria || typeof sugerenciaAleatoria !== 'object') {
                console.error('Sugerencia inválida:', sugerenciaAleatoria);
                document.getElementById('sugerenciaDia').innerHTML = '<p class="text-muted">Error al generar sugerencia</p>';
                return;
            }

            const html = `
        <div class="suggestion-card">
            <h6>${sugerenciaAleatoria.titulo || 'Sugerencia del día'}</h6>
            <p>${sugerenciaAleatoria.descripcion || 'No hay descripción disponible.'}</p>
            <div class="mb-2">
                <strong>Prendas sugeridas:</strong>
                <ul class="mb-2">
                    ${(sugerenciaAleatoria.prendas || []).map(prenda => `<li>${prenda.trim()}</li>`).join('')}
                </ul>
            </div>
            <div class="alert alert-light mb-0">
                <i class="fas fa-lightbulb me-2"></i><strong>Tip:</strong> ${sugerenciaAleatoria.tips || 'No hay tip disponible'}
            </div>
        </div>
    `;

            document.getElementById('sugerenciaDia').innerHTML = html;
        }

        function generarSugerenciaPersonalizada() {
            const form = document.getElementById('formSugerencia');
            const formData = new FormData(form);
            const contexto = formData.get('contexto');
            const clima = formData.get('clima');
            const espacioMochila = formData.get('espacio_mochila');
            const mudaExtra = formData.get('muda_extra') === 'on';

            document.getElementById('loadingSugerencias').classList.add('show');

            // Simular procesamiento
            setTimeout(() => {
                const prendasCompatibles = prendas.filter(prenda => {
                    const climaCompatible = prenda.clima_apropiado === clima || prenda.clima_apropiado === 'todo';
                    const formalidadCompatible =
                        (contexto === 'trabajo' && (prenda.formalidad === 'formal' || prenda.formalidad === 'semi-formal')) ||
                        (contexto === 'universidad' && prenda.formalidad !== 'formal') ||
                        (contexto !== 'trabajo' && contexto !== 'universidad');

                    return climaCompatible && formalidadCompatible && prenda.estado === 'disponible';
                });

                let sugerenciaHtml = '';

                if (prendasCompatibles.length === 0) {
                    sugerenciaHtml = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No se encontraron prendas compatibles con tus criterios.
                        </div>
                    `;
                } else {
                    // Agrupar por tipo
                    const tipos = {};
                    prendasCompatibles.forEach(prenda => {
                        if (!tipos[prenda.tipo]) tipos[prenda.tipo] = [];
                        tipos[prenda.tipo].push(prenda);
                    });

                    sugerenciaHtml = `
                        <div class="suggestion-card">
                            <h6><i class="fas fa-magic me-2"></i>Sugerencia Personalizada</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Contexto:</strong> ${contexto}</p>
                                    <p><strong>Clima:</strong> ${clima}</p>
                                    <p><strong>Espacio:</strong> ${espacioMochila}</p>
                                    ${mudaExtra ? '<p><span class="badge bg-success">Con muda extra</span></p>' : ''}
                                </div>
                                <div class="col-md-6">
                                    <h6>Prendas recomendadas:</h6>
                                    <div class="recommendations">
                    `;

                    Object.keys(tipos).forEach(tipo => {
                        const prendaTipo = tipos[tipo][Math.floor(Math.random() * tipos[tipo].length)];
                        sugerenciaHtml += `
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                <div>
                                    <strong>${prendaTipo.nombre}</strong><br>
                                    <small class="text-muted">${prendaTipo.color_principal} • ${prendaTipo.tela}</small>
                                </div>
                                <span class="badge bg-primary">${prendaTipo.tipo}</span>
                            </div>
                        `;
                    });

                    sugerenciaHtml += `
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Consejos:</strong>
                                    ${espacioMochila === 'limitado' ? 'Elige prendas que no se arruguen fácilmente. ' : ''}
                                    ${clima === 'lluvia' ? 'No olvides llevar paraguas o impermeable. ' : ''}
                                    ${mudaExtra ? 'Puedes ser más arriesgado con los colores sabiendo que tienes repuesto. ' : ''}
                                    ${contexto === 'trabajo' ? 'Mantén un estilo profesional y conservador.' : ''}
                                </div>
                            </div>
                            <div class="mt-3 text-center">
                                <button class="btn btn-outline-light btn-sm me-2" onclick="guardarSugerenciaComoOutfit()">
                                    <i class="fas fa-save me-1"></i>Guardar como Outfit
                                </button>
                                <button class="btn btn-outline-light btn-sm" onclick="generarSugerenciaPersonalizada()">
                                    <i class="fas fa-refresh me-1"></i>Nueva Sugerencia
                                </button>
                            </div>
                        </div>
                    `;
                }

                document.getElementById('loadingSugerencias').classList.remove('show');
                document.getElementById('sugerenciasPersonalizadas').innerHTML = sugerenciaHtml;
            }, 1500);
        }

        function guardarSugerenciaComoOutfit() {
            const nombre = prompt('Nombre para este outfit:');
            if (nombre) {
                mostrarAlerta('Outfit guardado exitosamente', 'success');
            }
        }

        function mostrarAlerta(mensaje, tipo = 'info', titulo = '') {
            // Crear elemento de alerta temporal
            const alertContainer = document.createElement('div');
            alertContainer.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
            alertContainer.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';

            alertContainer.innerHTML = `
                ${titulo ? `<h6>${titulo}</h6>` : ''}
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertContainer);

            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (alertContainer.parentNode) {
                    alertContainer.parentNode.removeChild(alertContainer);
                }
            }, 5000);
        }

        // Inicializar tooltips y popovers de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>