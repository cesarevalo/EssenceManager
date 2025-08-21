<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];
$id_pago = filter_input(INPUT_POST, 'id_pago', FILTER_SANITIZE_NUMBER_INT);

if (empty($id_pago)) {
    header('Location: ../../public/admin/pagos_revision.php');
    exit;
}

try {
    $db->beginTransaction();

    switch ($action) {
        case 'confirm_payment':
            // 1. Marcar el pago como 'Confirmado'
            $stmt_confirm = $db->prepare("UPDATE pagos SET estado_confirmacion = 'Confirmado' WHERE id = ?");
            $stmt_confirm->execute([$id_pago]);

            // 2. Obtener el id_venta de este pago
            $stmt_get_venta = $db->prepare("SELECT id_venta FROM pagos WHERE id = ?");
            $stmt_get_venta->execute([$id_pago]);
            $id_venta = $stmt_get_venta->fetchColumn();

            // 3. Recalcular el total pagado (SOLO CONFIRMADOS) para la venta
            $stmt_sum = $db->prepare("SELECT SUM(monto / IF(tasa_conversion > 0, tasa_conversion, 1)) FROM pagos WHERE id_venta = ? AND estado_confirmacion = 'Confirmado'");
            $stmt_sum->execute([$id_venta]);
            $total_pagado_actual_usdt = $stmt_sum->fetchColumn();

            // 4. Obtener el total de la venta
            $stmt_total_venta = $db->prepare("SELECT total_venta_usdt FROM ventas WHERE id = ?");
            $stmt_total_venta->execute([$id_venta]);
            $total_venta = $stmt_total_venta->fetchColumn();

            // 5. Actualizar el estado de pago de la venta
            $nuevo_estado_pago = 'Abonado';
            if ($total_pagado_actual_usdt >= ($total_venta - 0.01)) {
                $nuevo_estado_pago = 'Pagado';
            }
            
            $stmt_update_venta = $db->prepare("UPDATE ventas SET estado_pago = ? WHERE id = ?");
            $stmt_update_venta->execute([$nuevo_estado_pago, $id_venta]);

            $_SESSION['message'] = 'Pago confirmado y saldo de la venta actualizado.';
            $_SESSION['message_type'] = 'success';
            break;
        
        case 'reject_payment':
            $stmt_reject = $db->prepare("UPDATE pagos SET estado_confirmacion = 'Rechazado' WHERE id = ?");
            $stmt_reject->execute([$id_pago]);
            $_SESSION['message'] = 'Pago rechazado.';
            $_SESSION['message_type'] = 'warning';
            break;
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/pagos_revision.php');
exit;