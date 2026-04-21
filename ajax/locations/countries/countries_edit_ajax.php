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



require_once("../../../loader.php");
require_once("../../../helpers/querys.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('manage_countries');
require_csrf();

$errors = array();

if (empty($_POST['name']))
  $errors['name'] =  $lang['validate_field_ajax39'];


if (empty($_POST['currency_name']))
  $errors['currency_name'] = $lang['validate_field_ajax41'];

if (empty($_POST['currency_symbol']))
  $errors['currency_symbol'] =  $lang['validate_field_ajax42'];


if (!isset($_POST['is_active'])) {

  $is_active = 0;
} else {

  $is_active = $_POST['is_active'];
}

if (CDP_APP_MODE_DEMO === true) {
?>

  <div class="alert alert-warning" id="success-alert">
    <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
      <span>Error! </span> There was an error processing the request
    <ul class="error">

      <li>
        <i class="icon-double-angle-right"></i>
        This is a demo version, this action is not allowed, <a class="btn waves-effect waves-light btn-xs btn-success" href="https://codecanyon.net/item/courier-deprixa-pro-integrated-web-system-v32/15216982" target="_blank">Buy DEPRIXA PRO</a> the full version and enjoy all the functions...

      </li>


    </ul>
    </p>
  </div>
  <?php
} else {


    if (cdp_countryExists($_POST['name'], $_POST['id'])) {

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
            'is_active' => $is_active,
            'id' => cdp_sanitize($_POST['id'])
        );

        $update = cdp_updateCountry($data);

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_updated'];
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
}
?>