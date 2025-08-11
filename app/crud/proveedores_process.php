<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/proveedores.php');
    exit;
}

$db = (new Database())->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'create':
            $stmt = $db->prepare("INSERT INTO proveedores (nombre, rif, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['rif'], $_POST['telefono'], $_POST['email'], $_POST['direccion']]);
            $_SESSION['message'] = 'Proveedor creado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'update':
            $stmt = $db->prepare("UPDATE proveedores SET nombre = ?, rif = ?, telefono = ?, email = ?, direccion = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre'], $_POST['rif'], $_POST['telefono'], $_POST['email'], $_POST['direccion'], $_POST['id']]);
            $_SESSION['message'] = 'Proveedor actualizado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'delete':
            $stmt = $db->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $_SESSION['message'] = 'Proveedor eliminado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;
    }
} catch (PDOException $e) {
    // CAPTURA INTELIGENTE DEL ERROR DE CLAVE FORÁNEA
    if ($e->getCode() == '23000') {
        $_SESSION['message'] = 'Error: No se puede eliminar este proveedor porque está siendo utilizado por una o más compras.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $_SESSION['message'] = 'Error de base de datos: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/proveedores.php');
exit;