<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$id_cliente = $_SESSION['client_id'];
$database = new Database();
$db = $database->getConnection();

// 1. Obtenemos los datos completos del cliente
$stmt_cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$page_title = 'Mi Cuenta';
require_once '../templates/public/header_public.php';

// 2. Calculamos los totales para las tarjetas de resumen
$stmt_summary = $db->prepare("
    SELECT
        COALESCE(SUM(v.total_venta_usdt), 0) AS total_comprado,
        COALESCE((SELECT SUM(p.monto / IF(p.tasa_conversion > 0, p.tasa_conversion, 1)) FROM pagos p JOIN ventas v_p ON p.id_venta = v_p.id WHERE v_p.id_cliente = ? AND p.estado_confirmacion = 'Confirmado'), 0) AS total_pagado
    FROM ventas v
    WHERE v.id_cliente = ?
");
$stmt_summary->execute([$id_cliente, $id_cliente]);
$summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);
$total_comprado = $summary['total_comprado'];
$total_pagado = $summary['total_pagado'];
$saldo_total_pendiente = $total_comprado - $total_pagado;

// 3. Obtener métodos de pago activos y agruparlos por moneda
$stmt_metodos = $db->query("SELECT nombre_metodo, tipo_moneda, datos_pago FROM metodos_pago_config WHERE activo = 1 ORDER BY tipo_moneda, nombre_metodo");
$metodos_activos = $stmt_metodos->fetchAll(PDO::FETCH_ASSOC);
$metodos_por_moneda = [];
foreach ($metodos_activos as $metodo) {
    $metodos_por_moneda[$metodo['tipo_moneda']][] = $metodo;
}
?>

<div class="container">
    <h1 class="my-4">Mi Cuenta</h1>
    <p class="lead">Hola, <?php echo htmlspecialchars(strtoupper($cliente['nombre'])); ?>. Aquí puedes ver el resumen de tu cuenta y tu historial de pedidos.</p>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100"><div class="card-body text-center"><h6 class="card-subtitle mb-2 text-muted text-uppercase">Total Comprado</h6><p class="h3 card-text"><?php echo number_format($total_comprado, 2); ?> USDT</p></div></div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100"><div class="card-body text-center <?php echo ($saldo_total_pendiente > 0.01) ? 'text-danger' : 'text-success'; ?>"><h6 class="card-subtitle mb-2 text-muted text-uppercase">Saldo Pendiente (Deuda)</h6><p class="h3 card-text fw-bold"><?php echo number_format($saldo_total_pendiente, 2); ?> USDT</p></div></div>
        </div>
        <div class="col-md-4 mb-4">
            <?php if ($saldo_total_pendiente > 0.01): ?>
                <button class="btn btn-success btn-lg h-100 w-100" data-bs-toggle="modal" data-bs-target="#abonoModalCliente">
                    <i class="fas fa-hand-holding-usd me-2"></i>Reportar Abono a Cuenta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Información de la Cuenta</h4>
                    <a href="editar_perfil.php" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Editar Información</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars(strtoupper($cliente['nombre'] . ' ' . $cliente['apellido'])); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($cliente['email'] ?? 'Aún no registrado'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?></p>
                            <p><strong>Cédula:</strong> <?php echo htmlspecialchars($cliente['cedula'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4>Preferencias de Comunicación</h4>
                </div>
                <div class="card-body">
                    <form action="../app/client_profile_process.php" method="POST">
                        <input type="hidden" name="action" value="update_preferences">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="acepta_email_pub" value="1" id="acepta_email_pub" <?php echo ($cliente['acepta_email_pub'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="acepta_email_pub">Recibir correos publicitarios</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                             <input class="form-check-input" type="checkbox" name="acepta_ws_pub" value="1" id="acepta_ws_pub" <?php echo ($cliente['acepta_ws_pub'] == 1) ? 'checked' : ''; ?>>
                             <label class="form-check-label" for="acepta_ws_pub">Recibir publicidad por Whatsapp</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">Guardar Preferencias</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="mis_pedidos.php" class="btn btn-primary btn-lg" style="background-color: #3D405B; border-color: #3D405B;">
            <i class="fas fa-history me-2"></i>Ver mi Historial de Pedidos Detallado
        </a>
    </div>
</div>

<div class="modal fade" id="abonoModalCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reportar Abono a Cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="../app/payment_report_process.php" method="POST">
                    <input type="hidden" name="action" value="report_global_payment">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">1. ¿En qué moneda realizaste el pago?</label>
                        <div>
                            <?php foreach ($metodos_por_moneda as $moneda => $metodos): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="moneda_pago_selector" id="moneda_<?php echo $moneda; ?>" value="<?php echo $moneda; ?>">
                                <label class="form-check-label" for="moneda_<?php echo $moneda; ?>"><?php echo $moneda; ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div id="metodo-pago-container" class="mb-3" style="display:none;">
                        <label for="metodo_pago" class="form-label fw-bold">2. ¿Qué método utilizaste?</label>
                        <select name="metodo_pago" id="metodo_pago" class="form-select">
                        </select>
                    </div>

                    <div id="payment-instructions" class="alert alert-info" style="display:none;">
                    </div>

                    <div id="payment-details-container" style="display:none;">
                        <label class="form-label fw-bold">3. Completa los detalles de tu pago</label>
                        <div class="mb-3">
                            <label class="form-label" id="monto-pagado-label">Monto Exacto Pagado</label>
                            <input type="number" step="any" class="form-control" name="monto" id="monto-pagado-input" required>
                            <input type="hidden" name="moneda_pago" id="moneda_pago_hidden">
                        </div>
                        <div id="tasa-conversion-container" class="mb-3" style="display:none;">
                            <label class="form-label">Tasa de Conversión a USDT</label>
                            <input type="number" step="0.00000001" class="form-control" name="tasa_conversion" id="tasa_conversion_input" value="1.00" readonly>
                            <small id="tasa-helper" class="form-text text-muted">Tasa obtenida automáticamente.</small>
                            <div id="monto-convertido-container" class="mt-2" style="display:none;">
                                <strong>Equivale a: <span id="monto-convertido-valor" class="text-success"></span> USDT</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referencia / ID de Transacción</label>
                            <input type="text" class="form-control" name="referencia">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha del Pago</label>
                            <input type="date" class="form-control" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Enviar Reporte de Pago</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const metodosPorMoneda = <?php echo json_encode($metodos_por_moneda); ?>;
    const saldoPendienteUSDT = <?php echo $saldo_total_pendiente; ?>;

    const metodoPagoContainer = document.getElementById('metodo-pago-container');
    const metodoPagoSelect = document.getElementById('metodo_pago');
    const paymentInstructions = document.getElementById('payment-instructions');
    const paymentDetailsContainer = document.getElementById('payment-details-container');
    const monedaPagoHidden = document.getElementById('moneda_pago_hidden');
    const tasaConversionContainer = document.getElementById('tasa-conversion-container');
    const tasaConversionInput = document.getElementById('tasa_conversion_input');
    const tasaHelper = document.getElementById('tasa-helper');
    const montoPagadoInput = document.getElementById('monto-pagado-input');
    const montoPagadoLabel = document.getElementById('monto-pagado-label');
    const montoConvertidoContainer = document.getElementById('monto-convertido-container');
    const montoConvertidoValor = document.getElementById('monto-convertido-valor');
    
    function calcularConversion() {
        const monto = parseFloat(montoPagadoInput.value);
        const tasa = parseFloat(tasaConversionInput.value);
        if (!isNaN(monto) && !isNaN(tasa) && tasa > 0) {
            const convertido = monto / tasa;
            montoConvertidoValor.textContent = convertido.toFixed(2);
            montoConvertidoContainer.style.display = 'block';
        } else {
            montoConvertidoContainer.style.display = 'none';
        }
    }

    document.querySelectorAll('input[name="moneda_pago_selector"]').forEach(radio => {
        radio.addEventListener('change', async function() {
            const selectedMoneda = this.value;
            monedaPagoHidden.value = selectedMoneda;
            
            metodoPagoSelect.innerHTML = '<option value="">Selecciona un método...</option>';
            paymentInstructions.style.display = 'none';
            paymentDetailsContainer.style.display = 'none';
            tasaConversionInput.value = '1.00';
            tasaConversionInput.readOnly = true;
            montoPagadoLabel.textContent = `Monto Exacto Pagado en ${selectedMoneda}`;
            
            if (metodosPorMoneda[selectedMoneda]) {
                metodosPorMoneda[selectedMoneda].forEach(metodo => {
                    const option = document.createElement('option');
                    option.value = metodo.nombre_metodo;
                    option.textContent = metodo.nombre_metodo;
                    option.dataset.datos = metodo.datos_pago;
                    metodoPagoSelect.appendChild(option);
                });
                metodoPagoContainer.style.display = 'block';
            }

            if (selectedMoneda === 'VES') {
                tasaConversionContainer.style.display = 'block';
                tasaConversionInput.value = '';
                tasaHelper.textContent = 'Obteniendo tasa en vivo...';
                
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>public/api/get_exchange_rate.php');
                    const data = await response.json();

                    if (data.success) {
                        const tasa = data.rate;
                        tasaConversionInput.value = tasa;
                        tasaHelper.textContent = 'Tasa obtenida automáticamente.';
                        const montoRecomendadoVES = (saldoPendienteUSDT * tasa).toFixed(2);
                        montoPagadoInput.value = montoRecomendadoVES;
                    } else {
                        tasaHelper.textContent = 'No se pudo obtener la tasa. Por favor, ingrésala manualmente.';
                        tasaConversionInput.readOnly = false;
                    }
                } catch (error) {
                    console.error('Error fetching exchange rate:', error);
                    tasaHelper.textContent = 'Error de red. Por favor, ingrésala manualmente.';
                    tasaConversionInput.readOnly = false;
                }
            } else {
                tasaConversionContainer.style.display = 'none';
                montoPagadoInput.value = saldoPendienteUSDT.toFixed(2);
            }
            calcularConversion();
        });
    });

    metodoPagoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value && selectedOption.dataset.datos) {
            paymentInstructions.innerHTML = `<strong>Datos para realizar el pago:</strong><br>${selectedOption.dataset.datos.replace(/\n/g, '<br>')}`;
            paymentInstructions.style.display = 'block';
            paymentDetailsContainer.style.display = 'block';
        } else {
            paymentInstructions.style.display = 'none';
            paymentDetailsContainer.style.display = 'none';
        }
    });

    montoPagadoInput.addEventListener('keyup', calcularConversion);
    tasaConversionInput.addEventListener('keyup', calcularConversion);
});
</script>