<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_cliente'])) {
    header('Location: ../../public/admin/clientes.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Recolectar datos del pago
$id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_SANITIZE_NUMBER_INT);
$monto_recibido = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
$moneda_pago = filter_input(INPUT_POST, 'moneda_pago', FILTER_SANITIZE_STRING);
$tasa_conversion = filter_input(INPUT_POST, 'tasa_conversion', FILTER_VALIDATE_FLOAT) ?: 1.0;
$metodo_pago = filter_input(INPUT_POST, 'metodo_pago', FILTER_SANITIZE_STRING);
$referencia = filter_input(INPUT_POST, 'referencia', FILTER_SANITIZE_STRING);
$fecha_pago = filter_input(INPUT_POST, 'fecha_pago', FILTER_SANITIZE_STRING);

// Convertir el abono a USDT para los cálculos
$abono_restante_usdt = $monto_recibido / $tasa_conversion;

try {
    $db->beginTransaction();

    // 1. Buscar todas las ventas de este cliente con saldo pendiente, ordenadas por la más antigua primero (FIFO)
    $query_deudas = "
        SELECT 
            v.id, v.total_venta_usdt,
            COALESCE((SELECT SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) FROM pagos WHERE id_venta = v.id), 0) AS total_pagado
        FROM ventas v
        WHERE v.id_cliente = ? AND v.estado_pago IN ('Pendiente', 'Abonado')
        ORDER BY v.fecha_venta ASC
    ";
    $stmt_deudas = $db->prepare($query_deudas);
    $stmt_deudas->execute([$id_cliente]);
    $ventas_con_deuda = $stmt_deudas->fetchAll(PDO::FETCH_ASSOC);

    if (empty($ventas_con_deuda)) {
        throw new Exception("Este cliente no tiene deudas pendientes.");
    }

    // 2. Distribuir el abono entre las deudas
    foreach ($ventas_con_deuda as $venta) {
        if ($abono_restante_usdt <= 0.01) { // Usar una pequeña tolerancia
            break; // Salir del bucle si ya distribuimos todo el pago
        }

        $saldo_pendiente_venta = $venta['total_venta_usdt'] - $venta['total_pagado'];
        if ($saldo_pendiente_venta <= 0) {
            continue; // Ir a la siguiente venta si esta ya está pagada por alguna razón
        }

        // Determinar cuánto se puede aplicar a esta venta
        $monto_a_aplicar_usdt = min($abono_restante_usdt, $saldo_pendiente_venta);

        // 3. Registrar el pago parcial en la tabla 'pagos'
        $monto_original_aplicado = $monto_a_aplicar_usdt * $tasa_conversion;
        
        $stmt_pago = $db->prepare("INSERT INTO pagos (id_venta, monto, moneda_pago, tasa_conversion, metodo_pago, referencia, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_pago->execute([$venta['id'], $monto_original_aplicado, $moneda_pago, $tasa_conversion, $metodo_pago, $referencia, $fecha_pago]);

        // 4. Actualizar el estado de la venta
        $nuevo_total_pagado = $venta['total_pagado'] + $monto_a_aplicar_usdt;
        $nuevo_estado_pago = 'Abonado';
        if ($nuevo_total_pagado >= ($venta['total_venta_usdt'] - 0.01)) { // Usar tolerancia
            $nuevo_estado_pago = 'Pagado';
        }
        $stmt_update_venta = $db->prepare("UPDATE ventas SET estado_pago = ? WHERE id = ?");
        $stmt_update_venta->execute([$nuevo_estado_pago, $venta['id']]);

        // 5. Reducir el monto del abono restante
        $abono_restante_usdt -= $monto_a_aplicar_usdt;
    }

    $db->commit();
    $_SESSION['message'] = 'Abono distribuido exitosamente entre las ventas pendientes.';
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error al procesar el abono: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/cliente_history.php?id=' . $id_cliente);
exit;