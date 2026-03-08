<?php

$userData = $user->cdp_getUserData();
$where = '';
$col = 'col-2';

if (!empty($_GET['filter_virtual_mail_boxes'])) {
    $id = intval($_GET['filter_virtual_mail_boxes']);
    $where = " WHERE id = {$id}";
    $col = 'col-12';
}

$virtualMailboxes = $core->cdp_getVirtualMailboxes($where);

?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['virtual_mailbox-7'] ?> | <?php echo $core->site_name ?></title>
    <style>
        .flag-circle i.fi {
            display: inline-block;
            width: 1.5em;
            height: 1.5em;
            border-radius: 50%;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }
    </style>
    <!-- This Page CSS -->
    <!-- Custom CSS -->
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

            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-5 align-self-center">
                        <h4 class="page-title"> <?php echo $lang['virtual_mailbox-7']; ?></h4>

                    </div>
                </div>
            </div>

            <div class="container-fluid">
                <div class="row">
                    
                    <div class="col-12 col-sm-12 col-md-8 col-lg-8 col-xl-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card-header-title d-flex justify-content-between">
                                            <div class="card-title mb-6">
                                                <form method="GET" class="mb-3">
                                                    <div class="form-group">
                                                        <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['filter87'] ?></label>
                                                        <div class="input-group">
                                                            <select class="custom-select col-12" id="filterMailbox" name="filter_virtual_mail_boxes" onchange="this.form.submit()">
                                                                <option value=""><?= $lang['all_countries'] ?? 'All Countries' ?></option>
                                                                <?php
                                                                    $allMailboxes = $core->cdp_getVirtualMailboxes('');
                                                                    foreach ($allMailboxes as $mb): ?>
                                                                        <option value="<?= $mb->id; ?>" <?= (isset($_GET['filter_virtual_mail_boxes']) && $_GET['filter_virtual_mail_boxes'] == $mb->id) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($mb->country) ?>
                                                                        </option><?php
                                                                    endforeach;
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <div><br></div>
                                        <div class="pb-0">
                                            <div class="row">
                                                <!-- Virtual Address -->
                                                <?php foreach ($virtualMailboxes as $virtualMailbox) {
                                                    $db->cdp_query("SELECT * FROM cdb_countries WHERE id='" . $virtualMailbox-> cdb_countries_id . "'");
                                                    $db->cdp_execute();
                                                    $flag = strtolower($db->cdp_registro()->iso2);

                                                    $pattern = '/\(?locker\s*ID\)?/i';
                                                    $updatedAddress = preg_replace($pattern, ' (' . $userData->locker . ') ', $virtualMailbox->address);
                                                    ?>
                                                    
                                                    <div class="col-4">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h4 class="col-12">
                                                                    <?= $virtualMailbox->country; ?>
                                                                    <span class="display-20 flag-circle">
                                                                        <i class="fi fi-<?php echo $flag; ?>"></i>
                                                                        <hr>
                                                                    </span>
                                                                </h4>
                                                                <ul class="list-style-none">
                                                                    <li class="mb-0">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['virtual_mailbox-1']; ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <small class="text-muted"><?php echo $updatedAddress; ?></small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-0">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['dash-general-39'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <small class="text-muted"><?php echo $userData->locker; ?></small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-0">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['left92'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <small class="text-muted"><?php echo $virtualMailbox->city; ?></small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>

                                                                    <li class="mb-0">
                                                                        <div class="row">
                                                                            <div class="col-3">
                                                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                                                    <div class="me-2">
                                                                                        <h5 class="mb-0"><?php echo $lang['left94'] ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-9">
                                                                                <div class="user-progress align-items-center gap-3">
                                                                                    <div class="align-items-center gap-1">
                                                                                        <small class="text-muted"><?php echo $virtualMailbox->postcode; ?></small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                                <!--/ Virtual Address -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>

        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->
    <?php include('helpers/languages/translate_to_js.php'); ?>

    <!-- <script src="dataJs/recipients.js"></script> -->
</body>

</html>