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

require_once 'helpers/functions_money.php'; // Include LicenseBox external/client api helper file
$api = new License2l6aspi3ekdz14bfnxwtugjycm05hqcdpcoddingproV75(); // Initialize a new LicenseBoxExternalcdpcoddingproV7API object
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Verify Purchase - Deprixa Pro - Courier & Logistics System v8.4</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
        <meta name="author" content="Jaomweb">
        <meta name="description" content="">
        <!-- favicon -->
         <link rel="icon" type="image/png" sizes="16x16" href="assets/uploads/1657300911_favicon.png">
        <!-- CSS only -->
        <link rel="stylesheet" href="assets/custom_dependencies/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/custom_dependencies/css/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/custom_dependencies/css/nice-select.css">
        <link rel="stylesheet" href="assets/custom_dependencies/css/normalize.css">
        <link rel="stylesheet" href="assets/custom_dependencies/style.css">
        <link rel="stylesheet" href="assets/custom_dependencies/install.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    </head>

    <body>
        <main class="bforum-main">

            <!-- Left Area -->
            <div class="left-sidebar-area" style="background-image: url('assets/custom_dependencies/img/left-bg.jpg');">
                <div class="left-sidebar-area-full">
                    <div class="logo">
                        
                             <img class="p-t-lg m-t-sm" src="https://deprixapro.site/envato/deprixapro_install.png" width="240" alt="Coddingpro">
                        
                    </div>
                    <div class="content mi-div">
                        <h2>Verify Purchase</h2>
                        <br>
                        <div class="content has-text-centered">
                            <p>Copyright <?php echo date('Y'); ?> Deprixa Pro - Courier & Logistics System v8.4 All rights reserved.</p><br>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Right Full Section -->
            <div class="right-wrapper-area">
                <div class="right-wrapper-area-full">

                   
                        

                                <?php
                                $license_code = null;
                                $client_name = null;
                                if (!empty($_POST['license']) && !empty($_POST['client'])) {
                                    $license_code = trim(strip_tags((string) $_POST['license']));
                                    $client_name = trim(strip_tags((string) $_POST['client']));
                                    $response = $api->activate_license($license_code, $client_name);
                                    if (empty($response) || !is_array($response)) {
                                        $msg = 'Server is currently unavailable, please try again later.';
                                        $response = array('status' => false, 'message' => $msg);
                                    } else {
                                        $msg = isset($response['message']) ? $response['message'] : 'Invalid response.';
                                    }
                                    if (empty($response['status']) || $response['status'] !== true) {
                                ?>

                                    <div class="multisteps-form__panel js-active">
                                        <form class="multisteps-form__form" action="verify_purchase.php" id="wizard" method="POST">
                                            <div class="notification is-danger is-light"><?php echo ucfirst($msg); ?></div>
                                            <div class="row min-vh-100">
                                                <div class="from-header-info">
                                                    <strong>Check ENVATO license</strong>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="single-input">
                                                        <label><i class="bi bi-braces-asterisk"></i> Purchase code</label>
                                                        <span>( <small class="has-text-weight-normal has-text-grey"> (<a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_BLANK">Where is my purchase code?</a>)</small> )</span>
                                                        <input type="text"  name="license" class="form-control"  placeholder="Enter your purchase/license code" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="single-input">
                                                        <label><i class="bi bi-person"></i>Envato username</label>
                                                        <input type="text" name="client" class="form-control"  placeholder="Enter your name/envato username" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="actions">
                                                <ul>
                                                    <li>
                                                        <button type="submit" class="js-btn-next">ACTIVATE LICENSE <i class="bi bi-arrow-right"></i></button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </form>
                                    </div>


                                    <?php
                                    } else {
                                        $domain = (string) (getenv('HTTP_HOST') ?: ($_SERVER['HTTP_HOST'] ?? '') ?: getenv('SERVER_NAME') ?: ($_SERVER['SERVER_NAME'] ?? ''));
                                        $helpers_dir = realpath(__DIR__ . '/../helpers') ?: (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'helpers');
                                        $domain_file = rtrim($helpers_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.lic_domain';
                                        if ($domain !== '' && is_writable($helpers_dir)) {
                                            @file_put_contents($domain_file, $domain, LOCK_EX);
                                        }
                                        header('Location: index.php');
                                        exit;
                                    }
                                } else { ?>


                           
                                <div class="multisteps-form__panel js-active">
                                    <form class="multisteps-form__form" action="verify_purchase.php" id="wizard" method="POST">
                                        <div class="row min-vh-30">
                                            <div class="from-header-info">
                                                <strong>Check ENVATO license</strong>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-braces-asterisk"></i> Purchase code</label>
                                                    <span>( <small class="has-text-weight-normal has-text-grey"> (<a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_BLANK">Where is my purchase code?</a>)</small> )</span>
                                                    <input type="text"  name="license" class="form-control"  placeholder="Enter your purchase/license code" required>
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-person"></i>Envato username</label>
                                                    <input type="text" name="client" class="form-control"  placeholder="Enter your name/envato username" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="actions">
                                            <ul>
                                                <li>
                                                    <button type="submit" class="js-btn-next">ACTIVATE LICENSE <i class="bi bi-arrow-right"></i></button>
                                                </li>
                                            </ul>
                                        </div>
                                    </form>
                                 </div>
                           

                        <?php } ?>
                </div>
            </div>
            
        </main>

        <!-- JS -->
        <script src="assets/custom_dependencies/js/jquery-3.6.1.min.js"></script>
        <script src="assets/custom_dependencies/js/popper.min.js"></script>
        <script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
        <script src="assets/custom_dependencies/js/nice-select.js"></script>
        <script src="assets/custom_dependencies/js/jquery.validate.min.js"></script>
        <script src="assets/custom_dependencies/js/multi-step.js"></script>
        <script src="assets/custom_dependencies/js/main.js"></script>

    </body>

    </html>