<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida.']);
    exit;
}

$card_type = $_POST['card_type'] ?? '';
if (!in_array($card_type, ['debito', 'credito'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de tarjeta no válido.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Obtener la configuración de la pasarela y las comisiones
    $stmt_config = $db->query("SELECT * FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        throw new Exception("Configuración de pasarela no encontrada en la base de datos.");
    }

    // 2. OBTENER LA TASA DE CAMBIO DIRECTAMENTE DESDE CRIPTOYA
    $apiUrl = "https://criptoya.com/api/USDT/VES/0.1";
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        throw new Exception("No se pudo contactar el servicio de tasas de cambio.");
    }
    $tasa_data = json_decode($response, true);
    if (!$tasa_data || !isset($tasa_data['binancep2p']['totalAsk'])) {
        throw new Exception("La respuesta de la API de tasas no tiene el formato esperado.");
    }
    $tasa_ves_usdt = (float)$tasa_data['binancep2p']['totalAsk'];
    if ($tasa_ves_usdt <= 0) {
        throw new Exception("La tasa de cambio recibida no es válida.");
    }

    // 3. Calcular el total del carrito en USDT y luego el monto base en VES
    $total_usdt = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_usdt += $item['price'] * $item['quantity'];
    }
    $monto_base_ves = $total_usdt * $tasa_ves_usdt;

    // 4. Lógica de cálculo inverso (Gross-Up)
    $monto_final_ves = 0;
    if ($card_type === 'debito') {
        $factor_descuento = (1 - $config['comision_debito']) * (1 - $config['comision_liquidacion']);
    } else { // credito
        $factor_descuento = (1 - $config['comision_credito']) * (1 - $config['comision_islr']) * (1 - $config['comision_liquidacion']);
    }
    
    if ($factor_descuento > 0) {
        $monto_final_ves = $monto_base_ves / $factor_descuento;
    } else {
        throw new Exception("El factor de comisión configurado es inválido.");
    }

    $monto_comision_ves = $monto_final_ves - $monto_base_ves;

    echo json_encode([
        'success' => true,
        'monto_final_ves' => round($monto_final_ves, 2),
        'tasa_usada' => $tasa_ves_usdt,
        'monto_base_ves' => round($monto_base_ves, 2),
        'desglose_comisiones' => [
            ['nombre' => 'Comisiones y Gastos de Pasarela', 'monto' => $monto_comision_ves]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;