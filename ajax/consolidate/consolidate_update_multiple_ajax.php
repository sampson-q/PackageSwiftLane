<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************



require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');

require_login();
require_permission('view_consolidate_list');

session_start();

$status = intval($_GET['status']);
$data = json_decode($_GET['checked_data']);

$core = new Core();

foreach ($data as $key) {
    // Obtener información del envío
    $courier = cdp_getConsolidateMultiple($key);
    $prefix = $courier->order_prefix;
    $office = $courier->origin_off;
    $tracking = $prefix . $key;

    // Verificar si ya existe un registro para este seguimiento y estado
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {
        // Si no existe un registro duplicado, actualizar el estado del envío
        cdp_updateStatusConsolidateMultiple($key, $status);

        // Agregar comentario
        $comment = $comments = $lang['multiple_updated3'] . ' ' . $tracking;

        // Insertar en cdb_courier_track
        $user = $_SESSION['userid'];
        cdp_updateConsolidateTrackingMultiple($tracking, $status, $comment, $office, $user);

        $sender_data = cdp_getSenderCourier($courier->sender_id);
        $status_detail = $core->cdp_getStatusById($status);

        try {
            require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

            // Only send if sender has phone
            if ($sender_data && !empty($sender_data->phone)) {
                $whatsapp_body = "Dear {$sender_data->fname } {$sender_data->lname },\n\n
                Your shipment has been updated with a new status. Here are the details:\n
                *Tracking Number:* {$tracking}\n
                *Status:* {$status_detail->mod_style}\n

                Login to your account for more details.";

                // Send WhatsApp notification
                $wa_result = sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);

                // Log result (don't fail shipment if WhatsApp fails)
                if (!$wa_result['success']) {
                    error_log("WhatsApp notification failed for order {$order_id}: " . $wa_result['message']);
                }
            }
        } catch (Exception $e) {
            error_log('WhatsApp notification error for order ' . $order_id . ': ' . $e->getMessage());
        }

        // Agregar mensaje de éxito
        $message[$key] = $key . ' ' . $lang['modal-text30'];
    } else {
        // Si ya existe un registro duplicado, simplemente agregar un mensaje de advertencia
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}


if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Success! </span> Successfully updated shipments
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
