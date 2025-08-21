<?php
header('Content-Type: application/json');

// URL de la API de CriptoYa
$apiUrl = "https://criptoya.com/api/USDT/VES/0.1";

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => $apiUrl,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 15, // 15 segundos de tiempo de espera
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    // Si hay un error de conexión, devolvemos un JSON de error
    echo json_encode(['success' => false, 'message' => 'Error al contactar el servicio de tasas.']);
    exit;
}

$data = json_decode($response, true);

// Verificamos que la respuesta tenga la estructura esperada
if (!$data || !isset($data['binancep2p']['totalAsk'])) {
    echo json_encode(['success' => false, 'message' => 'No se pudo obtener la tasa de cambio.']);
    exit;
}

// Extraemos la tasa de venta (Ask) de Binance P2P. 
// Esta es la tasa a la que alguien "vende" USDT, que es lo que tu cliente hace al pagarte en VES.
$rate = (float)$data['binancep2p']['totalAsk'];

if ($rate > 0) {
    echo json_encode(['success' => true, 'rate' => $rate]);
} else {
    echo json_encode(['success' => false, 'message' => 'Tasa de cambio no válida recibida.']);
}
exit;
?>