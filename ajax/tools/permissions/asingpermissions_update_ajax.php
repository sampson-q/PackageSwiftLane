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
require_csrf();

require_once("../../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

if (!$user->cdp_is_Admin()) {
    echo json_encode(['status' => 'error', 'message' => 'Without authority']);
    exit;
}

$db = new Conexion;

$response = [];

// Validar datos de entrada
$roleId = intval($_POST['role_id']);
$roleName = $_POST['role_name'] ?? null;
$description = $_POST['description'] ?? null;
$permissions = $_POST['permissions'] ?? [];




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

    try {
        $db = new Conexion;

        // Verificar si el rol ya existe con el mismo nombre (excepto el actual)
        $db->cdp_query("SELECT role_id FROM cdb_user_roles WHERE role_name = :role_name AND role_id != :role_id");
        $db->bind(':role_name', $roleName);
        $db->bind(':role_id', $roleId);
        $db->cdp_execute();

        if ($db->cdp_rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => $lang['rolesp18']]); // El rol ya existe
            exit;
        }

        // Actualizar datos del rol
        $db->cdp_query("UPDATE cdb_user_roles SET role_name = :role_name, description = :description WHERE role_id = :role_id");
        $db->bind(':role_name', $roleName);
        $db->bind(':description', $description);
        $db->bind(':role_id', $roleId);

        if (!$db->cdp_execute()) {
            throw new Exception($lang['message_ajax_error1']); // Error al actualizar el rol
        }

        // Eliminar permisos existentes
       $db->cdp_query("DELETE FROM cdb_user_role_permissions WHERE role_id = :role_id");
          $db->bind(':role_id', $roleId);
          if (!$db->cdp_execute()) {
              echo json_encode(['status' => 'error', 'message' => 'Error al eliminar permisos existentes.']);
              exit;
          }

        // Insertar los nuevos permisos
        foreach ($permissions as $permission) {
            list($actionId, $moduleId) = explode(':', $permission);
            $db->cdp_query('INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at) 
              VALUES (:role_id, :module_action_id, 1, NOW())');
            $db->bind(':role_id', $roleId);
            $db->bind(':module_action_id', $actionId);
            if (!$db->cdp_execute()) {
                echo json_encode(['status' => 'error', 'message' => "Error al insertar permiso $actionId:$moduleId"]);
                exit;
            }
        }


        // Respuesta exitosa
        echo json_encode(['status' => 'success', 'message' => $lang['message_ajax_success_add']]);
        exit;

    } catch (Exception $e) {
        // Capturar errores y responder
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
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