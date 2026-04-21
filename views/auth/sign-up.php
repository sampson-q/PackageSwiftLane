<?php
// Sign-up view — Bootstrap 5
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['langs_010112'] ?> | <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon ?>">

    <!-- Bootstrap 5 (local) -->
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (local) -->
    <link href="assets/custom_dependencies/css/bootstrap-icons.css" rel="stylesheet">
    <!-- MDI Icons (local) -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="assets/template/assets/libs/select2/dist/css/select2.min.css" rel="stylesheet">
    <!-- intlTelInput -->
    <link href="assets/template/assets/libs/intlTelInput/intlTelInput.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">

    <!-- jQuery (required by global.js, custom.js, sign-up.js) -->
    <script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
    <script src="assets/js/global.js"></script>
    <script src="assets/js/custom.js"></script>

    <style>
        :root {
            --brand: #1a3a6b;
            --brand-light: #2a5298;
            --brand-grad: linear-gradient(135deg, #1a3a6b 0%, #2a5298 100%);
            --accent: #17b26a;
        }

        html, body {
            min-height: 100%;
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: #f0f4ff;
        }

        /* ── LAYOUT ─────────────────────────── */
        .signup-wrapper {
            min-height: 100vh;
            display: flex;
        }

        /* Left form panel */
        .signup-panel {
            flex: 0 0 580px;
            max-width: 580px;
            background: #fff;
            padding: 40px 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
            box-shadow: 4px 0 30px rgba(0,0,0,0.07);
            overflow-y: auto;
        }

        /* Right hero */
        .signup-hero {
            flex: 1;
            background: var(--brand-grad);
            position: relative;
            overflow: hidden;
        }

        .signup-hero-img {
            position: absolute;
            inset: 0;
            background-image: url('assets/css_main_deprixa/images/user/02.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.22;
        }

        .signup-hero-content {
            position: absolute;
            bottom: 52px;
            left: 52px;
            right: 52px;
            z-index: 2;
            color: #fff;
        }

        .signup-hero-content h1 {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 12px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }

        .signup-hero-content p {
            font-size: 0.95rem;
            opacity: 0.82;
            max-width: 360px;
            line-height: 1.6;
        }

        /* ── BRAND ──────────────────────────── */
        .signup-brand {
            margin-bottom: 24px;
        }

        .signup-brand h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 4px;
        }

        .signup-brand p {
            color: #6c757d;
            font-size: 0.83rem;
            margin: 0;
        }

        /* ── FORM ───────────────────────────── */
        .form-label-sm {
            font-size: 0.78rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            display: block;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1.5px solid #e5e7eb;
            font-size: 0.875rem;
            height: 42px;
            padding: 8px 12px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-light);
            box-shadow: 0 0 0 3px rgba(42,82,152,0.1);
            outline: none;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 15px;
            pointer-events: none;
        }

        .input-wrap .form-control {
            padding-left: 36px;
        }

        /* Select2 sizing */
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border-radius: 8px !important;
            border: 1.5px solid #e5e7eb !important;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            padding-left: 12px;
            font-size: 0.875rem;
            color: #495057;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
        }

        .select2-container {
            width: 100% !important;
        }

        /* Password strength */
        #password-strength-meter {
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 600;
            min-height: 18px;
        }

        #password-strength-meter.weak     { color: #ef4444; }
        #password-strength-meter.weaks    { color: #f97316; }
        #password-strength-meter.medium   { color: #eab308; }
        #password-strength-meter.good     { color: #22c55e; }
        #password-strength-meter.strong   { color: #16a34a; }
        #password-strength-meter.very-strong { color: #15803d; }

        #passwordMatch { font-size: 0.75rem; margin-top: 4px; }

        /* Section divider */
        .form-section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #9ca3af;
            margin: 18px 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* Submit button */
        .btn-signup {
            background: var(--brand-grad);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            font-size: 0.92rem;
            height: 46px;
            width: 100%;
            transition: opacity 0.2s, transform 0.15s;
            cursor: pointer;
        }

        .btn-signup:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* intlTelInput override */
        .iti { width: 100%; }
        .iti__tel-input { width: 100%; }

        /* Alert */
        .alert-warning-reg {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            color: #92400e;
            padding: 12px 16px;
            font-size: 0.875rem;
        }

        @media (max-width: 991px) {
            .signup-wrapper { flex-direction: column; }
            .signup-panel {
                flex: none;
                max-width: 100%;
                padding: 32px 24px;
                box-shadow: none;
            }
            .signup-hero { min-height: 200px; }
            .signup-hero-content {
                bottom: 24px;
                left: 24px;
                right: 24px;
            }
            .signup-hero-content h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="signup-wrapper">

    <!-- ── LEFT: Form Panel ─────────────────────────── -->
    <div class="signup-panel">

        <div class="signup-brand">
            <?php if ($core->logo_web): ?>
                <a href="index.php" style="display:inline-block;margin-bottom:12px">
                    <img src="assets/<?php echo htmlspecialchars((string)$core->logo_web, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8'); ?>"
                         style="max-height:40px;width:auto">
                </a>
            <?php endif; ?>
            <h2><?php echo $lang['left136'] ?></h2>
            <p><?php echo $lang['left137'] ?></p>
        </div>

        <div id="resultados_ajax"></div>

        <?php if (!$core->reg_allowed): ?>
            <div class="alert-warning-reg">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo $lang['langs_010133']; ?>
            </div>
        <?php else: ?>

        <form id="new_register" name="new_register" method="post" autocomplete="off">

            <!-- Personal Info -->
            <div class="form-section-label"><?php echo isset($lang['user_manage_section1']) ? $lang['user_manage_section1'] : 'Personal Information'; ?></div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['left138'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" class="form-control" placeholder="<?php echo $lang['left139'] ?>" name="fname" id="fname" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['left140'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" class="form-control" placeholder="<?php echo $lang['left141'] ?>" name="lname" id="lname" required>
                    </div>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['leftorder164'] ?></label>
                    <select class="form-select" id="document_type" name="document_type">
                        <option value="DNI"><?php echo $lang['leftorder165'] ?></option>
                        <option value="RIC"><?php echo $lang['leftorder166'] ?></option>
                        <option value="CI"><?php echo $lang['leftorder167'] ?></option>
                        <option value="CIE"><?php echo $lang['leftorder168'] ?></option>
                        <option value="CIN"><?php echo $lang['leftorder169'] ?></option>
                        <option value="CIE"><?php echo $lang['leftorder170'] ?></option>
                        <option value="CC"><?php echo $lang['leftorder171'] ?></option>
                        <option value="TI"><?php echo $lang['leftorder172'] ?></option>
                        <option value="CE"><?php echo $lang['leftorder173'] ?></option>
                        <option value="PSP"><?php echo $lang['leftorder174'] ?></option>
                        <option value="NIT"><?php echo $lang['leftorder1745'] ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['leftorder175'] ?></label>
                    <div class="input-wrap">
                        <i class="bi bi-hash input-icon"></i>
                        <input type="text" class="form-control" id="document_number" name="document_number" placeholder="<?php echo $lang['leftorder175'] ?>">
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="form-section-label"><?php echo isset($lang['user_manage_section2']) ? $lang['user_manage_section2'] : 'Contact'; ?></div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['left142'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" class="form-control" placeholder="<?php echo $lang['left143'] ?>" name="email" id="email" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['user_manage9'] ?> <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control iti__tel-input" name="phone_custom" id="phone_custom" autocomplete="off" placeholder="<?php echo $lang['user_manage9'] ?>">
                    <input type="hidden" name="phone" id="phone">
                    <span id="valid-msg" class="hide text-success" style="font-size:.75rem"></span>
                    <div id="error-msg" class="hide text-danger" style="font-size:.75rem"></div>
                </div>
            </div>

            <!-- Address -->
            <div class="form-section-label"><?php echo isset($lang['user_manage_section3']) ? $lang['user_manage_section3'] : 'Address'; ?></div>

            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label-sm"><?php echo $lang['translate_search_address_country'] ?> <span class="text-danger">*</span></label>
                    <select class="select2" name="country" id="country"></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-sm"><?php echo $lang['translate_search_address_state'] ?> <span class="text-danger">*</span></label>
                    <select class="select2" id="state" name="state" disabled></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-sm"><?php echo $lang['translate_search_address_city'] ?> <span class="text-danger">*</span></label>
                    <select class="select2" id="city" name="city" disabled></select>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-md-5">
                    <label class="form-label-sm"><?php echo $lang['user_manage14'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-mailbox input-icon"></i>
                        <input type="text" class="form-control" placeholder="<?php echo $lang['user_manage14'] ?>" name="postal" id="postal">
                    </div>
                </div>
                <div class="col-md-7">
                    <label class="form-label-sm"><?php echo $lang['user_manage10'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-geo-alt input-icon"></i>
                        <input type="text" class="form-control" placeholder="<?php echo $lang['user_manage10'] ?>" name="address" id="address">
                    </div>
                </div>
            </div>

            <!-- Credentials -->
            <div class="form-section-label"><?php echo isset($lang['user_manage_section4']) ? $lang['user_manage_section4'] : 'Account Credentials'; ?></div>

            <div class="mb-2">
                <label class="form-label-sm"><?php echo $lang['left144'] ?> <span class="text-danger">*</span></label>
                <div class="input-wrap">
                    <i class="bi bi-at input-icon"></i>
                    <input type="text" class="form-control" placeholder="<?php echo $lang['left145'] ?>" name="username" id="username" required>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['left146'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" class="form-control" placeholder="<?php echo $lang['left147'] ?>" name="pass" id="pass" required>
                    </div>
                    <div id="password-strength-meter"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-sm"><?php echo $lang['left148'] ?> <span class="text-danger">*</span></label>
                    <div class="input-wrap">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input type="password" class="form-control" placeholder="<?php echo $lang['left149'] ?>" name="pass2" id="pass2" required>
                    </div>
                    <div id="passwordMatch"></div>
                </div>
            </div>

            <!-- Locker (hidden) -->
            <?php if ($core->code_number_locker == 1): ?>
            <div class="mt-2" style="display:none">
                <div class="input-group">
                    <input type="number" class="form-control" name="locker" id="locker"
                           value="<?php echo $lockerauto; ?>"
                           onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>')">
                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>">
                </div>
            </div>
            <?php elseif ($core->code_number_locker == 2): ?>
            <div class="mt-2" style="display:none">
                <div class="input-group">
                    <input type="number" class="form-control" name="locker" id="locker"
                           value="<?php print_r(cdp_generarCodigo('' . $core->digit_random_locker . '')); ?>"
                           onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>')">
                    <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>">
                </div>
            </div>
            <?php endif; ?>

            <!-- Terms -->
            <div class="form-check mt-3 mb-3">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" value="yes" required>
                <label class="form-check-label" for="terms" style="font-size:.83rem;color:#6b7280">
                    <?php echo $lang['left164'] ?> <a href="terms.php" style="color:var(--brand-light);font-weight:600"><?php echo $lang['left165'] ?></a>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-signup" name="dosubmit">
                <i class="bi bi-person-plus me-2"></i>
                <?php echo $lang['left166'] ?>
            </button>

            <p class="text-center mt-3 mb-0" style="font-size:.83rem;color:#6b7280">
                <?php echo $lang['left167'] ?>
                <a href="index.php" style="font-weight:600;color:var(--brand-light);text-decoration:none"><?php echo $lang['left168'] ?></a>
            </p>

        </form>

        <?php endif; ?>

    </div>
    <!-- /.signup-panel -->

    <!-- ── RIGHT: Hero ──────────────────────────────── -->
    <div class="signup-hero d-none d-lg-flex">
        <div class="signup-hero-img"></div>
        <div class="signup-hero-content">
            <h1><?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?php echo isset($lang['cotizador_sub']) ? $lang['cotizador_sub'] : 'Join our platform and manage your shipments, track packages, and get instant rate estimates.'; ?></p>
        </div>
    </div>

</div>
<!-- /.signup-wrapper -->

<?php include('helpers/languages/translate_to_js.php'); ?>

<!-- Bootstrap 5 JS -->
<script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
<!-- Select2 -->
<script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
<!-- intlTelInput -->
<script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
<!-- SweetAlert2 -->
<script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>

<script>
function cdp_validateLockerNumber(value, lockDigits) {
    cdp_convertStrPad(value, lockDigits);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: "./ajax/validate_locker_virtual.php?track=" + value,
        success: function (data) {
            var main = $("#order_no_main").val();
            if (data) {
                alert(message_error_exist_locker);
                $("#digitslockers").val(main);
            }
        }
    });
}

function cdp_convertStrPad(value, dbDigits) {
    var pad = value.padStart(dbDigits, "0");
    $("#digitslockers").val(pad);
}
</script>

<script src="dataJs/sign-up.js"></script>

</body>
</html>
