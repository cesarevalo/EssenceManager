<?php
session_start();
require_once '../config/Database.php';

// Validaciones iniciales
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart']) || !isset($_SESSION['client_id'])) {
    header('Location: ../public/catalog.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction(); // Iniciar transacción para asegurar que todas las operaciones se completen

    // 1. Verificar el stock de todos los productos en el carrito
    $cart = $_SESSION['cart'];
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt_stock = $db->prepare("SELECT id, stock FROM productos WHERE id IN ($placeholders)");
    $stmt_stock->execute($ids);
    $stocks_db = $stmt_stock->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($cart as $product_id => $item) {
        if (!isset($stocks_db[$product_id]) || $stocks_db[$product_id] < $item['quantity']) {
            throw new Exception("Lo sentimos, no hay suficiente stock para el producto '" . htmlspecialchars($item['name']) . "'.");
        }
    }

    // 2. Calcular el total de la venta
    $total_venta = 0;
    foreach ($cart as $item) {
        $total_venta += $item['price'] * $item['quantity'];
    }

    // 3. Crear el registro en la tabla 'ventas'
    $query_venta = "INSERT INTO ventas (id_cliente, total_venta_usdt, tipo_pago, estado_pago) VALUES (?, ?, ?, 'Pendiente')";
    $stmt_venta = $db->prepare($query_venta);
    $stmt_venta->execute([
        $_SESSION['client_id'],
        $total_venta,
        $_POST['tipo_pago']
    ]);
    $id_venta = $db->lastInsertId();

    // 4. Crear los registros en 'ventas_detalle' y actualizar el stock
    $query_detalle = "INSERT INTO ventas_detalle (id_venta, id_producto, cantidad, precio_unitario_usdt) VALUES (?, ?, ?, ?)";
    $stmt_detalle = $db->prepare($query_detalle);

    $query_update_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
    $stmt_update_stock = $db->prepare($query_update_stock);

    foreach ($cart as $product_id => $item) {
        // Insertar detalle de la venta
        $stmt_detalle->execute([
            $id_venta,
            $product_id,
            $item['quantity'],
            $item['price']
        ]);
        // Actualizar stock del producto
        $stmt_update_stock->execute([
            $item['quantity'],
            $product_id
        ]);
    }

    // 5. Limpiar el carrito de compras
    unset($_SESSION['cart']);

    // 6. Confirmar la transacción
    $db->commit();
    
    // Opcional: Crear una página de "gracias por tu compra"
    $_SESSION['message'] = '¡Tu pedido ha sido realizado con éxito! Recibirás un correo con los detalles para el pago.';
    $_SESSION['message_type'] = 'success';
    header('Location: ../public/catalog.php'); // Redirigir al catálogo por ahora
    exit;

} catch (Exception $e) {
    $db->rollBack(); // Si algo falló, revertir todos los cambios
    $_SESSION['message'] = 'Error al procesar el pedido: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: ../public/checkout.php');
    exit;
}