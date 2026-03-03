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
require_permission('view_shipment_list');

require_once("../../helpers/querys.php");
$user = new User;
$core = new Core;
$errors = array();


if (empty($_POST['fname_recipient']))
    $errors['fname'] = $lang['validate_field_ajax122'];
if (empty($_POST['lname_recipient']))
    $errors['lname'] = $lang['validate_field_ajax123'];
if (empty($_POST['phone_custom_recipient']))
    $errors['phone_custom'] = $lang['validate_field_ajax128'];



$response = array();

 
if (!isset($response['status'])) {

    $sender_id = (int)$_GET['sender'];
    $data = array(
        'lname' => cdp_sanitize($_POST['lname_recipient']),
        'fname' => cdp_sanitize($_POST['fname_recipient']),
        'phone' => cdp_sanitize($_POST['phone_recipient']),
        'email' => cdp_sanitize($_POST['email_recipient']),
        'sender_id' => $sender_id,
    );
    $ctx = cdp_getAgencyContext();
    if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
        $data['agency_id'] = $ctx['agency_id'];
    } else {
        $db_u = new Conexion;
        $db_u->cdp_query('SELECT agency_id FROM cdb_users WHERE id = :id LIMIT 1');
        $db_u->bind(':id', $sender_id);
        $db_u->cdp_execute();
        $ru = $db_u->cdp_registro();
        if ($ru && isset($ru->agency_id) && (int)$ru->agency_id > 0) {
            $data['agency_id'] = (int)$ru->agency_id;
        }
    }

    $recipient_id = cdp_insertRecipient($data);

    if ($recipient_id !== null) {

        $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $recipient_id . "'");
        $customer_data = $db->cdp_registro();

        $db->cdp_query("
            INSERT INTO cdb_recipients_addresses 
            (
                country,
                state,
                city,
                zip_code,
                address,
                recipient_id                                
            )
            VALUES 
            (
                :country,
                :state,
                :city, 
                :zip_code,
                :address,
                :recipient_id                            
            )
        ");

        $db->bind(':recipient_id',  $recipient_id);
        $db->bind(':country',  cdp_sanitize($_POST["country_modal_recipient"]));
        $db->bind(':state',  cdp_sanitize($_POST["state_modal_recipient"]));
        $db->bind(':city',  cdp_sanitize($_POST["city_modal_recipient"]));
        $db->bind(':zip_code',  cdp_sanitize($_POST["postal_modal_recipient"]));
        $db->bind(':address',  cdp_sanitize($_POST["address_modal_recipient"]));

        $insert = $db->cdp_execute();

        $last_address_id = $db->dbh->lastInsertId();

        $db->cdp_query("SELECT * FROM cdb_recipients_addresses where id_addresses= '" . $last_address_id . "'");
        $customer_address = $db->cdp_registro();
    }


    if ($insert) {
        $response['status'] = 'success';
        $response['message'] = $lang['message_ajax_success_add'];
        $response['customer_data'] = $customer_data;
        $response['customer_address'] = $customer_address;
    } else {
        $response['status'] = 'error';
        $response['message'] = $lang['message_ajax_error1'];
    }
}

// Devuelve la respuesta como JSON
header('Content-type: application/json; charset=UTF-8');
echo json_encode($response);

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
            $("#add_recipient_from_modal_shipments")[0].reset();
        </script>
    </div>


<?php
}
?>