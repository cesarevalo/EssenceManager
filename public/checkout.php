<?php
$page_title = 'Finalizar Compra';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once '../templates/public/header_public.php';

if (empty($_SESSION['cart'])) {
    header('Location: catalog.php');
    exit;
}
if (!isset($_SESSION['client_logged_in'])) {
    $_SESSION['message'] = 'Por favor, inicia sesión o regístrate para continuar.';
    $_SESSION['message_type'] = 'info';
    $_SESSION['redirect_to_checkout'] = true;
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$old_data = $_SESSION['old_post_data'] ?? null;
unset($_SESSION['old_post_data']);

$id_cliente = $_SESSION['client_id'];
$stmt_cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$client_data = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$stmt_direcciones = $db->prepare("SELECT * FROM direcciones WHERE id_cliente = ?");
$stmt_direcciones->execute([$id_cliente]);
$direcciones_guardadas = $stmt_direcciones->fetchAll(PDO::FETCH_ASSOC);

$stmt_pasarela = $db->query("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
$pasarela_config = $stmt_pasarela->fetch(PDO::FETCH_ASSOC);
$pasarela_activa = $pasarela_config['activo'] ?? false;

// Definimos el origen de Credicard para pasarlo al JavaScript de forma segura
$credicard_origin = '';
if($pasarela_config) {
    $url_to_parse = ($pasarela_config['modo_produccion'] == 1) ? $pasarela_config['url_produccion'] : $pasarela_config['url_pruebas'];
    if (!empty($url_to_parse)) {
        $url_parts = parse_url($url_to_parse);
        $credicard_origin = ($url_parts['scheme'] ?? 'https') . '://' . ($url_parts['host'] ?? '');
    }
}
?>

<div class="container">
    <h1 class="mt-4 mb-4">Finalizar Compra</h1>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form id="checkout-form" data-base-url="<?php echo BASE_URL; ?>" data-credicard-origin="<?php echo htmlspecialchars($credicard_origin); ?>" novalidate>
                        <input type="hidden" name="monto_final_ves" id="monto_final_ves_hidden" value="0">

                        <h5 class="card-title">1. Información de Contacto</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($old_data['nombre'] ?? $client_data['nombre'] ?? ''); ?>" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Apellido</label><input type="text" class="form-control" name="apellido" value="<?php echo htmlspecialchars($old_data['apellido'] ?? $client_data['apellido'] ?? ''); ?>" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($old_data['email'] ?? $client_data['email'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label class="form-label">Teléfono</label><input type="tel" class="form-control" name="telefono" value="<?php echo htmlspecialchars($old_data['telefono'] ?? $client_data['telefono'] ?? ''); ?>" required></div>
                        
                        <hr class="my-4">
                        <h5 class="card-title">2. Método de Entrega</h5>
                        <div class="form-check border p-3 rounded mb-2">
                            <input class="form-check-input" type="radio" name="metodo_entrega" id="entrega_tienda" value="Retiro en Tienda" checked>
                            <label class="form-check-label fw-bold" for="entrega_tienda">Retiro Personal en Tienda</label>
                        </div>
                        <div class="form-check border p-3 rounded">
                            <input class="form-check-input" type="radio" name="metodo_entrega" id="entrega_courier" value="Envío por Courier">
                            <label class="form-check-label fw-bold" for="entrega_courier">Envío por Courier (Cobro en Destino)</label>
                        </div>
                        
                        <div id="address-section" class="mt-3 ps-4" style="display:none;">
                            <h6>Selecciona o añade una dirección de envío:</h6>
                            <?php if (!empty($direcciones_guardadas)): ?>
                                <?php foreach ($direcciones_guardadas as $dir): ?>
                                <div class="form-check"><input class="form-check-input" type="radio" name="address_option" id="dir_<?php echo $dir['id']; ?>" value="<?php echo $dir['id']; ?>"><label class="form-check-label" for="dir_<?php echo $dir['id']; ?>"><strong><?php echo htmlspecialchars($dir['ciudad']); ?>:</strong> <?php echo htmlspecialchars($dir['direccion']); ?></label></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="address_option" id="dir_nueva" value="nueva" <?php echo empty($direcciones_guardadas) ? 'checked' : ''; ?>><label class="form-check-label" for="dir_nueva"><strong>Usar una nueva dirección</strong></label></div>
                            <div id="new-address-form" class="mt-2" style="display:<?php echo empty($direcciones_guardadas) ? 'block' : 'none'; ?>;">
                                <div class="mb-2"><textarea class="form-control" name="direccion" placeholder="Dirección completa" rows="2"></textarea></div>
                                <div class="row">
                                    <div class="col-md-6"><input type="text" class="form-control" name="ciudad" placeholder="Ciudad"></div>
                                    <div class="col-md-6"><input type="text" class="form-control" name="estado" placeholder="Estado"></div>
                                </div>
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="guardar_direccion" value="1" id="guardar_direccion" checked><label class="form-check-label" for="guardar_direccion">Guardar dirección</label></div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="card-title">3. Método de Pago</h5>
                        <div class="form-check border p-3 rounded mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="pago_manual" value="manual" checked>
                            <label class="form-check-label fw-bold" for="pago_manual">Pago Manual (Zelle, Pago Móvil, etc.)</label>
                        </div>
                        <?php if ($pasarela_activa): ?>
                        <div class="form-check border p-3 rounded mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="pago_debito" value="credicard_debito">
                            <label class="form-check-label fw-bold" for="pago_debito">Pagar con Tarjeta de Débito Nacional</label>
                        </div>
                        <div class="form-check border p-3 rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="pago_credito" value="credicard_credito">
                            <label class="form-check-label fw-bold" for="pago_credito">Pagar con Tarjeta de Crédito Nacional</label>
                        </div>
                        
                        <div id="final-amount-container" class="alert alert-light border mt-3" style="display:none;">
                            <div id="calculation-details"></div>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid mt-4">
                            <button type="submit" id="submit-button" class="btn btn-primary btn-lg" style="background-color: #3D405B; border-color: #3D405B;">Realizar Pedido</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">Resumen de tu compra</h5>
                    <?php 
                    $cart_total_display = 0;
                    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $item):
                            $subtotal = $item['price'] * $item['quantity'];
                            $cart_total_display += $subtotal;
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo $item['quantity']; ?> x <?php echo htmlspecialchars($item['name']); ?></span>
                        <span><?php echo number_format($subtotal, 2); ?> USDT</span>
                    </div>
                    <?php 
                        endforeach;
                    }
                    ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold h5">
                        <span>Total a Pagar (en productos)</span>
                        <span><?php echo number_format($cart_total_display, 2); ?> USDT</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo BASE_URL; ?>public/js/checkout.js"></script>