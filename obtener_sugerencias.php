<?php
header('Content-Type: application/json');
include 'bd.php';

$city = $_GET['city'] ?? 'Santiago';
$api_key = "22524bcc23b8c0635c013a41f40f6a4c";

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

$temp_c = $data['main']['temp'] ?? 25;
$weather_desc = $data['weather'][0]['description'] ?? 'No disponible';
$icon_code = $data['weather'][0]['icon'] ?? '';
$icon_url = "http://openweathermap.org/img/wn/{$icon_code}@2x.png";

// Clasificación simple para seleccionar prendas
$weather_lower = strtolower($weather_desc);
if (strpos($weather_lower, 'lluvia') !== false || strpos($weather_lower, 'tormenta') !== false) {
    $clima_categoria = 'lluvia';
} elseif ($temp_c < 20) {
    $clima_categoria = 'frio';
} else {
    $clima_categoria = 'calor';
}

// Consulta prendas según clima
$sql = "SELECT * FROM prendas WHERE estado='disponible' AND clima_apropiado IN (?,'todo')";
$stmt = $mysqli_obj->prepare($sql);
if (!$stmt) {
    echo json_encode([]);
    exit;
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

// Seleccionar una prenda al azar por tipo
$sugerencia = [];
foreach ($prendas_sugeridas as $tipo => $items) {
    if (!empty($items)) {
        $sugerencia[$tipo] = $items[array_rand($items)];
    }
}

$sugerencias = [];
if (!empty($sugerencia)) {
    $sugerencias[] = [
        "titulo" => "Outfit sugerido según clima en $city",
        "descripcion" => "Basado en tus prendas disponibles y el clima actual ($weather_desc, {$temp_c}°C)",
        "prendas" => array_values($sugerencia),
        "tips" => "Recuerda revisar el clima antes de salir",
        "icono_clima" => $icon_url
    ];
}

echo json_encode($sugerencias, JSON_UNESCAPED_UNICODE);
