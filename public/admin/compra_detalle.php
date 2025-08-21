<?php
$page_title = 'Detalle de Compra';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

if (!isset($_GET['id'])) { header('Location: compras.php'); exit; }
$id_compra = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$page_title = 'Detalle de Compra #' . $id_compra;
$database = new Database();
$db = $database->getConnection();

// --- Obtener datos de la compra ---
$stmt_compra = $db->prepare("SELECT * FROM compras WHERE id = :id");
$stmt_compra->bindParam(':id', $id_compra);
$stmt_compra->execute();
$compra = $stmt_compra->fetch(PDO::FETCH_ASSOC);
if (!$compra) { header('Location: compras.php'); exit; }

// --- Obtener pagos y costos adicionales para el resumen financiero ---
$stmt_pagos = $db->prepare("SELECT * FROM compras_pagos WHERE id_compra = ? ORDER BY fecha_pago DESC");
$stmt_pagos->execute([$id_compra]);
$pagos_realizados = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);
$total_pagado = array_sum(array_column($pagos_realizados, 'monto_pagado'));

$stmt_costos = $db->prepare("SELECT * FROM compras_costos_adicionales WHERE id_compra = ?");
$stmt_costos->execute([$id_compra]);
$costos_adicionales = $stmt_costos->fetchAll(PDO::FETCH_ASSOC);
$total_costos_adicionales = array_sum(array_column($costos_adicionales, 'monto_costo_usdt'));

$egreso_total = $compra['total_compra'] + $total_costos_adicionales;
$saldo_pendiente = $egreso_total - $total_pagado;

// --- Obtener detalles de productos y productos disponibles ---
$stmt_detalle = $db->prepare("SELECT cd.id, p.nombre, cd.cantidad, cd.costo_unitario_usdt, cd.cantidad_recibida FROM compras_detalle cd JOIN productos p ON cd.id_producto = p.id WHERE cd.id_compra = :id_compra");
$stmt_detalle->bindParam(':id_compra', $id_compra);
$stmt_detalle->execute();
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
$stmt_productos = $db->query("SELECT id, nombre, codigo_barra FROM productos ORDER BY nombre ASC");
$productos_disponibles = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4 mb-0"><?php echo $page_title; ?></h1>
        <a href="compras.php" class="btn btn-secondary mt-4">
            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card mb-4">
                <div class="card-header"><h5>Resumen Financiero</h5></div>
                <div class="card-body">
                    <p>Costo Productos: <span class="float-end">$<?php echo number_format($compra['total_compra'], 2); ?></span></p>
                    <p>Costos Adicionales: <span class="float-end">$<?php echo number_format($total_costos_adicionales, 2); ?></span></p>
                    <p class="h5 border-top pt-2">Egreso Total: <span class="float-end">$<?php echo number_format($egreso_total, 2); ?></span></p>
                    <p class="h5 text-success">Total Pagado: <span class="float-end">$<?php echo number_format($total_pagado, 2); ?></span></p>
                    <hr>
                    <p class="h4 fw-bold <?php echo ($saldo_pendiente <= 0.01) ? 'text-success' : 'text-danger'; ?>">
                        Saldo Pendiente: <span class="float-end">$<?php echo number_format($saldo_pendiente, 2); ?></span>
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5>Añadir Costo Adicional</h5></div>
                <div class="card-body">
                    <form action="../../app/crud/compras_process.php" method="POST">
                        <input type="hidden" name="action" value="add_additional_cost">
                        <input type="hidden" name="id_compra" value="<?php echo $id_compra; ?>">
                        <div class="mb-3">
                            <label for="descripcion_costo" class="form-label">Descripción del Costo</label>
                            <input type="text" class="form-control" name="descripcion_costo" placeholder="Ej: Envío Interno, Courier DHL" required>
                        </div>
                        <div class="mb-3">
                            <label for="monto_costo_usdt" class="form-label">Monto (USDT)</label>
                            <input type="number" step="0.01" class="form-control" name="monto_costo_usdt" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Añadir Costo</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5>Registrar Nuevo Pago</h5></div>
                <div class="card-body">
                    <form action="../../app/crud/compras_process.php" method="POST">
                        <input type="hidden" name="action" value="add_payment_to_compra">
                        <input type="hidden" name="id_compra" value="<?php echo $id_compra; ?>">
                        <div class="mb-3">
                            <label for="monto_pagado" class="form-label">Monto a Pagar (USDT)</label>
                            <input type="number" step="0.01" class="form-control" name="monto_pagado" required>
                        </div>
                        <div class="mb-3">
                            <label for="metodo_pago" class="form-label">Método de Pago</label>
                            <input type="text" class="form-control" name="metodo_pago" placeholder="Ej: Zelle, Efectivo">
                        </div>
                        <div class="mb-3">
                            <label for="referencia_pago" class="form-label">Referencia / ID de TX</label>
                            <input type="text" class="form-control" name="referencia_pago">
                        </div>
                        <div class="mb-3">
                            <label for="fecha_pago" class="form-label">Fecha del Pago</label>
                            <input type="date" class="form-control" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Registrar Pago</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($compra['estado'] == 'En tránsito'): ?>
            <div class="card mb-4">
                <div class="card-header"><h5>Añadir Producto a la Compra</h5></div>
                <div class="card-body">
                    <form action="../../app/crud/compras_process.php" method="POST">
                        <input type="hidden" name="action" value="add_product_to_compra">
                        <input type="hidden" name="id_compra" value="<?php echo $id_compra; ?>">
                        <div class="mb-3">
                            <label for="id_producto" class="form-label">Buscar Producto</label>
                            <select class="form-select" id="id_producto" name="id_producto" required>
                                <option value="">Escribe para buscar...</option>
                                <?php foreach ($productos_disponibles as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>">
                                        <?php echo htmlspecialchars($prod['nombre']) . ' (Cod: ' . htmlspecialchars($prod['codigo_barra']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">Cantidad Pedida</label>
                            <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="costo_unitario_usdt" class="form-label">Costo Unitario (USDT)</label>
                            <input type="number" step="0.01" class="form-control" id="costo_unitario_usdt" name="costo_unitario_usdt" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Añadir Producto</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header"><h5>Historial de Costos Adicionales</h5></div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Descripción</th><th>Monto</th></tr></thead>
                            <tbody>
                                <?php if(empty($costos_adicionales)): ?>
                                    <tr><td colspan="2" class="text-center">No hay costos adicionales registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach($costos_adicionales as $costo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($costo['descripcion_costo']); ?></td>
                                            <td>$<?php echo number_format($costo['monto_costo_usdt'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header"><h5>Historial de Pagos de esta Compra</h5></div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Referencia</th></tr></thead>
                            <tbody>
                                <?php if(empty($pagos_realizados)): ?>
                                    <tr><td colspan="4" class="text-center">No hay pagos registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach($pagos_realizados as $pago): ?>
                                        <tr>
                                            <td><?php echo date("d/m/Y", strtotime($pago['fecha_pago'])); ?></td>
                                            <td>$<?php echo number_format($pago['monto_pagado'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                                            <td><?php echo htmlspecialchars($pago['referencia_pago']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <form action="../../app/crud/compras_process.php" method="POST">
                <input type="hidden" name="action" value="receive_stock">
                <input type="hidden" name="id_compra" value="<?php echo $id_compra; ?>">
                
                <div class="card">
                    <div class="card-header"><h5>Productos en esta Compra</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Producto</th><th>Costo Unitario</th><th>Cant. Pedida</th><th>Subtotal</th><th>Cant. Recibida</th></tr></thead>
                                <tbody>
                                    <?php if (empty($detalles)): ?>
                                        <tr><td colspan="5" class="text-center">Aún no hay productos en esta compra.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($detalles as $detalle): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                            <td>$<?php echo number_format($detalle['costo_unitario_usdt'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($detalle['cantidad']); ?></td>
                                            <td>$<?php echo number_format($detalle['costo_unitario_usdt'] * $detalle['cantidad'], 2); ?></td>
                                            <td>
                                                <?php if ($compra['estado'] == 'En tránsito'): ?>
                                                    <input type="number" name="cantidades_recibidas[<?php echo $detalle['id']; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($detalle['cantidad']); ?>" min="0" style="max-width: 100px;">
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($detalle['cantidad_recibida'] ?? 'N/A'); ?></strong>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($compra['estado'] == 'En tránsito' && !empty($detalles)): ?>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success" onclick="return confirm('¿Estás seguro? Esta acción actualizará el stock con las cantidades que introdujiste y no se puede deshacer.');">
                            <i class="fas fa-check-circle me-2"></i>Guardar Recepción y Actualizar Stock
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once '../../templates/admin/footer.php'; 
?>
<script>
$(document).ready(function() {
    $('#id_producto').select2({
        theme: "bootstrap-5",
        placeholder: "Escribe el nombre o código de barra",
    });
});
</script>