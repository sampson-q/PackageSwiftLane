<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');
require_once(__DIR__ . '/../../helpers/video_files.php');
cdp_purgeDeliveredVideosLater();   // videos of delivered packages are deleted after the response

require_once("../../helpers/querys.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

session_start();

$status = intval($_GET['status']);
$data = json_decode($_GET['checked_data']);

// Delivered / Picked up only with the owner's verified pickup code
// (helpers/pickup_code.php). Order numbers repeat across customers, so
// each number is resolved to the one package not yet handed over; a
// number shared by several such packages is refused, never guessed.
require_once(__DIR__ . '/../../helpers/pickup_code.php');
$cdpPickupIds = [];
if (cdp_pickupCodeGatedStatus($status)) {
    $cdpResolved = cdp_pickupCodeResolveNumbers('air', (array) $data);
    $cdpPickupMsg = '';
    if ($cdpResolved['ambiguous']) {
        $cdpPickupMsg = 'These order numbers belong to more than one package: ' . implode(', ', $cdpResolved['ambiguous'])
            . '. Hand each one over from its own page.';
    } else {
        $cdpPickupGate = cdp_pickupCodeGate('air', $cdpResolved['ids'], $status);
        $cdpPickupMsg  = $cdpPickupGate['ok'] ? '' : $cdpPickupGate['message'];
    }
    if ($cdpPickupMsg !== '') {
        echo '<div class="alert alert-danger" id="success-alert"><p>' . htmlspecialchars($cdpPickupMsg, ENT_QUOTES, 'UTF-8') . '</p></div>';
        exit;
    }
    foreach ($cdpResolved['ids'] as $cdpId) {
        $cdpRow = cdp_getCourier($cdpId);
        if ($cdpRow) {
            $cdpPickupIds[(string) $cdpRow->order_no] = (int) $cdpId;
        }
    }
}

foreach ($data as $key) {
    // Obtener información del envío
    if (cdp_pickupCodeGatedStatus($status)) {
        if (!isset($cdpPickupIds[(string) $key])) {
            continue; // already handed over
        }
        $courier = cdp_getCourier($cdpPickupIds[(string) $key]);
    } else {
        $courier = cdp_getCourierMultiple($key);
    }
    $prefix = $courier->order_prefix;
    $office = $courier->origin_off;
    $tracking = $prefix . $key;

    // Verificar si ya existe un registro para este seguimiento y estado
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {
        // Si no existe un registro duplicado, actualizar el estado del envío
        if (cdp_pickupCodeGatedStatus($status)) {
            // By order_id: a number update would also move another customer's package.
            $cdpDb = new Conexion;
            $cdpDb->cdp_query("UPDATE cdb_add_order SET status_courier = :s WHERE order_id = :id");
            $cdpDb->bind(':s', $status);
            $cdpDb->bind(':id', (int) $courier->order_id);
            $cdpDb->cdp_execute();
            cdp_pickupCodeConsume('air', [(int) $courier->order_id], (int) ($_SESSION['userid'] ?? 0));
        } else {
            cdp_updateStatusCourierMultiple($key, $status);
        }

        // Agregar comentario
        $comment = $comments = $lang['multiple_updated1'] . ' ' . $tracking;

        // Insertar en cdb_courier_track
        $user = $_SESSION['userid'];
        cdp_updateShipTrackingMultiple($tracking, $status, $comment, $office, $user);

        // Audit: one row per shipment moved.
        cdp_activityLogStatus(
            'shipments',
            'shipment',
            (int) $key,
            $tracking,
            $status,
            cdp_activityStatusName($status),
            cdp_activityStatusName((int) $courier->status_courier)
        );

        // =======================
        // WhatsApp v2 Notification (Template 11)
        // =======================
        try {
            $sender_data = cdp_getSenderCourier(intval($courier->sender_id));

            if (!empty($sender_data->phone)) {
                cdp_sendStatusUpdateWhatsApp(
                    $sender_data,
                    $tracking,
                    cdp_wa_lookupName('cdb_styles', 'mod_style', (int) $status)
                );
            }
        } catch (Exception $e) {
            error_log('Error sending WhatsApp v2 notification for bulk update: ' . $e->getMessage());
        }

        // Agregar mensaje de éxito
        $message[$key] = $key . ' ' . $lang['modal-text30'];
    } else {
        // Si ya existe un registro duplicado, simplemente agregar un mensaje de advertencia
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}

// Mostrar mensajes de éxito o advertencia
if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo  $lang['message_ajax_success_updated']; ?>
        <ul class="error">
            <?php
            foreach ($message as $msj) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $msj;
                    ?>
                </li>
            <?php
            }
            ?>
        </ul>
        </p>
    </div>
<?php
}
?>
