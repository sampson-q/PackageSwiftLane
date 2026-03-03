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
require_permission('view_consolidate_package');

require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

$amount = floatval($_POST['amount']);
$currency_code = strtoupper($_POST['currency']);
$status = $_POST["status"];
$payment_id = $_POST['id'];

$gateway = $lang['left533020034'];
$type_transaccition_courier = $lang['left-menu-sidebar-22'];
$order_track = cdp_sanitize($_GET['track_order']);

$order_id = $_GET['order_id'];


$data = array(

    'amount' => ($amount / 100),
    'currency_code' => $currency_code,
    'status' => $status,
    'gateway' => $gateway,
    'payment_id' => $payment_id,
    'type_transaccition_courier' => "consolidated_packages",
    'date' => date("Y-m-d H:i:s"),
    'order_track' => $order_track,
    'order_track_customer_id' => $_GET['customer'],


);

$insert = cdp_insertPaymentGateway($data);


if ($insert) {

    $messages[] = $lang['notification_shipment27'];
    $status_invoice = 1;

    $db->cdp_query('UPDATE cdb_consolidate_packages SET  status_invoice =:status_invoice WHERE  consolidate_id=:order_id');


    $db->bind(':status_invoice', $status_invoice);
    $db->bind(':order_id', $order_id);


    $db->cdp_execute();


    // SAVE NOTIFICATION
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
    $db->bind(':order_id', $order_id);
    $db->bind(':notification_description', $lang['notification_shipment26']);
    $db->bind(':shipping_type', '5');
    $db->bind(':notification_date',  date("Y-m-d H:i:s"));

    $db->cdp_execute();


    $notification_id = $db->dbh->lastInsertId();


    $users_employees = cdp_getUsersAdminEmployees();

    foreach ($users_employees as $key) {

        cdp_insertNotificationsUsers($notification_id, $key->id);
    }

    cdp_insertNotificationsUsers($notification_id, $_GET['customer']);
} else {

    $errors['critical_error'] = $lang['message_ajax_error1'];
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
    <div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>

        <script>
            setTimeout('redirect()', 3000);
        </script>
    </div>

<?php
}
