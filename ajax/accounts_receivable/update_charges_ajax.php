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
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_receivable_accounts');
require_csrf();

session_start();


$errors = array();

if (empty($_POST['total_pay']))
  $errors['total_pay'] = $lang['validate_field_ajax165'];

if (empty($_POST['mode_pay']))
  $errors['mode_pay'] = $lang['validate_field_ajax166'];

if (empty($_POST['amount']))
  $errors['amount'] = $lang['validate_field_ajax167'];

if (empty($_POST['balance']))
  $errors['balance'] = $lang['validate_field_ajax168'];

if ($_POST['total_pay'] > $_POST['balance'])
  $errors['eees'] = $lang['validate_field_ajax169'];




if (empty($errors)) {

  $data = array(
    'total' => cdp_sanitize($_POST['total_pay']),
    'payment_type' => cdp_sanitize($_POST['mode_pay']),
    'notes' => cdp_sanitize($_POST['notes']),
    'charge_id' => cdp_sanitize($_POST['charge_id']),
  );


  $insert = cdp_updateCharges($data);
  if ($insert) {
    $messages[] = $lang['message_ajax_success_updated'];
  } else {
    $errors['critical_error'] = $lang['message_ajax_error1'];
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
?>