<?php
session_start();
require_once '../config/Database.php';
require_once '../config/config.php';

// --- RUTA CORREGIDA ---
$log_file = __DIR__ . '/../logs/credicard.log';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

header('Content-Type: application/json');

if (empty($_SESSION['cart']) || !isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida o carrito vacío.']);
    exit;
}

$payment_method = $_POST['payment_method'] ?? 'manual';
$id_cliente = $_SESSION['client_id'];
$cart = $_SESSION['cart'];
$database = new Database();
$db = $database->getConnection();

try {
    $metodo_entrega = $_POST['metodo_entrega'] ?? 'Retiro en Tienda';
    $direccion_envio_texto = null;

    if ($metodo_entrega === 'Envío por Courier') {
        $address_option = $_POST['address_option'] ?? 'nueva';

        if ($address_option === 'nueva') {
            $direccion = filter_input(INPUT_POST, 'direccion', FILTER_SANITIZE_STRING);
            $ciudad = filter_input(INPUT_POST, 'ciudad', FILTER_SANITIZE_STRING);
            $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING);
            
            if (empty($direccion) || empty($ciudad) || empty($estado)) {
                throw new Exception("Si eliges 'Envío por Courier', debes proporcionar una dirección de envío completa.");
            }
            $direccion_envio_texto = "$direccion, $ciudad, $estado.";

            if (isset($_POST['guardar_direccion'])) {
                $stmt_save = $db->prepare("INSERT INTO direcciones (id_cliente, direccion, ciudad, estado, tipo) VALUES (?, ?, ?, ?, 'entrega')");
                $stmt_save->execute([$id_cliente, $direccion, $ciudad, $estado]);
            }
        } else {
            $id_direccion = filter_var($address_option, FILTER_SANITIZE_NUMBER_INT);
            $stmt_get_dir = $db->prepare("SELECT * FROM direcciones WHERE id = ? AND id_cliente = ?");
            $stmt_get_dir->execute([$id_direccion, $id_cliente]);
            $dir_data = $stmt_get_dir->fetch(PDO::FETCH_ASSOC);
            if ($dir_data) {
                $direccion_envio_texto = "{$dir_data['direccion']}, {$dir_data['ciudad']}, {$dir_data['estado']}.";
            } else {
                throw new Exception("La dirección seleccionada no es válida.");
            }
        }
    }
    
    $db->beginTransaction();

    if (strpos($payment_method, 'credicard') === 0) {
        $stmt_cred = $db->query("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard' AND activo = 1");
        $config = $stmt_cred->fetch(PDO::FETCH_ASSOC);
        if (!$config) { throw new Exception("La pasarela de pago no está configurada o está inactiva."); }

        $total_ves = filter_input(INPUT_POST, 'monto_final_ves', FILTER_VALIDATE_FLOAT);
        if (!$total_ves || $total_ves <= 0) {
            throw new Exception("El monto final a pagar no es válido. Por favor, vuelve a seleccionar un tipo de tarjeta.");
        }

        $tasa_response = file_get_contents(BASE_URL . 'public/api/get_exchange_rate.php');
        $tasa_data = json_decode($tasa_response, true);
        if (!$tasa_data || !$tasa_data['success']) {
            throw new Exception("No se pudo obtener la tasa de cambio para registrar la venta.");
        }
        $tasa = $tasa_data['rate'];

        $total_usdt = 0;
        foreach ($cart as $item) { $total_usdt += $item['price'] * $item['quantity']; }
        
        $query_venta_inicial = "INSERT INTO ventas (id_cliente, total_venta_usdt, tasa_venta, tipo_pago, estado_pago, metodo_entrega, direccion_envio) VALUES (?, ?, ?, ?, 'Iniciado', ?, ?)";
        $stmt_venta_inicial = $db->prepare($query_venta_inicial);
        $stmt_venta_inicial->execute([$id_cliente, $total_usdt, $tasa, 'Tarjeta', $metodo_entrega, $direccion_envio_texto]);
        $id_venta = $db->lastInsertId();

        $nombre_cliente = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
        $apellido_cliente = filter_input(INPUT_POST, 'apellido', FILTER_SANITIZE_STRING);
        $nombre_completo = trim($nombre_cliente . ' ' . $apellido_cliente);
        
        $tipo_tarjeta_texto = '';
        if ($payment_method === 'credicard_debito') {
            $tipo_tarjeta_texto = 'TDDN';
        } elseif ($payment_method === 'credicard_credito') {
            $tipo_tarjeta_texto = 'TDCN';
        }
        $concepto_pago = "Pago de {$nombre_completo} {$tipo_tarjeta_texto} #{$id_venta}";

        $baseUrl = ($config['modo_produccion'] == 1) ? $config['url_produccion'] : $config['url_pruebas'];
        if (empty($baseUrl)) { throw new Exception("La URL para el entorno seleccionado no está configurada."); }
        $apiUrl = $baseUrl . "/v1/api/commerce/paymentOrder/clientCredentials";

        $headers = [ 'client-id: ' . $config['client_id'], 'client-secret: ' . $config['client_secret'], 'Content-Type: application/json' ];
        
        $body = json_encode([
            'email' => $config['email_comercio'],
            'amount' => $total_ves,
            'concept' => $concepto_pago 
        ]);
        
        error_log(date('[Y-m-d H:i:s] ') . "CREATE PAYMENT REQUEST BODY: " . $body . "\n", 3, $log_file);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiResponse = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log(date('[Y-m-d H:i:s] ') . "CREATE PAYMENT RESPONSE: " . $apiResponse . "\n", 3, $log_file);
        
        $responseData = json_decode($apiResponse, true);
        
        if (!in_array($http_code, [200, 201]) || !isset($responseData['data']['id'])) {
            $db->rollBack();
            error_log(date('[Y-m-d H:i:s] ') . "ERROR ON CREATE: " . $apiResponse . "\n", 3, $log_file);
            throw new Exception("Error de la pasarela: " . htmlspecialchars($responseData['message'] ?? $apiResponse));
        }
        
        $paymentId = $responseData['data']['id'];
        $paymentUrl = $responseData['data']['paymentUrl'];

        $stmt_update_venta = $db->prepare("UPDATE ventas SET pasarela_payment_id = ?, estado_pago = 'Pendiente' WHERE id = ?");
        $stmt_update_venta->execute([$paymentId, $id_venta]);
        
        $_SESSION['pending_sale_id'] = $id_venta;
        $_SESSION['pending_payment_id'] = $paymentId;
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'paymentUrl' => $paymentUrl,
            'paymentId' => $paymentId
        ]);
        exit;

    } else {
        // ... (código de pago manual sin cambios) ...
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['old_post_data'] = $_POST;
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}