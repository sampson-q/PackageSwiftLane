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

if (empty($_POST['name_branch']))
  $errors['name_branch'] =  $lang['validate_field_ajax92'];


if (empty($_POST['branch_address']))
  $errors['branch_address'] =  $lang['validate_field_ajax94'];

if (empty($_POST['branch_city']))
  $errors['branch_city'] = $lang['validate_field_ajax95'];

if (empty($_POST['phone_branch']))
  $errors['phone_branch'] =  $lang['validate_field_ajax96'];



    $response = array();


    if (cdp_branchofficeExistsr9ufr($_POST['name_branch'])) {

        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax93'];
    }


    if (!isset($response['status'])) {
        $data = array(
          'name_branch' => cdp_sanitize($_POST['name_branch']),
          'branch_address' => cdp_sanitize($_POST['branch_address']),
          'branch_city' => cdp_sanitize($_POST['branch_city']),
          'phone_branch' => cdp_sanitize($_POST['phone_branch'])
        );

        $update = cdp_insertBranchOffices($data);

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_add'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
        }
    }

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
  </div>

<?php
}
?>