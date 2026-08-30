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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");

$user = new User;
$core = new Core;
$errors = array();
$messages = array();

// =====================
// VALIDATION (match add where applicable)
// =====================
if (empty($_POST['tracking_purchase'])) $errors['tracking_purchase'] = $lang['validate_field_ajax170'];
if (empty($_POST['provider_purchase'])) $errors['provider_purchase'] = $lang['validate_field_ajax172'];
if (empty($_POST['price_purchase'])) $errors['price_purchase'] = $lang['validate_field_ajax174'];

if (empty($_POST['sender_id'])) $errors['sender_id'] = $lang['validate_field_ajax150'];
if (empty($_POST['sender_address_id'])) $errors['sender_address_id'] = $lang['validate_field_ajax145'];

if (empty($_POST['agency'])) $errors['agency'] = $lang['validate_field_ajax148'];
if (empty($_POST['origin_off'])) $errors['origin_off'] = $lang['validate_field_ajax149'];

if (empty($_POST['order_item_category'])) $errors['order_item_category'] = $lang['validate_field_ajax151'];
if (empty($_POST['order_package'])) $errors['order_package'] = $lang['validate_field_ajax152'];
if (empty($_POST['order_courier'])) $errors['order_courier'] = $lang['validate_field_ajax153'];
// order_service_options keeps its stored value when not posted (the form's select is disabled).
if (empty($_POST['order_deli_time'])) $errors['order_deli_time'] = $lang['validate_field_ajax155'];

if (empty($_POST['status_courier'])) $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_id'])) $errors['order_id'] = $lang['message_ajax_error2'];

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
    $meter        = $settings->meter;

    // NOTIFY SMS CLICKSEND API
    $templatessender = 8;

    $min_cost_tax         = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $shipment_id = (int)cdp_sanitize($_POST["order_id"]);

    // =====================
    // SNAPSHOT BEFORE UPDATE — capture old values for diff
    // =====================
    $old_shipment = cdp_getCustomerPackage($shipment_id);

    // Resolve old lookup labels for diff comparison
    $old_courier_name  = '';
    $old_service_type  = '';
    $old_delivery_time = '';
    $old_status_label  = '';
    $old_eta           = '';

    if ($old_shipment) {
        $db_old = new Conexion;

        $db_old->cdp_query("SELECT name_com FROM cdb_courier_com WHERE id = :id LIMIT 1");
        $db_old->bind(':id', (int)$old_shipment->order_courier);
        $db_old->cdp_execute();
        $r = $db_old->cdp_registro();
        $old_courier_name = $r ? $r->name_com : '';

        $db_old->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
        $db_old->bind(':id', (int)$old_shipment->order_service_options);
        $db_old->cdp_execute();
        $r = $db_old->cdp_registro();
        $old_service_type = $r ? $r->ship_mode : '';

        $db_old->cdp_query("SELECT delitime FROM cdb_delivery_time WHERE id = :id LIMIT 1");
        $db_old->bind(':id', (int)$old_shipment->order_deli_time);
        $db_old->cdp_execute();
        $r = $db_old->cdp_registro();
        $old_delivery_time = $r ? $r->delitime : '';

        $old_status_obj   = cdp_getCourierstatusApi((int)$old_shipment->status_courier);
        $old_status_label = $old_status_obj ? $old_status_obj->mod_style : '';

        // Old ETA from tracking
        $db_old->cdp_query("SELECT estimated_eta FROM cdb_package_tracking WHERE order_id = :id LIMIT 1");
        $db_old->bind(':id', $shipment_id);
        $db_old->cdp_execute();
        $r = $db_old->cdp_registro();
        $old_eta = $r ? $r->estimated_eta : '';
    }

    // =====================
    // UPDATE SHIPMENT
    // =====================
    $dataShipment = array(
        'agency'                 => cdp_sanitize((int)$_POST["agency"]),
        'origin_off'             => cdp_sanitize((int)$_POST["origin_off"]),
        'sender_id'              => cdp_sanitize((int)$_POST["sender_id"]),
        'sender_address_id'      => cdp_sanitize((int)$_POST["sender_address_id"]),
        'tracking_purchase'      => cdp_sanitize($_POST["tracking_purchase"]),
        'provider_purchase'      => cdp_sanitize($_POST["provider_purchase"]),
        'price_purchase'         => cdp_sanitize((float)$_POST["price_purchase"]),
        'order_package'          => cdp_sanitize((int)$_POST["order_package"]),
        'order_item_category'    => cdp_sanitize((int)$_POST["order_item_category"]),
        'order_courier'          => cdp_sanitize((int)$_POST["order_courier"]),
        'order_service_options'  => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) ($old_shipment->order_service_options ?? 0),
        'order_deli_time'        => cdp_sanitize((int)$_POST["order_deli_time"]),
        'status_courier'         => cdp_sanitize((int)$_POST["status_courier"]),
        'order_id'               => $shipment_id,
        'recipient_id'           => (int)(cdp_sanitize($_POST['recipient_id'] ?? 0)),
        'recipient_address_id'   => (int)(cdp_sanitize($_POST['recipient_address_id'] ?? 0)),
        'order_payment_method'   => (int)(cdp_sanitize($_POST['order_payment_method'] ?? 0)),
        'notify_whatsapp_sender' => (int)(cdp_sanitize($_POST['notify_whatsapp_sender'] ?? 0)),
        'driver_id'              => (int)(cdp_sanitize($_POST['driver_id'] ?? 0)),
    );

    $updateShip = cdp_updateCustomerPackages($dataShipment);

    if ($updateShip) {

        // =====================
        // PACKAGES + TOTALS
        // =====================
        if (isset($_POST["packages"])) {
            cdp_deleteCustomersPackagesItems($shipment_id);

            $packages = json_decode($_POST['packages']);

            $sumador_total          = 0;
            $sumador_valor_declarado = 0;
            $max_fixed_charge       = 0;
            $sumador_libras         = 0;
            $sumador_volumetric     = 0;

            $total_impuesto         = 0;
            $total_descuento        = 0;
            $total_seguro           = 0;
            $total_peso             = 0;
            $total_impuesto_aduanero = 0;
            $total_valor_declarado  = 0;

            $tariffs_value       = $_POST["tariffs_value"];
            $declared_value_tax  = $_POST["declared_value_tax"];
            $insurance_value     = $_POST["insurance_value"];
            $tax_value           = $_POST["tax_value"];
            $discount_value      = $_POST["discount_value"];
            $reexpedicion_value  = $_POST["reexpedicion_value"];
            $price_lb            = $_POST["price_lb"];
            $insured_value       = $_POST["insured_value"];

            foreach ($packages as $package) {

                $dataAddresses = array(
                    'order_id'       => $shipment_id,
                    'qty'            => $package->qty,
                    'description'    => $package->description,
                    'length'         => $package->length,
                    'width'          => $package->width,
                    'height'         => $package->height,
                    'weight'         => $package->weight,
                    'declared_value' => $package->declared_value,
                    'fixed_value'    => $package->fixed_value,
                );

                cdp_insertCustomerPackagesItems($dataAddresses);

                $total_metric       = $package->length * $package->width * $package->height / $meter;
                $weight             = $package->weight;

                $sumador_volumetric += $total_metric;
                $sumador_libras     += $weight;

                if ($sumador_libras > $sumador_volumetric) {
                    $calculate_weight = $sumador_libras;
                } else {
                    $calculate_weight = $sumador_volumetric;
                }

                $sumador_total            = $calculate_weight * $price_lb;
                $sumador_valor_declarado += $package->declared_value;
                $max_fixed_charge        += $package->fixed_value;

                if ($sumador_total > $min_cost_tax) {
                    $total_impuesto = $sumador_total * $tax_value / 100;
                }

                if ($sumador_valor_declarado > $min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
                }
            }

            $total_descuento          = $sumador_total * $discount_value / 100;
            $total_peso               = $sumador_libras + $sumador_volumetric;
            $total_seguro             = $insured_value * $insurance_value / 100;
            $total_impuesto_aduanero  = ($total_peso * $tariffs_value) / 100;
            $total_envio              = ($sumador_total - $total_descuento)
                                        + $total_seguro
                                        + $total_impuesto
                                        + $total_impuesto_aduanero
                                        + $total_valor_declarado
                                        + $max_fixed_charge
                                        + $reexpedicion_value;
        }

        $dataShipmentUpdateTotals = array(
            'order_id'                   => $shipment_id,
            'value_weight'               => floatval($price_lb),
            'sub_total'                  => floatval($sumador_total),
            'tax_discount'               => floatval($discount_value),
            'total_insured_value'        => floatval($insured_value),
            'tax_insurance_value'        => floatval($insurance_value),
            'tax_custom_tariffis_value'  => floatval($tariffs_value),
            'tax_value'                  => floatval($tax_value),
            'declared_value'             => floatval($declared_value_tax),
            'total_reexp'                => floatval($reexpedicion_value),
            'total_declared_value'       => floatval($total_valor_declarado),
            'total_fixed_value'          => floatval($max_fixed_charge),
            'total_tax_discount'         => floatval($total_descuento),
            'total_tax_insurance'        => floatval($total_seguro),
            'total_tax_custom_tariffis'  => floatval($total_impuesto_aduanero),
            'total_tax'                  => floatval($total_impuesto),
            'total_weight'               => floatval($total_peso),
            'total_order'                => floatval($total_envio),
        );

        cdp_updateCustomerPackagesTotals($dataShipmentUpdateTotals);

        $shipment    = cdp_getCustomerPackage($shipment_id);
        $order_track = $shipment->order_prefix . $shipment->order_no;

        // Signature: (order_id, user_id, tracking_number, estimated_eta) — the ETA
        // was previously passed in the tracking_number slot.
        cdp_updatePackageTracking($shipment_id, $_SESSION['userid'] ?? null, null, cdp_sanitize($_POST['estimated_eta']));

        // =====================
        // DELETE EXISTING FILES
        // =====================
        if (isset($_POST['deleted_db_file_ids']) && !empty($_POST['deleted_db_file_ids'])) {
            $ids_raw = trim($_POST['deleted_db_file_ids']);
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            foreach ($ids as $fid) {
                if ($fid > 0) cdp_deleteFileCustomerPackages(['id' => $fid]);
            }
        }

        // =====================
        // UPLOAD FILES
        // =====================
        $target_dir      = "../../order_files/";
        $deleted_file_ids = array();

        if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
            $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
        }

        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {
            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {
                if (!in_array($key, $deleted_file_ids)) {
                    $image_name    = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                    $target_file   = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                    if ($_FILES["filesMultiple"]["size"][$key] > 0) {
                        move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file);
                    }
                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertCustomerPackagesFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), $imageFileType);
                }
            }
        }

        if (isset($_FILES['filesCapture']) && count($_FILES['filesCapture']['name']) > 0 && $_FILES['filesCapture']['tmp_name'][0] != '') {
            foreach ($_FILES["filesCapture"]['tmp_name'] as $key => $tmp_name) {
                if (!in_array($key, $deleted_file_ids)) {
                    $image_name    = $order_track . date("Y-m-d") . "_" . basename($_FILES["filesCapture"]["name"][$key]);
                    $target_file   = $target_dir . $image_name;
                    $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                    if ($_FILES["filesCapture"]["size"][$key] > 0) {
                        move_uploaded_file($_FILES["filesCapture"]["tmp_name"][$key], $target_file);
                    }
                    $target_file_db = "order_files/" . $image_name;
                    cdp_insertCustomerPackagesFiles($shipment_id, $target_file_db, $image_name, date("Y-m-d H:i:s"), $imageFileType);
                }
            }
        }

        // Video clips (captured/uploaded) — small client-side (~2–5 MB), 6 MB server cap.
        if (isset($_FILES['filesVideo']) && is_array($_FILES['filesVideo']['name'])) {
            cdp_saveShipmentVideos($_FILES['filesVideo'], (int) $shipment_id, $order_track, true);
        }

        // =====================
        // TRACK + HISTORY + NOTIFICATION
        // =====================
        $dataTrack = array(
            'user_id'        => $_SESSION['userid'],
            'order_id'       => $shipment_id,
            'order_track'    => $order_track,
            't_date'         => date("Y-m-d H:i:s"),
            'status_courier' => cdp_sanitize((int)$_POST["status_courier"]),
            'comments'       => $lang['messagesform109'],
            'office'         => cdp_sanitize((int)$_POST["origin_off"])
        );

        cdp_insertCourierShipmentTrack($dataTrack);

        $dataHistory = array(
            'user_id'      => $_SESSION['userid'],
            'order_id'     => $shipment_id,
            'order_track'  => $order_track,
            'action'       => $lang['messagesform109'],
            'date_history' => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertCourierShipmentUserHistory($dataHistory);

        $dataNotification = array(
            'user_id'                  => $_SESSION['userid'],
            'order_id'                 => $shipment_id,
            'notification_description' => $lang['notification_shipment23'],
            'shipping_type'            => '4',
            'notification_date'        => cdp_sanitize(date("Y-m-d H:i:s")),
        );

        cdp_insertNotification($dataNotification);

        // =====================
        // ADDRESS SNAPSHOT
        // =====================
        cdp_deleteCourierAddress($order_track);

        $sender_address_data = cdp_getSenderAddress((int)$_POST["sender_address_id"]);
        $final_sender_country_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '')
            : '';
        $final_sender_state_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->state, 'cdp_getState', $sender_address_data->legacy_state ?? '')
            : '';
        $final_sender_city_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->city, 'cdp_getCity', $sender_address_data->legacy_city ?? '')
            : '';

        // Resolve recipient address based on recipient_type
        $recipient_type = cdp_sanitize($_POST['recipient_type'] ?? 'recipient');
        $recip_addr     = null;
        if (!empty($_POST['recipient_address_id'])) {
            if ($recipient_type === 'user') {
                $recip_addr = cdp_getSenderAddress((int)$_POST["recipient_address_id"]);
            } else {
                $recip_addr = cdp_getRecipientAddress((int)$_POST["recipient_address_id"]);
            }
        }

        $dataAddresses = array(
            'order_id'           => $shipment_id,
            'order_track'        => $order_track,
            'sender_country'     => $final_sender_country_name,
            'sender_state'       => $final_sender_state_name,
            'sender_city'        => $final_sender_city_name,
            'sender_zip_code'    => $sender_address_data ? ($sender_address_data->zip_code ?? '') : '',
            'sender_address'     => $sender_address_data ? ($sender_address_data->address  ?? '') : '',
            'recipient_country'  => $recip_addr ? cdp_resolveAddressName($recip_addr->country ?? '', 'cdp_getCountry', $recip_addr->legacy_country ?? '') : '',
            'recipient_state'    => $recip_addr ? cdp_resolveAddressName($recip_addr->state   ?? '', 'cdp_getState',   $recip_addr->legacy_state   ?? '') : '',
            'recipient_city'     => $recip_addr ? cdp_resolveAddressName($recip_addr->city    ?? '', 'cdp_getCity',    $recip_addr->legacy_city    ?? '') : '',
            'recipient_zip_code' => $recip_addr ? ($recip_addr->zip_code ?? '') : '',
            'recipient_address'  => $recip_addr ? ($recip_addr->address  ?? '') : '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);

        // =====================
        // RESOLVE NEW LABELS for diff + notifications
        // =====================
        $sender_data = cdp_getSenderCourier((int)$_POST["sender_id"]);
        $fullshipment = $shipment->order_prefix . $shipment->order_no;

        $db_new = new Conexion;

        $db_new->cdp_query("SELECT name_com FROM cdb_courier_com WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)$_POST["order_courier"]);
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_courier_name = $r ? $r->name_com : 'N/A';

        $db_new->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)$_POST["order_service_options"]);
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_service_type = $r ? $r->ship_mode : 'N/A';

        $db_new->cdp_query("SELECT delitime FROM cdb_delivery_time WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)$_POST["order_deli_time"]);
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_delivery_time = $r ? $r->delitime : 'N/A';

        $new_status_obj   = cdp_getCourierstatusApi((int)$_POST["status_courier"]);
        $new_status_label = $new_status_obj ? $new_status_obj->mod_style : 'N/A';
        $new_eta          = cdp_sanitize($_POST['estimated_eta']);

        $app_url = rtrim((string) $settings->site_url, '/') . '/track_online_shopping.php?order_track=' . $fullshipment;

        // =====================
        // BUILD CHANGED FIELDS DIFF
        // =====================
        $changed_fields = [];

        if ($old_shipment) {
            if ((int)$old_shipment->order_courier !== (int)$_POST["order_courier"])
                $changed_fields['Courier'] = ['old' => $old_courier_name, 'new' => $new_courier_name];

            if ((int)$old_shipment->order_service_options !== (int)$_POST["order_service_options"])
                $changed_fields['Service Type'] = ['old' => $old_service_type, 'new' => $new_service_type];

            if ((int)$old_shipment->order_deli_time !== (int)$_POST["order_deli_time"])
                $changed_fields['Estimated Delivery'] = ['old' => $old_delivery_time, 'new' => $new_delivery_time];

            if ((int)$old_shipment->status_courier !== (int)$_POST["status_courier"])
                $changed_fields['Shipment Status'] = ['old' => $old_status_label, 'new' => $new_status_label];

            if (trim($old_eta) !== trim($new_eta) && !empty($new_eta))
                $changed_fields['ETA'] = ['old' => $old_eta ?: 'N/A', 'new' => $new_eta];

            if (trim($old_shipment->tracking_purchase) !== trim(cdp_sanitize($_POST["tracking_purchase"])))
                $changed_fields['Purchase Tracking'] = ['old' => $old_shipment->tracking_purchase, 'new' => cdp_sanitize($_POST["tracking_purchase"])];

            if (trim($old_shipment->provider_purchase) !== trim(cdp_sanitize($_POST["provider_purchase"])))
                $changed_fields['Provider'] = ['old' => $old_shipment->provider_purchase, 'new' => cdp_sanitize($_POST["provider_purchase"])];

            // Packages changed — flag it
            if (isset($_POST["packages"]))
                $changed_fields['_packages_updated'] = true;
        }

        // =====================
        // EMAIL NOTIFICATION
        // =====================
        $email_template = cdp_getEmailTemplatesdg1i4(34);

        // Full, current shipment details (status / weight / carrier tracking /
        // ETA / items, no money) — re-sent on every edit, not a diff-only.
        require_once(__DIR__ . '/../../helpers/notify_placeholders.php');
        $pkg_ph = cdp_buildPackageNotifyPlaceholders($shipment_id, 'sea');

        if ($email_template) {

            // Build changed fields HTML rows
            $changed_fields_html = '';
            $row_alt = false;
            foreach ($changed_fields as $label => $diff) {
                if ($label === '_packages_updated') continue;
                $bg = $row_alt ? 'background:#f0f0f0;' : '';
                $changed_fields_html .= '
                <tr style="' . $bg . '">
                    <td width="35%" style="font-size:13px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;">' . htmlspecialchars($label) . '</td>
                    <td style="font-size:13px;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;">
                        <span style="color:#999999;text-decoration:line-through;">' . htmlspecialchars($diff['old']) . '</span>
                        &nbsp;&rarr;&nbsp;
                        <strong style="color:#1a1a1a;">' . htmlspecialchars($diff['new']) . '</strong>
                    </td>
                </tr>';
                $row_alt = !$row_alt;
            }

            if (empty($changed_fields_html)) {
                $changed_fields_html = '
                <tr>
                    <td colspan="2" style="font-size:13px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;">No tracked field changes detected. Please review your shipment for full details.</td>
                </tr>';
            }

            // Build packages section HTML (only if packages were updated)
            $packages_section_html = '';
            if (isset($changed_fields['_packages_updated']) && isset($packages) && is_array($packages) && count($packages) > 0) {
                $pkg_rows = '';
                foreach ($packages as $index => $package) {
                    // No monetary values (declared value) in customer notifications.
                    $pkg_rows .= ($index + 1) . ". " . htmlspecialchars($package->description) . "\n" .
                        "   Weight: " . $package->weight . " lbs\n" .
                        "   Dimensions: " . $package->length . " x " . $package->width . " x " . $package->height . " inches\n\n";
                }

                $packages_section_html = '
                <p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;font-family:Roboto,Arial,Helvetica,sans-serif;">Package Breakdown</p>
                <table border="0" cellpadding="8" cellspacing="0" width="100%" style="background:#fff8f0;border-left:3px solid #f5a800;border-radius:4px;margin-bottom:20px;">
                  <tr>
                    <td style="font-size:13px;color:#444444;line-height:22px;font-family:Roboto,Arial,Helvetica,sans-serif;white-space:pre-line;">' . trim($pkg_rows) . '</td>
                  </tr>
                </table>';
            }

            // Always append the full current shipment details so the customer
            // sees the complete, up-to-date picture (not just the diff).
            if ($pkg_ph['[SHIPMENT_DETAILS]'] !== '') {
                $packages_section_html .=
                    '<p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;font-family:Roboto,Arial,Helvetica,sans-serif;">Updated Shipment Details</p>'
                    . $pkg_ph['[SHIPMENT_DETAILS]'];
            }

            $body = str_replace(
                [
                    '[NAME]',
                    '[TRACKING]',
                    '[CHANGED_FIELDS]',
                    '[PACKAGES_SECTION]',
                    '[TOTAL_AMOUNT]',
                    '[URL]',
                    '[URL_LINK]',
                    '[SITE_NAME]',
                    '[URL_SHIP]'
                ],
                [
                    cdp_nameWithLocker($sender_data),
                    $fullshipment,
                    $changed_fields_html,
                    $packages_section_html,
                    '', // [TOTAL_AMOUNT] — no monetary values in customer notifications
                    $msite_url,
                    $mlogo,
                    $msnames,
                    $app_url
                ],
                $email_template->body
            );

            $newbody = cdp_cleanOutx($body);
            $subject = 'Shipment Update - ' . $fullshipment;

            if ($check_mail == 'PHP') {

                $to     = $sender_data->email;
                $from   = $site_email;
                $header = "MIME-Version: 1.0\r\n";
                $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                $header .= "From: " . $from . " \r\n";
                try {
                    mail($to, $subject, $newbody, $header);
                } catch (Exception $e) {}

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
                $mail->addCC($site_email, $lang['messagesform109']);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = "<html><body><p>{$newbody}</p></body></html><br />";

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    )
                );

                try {
                    $mail->Send();
                } catch (Exception $e) {}
            }
        }

        // =====================
        // WHATSAPP NOTIFICATION
        // =====================
        if (!empty($sender_data->phone) && (int)($dataShipment['notify_whatsapp_sender']) === 1) {
            try {

                // Build changed lines for WhatsApp (old ➜ new; money excluded).
                $wa_changes = '';
                foreach ($changed_fields as $label => $diff) {
                    if ($label === '_packages_updated') {
                        $wa_changes .= "• Package details updated\n";
                        continue;
                    }
                    if ($label === 'Order Total') continue; // money is excluded from sender alerts
                    $wa_changes .= "• {$label}: " . ($diff['old'] ?? '') . " ➜ " . ($diff['new'] ?? '') . "\n";
                }

                // Full current details (no money, no dimensions). Status is shown
                // distinctly as Current Status, so it is not repeated here.
                $wa_details = '';
                if ($pkg_ph['[WEIGHT]'] !== 'N/A') {
                    $wa_details .= "• Total Weight: " . $pkg_ph['[WEIGHT]'] . "\n";
                }
                if ($pkg_ph['[ITEMS]'] !== 'N/A') {
                    $wa_details .= "• Items:\n";
                    foreach (explode("\n", $pkg_ph['[ITEMS]']) as $il) {
                        $wa_details .= "   - {$il}\n";
                    }
                }
                if ($pkg_ph['[POSTAL_TRACKING]'] !== 'N/A') {
                    $wa_details .= "• Carrier Tracking #: *" . $pkg_ph['[POSTAL_TRACKING]'] . "*\n";
                }
                if ($pkg_ph['[ETA]'] !== 'N/A') {
                    $wa_details .= "• Estimated Arrival: " . $pkg_ph['[ETA]'] . "\n";
                }

                // Send only when something actually changed.
                if (!empty($wa_changes)) {
                    $wa_status_line = ($pkg_ph['[STATUS]'] !== 'N/A') ? "\n*Current Status:* {$pkg_ph['[STATUS]']}\n" : '';
                    $whatsapp_body =
                        "Hello " . cdp_nameWithLocker($sender_data) . ",\n\n" .
                        "Your shipment *{$fullshipment}* has been updated.\n\n" .
                        "*What Changed:*\n{$wa_changes}" .
                        $wa_status_line .
                        ($wa_details !== '' ? "\n*Updated Details*\n" . $wa_details : '') .
                        "\nTrack your shipment at any time:\n" .
                        $app_url . "\n\n" .
                        "Thank you, *{$msnames}* Team";

                    sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);
                }

            } catch (Exception $e) {
                error_log('Error sending WhatsApp update notification: ' . $e->getMessage());
            }
        }

        // =====================
        // SMS NOTIFICATION (unchanged)
        // =====================
        $name_status  = cdp_getCourierstatusApi((int)$_POST["status_courier"]);
        $add_status   = $name_status->mod_style;

        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;

        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) {
            error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
        }

        $messages[] = $lang['message_ajax_success_add_update'];

        cdp_activityLog([
            'module'       => 'packages',
            'verb'         => 'update',
            'entity_type'  => 'package',
            'entity_id'    => (int) $shipment_id,
            'entity_label' => $fullshipment,
            'status_id'    => (int) $_POST['status_courier'],
            'status_name'  => $add_status,
            'summary'      => 'Edited package ' . $fullshipment . ' — status ' . $add_status,
            'meta'         => [
                'estimated_eta' => cdp_sanitize($_POST['estimated_eta'] ?? ''),
                'total_order'   => (float) ($total_envio ?? 0),
            ],
        ]);

    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
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