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

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_sms/api_sms_service.php");

$user   = new User;
$core   = new Core;
$errors = array();
$ctx = cdp_getAgencyContext();

if (empty($_POST['sender_id']))
    $errors['sender_id'] = $lang['validate_field_ajax144'];

if (empty($_POST['sender_address_id']))
    $errors['sender_address_id'] = $lang['validate_field_ajax145'];

if (empty($_POST['recipient_id']))
    $errors['recipient_id'] = $lang['validate_field_ajax146'];

if (empty($_POST['recipient_address_id']))
    $errors['recipient_address_id'] = $lang['validate_field_ajax147'];

if (empty($_POST['agency']))
    $errors['agency'] = $lang['validate_field_ajax148'];

if (empty($_POST['origin_off']))
    $errors['origin_off'] = $lang['validate_field_ajax149'];

if (empty($_POST['order_no']))
    $errors['order_no'] = $lang['validate_field_ajax150'];

// if (empty($_POST['order_item_category']))
//     $errors['order_item_category'] = $lang['validate_field_ajax151'];

if (empty($_POST['order_package']))
    $errors['order_package'] = $lang['validate_field_ajax152'];

if (empty($_POST['order_courier']))
    $errors['order_courier'] = $lang['validate_field_ajax153'];

// if (empty($_POST['order_service_options']))
//     $errors['order_service_options'] = $lang['validate_field_ajax154'];

if (empty($_POST['order_deli_time']))
    $errors['order_deli_time'] = $lang['validate_field_ajax155'];

if (empty($_POST['status_courier']))
    $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_payment_method']))
    $errors['order_payment_method'] = $lang['validate_field_ajax158'];

// -------------------------------------------------------------------------
// Package-item validation (weight XOR custom price). Runs BEFORE any row is
// inserted so an invalid payload can never leave an orphan shipment behind.
// Pricing model: each item is priced EITHER by weight OR by a custom USD
// price — never both, never neither.
// -------------------------------------------------------------------------
$packages_in = isset($_POST['packages']) ? json_decode($_POST['packages']) : null;
if (!is_array($packages_in) || count($packages_in) === 0) {
    $errors['packages'] = isset($lang['validate_field_packages_required'])
        ? $lang['validate_field_packages_required']
        : 'Add at least one package item.';
} else {
    foreach ($packages_in as $vi => $vp) {
        $rown    = $vi + 1;
        $vdesc   = isset($vp->description) ? trim((string) $vp->description) : '';
        $vqty    = isset($vp->qty) ? (float) $vp->qty : 0;
        $vweight = isset($vp->weight) ? (float) $vp->weight : 0;
        $vcustom = isset($vp->custom_price) ? (float) $vp->custom_price : 0;

        if ($vdesc === '') {
            $errors["pkg_desc_$vi"] = "Row $rown: description is required.";
        }
        if ($vqty <= 0) {
            $errors["pkg_qty_$vi"] = "Row $rown: quantity must be greater than 0.";
        }
        if ($vweight > 0 && $vcustom > 0) {
            $errors["pkg_excl_$vi"] = "Row $rown: use either weight OR custom price, not both.";
        }
        // Pricing (weight/custom) is intentionally NOT required at creation — the
        // adder only records qty + description; pricing is set later in Ghana.
    }
}

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $site_email   = $settings->email_address;
    $check_mail   = $settings->mailer;
    $names_info   = $settings->smtp_names;
    $mlogo        = $settings->logo;
    $msite_url    = $settings->site_url;
    $msnames      = $settings->site_name;
    $smtphoste    = $settings->smtp_host;
    $smtpuser     = $settings->smtp_user;
    $smtppass     = $settings->smtp_password;
    $smtpport     = $settings->smtp_port;
    $smtpsecure   = $settings->smtp_secure;
    $value_weight = $settings->value_weight;
    $meter        = $settings->meter;

    // NOTIFY SMS CLICKSEND API
    $templatessender   = 2;
    $templatesreceiver = 1;

    $next_order             = $core->cdp_order_track();
    $min_cost_tax           = $core->min_cost_tax;
    $min_cost_declared_tax  = $core->min_cost_declared_tax;

    $date      = date('Y-m-d', strtotime(cdp_sanitize($_POST["order_date"])));
    $time      = date("H:i:s");
    $date      = $date . ' ' . $time;
    $sale_date = date("Y-m-d H:i:s");

    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days           = intval($payment_methods->days);
    $due_date       = cdp_sumardias($sale_date, $days);

    if ($days == 0) {
        $status_invoice = 1;
    } else {
        $status_invoice = 2;
    }

    if (isset($_POST["prefix_check"]) && intval($_POST["prefix_check"]) == 1) {
        $code_prefix = cdp_sanitize($_POST["code_prefix2"]);
    } else {
        $code_prefix = cdp_sanitize($_POST["code_prefix"]);
    }

    $is_pickup       = false;
    $order_incomplete = 1;
    $tariff_mode      = isset($_POST['tariff_mode']) ? 1 : 0; // 1 = manual, 0 = motor tarifas

    if ($tariff_mode == 0 && isset($_POST['packages'])) {
        $packages_pre = json_decode($_POST['packages']);
        if (is_array($packages_pre) && count($packages_pre) > 0) {
            $distance_miles_pre = (float)($_POST['distance_miles'] ?? 0);
            $order_svc_pre      = (int)($_POST['order_service_options'] ?? 0);
            $tariff_pre = cdp_calculateTariffServerSide(
                intval($_POST['sender_id']),
                intval($_POST['sender_address_id']),
                intval($_POST['recipient_id']),
                intval($_POST['recipient_address_id']),
                $order_svc_pre,
                $packages_pre,
                $distance_miles_pre,
                (float)$meter,
                (int)($_POST['order_item_category'] ?? 0)
            );
            if ($tariff_pre === null) {
                $errors['tariff_not_found'] = isset($lang['tariff_no_configured']) ? $lang['tariff_no_configured'] : 'No hay tarifa configurada para la ruta/modo/peso indicados.';
            }
        }
    }

    if (empty($errors)) {

    $agencyId = (int)$_POST["agency"];
    if ($ctx['is_restricted']) {
        if ($ctx['agency_id'] === null) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'agency_not_configured', 'message' => 'Su usuario de agencia no tiene una agencia asociada.']);
            exit;
        }

        $agencyId = (int)$ctx['agency_id'];
        $sender_id = (int)$_POST['sender_id'];
        $db_check = new Conexion;
        $db_check->cdp_query('SELECT agency_id FROM cdb_users WHERE id = :id AND userlevel = 1 LIMIT 1');
        $db_check->bind(':id', $sender_id);
        $db_check->cdp_execute();
        $sender_row = $db_check->cdp_registro();
        if (!$sender_row || (int)$sender_row->agency_id !== $agencyId) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'sender_forbidden', 'message' => 'El cliente no pertenece a su agencia.']);
            exit;
        }
    }

    // The add form does not always submit service/category — fall back to the
    // admin-configured defaults so the stored row (and every message built from
    // it) carries real values instead of 0/N/A.
    $infoship_defaults = cdp_getInfoShipDefault();
    $order_service_options_in = intval($_POST["order_service_options"] ?? 0);
    if ($order_service_options_in <= 0) {
        $order_service_options_in = (int) ($infoship_defaults->service_default4 ?? 0);
    }
    $order_item_category_in = intval($_POST["order_item_category"] ?? 0);
    if ($order_item_category_in <= 0) {
        // Courier is AIR: default to "Air Freight" (cdb_category 26). The system
        // logistics_default1 is the sea/Ocean Freight default and must not leak here.
        $order_item_category_in = 26;
    }

    $dataShipment = array(
        'user_id'              => $_SESSION['userid'],
        'order_prefix'         => $code_prefix,
        'is_pickup'            => $is_pickup,
        'order_incomplete'     => $order_incomplete,
        'order_no'             => cdp_sanitize($_POST["order_no"]),
        'order_datetime'       => cdp_sanitize($date),
        'sender_id'            => cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id'         => cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id'    => cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' => cdp_sanitize(intval($_POST["recipient_address_id"])),
        'order_date'           => date("Y-m-d H:i:s"),
        'agency'               => $agencyId,
        'origin_off'           => cdp_sanitize(intval($_POST["origin_off"])),
        'order_package'        => cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category'  => $order_item_category_in,
        'order_courier'        => cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options'=> $order_service_options_in,
        'order_deli_time'      => cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' => cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier'       => cdp_sanitize(intval($_POST["status_courier"])),
        'driver_id'            => cdp_sanitize(intval($_POST["driver_id"])),
        'due_date'             => $due_date,
        'status_invoice'       => $status_invoice,
        'volumetric_percentage'=> $meter,
        'manual_tariff'        => $tariff_mode,
        'tracking_number' => cdp_sanitize(intval($_POST['tracking_number'])),
        'estimated_eta' => cdp_sanitize($_POST['estimated_eta']),
        'recipient_type' => cdp_sanitize($_POST['recipient_type'] ?? 'recipient'),
        'notify_whatsapp_sender' => (isset($_POST['notify_whatsapp_sender']) && intval($_POST['notify_whatsapp_sender']) === 1) ? 1 : 0,
        // Dangerous-goods (hazmat) flag — checkbox only posts when checked.
        'is_dangerous_good' => (isset($_POST['is_dangerous_good']) && intval($_POST['is_dangerous_good']) === 1) ? 1 : 0,
    );

    // Pre-alert conversion (air): carry the purchase details onto the shipment.
    $prealert_id = intval($_POST['prealert_id'] ?? 0);
    if ($prealert_id > 0) {
        $prealert_res = cdp_getPreAlert($prealert_id);
        $prealert_row = is_array($prealert_res) ? ($prealert_res['data'] ?? null) : $prealert_res;
        if ($prealert_row) {
            $dataShipment['tracking_purchase'] = $prealert_row->tracking;
            $dataShipment['provider_purchase'] = $prealert_row->provider_shop;
            $dataShipment['price_purchase']    = $prealert_row->purchase_price;
        }
    }

    $shipment_id = cdp_insertCourierShipment($dataShipment);

    if ($shipment_id !== null) {

        // Mark the pre-alert as converted into a shipment.
        if ($prealert_id > 0) {
            $db_pa = new Conexion;
            $db_pa->cdp_query("UPDATE cdb_pre_alert SET is_package = 1 WHERE pre_alert_id = :id");
            $db_pa->bind(':id', $prealert_id);
            $db_pa->cdp_execute();
        }

        // Original total weight: the actual package weight entered by staff.
        // Declared out here so a request without a packages payload can't leave
        // it undefined and silently save a 0 weight.
        $package_total_weight = (isset($_POST['package_total_weight']) && $_POST['package_total_weight'] !== '')
            ? floatval($_POST['package_total_weight'])
            : 0.0;

        if (isset($_POST["packages"])) {

            $packages = json_decode($_POST['packages']);

            // Sumas principales (alineadas con courier_add.js)
            $sumador_total           = 0.0;
            $sumador_valor_declarado = 0.0;
            $max_fixed_charge        = 0.0;
            $sumador_libras          = 0.0; // peso real acumulado
            $sumador_volumetric      = 0.0; // volumétrico retirado (siempre 0)
            $base_packages           = 0.0; // base de paquetes en USD (peso*tarifa + precio personalizado)

            $precio_total            = 0.0;
            $total_impuesto          = 0.0;
            $total_descuento         = 0.0;
            $total_seguro            = 0.0;
            $total_peso              = 0.0;
            $total_impuesto_aduanero = 0.0;
            $total_valor_declarado   = 0.0;

            // Valores provenientes del formulario
            $tariffs_value      = floatval($_POST["tariffs_value"]);
            $declared_value_tax = floatval($_POST["declared_value_tax"]);
            $insurance_value    = floatval($_POST["insurance_value"]);
            $tax_value          = floatval($_POST["tax_value"]);
            $discount_value     = floatval($_POST["discount_value"]);
            $reexpedicion_value = floatval($_POST["reexpedicion_value"]);
            $price_lb           = floatval($_POST["price_lb"]);
            $insured_value      = floatval($_POST["insured_value"]);

            // Pricing audit: count how the items were priced at creation so we
            // can log WHO did the pricing (weight vs custom price).
            $fs_price_custom = 0;
            $fs_price_weight = 0;

            foreach ($packages as $package) {

                // Cantidad (muy importante para que coincida con JS)
                $qty = isset($package->qty) ? floatval($package->qty) : 1;
                if ($qty <= 0) {
                    $qty = 1;
                }

                // New pricing model: an item is priced EITHER by weight OR by a
                // custom USD price — never both. Derive the mode from the flag
                // (fall back to "custom" when a custom price is present) and then
                // force-clear the unused field so the row can never carry both.
                $weight       = isset($package->weight)       ? floatval($package->weight)       : 0;
                $custom_price = isset($package->custom_price) ? floatval($package->custom_price) : 0;
                $use_custom   = isset($package->use_custom_price)
                    ? (int) $package->use_custom_price
                    : ($custom_price > 0 ? 1 : 0);

                if ($use_custom) {
                    $weight = 0.0;        // custom-priced item carries no pricing weight
                } else {
                    $custom_price = 0.0;
                }

                // Dimensions are retired from this flow; keep the columns zero-filled
                // (guarded so a payload without them never warns under PHP 8).
                $length = isset($package->length) ? floatval($package->length) : 0;
                $width  = isset($package->width)  ? floatval($package->width)  : 0;
                $height = isset($package->height) ? floatval($package->height) : 0;

                $declared_val   = isset($package->declared_value) ? floatval($package->declared_value) : 0;
                $fixed_val      = isset($package->fixed_value)    ? floatval($package->fixed_value)    : 0;

                // Guardar detalle de paquete
                $dataAddresses = array(
                    'order_id'       => $shipment_id,
                    'qty'            => $qty,
                    'description'    => $package->description ?? '',
                    'length'         => $length,
                    'width'          => $width,
                    'height'         => $height,
                    'weight'         => $weight,
                    'declared_value' => $declared_val,
                    'fixed_value'    => $fixed_val,
                    'custom_price'   => $use_custom ? $custom_price : null,
                );

                cdp_insertCourierShipmentPackages($dataAddresses);

                // Per-item line total in USD (mirrors computeLineTotal in courier_add.js):
                //   weight item -> weight * qty * price_lb (price_lb = system rate)
                //   custom item -> custom_price * qty
                if ($use_custom) {
                    $base_packages += $custom_price * $qty;
                    if ($custom_price > 0) { $fs_price_custom++; }
                } else {
                    $base_packages += $weight * $qty * $price_lb;
                    if ($weight > 0) { $fs_price_weight++; }
                }

                // Acumulados multiplicando por qty (igual JS)
                $sumador_libras          += $weight * $qty;
                $sumador_valor_declarado += $declared_val * $qty;
                $max_fixed_charge        += $fixed_val * $qty;
            }

            // Redondeos coherentes con JS
            $sumador_libras     = round($sumador_libras, 2);
            $sumador_volumetric = 0.0; // volumétrico retirado del flujo

            // Peso cobrable (chargeable) = peso real (volumétrico retirado)
            $calculate_weight = $sumador_libras;
            $base_packages    = round($base_packages, 2);

            // == BASE FLETE == (USD; backend es la fuente de verdad)
            if ($tariff_mode == 0) {
                $distance_miles = (float)($_POST['distance_miles'] ?? 0);
                $order_svc      = (int)($_POST['order_service_options'] ?? 0);
                $tariffResult   = cdp_calculateTariffServerSide(
                    intval($_POST['sender_id']),
                    intval($_POST['sender_address_id']),
                    intval($_POST['recipient_id']),
                    intval($_POST['recipient_address_id']),
                    $order_svc,
                    $packages,
                    $distance_miles,
                    (float)$meter,
                    (int)$order_item_category_in
                );
                if ($tariffResult !== null) {
                    $sumador_total = $tariffResult['total_tarifa'];
                    $price_lb      = $tariffResult['price_lb_derived'];
                } else {
                    $sumador_total = $base_packages;
                }
            } else {
                // Manual mode (default): per-item USD base = weight*rate + custom price.
                $sumador_total = $base_packages;
            }

            // == IMPUESTO ==
            if ($sumador_total > $min_cost_tax) {
                $total_impuesto = $sumador_total * ($tax_value / 100);
            }

            // == VALOR DECLARADO ==
            if ($sumador_valor_declarado > $min_cost_declared_tax) {
                $total_valor_declarado = $sumador_valor_declarado * ($declared_value_tax / 100);
            }

            // == DESCUENTO (porcentaje del base o monto fijo en USD) ==
            $discount_type = (isset($_POST['discount_type']) && $_POST['discount_type'] === 'amount') ? 'amount' : 'percent';
            $total_descuento = ($discount_type === 'amount')
                ? $discount_value
                : $sumador_total * ($discount_value / 100);
            // Evitar incoherencias (igual que JS: no negativo y no mayor que el total)
            if ($discount_value < 0 || $total_descuento > $sumador_total) {
                $discount_value  = 0;
                $total_descuento = 0;
            }

            // == SEGURO ==
            $total_seguro = $insured_value * ($insurance_value / 100);

            // == PESO TOTAL (para arancel) ==
            $total_peso = $sumador_libras + $sumador_volumetric;

            // Stored in cdb_add_order.total_weight (display/record only — the
            // customs tariff still uses the summed item weight above). Falls back
            // to the summed weight when staff left the field blank.
            if ($package_total_weight <= 0) {
                $package_total_weight = $total_peso;
            }

            // == IMPUESTO ADUANERO ==
            $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;

            // == TOTAL ENVÍO ==
            $total_envio = ($sumador_total - $total_descuento)
                + $total_seguro
                + $total_impuesto
                + $total_impuesto_aduanero
                + $total_valor_declarado
                + $max_fixed_charge
                + $reexpedicion_value;

            $total_envio = round($total_envio, 2);
        }

        // Totales para guardar en la tabla principal (coherentes con lo que ves en el front)
        $dataShipmentUpdateTotals = array(
            'order_id'                    => $shipment_id,
            'value_weight'                => floatval($price_lb),           // precio por lb/kilo
            'sub_total'                   => floatval($sumador_total),      // base_flete
            'tax_discount'                => floatval($discount_value),     // % o monto fijo
            'total_insured_value'         => floatval($insured_value),
            'tax_insurance_value'         => floatval($insurance_value),    // %
            'tax_custom_tariffis_value'   => floatval($tariffs_value),      // %
            'tax_value'                   => floatval($tax_value),          // %
            'declared_value'              => floatval($declared_value_tax), // %
            'total_reexp'                 => floatval($reexpedicion_value),
            'total_declared_value'        => floatval($total_valor_declarado),
            'total_fixed_value'           => floatval($max_fixed_charge),
            'total_tax_discount'          => floatval($total_descuento),
            'total_tax_insurance'         => floatval($total_seguro),
            'total_tax_custom_tariffis'   => floatval($total_impuesto_aduanero),
            'total_tax'                   => floatval($total_impuesto),
            'total_weight'                => floatval($package_total_weight),
            'total_order'                 => floatval($total_envio),
        );

        $update      = cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);

        // Persist the discount mode (percent vs flat amount) in its own column.
        $db_dt = new Conexion;
        $db_dt->cdp_query("UPDATE cdb_add_order SET discount_type = :dt WHERE order_id = :oid");
        $db_dt->bind(':dt', $discount_type ?? 'percent');
        $db_dt->bind(':oid', $shipment_id);
        $db_dt->cdp_execute();

        $order_track = $code_prefix . $_POST["order_no"];

        // =======================
        // ARCHIVOS ADJUNTOS
        // =======================
        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir       = "../../order_files/";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    $image_name    = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                    $target_file   = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                    $imageFileZise = $_FILES["filesMultiple"]["size"][$key];

                    if ($imageFileZise > 0) {
                        move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                    }

                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertOrdersFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), '0', $imageFileType);
                }
            }
        }

        // =======================
        // VIDEO CLIPS (captured/uploaded) — kept small client-side (~2–5 MB);
        // a hard 6 MB server cap guards against oversized uploads.
        // =======================
        if (isset($_FILES['filesVideo']) && is_array($_FILES['filesVideo']['name'])) {
            cdp_saveShipmentVideos($_FILES['filesVideo'], (int) $shipment_id, $order_track);
        }

        // =======================
        // TRACKING / HISTORIAL / NOTIFICACIONES
        // =======================

        $dataTrack = array(
            'user_id'       => $_SESSION['userid'],
            'order_id'      => $shipment_id,
            'order_track'   => $order_track,
            't_date'        => date("Y-m-d H:i:s"),
            'status_courier'=> cdp_sanitize(intval($_POST["status_courier"])),
            'comments'      => $lang['notification_shipment8'],
            'office'        => cdp_sanitize(intval($_POST["origin_off"]))
        );

        cdp_insertCourierShipmentTrack($dataTrack);

        $sender_data   = cdp_getSenderCourier(intval($_POST["sender_id"]));
        // recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
        $receiver_data = ((cdp_sanitize($_POST['recipient_type'] ?? 'recipient')) === 'user')
            ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
            : cdp_getRecipientCourier(intval($_POST["recipient_id"]));

        $fullshipment = $code_prefix . $_POST["order_no"];

        $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status  = $name_status->mod_style;

        $date_ship = date("Y-m-d H:i:s a");

        $app_url = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;
        $subject = $lang['notification_shipment2'] . $lang['notification_shipment6'] . $fullshipment;

        $email_template = cdp_getEmailTemplatesdg1i4(16);

        require_once(__DIR__ . '/../../helpers/notify_placeholders.php');
        $pkg_ph = cdp_buildPackageNotifyPlaceholders($shipment_id);

        $body = str_replace(
            array_merge(array(
                '[NAME]',
                '[TRACKING]',
                '[DELIVERY_TIME]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]',
                '[URL_SHIP]'
            ), array_keys($pkg_ph)),
            array_merge(array(
                cdp_nameWithLocker($sender_data),
                $fullshipment,
                $date_ship,
                $msite_url,
                $mlogo,
                $msnames,
                $app_url
            ), array_values($pkg_ph)),
            $email_template->body
        );

        // Template 16 ships with a [SHIPMENT_DETAILS] slot, but an admin can edit
        // it away — append the block so the package weight / items survive that.
        if (strpos($email_template->body, '[SHIPMENT_DETAILS]') === false && $pkg_ph['[SHIPMENT_DETAILS]'] !== '') {
            $body .= $pkg_ph['[SHIPMENT_DETAILS]'];
        }

        $newbody = cdp_cleanOutx($body);

        // --- Email (PHP mail o SMTP) ---
        if ($check_mail == 'PHP') {

            $message = $newbody;
            $to      = $sender_data->email;
            $from    = $site_email;

            $header  = "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html; charset=UTF-8 \r\n";
            $header .= "From: " . $from . " \r\n";
            try {
                mail($to, $subject, $message, $header);
            } catch (Exception $e) {
            }
        } elseif ($check_mail == 'SMTP') {

            $destinatario = $sender_data->email;

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $smtphoste;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpuser;
            $mail->Password   = $smtppass;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom($site_email, $names_info);
            $mail->addAddress($destinatario);
            $mail->addCC($site_email, $lang['notification_shipment5']);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = "
                <html> 
                <body> 
                <p>{$newbody}</p>
                </body> 
                </html>
                <br />";

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            try {
                $estadoEnvio = $mail->Send();
            } catch (Exception $e) {
            }
        }

        // Historial usuario
        $dataHistory = array(
            'user_id'      => $_SESSION['userid'],
            'order_id'     => $shipment_id,
            'order_track'  => $order_track,
            'action'       => $lang['notification_shipment8'],
            'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertCourierShipmentUserHistory($dataHistory);

        // Pricing audit — log WHO priced the items at creation (weight/custom).
        if (($fs_price_custom + $fs_price_weight) > 0) {
            cdp_insertCourierShipmentUserHistory(array(
                'user_id'      => $_SESSION['userid'],
                'order_id'     => $shipment_id,
                'order_track'  => $order_track,
                'action'       => 'Pricing — set at creation: ' . $fs_price_weight . ' by weight, '
                    . $fs_price_custom . ' custom-priced (base $' . number_format((float) $base_packages, 2) . ')',
                'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
            ));
        }

        // Notificación general
        $dataNotification = array(
            'user_id'                  => $_SESSION['userid'],
            'order_id'                 => $shipment_id,
            'order_track'              => $order_track,
            'notification_description' => $lang['notification_shipment'],
            'shipping_type'            => '1',
            'notification_date'        => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertNotification($dataNotification);
        $notification_id = $db->dbh->lastInsertId();

        // NOTIFICATION TO DRIVER
        cdp_insertNotificationsUsers($notification_id, intval($_POST["driver_id"]));
        // NOTIFICATION TO ADMIN AND EMPLOYEES
        $users_employees = cdp_getUsersAdminEmployees();
        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        // NOTIFICATION TO CUSTOMER
        cdp_insertNotificationsUsers($notification_id, intval($_POST['sender_id']));

        // Dirección remitente
        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $sender_zip_code     = $sender_address_data ? ($sender_address_data->zip_code ?? '') : '';
        $sender_address      = $sender_address_data ? ($sender_address_data->address ?? '') : '';
        $final_sender_country_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '')
            : '';
        $final_sender_state_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->state, 'cdp_getState', $sender_address_data->legacy_state ?? '')
            : '';
        $final_sender_city_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->city, 'cdp_getCity', $sender_address_data->legacy_city ?? '')
            : '';

        // Recipient address — use proper lookup with legacy fallback for user-type recipients
        $recipient_type = isset($_POST["recipient_type"]) ? cdp_sanitize($_POST["recipient_type"]) : 'recipient';
        if ($recipient_type === 'user') {
            $recipient_address_data = cdp_getSenderAddress(intval($_POST["recipient_address_id"]));
        } else {
            $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));
        }

        $recipient_address  = $recipient_address_data ? ($recipient_address_data->address  ?? '') : '';
        $recipient_zip_code = $recipient_address_data ? ($recipient_address_data->zip_code ?? '') : '';
        $final_recipient_country_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->country, 'cdp_getCountry', $recipient_address_data->legacy_country ?? '')
            : '';
        $final_recipient_state_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->state, 'cdp_getState', $recipient_address_data->legacy_state ?? '')
            : '';
        $final_recipient_city_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->city, 'cdp_getCity', $recipient_address_data->legacy_city ?? '')
            : '';

        // SAVE ADDRESS FOR Shipments
        $dataAddressesShip = array(
            'order_id'          => $shipment_id,
            'order_track'       => $order_track,
            'sender_country'    => $final_sender_country_name,
            'sender_state'      => $final_sender_state_name,
            'sender_city'       => $final_sender_city_name,
            'sender_zip_code'   => $sender_zip_code,
            'sender_address'    => $sender_address,
            'recipient_country' => $final_recipient_country_name,
            'recipient_state'   => $final_recipient_state_name,
            'recipient_city'    => $final_recipient_city_name,
            'recipient_zip_code'=> $recipient_zip_code,
            'recipient_address' => $recipient_address,
        );

        cdp_insertCourierShipmentAddresses($dataAddressesShip);

        if (!empty($_POST['notify_whatsapp_sender']) && intval($_POST['notify_whatsapp_sender']) === 1) {
            try {
                require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

                $db_sender = new Conexion;
                $db_sender->cdp_query("SELECT * FROM cdb_users WHERE id = :id");
                $db_sender->bind(':id', intval($_POST['sender_id']));
                $db_sender->cdp_execute();
                $sender_data = $db_sender->cdp_registro();

                if ($sender_data && !empty($sender_data->phone)) {
                    $tpl = getTemplateWhatsApp(4);

                    if ($tpl && !empty($tpl->body)) {

                        // Courier name
                        $db_courier = new Conexion;
                        $db_courier->cdp_query("SELECT name_com FROM cdb_courier_com WHERE id = :id LIMIT 1");
                        $db_courier->bind(':id', intval($dataShipment['order_courier']));
                        $db_courier->cdp_execute();
                        $courier_obj = $db_courier->cdp_registro();
                        $courier_name = $courier_obj ? $courier_obj->name_com : 'N/A';

                        // Service type
                        $db_service = new Conexion;
                        $db_service->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
                        $db_service->bind(':id', intval($dataShipment['order_service_options']));
                        $db_service->cdp_execute();
                        $service_obj = $db_service->cdp_registro();
                        $service_type = $service_obj ? $service_obj->ship_mode : 'N/A';

                        // Delivery time
                        $db_delivery = new Conexion;
                        $db_delivery->cdp_query("SELECT delitime FROM cdb_delivery_time WHERE id = :id LIMIT 1");
                        $db_delivery->bind(':id', intval($dataShipment['order_deli_time']));
                        $db_delivery->cdp_execute();
                        $delivery_obj = $db_delivery->cdp_registro();
                        $delivery_time = $delivery_obj ? $delivery_obj->delitime : 'N/A';

                        // Package type
                        $db_package = new Conexion;
                        $db_package->cdp_query("SELECT name_pack FROM cdb_pack WHERE id = :id LIMIT 1");
                        $db_package->bind(':id', intval($dataShipment['order_package']));
                        $db_package->cdp_execute();
                        $package_obj = $db_package->cdp_registro();
                        $package_type = $package_obj ? $package_obj->name_pack : 'N/A';

                        // Origin office
                        $db_office = new Conexion;
                        $db_office->cdp_query("SELECT name_off FROM cdb_offices WHERE id = :id LIMIT 1");
                        $db_office->bind(':id', intval($dataShipment['origin_off']));
                        $db_office->cdp_execute();
                        $office_obj = $db_office->cdp_registro();
                        $origin_office = $office_obj ? $office_obj->name_off : 'N/A';

                        // Extra, well-sourced details: pieces / weight /
                        // carrier tracking / ETA — only lines we actually have.
                        require_once(__DIR__ . '/../../helpers/notify_placeholders.php');
                        $wa_ph = cdp_buildPackageNotifyPlaceholders($shipment_id);

                        $extra_lines = array();
                        if (isset($_POST['packages'])) {
                            $pkgs_wa = json_decode($_POST['packages']);
                            if (is_array($pkgs_wa) && count($pkgs_wa) > 0) {
                                $pieces_wa = 0;
                                foreach ($pkgs_wa as $p_wa) {
                                    $pieces_wa += max(1, (int) ($p_wa->qty ?? 1));
                                }
                                $extra_lines[] = '• Pieces: ' . $pieces_wa;
                            }
                        }
                        // Package weight = the staff-entered Original Total Weight, read
                        // back from the row we just saved. Item weights only price items
                        // (a custom-priced item carries 0 weight), so they are never
                        // summed here. This line used to sit inside the packages guard
                        // above, which dropped it whenever no package json was posted.
                        if ($wa_ph['[WEIGHT]'] !== 'N/A') {
                            $extra_lines[] = '• Total Weight: ' . $wa_ph['[WEIGHT]'];
                        }
                        $postal_tracking_wa = trim((string) ($_POST['tracking_number'] ?? ''));
                        if ($postal_tracking_wa !== '' && $postal_tracking_wa !== '0') {
                            $extra_lines[] = '• Carrier Tracking #: ' . $postal_tracking_wa;
                        }
                        $eta_wa = trim((string) ($_POST['estimated_eta'] ?? ''));
                        if ($eta_wa !== '') {
                            $extra_lines[] = '• Estimated Arrival: ' . $eta_wa;
                        }

                        // Status + full itemized list (qty x description, no prices)
                        if ($wa_ph['[STATUS]'] !== 'N/A') {
                            $extra_lines[] = '• Status: ' . $wa_ph['[STATUS]'];
                        }
                        if ($wa_ph['[ITEMS]'] !== 'N/A') {
                            $extra_lines[] = '• Items:';
                            foreach (explode("\n", $wa_ph['[ITEMS]']) as $il) {
                                $extra_lines[] = '   - ' . $il;
                            }
                        }

                        $extra_details_wa = $extra_lines ? implode("\n", $extra_lines) . "\n" : '';
                        $track_url_wa = rtrim((string) ($settings->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($fullshipment);

                        $whatsapp_body = str_replace(
                            [
                                '[CUSTOMER_FULLNAME]',
                                '[TRACKING_NUMBER]',
                                '[PROVIDER_NAME]',
                                '[COURIER_NAME]',
                                '[PACKAGE_TYPE]',
                                '[SERVICE_TYPE]',
                                '[DELIVERY_TIME]',
                                '[ORIGIN_OFFICE]',
                                '[EXTRA_DETAILS]',
                                '[TRACK_URL]',
                                '[COMPANY_SITE_URL]',
                                '[COMPANY_NAME]'
                            ],
                            [
                                cdp_nameWithLocker($sender_data),
                                $fullshipment,
                                (string) ($dataShipment['provider_purchase'] ?? ''),
                                $courier_name,
                                $package_type,
                                $service_type,
                                $delivery_time,
                                $origin_office,
                                $extra_details_wa,
                                $track_url_wa,
                                !empty($settings->site_url) ? $settings->site_url : 'www.company.com',
                                !empty($settings->site_name) ? $settings->site_name : 'Our Company'
                            ],
                            $tpl->body
                        );

                        $wa_result = sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);

                        if (!$wa_result['success']) {
                            error_log("WhatsApp notification failed for shipment {$fullshipment}: " . $wa_result['message']);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('WhatsApp notification error for shipment ' . ($fullshipment ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        // =======================
        // SMS
        // =======================
        $notify_sms_sender   = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
        }

        try {
            $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);
            sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for receiver: ' . $e->getMessage());
        }

        cdp_insertPackageTracking($shipment_id, $_SESSION['userid'], cdp_sanitize($_POST['tracking_number']), cdp_sanitize($_POST['estimated_eta']));

        cdp_activityLog([
            'module'       => 'shipments',
            'verb'         => 'create',
            'entity_type'  => 'shipment',
            'entity_id'    => (int) $shipment_id,
            'entity_label' => $code_prefix . $_POST['order_no'],
            'status_id'    => (int) ($_POST['status_courier'] ?? 0) ?: null,
            'status_name'  => cdp_activityStatusName($_POST['status_courier'] ?? 0) ?: null,
            'summary'      => 'Created shipment ' . $code_prefix . $_POST['order_no'],
            'meta'         => [
                'sender_id'    => (int) ($_POST['sender_id'] ?? 0),
                'recipient_id' => (int) ($_POST['recipient_id'] ?? 0),
            ],
        ]);

        $messages[] = $lang['message_ajax_success_add_shipment'];
    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'errors'  => $errors
    ]);
} else {
    echo json_encode([
        'success'     => true,
        'messages'    => $messages,
        'shipment_id' => $shipment_id,
    ]);
}
