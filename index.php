<?php
include 'bd.php'; // conexión a la base de datos

// --- NUEVO: Disparador para actualizar estados diarios ---
// Lógica para ejecutar actualizar_estados_diarios.php una vez al día
session_start(); // Asegúrate de iniciar la sesión si aún no lo haces

$last_update_date = $_SESSION['last_daily_status_update'] ?? null;
$today_date_str = date('Y-m-d');

if ($last_update_date !== $today_date_str) {
    // Si la última actualización no fue hoy, ejecutar el script de actualización
    // Usar file_get_contents o cURL para una llamada HTTP interna
    // Es preferible usar cURL o una función que no dependa de allow_url_fopen
    $update_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/actualizar_estados_diarios.php";

    // Puedes hacer la llamada silenciosa con cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $update_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // No recomendado para producción sin configurar CA certs
    curl_exec($ch); // Ejecutar la llamada (ignorar la respuesta)
    curl_close($ch);

    $_SESSION['last_daily_status_update'] = $today_date_str; // Marcar como actualizado para hoy
    // Opcional: Loggear si la actualización fue exitosa/fallida
    // error_log("Estado diario de prendas actualizado en: " . $today_date_str);
}
// --- FIN: Disparador ---


// Consulta para obtener prendas disponibles
$sql = "SELECT * FROM prendas ORDER BY FIELD(estado, 'en uso', 'disponible', 'sucio', 'lavando'), `prendas`.`usos_esta_semana` DESC, `prendas`.`fecha_agregado` DESC;";
$result = $mysqli_obj->query($sql);
$result_prendas = $mysqli_obj->query($sql);

$result2 = $mysqli_obj->query($sql);
$result_prendas2 = $mysqli_obj->query($sql);


if ($result_prendas2) {
    while ($prenda_total = $result_prendas2->fetch_assoc()) {
        $prenda_id = $prenda_total['id'];
        $usos = $prenda_total['usos_esta_semana'];
        $prenda_tipo = $prenda_total['tipo']; // Use original case or convert to lower as needed by the function
        $es_uso_ilimitado = $prenda_total['uso_ilimitado'];

        // Use the new function to get usage status
        $usageStatus = getUsageLimitStatus($prenda_tipo, $usos);
        $max_usos_permitidos = $usageStatus['max_uses'];
        $isOverused = $usageStatus['is_overused'];

        // Condition for filtering: if it's unlimited use OR it's not overused
        if ($es_uso_ilimitado || !$isOverused) { // Use the boolean result from the function
            $prendas_para_sugerencia_compras[] = [
                'id' => $prenda_total['id'],
                'nombre' => $prenda_total['nombre'],
                'tipo' => $prenda_total['tipo'],
                'color' => $prenda_total['color_principal'],
                'usos_esta_semana' => $usos,
                'comentarios' => $prenda_total['detalles_adicionales'] ?? '',
                'uso_ilimitado' => $prenda_total
            ];
        }
    }
}

// Convertir el array PHP a JSON para pasarlo a JavaScript
$json_prendas_para_sugerencia_compras = json_encode($prendas_para_sugerencia_compras);


// Consulta para obtener prendas disponibles y con menos de 3 usos esta semana
$prendas_para_sugerencia_ia = [];



// Consulta para obtener prendas disponibles
$sql_prendas_disponibles = "SELECT * FROM prendas WHERE estado = 'disponible' OR uso_ilimitado = TRUE ORDER BY `prendas`.`nombre` ASC"; // AÑADIR uso_ilimitado
$result_disponibles = $mysqli_obj->query($sql_prendas_disponibles);

if ($result_disponibles) {
    while ($prenda_disp = $result_disponibles->fetch_assoc()) {
        $prenda_id = $prenda_disp['id'];
        $usos = $prenda_disp['usos_esta_semana'];
        $prenda_tipo = $prenda_disp['tipo']; // Use original case or convert to lower as needed by the function
        $es_uso_ilimitado = $prenda_disp['uso_ilimitado'];

        // Use the new function to get usage status
        $usageStatus = getUsageLimitStatus($prenda_tipo, $usos);
        $max_usos_permitidos = $usageStatus['max_uses'];
        $isOverused = $usageStatus['is_overused'];

        // Condition for filtering: if it's unlimited use OR it's not overused
        if ($es_uso_ilimitado || !$isOverused) { // Use the boolean result from the function
            $prendas_para_sugerencia_ia[] = [
                'id' => $prenda_disp['id'],
                'nombre' => $prenda_disp['nombre'],
                'tipo' => $prenda_disp['tipo'],
                'color' => $prenda_disp['color_principal'],
                'usos_esta_semana' => $usos,
                'comentarios' => $prenda_disp['detalles_adicionales'] ?? '',
                'uso_ilimitado' => $es_uso_ilimitado
            ];
        }
    }
}

// Convertir el array PHP a JSON para pasarlo a JavaScript
$json_prendas_para_sugerencia_ia = json_encode($prendas_para_sugerencia_ia);


// --- INICIO: Consulta para obtener el historial de outfits usados ---
$outfits_usados_history = [];

// Obtener los últimos outfits usados (ej. los últimos 5, puedes ajustar el LIMIT)
$sql_history = "
    SELECT
        o.id,
        o.nombre,
        o.comentarios,
        o.fecha_ultimo_uso_outfit, -- NUEVO: Seleccionar la fecha de último uso
        GROUP_CONCAT(p.nombre ORDER BY p.nombre ASC SEPARATOR ', ') AS prendas_nombres
    FROM
        outfits o
    JOIN
        outfit_prendas op ON o.id = op.outfit_id
    JOIN
        prendas p ON op.prenda_id = p.id
    GROUP BY
        o.id, o.nombre, o.comentarios
    ORDER BY
        MAX(o.fecha_creado) DESC -- Ordenar por la fecha de uso más reciente del outfit
    LIMIT 5;
";

$result_history = $mysqli_obj->query($sql_history);

if ($result_history) {
    while ($row = $result_history->fetch_assoc()) {
        $outfits_usados_history[] = [
            'nombre' => $row['nombre'],
            'prendas' => $row['prendas_nombres'], // Esto será un string con nombres separados por coma
            'comentarios' => $row['comentarios'],
            'fecha_ultimo_uso' => $row['fecha_ultimo_uso_outfit'] ?? '' // Añadir la fecha para referencia en el prompt
        ];
    }
}
$json_outfits_usados_history = json_encode($outfits_usados_history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
// --- FIN: Consulta para obtener el historial de outfits usados ---


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
    // ... (código existente hasta $data_forecast) ...

    if ($data_forecast && !empty($data_forecast['list'])) {
        date_default_timezone_set('America/Santiago');
        $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

        $forecast_data = [];
        $target_schedule_hours_24h = ['05', '17', '22']; // Horas objetivo en formato 24h

        // Obtener todas las entradas del pronóstico para mañana
        $tomorrow_forecast_items = array_filter($data_forecast['list'], function ($item) use ($tomorrow) {
            $dt = new DateTime($item['dt_txt'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Santiago'));
            return $dt->format('Y-m-d') === $tomorrow;
        });

        foreach ($target_schedule_hours_24h as $target_hour_24) {
            $closest_temps = [];
            $main_desc = 'No disponible';
            $main_entry_for_desc = null;
            $target_dt_obj = (new DateTime($tomorrow . ' ' . $target_hour_24 . ':00', new DateTimeZone('America/Santiago')));
            $target_dt_timestamp = $target_dt_obj->getTimestamp();

            // Buscar la entrada más cercana para la descripción
            $min_time_diff = PHP_INT_MAX;
            foreach ($tomorrow_forecast_items as $item) {
                $item_dt = (new DateTime($item['dt_txt'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Santiago'));
                $time_diff = abs($item_dt->getTimestamp() - $target_dt_timestamp);
                if ($time_diff < $min_time_diff) {
                    $min_time_diff = $time_diff;
                    $main_entry_for_desc = $item;
                }
            }
            if ($main_entry_for_desc) {
                $main_desc = $main_entry_for_desc['weather'][0]['description'] ?? 'No disponible';
            }

            // Buscar temperaturas en un rango de +/- 3 horas de la hora objetivo
            foreach ($tomorrow_forecast_items as $item) {
                $item_dt = (new DateTime($item['dt_txt'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Santiago'));
                $item_timestamp = $item_dt->getTimestamp();

                // Si la entrada de la API está a menos de 3 horas de nuestra hora objetivo
                if (abs($item_timestamp - $target_dt_timestamp) <= 3 * 3600) {
                    $closest_temps[] = round($item['main']['temp']);
                }
            }

            // Si encontramos temperaturas, creamos el rango
            if (!empty($closest_temps)) {
                $min_temp = min($closest_temps);
                $max_temp = max($closest_temps);
                $temp_range = ($min_temp === $max_temp) ? $min_temp . '°C' : $min_temp . '°C - ' . $max_temp . '°C';
            } else {
                $temp_range = 'No disponible';
            }

            $forecast_data[] = [
                'label' => $target_dt_obj->format('g A'),
                'temp_range' => $temp_range,
                'desc' => $main_desc
            ];
        }

        // Opcional: Ordenar el array final para que las horas aparezcan en orden lógico
        usort($forecast_data, function ($a, $b) {
            $time_order = ['5 AM' => 1, '5 PM' => 2, '10 PM' => 3];
            return $time_order[$a['label']] <=> $time_order[$b['label']];
        });
    }
}
// --- Exportar JSON del pronóstico ---
$json_forecast_data = json_encode($forecast_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// --- FIN: Clima actual y pronóstico para mañana ---

// Definir los límites aquí una vez, de forma consistente con getUsageLimitStatus
$usage_limits_by_type = [];
$all_garment_types = ['polera', 'camiseta', 'pantalon', 'short', 'zapatillas', 'camisa', 'falda', 'vestido', 'chaqueta', 'abrigo', 'accesorios']; // Asegúrate de listar todos tus tipos
foreach ($all_garment_types as $type) {
    // Usamos la función PHP getUsageLimitStatus para calcular los límites
    $limits = getUsageLimitStatus($type, 0); // Pasamos 0 usos, solo nos interesa el max_uses
    $usage_limits_by_type[$type] = $limits['max_uses'];
}
$json_usage_limits_by_type = json_encode($usage_limits_by_type, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
// --- FIN NUEVO ---

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
            position: relative;
            /* Necesario para posicionar el contador absoluto */
            overflow: hidden;
            /* Asegura que el contador no se salga si hay bordes */
        }

        .uso-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #667eea;
            /* Color principal */
            color: white;
            border-radius: 50%;
            /* Para que sea un círculo */
            width: 30px;
            /* Tamaño del círculo */
            height: 30px;
            /* Tamaño del círculo */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 10;
            /* Asegura que esté por encima de la imagen */
        }

        /* Color si los usos son altos (opcional, para avisar que está cerca del límite) */
        .uso-badge.high-usage {
            background-color: #dc3545;
            /* Rojo */
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

        #outfitFormContainer {
            display: block;
            /* Oculta el elemento por defecto */
        }

        #toggleOutfitFormBtn {
            display: none;
        }

        /* En pantallas medianas y superiores, el contenido siempre es visible (anula .hidden-mobile-content) */
        @media (max-width: 768px) {

            #outfitFormContainer {
                display: none;
                /* Oculta el elemento por defecto */
            }

            #toggleOutfitFormBtn {
                display: block;
            }

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
                    <li class="nav-item">
                        <a class="nav-link" href="gestor_rapido_prendas.php?estado=todos">
                            <i class="fas fa-check-square me-2"></i>Gestión Rápida
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
                                         SELECT * FROM `prendas` ORDER BY `prendas`.`usos_esta_semana` DESC LIMIT 5;
                                        ";

                                    $resultado = $mysqli_proc->query($query);

                                    if ($resultado && $resultado->num_rows > 0) {
                                        echo '<ul class="list-group list-group-flush">';
                                        while ($fila = $resultado->fetch_assoc()) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo htmlspecialchars($fila['nombre']);
                                            echo '<span class="badge bg-primary rounded-pill">' . (int)$fila['usos_esta_semana'] . '</span>';
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
                                    $sql_outfit_dia = "SELECT id, nombre, contexto, clima_base, fecha_ultimo_uso_outfit
                                        FROM outfits
                                        WHERE fecha_ultimo_uso_outfit = ?
                                        ORDER BY fecha_ultimo_uso_outfit DESC, id DESC LIMIT 1"; // En caso de múltiples usos hoy, coge el último

                                    if ($stmt_outfit_dia = $mysqli_obj->prepare($sql_outfit_dia)) {
                                        $stmt_outfit_dia->bind_param("s", $fecha_actual);
                                        $stmt_outfit_dia->execute();
                                        $result_outfit_dia = $stmt_outfit_dia->get_result();
                                        $outfit_del_dia = $result_outfit_dia->fetch_assoc();
                                        $stmt_outfit_dia->close();
                                    }

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
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Nueva Prenda</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label d-block">¿Cómo quieres agregar tu prenda?</label>
                                        <div class="btn-group w-100" role="group" aria-label="Seleccionar tipo de formulario de prenda">
                                            <input type="radio" class="btn-check" name="prendaAddType" id="addManual" autocomplete="off">
                                            <label class="btn btn-outline-primary" for="addManual"><i class="fas fa-pencil-alt me-2"></i>Manual</label>

                                            <input type="radio" class="btn-check" name="prendaAddType" id="addWithAI" autocomplete="off" checked>
                                            <label class="btn btn-outline-primary" for="addWithAI"><i class="fas fa-robot me-2"></i>Con IA</label>
                                        </div>
                                    </div>

                                    <div id="formManualContainer" style="display:none;">
                                        <h6 class="mb-3 text-muted">Ingresa los detalles manualmente:</h6>
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
                                                <label class="form-label">Comentarios</label>
                                                <textarea class="form-control" name="comentarios" rows="3"></textarea>
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
                                    <div id="formAIContainer">
                                        <div class="card">
                                            <div class="card-body">
                                                <form id="formPrendaIA">
                                                    <div class="mb-3">
                                                        <label class="form-label">Sube la Foto de tu Prenda</label>
                                                        <input type="file" class="form-control" id="iaPrendaFoto" name="foto" accept="image/*" required onchange="previewImage(this, 'previewPrendaIA')">
                                                        <div class="mt-2 text-center">
                                                            <img id="previewPrendaIA" class="image-preview" style="display: none; max-width: 150px; max-height: 150px; border: 1px solid #ddd;">
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-info w-100 mb-3" id="generarPromptPrendaBtn">
                                                        <i class="fas fa-robot me-2"></i>Generar Prompt de Descripción
                                                    </button>

                                                    <div id="aiDescriptionSection" style="display:none;">
                                                        <div class="form-section mb-3">
                                                            <h6>Prompt para describir la prenda con IA:</h6>
                                                            <div class="input-group">
                                                                <textarea id="aiPrendaPrompt" class="form-control" rows="6" readonly></textarea>
                                                                <button class="btn btn-outline-secondary" type="button" id="copyPrendaPromptBtn">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                            <small class="form-text text-muted">Copia el prompt, pégalo en tu IA y luego pega la respuesta abajo.</small>
                                                        </div>

                                                        <div class="form-section mb-3">
                                                            <h6>Pega la Respuesta de la IA aquí:</h6>
                                                            <textarea id="aiPrendaResponse" class="form-control" rows="6" placeholder="Pega el JSON de la IA aquí..." onkeyup="processPrendaAIResponse()"></textarea>
                                                            <small class="form-text text-muted">Asegúrate de que sea JSON válido. Se autocompletarán los campos.</small>
                                                        </div>

                                                        <h6>Detalles de la Prenda (Auto-completado por IA):</h6>
                                                        <div class="mb-3">
                                                            <label class="form-label">Nombre (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="nombre_ia" id="iaNombre" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Tipo (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="tipo_ia" id="iaTipo" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Color Principal (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="color_principal_ia" id="iaColorPrincipal">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Tela (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="tela_ia" id="iaTela">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Textura (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="textura_ia" id="iaTextura">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Estampado (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="estampado_ia" id="iaEstampado">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Clima Apropiado (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="clima_apropiado_ia" id="iaClimaApropiado">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Formalidad (Sugerido por IA)</label>
                                                            <input type="text" class="form-control" name="formalidad_ia" id="iaFormalidad">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Comentarios (Sugerido por IA)</label>
                                                            <textarea class="form-control" name="comentarios_ia" id="iaComentarios" rows="3"></textarea>
                                                        </div>

                                                        <button type="submit" class="btn btn-primary w-100" id="guardarPrendaIABtn">
                                                            <i class="fas fa-save me-2"></i>Guardar Prenda en Clóset
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--
                        
                                -->
                        </div>

                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                    <h5 class="mb-0 me-3"><i class="fas fa-list me-2"></i>Mi Clóset</h5>
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end"> <input type="text" class="form-control" style="max-width: 200px;"
                                            placeholder="Buscar prendas..." id="buscarPrendas" onkeyup="filtrarPrendas()">

                                        <select class="form-select" id="filterPrendaTipo" style="max-width: 150px;" onchange="filtrarPrendas()">
                                            <option value="todos">Todos los Tipos</option>
                                            <?php
                                            $queryCargos2 = "SELECT DISTINCT(tipo) FROM prendas ORDER BY `prendas`.`tipo` ASC";
                                            $resultCargos2 = mysqli_query($mysqli_obj, $queryCargos2);
                                            while ($rowCargos = mysqli_fetch_assoc($resultCargos2)) {
                                                echo "<option value='" . $rowCargos['tipo'] . "'>" . $rowCargos['tipo'] . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="loading" id="loadingPrendas">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                                        <p>Cargando prendas...</p>
                                    </div>

                                    <div class="row">
                                        <?php while ($row = $result_prendas->fetch_assoc()): ?>
                                            <div class="col-md-6 mb-3 prenda-item"
                                                data-nombre-prenda="<?php echo htmlspecialchars(strtolower($row['nombre'])); ?>"
                                                data-prenda-tipo="<?php echo htmlspecialchars(strtolower($row['tipo'])); ?>">
                                                <div class="card prenda-card">

                                                    <?php
                                                    $uso_badge_class = '';
                                                    $current_garment_uses = (int)$row['usos_esta_semana'];
                                                    $garment_type_for_function = $row['tipo'];

                                                    // Utiliza la función para obtener el estado de uso
                                                    $badgeUsageStatus = getUsageLimitStatus($garment_type_for_function, $current_garment_uses);

                                                    // Aplica la clase 'high-usage' si la prenda está sobreusada Y no es de uso ilimitado
                                                    if (!$row['uso_ilimitado'] && $badgeUsageStatus['is_overused']) {
                                                        $uso_badge_class = ' high-usage';
                                                    }

                                                    // Muestra el badge solo si la prenda NO es de uso ilimitado
                                                    // (Si quieres mostrar el contador para prendas de uso ilimitado también, elimina este 'if')
                                                    if (!$row['uso_ilimitado']) {
                                                        echo '<span class="uso-badge' . $uso_badge_class . '">' . $current_garment_uses . '</span>';
                                                    }
                                                    ?>

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
                                                            <span class="badge bg-<?php
                                                                                    echo $row['estado'] === 'disponible' ? 'success' : ($row['estado'] === 'sucio' ? 'danger' : 'warning');
                                                                                    ?>">
                                                                <?php echo $row['estado']; ?></span>
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
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Crear Outfit</h5>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleOutfitForm()" id="toggleOutfitFormBtn">
                                        <i class="fas fa-eye me-2"></i>Mostrar Formulario
                                    </button>
                                </div>
                                <div class="collapse show d-md-block" id="outfitFormContainer">
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
                                                    <?php
                                                    // Asegúrate de que $climas_outfit esté definida en tu PHP inicial si no lo está globalmente
                                                    // $climas_outfit = ['todo' => 'Todo clima', 'calor' => 'Calor', 'frio' => 'Frío', 'lluvia' => 'Lluvia'];
                                                    if (!isset($climas_outfit)) { // Definir si no está definida globalmente
                                                        $climas_outfit = ['todo' => 'Todo clima', 'calor' => 'Calor', 'frio' => 'Frío', 'lluvia' => 'Lluvia'];
                                                    }
                                                    foreach ($climas_outfit as $valor => $texto) {
                                                        echo "<option value=\"$valor\">" . ucfirst($texto) . "</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Comentarios</label>
                                                <textarea class="form-control" name="comentarios" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Seleccionar Prendas</label>

                                                <input type="text" class="form-control mb-2" id="buscarPrendasOutfit" placeholder="Buscar prenda por nombre, tipo o color..." onkeyup="filterOutfitPrendas()">
                                                <div id="listaPrendasOutfit">
                                                    <?php
                                                    // Asegúrate de que $mysqli_obj esté disponible aquí
                                                    $sql_prendas_outfit_form = "SELECT id, nombre, tipo, color_principal, foto, uso_ilimitado FROM prendas WHERE estado = 'disponible' OR uso_ilimitado = TRUE";
                                                    $result_prendas_outfit_form = $mysqli_obj->query($sql_prendas_outfit_form);

                                                    if ($result_prendas_outfit_form && $result_prendas_outfit_form->num_rows > 0) {
                                                        while ($prenda = $result_prendas_outfit_form->fetch_assoc()) {
                                                            echo '
                                                            <div class="form-check mb-2 prenda-outfit-item" data-nombre-prenda="' . htmlspecialchars(strtolower($prenda['nombre'])) . '" data-tipo-prenda="' . htmlspecialchars(strtolower($prenda['tipo'])) . '" data-color-prenda="' . htmlspecialchars(strtolower($prenda['color_principal'])) . '">
                                                                <input class="form-check-input" type="checkbox" name="prendas[]" value="' . $prenda['id'] . '" id="prendaOutfit' . $prenda['id'] . '">
                                                                <label class="form-check-label d-flex align-items-center" for="prendaOutfit' . $prenda['id'] . '">
                                                                    ';
                                                            $imagen_src = !empty($prenda['foto']) ? htmlspecialchars($prenda['foto']) : 'https://via.placeholder.com/50x50?text=Sin+Imagen';
                                                            echo '<img src="' . $imagen_src . '" alt="Imagen de ' . htmlspecialchars($prenda['nombre']) . '" class="me-2 rounded" style="width: 50px; height: 50px; object-fit: cover;">';
                                                            echo '
                                                                    <div>
                                                                        <strong>' . htmlspecialchars($prenda['nombre']) . '</strong><br>
                                                                        <small class="text-muted">' . htmlspecialchars($prenda['tipo']) . ' • ' . htmlspecialchars($prenda['color_principal']) . '</small>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                            ';
                                                        }
                                                    } else {
                                                        echo '<p class="text-muted">No hay prendas disponibles para seleccionar.</p>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-save me-2"></i>Crear Outfit
                                            </button>
                                        </form>
                                    </div>
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
                            <h6 class="card-title"><a style="text-decoration: none;" href="detalle_outfit.php?id=' . $outfit['id'] . '">' . htmlspecialchars($outfit['nombre']) . '</a></h6>
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

                                        <div class="mb-3">
                                            <label for="reglasEspecificas" class="form-label">Reglas o Requisitos Específicos (opcional)</label>
                                            <textarea class="form-control" id="reglasEspecificas" name="reglas_especificas" rows="3" placeholder="Ej: 'Obligado a usar la chaqueta azul de la empresa', 'No se permiten jeans rotos', 'Debe incluir un sombrero'."></textarea>
                                        </div>
                                        <div class=" mb-3">
                                            <label for="pronosticoPersonalizado" class="form-label">Pronóstico del Clima de Mañana (Opcional)</label>
                                            <textarea class="form-control" id="pronosticoPersonalizado" rows="5" placeholder="Ej: '5 AM: 10°C, soleado. 5 PM: 20°C, despejado. 10 PM: 15°C, nublado.'"></textarea>
                                            <small class="form-text text-muted">Si no ingresas un pronóstico, se usará el de Santiago por defecto.</small>
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
                                        <button type="button" class="btn btn-info w-100 mb-3" onclick="generarPromptCompra()">
                                            <i class="fas fa-shopping-bag me-2"></i>Recomendaciones de Compra
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
        // Asegúrate de que esta función esté fuera del DOMContentLoaded para ser global
        async function sendCreateOutfitRequest(formData, forceDuplicate = false) {
            if (forceDuplicate) {
                formData.append('force_duplicate', 'true');
            }

            Swal.fire({
                title: 'Creando Outfit...',
                text: 'Por favor espera mientras tu outfit se guarda.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('crear_outfit.php', { // Endpoint para creación manual
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                Swal.close();

                if (data.success) {
                    Swal.fire('Outfit Creado!', data.message, 'success')
                        .then(() => {
                            location.reload();
                        });
                } else if (data.code === 'DUPLICATE_OUTFIT') {
                    Swal.fire({
                        title: 'Outfit Duplicado Detectado',
                        html: data.message + '<br><br>¿Deseas crear este outfit duplicado de todas formas?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, crear duplicado',
                        cancelButtonText: 'No, cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Si el usuario confirma, re-enviar la solicitud con el mismo formData y forzando
                            sendCreateOutfitRequest(formData, true);
                        } else {
                            Swal.fire('Creación Cancelada', 'El outfit no ha sido creado.', 'info');
                        }
                    });
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido al crear el outfit.', 'error');
                }
            } catch (error) {
                Swal.close();
                console.error('Error al enviar la solicitud de creación de outfit:', error);
                Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor para crear el outfit.', 'error');
            }
        }

        async function sendPrendaRequest(formData, endpoint, forceDuplicateName = false) {
            if (forceDuplicateName) {
                formData.append('force_duplicate_name', 'true');
            }

            Swal.fire({
                title: 'Guardando Prenda...',
                text: 'Por favor espera.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch(endpoint, { // El endpoint es variable (crear_prenda.php o upload_and_process_ai.php)
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                Swal.close();

                if (data.success) {
                    Swal.fire('¡Guardada!', data.message, 'success')
                        .then(() => location.reload());
                } else if (data.code === 'DUPLICATE_PRENDA_NAME') {
                    Swal.fire({
                        title: 'Nombre de Prenda Duplicado',
                        html: data.message + '<br><br>¿Deseas guardar la prenda con este nombre duplicado de todas formas?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, guardar de todos modos',
                        cancelButtonText: 'No, cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Re-enviar la solicitud forzando la creación
                            sendPrendaRequest(formData, endpoint, true);
                        } else {
                            Swal.fire('Guardado Cancelado', 'La prenda no ha sido guardada.', 'info');
                        }
                    });
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido al guardar la prenda.', 'error');
                }
            } catch (error) {
                Swal.close();
                console.error('Error al enviar la solicitud de prenda:', error);
                Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor.', 'error');
            }
        }
        // --- NUEVA LÓGICA: Alternar formularios de agregar prenda ---

        // Agregar prenda
        const formPrendaManual = document.getElementById('formPrenda');
        if (formPrendaManual) {
            formPrendaManual.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                sendPrendaRequest(formData, 'crear_prenda.php'); // Llama a la función unificada
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // ... (Tu código JavaScript existente, variables, funciones como previewImage, etc.) ...
            const addManualRadio = document.getElementById('addManual');
            const addWithAIRadio = document.getElementById('addWithAI');
            const formManualContainer = document.getElementById('formManualContainer');
            const formAIContainer = document.getElementById('formAIContainer');

            function togglePrendaForms() {
                if (addManualRadio.checked) {
                    formManualContainer.style.display = 'block';
                    formAIContainer.style.display = 'none';
                } else {
                    formManualContainer.style.display = 'none';
                    formAIContainer.style.display = 'block';
                }
            }

            // Escuchar cambios en los botones de radio
            addManualRadio.addEventListener('change', togglePrendaForms);
            addWithAIRadio.addEventListener('change', togglePrendaForms);

            // Ejecutar al cargar para asegurar que el formulario correcto se muestre al inicio
            togglePrendaForms();
            // --- FIN NUEVA LÓGICA ---

            // --- Control de rotación del icono para "Crear Outfit" ---
            var collapseElementCreateOutfit = document.getElementById('collapseFormOutfit');
            var collapseButtonCreateOutfitIcon = document.getElementById('createOutfitToggleIcon');

            if (collapseElementCreateOutfit && collapseButtonCreateOutfitIcon) {
                collapseElementCreateOutfit.addEventListener('show.bs.collapse', function() {
                    collapseButtonCreateOutfitIcon.classList.remove('fa-chevron-down');
                    collapseButtonCreateOutfitIcon.classList.add('fa-chevron-up');
                });

                collapseElementCreateOutfit.addEventListener('hide.bs.collapse', function() {
                    collapseButtonCreateOutfitIcon.classList.remove('fa-chevron-up');
                    collapseButtonCreateOutfitIcon.classList.add('fa-chevron-down');
                });
            }

            // Agregar nuevo outfit
            const formOutfit = document.getElementById('formOutfit');
            if (formOutfit) {
                formOutfit.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this); // Captura todos los campos del formulario

                    // Llamar a la función unificada de envío
                    sendCreateOutfitRequest(formData);
                });
            }

            // --- NUEVO: Cargar reglas guardadas al inicio ---
            const reglasEspecificasTextarea = document.getElementById('reglasEspecificas');
            if (reglasEspecificasTextarea) {
                fetch('obtener_reglas_sugerencia.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.reglas_especificas) {
                            reglasEspecificasTextarea.value = data.reglas_especificas;
                        } else if (data.message) {
                            console.log(data.message); // Por si no hay reglas
                        }
                    })
                    .catch(error => console.error('Error al cargar reglas:', error));
            }
            // --- FIN NUEVO ---

            const guardarReglasBtn = document.getElementById('guardarReglasBtn');
            if (guardarReglasBtn) {
                guardarReglasBtn.addEventListener('click', function() {
                    const reglas = reglasEspecificasTextarea.value; // Ya definida arriba

                    const formData = new FormData();
                    formData.append('reglas_especificas', reglas);

                    fetch('guardar_reglas_sugerencia.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('¡Guardado!', data.message, 'success');
                            } else {
                                Swal.fire('Error', data.message || 'Error al guardar las reglas.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error al guardar reglas:', error);
                            Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor para guardar las reglas.', 'error');
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
                                                            ${prenda.detalles_adicionales ? `<p class="card-text"><small class="text-muted"><em>"${prenda.detalles_adicionales.substring(0, 50)}..."</em></small></p>` : ''}
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
            const searchTerm = document.getElementById('buscarPrendas').value.toLowerCase().trim();
            const selectedType = document.getElementById('filterPrendaTipo').value; // Obtener el tipo seleccionado
            const prendaItems = document.querySelectorAll('.prenda-item'); // Seleccionar todas las tarjetas de prendas

            prendaItems.forEach(item => {
                const nombrePrenda = item.getAttribute('data-nombre-prenda');
                const tipoPrenda = item.getAttribute('data-prenda-tipo'); // Obtener el tipo de la prenda

                // Lógica de filtrado
                const matchesSearch = nombrePrenda.includes(searchTerm);
                const matchesType = (selectedType === 'todos' || tipoPrenda === selectedType);

                if (matchesSearch && matchesType) {
                    item.style.display = ''; // Mostrar si coincide con ambos filtros
                } else {
                    item.style.display = 'none'; // Ocultar si no coincide con alguno
                }
            });
        }

        const availableFilteredPrendas = <?php echo $json_prendas_para_sugerencia_ia; ?>;
        const availablePrendas = <?php echo $json_prendas_para_sugerencia_compras; ?>;
        const tomorrowForecast = <?php echo $json_forecast_data; ?>;
        const outfitsUsedHistory = <?php echo $json_outfits_usados_history; ?>; // NUEVA LÍNEA
        const garmentUsageLimits = <?php echo $json_usage_limits_by_type; ?>; // NUEVA LÍNEA


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
            // NUEVA LÍNEA: Obtener las reglas específicas
            const reglas_especificas = document.getElementById('reglasEspecificas').value.trim(); // .trim() para quitar espacios al inicio/final

            let prendasListForIA = '';
            if (availableFilteredPrendas.length > 0) {
                prendasListForIA = '\n\nAquí tienes una lista de las prendas disponibles en mi clóset que tienen menos de 3 usos esta semana. Por favor, **utiliza SOLAMENTE estas prendas** en tu sugerencia:\n';
                availableFilteredPrendas.forEach(prenda => {
                    const comments = prenda.comentarios ? ` - Detalle: "${prenda.comentarios}"` : '';
                    prendasListForIA += `- ${prenda.nombre} (Tipo: ${prenda.tipo}, Color: ${prenda.color})${comments}\n`;
                });
                prendasListForIA += '\n';
            } else {
                prendasListForIA = '\n\nNo tengo prendas disponibles con menos de 3 usos esta semana. Por favor, sugiere un outfit general basado en las condiciones.\n';
            }

            let tomorrowForecastForIA = '';
            const pronosticoPersonalizado = document.getElementById('pronosticoPersonalizado').value.trim();

            if (pronosticoPersonalizado) {
                tomorrowForecastForIA = '\n\nPronóstico del clima de mañana (personalizado por el usuario):\n' + pronosticoPersonalizado + '\n\n';
            } else if (tomorrowForecast.length > 0) {
                tomorrowForecastForIA = '\n\nPronóstico del clima para mañana (basado en Santiago):\n';
                tomorrowForecast.forEach(forecast => {
                    // Usar forecast.temp_range en lugar de forecast.temp
                    tomorrowForecastForIA += `- ${forecast.label}: ${forecast.temp_range}, ${forecast.desc}\n`;
                });
                tomorrowForecastForIA += '\n';
            } else {
                tomorrowForecastForIA = '\n\nNo se pudo obtener el pronóstico del clima. Considera un clima general para la temporada.\n';
            }
            // --- FIN NUEVO ---

            // --- INICIO: Historial de Outfits Usados para el prompt ---
            let outfitsHistoryForIA = '';
            if (outfitsUsedHistory.length > 0) {
                outfitsHistoryForIA = '\n\nMis outfits anteriores que podrías usar como referencia o inspiración:\n';
                outfitsUsedHistory.forEach(outfit => {
                    const lastUsedDate = outfit.fecha_ultimo_uso ? ` (Último uso: ${outfit.fecha_ultimo_uso})` : ''; // Formato de fecha
                    outfitsHistoryForIA += `- Nombre: "${outfit.nombre}"${lastUsedDate}\n`; // Añadir la fecha al nombre
                    outfitsHistoryForIA += `  Prendas: ${outfit.prendas}\n`;
                    if (outfit.comentarios) {
                        outfitsHistoryForIA += `  Comentarios: "${outfit.comentarios}"\n`;
                    }
                });
                outfitsHistoryForIA += '\n';
            } else {
                outfitsHistoryForIA = '\n\nNo tengo historial de outfits usados para referencia.\n';
            }
            // --- FIN: Historial de Outfits Usados para el prompt ---


            let prompt = `\nComo experto en moda y estilismo, necesito una sugerencia de outfit detallada para las siguientes condiciones. Me gustaría que la ropa sugerida sea cómoda, adecuada para los cambios de temperatura y el pronóstico detallado. Por favor, sé creativo y proporciona la información de forma estructurada para que pueda ser parseada fácilmente.

            Condiciones para el outfit:
            - Contexto: ${contextoForIA}
            - Espacio en mochila disponible: ${espacio_mochila.charAt(0).toUpperCase() + espacio_mochila.slice(1)}
            - ¿Posibilidad de llevar muda extra?: ${muda_extra ? 'Sí' : 'No'}
            ${tomorrowForecastForIA}

            ${reglas_especificas ? `**Reglas o Requisitos Obligatorios:**\n- ${reglas_especificas}\n\n` : ''}

             **Instrucción Adicional:** Para los 3 outfits que vas a sugerir, **no repitas las prendas superiores** (poleras, camisetas, camisas, abrigos o chaquetas) entre ellos. Asegúrate de que cada outfit tenga una prenda superior única.
            
            ${prendasListForIA}


            ${outfitsHistoryForIA}
            

           Tu respuesta debe tener el siguiente formato JSON estricto: un **objeto principal** que contenga una clave "type" (que será "outfit_suggestions") y una clave "suggestions" que sea un array de objetos. Proporciona **3 ideas de outfit distintas**. Sin texto adicional antes ni después del JSON. Cada objeto de outfit en el array "suggestions" debe tener las propiedades "titulo", "descripcion", "prendas_sugeridas" (un array de strings concisos) y "tips_adicionales".
            
            **IMPORTANTE: Para las "prendas_sugeridas", por favor, utiliza los nombres EXACTOS de las prendas que te proporcioné en mi lista de clóset, o en su defecto, la combinación 'Tipo de prenda - Color' si no hay un nombre específico.** Por ejemplo, si mi lista tiene 'Camiseta Naranja Salmón (camiseta, Naranja Salmón)', por favor, usa 'Camiseta Naranja Salmón' o 'camiseta - Naranja Salmón'. Si no puedes encontrar una prenda exacta, sugiere una genérica.

            {
              "type": "outfit_suggestions",
              "suggestions": [
                {
                "titulo": "Un título atractivo para el Outfit 1",
                "descripcion": "Una descripción breve y convincente del Outfit 1, explicando por qué es adecuado y cómo se adapta al clima y la comodidad.",
                "prendas_sugeridas": [
                  "Nombre Exacto de Prenda de tu lista 1", // Ejemplo: "Camiseta Naranja Salmón"
                  "Tipo de prenda - Color (ej. 'pantalon - Azul Claro/Medio')", // Si no hay nombre exacto
                  "Chaqueta Softshell Negra" // Otro ejemplo de nombre exacto
                ],
                "tips_adicionales": "Un consejo de estilo o practicidad relacionado con el Outfit 1 (ej. 'No olvides una bufanda ligera para la mañana fría.')"
              },
              {
                "titulo": "Un título atractivo para el Outfit 2",
                "descripcion": "Una descripción breve y convincente del Outfit 2.",
                "prendas_sugeridas": [
                  "Nombre Exacto de Prenda 1", "Nombre Exacto de Prenda 2"
                ],
                "tips_adicionales": "Tip para Outfit 2."
              },
              {
                "titulo": "Un título atractivo para el Outfit 3",
                "descripcion": "Una descripción breve y convincente del Outfit 3.",
                "prendas_sugeridas": [
                  "Nombre Exacto de Prenda X", "Nombre Exacto de Prenda Y"
                ],
                "tips_adicionales": "Tip para Outfit 3."
              }
            ]
        }
`;

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

        // Nueva función para generar el prompt para recomendaciones de compra
        function generarPromptCompra() {
            // Recopilar datos generales del clóset
            let prendasInventarioForIA = '';
            if (availablePrendas.length > 0) { // availablePrendas ahora contiene TODAS las prendas
                prendasInventarioForIA = '\n\nMi inventario actual de prendas (incluye usos semanales y comentarios):\n';
                availablePrendas.forEach(prenda => {
                    const comments = prenda.comentarios ? ` - Comentarios: "${prenda.comentarios}"` : '';
                    prendasInventarioForIA += `- ${prenda.nombre} (Tipo: ${prenda.tipo}, Color: ${prenda.color}, Usos esta semana: ${prenda.usos_esta_semana}, Uso Ilimitado: ${prenda.uso_ilimitado ? 'No' : 'Sí'})${comments}\n`;
                });
                prendasInventarioForIA += '\n';
            } else {
                prendasInventarioForIA = '\n\nNo tienes prendas registradas en tu clóset. La recomendación será genérica.\n';
            }

            // Datos de uso y posibles "sobreusadas"
            let prendasSobreusadasForIA = '';
            const overusedGarments = availablePrendas.filter(p => {
                // Si no es de uso ilimitado y ha excedido sus usos semanales
                // Obtener el límite del objeto garmentUsageLimits
                const maxUsesForType = garmentUsageLimits[p.tipo.toLowerCase()] || 2; // Default 2 si el tipo no está en la lista
                return !p.uso_ilimitado && p.usos_esta_semana >= maxUsesForType;
            });

            if (overusedGarments.length > 0) {
                prendasSobreusadasForIA = '\n\nLas siguientes prendas han sido sobreusadas esta semana (quizás necesito más opciones de este tipo o categoría):\n';
                overusedGarments.forEach(prenda => {
                    // Obtener el límite del objeto garmentUsageLimits para mostrarlo
                    const maxUsesForDisplay = garmentUsageLimits[prenda.tipo.toLowerCase()] || 2;
                    prendasSobreusadasForIA += `- ${prenda.nombre} (Tipo: ${prenda.tipo}, Usos esta semana: ${prenda.usos_esta_semana}, Límite: ${maxUsesForDisplay})\n`;
                });
                prendasSobreusadasForIA += '\n';
            } else {
                prendasSobreusadasForIA = '\n\nNinguna prenda ha sido sobreusada esta semana.\n';
            }

            // Historial de outfits usados (reutiliza la variable ya generada)
            let outfitsHistoryForIA = '';
            if (outfitsUsedHistory.length > 0) {
                outfitsHistoryForIA = '\n\nMis outfits anteriores que podrían indicar mi estilo o necesidades:\n';
                outfitsUsedHistory.forEach(outfit => {
                    const lastUsedDate = outfit.fecha_ultimo_uso ? ` (Último uso: ${outfit.fecha_ultimo_uso})` : '';
                    outfitsHistoryForIA += `- Nombre: "${outfit.nombre}"${lastUsedDate}\n`;
                    outfitsHistoryForIA += `  Prendas: ${outfit.prendas}\n`;
                    if (outfit.comentarios) {
                        outfitsHistoryForIA += `  Comentarios: "${outfit.comentarios}"\n`;
                    }
                });
                outfitsHistoryForIA += '\n';
            } else {
                outfitsHistoryForIA = '\n\nNo tengo historial de outfits usados para referencia.\n';
            }

            // Prompt principal
            let prompt = `Como experto en moda, estilismo y optimización de guardarropas, necesito tu ayuda para identificar qué prendas debería considerar comprar para mejorar y complementar mi clóset existente.

            Analiza la información de mi clóset a continuación, incluyendo mi inventario actual, mis patrones de uso semanal y mis outfits anteriores.

            **Mi Objetivo:** Quiero prendas que:
            - Rellenen "huecos" en mi guardarropa.
            - Reduzcan el sobreuso de ciertas prendas.
            - Aumenten la versatilidad de mis outfits.
            - Se adapten a mi estilo (inferido de mis outfits anteriores).
            - Consideren la comodidad y funcionalidad para mis contextos habituales.

            ${prendasInventarioForIA}
            ${prendasSobreusadasForIA}
            ${outfitsHistoryForIA}

            Tu respuesta debe tener el siguiente formato JSON estricto: un **objeto principal** que contenga una clave "type" (que será "purchase_recommendations") y una clave "recommendations" que sea un array de objetos. Proporciona **hasta 5 recomendaciones de prendas diferentes**. Sin texto adicional antes ni después del JSON.

            {
              "type": "purchase_recommendations",
              "recommendations": [
                {
                  "prenda_sugerida": "Nombre descriptivo de la prenda a comprar (ej. Polera básica de algodón color neutro)",
                  "tipo_recomendado": "Tipo de prenda (ej. polera, pantalon, chaqueta)",
                  "justificacion": "Breve explicación de por qué esta prenda complementaría tu clóset (ej. 'Necesitas otra polera básica para reducir el sobreuso de las que ya tienes.')"
                },
                {
                  "prenda_sugerida": "Zapatillas deportivas para running",
                  "tipo_recomendado": "zapatillas",
                  "justificacion": "Tus zapatillas de skate están sobreusadas para deporte, una opción específica para running sería útil."
                }
              ]
            }
            `;

            const aiPromptElement = document.getElementById('aiPrompt');
            aiPromptElement.value = prompt;

            aiPromptElement.select();
            aiPromptElement.setSelectionRange(0, 99999);
            try {
                navigator.clipboard.writeText(aiPromptElement.value);
                Swal.fire('¡Copiado!', 'El prompt de compra ha sido copiado al portapapeles.', 'success');
            } catch (err) {
                console.error('No se pudo copiar el prompt de compra al portapapeles:', err);
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
        // Función para procesar la respuesta de la IA (MEJORADA)
        document.getElementById('processResponseBtn').addEventListener('click', function() {
            const aiResponseText = document.getElementById('aiResponse').value.trim(); // .trim() para limpiar espacios
            const processedSuggestionDiv = document.getElementById('processedSuggestion');

            processedSuggestionDiv.innerHTML = ''; // Limpiar el contenido anterior de sugerencias

            if (aiResponseText === '') {
                Swal.fire('Atención', 'Por favor, pega la respuesta JSON de la IA en el campo.', 'info');
                return;
            }

            try {
                const aiResponseData = JSON.parse(aiResponseText); // Ahora esperamos un OBJETO principal

                // 1. Validar que sea un objeto y tenga una propiedad 'type'
                if (typeof aiResponseData !== 'object' || aiResponseData === null || !aiResponseData.type) {
                    throw new Error('La respuesta de la IA no es un objeto JSON válido o no contiene una propiedad "type" que indique el tipo de sugerencia.');
                }

                // 2. Delegar el renderizado basado en el 'type'
                if (aiResponseData.type === 'outfit_suggestions') {
                    if (!Array.isArray(aiResponseData.suggestions) || aiResponseData.suggestions.length === 0) {
                        throw new Error('El tipo es "outfit_suggestions" pero "suggestions" no es un array o está vacío.');
                    }
                    renderOutfitSuggestions(aiResponseData.suggestions);
                } else if (aiResponseData.type === 'purchase_recommendations') {
                    if (!Array.isArray(aiResponseData.recommendations) || aiResponseData.recommendations.length === 0) {
                        throw new Error('El tipo es "purchase_recommendations" pero "recommendations" no es un array o está vacío.');
                    }
                    renderPurchaseRecommendations(aiResponseData.recommendations);
                } else {
                    throw new Error(`Tipo de respuesta de IA no reconocido: "${aiResponseData.type}".`);
                }

                Swal.fire('¡Sugerencias Listas!', 'La respuesta de la IA ha sido procesada.', 'success');

            } catch (e) {
                console.error('Error al procesar la respuesta de la IA:', e);
                processedSuggestionDiv.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <h4>Error al procesar la respuesta de la IA:</h4>
                        <p>${htmlspecialchars(e.message)}</p>
                        <p>Asegúrate de que la IA devuelva un **OBJETO JSON válido** con la estructura <code>{"type": "outfit_suggestions", "suggestions": [...] }</code> o <code>{"type": "purchase_recommendations", "recommendations": [...] }</code>.</p>
                        <p>Respuesta recibida: <pre>${htmlspecialchars(aiResponseText)}</pre></p>
                    </div>
                `;
                Swal.fire('Error', 'No se pudo procesar la respuesta. Revisa el formato.', 'error');
            }
        });


        // Función para renderizar sugerencias de outfits
        function renderOutfitSuggestions(suggestions) {
            const processedSuggestionDiv = document.getElementById('processedSuggestion');
            let allOutfitsHtml = '';

            suggestions.forEach((suggestion, index) => {
                if (suggestion && suggestion.titulo && suggestion.descripcion && suggestion.prendas_sugeridas && Array.isArray(suggestion.prendas_sugeridas) && suggestion.tips_adicionales) {
                    let prendasListHtml = suggestion.prendas_sugeridas.map(prenda => `<li>${htmlspecialchars(prenda)}</li>`).join('');

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
                                        data-outfit-data='${JSON.stringify(suggestion)}'>
                                    <i class="fas fa-plus-circle me-2"></i>Crear este Outfit
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

            // Añadir event listeners a los nuevos botones "Crear este Outfit"
            document.querySelectorAll('.crear-outfit-sugerido').forEach(button => {
                button.addEventListener('click', function() {
                    const outfitData = JSON.parse(this.dataset.outfitData);
                    createOutfitFromSuggestion(outfitData);
                });
            });
        }

        // Función para renderizar recomendaciones de compra
        function renderPurchaseRecommendations(recommendations) {
            const processedSuggestionDiv = document.getElementById('processedSuggestion');
            let allRecommendationsHtml = '<h5>Recomendaciones de Compra:</h5><div class="list-group">';

            recommendations.forEach((rec, index) => {
                if (rec && rec.prenda_sugerida && rec.tipo_recomendado && rec.justificacion) {
                    allRecommendationsHtml += `
                        <div class="list-group-item list-group-item-action mb-2 rounded shadow-sm">
                            <h6 class="mb-1">${htmlspecialchars(rec.prenda_sugerida)} 
                                <span class="badge bg-secondary ms-2">${htmlspecialchars(rec.tipo_recomendado)}</span>
                            </h6>
                            <p class="mb-1 text-muted"><small>${htmlspecialchars(rec.justificacion)}</small></p>
                        </div>
                    `;
                } else {
                    allRecommendationsHtml += `
                        <div class="alert alert-warning mb-4" role="alert">
                            <h4>Advertencia: Una recomendación de compra no tiene el formato correcto.</h4>
                            <pre>${htmlspecialchars(JSON.stringify(rec, null, 2))}</pre>
                        </div>
                    `;
                }
            });
            allRecommendationsHtml += '</div>';
            processedSuggestionDiv.innerHTML = allRecommendationsHtml;
        }

        // Helper function para escapar HTML y evitar XSS al mostrar la respuesta
        function htmlspecialchars(str) {
            let div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // Función para crear un outfit en la DB a partir de una sugerencia de la IA
        async function createOutfitFromSuggestion(suggestion, forceDuplicate = false) {
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

                    const outfitComments = suggestion.descripcion + "<br>" + suggestion.tips_adicionales;

                    // --- Mapear nombres de prendas sugeridas a IDs de prendas existentes ---
                    const selectedPrendaIds = [];
                    const unmatchedPrendas = [];

                    suggestion.prendas_sugeridas.forEach(aiSuggestedPrenda => {
                        const suggestedLower = aiSuggestedPrenda.toLowerCase();

                        const foundPrenda = availableFilteredPrendas.find(p => {
                            const pNameLower = p.nombre.toLowerCase();
                            const pTypeColorLower = `${p.tipo.toLowerCase()} - ${p.color.toLowerCase()}`; // Ejemplo: "camiseta - naranja salmón"

                            // Intentar coincidencia exacta primero con el nombre de la prenda
                            if (suggestedLower === pNameLower) {
                                return true;
                            }
                            // Intentar coincidencia con la combinación tipo - color
                            if (suggestedLower === pTypeColorLower) {
                                return true;
                            }
                            // Si no hay coincidencia exacta, buscar si el nombre sugerido CONTIENE el nombre de la prenda o tipo/color
                            // (Esto puede ser más flexible, pero puede dar falsos positivos)
                            return suggestedLower.includes(pNameLower) || suggestedLower.includes(pTypeColorLower);
                        });

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

                    // --- REORGANIZACIÓN DE LAS ADVERTENCIAS/CONFIRMACIONES ---

                    // 1. Advertencia si hay prendas que la IA sugirió pero no se encontraron en el clóset
                    if (unmatchedPrendas.length > 0) {
                        const confirmProceedUnmatched = await Swal.fire({
                            title: 'Algunas prendas sugeridas no se encontraron',
                            html: `La IA sugirió las siguientes prendas que no se pudieron mapear a tu clóset: <strong>${unmatchedPrendas.join(', ')}</strong>.<br>¿Deseas crear el outfit solo con las prendas encontradas?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, crear de todos modos',
                            cancelButtonText: 'No, cancelar'
                        });
                        if (!confirmProceedUnmatched.isConfirmed) {
                            return; // Cancelar si el usuario no quiere crear con prendas faltantes
                        }
                    }

                    // 2. Advertencia si hay prendas seleccionadas que no están "disponibles"
                    const nonAvailablePrendas = [];
                    selectedPrendaIds.forEach(prendaId => {
                        const prendaActual = availableFilteredPrendas.find(p => p.id === prendaId);
                        const nonAvailableStates = ['sucio', 'en uso', 'prestado'];

                        // Asegúrate de que prendaActual exista y que no sea una prenda de uso ilimitado
                        if (prendaActual && nonAvailableStates.includes(prendaActual.estado) && !prendaActual.uso_ilimitado) {
                            nonAvailablePrendas.push(prendaActual.nombre);
                        }
                    });

                    if (nonAvailablePrendas.length > 0) {
                        const confirmProceedNonAvailable = await Swal.fire({
                            title: '¡Algunas prendas no están disponibles para usar!',
                            html: `Las siguientes prendas están en un estado que podría impedir su uso inmediato:<br>
                                   <strong>${nonAvailablePrendas.join(', ')}</strong>.<br><br>
                                   ¿Deseas crear el outfit de todos modos?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, crear de todos modos',
                            cancelButtonText: 'No, cancelar'
                        });
                        if (!confirmProceedNonAvailable.isConfirmed) {
                            return; // Cancelar si el usuario no quiere crear con prendas no disponibles
                        }
                    }
                    // --- FIN REORGANIZACIÓN ---


                    // Append data to formData
                    formData.append('nombre', outfitName);
                    // Append only the first context, or 'General'
                    formData.append('contexto', contextsToUse.length > 0 ? contextsToUse[0] : 'General');
                    formData.append('clima_base', 'todo'); // Default or derived from forecast if needed
                    formData.append('comentarios', outfitComments);
                    selectedPrendaIds.forEach(id => {
                        formData.append('prendas[]', id);
                    });


                    await sendCreateOutfitRequest(formData, forceDuplicate);
                }
            });
        }

        function processPrendaAIResponse() {
            // Obtener el elemento textarea directamente dentro de la función
            const aiPrendaResponseTextarea = document.getElementById('aiPrendaResponse');
            const responseText = aiPrendaResponseTextarea.value;
            try {
                const aiData = JSON.parse(responseText);

                // Autocompletar campos si el JSON es válido
                document.getElementById('iaNombre').value = aiData.nombre || '';
                document.getElementById('iaTipo').value = aiData.tipo || '';
                document.getElementById('iaColorPrincipal').value = aiData.color_principal || '';
                document.getElementById('iaTela').value = aiData.tela || '';
                document.getElementById('iaTextura').value = aiData.textura || '';
                document.getElementById('iaEstampado').value = aiData.estampado || '';
                document.getElementById('iaClimaApropiado').value = aiData.clima_apropiado || 'todo'; // Default si IA no lo da
                document.getElementById('iaFormalidad').value = aiData.formalidad || 'casual'; // Default si IA no lo da
                document.getElementById('iaComentarios').value = aiData.comentarios || '';

                // Mostrar mensaje de éxito si ya se pegó y es válido
                if (responseText.length > 0) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        icon: 'success',
                        title: 'Campos autocompletados con la IA!'
                    });
                }

            } catch (e) {
                // Limpiar campos si el JSON no es válido
                document.getElementById('iaNombre').value = '';
                document.getElementById('iaTipo').value = '';
                document.getElementById('iaColorPrincipal').value = '';
                document.getElementById('iaTela').value = '';
                document.getElementById('iaTextura').value = '';
                document.getElementById('iaEstampado').value = '';
                document.getElementById('iaClimaApropiado').value = 'todo';
                document.getElementById('iaFormalidad').value = 'casual';
                document.getElementById('iaComentarios').value = '';

                // Solo mostrar error si el usuario ha pegado algo que no es vacío y no es JSON
                if (responseText.trim().length > 0) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        icon: 'error',
                        title: 'Error: Respuesta de IA no es JSON válido.'
                    });
                }
            }
        }


        // --- NUEVA LÓGICA: Subir Prenda con Asistencia IA ---
        document.addEventListener('DOMContentLoaded', () => {
            const formPrendaIA = document.getElementById('formPrendaIA');
            const generarPromptPrendaBtn = document.getElementById('generarPromptPrendaBtn');
            const aiDescriptionSection = document.getElementById('aiDescriptionSection');
            const aiPrendaPrompt = document.getElementById('aiPrendaPrompt');
            const copyPrendaPromptBtn = document.getElementById('copyPrendaPromptBtn');
            const aiPrendaFotoInput = document.getElementById('iaPrendaFoto');
            const aiPrendaResponseTextarea = document.getElementById('aiPrendaResponse');
            const guardarPrendaIABtn = document.getElementById('guardarPrendaIABtn');

            let currentUploadedPrendaPath = ''; // Variable para guardar la ruta de la foto subida temporalmente

            // Listener para el botón "Generar Prompt de Descripción"
            generarPromptPrendaBtn.addEventListener('click', async () => {
                const file = aiPrendaFotoInput.files[0];
                if (!file) {
                    Swal.fire('Error', 'Por favor, selecciona una imagen de la prenda primero.', 'error');
                    return;
                }

                // 1. Subir la imagen temporalmente (simulado o a un script que la guarde y devuelva la ruta)
                // Por la "mecánica de copiar y pegar", no subiremos la imagen real a un script de IA.
                // En su lugar, el prompt dirá a la IA "describe la imagen que te pegaré".
                // La imagen se subirá REALMENTE a 'save_prenda_from_ai.php' más tarde.

                // Aquí podríamos subir la imagen a un script PHP para obtener una URL temporal si tu IA lo permite.
                // O si la IA puede procesar imágenes directamente (como Gemini Vision), el usuario la pegaría allí.
                // Para este flujo "copiar y pegar prompt", asumimos que la imagen se verá visualmente en la IA por el usuario.

                // Por ahora, solo guardaremos el archivo localmente para la previsualización y la subida final.
                // La URL de la foto en el prompt es un placeholder para que la IA sepa qué tipo de input esperar.

                // Simular una subida temporal para obtener la URL para el prompt
                // En una app real, aquí enviarías la imagen a un script PHP que la guarda
                // y devuelve su URL pública. Por ahora, solo indicamos el nombre del archivo.
                const simulatedFileName = file.name;

                // 2. Generar el prompt con instrucciones para describir la imagen.
                const prompt = `Actúa como un experto en moda y un asistente de clóset. Necesito que describas una prenda de ropa basándote en la imagen que te voy a proporcionar. Por favor, sé extremadamente detallado y proporciona las características exactas para catalogarla en mi clóset inteligente.

                Tu respuesta debe ser un objeto JSON estricto con las siguientes propiedades. Si una característica no es clara en la imagen, omítela o usa "N/A" (No Aplicable):

                {
                "nombre": "Nombre descriptivo de la prenda (ej. Camiseta de algodón básica, Jeans ajustados, Chaqueta impermeable)",
                "tipo": "Tipo de prenda (ej. camiseta, camisa, pantalon, short, chaqueta, abrigo, zapatos, sandalias, vestido, accesorio)",
                "color_principal": "Color dominante (ej. Azul oscuro, Blanco, Negro, Naranja Salmón)",
                "tela": "Tipo de tela (ej. algodón, lino, mezclilla, poliéster, lana)",
                "textura": "Textura visual de la tela (ej. liso, rugoso, acanalado, punto)",
                "estampado": "Descripción del estampado (ej. rayas, flores, lunares, liso, gráfico)",
                "clima_apropiado": "Clima principal para usarla (ej. calor, frio, lluvia, todo)",
                "formalidad": "Nivel de formalidad (ej. casual, semi-formal, formal)",
                "comentarios": "Cualquier detalle adicional relevante (ej. 'mancha frontal', 'corte oversized', 'ideal para correr')"
                }
                `;

                aiPrendaPrompt.value = prompt;
                aiDescriptionSection.style.display = 'block'; // Mostrar la sección de descripción

                // Copiar el prompt al portapapeles
                aiPrendaPrompt.select();
                aiPrendaPrompt.setSelectionRange(0, 99999);
                try {
                    await navigator.clipboard.writeText(aiPrendaPrompt.value);
                    Swal.fire('¡Prompt Copiado!', 'Ahora, pega este prompt en tu IA y sube la imagen de la prenda.', 'success');
                } catch (err) {
                    console.error('Error al copiar el prompt:', err);
                    Swal.fire('Error', 'No se pudo copiar el prompt automáticamente. Por favor, cópialo manualmente.', 'error');
                }
            });

            // Listener para copiar el prompt manualmente
            copyPrendaPromptBtn.addEventListener('click', async () => {
                aiPrendaPrompt.select();
                aiPrendaPrompt.setSelectionRange(0, 99999);
                try {
                    await navigator.clipboard.writeText(aiPrendaPrompt.value);
                    Swal.fire('¡Copiado!', 'El prompt ha sido copiado al portapapeles.', 'success');
                } catch (err) {
                    console.error('Error al copiar el prompt:', err);
                    Swal.fire('Error', 'No se pudo copiar el prompt automáticamente. Por favor, cópialo manualmente.', 'error');
                }
            });


            // Función para procesar la respuesta de la IA y autocompletar campos

            // Listener para el botón "Guardar Prenda en Clóset" (final)
            if (guardarPrendaIABtn) {
                guardarPrendaIABtn.addEventListener('click', async (e) => {
                    e.preventDefault(); // Esto es un click listener, así que prevenimos el submit si es un botón type="submit"

                    // Validar que la foto esté seleccionada
                    const iaPrendaFotoInput = document.getElementById('iaPrendaFotoAutomatica') || document.getElementById('iaPrendaFoto'); // Asegúrate del ID correcto
                    const fotoFile = iaPrendaFotoInput.files[0];
                    if (!fotoFile) {
                        Swal.fire('Error', 'Debes seleccionar una imagen de la prenda.', 'error');
                        return;
                    }

                    // Validar campos autocompletados (nombre, tipo)
                    const iaNombre = document.getElementById('iaNombre').value.trim();
                    const iaTipo = document.getElementById('iaTipo').value.trim();
                    if (!iaNombre || !iaTipo) {
                        Swal.fire('Error', 'Los campos "Nombre" y "Tipo" son obligatorios (autocompletados por IA).', 'error');
                        return;
                    }

                    const formData2 = new FormData();
                    formData2.append('foto', fotoFile);
                    formData2.append('nombre', iaNombre); // Usar 'nombre' para consistencia con backend
                    formData2.append('tipo', iaTipo);
                    formData2.append('color_principal_ia', document.getElementById('iaColorPrincipal').value.trim());
                    formData2.append('tela_ia', document.getElementById('iaTela').value.trim());
                    formData2.append('textura_ia', document.getElementById('iaTextura').value.trim());
                    formData2.append('estampado_ia', document.getElementById('iaEstampado').value.trim());
                    formData2.append('clima_apropiado_ia', document.getElementById('iaClimaApropiado').value.trim());
                    formData2.append('formalidad_ia', document.getElementById('iaFormalidad').value.trim());
                    formData2.append('comentarios_ia', document.getElementById('iaComentarios').value.trim());

                    sendPrendaRequest(formData2, 'save_prenda_from_ai.php'); // Llama a la función unificada
                });
            }
        });

        // Función para filtrar prendas en el formulario de creación de outfits
        function filterOutfitPrendas() {
            const searchTerm = document.getElementById('buscarPrendasOutfit').value.toLowerCase();
            const prendaItems = document.querySelectorAll('#listaPrendasOutfit .prenda-outfit-item'); // Seleccionar solo los ítems dentro del contenedor

            prendaItems.forEach(item => {
                const nombre = item.getAttribute('data-nombre-prenda');
                const tipo = item.getAttribute('data-tipo-prenda');
                const color = item.getAttribute('data-color-prenda');

                // Combinar los atributos para la búsqueda
                const fullText = `${nombre} ${tipo} ${color}`;

                if (fullText.includes(searchTerm)) {
                    item.style.display = ''; // Mostrar el ítem
                } else {
                    item.style.display = 'none'; // Ocultar el ítem
                }
            });
        }
    </script>
    <script>
        function toggleOutfitForm() {
            const formContainer = document.getElementById('outfitFormContainer');
            const toggleBtn = document.getElementById('toggleOutfitFormBtn');

            if (formContainer.style.display === 'none') {
                formContainer.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash me-2"></i>Ocultar Formulario';
            } else {
                formContainer.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-eye me-2"></i>Mostrar Formulario';
            }
        }
    </script>
</body>

</html>