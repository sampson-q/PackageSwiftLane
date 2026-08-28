<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Freight Forwarding & Shipping Software Solutions          *
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
require_permission('view_tools');

require_once("../../helpers/querys.php");

$errors = array();

if (empty($_POST['logistics_default1']))

  $errors['logistics_default1'] = $lang['validate_field_ajax31'];

if (empty($_POST['packaging_default2']))

  $errors['packaging_default2'] = $lang['validate_field_ajax32'];

if (empty($_POST['courier_default3']))

  $errors['courier_default3'] = $lang['validate_field_ajax33'];

if (empty($_POST['service_default4']))

  $errors['service_default4'] = $lang['validate_field_ajax34'];

if (empty($_POST['time_default5']))

  $errors['time_default5'] = $lang['validate_field_ajax35'];

if (empty($_POST['pay_default6']))

  $errors['pay_default6'] = $lang['validate_field_ajax36'];

if (empty($_POST['payment_default7']))

  $errors['payment_default7'] = $lang['validate_field_ajax37'];

if (empty($_POST['status_default8']))

  $errors['status_default8'] = $lang['validate_field_ajax38'];


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

    $data = array(

      'logistics_default1' => cdp_sanitize($_POST['logistics_default1']),
      'packaging_default2' => cdp_sanitize($_POST['packaging_default2']),
      'courier_default3' => cdp_sanitize($_POST['courier_default3']),
      'service_default4' => cdp_sanitize($_POST['service_default4']),
      'time_default5' => cdp_sanitize($_POST['time_default5']),
      'pay_default6' => cdp_sanitize($_POST['pay_default6']),
      'payment_default7' => cdp_sanitize($_POST['payment_default7']),
      'status_default8' => cdp_sanitize($_POST['status_default8']),
    );


    $insert = cdp_updateConfigInfoShipDefault4xiw0($data);



    if ($insert) {
        $response['status'] = 'success';
        $response['message'] = $lang['messageerrorform17'];
    } else {
        $response['status'] = 'error';
        $response['message'] = $lang['message_ajax_error1'];
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
    <div class="alert alert-info" id="success-alert">
      <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
        <?php
        foreach ($messages as $message) {
          echo $message;
        }
        ?>
      </p>
    </div>


<?php
  }
}
?>