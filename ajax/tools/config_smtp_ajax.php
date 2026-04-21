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
require_permission('view_tools');
require_csrf();

$errors = array();

if (empty($_POST['smtp_names']))

  $errors['smtp_names'] = $lang['validate_field_ajax63'];

if (empty($_POST['email_address']))

  $errors['email_address'] = $lang['validate_field_ajax64'];

if (empty($_POST['smtp_host']))

  $errors['smtp_host'] = $lang['validate_field_ajax65'];

if (empty($_POST['smtp_user']))

  $errors['smtp_user'] = $lang['validate_field_ajax66'];

if (empty($_POST['smtp_password']))

  $errors['smtp_password'] = $lang['validate_field_ajax67'];

if (empty($_POST['smtp_port']))

  $errors['smtp_port'] = $lang['validate_field_ajax68'];

if (empty($_POST['smtp_secure']))

  $errors['smtp_secure'] = $lang['validate_field_ajax69'];

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

      'mailer' => cdp_sanitize($_POST['mailer']),
      'smtp_names' => cdp_sanitize($_POST['smtp_names']),
      'email_address' => cdp_sanitize($_POST['email_address']),
      'smtp_host' => cdp_sanitize($_POST['smtp_host']),
      'smtp_user' => cdp_sanitize($_POST['smtp_user']),
      'smtp_password' => cdp_sanitize($_POST['smtp_password']),
      'smtp_port' => cdp_sanitize($_POST['smtp_port']),
      'smtp_secure' => cdp_sanitize($_POST['smtp_secure']),
    );


    $insert = cdp_updateConfigSmtpemailr2g61($data);

    if ($insert) {
          $response['status'] = 'success';
          $response['message'] = $lang['message_ajax_success_updated'];
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