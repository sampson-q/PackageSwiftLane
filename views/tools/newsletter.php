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



require_once('helpers/querys.php');
$userData = $user->cdp_getUserData();

if (isset($_GET['email'])) {

    $email_template = cdp_getEmailTemplatesdg1i4(12);
} else {

    $email_template = cdp_getEmailTemplatesdg1i4(4);
}

?>


<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Meta Description (for search results) -->
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Author (content owner) -->
    <meta name="author" content="CODDINGPRO">
    <!-- Keywords (related keywords) -->
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Open Graph Meta (for social media sharing, like Facebook) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['tools-config61'] ?> | <?php echo $core->site_name ?></title>
    <link href="assets/template/assets/libs/summernote/dist/summernote-bs4.css" rel="stylesheet">
    <?php include 'views/inc/head_scripts.php'; ?>

</head>

<body>

    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->


    <?php include 'views/inc/preloader.php'; ?>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->

        <!-- ============================================================== -->
        <!-- Preloader - style you can find in spinners.css -->
        <!-- ============================================================== -->

        <?php include 'views/inc/topbar.php'; ?>

        <!-- End Topbar header -->


        <!-- Left Sidebar - style you can find in sidebar.scss  -->

        <?php include 'views/inc/left_sidebar.php'; ?>


        <!-- End Left Sidebar - style you can find in sidebar.scss  -->

        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">

            <div class="container-fluid">

                <div class="row">
                    <!-- Column -->

                    <div class="col-lg-12 col-xl-12 col-md-12">

                        <div class="card">
                            <div class="card-body">

                                <!-- Button group part -->
                                <div class="bg-light">
                                    <div class="row justify-content-center">
                                        <div class="col-md-12">
                                            <div class="row">
                                                <div class="col-12">
                                                    <!-- <div id="loader" style="display:none"></div> -->
                                                    <div id="resultados_ajax"></div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Action part -->

                                <div class="d-md-flex align-items-center">
                                    <div>
                                        <h3 class="card-title"><span><?php echo $lang['send-news11'] ?></span></h3>
                                    </div>
                                </div>
                                <div><hr><br></div>


                                <form class="form-horizontal form-material" name="send_email" id="send_email" method="post">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <section>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <input class="form-control" name="title" type="text" disabled="disabled" value="<?php echo $core->site_email; ?>" placeholder="<?php echo $lang['send-news3'] ?>" readonly="readonly">
                                                    <div class="note"><?php echo $lang['send-news3'] ?></div>
                                                </div>

                                            </div>


                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <?php
                                                    if (isset($_GET['email'])) {
                                                    ?>

                                                        <input class="form-control" name="recipient" type="text" 
                                                        value="<?php if (isset($_GET['email'])) {
                                                            echo $_GET['email'];
                                                        } ?>" placeholder="<?php echo $lang['send-news4'] ?>" readonly>
                                                    <?php
                                                    } else {

                                                    ?>
                                                        <select type="text" class="form-control custom-select" name="recipient">
                                                            <option value="1"><?php echo $lang['send-news5'] ?></option>
                                                            <option value="2"><?php echo $lang['send-news6'] ?></option>
                                                        </select>

                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                                <div class="note"><?php echo $lang['send-news4'] ?></div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="form-group">

                                                    <input class="form-control" type="text" name="subject" value="<?php echo $email_template->subject; ?>" 
                                                    placeholder="<?php echo $lang['send-news7'] ?>">
                                                    <div class="note note-error"><?php echo $lang['send-news7'] ?></div>

                                                </div>
                                            </div>
                                        </div>


                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="editor">
                                                    <textarea class="summernote" name="body" style="margin-top: 30px;" placeholder="Type some text">
                                                    <?php echo $email_template->body; ?>
                                                </textarea>
                                                    <div class="label2 label-important"><?php echo $lang['tools-template6'] ?> [ ]</div>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                    <br><br>
                                    <div class="form-group">
                                        <div class="col-sm-12">
                                            <button class="btn btn-outline-danger btn-confirmation" name="dosubmit" type="submit"><?php echo $lang['send-news9'] ?> <span><i class="icon-ok"></i></span></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <!-- Column -->
                    </div>
                </div>
            </div>


        </div>
        <?php include 'views/inc/footer.php'; ?>


        </div>
        <!-- ============================================================== -->
        <!-- End Wrapper -->
        <!-- ============================================================== -->

        <script src="assets/template/assets/libs/summernote/dist/summernote-bs4.min.js"></script>

        <script>
            $(document).ready(function() {
                $('.summernote').summernote();
            });
        </script>
        <script src="dataJs/newsletter.js"></script>



</body>

</html>