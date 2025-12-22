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
    'Negro'  => '#212121',
    'Gris'   => '#9e9e9e',
    'Azul'   => '#1e88e5',
    'Rojo'   => '#e53935',
    'Blanco' => '#f5f5f5',
    'Verde'  => '#2e7d32',
    'Rosa'   => '#f06292',
    'Naranja' => '#ff8a65',
    'Beige'  => '#f5f5dc',
    'Marrón' => '#795548',
    'Vino'   => '#880e4f',
    'Borgoña'  => '#800020', // Nuevo: Color vino profundo
    'Turquesa' => '#40E0D0'  // Nuevo: Color turquesa vibrante
];

// Consulta para contar prendas por color
$sqlColores = "SELECT nombre, color_principal, COUNT(*) as cantidad 
               FROM prendas 
               WHERE color_principal IS NOT NULL AND color_principal != '' 
               GROUP BY nombre, color_principal 
               ORDER BY cantidad DESC;";

$resColores = $mysqli_obj->query($sqlColores);
$conteoAgrupado = [];
$conteoAgrupado['Otros'] = 0;
$prendasEnOtros = [];

while ($row = $resColores->fetch_assoc()) {
    $colorOriginal = mb_strtolower($row['color_principal']); // Convertimos a minúsculas para comparar
    $nombrePrenda = $row['nombre'];
    $encontrado = false;

    // Buscamos si el nombre original contiene alguna palabra clave de nuestras categorías
    foreach ($categoriasBase as $base => $hex) {
        if (strpos($colorOriginal, strtolower($base)) !== false) {
            if (!isset($conteoAgrupado[$base])) {
                $conteoAgrupado[$base] = 0;
            }
            $conteoAgrupado[$base] += (int)$row['cantidad'];
            $encontrado = true;
            break;
        }
    }

    // Si no coincide con ninguna base (ej: "Turquesa"), lo ponemos en 'Otros'
    if (!$encontrado) {
        $conteoAgrupado['Otros'] += (int)$row['cantidad'];
        $prendasEnOtros[] = $nombrePrenda . " (" . $row['color_principal'] . ")";
    }
}

// Limpieza: Si al final 'Otros' quedó en 0 (porque todo coincidió), lo eliminamos
if ($conteoAgrupado['Otros'] === 0) {
    unset($conteoAgrupado['Otros']);
}

// 3. Ordenamos de mayor a menor uso
arsort($conteoAgrupado);

// 4. Preparamos los datos para JS
$labelsFinales = array_keys($conteoAgrupado);
$datosFinales = array_values($conteoAgrupado);
$coloresFinales = array_map(function ($l) use ($categoriasBase) {
    return $categoriasBase[$l] ?? '#bdc3c7';
}, $labelsFinales);


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

?>

<div class="tab-pane fade" id="reportes">
    <div class="container mt-4">
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
        </div>

        <div class="row">
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
        </div>


        <div class="row">

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-1">
                            <i class="fas fa-palette me-2 text-purple-500"></i>Análisis de Colorimetría
                        </h6>
                        <p class="text-xs text-gray-400 mb-3">Predominancia de colores en tus prendas</p>
                        <div id="echartColorimetria" style="width: 100%; min-height: 350px;"></div>
                    </div>
                </div>
                <?php if (!empty($prendasEnOtros)): ?>
                    <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <h7 class="text-xs font-bold text-gray-600">
                            <i class="fas fa-info-circle me-1"></i> Prendas en "Otros":
                        </h7>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo implode(', ', $prendasEnOtros); ?>.
                        </p>
                        <p class="text-[10px] text-orange-400 mt-2 italic">
                            * Sugerencia: Agrega estos colores al código para clasificarlos mejor.
                        </p>
                    </div>
                <?php endif; ?>
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
                right: '4%',
                bottom: '3%',
                containLabel: true
            },
            xAxis: {
                type: 'value'
            },
            yAxis: {
                type: 'category',
                data: <?php echo json_encode(array_reverse($nombresUsoOlvido)); ?>,
                axisLabel: {
                    fontSize: 11
                }
            },
            series: [{
                name: 'Veces Usada',
                type: 'bar',
                data: <?php echo json_encode(array_reverse($datosUsoOlvido)); ?>,
                itemStyle: {
                    // Color dinámico: Verde para las más usadas, Rojo para las olvidadas
                    color: function(params) {
                        // Como invertimos el array para ECharts, los últimos 5 del SQL ahora son los primeros del gráfico
                        return params.dataIndex < 5 ? '#ef4444' : '#10b981';
                    },
                    borderRadius: [0, 5, 5, 0]
                },
                label: {
                    show: true,
                    position: 'right',
                    formatter: '{c} usos'
                }
            }]
        };

        const optionColorimetria = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            grid: {
                left: '3%',
                right: '10%',
                bottom: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value'
            },
            yAxis: {
                type: 'category',
                data: <?php echo json_encode(array_reverse($labelsFinales)); ?>,
                axisLabel: {
                    fontWeight: 'bold'
                }
            },
            series: [{
                name: 'Total Prendas',
                type: 'bar',
                data: <?php echo json_encode(array_reverse($datosFinales)); ?>,
                itemStyle: {
                    borderRadius: [0, 8, 8, 0],
                    color: function(params) {
                        const paleta = <?php echo json_encode(array_reverse($coloresFinales)); ?>;
                        return paleta[params.dataIndex];
                    },
                    borderColor: '#ddd',
                    borderWidth: 1
                },
                label: {
                    show: true,
                    position: 'right',
                    formatter: '{c} prendas'
                }
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
                const chartRadar = echarts.init(document.getElementById('echartRadar'));

                chartPie.setOption(optionPie);
                chartBarras.setOption(optionBarras);
                chartGauge.setOption(optionGauge);
                chartUsoOlvido.setOption(optionUsoOlvido);
                chartColorimetria.setOption(optionColorimetria);
                chartRadar.setOption(optionRadar);

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