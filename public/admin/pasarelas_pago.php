<?php
$page_title = 'Configuración de Pasarelas de Pago';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
$stmt->execute();
$credicard_config = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe, preparamos un array con valores por defecto
if (!$credicard_config) {
    $credicard_config = [
        'client_id' => '', 'client_secret' => '', 'email_comercio' => '',
        'url_produccion' => 'https://gateway.credicard.com.ve', 'url_pruebas' => '', 
        'modo_produccion' => 0, 'activo' => 0,
        'comision_debito' => '0.0075', 'comision_credito' => '0.0250',
        'comision_islr' => '0.025862', 'comision_liquidacion' => '0.0200'
    ];
}

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <p>Gestiona las credenciales y el estado de tus pasarelas de pago online.</p>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="card" style="max-width: 800px;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Configuración de Credicard</h5>
            <button id="test-connection-btn" class="btn btn-info btn-sm">Probar Conexión</button>
        </div>
        <div class="card-body">
            <div id="test-result" class="mb-3"></div>
            
            <form action="../../app/crud/pasarelas_pago_process.php" method="POST" id="pasarela-form">
                <div class="mb-3">
                    <label for="client_id" class="form-label">Client ID</label>
                    <input type="text" class="form-control" name="client_id" value="<?php echo htmlspecialchars($credicard_config['client_id']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="client_secret" class="form-label">Client Secret (SecretID)</label>
                    <input type="password" class="form-control" name="client_secret" value="<?php echo htmlspecialchars($credicard_config['client_secret']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email_comercio" class="form-label">Email del Comercio (Registrado en Credicard)</label>
                    <input type="email" class="form-control" name="email_comercio" value="<?php echo htmlspecialchars($credicard_config['email_comercio']); ?>" required>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="url_produccion" class="form-label">URL de Producción</label>
                    <input type="url" class="form-control" name="url_produccion" value="<?php echo htmlspecialchars($credicard_config['url_produccion']); ?>" placeholder="https://gateway.credicard.com.ve" required>
                </div>
                <div class="mb-3">
                    <label for="url_pruebas" class="form-label">URL de Pruebas (Sandbox)</label>
                    <input type="url" class="form-control" name="url_pruebas" value="<?php echo htmlspecialchars($credicard_config['url_pruebas']); ?>" placeholder="Ej: https://gateway-sandbox.credicard.com.ve">
                </div>
                <hr>
                <h6 class="text-muted">Configuración de Comisiones</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comisión Débito (%)</label>
                        <input type="number" step="0.0001" class="form-control" name="comision_debito" value="<?php echo htmlspecialchars($credicard_config['comision_debito'] * 100); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comisión Crédito (%)</label>
                        <input type="number" step="0.0001" class="form-control" name="comision_credito" value="<?php echo htmlspecialchars($credicard_config['comision_credito'] * 100); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comisión ISLR (%)</label>
                        <input type="number" step="0.0001" class="form-control" name="comision_islr" value="<?php echo htmlspecialchars($credicard_config['comision_islr'] * 100); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comisión Liquidación (%)</label>
                        <input type="number" step="0.0001" class="form-control" name="comision_liquidacion" value="<?php echo htmlspecialchars($credicard_config['comision_liquidacion'] * 100); ?>">
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Entorno</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modo_produccion" value="0" id="modo_test" <?php echo ($credicard_config['modo_produccion'] == 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="modo_test">Pruebas</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modo_produccion" value="1" id="modo_prod" <?php echo ($credicard_config['modo_produccion'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="modo_prod">Producción (Real)</label>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Estado de la Pasarela</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?php echo ($credicard_config['activo'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">Activar Credicard en el checkout</label>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#test-connection-btn').on('click', function() {
        const resultDiv = $('#test-result');
        resultDiv.html('<div class="alert alert-secondary">Probando conexión...</div>');
        
        $.ajax({
            url: '../../app/crud/test_credicard_connection.php',
            type: 'POST',
            data: $('#pasarela-form').serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resultDiv.html(`<div class="alert alert-success"><strong>Éxito:</strong> ${response.message}</div>`);
                } else {
                    resultDiv.html(`<div class="alert alert-danger"><strong>Error:</strong> ${response.message}</div>`);
                }
            },
            error: function() {
                resultDiv.html('<div class="alert alert-danger"><strong>Error:</strong> No se pudo contactar al servidor de prueba.</div>');
            }
        });
    });
});
</script>