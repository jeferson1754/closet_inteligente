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
?>
<div class="tab-pane fade" id="reportes">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-3">Distribución por Tipo</h6>
                        <div id="echartPie" style="width: 100%; min-height: 500px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-xl">
                    <div class="card-body">
                        <h6 class="font-bold text-gray-700 mb-3">Top 50 Prendas más usadas</h6>
                        <div id="echartBarras" style="width: 100%; min-height: 500px;"></div>
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
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { 
                type: 'scroll', // Añadido scroll por si hay muchos tipos
                bottom: '0%', 
                left: 'center' 
            },
            series: [{
                name: 'Tipo de Prenda',
                type: 'pie',
                radius: ['40%', '70%'],
                avoidLabelOverlap: false,
                itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
                label: { show: false, position: 'center' },
                emphasis: { label: { show: true, fontSize: '18', fontWeight: 'bold' } },
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
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            grid: { left: '3%', right: '10%', bottom: '3%', containLabel: true }, // Aumentado margen derecho
            xAxis: { type: 'value', boundaryGap: [0, 0.01] },
            yAxis: {
                type: 'category',
                data: <?php echo json_encode(array_reverse($nombresPrendas)); ?>,
                inverse: false
            },
            dataZoom: [
                { type: 'inside', start: 70, end: 100, yAxisIndex: 0 },
                { type: 'slider', yAxisIndex: 0, right: 10 }
            ],
            series: [{
                name: 'Usos',
                type: 'bar',
                data: <?php echo json_encode(array_reverse($conteoUsos)); ?>,
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: '#83a4d4' },
                        { offset: 1, color: '#b6fbff' }
                    ]),
                    borderRadius: [0, 5, 5, 0]
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
                
                chartPie.setOption(optionPie);
                chartBarras.setOption(optionBarras);
                
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