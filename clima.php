<?php
date_default_timezone_set('America/Santiago');

$city = $_GET['city'] ?? 'Santiago';
$api_key = '22524bcc23b8c0635c013a41f40f6a4c';
$units = 'metric';
$lang = 'es';

// Obtener coordenadas actuales
$api_url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=$api_key&units=$units&lang=$lang";
$data = @file_get_contents($api_url);
$json = $data ? json_decode($data, true) : null;

if (!$json || empty($json['coord'])) {
    echo "Error al obtener coordenadas.";
    exit;
}

$lat = $json['coord']['lat'];
$lon = $json['coord']['lon'];

// Obtener pronóstico
$forecast_url = "https://api.openweathermap.org/data/2.5/forecast?lat=$lat&lon=$lon&appid=$api_key&units=$units&lang=$lang";
$data = @file_get_contents($forecast_url);
$forecast = $data ? json_decode($data, true) : null;

if (!$forecast || empty($forecast['list'])) {
    echo "Error al obtener el pronóstico.";
    exit;
}

// Definir horarios objetivo (hora exacta o alternativas)
$target_schedule = [
    '5 AM' => ['05'],
    '5 PM' => ['17'],
    '10 PM (o cercanos)' => ['22', '23', '21']
];

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
$resultados = [];

foreach ($target_schedule as $label => $hour_options) {
    $encontrado = false;

    foreach ($hour_options as $target_hour) {
        foreach ($forecast['list'] as $item) {
            $utc_time = $item['dt_txt'] ?? '';
            if (!$utc_time) continue;

            $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Santiago'));

            $local_date = $dt->format('Y-m-d');
            $local_hour = $dt->format('H');

            if ($local_date === $tomorrow && $local_hour === $target_hour) {
                $hora_legible = $dt->format('g A');
                $temp = round($item['main']['temp']) . "°C";
                $resultados[] = "$label ($hora_legible): $temp";
                $encontrado = true;
                break 2; // salir de ambos bucles
            }
        }
    }

    if (!$encontrado) {
        $resultados[] = "$label: No disponible";
    }
}

// Mostrar resultados
echo "<h3>Temperaturas para mañana en $city:</h3>";
echo "<ul>";
foreach ($resultados as $linea) {
    echo "<li>$linea</li>";
}
echo "</ul>";
?>
