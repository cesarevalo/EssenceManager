<?php
require_once '../../config/Database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit;
}
$id_producto = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

$database = new Database();
$db = $database->getConnection();

// Obtenemos los datos del producto
$stmt_prod = $db->prepare("SELECT nombre, sku, codigo_barra, stock, costo_promedio_usdt FROM productos WHERE id = ?");
$stmt_prod->execute([$id_producto]);
$producto = $stmt_prod->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: products.php');
    exit;
}
$page_title = 'Movimientos de Inventario: ' . htmlspecialchars($producto['nombre']);
require_once '../../templates/admin/header.php';

// CONSULTA UNIFICADA (KARDEX)
$query = "
    (SELECT 
        c.fecha_compra AS fecha,
        'Entrada (Compra)' AS tipo_movimiento,
        cd.cantidad_recibida AS cantidad,
        cd.costo_unitario_usdt AS monto_unitario,
        c.id AS id_referencia,
        prov.nombre AS tercero
    FROM compras_detalle cd
    JOIN compras c ON cd.id_compra = c.id
    LEFT JOIN proveedores prov ON c.id_proveedor = prov.id
    WHERE cd.id_producto = ? AND c.estado = 'Recibido' AND cd.cantidad_recibida > 0)
    
    UNION ALL
    
    (SELECT 
        v.fecha_venta AS fecha,
        'Salida (Venta)' AS tipo_movimiento,
        vd.cantidad * -1 AS cantidad, -- Usamos un número negativo para las salidas
        vd.precio_unitario_usdt AS monto_unitario,
        v.id AS id_referencia,
        CONCAT(cli.nombre, ' ', cli.apellido) AS tercero
    FROM ventas_detalle vd
    JOIN ventas v ON vd.id_venta = v.id
    LEFT JOIN clientes cli ON v.id_cliente = cli.id
    WHERE vd.id_producto = ?)

    ORDER BY fecha DESC
";
$stmt_movimientos = $db->prepare($query);
$stmt_movimientos->execute([$id_producto, $id_producto]);
$movimientos = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    
    <div class="row mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="card-subtitle mb-2 text-muted">Código de Barra</h6><p class="card-text h5"><?php echo htmlspecialchars($producto['codigo_barra'] ?? 'N/A'); ?></p></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="card-subtitle mb-2 text-muted">Stock Actual</h6><p class="card-text h5"><?php echo htmlspecialchars($producto['stock']); ?></p></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body text-danger"><h6 class="card-subtitle mb-2 text-muted">Costo Promedio</h6><p class="card-text h5 fw-bold">$<?php echo number_format($producto['costo_promedio_usdt'], 2); ?></p></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Historial de Movimientos (Kardex)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo de Movimiento</th>
                            <th>Tercero (Proveedor/Cliente)</th>
                            <th>Cantidad</th>
                            <th>Monto Unitario (USDT)</th>
                            <th>Referencia (ID)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="6" class="text-center">Este producto no tiene movimientos de inventario registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?php echo date("d/m/Y", strtotime($mov['fecha'])); ?></td>
                                    <td>
                                        <?php if ($mov['tipo_movimiento'] == 'Entrada (Compra)'): ?>
                                            <span class="badge bg-success">Entrada</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Salida</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mov['tercero'] ?? 'N/A'); ?></td>
                                    <td class="fw-bold <?php echo ($mov['cantidad'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $mov['cantidad']; ?>
                                    </td>
                                    <td>$<?php echo number_format($mov['monto_unitario'], 2); ?></td>
                                    <td>
                                        <?php if ($mov['tipo_movimiento'] == 'Entrada (Compra)'): ?>
                                            <a href="compra_detalle.php?id=<?php echo $mov['id_referencia']; ?>">Compra #<?php echo $mov['id_referencia']; ?></a>
                                        <?php else: ?>
                                            <a href="venta_detalle.php?id=<?php echo $mov['id_referencia']; ?>">Venta #<?php echo $mov['id_referencia']; ?></a>
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
            <a href="products.php" class="btn btn-secondary">Volver al Listado de Productos</a>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>