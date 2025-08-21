<?php
session_start();
require_once '../config/Database.php';

// Seguridad: solo procesar si el método es POST y el cliente ha iniciado sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['client_id'])) {
    header('Location: ../public/catalog.php');
    exit;
}

$id_cliente = $_SESSION['client_id'];
$id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_SANITIZE_NUMBER_INT);

if (empty($id_producto)) {
    header('Location: ../public/catalog.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verificar que no exista ya una solicitud (doble seguridad)
    $stmt_check = $db->prepare("SELECT id FROM notificaciones_stock WHERE id_cliente = ? AND id_producto = ?");
    $stmt_check->execute([$id_cliente, $id_producto]);
    
    if (!$stmt_check->fetch()) {
        // Si no existe, la insertamos
        $stmt_insert = $db->prepare("INSERT INTO notificaciones_stock (id_cliente, id_producto) VALUES (?, ?)");
        $stmt_insert->execute([$id_cliente, $id_producto]);
        
        $_SESSION['message'] = '¡Perfecto! Te avisaremos por correo cuando este producto esté disponible.';
        $_SESSION['message_type'] = 'success';
    } else {
        // Si ya existía, solo mostramos un mensaje informativo
        $_SESSION['message'] = 'Ya estabas en la lista de espera para este producto.';
        $_SESSION['message_type'] = 'info';
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Error: No se pudo procesar tu solicitud.';
    $_SESSION['message_type'] = 'danger';
}

// Redirigir de vuelta a la página del producto
header('Location: ../public/product_detail.php?id=' . $id_producto);
exit;