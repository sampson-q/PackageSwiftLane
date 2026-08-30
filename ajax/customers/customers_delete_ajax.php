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
require_once(__DIR__ . '/../../helpers/rbac.php');
require_login();
require_permission('delete_client');

$db = new Conexion;

$id = $_REQUEST['id'];

// Only ever delete CLIENT accounts here — never staff.
$tdb = new Conexion;
$tdb->cdp_query("SELECT userlevel FROM cdb_users WHERE id = :id LIMIT 1");
$tdb->bind(':id', (int) $id);
$tdb->cdp_execute();
$trow = $tdb->cdp_registro();
if (!$trow || !cdp_roleIsClient((int) $trow->userlevel)) {
    echo json_encode(['status' => 'error', 'message' => 'This action can only delete client accounts.']);
    exit;
}

$errors = array();

if ($id == 1) {

    $errors['admin'] =  $lang['validate_field_ajax116'];
}
if (CDP_APP_MODE_DEMO === true) {
?>

    <div class="alert alert-warning" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Error! </span> There was an error processing the request
        <ul class="error">

            <li>
                <i class="icon-double-angle-right"></i>
                This is a demo version, this action is not allowed. Contact iSolveAfrica Ltd. to enable the full version of Swiftlane.

            </li>


        </ul>
        </p>
    </div>
    <?php
} else {



    if (empty($errors)) {

        header('Content-type: application/json; charset=UTF-8');
    
        $response = array();

        $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'sender_id', $id);
        $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'sender_id', $id);
        $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'sender_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {

            $response['status'] = 'error1'; // Cambio aquí para manejar 'error1'
            $response['message'] = $lang['validate_field_ajax132'];
        } else {
            
            // Audit: read the account before it goes, so the trail keeps a name.
            $cdp_gone_user = cdp_getUserEdit4bozo($id);
            $cdp_gone_name = isset($cdp_gone_user['data'])
                ? trim($cdp_gone_user['data']->fname . ' ' . $cdp_gone_user['data']->lname)
                : ('#' . (int) $id);
            cdp_activityLog([
                'module'       => 'customers',
                'verb'         => 'delete',
                'entity_type'  => 'user',
                'entity_id'    => (int) $id,
                'entity_label' => $cdp_gone_name,
                'summary'      => 'Deleted customer ' . $cdp_gone_name,
            ]);

            $delete = cdp_deleteUsersrhv5($id);


            if ($delete) {
                $response['status'] = 'success';
                $response['message'] = $lang['message_ajax_success_delete'];
            } else {
                $response['status'] = 'error';
                $response['message'] = $lang['message_ajax_error1'];
            }
        }

        echo json_encode($response);

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
}
