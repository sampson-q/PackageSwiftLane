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
require_permission('view_client_list');
require_csrf();

require_once("../../helpers/querys.php");

session_start();

$status = intval($_GET['status']);
$data = json_decode($_GET['checked_data']);



foreach ($data as $key) {
    // Obtener información del envío
    $courier = cdp_getPackageMultiple($key);
    $prefix = $courier->order_prefix;
    $office = $courier->origin_off;
    $tracking = $prefix . $key;

    // Verificar si ya existe un registro para este seguimiento y estado
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {
        // Si no existe un registro duplicado, actualizar el estado del envío
        cdp_updateStatusCustomerPackageMultiple($key, $status);

        // Agregar comentario
        $comment = $comments = $lang['multiple_updated2'] . ' ' . $tracking;

        // Insertar en cdb_courier_track
        $user = $_SESSION['userid'];
        cdp_updateShipTrackingMultiple($tracking, $status, $comment, $office, $user);

        // Agregar mensaje de éxito
        $message[$key] = $key . ' ' . $lang['modal-text30'];
    } else {
        // Si ya existe un registro duplicado, simplemente agregar un mensaje de advertencia
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}


if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo  $lang['message_ajax_success_updated']; ?>
        <ul class="error">
            <?php
            foreach ($message as $msj) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $msj;

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
