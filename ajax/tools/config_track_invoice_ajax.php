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
require_permission('view_tools');
require_csrf();

require_once("../../helpers/querys.php");

$errors = array();

if (empty($_POST['interms']))

  $errors['interms'] = $lang['validate_field_ajax22'];

if (empty($_POST['prefix']))

  $errors['prefix'] = $lang['validate_field_ajax23'];

if (empty($_POST['track_digit']))

  $errors['track_digit'] = $lang['validate_field_ajax24'];


if (intval($_POST['track_digit']) > 10 || intval($_POST['track_digit']) < 1)

  $errors['track_digit_length'] = $lang['validate_field_ajax25'];


if (empty($_POST['digit_random']))

  $errors['digit_random'] = $lang['validate_field_ajax26'];


if (intval($_POST['digit_random']) > 10 || intval($_POST['digit_random']) < 1)

  $errors['track_digit_length'] = $lang['validate_field_ajax27'];

if (empty($_POST['prefix_consolidate']))

  $errors['prefix_consolidate'] = $lang['validate_field_ajax28'];

if (empty($_POST['track_consolidate']))

  $errors['track_consolidate'] = $lang['validate_field_ajax29'];

if (intval($_POST['track_consolidate']) > 10 || intval($_POST['track_consolidate']) < 1)

  $errors['track_digit_length_consolidate'] = $lang['validate_field_ajax30'];
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

  if (empty($errors)) {

    header('Content-type: application/json; charset=UTF-8');
    
    $response = array();

    $data = array(
      'interms' => cdp_sanitize($_POST['interms']),
      'signing_customer' => cdp_sanitize($_POST['signing_customer']),
      'signing_company' => cdp_sanitize($_POST['signing_company']),
      'prefix' => cdp_sanitize($_POST['prefix']),
      'track_digit' => cdp_sanitize($_POST['track_digit']),
      'code_number' => intval($_POST['code_number']),
      'digit_random' => cdp_sanitize($_POST['digit_random']),
      'prefix_consolidate' => cdp_sanitize($_POST['prefix_consolidate']),
      'track_consolidate' => cdp_sanitize($_POST['track_consolidate'])
    );


    $insert = cdp_updateConfigTrackInvoicepn8vt($data);


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