<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';

$log_file = __DIR__ . '/../../logs/credicard.log';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$payment_id = $_POST['paymentId'] ?? null;

if (!$payment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Falta el ID de pago.']);
    exit;
}

$database = new Database();
// --- CORRECCIÓN AQUÍ ---
$db = $database->getConnection();
$final_status = 'UNKNOWN';

try {
    $stmt_cred = $db->query("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
    $config = $stmt_cred->fetch(PDO::FETCH_ASSOC);
    if (!$config) { throw new Exception("La pasarela de pago no está configurada."); }

    $baseUrl = ($config['modo_produccion'] == 1) ? $config['url_produccion'] : $config['url_pruebas'];
    $apiUrl = $baseUrl . "/v1/api/commerce/paymentOrder/clientCredentials/" . $payment_id;

    $headers = [ 'client-id: ' . $config['client_id'], 'client-secret: ' . $config['client_secret'] ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    curl_close($ch);
    
    $log_message = date('[Y-m-d H:i:s] ') . "CHECK STATUS (API - UNEXPECTED CLOSE) RESPONSE: " . $apiResponse . "\n";
    error_log($log_message, 3, $log_file);
    
    $responseData = json_decode($apiResponse, true);
    $final_status = $responseData['data']['status'] ?? 'UNKNOWN';

    if (strcasecmp($final_status, 'PAID') !== 0) {
        $stmt_cancel = $db->prepare("UPDATE ventas SET estado_pago = 'Anulado' WHERE pasarela_payment_id = ? AND estado_pago = 'Pendiente'");
        $stmt_cancel->execute([$payment_id]);
    }
    
    echo json_encode(['success' => true, 'status' => strtoupper($final_status)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>