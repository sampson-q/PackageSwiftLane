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
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
notify_after_response(); // email + WhatsApp go out after the response (helpers/after_response.php)
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);

session_start();

$db = new Conexion;
$errors = array();

if (empty($_POST['id_shipment']))
    $errors['id_shipment'] = 'Please Enter shipment';


if (empty($_POST['driver_id']))
    $errors['driver_id'] = $lang['validate_field_ajax164'];



if (empty($errors)) {

    // id_shipment is a consolidate_id — read the CONSOLIDATION, not the package
    // that happens to share the number.
    $customer_packages = cdp_getConsolidatePackage(cdp_sanitize($_POST['id_shipment']));

    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);

    $driver_data = cdp_getSenderCourier(cdp_sanitize($_POST['driver_id']));

    $tracking_code = $customer_packages->c_prefix . $customer_packages->c_no;
    $tracking_ref  = cdp_consolidationRef($customer_packages, 'consolidate_packages'); // waybill first, then the code

    // The consolidation's ETA — every package inside it inherits this.
    $eta_value = cdp_getConsolidationEtaById(cdp_sanitize($_POST['id_shipment']), true);
    $eta = ($eta_value !== '' && $eta_value !== 'N/A') ? "*Estimated Time of Arrival:* " . $eta_value . "\n\n" : "\n";

    $data = array(
        'id_shipment' => trim($_POST['id_shipment']),
        'driver_id' => trim($_POST['driver_id']),
    );

    $insert = cdp_updateDriverConsolidatePackages($data);

    if ($insert) {

        $messages[] = $lang['message_ajax_success_updated'];


        $db->cdp_query("
                                INSERT INTO cdb_notifications 
                                (
                                    user_id,
                                    order_id,
                                    notification_description,
                                    shipping_type,
                                    notification_date

                                )
                                VALUES
                                    (
                                    :user_id,                    
                                    :order_id,
                                    :notification_description,
                                    :shipping_type,
                                    :notification_date                    
                                    )
                            ");



        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':order_id',  $_POST['id_shipment']);
        $db->bind(':notification_description',  $lang['notification_shipment15']);
        $db->bind(':shipping_type', '5');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO DRIVER

        cdp_insertNotificationsUsers($notification_id, $_POST["driver_id"]);

        // No WhatsApp to the consolidation's own sender: a driver assignment is
        // not news for the package owners, and the consolidation number is never
        // sent to customers.


        //NOTIFICATION TO ADMIN AND EMPLOYEES

        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
    } else {

        $errors['critical_error'] =  $lang['message_error'];
    }
}


if (!empty($errors)) {
?>
    <div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_error2']; ?>
        <ul class="error">
            <?php
            foreach ($errors as $error) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $error;

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

if (isset($messages)) {

?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <p><span class="icon-info-sign"></span>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

<?php
}
?>