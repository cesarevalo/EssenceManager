<?php
require_once '../../config/Database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: clientes.php');
    exit;
}
$id_cliente = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

$database = new Database();
$db = $database->getConnection();

// 1. Obtenemos los datos del cliente
$stmt_cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: clientes.php');
    exit;
}

$page_title = 'Historial de Compras de: ' . htmlspecialchars(strtoupper($cliente['nombre'] . ' ' . $cliente['apellido']));
require_once '../../templates/admin/header.php';


// 2. Obtenemos todas las ventas del cliente para el listado principal
$query_ventas = "
    SELECT
        v.id, v.fecha_venta, v.total_venta_usdt, v.estado_pago,
        (SELECT SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) FROM pagos WHERE id_venta = v.id AND estado_confirmacion = 'Confirmado') AS total_pagado
    FROM
        ventas v
    WHERE
        v.id_cliente = ?
    ORDER BY
        v.fecha_venta DESC;
";
$stmt_history = $db->prepare($query_ventas);
$stmt_history->execute([$id_cliente]);
$historial_ventas = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtenemos TODOS los productos de TODAS esas ventas en una sola consulta
$ids_ventas = array_column($historial_ventas, 'id');
$detalles_por_venta = [];
if (!empty($ids_ventas)) {
    $placeholders = implode(',', array_fill(0, count($ids_ventas), '?'));
    $query_detalles = "
        SELECT vd.id_venta, vd.cantidad, vd.precio_unitario_usdt, p.nombre 
        FROM ventas_detalle vd 
        JOIN productos p ON vd.id_producto = p.id 
        WHERE vd.id_venta IN ($placeholders)
    ";
    $stmt_detalles = $db->prepare($query_detalles);
    $stmt_detalles->execute($ids_ventas);
    $todos_los_detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

    // Agrupamos los productos por id_venta para fácil acceso
    foreach ($todos_los_detalles as $detalle) {
        $detalles_por_venta[$detalle['id_venta']][] = $detalle;
    }
}

// 4. Calculamos los totales para las tarjetas de resumen
$total_comprado = array_sum(array_column($historial_ventas, 'total_venta_usdt'));
$total_pagado = array_sum(array_column($historial_ventas, 'total_pagado'));
$saldo_total_pendiente = $total_comprado - $total_pagado;

// 5. Obtener los 5 productos más comprados por este cliente
$query_top_products = "
    SELECT p.nombre, SUM(vd.cantidad) AS total_comprado
    FROM ventas_detalle vd
    JOIN ventas v ON vd.id_venta = v.id
    JOIN productos p ON vd.id_producto = p.id
    WHERE v.id_cliente = ?
    GROUP BY p.id, p.nombre
    ORDER BY total_comprado DESC
    LIMIT 5;
";
$stmt_top = $db->prepare($query_top_products);
$stmt_top->execute([$id_cliente]);
$productos_mas_comprados = $stmt_top->fetchAll(PDO::FETCH_ASSOC);


require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4 mb-0"><?php echo $page_title; ?></h1>
        <a href="clientes.php" class="btn btn-secondary mt-4"><i class="fas fa-arrow-left me-2"></i>Volver al Listado</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Comprado (Histórico)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_comprado, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-shopping-bag fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Monto Total Pendiente</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($saldo_total_pendiente, 2); ?> USDT</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-comments-dollar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <?php if ($saldo_total_pendiente > 0.01): ?>
                <button class="btn btn-success btn-lg h-100 w-100" data-bs-toggle="modal" data-bs-target="#abonoModal">
                    <i class="fas fa-plus-circle me-2"></i>Registrar Abono a Cuenta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5>Historial de Ventas</h5></div>
                <div class="card-body">
                    <div class="accordion" id="accordionVentas">
                        <?php if (empty($historial_ventas)): ?>
                            <p class="text-center">Este cliente no tiene historial de compras.</p>
                        <?php else: ?>
                            <?php foreach ($historial_ventas as $index => $venta): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-<?php echo $venta['id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $venta['id']; ?>">
                                        <div class="w-100 d-flex justify-content-between flex-wrap">
                                            <strong class="me-3">Venta #<?php echo $venta['id']; ?></strong>
                                            <span class="me-3"><?php echo date("d/m/Y", strtotime($venta['fecha_venta'])); ?></span>
                                            <span class="me-3">Total: $<?php echo number_format($venta['total_venta_usdt'], 2); ?></span>
                                            <span>
                                                <?php 
                                                    $estado = htmlspecialchars($venta['estado_pago']);
                                                    $badge_class = 'secondary';
                                                    if ($estado == 'Pendiente') $badge_class = 'danger';
                                                    if ($estado == 'Abonado') $badge_class = 'warning';
                                                    if ($estado == 'Pagado') $badge_class = 'success';
                                                    echo "<span class=\"badge bg-{$badge_class}\">{$estado}</span>";
                                                ?>
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $venta['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionVentas">
                                    <div class="accordion-body">
                                        <h6>Productos en esta compra:</h6>
                                        <table class="table table-sm table-borderless">
                                            <tbody>
                                            <?php if(isset($detalles_por_venta[$venta['id']])): ?>
                                                <?php foreach($detalles_por_venta[$venta['id']] as $detalle): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                                        <td>x <?php echo $detalle['cantidad']; ?></td>
                                                        <td class="text-end">$<?php echo number_format($detalle['precio_unitario_usdt'] * $detalle['cantidad'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                        <a href="venta_detalle.php?id=<?php echo $venta['id']; ?>" class="btn btn-info btn-sm">Ver Detalles y Pagos de esta Venta</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5>Top 5 Productos Comprados</h5></div>
                <div class="card-body">
                    <?php if (empty($productos_mas_comprados)): ?>
                        <p class="text-center">Este cliente aún no ha comprado productos.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($productos_mas_comprados as $producto): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($producto['nombre']); ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $producto['total_comprado']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="abonoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Abono a Cuenta General</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="../../app/crud/pagos_globales_process.php" method="POST">
                    <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
                    <div class="mb-3"><label class="form-label">Monto Recibido</label><input type="number" step="0.01" class="form-control" name="monto" required></div>
                    <div class="mb-3"><label class="form-label">Moneda del Pago</label><input type="text" class="form-control" name="moneda_pago" placeholder="Ej: USDT, VES, USD" required></div>
                    <div class="mb-3"><label class="form-label">Tasa de Conversión a USDT</label><input type="number" step="0.00000001" class="form-control" name="tasa_conversion" value="1.00"></div>
                    <div class="mb-3"><label class="form-label">Método de Pago</label><input type="text" class="form-control" name="metodo_pago" placeholder="Ej: Zelle, Pago Móvil, Efectivo" required></div>
                    <div class="mb-3"><label class="form-label">Referencia</label><input type="text" class="form-control" name="referencia"></div>
                    <div class="mb-3"><label class="form-label">Fecha de Pago</label><input type="date" class="form-control" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="d-grid"><button type="submit" class="btn btn-success">Aplicar Pago</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>