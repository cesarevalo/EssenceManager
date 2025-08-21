<?php
$page_title = 'Revisión de Pagos Pendientes';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$query = "
    SELECT p.*, c.nombre, c.apellido 
    FROM pagos p 
    JOIN ventas v ON p.id_venta = v.id
    JOIN clientes c ON v.id_cliente = c.id
    WHERE p.estado_confirmacion = 'Pendiente'
    ORDER BY p.fecha_pago ASC
";
$stmt = $db->prepare($query);
$stmt->execute();
$pagos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <p>Aquí se listan los pagos reportados por los clientes que necesitan tu confirmación.</p>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Fecha Reporte</th>
                            <th>Cliente</th>
                            <th>Venta ID</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos_pendientes)): ?>
                            <tr><td colspan="7" class="text-center">No hay pagos pendientes por revisar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pagos_pendientes as $pago): ?>
                                <tr>
                                    <td><?php echo date("d/m/Y", strtotime($pago['fecha_pago'])); ?></td>
                                    <td><?php echo htmlspecialchars(strtoupper($pago['nombre'] . ' ' . $pago['apellido'])); ?></td>
                                    <td><a href="venta_detalle.php?id=<?php echo $pago['id_venta']; ?>">#<?php echo $pago['id_venta']; ?></a></td>
                                    <td class="fw-bold"><?php echo number_format($pago['monto'], 2) . ' ' . $pago['moneda_pago']; ?></td>
                                    <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                                    <td><?php echo htmlspecialchars($pago['referencia']); ?></td>
                                    <td class="text-nowrap">
                                        <form action="../../app/crud/pagos_admin_process.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="confirm_payment">
                                            <input type="hidden" name="id_pago" value="<?php echo $pago['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Confirmar Pago"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="../../app/crud/pagos_admin_process.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="reject_payment">
                                            <input type="hidden" name="id_pago" value="<?php echo $pago['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Rechazar Pago"><i class="fas fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>