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
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);

session_start();

$db = new Conexion;
$errors = array();

if (empty($_POST['id_shipment']))
    $errors['id_shipment'] = 'Please Enter shipment';


if (empty($_POST['driver_id']))
    $errors['driver_id'] = $lang['validate_field_ajax164'];



if (empty($errors)) {

    $customer_packages = cdp_getCustomerPackagePrint(cdp_sanitize($_POST['id_shipment']))['data'];
    
    $sender_id = $customer_packages->sender_id;
    $sender_data = cdp_getSenderCourier($sender_id);
    
    $driver_data = cdp_getSenderCourier(cdp_sanitize($_POST['driver_id']));

    $estimated_eta = cdp_getPackageTracking(cdp_sanitize($_POST['id_shipment']));
    $eta = $estimated_eta->estimated_eta ? "*Estimated Time of Arrival:* " . $estimated_eta->estimated_eta . "\n\n" :  "\n";

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


        //NOTIFICATION TO ADMIN AND EMPLOYEES

        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        //NOTIFICATION TO CUSTOMER

        cdp_insertNotificationsUsers($notification_id, $_POST['id_senderclient_driver_update']);
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