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
require_permission('view_client_list');

require_once("../../helpers/querys.php");

session_start();

$db = new Conexion;

$driver = intval($_GET['driver']);
$data = json_decode($_GET['checked_data']);

foreach ($data as $key) {

    $customer_packages = cdp_getPackageMultiple($key);
        
    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);

    $driver_data = cdp_getSenderCourier(cdp_sanitize($_POST['driver']));

    $order_id = $customer_packages->order_id;
    $estimated_eta = cdp_getPackageTracking($order_id);

    $eta = $estimated_eta->estimated_eta ? "*Estimated Time of Arrival:* " . $estimated_eta->estimated_eta . "\n\n" :  "\n";

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
    
    cdp_updateDriverCustomersPackageMultiple($key, $driver);

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
