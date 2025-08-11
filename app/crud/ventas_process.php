<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/ventas.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    $db->beginTransaction();

    switch ($action) {
        case 'create_manual_sale':
            $client_type = $_POST['client_type'] ?? 'existing';
            $id_cliente = null;

            if ($client_type === 'new') {
                $nombre_raw = filter_input(INPUT_POST, 'nombre_nuevo', FILTER_SANITIZE_STRING);
                $telefono_raw = filter_input(INPUT_POST, 'telefono_nuevo', FILTER_SANITIZE_NUMBER_INT);
                if (empty($nombre_raw) || empty($telefono_raw)) {
                    throw new Exception("El nombre y el teléfono son obligatorios para un cliente nuevo.");
                }
                $nombre = mb_strtoupper($nombre_raw, 'UTF-8');
                if (substr($telefono_raw, 0, 1) === '0') {
                    $telefono_limpio = substr($telefono_raw, 1);
                } else {
                    $telefono_limpio = $telefono_raw;
                }
                $telefono_final = '58' . $telefono_limpio;
                $query_new_client = "INSERT INTO clientes (nombre, apellido, email, password, telefono, pais) VALUES (?, '', NULL, NULL, ?, 'VE')";
                $stmt_new_client = $db->prepare($query_new_client);
                $stmt_new_client->execute([$nombre, $telefono_final]);
                $id_cliente = $db->lastInsertId();
            } else {
                $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_SANITIZE_NUMBER_INT);
                if (empty($id_cliente)) {
                    throw new Exception("Debes seleccionar un cliente existente.");
                }
            }
            $id_vendedor = $_SESSION['user_id'] ?? null;
            $query_venta = "INSERT INTO ventas (id_cliente, id_vendedor, total_venta_usdt, tipo_pago, estado_pago) VALUES (?, ?, 0, 'Contado', 'Pendiente')";
            $stmt_venta = $db->prepare($query_venta);
            $stmt_venta->execute([$id_cliente, $id_vendedor]);
            $new_venta_id = $db->lastInsertId();
            header('Location: ../../public/admin/venta_detalle.php?id=' . $new_venta_id);
            break;

        case 'add_product_to_venta':
            $id_venta = filter_input(INPUT_POST, 'id_venta', FILTER_SANITIZE_NUMBER_INT);
            $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_SANITIZE_NUMBER_INT);
            $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_SANITIZE_NUMBER_INT);
            if(empty($id_venta) || empty($id_producto) || empty($cantidad) || $cantidad <= 0) {
                throw new Exception("Datos de producto inválidos.");
            }
            $stmt_prod = $db->prepare("SELECT precio_usdt, stock FROM productos WHERE id = ?");
            $stmt_prod->execute([$id_producto]);
            $producto = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            if (!$producto) throw new Exception("Producto no encontrado.");
            if ($producto['stock'] < $cantidad) throw new Exception("Stock insuficiente. Solo quedan " . $producto['stock'] . " unidades.");
            $precio_unitario = $producto['precio_usdt'];
            $stmt_add = $db->prepare("INSERT INTO ventas_detalle (id_venta, id_producto, cantidad, precio_unitario_usdt) VALUES (?, ?, ?, ?)");
            $stmt_add->execute([$id_venta, $id_producto, $cantidad, $precio_unitario]);
            $subtotal = $cantidad * $precio_unitario;
            $stmt_update_total = $db->prepare("UPDATE ventas SET total_venta_usdt = total_venta_usdt + ? WHERE id = ?");
            $stmt_update_total->execute([$subtotal, $id_venta]);
            $stmt_update_stock = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmt_update_stock->execute([$cantidad, $id_producto]);
            $_SESSION['message'] = 'Producto añadido a la venta y stock actualizado.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/venta_detalle.php?id=' . $id_venta);
            break;

        case 'remove_product_from_venta':
            $id_venta = filter_input(INPUT_POST, 'id_venta', FILTER_SANITIZE_NUMBER_INT);
            $id_venta_detalle = filter_input(INPUT_POST, 'id_venta_detalle', FILTER_SANITIZE_NUMBER_INT);
            $stmt_check_payments = $db->prepare("SELECT COUNT(id) FROM pagos WHERE id_venta = ?");
            $stmt_check_payments->execute([$id_venta]);
            if ($stmt_check_payments->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar un producto de una venta que ya tiene pagos registrados.");
            }
            $stmt_get = $db->prepare("SELECT * FROM ventas_detalle WHERE id = ?");
            $stmt_get->execute([$id_venta_detalle]);
            $detalle = $stmt_get->fetch(PDO::FETCH_ASSOC);
            if (!$detalle) throw new Exception("El producto no se encontró en la venta.");
            $stmt_return_stock = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            $stmt_return_stock->execute([$detalle['cantidad'], $detalle['id_producto']]);
            $subtotal_a_restar = $detalle['cantidad'] * $detalle['precio_unitario_usdt'];
            $stmt_update_total = $db->prepare("UPDATE ventas SET total_venta_usdt = total_venta_usdt - ? WHERE id = ?");
            $stmt_update_total->execute([$subtotal_a_restar, $id_venta]);
            $stmt_delete = $db->prepare("DELETE FROM ventas_detalle WHERE id = ?");
            $stmt_delete->execute([$id_venta_detalle]);
            $_SESSION['message'] = 'Producto eliminado de la venta y stock devuelto.';
            $_SESSION['message_type'] = 'warning';
            header('Location: ../../public/admin/venta_detalle.php?id=' . $id_venta);
            break;

        case 'delete_venta':
            $id_venta = filter_input(INPUT_POST, 'id_venta', FILTER_SANITIZE_NUMBER_INT);
            if (empty($id_venta)) {
                throw new Exception("ID de venta no válido.");
            }
            $stmt_check = $db->prepare("SELECT COUNT(id) FROM pagos WHERE id_venta = ?");
            $stmt_check->execute([$id_venta]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar una venta que ya tiene pagos registrados.");
            }
            $stmt_get_details = $db->prepare("SELECT id_producto, cantidad FROM ventas_detalle WHERE id_venta = ?");
            $stmt_get_details->execute([$id_venta]);
            $detalles_venta = $stmt_get_details->fetchAll(PDO::FETCH_ASSOC);
            $stmt_return_stock = $db->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            foreach ($detalles_venta as $detalle) {
                $stmt_return_stock->execute([$detalle['cantidad'], $detalle['id_producto']]);
            }
            $stmt_delete = $db->prepare("DELETE FROM ventas WHERE id = ?");
            $stmt_delete->execute([$id_venta]);
            $_SESSION['message'] = 'La venta ha sido eliminada y el stock ha sido devuelto.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/ventas.php');
            break;
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    $redirect_url = isset($_POST['id_venta']) ? '../../public/admin/venta_detalle.php?id=' . $_POST['id_venta'] : '../../public/admin/ventas.php';
    header("Location: $redirect_url");
}
exit;