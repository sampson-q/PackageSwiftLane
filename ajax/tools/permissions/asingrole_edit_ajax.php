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

ini_set('display_errors', 0);

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

require_once("../../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

if (!$user->cdp_is_Admin()) {
    echo json_encode(['status' => 'error', 'message' => 'Without authority']);
    exit;
}

$db = new Conexion;

$id = $_POST['id'];
$module_name = $_POST['module_name'];
$description = $_POST['description'];
$module_actions = $_POST['action_name'];


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


    $response = array();

    // Si no hay errores, continuamos con la actualización
    if (!isset($response['status'])) {
            // Actualizar módulo
            $db->cdp_query('UPDATE cdb_user_module_permissions SET module_name = :module_name, description = :description WHERE id = :id');
            $db->bind(':module_name', $module_name);
            $db->bind(':description', $description);
            $db->bind(':id', $id);
            $module_update = $db->cdp_execute();

            if ($module_update) {
                // Actualizar acciones del módulo
                foreach ($module_actions as $action_id => $action_name) {
                    $action_desc = $_POST['description_module'][$action_id];

                    $db->cdp_query('UPDATE cdb_user_module_actions SET action_name = :action_name, description_module = :description_module WHERE id = :action_id');
                    $db->bind(':action_name', $action_name);
                    $db->bind(':description_module', $action_desc);
                    $db->bind(':action_id', $action_id);
                    $action_update = $db->cdp_execute();

                    if (!$action_update) {
                        echo json_encode([
                            'status' => 'error',
                            'message' => $lang['asingmodule6'] . ' ' . $action_id,
                        ]);
                        exit;
                    }
                }

                $response['status'] = 'success';
                $response['message'] = $lang['asingmodule7'];
            } else {
                $response['status'] = 'error';
                $response['message'] = $lang['asingmodule8'];
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