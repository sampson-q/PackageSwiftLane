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
require_permission('view_client_list');

$id = $_REQUEST['id'];

$errors = array();

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

        $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'sender_address_id', $id);
        $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'sender_address_id', $id);
        $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'sender_address_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {
            $errors['constrains'] = $lang['validate_field_ajax133'];
        } else {

            $delete = cdp_deleteCustomerAddress($id);
            if ($delete) {
                $messages[] = $lang['message_ajax_success_delete'];
            } else {
                $errors['critical_error'] = $lang['message_ajax_error1'];
            }
        }
    }

    if (!empty($errors)) {

        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
    } else {

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    }
}
