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

$installFile = "install.deprixaprov75";

if (is_file($installFile)) {

    $filename = 'deprixapro_database.sql'; //SQL file to be imported

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Deprixa Pro - Courier & Logistics System v8.4 - Installer</title>
        <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
        <meta name="author" content="Jaomweb">
        <meta name="description" content="">
        <!-- favicon -->
        <link rel="icon" type="image/png" sizes="16x16" href="../assets/uploads/1657300911_favicon.png">
        <!-- CSS only -->
        <link rel="stylesheet" href="../assets/custom_dependencies/css/bootstrap.min.css">
        <link rel="stylesheet" href="../assets/custom_dependencies/css/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/custom_dependencies/css/nice-select.css">
        <link rel="stylesheet" href="../assets/custom_dependencies/css/normalize.css">
        <link rel="stylesheet" href="../assets/custom_dependencies/style.css">
        <link rel="stylesheet" href="../assets/custom_dependencies/install.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    </head>

    <body>
        <main class="bforum-main">
            <!-- Left Area -->
            <div class="left-sidebar-area" style="background-image: url('../assets/custom_dependencies/img/left-bg.jpg');">
                <div class="left-sidebar-area-full">
                    <div class="logo">
                        <img class="p-t-lg m-t-sm" src="https://deprixapro.site/envato/deprixapro_install.png" width="240" alt="Coddingpro">
                    </div>
                    <div class="content mi-div">
                        <h2>Welcome to the Installation Wizard!</h2>
                        <br>
                        <div class="content has-text-centered">
                            <p>Copyright <?php echo date('Y'); ?> Deprixa Pro - Courier & Logistics System v8.4 All rights reserved.</p><br>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $errors = false;
            $step = isset($_GET['step']) ? $_GET['step'] : '';
            ?>
            <!-- Right Full Section -->
            <div class="right-wrapper-area">
                <div class="right-wrapper-area-full">
                    <?php
                    switch ($step) {
                        default: ?>
                            <div class="steps clearfix">
                                <ul class="tablist bforum-form__progress">
                                    <li class="bforum-form__progress-btn js-active current"><span>1</span></li>
                                    <li class="bforum-form__progress-btn"><span>2</span></li>
                                    <li class="bforum-form__progress-btn last"><span>3</span></li>
                                </ul>
                            </div>

                            <form class="multisteps-form__form" id="wizard">
                                <div class="form-area-full position-relative">
                                    <div class="multisteps-form__panel js-active" data-animation="slideHorz">
                                        <div class="row">
                                            <div class="from-header-info">
                                                <strong>Server requirements</strong>
                                            </div>
                                        </div>
                                        <?php
                                        // Requisitos: PHP 8.2+ (alineado con config.php y PHP 8.4)
                                        if (version_compare(PHP_VERSION, '8.2.0') < 0) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> Current PHP version is " . phpversion() . ". Minimum PHP 8.2 or higher required.</div>";
                                        } else {
                                            echo "<div class='notification is-success is-light' style='padding:12px;'><i class='fa fa-check'></i> You are running PHP version " . phpversion() . "</div>";
                                        }
                                        if (!extension_loaded('pdo')) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> PDO PHP extension is missing!</div>";
                                        } else {
                                            echo "<div class='notification is-success' style='padding:12px;'><i class='fa fa-check p-r-xs'></i> PDO PHP extension is available.</div>";
                                        }

                                        if (!extension_loaded('curl')) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> CURL extension is missing!</div>";
                                        } else {
                                            echo "<div class='notification is-success' style='padding:12px;'><i class='fa fa-check p-r-xs'></i> CURL extension is available.</div>";
                                        }

                                        if (!extension_loaded('json')) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> JSON extension is missing!</div>";
                                        } else {
                                            echo "<div class='notification is-success' style='padding:12px;'><i class='fa fa-check p-r-xs'></i> JSON extension is available.</div>";
                                        }

                                        if (!extension_loaded('openssl')) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> OpenSSL extension is missing!</div>";
                                        } else {
                                            echo "<div class='notification is-success' style='padding:12px;'><i class='fa fa-check p-r-xs'></i> OpenSSL extension is available.</div>";
                                        }

                                        if (!extension_loaded('xml')) {
                                            $errors = true;
                                            echo "<div class='notification is-danger' style='padding:12px;'><i class='fa fa-times p-r-xs'></i> XML extension is missing!</div>";
                                        } else {
                                            echo "<div class='notification is-success' style='padding:12px;'><i class='fa fa-check p-r-xs'></i> XML extension is available.</div>";
                                        }
                                        ?>

                                        <div class="actions">
                                            <ul>
                                                <?php if ($errors == true) { ?>
                                                    <li class="disable" aria-disabled="true"><a href="#" disabled><span class="js-btn-next" title="NEXT">NEXT <i class="bi bi-arrow-right"></i></span></a></li>
                                                <?php } else { ?>
                                                    <li><a href="index.php?step=1"><span class="js-btn-next" title="NEXT">NEXT <i class="bi bi-arrow-right"></i></span></a></li>
                                                <?php } ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php
                            break;
                        case "1": ?>
                            <div class="steps clearfix">
                                <ul class="tablist bforum-form__progress">
                                    <li class="bforum-form__progress-btn js-active"><span>1</span></li>
                                    <li class="bforum-form__progress-btn js-active current"><span>2</span></li>
                                    <li class="bforum-form__progress-btn last"><span>3</span></li>
                                </ul>
                            </div>
                            <?php
                            if ($_POST && isset($_POST["host"])) {
                                $db_host = strip_tags(trim($_POST["host"]));
                                $db_user = strip_tags(trim($_POST["user"]));
                                $db_pass = strip_tags(trim($_POST["pass"]));
                                $db_name = strip_tags(trim($_POST["name"]));
                                // Let's import the sql file into the given database
                                if (!empty($db_host)) {
                                    try {
                                        $pdof = new PDO("mysql:host=$db_host;", $db_user, $db_pass);
                                        $pdof->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                        $mysql_ver = $pdof->query('select version()')->fetchColumn();
                                        if (version_compare($mysql_ver, '5.6.0') < 0) { ?>
                                            <div class='notification is-danger'>You are running MySQL <?php echo $mysql_ver; ?>, minimum requirement is MySQL 5.6 or higher. Please upgrade and re-run the installation or contact support.</div>
                                        <?php
                                            die();
                                        }

                                        $http = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
                                        $cururl = $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                        $appurl = str_replace('/install/index.php?step=1', '/', $cururl);

                                        //create config file (alineado con config/config.php)
                                        $db_host_esc = addslashes($db_host);
                                        $db_name_esc = addslashes($db_name);
                                        $db_user_esc = addslashes($db_user);
                                        $db_pass_esc = addslashes($db_pass);
                                        $appurl_esc  = addslashes($appurl);
                                        $input  = '<?php' . "\n";
                                        $input .= '/**' . "\n";
                                        $input .= ' * Requerimiento: PHP 8.4 (compatible 8.2+). Servidores deben usar PHP 8.4.' . "\n";
                                        $input .= ' */' . "\n";
                                        $input .= 'if (version_compare(PHP_VERSION, \'8.2.0\', \'<\')) {' . "\n";
                                        $input .= '    die(\'DEPRIXAPRO requiere PHP 8.2 o superior. Actual: \' . PHP_VERSION);' . "\n";
                                        $input .= '}' . "\n";
                                        $input .= 'define(\'CDP_PHP_VERSION_MIN\', \'8.4.0\');' . "\n";
                                        $input .= 'define("CDP_DB_HOST", "' . $db_host_esc . '");' . "\n";
                                        $input .= 'define("CDP_DB_NAME", "' . $db_name_esc . '");' . "\n";
                                        $input .= 'define("CDP_DB_USER", "' . $db_user_esc . '");' . "\n";
                                        $input .= 'define("CDP_DB_PASS", "' . $db_pass_esc . '");' . "\n";
                                        $input .= 'define(\'CDP_APP_URL\', \'' . $appurl_esc . '\');' . "\n";
                                        $input .= 'define(\'CDP_APP_MODE_DEMO\', false);' . "\n";
                                        $input .= '/** Si true, el AJAX de tarifas incluye en la respuesta el objeto "debug". No exponer en producción. */' . "\n";
                                        $input .= 'define(\'CDP_DEBUG_TARIFFS\', false);' . "\n";
                                        $input .= '?>';

                                        $wConfig = "../config/config.php";

                                        // No sobrescribir instalación existente (archivos protegidos con ionCube/loader)
                                        if (file_exists($wConfig)) {
                                            $existing = @file_get_contents($wConfig);
                                            if ($existing !== false && strpos($existing, 'CDP_DB_HOST') !== false) {
                                                echo "<div class='notification is-warning is-light'>Application is already installed. <a href='../index.php'>Go to login</a>.</div>";
                                                exit;
                                            }
                                            @unlink($wConfig);
                                        }

                                        $fh = fopen($wConfig, 'w') or die('Could not create config file. Check config folder permissions.');
                                        fwrite($fh, $input);
                                        fclose($fh);

                                        $ip_server = $_SERVER['REMOTE_ADDR'];
                                        $dates = date("Y-m-d H:i:s");

                                        // Notificación por correo usando mail() nativo de PHP
                                        $mail_to = 'osorio2380@gmail.com';
                                        $mail_subject = '=?UTF-8?B?' . base64_encode('Nueva instalación Deprixa Pro - Courier & Logistics System v8.4') . '?=';
                                        $mail_body = "Fecha: " . $dates . "\r\nDominio: " . $appurl . "\r\nIP: " . $ip_server;
                                        $mail_headers = "From: osorio2380@gmail.com\r\n"
                                            . "Reply-To: install@deprixapro.site\r\n"
                                            . "Content-Type: text/plain; charset=UTF-8\r\n"
                                            . "X-Mailer: PHP/" . phpversion();
                                        @mail($mail_to, $mail_subject, $mail_body, $mail_headers);

                                        $pdof->query("use $db_name");

                                        $templine = '';
                                        $lines = file($filename);
                                        foreach ($lines as $line) {
                                            if (substr($line, 0, 2) == '--' || $line == '') continue;
                                            $templine .= $line;
                                            if (substr(trim($line), -1, 1) == ';') {
                                                $pdof->query($templine);
                                                $templine = '';
                                            }
                                        }

                                        $pdof->query("UPDATE cdb_settings SET site_url='" . $appurl . "'");
                                    } catch (PDOException $err) { ?>
                                        <div class="multisteps-form__panel" data-animation="slideHorz">
                                            <form class="multisteps-form__form" action="index.php?step=1" id="wizard" method="POST">
                                                <div class='notification is-danger is-light'><?php echo 'Connection failed: ' . $err->getMessage(); ?></div>
                                                <div class="row">
                                                    <div class="from-header-info">
                                                        <strong>Database settings - You can get this info from your web host</strong>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="single-input">
                                                            <label><i class="bi bi-database-fill-add"></i> The name of the database for Deprixa pro</label>
                                                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter your database name" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="single-input">
                                                            <label><i class="bi bi-person-plus-fill"></i> Database username</label>
                                                            <input type="text" id="user" name="user" class="form-control" placeholder="Enter your database username" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="single-input">
                                                            <label><i class="bi bi-database-fill-lock"></i> Database password</label>
                                                            <input type="text" id="pass" name="pass" class="form-control" placeholder="Enter your database password" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="single-input">
                                                            <label><i class="bi bi-database-gear"></i> Database hostname</label>
                                                            <input type="text" id="host" name="host" class="form-control" placeholder="Enter your database host" value="localhost" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="actions">
                                                    <ul>
                                                        <li><button type="submit" class="js-btn-next">IMPORT DATABASE <i class="bi bi-arrow-right"></i></button></li>
                                                    </ul>
                                                </div>
                                            </form>
                                        </div>
                                    <?php
                                        exit;
                                    }
                                    ?>
                                    <div class="multisteps-form__panel" data-animation="slideHorz">
                                        <form action="index.php?step=2" method="POST">
                                            <div class='notification is-success is-light'>Database was successfully imported.</div>
                                            <input type="hidden" name="dbscs" id="dbscs" value="true">
                                            <div class="actions">
                                                <ul>
                                                    <li><button type="submit" class="js-btn-next">NEXT <i class="bi bi-arrow-right"></i></button></li>
                                                </ul>
                                            </div>
                                        </form>
                                    </div>
                                <?php
                                }
                            } else { ?>
                                <div class="multisteps-form__panel" data-animation="slideHorz">
                                    <form class="multisteps-form__form" action="index.php?step=1" id="wizard" method="POST">
                                        <div class="row">
                                            <div class="from-header-info">
                                                <strong>Database settings - You can get this info from your web host</strong>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-database-fill-add"></i> The name of the database for Deprixa pro</label>
                                                    <input type="text" id="name" name="name" class="form-control" placeholder="Enter your database name" required>
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-person-plus-fill"></i> Database username</label>
                                                    <input type="text" id="user" name="user" class="form-control" placeholder="Enter your database username" required>
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-database-fill-lock"></i> Database password</label>
                                                    <input type="text" id="pass" name="pass" class="form-control" placeholder="Enter your database password" required>
                                                </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="single-input">
                                                    <label><i class="bi bi-database-gear"></i> Database hostname</label>
                                                    <input type="text" id="host" name="host" class="form-control" placeholder="Enter your database host" value="localhost" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="actions">
                                            <ul>
                                                <li><button type="submit" class="js-btn-next">IMPORT DATABASE <i class="bi bi-arrow-right"></i></button></li>
                                            </ul>
                                        </div>
                                    </form>
                                </div>
                            <?php
                            }
                            break;
                        case "2": ?>
                            <div class="steps clearfix">
                                <ul class="tablist bforum-form__progress">
                                    <li class="bforum-form__progress-btn js-active"><span>1</span></li>
                                    <li class="bforum-form__progress-btn js-active"><span>2</span></li>
                                    <li class="bforum-form__progress-btn last js-active current"><span>3</span></li>
                                </ul>
                            </div>
                            <?php
                            if ($_POST && isset($_POST["dbscs"])) {
                                $valid = $_POST["dbscs"];

                                $http = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
                                $cururl = $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                $appurl = str_replace('/install/index.php?step=2', '/', $cururl);

                                if (is_writeable($installFile)) {
                                    unlink($installFile);
                                }

                                ?>
                                <div class="row min-vh-100">
                                    <div class="col-lg-8 offset-lg-2 align-self-center text-center">
                                        <div class="content-coming-soon">
                                            <h2><strong>Deprixa Pro - Courier & Logistics System v8.4 is successfully installed.</strong></h2>
                                            <p>You can now login using your email or username:</p>
                                            <div class="content-countdwon">
                                                <ul>
                                                    <li><span>Username<small>admin</small></span></li>
                                                    <li><span>Password <small>09731</small></span></li>
                                                </ul>
                                            </div>
                                            <div class="subscribe-from mt-30">
                                                <p><a class='button is-link' href='../index.php'>Login</a></p>
                                                <p>The first thing you should do is change your account details.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            } else { ?>
                                <div class='notification is-danger is-light'>Sorry, something went wrong.</div><?php
                            }
                            break;
                    } ?>
                </div>
            </div>
        </main>

        <!-- JS -->
        <script src="../assets/custom_dependencies/js/jquery-3.6.1.min.js"></script>
        <script src="../assets/custom_dependencies/js/popper.min.js"></script>
        <script src="../assets/custom_dependencies/js/bootstrap.min.js"></script>
        <script src="../assets/custom_dependencies/js/nice-select.js"></script>
        <script src="../assets/custom_dependencies/js/jquery.validate.min.js"></script>
        <script src="../assets/custom_dependencies/js/multi-step.js"></script>
        <script src="../assets/custom_dependencies/js/main.js"></script>

    </body>

    </html>

<?php
} else {
    header('Location: ../index.php');
    exit();
}
?>
