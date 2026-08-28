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
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('delete_user');
require_once(__DIR__ . '/../../helpers/rbac.php');

$db = new Conexion;

$id = intval($_REQUEST['id'] ?? 0);

$errors = array();

if ($id == 1) {
    $errors['admin'] = $lang['validate_field_ajax116'];
} else {
    $db->cdp_query('SELECT userlevel FROM cdb_users WHERE id = :id LIMIT 1');
    $db->bind(':id', $id);
    $db->cdp_execute();
    $row = $db->cdp_registro();
    if ($row && cdp_roleHasFlag((int)$row->userlevel, 'is_superadmin')) {
        $errors['admin'] = isset($lang['super_admin_no_delete']) ? $lang['super_admin_no_delete'] : 'You cannot delete a Super Admin user.';
    } elseif ($id === (int)$user->uid) {
        $errors['admin'] = 'You cannot delete your own account.';
    } elseif ($row && !cdp_canManageUser($user, (int)$row->userlevel)) {
        $errors['admin'] = 'No permission to delete this user.';
    }
}

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

        header('Content-type: application/json; charset=UTF-8');
    
        $response = array();

            
            $delete = cdp_deleteUsersrhv5($id);


            if ($delete) {
                $response['status'] = 'success';
                $response['message'] = $lang['message_ajax_success_delete'];
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
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <p><span class="icon-info-sign"></span>
                <?php
                foreach ($messages as $message) {
                    echo $message;
                }
                ?>
            </p>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

<?php
    }
}
