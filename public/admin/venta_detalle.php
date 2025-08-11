<?php
$page_title = 'Detalle de Venta';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

if (!isset($_GET['id'])) { header('Location: ventas.php'); exit; }
$id_venta = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$page_title = 'Detalle de Venta #' . $id_venta;

$database = new Database();
$db = $database->getConnection();

// --- Obtener datos de la venta ---
$stmt_venta = $db->prepare("SELECT v.*, c.nombre, c.apellido FROM ventas v JOIN clientes c ON v.id_cliente = c.id WHERE v.id = ?");
$stmt_venta->execute([$id_venta]);
$venta = $stmt_venta->fetch(PDO::FETCH_ASSOC);
if (!$venta) { header('Location: ventas.php'); exit; }

// --- Obtener historial de pagos ---
$stmt_pagos = $db->prepare("SELECT * FROM pagos WHERE id_venta = ? ORDER BY fecha_pago DESC");
$stmt_pagos->execute([$id_venta]);
$pagos_realizados = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

// --- Calcular totales ---
$total_pagado_en_usdt = 0;
foreach ($pagos_realizados as $pago) {
    $tasa = ($pago['tasa_conversion'] > 0) ? $pago['tasa_conversion'] : 1;
    $total_pagado_en_usdt += $pago['monto'] / $tasa;
}
$saldo_pendiente = $venta['total_venta_usdt'] - $total_pagado_en_usdt;

// --- OBTENER DETALLES DE PRODUCTOS VENDIDOS (CON COSTO) ---
$stmt_detalle = $db->prepare("
    SELECT 
        vd.id, vd.cantidad, vd.precio_unitario_usdt, 
        p.nombre as producto_nombre,
        p.costo_promedio_usdt 
    FROM ventas_detalle vd 
    JOIN productos p ON vd.id_producto = p.id 
    WHERE vd.id_venta = ?
");
$stmt_detalle->execute([$id_venta]);
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

// --- Obtener todos los productos para el buscador ---
$stmt_productos = $db->query("SELECT id, nombre, codigo_barra, stock, precio_usdt FROM productos WHERE stock > 0 AND visible = 1 ORDER BY nombre ASC");
$productos_disponibles = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4 mb-0"><?php echo $page_title; ?></h1>
        <a href="ventas.php" class="btn btn-secondary mt-4">
            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
        </a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert"><?php echo $_SESSION['message']; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card mb-4">
                <div class="card-header"><h5>Resumen Financiero</h5></div>
                <div class="card-body">
                    <p>Cliente: <strong><?php echo htmlspecialchars(strtoupper($venta['nombre'] . ' ' . $venta['apellido'])); ?></strong></p>
                    <p class="h5">Total Venta: <span class="float-end">$<?php echo number_format($venta['total_venta_usdt'], 2); ?></span></p>
                    <p class="h5 text-success">Total Pagado: <span class="float-end">$<?php echo number_format($total_pagado_en_usdt, 2); ?></span></p>
                    <hr>
                    <p class="h4 fw-bold <?php echo ($saldo_pendiente <= 0.01) ? 'text-success' : 'text-danger'; ?>">
                        Saldo Pendiente: <span class="float-end">$<?php echo number_format($saldo_pendiente, 2); ?></span>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Registrar Nuevo Pago / Abono</h5></div>
                <div class="card-body">
                    <form action="../../app/crud/pagos_process.php" method="POST">
                        <input type="hidden" name="action" value="add_payment_to_venta">
                        <input type="hidden" name="id_venta" value="<?php echo $id_venta; ?>">
                        <div class="mb-3">
                            <label class="form-label">Monto Recibido</label>
                            <input type="number" step="0.01" class="form-control" name="monto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Moneda del Pago</label>
                            <input type="text" class="form-control" name="moneda_pago" placeholder="Ej: USDT, VES, USD" required>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Tasa de Conversión a USDT (Si aplica)</label>
                            <input type="number" step="0.00000001" class="form-control" name="tasa_conversion" value="1.00">
                             <small class="form-text text-muted">Si el pago es en VES, colocar la tasa del día. Si es en USDT/USD, dejar en 1.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Pago</label>
                            <input type="text" class="form-control" name="metodo_pago" placeholder="Ej: Zelle, Pago Móvil, Efectivo" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referencia (Opcional)</label>
                            <input type="text" class="form-control" name="referencia">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha de Pago</label>
                            <input type="date" class="form-control" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Registrar Pago</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if (empty($pagos_realizados)): ?>
            <div class="card mb-4">
                <div class="card-header"><h5>Añadir Producto a la Venta</h5></div>
                <div class="card-body">
                    <form action="../../app/crud/ventas_process.php" method="POST">
                        <input type="hidden" name="action" value="add_product_to_venta">
                        <input type="hidden" name="id_venta" value="<?php echo $id_venta; ?>">
                        <div class="mb-3">
                            <label for="id_producto" class="form-label">Buscar Producto</label>
                            <select class="form-select" id="id_producto" name="id_producto" required>
                                <option value="">Escribe para buscar un producto...</option>
                                <?php foreach ($productos_disponibles as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>">
                                        <?php echo htmlspecialchars($prod['nombre']) . ' (Cod: ' . htmlspecialchars($prod['codigo_barra']) . ') - Stock: ' . $prod['stock'] . ' - $' . number_format($prod['precio_usdt'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad a Vender</label>
                            <input type="number" class="form-control" name="cantidad" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Añadir Producto</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">Esta venta ya tiene pagos registrados y no puede ser modificada.</div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header"><h5>Productos Vendidos</h5></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Costo Unit.</th>
                                <th>Precio Unit.</th>
                                <th>Ganancia</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                $ganancia_total_venta = 0;
                                if (empty($detalles)): 
                            ?>
                                <tr><td colspan="6" class="text-center">Aún no se han añadido productos a esta venta.</td></tr>
                            <?php else: ?>
                                <?php foreach($detalles as $detalle): ?>
                                <?php
                                    $costo_total_linea = $detalle['costo_promedio_usdt'] * $detalle['cantidad'];
                                    $venta_total_linea = $detalle['precio_unitario_usdt'] * $detalle['cantidad'];
                                    $ganancia_linea = $venta_total_linea - $costo_total_linea;
                                    $ganancia_total_venta += $ganancia_linea;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                                    <td><?php echo $detalle['cantidad']; ?></td>
                                    <td class="text-danger">$<?php echo number_format($detalle['costo_promedio_usdt'], 2); ?></td>
                                    <td class="text-success">$<?php echo number_format($detalle['precio_unitario_usdt'], 2); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($ganancia_linea, 2); ?></td>
                                    <td>
                                        <?php if (empty($pagos_realizados)): ?>
                                        <form action="../../app/crud/ventas_process.php" method="POST" onsubmit="return confirm('¿Estás seguro? El stock será devuelto.');">
                                            <input type="hidden" name="action" value="remove_product_from_venta">
                                            <input type="hidden" name="id_venta_detalle" value="<?php echo $detalle['id']; ?>">
                                            <input type="hidden" name="id_venta" value="<?php echo $id_venta; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold">Ganancia Total de la Venta:</td>
                                <td class="fw-bold h5">$<?php echo number_format($ganancia_total_venta, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Historial de Pagos de esta Venta</h5></div>
                <div class="card-body">
                     <table class="table table-sm">
                        <thead><tr><th>Fecha</th><th>Monto Original</th><th>Tasa</th><th>Monto en USDT</th><th>Método</th><th>Ref.</th></tr></thead>
                        <tbody>
                            <?php if(empty($pagos_realizados)): ?>
                            <tr><td colspan="6" class="text-center">No hay pagos registrados.</td></tr>
                            <?php else: ?>
                            <?php foreach($pagos_realizados as $pago): ?>
                            <tr>
                                <td><?php echo date("d/m/Y", strtotime($pago['fecha_pago'])); ?></td>
                                <td><?php echo number_format($pago['monto'], 2) . ' ' . $pago['moneda_pago']; ?></td>
                                <td><?php echo number_format($pago['tasa_conversion'], 4); ?></td>
                                <td class="fw-bold">$<?php echo number_format($pago['monto'] / (($pago['tasa_conversion'] > 0) ? $pago['tasa_conversion'] : 1), 2); ?></td>
                                <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                                <td><?php echo htmlspecialchars($pago['referencia']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#id_producto').select2({
        theme: "bootstrap-5",
        placeholder: "Busca por nombre o código de barra",
    });
});
</script>