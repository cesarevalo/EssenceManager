<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../public/catalog.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'add':
            $id_producto = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

            if (!$id_producto || !$quantity || $quantity <= 0) {
                throw new Exception('Datos de producto inválidos.');
            }

            $stmt = $db->prepare("SELECT nombre, precio_usdt, imagen_url, stock FROM productos WHERE id = ?");
            $stmt->execute([$id_producto]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) throw new Exception('Producto no encontrado.');

            $new_quantity = isset($_SESSION['cart'][$id_producto]) ? $_SESSION['cart'][$id_producto]['quantity'] + $quantity : $quantity;
            if ($product['stock'] < $new_quantity) {
                throw new Exception('No hay suficiente stock. Solo quedan ' . $product['stock'] . ' unidades.');
            }

            if (isset($_SESSION['cart'][$id_producto])) {
                $_SESSION['cart'][$id_producto]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$id_producto] = [
                    'name' => $product['nombre'], 'price' => $product['precio_usdt'],
                    'image' => $product['imagen_url'], 'quantity' => $quantity
                ];
            }
            
            $_SESSION['message'] = '¡Producto añadido al carrito!';
            $_SESSION['message_type'] = 'success';
            header('Location: ../public/product_detail.php?id=' . $id_producto);
            exit;

        case 'update_quantity':
            $id_producto = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

            if (!$id_producto) {
                header('Location: ../public/cart_view.php'); exit;
            }

            if ($quantity <= 0) {
                unset($_SESSION['cart'][$id_producto]);
            } else {
                 $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ?");
                 $stmt->execute([$id_producto]);
                 $stock = $stmt->fetchColumn();

                 if ($stock < $quantity) {
                     throw new Exception('No hay suficiente stock. Solo quedan ' . $stock . ' unidades.');
                 }
                $_SESSION['cart'][$id_producto]['quantity'] = $quantity;
            }
            header('Location: ../public/cart_view.php');
            exit;

        case 'remove':
            $id_producto = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            if ($id_producto && isset($_SESSION['cart'][$id_producto])) {
                unset($_SESSION['cart'][$id_producto]);
            }
            $_SESSION['message'] = 'Producto eliminado del carrito.';
            $_SESSION['message_type'] = 'warning';
            header('Location: ../public/cart_view.php');
            exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    
    $referer = $_SERVER['HTTP_REFERER'] ?? '../public/catalog.php';
    header("Location: $referer");
    exit;
}