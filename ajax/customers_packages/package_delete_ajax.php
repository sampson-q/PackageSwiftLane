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
require_permission('view_client_list');

$db = new Conexion;
$user = new User;
$userData = $user->cdp_getUserData();

$errors = array();



if (empty($_POST['id_delete']))

    $errors['id_delete'] = 'Please select shipment package';



if (empty($errors)) {

    $db->cdp_query("SELECT * FROM cdb_customers_packages where order_id=" . $_POST['id_delete'] . "");
    $trackings_order = $db->cdp_registro();


    $track_resulta = $trackings_order->order_prefix . $trackings_order->order_no;


    $data = array(

        'id' => trim($_POST['id_delete'])
    );



    $insert = cdp_deleteFullCourierpackage($data);


    if ($insert) {


        $messages[] = $lang['notification_shipment0888'] . $track_resulta;



        //INSERT HISTORY USER
        $date = date("Y-m-d H:i:s");
        $db->cdp_query("
                        INSERT INTO cdb_order_user_history 
                        (
                            user_id,
                            order_id,
                            action,
                            date_history                   
                            )
                        VALUES
                            (
                            :user_id,
                            :order_id,
                            :action,
                            :date_history
                            )
                    ");



        $db->bind(':order_id',  $_POST['id_delete']);
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':action', $lang['notification_shipment0888'] . $track_resulta . '');
        $db->bind(':date_history',  trim($date));
        $db->cdp_execute();
    } else {

        $errors['critical_error'] = $lang['message_ajax_error1'];
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