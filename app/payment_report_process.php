<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['client_id'])) {
    header('Location: ../public/catalog.php');
    exit;
}

$id_cliente = $_SESSION['client_id'];
$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? '';

try {
    $db->beginTransaction();

    switch ($action) {
        case 'report_global_payment':
            // Recolectar datos del pago global
            $monto_recibido = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
            $moneda_pago = filter_input(INPUT_POST, 'moneda_pago', FILTER_SANITIZE_STRING);
            $tasa_conversion = filter_input(INPUT_POST, 'tasa_conversion', FILTER_VALIDATE_FLOAT) ?: 1.0;
            $metodo_pago = filter_input(INPUT_POST, 'metodo_pago', FILTER_SANITIZE_STRING);
            $referencia = filter_input(INPUT_POST, 'referencia', FILTER_SANITIZE_STRING);
            $fecha_pago = filter_input(INPUT_POST, 'fecha_pago', FILTER_SANITIZE_STRING);

            if ($monto_recibido <= 0) { throw new Exception("El monto del pago debe ser mayor a cero."); }

            // Convertir el abono a USDT para los cálculos
            $abono_restante_usdt = $monto_recibido / $tasa_conversion;

            // 1. Buscar todas las ventas de este cliente con saldo pendiente, de la más antigua a la más nueva
            $query_deudas = "
                SELECT v.id, v.total_venta_usdt,
                       COALESCE((SELECT SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) FROM pagos WHERE id_venta = v.id AND estado_confirmacion = 'Confirmado'), 0) AS total_pagado
                FROM ventas v
                WHERE v.id_cliente = ? AND v.estado_pago IN ('Pendiente', 'Abonado')
                ORDER BY v.fecha_venta ASC
            ";
            $stmt_deudas = $db->prepare($query_deudas);
            $stmt_deudas->execute([$id_cliente]);
            $ventas_con_deuda = $stmt_deudas->fetchAll(PDO::FETCH_ASSOC);

            if (empty($ventas_con_deuda)) {
                throw new Exception("No tienes deudas pendientes para aplicar este pago.");
            }

            // 2. Distribuir el abono entre las deudas
            foreach ($ventas_con_deuda as $venta) {
                if ($abono_restante_usdt <= 0.01) { break; }

                $saldo_pendiente_venta = $venta['total_venta_usdt'] - $venta['total_pagado'];
                if ($saldo_pendiente_venta <= 0) { continue; }

                $monto_a_aplicar_usdt = min($abono_restante_usdt, $saldo_pendiente_venta);
                $monto_original_aplicado = $monto_a_aplicar_usdt * $tasa_conversion;
                
                // Insertamos el pago parcial en la tabla 'pagos' con estado PENDIENTE
                $stmt_pago = $db->prepare("INSERT INTO pagos (id_venta, monto, moneda_pago, tasa_conversion, metodo_pago, referencia, fecha_pago, estado_confirmacion) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
                $stmt_pago->execute([$venta['id'], $monto_original_aplicado, $moneda_pago, $tasa_conversion, $metodo_pago, $referencia, $fecha_pago]);

                $abono_restante_usdt -= $monto_a_aplicar_usdt;
            }

            $_SESSION['message'] = 'Tu reporte de pago ha sido enviado y distribuido entre tus deudas pendientes. Será verificado a la brevedad.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../public/mi_cuenta.php');
            break;
        
        default:
            throw new Exception("Acción no reconocida.");
    }
    
    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: ../public/mi_cuenta.php');
}
exit;