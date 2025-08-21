<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/metodos_pago.php');
    exit;
}

$db = (new Database())->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'create':
            $activo = isset($_POST['activo']) ? 1 : 0;
            $stmt = $db->prepare("INSERT INTO metodos_pago_config (nombre_metodo, tipo_moneda, datos_pago, activo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['nombre_metodo'], $_POST['tipo_moneda'], $_POST['datos_pago'], $activo]);
            $_SESSION['message'] = 'Método de pago creado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'update':
            $activo = isset($_POST['activo']) ? 1 : 0;
            $stmt = $db->prepare("UPDATE metodos_pago_config SET nombre_metodo = ?, tipo_moneda = ?, datos_pago = ?, activo = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre_metodo'], $_POST['tipo_moneda'], $_POST['datos_pago'], $activo, $_POST['id']]);
            $_SESSION['message'] = 'Método de pago actualizado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'delete':
            $stmt = $db->prepare("DELETE FROM metodos_pago_config WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $_SESSION['message'] = 'Método de pago eliminado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/metodos_pago.php');
exit;