<?php
header('Content-Type: application/json');
require_once '../../config/Database.php';
require_once '../../config/config.php'; // Necesitamos la BASE_URL para la tasa

// Recolectar datos del formulario de configuración
$client_id = $_POST['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? '';
$email_comercio = $_POST['email_comercio'] ?? '';
$url_produccion = $_POST['url_produccion'] ?? '';
$url_pruebas = $_POST['url_pruebas'] ?? '';
$modo_produccion = $_POST['modo_produccion'] ?? 0;

// Determinar qué URL usar
$baseUrl = ($modo_produccion == 1) ? $url_produccion : $url_pruebas;
if (empty($baseUrl)) {
    echo json_encode(['success' => false, 'message' => 'La URL para el entorno de pruebas/producción seleccionado no está configurada.']);
    exit;
}
$apiUrl = $baseUrl . "/v1/api/commerce/paymentOrder/clientCredentials";

try {
    // Obtener la tasa de cambio para calcular un monto de prueba realista
    $tasa_response = file_get_contents(BASE_URL . 'public/api/get_exchange_rate.php');
    $tasa_data = json_decode($tasa_response, true);
    if (!$tasa_data || !$tasa_data['success']) {
        throw new Exception("No se pudo obtener la tasa de cambio para la prueba.");
    }
    $tasa = $tasa_data['rate'];
    $test_amount_ves = round(1 * $tasa, 2);

    // Preparar la llamada a la API
    $headers = [
        'client-id: ' . $client_id,
        'client-secret: ' . $client_secret,
        'Content-Type: application/json'
    ];
    $body = json_encode(['email' => $email_comercio, 'amount' => $test_amount_ves, 'concept' => 'Prueba de Conexion']);

    // Ejecutar la llamada cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($apiResponse, true);

    // --- LÓGICA CORREGIDA: Ahora busca 'id' dentro de 'data' ---
    if (in_array($http_code, [200, 201]) && isset($responseData['data']['id'])) {
        echo json_encode(['success' => true, 'message' => 'Conexión exitosa. Se pudo crear una orden de pago de prueba. (HTTP ' . $http_code . ')']);
    } else {
        $error_message = $responseData['message'] ?? $apiResponse;
        echo json_encode(['success' => false, 'message' => 'Falló la conexión. Respuesta de la API (HTTP ' . $http_code . '): ' . htmlspecialchars($error_message)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno en el servidor: ' . $e->getMessage()]);
}
exit;