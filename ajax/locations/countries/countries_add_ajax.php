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
require_once("../../../helpers/querys.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('manage_countries');

$errors = array();


    $response = array();


    if (cdp_countryExists($_POST['name'])) {

        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax40'];
    }


    if (!isset($response['status'])) {
        $data = array(
          'name' => cdp_sanitize($_POST['name']),
          'iso3' => cdp_sanitize($_POST['iso3']),
          'phone_code' => cdp_sanitize($_POST['phone_code']),
          'capital' => cdp_sanitize($_POST['capital']),
          'currency_name' => cdp_sanitize($_POST['currency_name']),
          'currency_symbol' => cdp_sanitize($_POST['currency_symbol']),
          'region' => cdp_sanitize($_POST['region']),
          'is_active' => 1
        );

        $update = cdp_insertCountry($data);

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