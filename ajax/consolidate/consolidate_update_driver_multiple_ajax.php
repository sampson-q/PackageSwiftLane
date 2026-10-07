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
require_permission('view_consolidate_list');

require_once("../../helpers/querys.php");

session_start();

$driver = intval($_GET['driver']);
$data = json_decode($_GET['checked_data']);

foreach ($data as $key) {

    cdp_updateDriverConsolidateMultiple($key, $driver);

    // $key is a consolidation number (c_no) — read the CONSOLIDATION, not a
    // shipment that happens to share the number.
    $customer_packages = cdp_getConsolidateMultiple($key);

    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);

    $driver_data = cdp_getSenderCourier($driver);

    $order_id = $customer_packages->consolidate_id;
    $tracking_code = $customer_packages->c_prefix . $customer_packages->c_no;
    $tracking_ref  = cdp_consolidationRef($customer_packages, 'consolidate'); // waybill first, then the code

    // The consolidation's ETA — every package inside it inherits this.
    $eta_value = cdp_getConsolidationEtaById($order_id);
    $eta = ($eta_value !== '' && $eta_value !== 'N/A') ? "*Estimated Time of Arrival:* " . $eta_value . "\n\n" : "\n";

    // No WhatsApp to the consolidation's own sender: a driver assignment is
    // not news for the package owners, and the consolidation number is never
    // sent to customers.

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
