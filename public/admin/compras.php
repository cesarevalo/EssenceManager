<?php
$page_title = 'Gestión de Compras';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Query actualizada para traer también el total pagado de cada compra
$query = "
    SELECT 
        c.id, c.fecha_compra, c.total_compra, c.estado, c.estado_pago,
        (SELECT SUM(monto_pagado) FROM compras_pagos WHERE id_compra = c.id) as total_pagado
    FROM 
        compras c 
    ORDER BY 
        c.fecha_compra DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>

    <div class="mb-3">
        <a href="compra_form.php" class="btn btn-success"><i class="fas fa-plus"></i> Registrar Nueva Compra</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5>Historial de Compras</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID Compra</th>
                            <th>Fecha</th>
                            <th>Total (USDT)</th>
                            <th>Estado Mercancía</th>
                            <th>Estado de Pago</th> <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($compra['id']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($compra['fecha_compra'])); ?></td>
                            <td>$<?php echo number_format($compra['total_compra'], 2); ?></td>
                            <td>
                                <?php 
                                    $estado = htmlspecialchars($compra['estado']);
                                    $badge_class = 'secondary';
                                    if ($estado == 'En tránsito') $badge_class = 'warning';
                                    if ($estado == 'Recibido') $badge_class = 'success';
                                    if ($estado == 'Cancelado') $badge_class = 'danger';
                                    echo "<span class=\"badge bg-{$badge_class}\">{$estado}</span>";
                                ?>
                            </td>
                            <td>
                                <?php
                                    $total_pagado = $compra['total_pagado'] ?? 0;
                                    $estado_pago_texto = 'Pendiente';
                                    $badge_pago_class = 'secondary';

                                    if ($compra['estado_pago'] == 'Pagado') {
                                        $estado_pago_texto = 'Pagado';
                                        $badge_pago_class = 'success';
                                    } elseif ($total_pagado > 0 && $total_pagado < $compra['total_compra']) {
                                        $estado_pago_texto = 'Pago Parcial';
                                        $badge_pago_class = 'warning';
                                    }
                                    echo "<span class=\"badge bg-{$badge_pago_class}\">{$estado_pago_texto}</span>";
                                ?>
                            </td>
                            <td>
                                <a href="compra_detalle.php?id=<?php echo $compra['id']; ?>" class="btn btn-sm btn-info" title="Ver Detalles"><i class="fas fa-eye"></i></a>
                                
                                <?php if ($compra['estado'] != 'Recibido' && $total_pagado == 0): ?>
                                <form action="../../app/crud/compras_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta orden de compra? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="action" value="delete_compra">
                                    <input type="hidden" name="id_compra" value="<?php echo $compra['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Compra"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>