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

/**
 * Customer "My Profile".
 *
 * What the customer can maintain here:
 *   - profile photo (avatar)                       -> customers_avatar_edit_ajax.php
 *   - ID document: type, number, photo (OPTIONAL) -> save_profile_document_ajax.php
 *   - WhatsApp number: confirm the number on file, or enter a new one; a
 *     one-time code is sent over WhatsApp unless OTP is switched off
 *     system-wide                                  -> send/verify_profile_phone_otp_ajax.php
 *   - name, email, gender, password (optional), addresses (at least one,
 *     every field required), notes                -> customers_profile_edit_ajax.php
 */

$userData = $user->cdp_getUserData();

$targetId = isset($_GET['user']) ? (int) $_GET['user'] : 0;

if ($targetId !== (int) $userData->id && !$user->cdp_is_Admin()) {
    cdp_redirect_to("login.php");
}

require_once('helpers/querys.php');
require_once('helpers/profile.php');

$data = $targetId > 0 ? cdp_getUserEdit4bozo($targetId) : ['rowCount' => 0];

if ($data['rowCount'] != 1) {
    cdp_redirect_to($user->cdp_is_Admin() ? "customers_list.php" : "index.php");
}

$row   = $data['data'];
$isOwn = ((int) $row->id === (int) $userData->id);

$db->cdp_query("SELECT * FROM cdb_senders_addresses WHERE user_id = :uid ORDER BY id_addresses ASC");
$db->bind(':uid', (int) $row->id);
$user_addreses = $db->cdp_registros();

// WhatsApp number status: "verified" only when the number on file is the one
// the customer actually confirmed (cdb_profile_phone_verified); "pending" when
// the onboarding checklist still has the step open; otherwise no badge.
if (cdp_profilePhoneVerified((int) $row->id, (string) $row->phone)) {
    $phoneStatus = 'verified';
} else {
    $db->cdp_query("SELECT update_phone FROM cdb_user_details_update_check WHERE user_id = :uid LIMIT 1");
    $db->bind(':uid', (int) $row->id);
    $chk = $db->cdp_registro();
    $phoneStatus = ($chk && (int) $chk->update_phone === 0) ? 'pending' : 'unknown';
}

$avatarUrl   = cdp_avatarUrl($row->avatar);
$documentUrl = cdp_avatarUrl($row->document_photo, 'uploads/blankID.jpg');
$hasDocPhoto = (strpos($documentUrl, 'blankID') === false);

$documentTypes = [
    'PSP' => 'Passport',
    'ECW' => 'Ghana Card',
    'DNI' => 'National Identity Document',
];

$pageTitle = $isOwn ? ($lang['miprofile'] ?? 'My Profile') : ($lang['filter4'] ?? 'Edit Client');
$h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo $h($core->meta_description); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo $h($core->meta_keywords); ?>">
    <meta property="og:title" content="<?php echo $h($core->og_title); ?>">
    <meta property="og:description" content="<?php echo $h($core->og_description); ?>">
    <meta property="og:type" content="<?php echo $h($core->og_type); ?>">
    <meta property="og:url" content="<?php echo $h($core->og_url); ?>">
    <meta property="og:image" content="<?php echo $h($core->og_image); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $h($pageTitle); ?> | <?php echo $h($core->site_name); ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">

    <?php include 'views/inc/head_scripts.php'; ?>
    <link href="assets/template/dist/css/custom_swicth.css" rel="stylesheet">

    <style>
        .select2-selection__rendered { line-height: 31px !important; }
        .select2-container .select2-selection--single { height: 35px !important; }
        .select2-selection__arrow { height: 34px !important; }
        .profile-photo { width: 150px; height: 150px; object-fit: cover; cursor: pointer; transition: opacity .2s; }
        .profile-photo:hover { opacity: .75; }
        .profile-doc { max-width: 100%; height: 140px; object-fit: cover; border-radius: 12px; cursor: pointer; border: 1px solid #e5e5e5; transition: opacity .2s; }
        .profile-doc:hover { opacity: .75; }
        .required-mark { color: #dc3545; }
        .field-error-message { display: block; font-size: .875rem; margin-top: 5px; }
        .form-group.has-error .form-control, .form-group.has-error .select2-selection { border-color: #dc3545 !important; }
        .profile-phone-status { font-size: .75rem; }
        .address-block { border: 1px dashed #dcdcdc; border-radius: 10px; padding: 14px 14px 0; margin-bottom: 14px; }
    </style>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="row">

                    <!-- ============================ Left column ============================ -->
                    <div class="col-lg-4 col-xlg-3 col-md-5">

                        <!-- Photo + account summary -->
                        <div class="card">
                            <div class="card-body">
                                <div class="d-md-flex align-items-center">
                                    <div><h3 class="card-title"><span><?php echo $h($pageTitle); ?></span></h3></div>
                                </div>
                                <div><hr></div>

                                <center class="m-t-10">
                                    <form class="form-horizontal form-material" id="edit_avatar_form" name="edit_avatar_form" method="post" enctype="multipart/form-data">
                                        <label for="avatarInput" title="Click to choose a new photo" class="mb-2">
                                            <img id="avatarPreview" src="<?php echo $h($avatarUrl); ?>" class="rounded-circle profile-photo" alt="Profile photo" />
                                        </label>
                                        <div class="form-group" style="display:none;">
                                            <input class="form-control" id="avatarInput" name="avatar" type="file" accept="image/*" />
                                        </div>
                                        <div class="mb-2">
                                            <button type="submit" class="btn btn-outline-warning btn-confirmation" id="avatarSubmitBtn" disabled title="First choose an image">
                                                <?php echo $lang['messageerrorform13'] ?>
                                            </button>
                                        </div>
                                        <small class="text-muted d-block">Click the photo to choose a new one (JPG, PNG, GIF or WEBP, up to 5MB).</small>
                                        <input name="id" type="hidden" value="<?php echo (int) $row->id; ?>" />
                                    </form>

                                    <h4 class="card-title m-t-20"><?php echo $h($row->fname . ' ' . $row->lname); ?></h4>
                                    <h6 class="card-subtitle"><span><?php echo $lang['user_manage2'] ?> <i class="icon-double-angle-right"></i></span>
                                        <div class="badge badge-pill badge-light font-16"><span class="ti-user text-warning"></span> <?php echo $h($row->username); ?></div>
                                    </h6>
                                    <h6 class="card-subtitle"><span><?php echo $lang['user-account21000'] ?> <i class="icon-double-angle-right"></i></span>
                                        <div class="badge badge-pill badge-light font-16"><?php echo $h($row->locker); ?></div>
                                    </h6>
                                </center>
                            </div>
                            <div><hr></div>
                            <div class="card-body">
                                <small class="text-muted"><?php echo $lang['user-account4'] ?></small>
                                <h6 id="profile_email_display"><?php echo $h($row->email); ?></h6>

                                <small class="text-muted p-t-30 db">WhatsApp <?php echo $lang['user-account8'] ?></small>
                                <h6 class="mb-1">
                                    <span id="profile_phone_display"><?php echo $row->phone !== '' ? $h($row->phone) : '<span class="text-muted">Not set</span>'; ?></span>
                                    <?php if ($phoneStatus === 'verified') { ?>
                                        <span class="badge badge-success profile-phone-status ml-1">Confirmed</span>
                                    <?php } elseif ($phoneStatus === 'pending') { ?>
                                        <span class="badge badge-warning profile-phone-status ml-1">Not Confirmed</span>
                                    <?php } ?>
                                </h6>
                                <?php if ($isOwn) { ?>
                                    <button type="button" class="btn btn-sm btn-outline-success mt-1" id="btn_change_whatsapp">
                                        <i class="fab fa-whatsapp"></i>
                                        <?php echo $row->phone !== '' ? 'Confirm Or Change WhatsApp Number' : 'Add WhatsApp Number'; ?>
                                    </button>
                                    <small class="text-muted d-block mt-2">We will first ask whether the number on file is the one you use on WhatsApp, then send a code to confirm it.</small>
                                <?php } ?>
                            </div>
                            <div class="card-body row text-center">
                                <div class="col-6 border-right">
                                    <h6><?php echo $h($row->created); ?></h6>
                                    <span><?php echo $lang['user-account18'] ?></span>
                                </div>
                                <div class="col-6">
                                    <h6><?php echo $h($row->lastlogin); ?></h6>
                                    <span><?php echo $lang['user-account19'] ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- ID document (optional) -->
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-1"><?php echo $lang['leftorder164'] ?> <small class="text-muted">(Optional)</small></h4>
                                <p class="text-muted small mb-3">Your ID card or passport is <strong>not compulsory</strong>. You can add or update it here whenever you like.</p>

                                <form class="form-horizontal form-material" id="edit_document_form" name="edit_document_form" method="post" enctype="multipart/form-data">
                                    <center>
                                        <label for="documentInput" title="Click to choose a photo of your document">
                                            <img id="documentPreview" src="<?php echo $h($documentUrl); ?>" class="profile-doc" alt="Document photo" />
                                        </label>
                                        <div class="form-group" style="display:none;">
                                            <input class="form-control" id="documentInput" name="document_photo" type="file" accept="image/*" />
                                        </div>
                                        <small class="text-muted d-block mb-3">Click the image to upload a photo of your document (optional).</small>
                                    </center>

                                    <div class="form-group">
                                        <label for="document_type"><?php echo $lang['leftorder164'] ?></label>
                                        <select class="custom-select form-control" id="document_type" name="document_type">
                                            <option value="">-- None --</option>
                                            <?php foreach ($documentTypes as $code => $label) { ?>
                                                <option value="<?php echo $code; ?>" <?php echo ($row->document_type === $code) ? 'selected' : ''; ?>><?php echo $h($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="document_number"><?php echo $lang['leftorder175'] ?></label>
                                        <input type="text" class="form-control" id="document_number" name="document_number" value="<?php echo $h($row->document_number); ?>" placeholder="<?php echo $lang['leftorder175'] ?>">
                                    </div>

                                    <div class="d-flex align-items-center flex-wrap">
                                        <button type="submit" class="btn btn-outline-warning btn-confirmation mr-2" id="documentSubmitBtn" disabled title="Change something first">
                                            Save Document
                                        </button>
                                        <a href="<?php echo $h($documentUrl); ?>" target="_blank" id="documentViewBtn" class="btn btn-outline-secondary btn-confirmation <?php echo $hasDocPhoto ? '' : 'd-none'; ?>">
                                            <?php echo $lang['documentViewBtn'] ?? 'View Document'; ?>
                                        </a>
                                    </div>
                                    <input name="id" type="hidden" value="<?php echo (int) $row->id; ?>" />
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ============================ Right column ============================ -->
                    <div class="col-lg-8 col-xlg-9 col-md-7">
                        <div class="card">
                            <ul class="nav nav-pills custom-pills" id="pills-tab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="pills-setting-tab" data-toggle="pill" href="#profile-details" role="tab" aria-selected="true">
                                        <span><?php echo $lang['edit-clien2'] ?> <i class="icon-double-angle-right"></i> <?php echo $h($row->username); ?></span>
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="profile-details" role="tabpanel">
                                    <div class="card-body">
                                        <p class="text-muted small">Fields marked <span class="required-mark">*</span> are required. Your ID document (left) is optional.</p>

                                        <form class="form-horizontal form-material" id="edit_user" name="edit_user" method="post" autocomplete="off">
                                            <input type="hidden" name="_csrf_token" value="<?php echo $h(cdp_csrf_token()); ?>">
                                            <section>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><?php echo $lang['user_manage3'] ?></label>
                                                            <input type="text" class="form-control" disabled readonly value="<?php echo $h($row->username); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="password"><?php echo $lang['user_manage32'] ?> <small class="text-muted">(optional)</small></label>
                                                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" placeholder="<?php echo $lang['user_manage4'] ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="fname"><?php echo $lang['user_manage6'] ?> <span class="required-mark">*</span></label>
                                                            <input type="text" class="form-control" name="fname" id="fname" value="<?php echo $h($row->fname); ?>" placeholder="<?php echo $lang['user_manage6'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="lname"><?php echo $lang['user_manage7'] ?> <span class="required-mark">*</span></label>
                                                            <input type="text" class="form-control" name="lname" id="lname" value="<?php echo $h($row->lname); ?>" placeholder="<?php echo $lang['user_manage7'] ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="email"><?php echo $lang['user_manage5'] ?> <span class="required-mark">*</span></label>
                                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $h($row->email); ?>" placeholder="<?php echo $lang['user_manage5'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="gender"><?php echo $lang['user_manage11'] ?> <span class="required-mark">*</span></label>
                                                            <select class="custom-select form-control" id="gender" name="gender">
                                                                <option value="">-- Select --</option>
                                                                <option value="Male" <?php echo ($row->gender == 'Male') ? 'selected' : ''; ?>><?php echo $lang['leftorder179'] ?></option>
                                                                <option value="Female" <?php echo ($row->gender == 'Female') ? 'selected' : ''; ?>><?php echo $lang['leftorder178'] ?></option>
                                                                <option value="Other" <?php echo ($row->gender == 'Other') ? 'selected' : ''; ?>><?php echo $lang['leftorder180'] ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>WhatsApp <?php echo $lang['user_manage9'] ?></label>
                                                            <input type="text" class="form-control" disabled readonly value="<?php echo $h($row->phone); ?>">
                                                            <small class="text-muted">To change it, use the <strong>WhatsApp Number</strong> button on the left. Changes are confirmed by a code sent to the number.</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <hr>
                                                <h4>Addresses <span class="required-mark">*</span></h4>
                                                <p class="text-muted small">At least one address is required, and every field of each address must be filled.</p>

                                                <div id="resultados_ajax"></div>

                                                <?php
                                                $count = 0;
                                                foreach ($user_addreses as $rowAddress) {
                                                    $count++;

                                                    $db->cdp_query("SELECT id, name FROM cdb_countries WHERE id = :id");
                                                    $db->bind(':id', (int) $rowAddress->country);
                                                    $country = $db->cdp_registro();

                                                    $db->cdp_query("SELECT id, name FROM cdb_states WHERE id = :id");
                                                    $db->bind(':id', (int) $rowAddress->state);
                                                    $state = $db->cdp_registro();

                                                    $db->cdp_query("SELECT id, name FROM cdb_cities WHERE id = :id");
                                                    $db->bind(':id', (int) $rowAddress->city);
                                                    $city = $db->cdp_registro();
                                                ?>
                                                    <div id="div_parent_<?php echo $count; ?>" class="address-block">
                                                        <h5><?php echo $lang['laddress'] . ' ' . $count; ?></h5>
                                                        <div class="row">
                                                            <div class="col-md-4 mb-3">
                                                                <div class="form-group">
                                                                    <label class="control-label col-form-label"><?php echo $lang['leftorder318'] ?> <span class="required-mark">*</span></label>
                                                                    <select class="select2 form-control custom-select" name="country[]" id="country<?php echo $count; ?>">
                                                                        <?php if ($country) { ?><option value="<?php echo (int) $country->id; ?>" selected><?php echo $h($country->name); ?></option><?php } ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <div class="form-group">
                                                                    <label class="control-label col-form-label">State <span class="required-mark">*</span></label>
                                                                    <select class="select2 form-control custom-select" id="state<?php echo $count; ?>" name="state[]">
                                                                        <?php if ($state) { ?><option value="<?php echo (int) $state->id; ?>" selected><?php echo $h($state->name); ?></option><?php } ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <div class="form-group">
                                                                    <label class="control-label col-form-label"><?php echo $lang['leftorder320'] ?> <span class="required-mark">*</span></label>
                                                                    <select class="select2 form-control custom-select" id="city<?php echo $count; ?>" name="city[]">
                                                                        <?php if ($city) { ?><option value="<?php echo (int) $city->id; ?>" selected><?php echo $h($city->name); ?></option><?php } ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label><?php echo $lang['user_manage14'] ?> <span class="required-mark">*</span></label>
                                                                    <input type="text" class="form-control form-control-sm" value="<?php echo $h($rowAddress->zip_code); ?>" name="postal[]" id="postal<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage14'] ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="form-group">
                                                                    <label><?php echo $lang['user_manage10'] ?> <span class="required-mark">*</span></label>
                                                                    <input type="text" class="form-control form-control-sm" value="<?php echo $h($rowAddress->address); ?>" name="address[]" id="address<?php echo $count; ?>" placeholder="<?php echo $lang['user_manage10'] ?>">
                                                                </div>
                                                            </div>
                                                            <input type="hidden" name="address_id[]" id="address_id<?php echo $count; ?>" value="<?php echo (int) $rowAddress->id_addresses; ?>" />
                                                            <?php if ($count > 1) { ?>
                                                                <div align="center" class="col-md-4">
                                                                    <label>&nbsp;</label>
                                                                    <div class="form-group">
                                                                        <button type="button" name="remove_row" id="<?php echo $count; ?>" class="btn btn-danger remove_row">
                                                                            <span class="fa fa-trash"></span> <?php echo $lang['delete_address_recepient'] ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                <?php } ?>

                                                <input type="hidden" name="total_address" id="total_address" value="<?php echo $count; ?>" />
                                                <div id="div_address_multiple"></div>

                                                <div align="left">
                                                    <button type="button" name="add_row" id="add_row" class="btn btn-success mb-2"><span class="fa fa-plus"></span> <?php echo $lang['add_address_recepient'] ?></button>
                                                </div>

                                                <hr />
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label for="notes"><?php echo $lang['user_manage31'] ?> <small class="text-muted">(optional)</small></label>
                                                            <textarea class="form-control" name="notes" id="notes" rows="4" placeholder="<?php echo $lang['user_manage31'] ?>"><?php echo $h(trim((string) $row->notes)); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>

                                            <div class="form-group">
                                                <div class="col-sm-12">
                                                    <button class="btn btn-outline-danger btn-confirmation" name="save_data" id="save_data" type="submit"><?php echo $lang['user-account20'] ?> <span><i class="icon-ok"></i></span></button>
                                                    <a href="<?php echo $user->cdp_is_Admin() && !$isOwn ? 'customers_list.php' : 'index.php'; ?>" class="btn btn-outline-secondary btn-confirmation"><span><i class="ti-share-alt"></i></span> <?php echo $lang['user_manage30'] ?></a>
                                                </div>
                                                <input name="id" id="profile_user_id" type="hidden" value="<?php echo (int) $row->id; ?>" />
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="count_address" id="count_address" value="<?php echo $count; ?>" />
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script>
        window.cdpProfile = {
            isOwn: <?php echo $isOwn ? 'true' : 'false'; ?>,
            userId: <?php echo (int) $row->id; ?>,
            phone: <?php echo json_encode((string) $row->phone); ?>,
            phoneStatus: <?php echo json_encode($phoneStatus); ?>
        };
    </script>
    <script src="<?= cdp_asset('dataJs/customers_profile_edit.js') ?>"></script>
</body>

</html>
