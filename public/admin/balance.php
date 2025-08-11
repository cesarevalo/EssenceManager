<?php
$page_title = 'Balance y Reportes';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// --- Lógica de Filtros de Fecha ---
$date_filter_clause_pagos_ventas = '';
$date_filter_clause_pagos_compras = '';
$date_filter_clause_costos = '';
$params_pagos_ventas = [];
$params_pagos_compras = [];
$params_costos = [];

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $date_filter_clause_pagos_ventas = " WHERE fecha_pago BETWEEN :start_date AND :end_date";
    $params_pagos_ventas = [':start_date' => $start_date, ':end_date' => $end_date];

    $date_filter_clause_pagos_compras = " WHERE fecha_pago BETWEEN :start_date AND :end_date";
    $params_pagos_compras = [':start_date' => $start_date, ':end_date' => $end_date];

    // Para los costos adicionales, filtramos por la fecha de la compra
    $date_filter_clause_costos = " WHERE c.fecha_compra BETWEEN :start_date AND :end_date";
    $params_costos = [':start_date' => $start_date, ':end_date' => $end_date];
}

// --- Cálculo de Ingresos Totales ---
$query_ingresos = "
    SELECT SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) as total_ingresos
    FROM pagos
    {$date_filter_clause_pagos_ventas}
";
$stmt_ingresos = $db->prepare($query_ingresos);
$stmt_ingresos->execute($params_pagos_ventas);
$total_ingresos = $stmt_ingresos->fetchColumn() ?: 0;

// --- Cálculo de Egresos Totales ---
// 1. Egresos por pago a proveedores
$query_egresos_compras = "
    SELECT SUM(monto_pagado) as total_pagos_compras
    FROM compras_pagos
    {$date_filter_clause_pagos_compras}
";
$stmt_egresos_compras = $db->prepare($query_egresos_compras);
$stmt_egresos_compras->execute($params_pagos_compras);
$total_pagos_compras = $stmt_egresos_compras->fetchColumn() ?: 0;

// 2. Egresos por costos adicionales
$query_costos_adicionales = "
    SELECT SUM(ca.monto_costo_usdt) as total_costos
    FROM compras_costos_adicionales ca
    JOIN compras c ON ca.id_compra = c.id
    {$date_filter_clause_costos}
";
$stmt_costos_adicionales = $db->prepare($query_costos_adicionales);
$stmt_costos_adicionales->execute($params_costos);
$total_costos_adicionales = $stmt_costos_adicionales->fetchColumn() ?: 0;

$total_egresos = $total_pagos_compras + $total_costos_adicionales;

// --- Cálculo del Balance Final ---
$balance_final = $total_ingresos - $total_egresos;

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <p class="mb-4">Aquí puedes ver un resumen de los ingresos y egresos de tu negocio.</p>

    <div class="card mb-4">
        <div class="card-header">
            Filtrar por Rango de Fechas de Pago
        </div>
        <div class="card-body">
            <form action="balance.php" method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="start_date" class="form-label">Desde:</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-auto">
                    <label for="end_date" class="form-label">Hasta:</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-auto mt-4">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="balance.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ingresos Totales (Ventas Pagadas)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_ingresos, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Egresos Totales (Compras y Gastos)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_egresos, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card <?php echo ($balance_final >= 0) ? 'border-left-primary' : 'border-left-warning'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold <?php echo ($balance_final >= 0) ? 'text-primary' : 'text-warning'; ?> text-uppercase mb-1">Balance Final</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($balance_final, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale-right fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Estilos para las tarjetas de resumen */
.card .border-left-primary { border-left: .25rem solid #4e73df!important; }
.card .border-left-success { border-left: .25rem solid #1cc88a!important; }
.card .border-left-danger { border-left: .25rem solid #e74a3b!important; }
.card .border-left-warning { border-left: .25rem solid #f6c23e!important; }
.text-xs { font-size: .9rem; }
</style>

<?php require_once '../../templates/admin/footer.php'; ?>