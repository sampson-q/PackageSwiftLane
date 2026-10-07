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
notify_after_response(); // email + WhatsApp go out after the response (helpers/after_response.php)
require_permission('view_client_list');

require_once("../../helpers/querys.php");

session_start();

$db = new Conexion;

$driver = intval($_GET['driver']);
$data = json_decode($_GET['checked_data']);

foreach ($data as $key) {

    // $key is a package's order number; numbers repeat across customers, so
    // it is resolved to one package (cdp_resolveOrderNumber) and that package
    // alone is updated and its own owner told. Ambiguous numbers are skipped.
    $cdpRes = cdp_resolveOrderNumber('cdb_customers_packages', $key);
    if (!$cdpRes['row']) {
        $message[$key] = $key . ($cdpRes['ambiguous']
            ? ': this order number belongs to more than one package. Assign its driver from its own page.'
            : ': not found.');
        continue;
    }
    $customer_packages = $cdpRes['row'];

    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);

    // The driver comes in the query string ($_GET), not $_POST.
    $driver_data = cdp_getSenderCourier($driver);

    $order_id = $customer_packages->order_id;

    // Inside a consolidation the package quotes the CONSOLIDATION's ETA.
    $eta_value = cdp_getEffectiveEta($order_id, $customer_packages->order_no, $customer_packages->order_deli_time ?? null, $customer_packages->is_consolidate ?? null, true);
    $eta = ($eta_value !== '' && $eta_value !== 'N/A') ? "*Estimated Time of Arrival:* " . $eta_value . "\n\n" : "\n";

    try {
        require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

        // Only send if sender has phone
        if ($sender_data && !empty($sender_data->phone)) {
            $whatsapp_body = "Dear {$sender_data->fname } {$sender_data->lname },\n\n
            Your shipment has been updated with a new driver assignment. Here are the details:\n
            *Tracking Number:* {$customer_packages->order_prefix}{$customer_packages->order_no}\n
            *Courier:* {$driver_data->fname}\n
            $eta
            
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

    $db->cdp_query("UPDATE cdb_customers_packages SET driver_id = :d WHERE order_id = :id");
    $db->bind(':d', $driver);
    $db->bind(':id', (int) $customer_packages->order_id);
    $db->cdp_execute();

    $message[$key] = $key . ' ' . $lang['modal-text30'];
}


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
