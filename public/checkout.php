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
$client_data = [];
if (isset($_SESSION['client_id'])) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $client_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container">
    <h1 class="mt-4 mb-4">Finalizar Compra</h1>
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Información de Envío y Contacto</h5>
                    <form action="../app/checkout_process.php" method="POST">
                        <hr class="my-4">
                        <h5 class="card-title">Tipo de Pago</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_contado" value="Contado" checked>
                            <label class="form-check-label" for="pago_contado">
                                Pago de Contado (Zelle, Pago Móvil, Efectivo)
                            </label>
                        </div>
                        
                        <div class="form-check <?php echo ($client_data['permite_credito'] == 1) ? '' : 'disabled text-muted'; ?>">
                            <input class="form-check-input" type="radio" name="tipo_pago" id="pago_credito" value="Crédito" <?php echo ($client_data['permite_credito'] == 1) ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="pago_credito">
                                Pago a Crédito <?php echo ($client_data['permite_credito'] == 1) ? '' : '(No disponible para esta cuenta)'; ?>
                            </label>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" style="background-color: #3D405B; border-color: #3D405B;">Realizar Pedido</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            </div>
    </div>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>