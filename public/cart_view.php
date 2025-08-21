<?php
$page_title = 'Mi Carrito de Compras';
require_once __DIR__ . '/../config/config.php';
require_once '../templates/public/header_public.php';

$cart_items = $_SESSION['cart'] ?? [];
$cart_total = 0;
?>

<div class="container">
    <h1 class="mt-4 mb-4">Mi Carrito de Compras</h1>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info text-center">
            <p class="lead">Tu carrito está vacío.</p>
            <a href="catalog.php" class="btn btn-primary" style="background-color: #3D405B; border-color: #3D405B;">Volver al Catálogo</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <?php foreach ($cart_items as $product_id => $item): ?>
                            <?php $subtotal = $item['price'] * $item['quantity']; $cart_total += $subtotal; ?>
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-2">
                                    <img src="uploads/products/<?php echo htmlspecialchars($item['image'] ?? 'placeholder.png'); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <h5 class="card-title" style="font-family: 'Playfair Display', serif;"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <p class="text-muted mb-0"><?php echo number_format($item['price'], 2); ?> USDT</p>
                                </div>
                                <div class="col-md-3">
                                    <form action="../app/cart_process.php" method="POST" class="d-flex">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                        <input type="number" name="quantity" class="form-control form-control-sm" value="<?php echo $item['quantity']; ?>" min="1" style="width: 70px;">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm ms-2" title="Actualizar cantidad"><i class="fas fa-sync-alt"></i></button>
                                    </form>
                                </div>
                                <div class="col-md-2 text-end">
                                    <strong><?php echo number_format($subtotal, 2); ?> USDT</strong>
                                </div>
                                <div class="col-md-1 text-end">
                                    <form action="../app/cart_process.php" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar producto"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php if(next($cart_items)): ?><hr><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resumen del Pedido</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Subtotal
                                <span><?php echo number_format($cart_total, 2); ?> USDT</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Envío
                                <span><em>(Calculado al finalizar)</em></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center fw-bold h5">
                                Total
                                <span><?php echo number_format($cart_total, 2); ?> USDT</span>
                            </li>
                        </ul>
                        <div class="d-grid gap-2 mt-3">
                            <a href="checkout.php" class="btn btn-primary" style="background-color: #3D405B; border-color: #3D405B;">Proceder al Pago</a>
                            <a href="catalog.php" class="btn btn-outline-secondary">Seguir Comprando</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>