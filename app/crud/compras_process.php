<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/compras.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    $db->beginTransaction(); // Iniciar transacción para asegurar consistencia

    switch ($action) {
        case 'create_compra':
            $fecha_compra = $_POST['fecha_compra'];
            $id_proveedor = !empty($_POST['id_proveedor']) ? filter_var($_POST['id_proveedor'], FILTER_SANITIZE_NUMBER_INT) : null;
            $query = "INSERT INTO compras (fecha_compra, id_proveedor, total_compra, estado) VALUES (:fecha_compra, :id_proveedor, 0, 'En tránsito')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':fecha_compra', $fecha_compra);
            $stmt->bindParam(':id_proveedor', $id_proveedor);
            $stmt->execute();
            $new_compra_id = $db->lastInsertId();
            $_SESSION['message'] = 'Registro de compra creado. Ahora añade los productos.';
            $_SESSION['message_type'] = 'info';
            header('Location: ../../public/admin/compra_detalle.php?id=' . $new_compra_id);
            break;

        case 'add_product_to_compra':
            $id_compra = filter_input(INPUT_POST, 'id_compra', FILTER_SANITIZE_NUMBER_INT);
            $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_SANITIZE_NUMBER_INT);
            $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_SANITIZE_NUMBER_INT);
            $costo_unitario_usdt = filter_input(INPUT_POST, 'costo_unitario_usdt', FILTER_VALIDATE_FLOAT);
            $query_add = "INSERT INTO compras_detalle (id_compra, id_producto, cantidad, costo_unitario_usdt) VALUES (?, ?, ?, ?)";
            $stmt_add = $db->prepare($query_add);
            $stmt_add->execute([$id_compra, $id_producto, $cantidad, $costo_unitario_usdt]);
            $subtotal = $cantidad * $costo_unitario_usdt;
            $query_update_total = "UPDATE compras SET total_compra = total_compra + ? WHERE id = ?";
            $stmt_update_total = $db->prepare($query_update_total);
            $stmt_update_total->execute([$subtotal, $id_compra]);
            $_SESSION['message'] = 'Producto añadido a la compra.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/compra_detalle.php?id=' . $id_compra);
            break;
            
        case 'receive_stock':
            $id_compra = filter_input(INPUT_POST, 'id_compra', FILTER_SANITIZE_NUMBER_INT);
            $cantidades_recibidas = $_POST['cantidades_recibidas'];
            if (empty($id_compra) || empty($cantidades_recibidas)) {
                throw new Exception("No se proporcionaron datos de recepción.");
            }
            $stmt_get_product_data = $db->prepare("SELECT id_producto, costo_unitario_usdt FROM compras_detalle WHERE id = :id_detalle");
            $stmt_get_current_product = $db->prepare("SELECT stock, costo_promedio_usdt FROM productos WHERE id = :id_producto");
            $stmt_update_product = $db->prepare("UPDATE productos SET stock = :nuevo_stock, costo_promedio_usdt = :nuevo_costo WHERE id = :id_producto");
            $stmt_update_detalle = $db->prepare("UPDATE compras_detalle SET cantidad_recibida = :cantidad_recibida WHERE id = :id_detalle");
            foreach ($cantidades_recibidas as $id_detalle => $cantidad_recibida) {
                $id_detalle_sanitized = filter_var($id_detalle, FILTER_SANITIZE_NUMBER_INT);
                $cantidad_recibida_sanitized = filter_var($cantidad_recibida, FILTER_SANITIZE_NUMBER_INT);
                $stmt_get_product_data->execute([':id_detalle' => $id_detalle_sanitized]);
                $compra_detalle_data = $stmt_get_product_data->fetch(PDO::FETCH_ASSOC);
                $id_producto = $compra_detalle_data['id_producto'];
                $costo_compra_actual = $compra_detalle_data['costo_unitario_usdt'];
                if ($cantidad_recibida_sanitized > 0) {
                    $stmt_get_current_product->execute([':id_producto' => $id_producto]);
                    $current_product = $stmt_get_current_product->fetch(PDO::FETCH_ASSOC);
                    $stock_actual = $current_product['stock'];
                    $costo_promedio_actual = $current_product['costo_promedio_usdt'];
                    $valor_inventario_actual = $stock_actual * $costo_promedio_actual;
                    $valor_inventario_nuevo = $cantidad_recibida_sanitized * $costo_compra_actual;
                    $nuevo_stock_total = $stock_actual + $cantidad_recibida_sanitized;
                    $nuevo_costo_promedio = ($nuevo_stock_total > 0) ? (($valor_inventario_actual + $valor_inventario_nuevo) / $nuevo_stock_total) : $costo_compra_actual;
                    $stmt_update_product->execute([':nuevo_stock' => $nuevo_stock_total, ':nuevo_costo' => $nuevo_costo_promedio, ':id_producto' => $id_producto]);
                }
                $stmt_update_detalle->execute([':cantidad_recibida' => $cantidad_recibida_sanitized, ':id_detalle' => $id_detalle_sanitized]);
            }
            $stmt_update_compra = $db->prepare("UPDATE compras SET estado = 'Recibido' WHERE id = :id_compra");
            $stmt_update_compra->execute([':id_compra' => $id_compra]);
            $_SESSION['message'] = '¡Mercancía recibida! El stock y el costo promedio han sido actualizados.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/compra_detalle.php?id=' . $id_compra);
            break;

        case 'add_additional_cost':
            $id_compra = filter_input(INPUT_POST, 'id_compra', FILTER_SANITIZE_NUMBER_INT);
            $descripcion_costo = filter_input(INPUT_POST, 'descripcion_costo', FILTER_SANITIZE_STRING);
            $monto_costo_usdt = filter_input(INPUT_POST, 'monto_costo_usdt', FILTER_VALIDATE_FLOAT);
            if (empty($id_compra) || empty($descripcion_costo) || $monto_costo_usdt <= 0) {
                throw new Exception("Datos de costo inválidos.");
            }
            $query = "INSERT INTO compras_costos_adicionales (id_compra, descripcion_costo, monto_costo_usdt) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$id_compra, $descripcion_costo, $monto_costo_usdt]);
            $_SESSION['message'] = 'Costo adicional registrado exitosamente.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/compra_detalle.php?id=' . $id_compra);
            break;

        case 'add_payment_to_compra':
            $id_compra = filter_input(INPUT_POST, 'id_compra', FILTER_SANITIZE_NUMBER_INT);
            $monto_pagado = filter_input(INPUT_POST, 'monto_pagado', FILTER_VALIDATE_FLOAT);
            $metodo_pago = filter_input(INPUT_POST, 'metodo_pago', FILTER_SANITIZE_STRING);
            $referencia_pago = filter_input(INPUT_POST, 'referencia_pago', FILTER_SANITIZE_STRING);
            $fecha_pago = filter_input(INPUT_POST, 'fecha_pago', FILTER_SANITIZE_STRING);
            if (empty($id_compra) || $monto_pagado <= 0) {
                throw new Exception("Datos de pago inválidos.");
            }
            $query_pago = "INSERT INTO compras_pagos (id_compra, monto_pagado, metodo_pago, referencia_pago, fecha_pago) VALUES (?, ?, ?, ?, ?)";
            $stmt_pago = $db->prepare($query_pago);
            $stmt_pago->execute([$id_compra, $monto_pagado, $metodo_pago, $referencia_pago, $fecha_pago]);
            $stmt_sum = $db->prepare("SELECT SUM(monto_pagado) FROM compras_pagos WHERE id_compra = ?");
            $stmt_sum->execute([$id_compra]);
            $total_pagado_actual = $stmt_sum->fetchColumn();
            $stmt_total = $db->prepare("SELECT total_compra FROM compras WHERE id = ?");
            $stmt_total->execute([$id_compra]);
            $total_compra = $stmt_total->fetchColumn();
            if ($total_pagado_actual >= $total_compra) {
                $stmt_update_compra = $db->prepare("UPDATE compras SET estado_pago = 'Pagado' WHERE id = ?");
                $stmt_update_compra->execute([$id_compra]);
            } else {
                $stmt_update_compra = $db->prepare("UPDATE compras SET estado_pago = 'Pendiente' WHERE id = ?");
                $stmt_update_compra->execute([$id_compra]);
            }
            $_SESSION['message'] = 'Pago registrado exitosamente.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/compra_detalle.php?id=' . $id_compra);
            break;

        case 'delete_compra':
            $id_compra = filter_input(INPUT_POST, 'id_compra', FILTER_SANITIZE_NUMBER_INT);
            if (empty($id_compra)) {
                throw new Exception("ID de compra no válido.");
            }
            $stmt_check = $db->prepare("SELECT estado, (SELECT SUM(monto_pagado) FROM compras_pagos WHERE id_compra = ?) as total_pagado FROM compras WHERE id = ?");
            $stmt_check->execute([$id_compra, $id_compra]);
            $compra_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
            if ($compra_check && ($compra_check['estado'] != 'Recibido' && ($compra_check['total_pagado'] ?? 0) == 0)) {
                $stmt_delete = $db->prepare("DELETE FROM compras WHERE id = ?");
                $stmt_delete->execute([$id_compra]);
                $_SESSION['message'] = 'La orden de compra ha sido eliminada exitosamente.';
                $_SESSION['message_type'] = 'success';
            } else {
                throw new Exception("No se puede eliminar una compra que ya ha sido recibida o que tiene pagos registrados.");
            }
            header('Location: ../../public/admin/compras.php');
            break;
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    $redirect_url = isset($_POST['id_compra']) ? '../../public/admin/compra_detalle.php?id=' . $_POST['id_compra'] : '../../public/admin/compras.php';
    header('Location: ' . $redirect_url);
}
exit;