<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

$log_file = __DIR__ . '/../logs/credicard.log';

if (!isset($_SESSION['pending_sale_id']) || !isset($_GET['payment_id'])) {
    header('Location: catalog.php');
    exit;
}

$id_venta_pendiente = $_SESSION['pending_sale_id'];
$payment_id_from_url = filter_var($_GET['payment_id'], FILTER_SANITIZE_STRING);
$payment_id_from_session = $_SESSION['pending_payment_id'] ?? null;

if ($payment_id_from_url !== $payment_id_from_session) {
    unset($_SESSION['pending_sale_id'], $_SESSION['pending_payment_id']);
    header('Location: catalog.php');
    exit;
}

$database = new Database();
// --- CORRECCIÓN AQUÍ ---
$db = $database->getConnection();
$page_title = 'Estado de tu Pedido';
require_once '../templates/public/header_public.php';

$payment_success = false;
$error_message = '';

try {
    $stmt_cred = $db->query("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
    $config = $stmt_cred->fetch(PDO::FETCH_ASSOC);
    if (!$config) { throw new Exception("La pasarela de pago no está configurada."); }

    $baseUrl = ($config['modo_produccion'] == 1) ? $config['url_produccion'] : $config['url_pruebas'];
    $apiUrl = $baseUrl . "/v1/api/commerce/paymentOrder/clientCredentials/" . $payment_id_from_session;

    $headers = [ 'client-id: ' . $config['client_id'], 'client-secret: ' . $config['client_secret'] ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    curl_close($ch);
    
    $log_message = date('[Y-m-d H:i:s] ') . "CHECK STATUS (PAGE) RESPONSE: " . $apiResponse . "\n";
    error_log($log_message, 3, $log_file);
    
    $responseData = json_decode($apiResponse, true);
    $status_final = $responseData['data']['status'] ?? 'UNKNOWN';

    if (strcasecmp($status_final, 'PAID') === 0) {
        $db->beginTransaction();
        
        $stmt_update_venta = $db->prepare("UPDATE ventas SET estado_pago = 'Pagado' WHERE id = ?");
        $stmt_update_venta->execute([$id_venta_pendiente]);
        
        $cart = $_SESSION['cart'];
        $query_detalle = "INSERT INTO ventas_detalle (id_venta, id_producto, cantidad, precio_unitario_usdt) VALUES (?, ?, ?, ?)";
        $stmt_detalle = $db->prepare($query_detalle);
        $query_update_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
        $stmt_update_stock = $db->prepare($query_update_stock);

        foreach ($cart as $product_id => $item) {
            $stmt_detalle->execute([$id_venta_pendiente, $product_id, $item['quantity'], $item['price']]);
            $stmt_update_stock->execute([$item['quantity'], $product_id]);
        }
        
        $db->commit();
        $payment_success = true;

    } else {
        $stmt_cancel_venta = $db->prepare("UPDATE ventas SET estado_pago = 'Anulado' WHERE id = ?");
        $stmt_cancel_venta->execute([$id_venta_pendiente]);
        throw new Exception("El pago fue declinado, cancelado o no pudo ser procesado. Estado final: " . htmlspecialchars($status_final));
    }
} catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    $error_message = $e->getMessage();
}

unset($_SESSION['cart']);
unset($_SESSION['pending_sale_id']);
unset($_SESSION['pending_payment_id']);
?>

<div class="container text-center my-5">
    <?php if ($payment_success): ?>
        <i class="fas fa-check-circle fa-5x text-success mb-4"></i>
        <h1 class="display-4">¡Pago Exitoso!</h1>
        <p class="lead">Tu pedido #<?php echo $id_venta_pendiente; ?> ha sido procesado correctamente.</p>
        <p>Gracias por tu compra.</p>
        <a href="mis_pedidos.php" class="btn btn-primary mt-3">Ver mis Pedidos</a>
    <?php else: ?>
        <i class="fas fa-times-circle fa-5x text-danger mb-4"></i>
        <h1 class="display-4">Pago Fallido</h1>
        <p class="lead">Hubo un problema al procesar tu pago.</p>
        <p class="text-muted"><strong>Motivo:</strong> <?php echo $error_message; ?></p>
        <p>No se ha realizado ningún cargo a tu tarjeta. Por favor, intenta de nuevo.</p>
        <a href="checkout.php" class="btn btn-warning mt-3">Intentar de Nuevo</a>
    <?php endif; ?>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>