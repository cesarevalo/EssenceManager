<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

// Seguridad: Si el cliente no ha iniciado sesión, lo redirigimos al login
if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$id_cliente = $_SESSION['client_id'];
$database = new Database();
$db = $database->getConnection();

// 1. Obtenemos el nombre del cliente
$stmt_cliente = $db->prepare("SELECT nombre, apellido FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$page_title = 'Mis Pedidos';
require_once '../templates/public/header_public.php';

// 2. CONSULTA ACTUALIZADA: Obtiene cada producto comprado individualmente
$query_productos = "
    SELECT 
        p.id AS id_producto,
        p.nombre AS producto_nombre,
        p.imagen_url,
        vd.cantidad,
        vd.precio_unitario_usdt,
        v.fecha_venta,
        v.estado_pago,
        v.id AS id_venta
    FROM ventas_detalle vd
    JOIN productos p ON vd.id_producto = p.id
    JOIN ventas v ON vd.id_venta = v.id
    WHERE v.id_cliente = ?
    ORDER BY v.fecha_venta DESC;
";
$stmt_productos = $db->prepare($query_productos);
$stmt_productos->execute([$id_cliente]);
$productos_comprados = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">
    <h1 class="my-4">Mis Pedidos</h1>
    <p class="lead">Hola, <?php echo htmlspecialchars(strtoupper($cliente['nombre'])); ?>. Aquí puedes ver el detalle de todos los productos que has comprado.</p>

    <div class="card">
        <div class="card-header">
            <h4>Historial de Productos Comprados</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Fecha de Compra</th>
                            <th>Monto Total</th>
                            <th>Estado del Pedido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos_comprados)): ?>
                            <tr><td colspan="6" class="text-center">Aún no has realizado ninguna compra.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos_comprados as $producto): ?>
                                <tr>
                                    <td>
                                        <a href="product_detail.php?id=<?php echo $producto['id_producto']; ?>">
                                            <img src="<?php echo BASE_URL; ?>public/uploads/products/<?php echo htmlspecialchars($producto['imagen_url'] ?? 'placeholder.png'); ?>" alt="<?php echo htmlspecialchars($producto['producto_nombre']); ?>" width="60" class="img-thumbnail">
                                        </a>
                                    </td>
                                    <td>
                                        <a href="product_detail.php?id=<?php echo $producto['id_producto']; ?>">
                                            <?php echo htmlspecialchars($producto['producto_nombre']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($producto['fecha_venta'])); ?></td>
                                    <td class="fw-bold"><?php echo number_format($producto['precio_unitario_usdt'] * $producto['cantidad'], 2); ?> USDT</td>
                                    <td>
                                        <?php 
                                            $estado = htmlspecialchars($producto['estado_pago']);
                                            $badge_class = 'secondary';
                                            if ($estado == 'Pendiente' || $estado == 'Abonado') $badge_class = 'warning';
                                            if ($estado == 'Pagado') $badge_class = 'success';
                                            echo "<span class=\"badge bg-{$badge_class}\">{$estado}</span>";
                                        ?>
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

<?php require_once '../templates/public/footer_public.php'; ?>