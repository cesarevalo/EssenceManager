<?php
$page_title = 'Gestión de Ventas';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// --- LÓGICA DE FILTROS Y PAGINACIÓN ---
$records_per_page = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$where_clauses = [];
$params = [];
$filter_params_for_url = []; // Para los enlaces de paginación

// Filtro por Rango de Fechas
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "v.fecha_venta BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $params[':end_date'] = $end_date . ' 23:59:59';
    $filter_params_for_url['start_date'] = $start_date;
    $filter_params_for_url['end_date'] = $end_date;
}

$where_sql = count($where_clauses) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

// --- CÁLCULO DE TOTALES PARA LAS TARJETAS (RESPETANDO EL FILTRO) ---
$query_total_vendido = "SELECT SUM(total_venta_usdt) FROM ventas v {$where_sql}";
$stmt_total_vendido = $db->prepare($query_total_vendido);
$stmt_total_vendido->execute($params);
$total_vendido = $stmt_total_vendido->fetchColumn() ?: 0;

$pagos_params = [];
$where_pagos_sql = '';
if (!empty($start_date) && !empty($end_date)) {
    $where_pagos_sql = " WHERE p.fecha_pago BETWEEN :start_date AND :end_date";
    $pagos_params[':start_date'] = $start_date;
    $pagos_params[':end_date'] = $end_date;
}
$query_total_cobrado = "SELECT SUM(p.monto / IF(p.tasa_conversion > 0, p.tasa_conversion, 1)) FROM pagos p {$where_pagos_sql}";
$stmt_total_cobrado = $db->prepare($query_total_cobrado);
$stmt_total_cobrado->execute($pagos_params);
$total_cobrado = $stmt_total_cobrado->fetchColumn() ?: 0;
$total_por_cobrar = $total_vendido - $total_cobrado;

// --- OBTENER EL NÚMERO TOTAL DE REGISTROS PARA LA PAGINACIÓN ---
$query_count = "SELECT COUNT(v.id) FROM ventas v {$where_sql}";
$stmt_count = $db->prepare($query_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// --- CONSULTA PARA OBTENER LOS REGISTROS DE LA PÁGINA ACTUAL ---
$query = "
    SELECT 
        v.id, v.fecha_venta, v.total_venta_usdt, v.estado_pago,
        c.nombre, c.apellido,
        COALESCE(pagos.total_pagado_usdt, 0) AS total_pagado,
        COALESCE(detalles.item_count, 0) AS item_count
    FROM 
        ventas v
    LEFT JOIN 
        clientes c ON v.id_cliente = c.id
    LEFT JOIN 
        (SELECT id_venta, SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) as total_pagado_usdt FROM pagos GROUP BY id_venta) pagos 
        ON v.id = pagos.id_venta
    LEFT JOIN
        (SELECT id_venta, COUNT(id) as item_count FROM ventas_detalle GROUP BY id_venta) detalles
        ON v.id = detalles.id_venta
    {$where_sql}
    ORDER BY 
        v.fecha_venta DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $db->prepare($query);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Facturado (Ventas)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_vendido, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cobrado</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_cobrado, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total por Cobrar (Saldo)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_por_cobrar, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-comments-dollar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    
    <div class="card mb-4">
        <div class="card-body">
            <form action="ventas.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4"><label for="start_date" class="form-label">Desde:</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></div>
                <div class="col-md-4"><label for="end_date" class="form-label">Hasta:</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn btn-primary me-2">Filtrar</button><a href="ventas.php" class="btn btn-secondary">Limpiar</a></div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="venta_form.php" class="btn btn-success"><i class="fas fa-plus"></i> Registrar Venta Manual</a>
        <span>Mostrando <?php echo count($ventas); ?> de <?php echo $total_records; ?> ventas</span>
    </div>

    <div class="card">
        <div class="card-header"><h5>Historial de Ventas</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID Venta</th><th>Fecha</th><th>Cliente</th><th>Total Venta</th><th>Monto Pendiente</th><th>Estado de Pago</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr><td colspan="7" class="text-center">No se encontraron ventas para el filtro aplicado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                            <?php $saldo_pendiente = $venta['total_venta_usdt'] - $venta['total_pagado']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($venta['id']); ?></td>
                                <td><?php echo date("d/m/Y h:i A", strtotime($venta['fecha_venta'])); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper(($venta['nombre'] ?? 'Cliente') . ' ' . ($venta['apellido'] ?? 'Eliminado'))); ?></td>
                                <td class="text-success fw-bold">$<?php echo number_format($venta['total_venta_usdt'], 2); ?></td>
                                <td class="fw-bold <?php echo ($saldo_pendiente > 0.01) ? 'text-danger' : 'text-success'; ?>">$<?php echo number_format($saldo_pendiente, 2); ?></td>
                                <td>
                                    <?php 
                                        $estado = htmlspecialchars($venta['estado_pago']);
                                        $badge_class = 'secondary';
                                        if ($estado == 'Pendiente') $badge_class = 'danger';
                                        if ($estado == 'Abonado') $badge_class = 'warning';
                                        if ($estado == 'Pagado') $badge_class = 'success';
                                        echo "<span class=\"badge bg-{$badge_class}\">{$estado}</span>";
                                    ?>
                                </td>
                                <td class="text-nowrap">
                                    <a href="venta_detalle.php?id=<?php echo $venta['id']; ?>" class="btn btn-sm btn-info" title="Ver Detalles y Pagos"><i class="fas fa-eye"></i></a>
                                    <?php if ($venta['total_pagado'] == 0 && $venta['item_count'] == 0): ?>
                                    <form action="../../app/crud/ventas_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres ELIMINAR esta venta? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="action" value="delete_venta">
                                        <input type="hidden" name="id_venta" value="<?php echo $venta['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Venta"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $page - 1])); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $page + 1])); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.card .border-left-primary { border-left: .25rem solid #4e73df!important; }
.card .border-left-success { border-left: .25rem solid #1cc88a!important; }
.card .border-left-danger { border-left: .25rem solid #e74a3b!important; }
.card .border-left-warning { border-left: .25rem solid #f6c23e!important; }
.text-xs { font-size: .9rem; }
</style>

<?php require_once '../../templates/admin/footer.php'; ?>