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



require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

require_once("../../../helpers/querys.php");

$errors = array();

if (empty($_POST['inco_name']))

    $errors['inco_name'] = 'Please Enter New Incoterms';

if (cdp_incoExists($_POST['inco_name'], $_POST['id']))

    $errors['inco_name'] = 'The new Incoterms is already in use.. <b>"' . $_POST['inco_name'] . '"</b>';

if (empty($_POST['detail']))
    $errors['detail'] = 'Please Enter New Observations';
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

        $data = array(
            'inco_name' => trim($_POST['inco_name']),
            'id' => trim($_POST['id']),
            'detail' => trim($_POST['detail'])
        );

        $insert = cdp_updateIncoterms($data);

        if ($insert) {

            $messages[] = "New Incoterms updated successfully!";
        } else {

            $errors['critical_error'] = "the insert was not completed";
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
}
?>