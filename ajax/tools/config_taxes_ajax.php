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

if (empty($_POST['tax']))

  $errors['tax'] = $lang['validate_field_ajax18'];

if (empty($_POST['insurance']))

  $errors['insurance'] = $lang['validate_field_ajax19'];

if (empty($_POST['value_weight']))

  $errors['value_weight'] = $lang['validate_field_ajax20'];

if (empty($_POST['meter']))

  $errors['meter'] = $lang['validate_field_ajax21'];

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

      'tax' => cdp_sanitize($_POST['tax']),
      'min_cost_tax' => cdp_sanitize($_POST['min_cost_tax']),
      'min_cost_declared_tax' => cdp_sanitize($_POST['min_cost_declared_tax']),
      'declared_tax' => cdp_sanitize($_POST['declared_tax']),
      'insurance' => cdp_sanitize($_POST['insurance']),
      'value_weight' => cdp_sanitize($_POST['value_weight']),
      'weight_p' => cdp_sanitize($_POST['weight_p']),
      'meter' => cdp_sanitize($_POST['meter']),
      'units' => cdp_sanitize($_POST['units']),
      'c_tariffs' => cdp_sanitize($_POST['c_tariffs']),
    );


    $insert = cdp_updateConfigTaxesx4spw($data);


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