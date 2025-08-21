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
        case 'add_payment_to_venta':
            $id_venta = filter_input(INPUT_POST, 'id_venta', FILTER_SANITIZE_NUMBER_INT);
            $monto = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
            $moneda_pago = filter_input(INPUT_POST, 'moneda_pago', FILTER_SANITIZE_STRING);
            $tasa_conversion = filter_input(INPUT_POST, 'tasa_conversion', FILTER_VALIDATE_FLOAT);
            $metodo_pago = filter_input(INPUT_POST, 'metodo_pago', FILTER_SANITIZE_STRING);
            $referencia = filter_input(INPUT_POST, 'referencia', FILTER_SANITIZE_STRING);
            $fecha_pago = filter_input(INPUT_POST, 'fecha_pago', FILTER_SANITIZE_STRING);

            if (empty($id_venta) || $monto <= 0 || empty($fecha_pago)) {
                throw new Exception("Datos de pago inválidos.");
            }

            // 1. Insertar el nuevo pago en la tabla 'pagos'
            $query_pago = "INSERT INTO pagos (id_venta, monto, moneda_pago, tasa_conversion, metodo_pago, referencia, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_pago = $db->prepare($query_pago);
            $stmt_pago->execute([$id_venta, $monto, $moneda_pago, $tasa_conversion, $metodo_pago, $referencia, $fecha_pago]);

            // 2. Recalcular el total pagado (en USDT)
            $stmt_sum = $db->prepare("SELECT monto, tasa_conversion FROM pagos WHERE id_venta = ?");
            $stmt_sum->execute([$id_venta]);
            $pagos_actuales = $stmt_sum->fetchAll(PDO::FETCH_ASSOC);
            $total_pagado_actual_usdt = 0;
            foreach ($pagos_actuales as $pago) {
                $tasa = ($pago['tasa_conversion'] > 0) ? $pago['tasa_conversion'] : 1;
                $total_pagado_actual_usdt += $pago['monto'] / $tasa;
            }

            // 3. Obtener el total de la venta
            $stmt_total_venta = $db->prepare("SELECT total_venta_usdt FROM ventas WHERE id = ?");
            $stmt_total_venta->execute([$id_venta]);
            $total_venta = $stmt_total_venta->fetchColumn();

            // 4. Actualizar el estado de pago de la venta
            $nuevo_estado_pago = 'Pendiente';
            // Usamos una pequeña tolerancia para comparaciones de punto flotante
            if ($total_pagado_actual_usdt >= ($total_venta - 0.01)) {
                $nuevo_estado_pago = 'Pagado';
            } elseif ($total_pagado_actual_usdt > 0) {
                $nuevo_estado_pago = 'Abonado';
            }
            
            $stmt_update_venta = $db->prepare("UPDATE ventas SET estado_pago = ? WHERE id = ?");
            $stmt_update_venta->execute([$nuevo_estado_pago, $id_venta]);
            
            $_SESSION['message'] = 'Pago registrado exitosamente.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/venta_detalle.php?id=' . $id_venta);
            break;
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    $redirect_url = isset($_POST['id_venta']) ? '../../public/admin/venta_detalle.php?id=' . $_POST['id_venta'] : '../../public/admin/ventas.php';
    header('Location: ' . $redirect_url);
}
exit;