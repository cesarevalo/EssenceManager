<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/admin/pasarelas_pago.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Recolectar datos
$client_id = $_POST['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? '';
$email_comercio = $_POST['email_comercio'] ?? '';
$url_produccion = $_POST['url_produccion'] ?? '';
$url_pruebas = $_POST['url_pruebas'] ?? '';
$modo_produccion = $_POST['modo_produccion'] ?? 0;
$activo = isset($_POST['activo']) ? 1 : 0;

try {
    // Verificar si ya existe una configuración para Credicard
    $stmt = $db->prepare("SELECT id FROM configuracion_pasarelas WHERE pasarela = 'Credicard'");
    $stmt->execute();
    
    if ($stmt->fetch()) {
        // Si existe, la actualizamos (UPDATE)
        $sql = "UPDATE configuracion_pasarelas SET client_id = ?, client_secret = ?, email_comercio = ?, url_produccion = ?, url_pruebas = ?, modo_produccion = ?, activo = ? WHERE pasarela = 'Credicard'";
        $stmt_update = $db->prepare($sql);
        $stmt_update->execute([$client_id, $client_secret, $email_comercio, $url_produccion, $url_pruebas, $modo_produccion, $activo]);
    } else {
        // Si no existe, la creamos (INSERT)
        $sql = "INSERT INTO configuracion_pasarelas (pasarela, client_id, client_secret, email_comercio, url_produccion, url_pruebas, modo_produccion, activo) VALUES ('Credicard', ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $db->prepare($sql);
        $stmt_insert->execute([$client_id, $client_secret, $email_comercio, $url_produccion, $url_pruebas, $modo_produccion, $activo]);
    }
    $_SESSION['message'] = 'La configuración de Credicard se ha guardado exitosamente.';
    $_SESSION['message_type'] = 'success';
} catch (Exception $e) {
    $_SESSION['message'] = 'Error al guardar la configuración: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/pasarelas_pago.php');
exit;