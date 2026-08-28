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
require_permission('view_tools');

require_once("../../helpers/querys.php");

$errors = array();

if (empty($_POST['namesms']))

  $errors['namesms'] = 'Please Enter SMS Company';

if (empty($_POST['api_key']))

  $errors['api_key'] = 'Please Enter Api Key';

if (empty($_POST['api_secret']))

  $errors['api_secret'] = 'Please Enter Api Secret';

if (empty($_POST['nexmo_number']))

  $errors['nexmo_number'] = 'Please Enter Nexmo Number';


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
      'namesms' => cdp_sanitize($_POST['namesms']),
      'api_key' => cdp_sanitize($_POST['api_key']),
      'api_secret' => cdp_sanitize($_POST['api_secret']),
      'nexmo_number' => cdp_sanitize($_POST['nexmo_number']),
      'active_nex' => intval($_POST['active_nex']),
      'id' => intval($_POST['id'])
    );

    $insert = cdp_updateConfigSmsNexmo($data);

    if ($insert) {

      $messages[] = "SMS Nexmo updated successfully!";
    } else {

      $errors['critical_error'] = "the update was not completed";
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