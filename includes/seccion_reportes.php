<?php
// Consulta: Top Prendas más usadas (Excluyendo pijamas)
$sqlMasUsadas = "SELECT p.nombre, COUNT(dv.id) AS Total_Usos 
                 FROM prendas p 
                 LEFT JOIN historial_usos dv ON dv.prenda_id = p.id 
                 WHERE p.tipo != 'pijama' 
                 GROUP BY p.id, p.nombre 
                 ORDER BY Total_Usos DESC 
                 LIMIT 50";

$resMasUsadas = $mysqli_obj->query($sqlMasUsadas);
$nombresPrendas = [];
$conteoUsos = [];

while ($row = $resMasUsadas->fetch_assoc()) {
    // Si el nombre es muy largo, lo acortamos para el gráfico
    $nombreCorto = (strlen($row['nombre']) > 20) ? substr($row['nombre'], 0, 18) . '..' : $row['nombre'];
    $nombresPrendas[] = $nombreCorto;
    $conteoUsos[] = $row['Total_Usos'];
}

// Consulta 2: Tipos de prendas disponibles

// Consulta mejorada: Cuenta prendas agrupadas por tipo
$sqlTipos = "SELECT tipo, COUNT(*) as cantidad FROM prendas GROUP BY tipo";
$resTipos = $mysqli_obj->query($sqlTipos);

$labelsTipos = [];
$datosCantidades = [];

while ($row = $resTipos->fetch_assoc()) {
    $labelsTipos[] = ucfirst($row['tipo']); // Capitaliza la primera letra (ej: 'pijama' -> 'Pijama')
    $datosCantidades[] = (int)$row['cantidad'];
}

// 1. Total de prendas
$resTotal = $mysqli_obj->query("SELECT COUNT(*) as total FROM prendas");
$totalPrendas = $resTotal->fetch_assoc()['total'];

// 2. Prendas usadas en los últimos 30 días
$resUsadas = $mysqli_obj->query("SELECT COUNT(DISTINCT prenda_id) as usadas FROM historial_usos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$usadasReciente = $resUsadas->fetch_assoc()['usadas'];

$porcentajeRotacion = ($totalPrendas > 0) ? round(($usadasReciente / $totalPrendas) * 100, 1) : 0;


// 1. Las 5 prendas MÁS usadas
$sqlMas = "SELECT p.nombre, COUNT(hv.id) AS total 
           FROM prendas p 
           LEFT JOIN historial_usos hv ON p.id = hv.prenda_id 
           GROUP BY p.id, p.nombre 
           ORDER BY total DESC 
           LIMIT 5";
$resMas = $mysqli_obj->query($sqlMas);
$masUsadas = [];
while ($row = $resMas->fetch_assoc()) {
    $masUsadas[] = $row;
}

// 2. Las 5 prendas MENOS usadas (Olvidadas)
$sqlMenos = "SELECT p.nombre, COUNT(hv.id) AS total 
             FROM prendas p 
             LEFT JOIN historial_usos hv ON p.id = hv.prenda_id 
             GROUP BY p.id, p.nombre 
             ORDER BY total ASC 
             LIMIT 5";
$resMenos = $mysqli_obj->query($sqlMenos);
$menosUsadas = [];
while ($row = $resMenos->fetch_assoc()) {
    $menosUsadas[] = $row;
}

// Combinamos nombres y datos para el gráfico
$nombresUsoOlvido = array_merge(
    array_column($masUsadas, 'nombre'),
    array_column($menosUsadas, 'nombre')
);
$datosUsoOlvido = array_merge(
    array_column($masUsadas, 'total'),
    array_column($menosUsadas, 'total')
);

$categoriasBase = [
    'Negro'    => '#212121',
    'Gris'     => '#9e9e9e',
    'Azul'     => '#1e88e5',
    'Rojo'     => '#e53935',
    'Blanco'   => '#f5f5f5',
    'Verde'    => '#2e7d32',
    'Rosa'     => '#f06292',
    'Naranja'  => '#ff8a65',
    'Beige'    => '#f5f5dc',
    'Marrón'   => '#795548',
    'Vino'     => '#880e4f',
    'Borgoña'  => '#800020',
    'Turquesa' => '#40E0D0'
];

// Cambiamos la SQL: Solo agrupamos por color_principal para sumar todas las prendas de ese tono
$sqlColores = "SELECT color_principal, COUNT(*) as cantidad 
               FROM prendas 
               WHERE color_principal IS NOT NULL AND color_principal != '' 
               GROUP BY color_principal";

$resColores = $mysqli_obj->query($sqlColores);
$conteoAgrupado = [];

while ($row = $resColores->fetch_assoc()) {
    $colorDB = mb_strtolower($row['color_principal']);
    $cantidad = (int)$row['cantidad'];
    $encontrado = false;

    foreach ($categoriasBase as $nombreBase => $hex) {
        // Si el color de la base está contenido en el texto de la DB (ej: "azul marino" contiene "azul")
        if (strpos($colorDB, mb_strtolower($nombreBase)) !== false) {
            if (!isset($conteoAgrupado[$nombreBase])) {
                $conteoAgrupado[$nombreBase] = 0;
            }
            $conteoAgrupado[$nombreBase] += $cantidad;
            $encontrado = true;
            break;
        }
    }

    if (!$encontrado) {
        if (!isset($conteoAgrupado['Otros'])) $conteoAgrupado['Otros'] = 0;
        $conteoAgrupado['Otros'] += $cantidad;
    }
}

arsort($conteoAgrupado);

// Formateamos EXACTAMENTE como lo pide ECharts (Array de Objetos)
$dataParaJS = [];
foreach ($conteoAgrupado as $nombre => $total) {
    $dataParaJS[] = [
        'name' => $nombre,
        'value' => $total,
        'itemStyle' => [
            'color' => $categoriasBase[$nombre] ?? '#bdc3c7'
        ]
    ];
}



// Inicializamos los ejes
$radarEjes = [
    'Formal'      => 0,
    'Semi-formal' => 0,
    'Casual'      => 0,
    'Deportivo'   => 0,
    'Exterior'    => 0
];

// Consulta que trae formalidad y tipo
$sqlRadar = "SELECT formalidad, tipo, COUNT(*) as cantidad FROM prendas GROUP BY formalidad, tipo";
$resRadar = $mysqli_obj->query($sqlRadar);

while ($row = $resRadar->fetch_assoc()) {
    $formalidad = mb_strtolower($row['formalidad'] ?? '');
    $tipo = mb_strtolower($row['tipo'] ?? '');
    $cant = (int)$row['cantidad'];

    // 1. Mapeo directo por tu campo 'formalidad'
    if ($formalidad == 'formal') $radarEjes['Formal'] += $cant;
    if ($formalidad == 'semi-formal') $radarEjes['Semi-formal'] += $cant;
    if ($formalidad == 'casual') $radarEjes['Casual'] += $cant;

    // 2. Mapeo complementario por 'tipo' para completar el radar
    if (strpos($tipo, 'zapatillas') !== false || strpos($tipo, 'short') !== false) {
        $radarEjes['Deportivo'] += $cant;
    }
    if (strpos($tipo, 'chaqueta') !== false || strpos($tipo, 'suéter') !== false || strpos($tipo, 'abrigo') !== false) {
        $radarEjes['Exterior'] += $cant;
    }
}

$valoresRadar = array_values($radarEjes);
$maxValor = max($valoresRadar) > 0 ? max($valoresRadar) + 2 : 10;

// Obtenemos el conteo de registros por cada día del último año
$sqlHeatmap = "SELECT DATE(fecha) as dia, COUNT(*) as cantidad 
               FROM historial_usos 
               WHERE fecha >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
               GROUP BY DATE(fecha)";
$resHeatmap = $mysqli_obj->query($sqlHeatmap);

$dataHeatmap = [];
while ($row = $resHeatmap->fetch_assoc()) {
    // ECharts necesita el formato [fecha, cantidad]
    $dataHeatmap[] = [$row['dia'], (int)$row['cantidad']];
}

// Año actual para el calendario
$currentYear = date('Y');

// Consulta que une el inventario con el historial de usos
$sqlTreemap = "SELECT 
                p.tipo, 
                COUNT(DISTINCT p.id) as total_prendas,
                COUNT(h.id) as total_usos
               FROM prendas p
               LEFT JOIN historial_usos h ON p.id = h.prenda_id
               GROUP BY p.tipo;";

$resTreemap = $mysqli_obj->query($sqlTreemap);
$dataTreemap = [];

while ($row = $resTreemap->fetch_assoc()) {
    $tipo = ucfirst(mb_strtolower($row['tipo'] ?? 'Desconocido'));
    $total = (int)$row['total_prendas'];
    $usos = (int)$row['total_usos'];

    // El 'valor' del cuadro es el número de prendas (tamaño)
    // Guardamos los usos para el color y el tooltip
    $dataTreemap[] = [
        'name' => $tipo,
        'value' => $total,
        'usos' => $usos
    ];
}


$consejosIA = [];

// 1. Análisis de Rotación (Basado en tu Gauge)
if ($porcentajeRotacion < 30) {
    $consejosIA[] = "⚠️ <b>Baja Rotación:</b> Estás usando menos del 30% de tu closet. Tienes 'ropa dormida' que podrías empezar a rotar mañana mismo.";
}

// 2. Análisis de Versatilidad (Basado en el Radar)
if ($radarEjes['Formal'] == 0) {
    $consejosIA[] = "👔 <b>Vacío de Estilo:</b> No tienes prendas marcadas como 'Formal'. Si tienes un evento importante pronto, estarás en problemas.";
}

/*
// 3. Análisis de Color (Basado en Colorimetría)
$colorDominante = $labelsFinales[0]; // El primero tras el arsort
if ($datosFinales[0] > (array_sum($datosFinales) * 0.5)) {
    $consejosIA[] = "🎨 <b>Monocromía detectada:</b> Más de la mitad de tu closet es de color <b>$colorDominante</b>. ¿Has pensado en probar tonos complementarios?";
}
    */

// 4. Análisis de Eficiencia (Basado en el Treemap)
foreach ($dataTreemap as $item) {
    if ($item['value'] > 5 && $item['usos'] == 0) {
        $consejosIA[] = "📦 <b>Exceso Crítico:</b> Tienes {$item['value']} prendas de tipo '{$item['name']}' que NUNCA has usado. Considera donarlas o venderlas.";
    }
}
// Consultamos las relaciones entre tipo y formalidad
$sqlSankey = "SELECT tipo, formalidad, COUNT(*) as cantidad FROM prendas GROUP BY tipo, formalidad";
$resSankey = $mysqli_obj->query($sqlSankey);

$nodes = [];
$links = [];
$tempNodes = [];

while ($row = $resSankey->fetch_assoc()) {
    $source = ucfirst(mb_strtolower($row['tipo']));
    $target = ucfirst(mb_strtolower($row['formalidad']));
    $value = (int)$row['cantidad'];

    // Registrar nodos únicos
    if (!in_array($source, $tempNodes)) $tempNodes[] = $source;
    if (!in_array($target, $tempNodes)) $tempNodes[] = $target;

    $links[] = ['source' => $source, 'target' => $target, 'value' => $value];
}

foreach ($tempNodes as $node) {
    $nodes[] = ['name' => $node];
}

// Obtenemos días desde que se agregó la prenda y sus usos totales
$sqlScatter = "SELECT 
                    p.nombre, 
                    DATEDIFF(NOW(), p.fecha_agregado) as dias_antiguedad, 
                    COUNT(h.id) as usos_totales 
               FROM prendas p
               LEFT JOIN historial_usos h ON p.id = h.prenda_id
               GROUP BY p.id;";
$resScatter = $mysqli_obj->query($sqlScatter);
$dataScatter = [];

while ($row = $resScatter->fetch_assoc()) {
    // [X: Antigüedad, Y: Usos, Nombre]
    $dataScatter[] = [
        (int)$row['dias_antiguedad'],
        (int)$row['usos_totales'],
        $row['nombre']
    ];
}

?>

<div class="tab-pane fade" id="reportes">
    <div class="container mt-4">

        <div class="col-12 mb-4">
            <div class="card border-0 bg-gradient-to-r from-emerald-600 to-teal-700 shadow-lg rounded-xl">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-white/20 p-2 rounded-lg me-3">
                            <i class="fas fa-brain fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-bold">Diagnóstico del Closet Inteligente</h5>
                            <p class=" text-xs mb-0">Basado en el análisis de tus 8 reportes activos</p>
                        </div>

                        <div class="row">
                            <?php if (empty($consejosIA)): ?>
                                <p class="mb-0 italic">
                                    <i class="fas fa-check-circle me-2"></i>
                                    ¡Tu closet está perfectamente equilibrado! Sigue así.
                                </p>
                            <?php else: ?>
                                <?php foreach ($consejosIA as $consejo): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="bg-white/10 p-2 rounded border border-white/10 text-sm">
                                            <?php echo $consejo; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-sync me-2 text-green-500"></i>Uso del Closet (30 días)
                        </h6>
                        <p class="text-xs text-gray-400">Porcentaje de prendas que has rotado este mes</p>
                        <div id="echartGauge" style="width: 100%; min-height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-th-large me-2 text-blue-500"></i>Distribución por Tipo
                        </h6>
                        <p class="text-xs text-gray-400">Variedad de categorías en tu closet actual</p>
                        <div id="echartPie" style="width: 100%; min-height: 300px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">

                <div class="card border-0 shadow-sm rounded-xl" style="background: linear-gradient(to bottom, #ffffff, #f9fafb);">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-palette me-2 text-indigo-500"></i>Paleta de Estilo
                        </h6>
                        <p class="text-xs text-gray-400 mb-3">Distribución cromática de tu armario</p>
                        <div id="echartColorimetria" style="width: 100%; min-height: 350px;"></div>
                    </div>
                </div>

            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-spider me-2 text-indigo-500"></i>Radar de Versatilidad
                        </h6>
                        <p class="text-xs text-gray-400 mb-3">Equilibrio de estilos en tu armario</p>
                        <div id="echartRadar" style="width: 100%; min-height: 350px;"></div>
                    </div>

                </div>
            </div>

            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-th-large me-2 text-green-500"></i>Mapa de Eficiencia por Categoría
                        </h6>
                        <p class="text-xs text-gray-400 mb-3">Tamaño = Cantidad de ropa | Color = Intensidad de uso</p>
                        <div id="echartTreemap" style="width: 100%; min-height: 400px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-fire me-2 text-red-500"></i>Ranking de Actividad
                        </h6>
                        <p class="text-xs text-gray-400">Listado de las 50 prendas con más registros</p>
                        <div id="echartBarras" style="width: 100%; min-height: 500px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-balance-scale me-2 text-blue-500"></i>Uso vs. Olvido
                        </h6>
                        <p class="text-xs text-gray-400">Comparativa: Top 5 más usadas vs. 5 menos usadas</p>
                        <div id="echartUsoOlvido" style="width: 100%; min-height: 500px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-7 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">Flujo de Estilo (Tipo → Estilo)</h6>
                        <div id="echartSankey" style="width: 100%; min-height: 400px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-5 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">Novedad vs. Olvido</h6>
                        <div id="echartScatter" style="width: 100%; min-height: 400px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-calendar-alt me-2 text-red-500"></i>Consistencia de Registro
                        </h6>
                        <p class="text-xs text-gray-400 mb-3">Intensidad de uso de la aplicación por día</p>
                        <div id="echartHeatmap" style="width: 100%; min-height: 250px;"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Definimos las OPCIONES (pero no inicializamos el gráfico aún)
        const optionPie = {
            tooltip: {
                trigger: 'item',
                formatter: '{b}: {c} ({d}%)'
            },
            legend: {
                type: 'scroll', // Añadido scroll por si hay muchos tipos
                bottom: '0%',
                left: 'center'
            },
            series: [{
                name: 'Tipo de Prenda',
                type: 'pie',
                radius: ['50%', '80%'],
                avoidLabelOverlap: false,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#fff',
                    borderWidth: 2
                },
                label: {
                    show: false,
                    position: 'center'
                },
                emphasis: {
                    label: {
                        show: true,
                        fontSize: '18',
                        fontWeight: 'bold'
                    }
                },
                data: <?php
                        $dataPie = [];
                        for ($i = 0; $i < count($labelsTipos); $i++) {
                            $dataPie[] = ['name' => $labelsTipos[$i], 'value' => $datosCantidades[$i]];
                        }
                        echo json_encode($dataPie);
                        ?>
            }]
        };

        const optionBarras = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            grid: {
                left: '3%',
                right: '10%',
                bottom: '3%',
                containLabel: true
            }, // Aumentado margen derecho
            xAxis: {
                type: 'value',
                boundaryGap: [0, 0.01]
            },
            yAxis: {
                type: 'category',
                data: <?php echo json_encode(array_reverse($nombresPrendas)); ?>,
                inverse: false
            },
            dataZoom: [{
                    type: 'inside',
                    start: 70,
                    end: 100,
                    yAxisIndex: 0
                },
                {
                    type: 'slider',
                    yAxisIndex: 0,
                    right: 10
                }
            ],
            series: [{
                name: 'Usos',
                type: 'bar',
                data: <?php echo json_encode(array_reverse($conteoUsos)); ?>,
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [{
                            offset: 0,
                            color: '#83a4d4'
                        },
                        {
                            offset: 1,
                            color: '#b6fbff'
                        }
                    ]),
                    borderRadius: [0, 5, 5, 0]
                }
            }]

        };

        const optionGauge = {
            series: [{
                type: 'gauge',
                startAngle: 180,
                endAngle: 0,
                min: 0,
                max: 100,
                itemStyle: {
                    color: '#58D68D',
                    shadowColor: 'rgba(0,138,255,0.45)',
                    shadowBlur: 10,
                    shadowOffsetX: 2,
                    shadowOffsetY: 2
                },
                progress: {
                    show: true,
                    roundCap: true,
                    width: 18
                },
                pointer: {
                    show: false
                },
                axisLine: {
                    roundCap: true,
                    lineStyle: {
                        width: 18
                    }
                },
                axisTick: {
                    show: false
                },
                splitLine: {
                    show: false
                },
                axisLabel: {
                    show: false
                },
                detail: {
                    valueAnimation: true,
                    formatter: '{value}%',
                    fontSize: 30,
                    offsetCenter: [0, '0%'],
                    color: '#4b5563'
                },
                data: [{
                    value: <?php echo $porcentajeRotacion; ?>,
                    name: 'Rotación'
                }]
            }]
        };

        const optionUsoOlvido = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            grid: {
                left: '3%',
                right: '10%', // Aumentado para que no se corte la etiqueta de usos
                bottom: '3%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                name: 'Usos',
                splitLine: {
                    lineStyle: {
                        type: 'dashed'
                    }
                }
            },
            yAxis: {
                type: 'category',
                // Invertimos para que las más usadas aparezcan arriba
                data: <?php echo json_encode(array_reverse($nombresUsoOlvido)); ?>,
                axisLabel: {
                    fontSize: 11,
                    fontWeight: 'bold',
                    // Color de texto gris oscuro para mejor lectura
                    color: '#374151'
                }
            },
            series: [{
                name: 'Veces Usada',
                type: 'bar',
                barWidth: '60%',
                data: <?php echo json_encode(array_reverse($datosUsoOlvido)); ?>,
                itemStyle: {
                    // Lógica de color mejorada
                    color: function(params) {
                        /* Al usar array_reverse:
                           - Los índices 0 a 4 son las MENOS usadas (abajo en el gráfico)
                           - Los índices 5 a 9 son las MÁS usadas (arriba en el gráfico)
                        */
                        return params.dataIndex < 5 ? '#f87171' : '#34d399';
                    },
                    borderRadius: [0, 4, 4, 0], // Bordes redondeados en la punta derecha
                    shadowBlur: 2,
                    shadowColor: 'rgba(0,0,0,0.1)'
                },
                label: {
                    show: true,
                    position: 'right',
                    formatter: '{c}', // Muestra el número de usos al final de la barra
                    fontWeight: 'bold',
                    color: '#4b5563'
                },
                // Efecto visual al pasar el mouse
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(0,0,0,0.3)'
                    }
                }
            }]
        };

        const optionColorimetria = {
            tooltip: {
                trigger: 'item',
                formatter: '{b}: <b>{c} prendas</b> ({d}%)'
            },
            series: [{
                name: 'Colores',
                type: 'pie',
                radius: ['45%', '75%'], // Hueco central para que parezca una dona
                avoidLabelOverlap: true,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#fff',
                    borderWidth: 2
                },
                label: {
                    show: false, // Ocultamos labels fuera para que no se amontonen
                    position: 'center'
                },
                emphasis: {
                    label: {
                        show: true,
                        fontSize: 18,
                        fontWeight: 'bold',
                        formatter: '{b}'
                    }
                },
                data: <?php echo json_encode($dataParaJS); ?>
            }]
        };

        const optionRadar = {
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    const labels = ['Formal', 'Semi-Formal', 'Casual', 'Deportivo', 'Exterior'];

                    let texto = `<strong>${params.name}</strong><br>`;

                    params.value.forEach((val, i) => {
                        texto += `${labels[i]}: ${val}<br>`;
                    });

                    return texto;
                }
            },


            radar: {
                indicator: [{
                        name: 'Formal',
                        max: <?php echo $maxValor; ?>
                    },
                    {
                        name: 'Semi-Formal',
                        max: <?php echo $maxValor; ?>
                    },
                    {
                        name: 'Casual',
                        max: <?php echo $maxValor; ?>
                    },
                    {
                        name: 'Deportivo',
                        max: <?php echo $maxValor; ?>
                    },
                    {
                        name: 'Exterior',
                        max: <?php echo $maxValor; ?>
                    }
                ],
                radius: '65%',
                axisName: {
                    color: '#374151',
                    backgroundColor: '#f3f4f6',
                    borderRadius: 3,
                    padding: [3, 5]
                },
                splitLine: {
                    lineStyle: {
                        color: '#e5e7eb'
                    }
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: ['#fff', '#f9fafb']
                    }
                }
            },

            series: [{
                name: 'Estilos',
                type: 'radar',
                data: [{
                    value: <?php echo json_encode($valoresRadar); ?>,
                    name: 'Mi Perfil de Estilo',
                    areaStyle: {
                        color: new echarts.graphic.RadialGradient(0.5, 0.5, 1, [{
                                color: 'rgba(99, 102, 241, 0.1)',
                                offset: 0
                            },
                            {
                                color: 'rgba(99, 102, 241, 0.6)',
                                offset: 1
                            }
                        ])
                    },
                    lineStyle: {
                        color: '#6366f1',
                        width: 3
                    },
                    itemStyle: {
                        color: '#4f46e5'
                    }
                }]
            }]
        };

        const optionHeatmap = {
            tooltip: {
                position: 'top',
                formatter: function(p) {
                    const format = echarts.format.formatTime('yyyy-MM-dd', p.data[0]);
                    return `<b>${format}</b><br/>${p.data[1]} prendas usadas`;
                }
            },
            visualMap: {
                min: 0,
                max: 10, // Ajusta esto según el máximo de prendas que sueles usar al día
                type: 'piecewise',
                orient: 'horizontal',
                left: 'center',
                top: 0,
                pieces: [{
                        min: 0,
                        max: 0,
                        label: 'Sin uso',
                        color: '#ebedf0'
                    },
                    {
                        min: 1,
                        max: 2,
                        label: 'Bajo',
                        color: '#9be9a8'
                    },
                    {
                        min: 3,
                        max: 5,
                        label: 'Medio',
                        color: '#40c463'
                    },
                    {
                        min: 6,
                        max: 10,
                        label: 'Alto',
                        color: '#216e39'
                    }
                ]
            },
            calendar: {
                top: 60,
                left: 30,
                right: 30,
                cellSize: ['auto', 13],
                range: '<?php echo $currentYear; ?>',
                itemStyle: {
                    borderWidth: 0.5
                },
                yearLabel: {
                    show: false
                },
                dayLabel: {
                    firstDay: 1,
                    nameMap: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
                },
                monthLabel: {
                    // Definición manual de meses en español
                    nameMap: [
                        'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                        'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'
                    ],
                    fontSize: 11
                }
            },
            series: {
                type: 'heatmap',
                coordinateSystem: 'calendar',
                data: <?php echo json_encode($dataHeatmap); ?>,
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }
        };

        const dataTreemapRaw = <?php echo json_encode($dataTreemap); ?>;
        const optionTreemap = {
            tooltip: {
                formatter: function(info) {
                    return [
                        '<div class="tooltip-title">' + info.name + '</div>',
                        'Cantidad: ' + info.value + ' prendas<br>',
                        'Uso Total: ' + info.data.usos + ' veces'
                    ].join('');
                }
            },
            series: [{
                name: 'Categorías',
                type: 'treemap',
                visibleMin: 300,
                label: {
                    show: true,
                    formatter: '{b}'
                },
                itemStyle: {
                    borderColor: '#fff'
                },
                // Configuración de niveles para el color
                levels: [{
                    itemStyle: {
                        borderGapWidth: 1
                    },
                    upperLabel: {
                        show: false
                    }
                }],
                data: dataTreemapRaw.map((item, index) => {
                    // Definimos una paleta de colores moderna
                    const palette = [
                        '#5470c6', '#91cc75', '#fac858', '#ee6666',
                        '#73c0de', '#3ba272', '#fc8452', '#9a60b4'
                    ];

                    return {
                        name: item.name,
                        value: item.value,
                        usos: item.usos,
                        itemStyle: {
                            // El color base viene de la paleta
                            color: palette[index % palette.length],

                            // La opacidad varía según el uso:
                            // Si tiene > 10 usos, opacidad 1 (sólido)
                            // Si tiene entre 2 y 10, opacidad 0.6 (medio)
                            // Si tiene 0-1, opacidad 0.3 (casi transparente/lavado)
                            opacity: item.usos > 10 ? 1 : (item.usos > 2 ? 0.6 : 0.3)
                        },
                        label: {
                            show: true,
                            // Mostramos el nombre, cantidad y usos para que quede claro
                            formatter: '{b}\n({c} prendas)\n Usos:' + item.usos,
                            fontSize: 12,
                            fontWeight: 'bold',
                            color: item.usos > 2 ? '#fff' : '#444' // Texto oscuro si el fondo es muy transparente
                        }
                    };
                })

            }]
        };


        const optionSankey = {
            tooltip: {
                trigger: 'item',
                triggerOn: 'mousemove'
            },
            series: [{
                type: 'sankey',
                data: <?php echo json_encode($nodes); ?>,
                links: <?php echo json_encode($links); ?>,
                emphasis: {
                    focus: 'adjacency'
                },
                lineStyle: {
                    color: 'gradient',
                    curveness: 0.5
                }
            }]
        };

        const optionScatter = {
            title: {
                text: 'Uso según Antigüedad',
                left: 'center'
            },
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    return `<b>${params.data[2]}</b><br/>Antigüedad: ${params.data[0]} días<br/>Usos: ${params.data[1]}`;
                }
            },
            // --- NUEVO: Herramientas de Zoom ---
            dataZoom: [{
                    type: 'inside',
                    xAxisIndex: 0
                }, // Zoom con scroll del mouse
                {
                    type: 'slider',
                    xAxisIndex: 0,
                    bottom: 10
                } // Barra inferior para deslizar
            ],
            // --- NUEVO: Escala de colores inteligente ---
            visualMap: {
                min: 0,
                max: 50, // Ajusta según tu uso máximo promedio
                dimension: 1, // El color depende del eje Y (Usos)
                orient: 'vertical',
                right: 10,
                top: 'center',
                text: ['Mucho Uso', 'Poco Uso'],
                calculable: true,
                inRange: {
                    color: ['#f87171', '#fbbf24', '#34d399'] // Rojo (poco uso) -> Amarillo -> Verde (mucho uso)
                }
            },
            xAxis: {
                name: 'Días',
                splitLine: {
                    lineStyle: {
                        type: 'dashed'
                    }
                },
                scale: true // Esto hace que el eje no empiece en 0 si no es necesario
            },
            yAxis: {
                name: 'Usos Totales'
            },
            series: [{
                symbolSize: function(data) {
                    // El punto se hace un poco más grande si se usa mucho
                    return Math.sqrt(data[1]) * 5 + 10;
                },
                data: <?php echo json_encode($dataScatter); ?>,
                type: 'scatter',
                itemStyle: {
                    opacity: 0.8,
                    shadowBlur: 10,
                    shadowColor: 'rgba(0, 0, 0, 0.2)'
                }
            }]
        };

        // 2. Variables para los gráficos
        let chartPie;
        let chartBarras;
        let chartsInitialized = false;

        // 3. EVENTO CLAVE: Solo inicializar cuando la pestaña se muestra
        document.querySelector('a[href="#reportes"]').addEventListener('shown.bs.tab', function() {
            if (!chartsInitialized) {
                // Inicializamos ahora que el contenedor es VISIBLE
                chartPie = echarts.init(document.getElementById('echartPie'));
                chartBarras = echarts.init(document.getElementById('echartBarras'));
                chartGauge = echarts.init(document.getElementById('echartGauge'));
                chartUsoOlvido = echarts.init(document.getElementById('echartUsoOlvido'));
                chartColorimetria = echarts.init(document.getElementById('echartColorimetria'));
                chartRadar = echarts.init(document.getElementById('echartRadar'));
                chartHeatmap = echarts.init(document.getElementById('echartHeatmap'));
                chartTreemap = echarts.init(document.getElementById('echartTreemap'));
                const chartSankey = echarts.init(document.getElementById('echartSankey'));
                const chartScatter = echarts.init(document.getElementById('echartScatter'));

                chartPie.setOption(optionPie);
                chartBarras.setOption(optionBarras);
                chartGauge.setOption(optionGauge);
                chartUsoOlvido.setOption(optionUsoOlvido);
                chartColorimetria.setOption(optionColorimetria);
                chartRadar.setOption(optionRadar);
                chartHeatmap.setOption(optionHeatmap);
                chartTreemap.setOption(optionTreemap);
                chartSankey.setOption(optionSankey);
                chartScatter.setOption(optionScatter);

                chartsInitialized = true;
            } else {
                // Si ya existen, solo los ajustamos al tamaño
                chartPie.resize();
                chartBarras.resize();
            }
        });

        // Ajustar al cambiar tamaño de ventana
        window.addEventListener('resize', function() {
            if (chartsInitialized) {
                chartPie.resize();
                chartBarras.resize();
            }
        });
    });
</script>