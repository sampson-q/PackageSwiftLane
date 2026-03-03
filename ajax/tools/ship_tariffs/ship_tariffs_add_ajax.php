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
require_permission('view_shipping_tariffs');

$errors = array();

if (empty($_POST['country_destiny']))
    $errors['country_destiny'] =  $lang['validate_field_ajax1'];

if (empty($_POST['state_destinystates']))
    $errors['state_destinystates'] = $lang['validate_field_ajax3'];

if (empty($_POST['city_destinycities']))
    $errors['city_destinycities'] = $lang['validate_field_ajax2'];

if (empty($_POST['country_origin']))
    $errors['country_origin'] = $lang['validate_field_ajax4'];

if (empty($_POST['initial_range']))
    $errors['initial_range'] = $lang['validate_field_ajax5'];

if (empty($_POST['final_range']))
    $errors['final_range'] = $lang['validate_field_ajax6'];

if (empty($_POST['tariff_price']))
    $errors['tariff_price'] = $lang['validate_field_ajax7'];

if (empty($_POST['ship_mode']))
    $errors['ship_mode'] = 'Select shipping mode';

if (empty($_POST['volumetric_percentage']))
    $errors['volumetric_percentage'] = 'Enter volumetric factor';

if (empty($_POST['price_mile']))
    $errors['price_mile'] = 'Enter price per mile';

$response = array();

if (isset($_POST['initial_range'], $_POST['final_range']) && ((float)$_POST['final_range'] < (float)$_POST['initial_range'])) {
    $response['status']  = 'error';
    $response['message'] = $lang['validate_field_ajax8'];
}

if (!isset($response['status'])) {
    $data = array(
        'tariff_price'          => cdp_sanitize($_POST['tariff_price']),
        'initial_range'         => cdp_sanitize($_POST['initial_range']),
        'final_range'           => cdp_sanitize($_POST['final_range']),
        'country_origin'        => cdp_sanitize($_POST['country_origin']),
        'country_destiny'       => cdp_sanitize($_POST['country_destiny']),
        'state_destinystates'   => cdp_sanitize($_POST['state_destinystates']),
        'city_destinycities'    => cdp_sanitize($_POST['city_destinycities']),
        'ship_mode'             => cdp_sanitize($_POST['ship_mode']),
        'volumetric_percentage' => isset($_POST['volumetric_percentage']) ? cdp_sanitize($_POST['volumetric_percentage']) : 5000,
        'price_mile'            => isset($_POST['price_mile']) ? cdp_sanitize($_POST['price_mile']) : 0,
        'client_id'             => !empty($_POST['client_id']) ? intval($_POST['client_id']) : null,
    );

    // Validar solapamiento con misma ruta (origin, destiny, state, city, ship_mode, client_id)
    $overlap = cdp_verifyRangeTariffsExist(
        $data['country_origin'],
        $data['country_destiny'],
        (float)$data['initial_range'],
        (float)$data['final_range'],
        null,
        $data['state_destinystates'],
        $data['city_destinycities'],
        $data['ship_mode'],
        $data['client_id']
    );
    if ($overlap) {
        $response['status']  = 'error';
        $response['message'] = isset($lang['tariff_overlap_error']) ? $lang['tariff_overlap_error'] : 'Existe una tarifa que se cruza con este rango.';
    } else {
        $insert = cdp_insertTariffs($data);
        if ($insert) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_add'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
            $response['error_sql'] = isset($GLOBALS['cdp_error']) ? $GLOBALS['cdp_error'] : '';
        }
    }
}

header('Content-type: application/json; charset=UTF-8');
echo json_encode($response);
exit;

// OJO: bloques HTML siguientes solo se ejecutan si no se envió JSON (p. ej. validación previa).
// Si quieres mantener abajo los bloques HTML, quita dataType:'json' en el JS o añade un exit; aquí.

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
            <script>
                $("#save_data")[0].reset();
            </script>
        </p>
    </div>
<?php
}
