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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);

require_once("../../helpers/querys.php");

session_start();

$driver = intval($_GET['driver']);
$data = json_decode($_GET['checked_data']);

foreach ($data as $key) {

    cdp_updateDriverConsolidatePackagesMultiple($key, $driver);

    $customer_packages = cdp_getPackageMultiple($key);
        
    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);

    $driver_data = cdp_getSenderCourier($driver);

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
