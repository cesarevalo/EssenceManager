<?php
require_once '../../config/Database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit;
}

$id_producto = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

$database = new Database();
$db = $database->getConnection();

// --- Obtener los datos del producto ---
$stmt_prod = $db->prepare("SELECT nombre, sku, codigo_barra, stock, costo_promedio_usdt FROM productos WHERE id = ?");
$stmt_prod->execute([$id_producto]);
$producto = $stmt_prod->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: products.php');
    exit;
}

$page_title = 'Historial de Compras: ' . htmlspecialchars($producto['nombre']);
require_once '../../templates/admin/header.php';


// --- CONSULTA ACTUALIZADA: Ahora trae el número de factura ---
$query = "
    SELECT
        c.id AS id_compra,
        c.fecha_compra,
        c.numero_factura,
        cd.cantidad,
        cd.cantidad_recibida,
        cd.costo_unitario_usdt,
        pr.nombre AS nombre_proveedor
    FROM
        compras_detalle cd
    JOIN
        compras c ON cd.id_compra = c.id
    LEFT JOIN
        proveedores pr ON c.id_proveedor = pr.id
    WHERE
        cd.id_producto = ?
    ORDER BY
        c.fecha_compra DESC;
";
$stmt_history = $db->prepare($query);
$stmt_history->execute([$id_producto]);
$historial = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Código de Barra</h6>
                    <p class="card-text h5"><?php echo htmlspecialchars($producto['codigo_barra'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Cantidad Disponible (Stock)</h6>
                    <p class="card-text h5"><?php echo htmlspecialchars($producto['stock']); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-danger">
                    <h6 class="card-subtitle mb-2 text-muted">Costo Promedio</h6>
                    <p class="card-text h5 fw-bold">$<?php echo number_format($producto['costo_promedio_usdt'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Registros de Compra</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID Compra</th>
                            <th>Fecha</th>
                            <th>Nro. Factura</th> <th>Proveedor</th>
                            <th>Costo Unitario (USDT)</th>
                            <th>Cant. Pedida</th>
                            <th>Cant. Recibida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr><td colspan="7" class="text-center">Este producto no tiene historial de compras.</td></tr>
                        <?php else: ?>
                            <?php foreach ($historial as $registro): ?>
                                <tr>
                                    <td>
                                        <a href="compra_detalle.php?id=<?php echo $registro['id_compra']; ?>">
                                            <?php echo $registro['id_compra']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date("d/m/Y", strtotime($registro['fecha_compra'])); ?></td>
                                    <td><?php echo htmlspecialchars($registro['numero_factura'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($registro['nombre_proveedor'] ?? 'N/A'); ?></td>
                                    <td class="text-danger fw-bold">$<?php echo number_format($registro['costo_unitario_usdt'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($registro['cantidad']); ?></td>
                                    <td><?php echo htmlspecialchars($registro['cantidad_recibida'] ?? 'Pendiente'); ?></td>
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