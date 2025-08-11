<?php
header('Content-Type: application/json');

// --- CONFIGURACIÓN DE TU API ---
// !!! IMPORTANTE: REEMPLAZA ESTOS VALORES CON TUS CREDENCIALES REALES !!!
define('API_APP_ID', '1277');
define('API_TOKEN', '80b8eab8a031d3e1be6b32114e33b759');
// ---------------------------------

$cedula = filter_input(INPUT_GET, 'cedula', FILTER_SANITIZE_NUMBER_INT);
$nacionalidad = 'V';

if (empty($cedula)) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionó una cédula.']);
    exit;
}

$apiUrl = sprintf(
    "https://api.cedula.com.ve/api/v1?app_id=%s&token=%s&nacionalidad=%s&cedula=%s",
    API_APP_ID,
    API_TOKEN,
    $nacionalidad,
    $cedula
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión con el servicio de validación.']);
    curl_close($ch);
    exit;
}

curl_close($ch);
$data = json_decode($apiResponse, true);

if ($httpCode == 200 && isset($data['error']) && $data['error'] === false) {
    $primer_nombre = trim($data['data']['primer_nombre']);
    $segundo_nombre = trim($data['data']['segundo_nombre'] ?? '');
    $primer_apellido = trim($data['data']['primer_apellido']);
    $segundo_apellido = trim($data['data']['segundo_apellido'] ?? '');
    
    // ===================================================================
    // LÍNEA CORREGIDA: Usamos 'fecha_nac' en lugar de 'fecha_nacimiento'
    $fecha_nacimiento = $data['data']['fecha_nac'] ?? null; 
    // ===================================================================

    $nombre_completo = $primer_nombre . ($segundo_nombre ? ' ' . $segundo_nombre : '');
    $apellido_completo = $primer_apellido . ($segundo_apellido ? ' ' . $segundo_apellido : '');
    
    $response = [
        'success' => true,
        'nombre' => $nombre_completo,
        'apellido' => $apellido_completo,
        'fecha_nacimiento' => $fecha_nacimiento // Se lo enviamos al frontend con nuestro nombre estándar
    ];
} else {
    $errorMessage = $data['error_str'] ?? 'La cédula no es válida o no se encontró.';
    if ($errorMessage === false) $errorMessage = 'RECORD_NOT_FOUND';
    $response = [
        'success' => false,
        'message' => $errorMessage
    ];
}

echo json_encode($response);
?>