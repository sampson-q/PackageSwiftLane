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
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');
require_csrf();

require_once("../../../helpers/querys.php");

$errors = array();

if (empty($_POST['name_com']))
    $errors['name_com'] = $lang['validate_field_ajax98'];


if (empty($_POST['address_cou']))
    $errors['address_cou'] = $lang['validate_field_ajax100'];

if (empty($_POST['phone_cou']))
    $errors['phone_cou'] = $lang['validate_field_ajax101'];

if (empty($_POST['country_cou']))
    $errors['country_cou'] = $lang['validate_field_ajax102'];

if (empty($_POST['city_cou']))
    $errors['city_cou'] = $lang['validate_field_ajax103'];

if (empty($_POST['postal_cou']))
    $errors['postal_cou'] = $lang['validate_field_ajax104'];




    $response = array();


    if (cdp_courierExists9y45g($_POST['name_com'])) {

        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax99'];
    }


    if (!isset($response['status'])) {
        $data = array(
            'name_com' => cdp_sanitize($_POST['name_com']),
            'address_cou' => cdp_sanitize($_POST['address_cou']),
            'phone_cou' => cdp_sanitize($_POST['phone_cou']),
            'country_cou' => cdp_sanitize($_POST['country_cou']),
            'city_cou' => cdp_sanitize($_POST['city_cou']),
            'postal_cou' => cdp_sanitize($_POST['postal_cou'])
        );

        $update = cdp_insertCourierCompany($data);

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_add'];
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
?>