<?php
ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");

$user   = new User;
$core   = new Core;
$errors = array();

// =======================
// VALIDATIONS
// =======================
if (empty($_POST['sender_id']))            $errors['sender_id']            = $lang['validate_field_ajax144'];
if (empty($_POST['sender_address_id']))    $errors['sender_address_id']    = $lang['validate_field_ajax145'];
if (empty($_POST['recipient_id']))         $errors['recipient_id']         = $lang['validate_field_ajax146'];
if (empty($_POST['recipient_address_id'])) $errors['recipient_address_id'] = $lang['validate_field_ajax147'];
if (empty($_POST['agency']))               $errors['agency']               = $lang['validate_field_ajax148'];
if (empty($_POST['origin_off']))           $errors['origin_off']           = $lang['validate_field_ajax149'];
if (empty($_POST['order_no']))             $errors['order_no']             = $lang['validate_field_ajax150'];
if (empty($_POST['order_package']))        $errors['order_package']        = $lang['validate_field_ajax152'];
if (empty($_POST['order_courier']))        $errors['order_courier']        = $lang['validate_field_ajax153'];
if (empty($_POST['order_deli_time']))      $errors['order_deli_time']      = $lang['validate_field_ajax155'];
if (empty($_POST['status_courier']))       $errors['status_courier']       = $lang['validate_field_ajax157'];
if (empty($_POST['order_payment_method'])) $errors['order_payment_method'] = $lang['validate_field_ajax158'];

$_POST["order_service_options"] = 8;

// -------------------------------------------------------------------------
// Package-item validation (weight XOR custom price). Runs BEFORE the existing
// items are deleted/re-inserted so an invalid payload can never wipe the rows.
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

        if ($vdesc === '')                       { $errors["pkg_desc_$vi"] = "Row $rown: description is required."; }
        if ($vqty <= 0)                          { $errors["pkg_qty_$vi"]  = "Row $rown: quantity must be greater than 0."; }
        if ($vweight > 0 && $vcustom > 0)        { $errors["pkg_excl_$vi"] = "Row $rown: use either weight OR custom price, not both."; }
        // Pricing is optional here too — items may be priced incrementally by different staff.
    }
}

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $site_email = $settings->email_address;
    $check_mail = $settings->mailer;
    $names_info = $settings->smtp_names;
    $mlogo      = $settings->logo;
    $msite_url  = $settings->site_url;
    $msnames    = $settings->site_name;
    $smtphoste  = $settings->smtp_host;
    $smtpuser   = $settings->smtp_user;
    $smtppass   = $settings->smtp_password;
    $smtpport   = $settings->smtp_port;
    $smtpsecure = $settings->smtp_secure;

    $templatessender   = 4;
    $templatesreceiver = 3;

    $min_cost_tax          = (float)$core->min_cost_tax;
    $min_cost_declared_tax = (float)$core->min_cost_declared_tax;

    $sale_date = date("Y-m-d H:i:s");

    $days            = 0;
    $payment_methods = null;
    if (!empty($_POST["order_payment_method"])) {
        $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
        if ($payment_methods && isset($payment_methods->days)) {
            $days = (int)$payment_methods->days;
        }
    }
    $due_date       = cdp_sumardias($sale_date, $days);
    $status_invoice = ($days === 0) ? 1 : 2;
    $tariff_mode    = isset($_POST['tariff_mode']) ? 1 : 0;

    $shipment_id = cdp_sanitize(intval($_POST["order_id"]));

    // =======================
    // SNAPSHOT OLD VALUES BEFORE UPDATE
    // =======================
    $old_shipment = cdp_getCourier($shipment_id);

    $old_status_label   = '';
    $old_courier_name   = '';
    $old_service_type   = '';
    $old_delivery_time  = '';
    $old_eta            = '';
    $old_total          = 0;

    if ($old_shipment) {
        $db_snap = new Conexion;

        $old_status_obj   = cdp_getCourierstatusApi((int)$old_shipment->status_courier);
        $old_status_label = $old_status_obj ? $old_status_obj->mod_style : 'N/A';

        $db_snap->cdp_query("SELECT name_com FROM cdb_courier_com WHERE id = :id LIMIT 1");
        $db_snap->bind(':id', (int)$old_shipment->order_courier);
        $db_snap->cdp_execute();
        $r = $db_snap->cdp_registro();
        $old_courier_name = $r ? $r->name_com : 'N/A';

        $db_snap->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id = 8");
        $db_snap->bind(':id', (int) $old_shipment->order_service_options);
        $db_snap->cdp_execute();
        $r = $db_snap->cdp_registro();
        $old_service_type = $r ? $r->ship_mode : 'N/A';

        $db_snap->cdp_query("SELECT delitime FROM cdb_delivery_time WHERE id = :id LIMIT 1");
        $db_snap->bind(':id', (int)$old_shipment->order_deli_time);
        $db_snap->cdp_execute();
        $r = $db_snap->cdp_registro();
        $old_delivery_time = $r ? $r->delitime : 'N/A';

        $db_snap->cdp_query("SELECT * FROM cdb_package_tracking_number WHERE order_id = :id LIMIT 1");
        $db_snap->bind(':id', $shipment_id);
        $db_snap->cdp_execute();
        $r = $db_snap->cdp_registro();
        $old_eta = $r ? $r->estimated_eta : '';
        $old_tracking = $r ? $r->tracking_number : '';

        $db_snap->cdp_query("SELECT total_order FROM cdb_courier_totals WHERE order_id = :id LIMIT 1");
        $db_snap->bind(':id', $shipment_id);
        $db_snap->cdp_execute();
        $r = $db_snap->cdp_registro();
        $old_total = $r ? (float)$r->total_order : 0;
    }

    // =======================
    // UPDATE SHIPMENT HEADER
    // =======================
    $dataShipment = array(
        'order_id'              => $shipment_id,
        'driver_id'             => cdp_sanitize(intval($_POST["driver_id"])),
        'sender_id'             => cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id'          => cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id'     => cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id'  => cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency'                => cdp_sanitize(intval($_POST["agency"])),
        'origin_off'            => cdp_sanitize(intval($_POST["origin_off"])),
        'order_package'         => cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category'   => cdp_sanitize(intval($_POST["order_item_category"] ?? 0)),
        'order_courier'         => cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) ($old_shipment->order_service_options ?? 0),
        'order_deli_time'       => cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method'  => cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier'        => cdp_sanitize(intval($_POST["status_courier"])),
        'due_date'              => $due_date,
        'status_invoice'        => $status_invoice,
        'manual_tariff'         => $tariff_mode,
        'courier_notes'         => cdp_sanitize($_POST["courier_notes"]),
    );

    $updateShip = cdp_updateCourierShipment($dataShipment);
    $messages   = array();

    if ($updateShip) {

        // =======================
        // PACKAGES + TOTALS
        // =======================
        $sum_total_flete         = 0.0;
        $sum_weight_real         = 0.0;
        $sum_weight_vol          = 0.0;
        $sum_declared            = 0.0;
        $sum_fixed               = 0.0;
        $total_impuesto          = 0.0;
        $total_descuento         = 0.0;
        $total_seguro            = 0.0;
        $total_peso              = 0.0;
        $total_impuesto_aduanero = 0.0;
        $total_valor_declarado   = 0.0;
        $total_envio             = 0.0;

        $price_lb           = isset($_POST["price_lb"])           ? floatval($_POST["price_lb"])           : 0;
        $insured_value      = isset($_POST["insured_value"])      ? floatval($_POST["insured_value"])      : 0;
        $insurance_value    = isset($_POST["insurance_value"])    ? floatval($_POST["insurance_value"])    : 0;
        $reexpedicion_value = isset($_POST["reexpedicion_value"]) ? floatval($_POST["reexpedicion_value"]) : 0;
        $discount_value     = isset($_POST["discount_value"])     ? floatval($_POST["discount_value"])     : 0;
        $tax_value          = isset($_POST["tax_value"])          ? floatval($_POST["tax_value"])          : 0;
        $declared_value_tax = isset($_POST["declared_value_tax"]) ? floatval($_POST["declared_value_tax"]) : 0;
        $tariffs_value      = isset($_POST["tariffs_value"])      ? floatval($_POST["tariffs_value"])      : 0;
        $core_meter         = isset($_POST["meter"])              ? floatval($_POST["meter"])              : floatval($settings->meter ?? 0);

        if (isset($_POST["packages"])) {

            cdp_deleteCourierPackages($shipment_id);
            $packages = json_decode($_POST['packages']);

            $base_packages = 0.0; // USD base = Σ(weight*qty*rate) + Σ(custom_price*qty)

            if ($packages && is_array($packages)) {
                foreach ($packages as $package) {
                    $qty      = isset($package->qty)            ? floatval($package->qty)            : 1;
                    if ($qty <= 0) $qty = 1;

                    // New pricing model: weight XOR custom price. Force-clear the
                    // unused field so a row can never carry both.
                    $weight       = isset($package->weight)       ? floatval($package->weight)       : 0;
                    $custom_price = isset($package->custom_price) ? floatval($package->custom_price) : 0;
                    $use_custom   = isset($package->use_custom_price)
                        ? (int) $package->use_custom_price
                        : ($custom_price > 0 ? 1 : 0);
                    if ($use_custom) { $weight = 0.0; } else { $custom_price = 0.0; }

                    // Dimensions retired; keep columns zero-filled (guarded).
                    $length   = isset($package->length)         ? floatval($package->length)         : 0;
                    $width    = isset($package->width)          ? floatval($package->width)          : 0;
                    $height   = isset($package->height)         ? floatval($package->height)         : 0;
                    $declared = isset($package->declared_value) ? floatval($package->declared_value) : 0;
                    $fixed    = isset($package->fixed_value)    ? floatval($package->fixed_value)    : 0;
                    $descr    = isset($package->description)    ? trim($package->description)        : '';

                    cdp_insertCourierShipmentPackages(array(
                        'order_id'       => $shipment_id,
                        'qty'            => $qty,
                        'description'    => $descr,
                        'length'         => $length,
                        'width'          => $width,
                        'height'         => $height,
                        'weight'         => $weight,
                        'declared_value' => $declared,
                        'fixed_value'    => $fixed,
                        'custom_price'   => $use_custom ? $custom_price : null,
                    ));

                    // Per-item USD line total (mirrors computeLineTotal in courier_edit.js).
                    if ($use_custom) {
                        $base_packages += $custom_price * $qty;
                    } else {
                        $base_packages += $weight * $qty * $price_lb;
                    }

                    $sum_weight_real += $weight   * $qty;
                    $sum_declared    += $declared * $qty;
                    $sum_fixed       += $fixed    * $qty;
                }
            }

            $sum_weight_real = round($sum_weight_real, 2);
            $sum_weight_vol  = 0.0; // volumetric retired
            $sum_declared    = round($sum_declared, 2);
            $sum_fixed       = round($sum_fixed, 2);
            $base_packages   = round($base_packages, 2);

            $calculate_weight = $sum_weight_real; // chargeable = real weight
            $total_peso       = $sum_weight_real;

            // Original total weight: actual package weight entered by staff
            // (display/record only; tariff still uses the summed item weight).
            // Falls back to the summed weight when left blank.
            $package_total_weight = (isset($_POST['package_total_weight']) && $_POST['package_total_weight'] !== '')
                ? floatval($_POST['package_total_weight'])
                : $total_peso;

            if ($tariff_mode == 0) {
                $distance_miles_edit = (float)($_POST['distance_miles'] ?? 0);
                $order_svc_edit      = (int)($_POST['order_service_options'] ?? 0);
                $meter_edit          = (float)($settings->meter ?? $core_meter);
                $tariffEdit = cdp_calculateTariffServerSide(
                    intval($_POST['sender_id']),
                    intval($_POST['sender_address_id']),
                    intval($_POST['recipient_id']),
                    intval($_POST['recipient_address_id']),
                    $order_svc_edit,
                    $packages,
                    $distance_miles_edit,
                    $meter_edit
                );
                if ($tariffEdit !== null) {
                    $sum_total_flete = $tariffEdit['total_tarifa'];
                    $price_lb        = $tariffEdit['price_lb_derived'];
                } else {
                    $sum_total_flete = $base_packages;
                }
            } else {
                // Manual mode (default): per-item USD base = weight*rate + custom price.
                $sum_total_flete = $base_packages;
            }

            if ($sum_total_flete > $min_cost_tax) {
                $total_impuesto = $sum_total_flete * $tax_value / 100;
            }
            if ($sum_declared > $min_cost_declared_tax) {
                $total_valor_declarado = $sum_declared * $declared_value_tax / 100;
            }

            $discount_type = (isset($_POST['discount_type']) && $_POST['discount_type'] === 'amount') ? 'amount' : 'percent';
            $total_descuento = ($discount_type === 'amount')
                ? $discount_value
                : $sum_total_flete * $discount_value / 100;
            if ($total_descuento > $sum_total_flete || $discount_value < 0) { $total_descuento = 0; }

            $total_seguro            = $insured_value * $insurance_value / 100;
            $total_impuesto_aduanero = $total_peso * $tariffs_value / 100;

            $total_envio = $sum_total_flete
                           - $total_descuento
                           + $total_seguro
                           + $total_impuesto
                           + $total_impuesto_aduanero
                           + $total_valor_declarado
                           + $sum_fixed
                           + $reexpedicion_value;
            $total_envio = round($total_envio, 2);
        }

        // =======================
        // UPDATE TOTALS
        // =======================
        cdp_updateCourierShipmentTotals(array(
            'order_id'                   => $shipment_id,
            'value_weight'               => floatval($price_lb),
            'sub_total'                  => floatval($sum_total_flete),
            'tax_discount'               => floatval($discount_value),
            'total_insured_value'        => floatval($insured_value),
            'tax_insurance_value'        => floatval($insurance_value),
            'tax_custom_tariffis_value'  => floatval($tariffs_value),
            'tax_value'                  => floatval($tax_value),
            'declared_value'             => floatval($declared_value_tax),
            'total_reexp'                => floatval($reexpedicion_value),
            'total_declared_value'       => floatval($total_valor_declarado),
            'total_fixed_value'          => floatval($sum_fixed),
            'total_tax_discount'         => floatval($total_descuento),
            'total_tax_insurance'        => floatval($total_seguro),
            'total_tax_custom_tariffis'  => floatval($total_impuesto_aduanero),
            'total_tax'                  => floatval($total_impuesto),
            'total_weight'               => floatval($package_total_weight ?? $total_peso),
            'total_order'                => floatval($total_envio),
        ));

        // Persist the discount mode (percent vs flat amount) in its own column.
        $db_dt = new Conexion;
        $db_dt->cdp_query("UPDATE cdb_add_order SET discount_type = :dt WHERE order_id = :oid");
        $db_dt->bind(':dt', $discount_type ?? 'percent');
        $db_dt->bind(':oid', $shipment_id);
        $db_dt->cdp_execute();

        // =======================
        // TRACKING NUMBER / ETA
        // =======================
        $tracking_number = cdp_sanitize($_POST['tracking_number']);
        $estimated_eta   = cdp_sanitize($_POST['estimated_eta']);
        cdp_updatePackageTracking($shipment_id, $_SESSION['userid'], $tracking_number, $estimated_eta);

        // =======================
        // FILES
        // =======================
        $shipment    = cdp_getCourier($shipment_id);
        $order_track = $shipment->order_prefix . $shipment->order_no;

        if (isset($_FILES['filesMultiple']) &&
            count($_FILES['filesMultiple']['name']) > 0 &&
            $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir       = "../../order_files/";
            $deleted_file_ids = array();
            if (!empty($_POST['deleted_file_ids'])) {
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
                    cdp_insertOrdersFiles($shipment_id, "order_files/" . $image_name, $image_name, date("Y-m-d H:i:s"), '0', $imageFileType);
                }
            }
        }

        // =======================
        // HISTORY
        // =======================
        cdp_insertCourierShipmentUserHistory(array(
            'user_id'      => $_SESSION['userid'],
            'order_id'     => $shipment_id,
            'order_track'  => $order_track,
            'action'       => $lang['notification_shipment7'],
            'date_history' => date("Y-m-d H:i:s"),
        ));

        // =======================
        // ADDRESSES — delete and re-insert
        // =======================
        cdp_deleteCourierAddress($order_track);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $final_sender_country_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '')
            : '';
        $final_sender_state_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->state, 'cdp_getState', $sender_address_data->legacy_state ?? '')
            : '';
        $final_sender_city_name = $sender_address_data
            ? cdp_resolveAddressName($sender_address_data->city, 'cdp_getCity', $sender_address_data->legacy_city ?? '')
            : '';

        $recipient_type = cdp_sanitize($_POST['recipient_type'] ?? 'recipient');
        if ($recipient_type === 'user') {
            $recipient_address_data = cdp_getSenderAddress(intval($_POST["recipient_address_id"]));
        } else {
            $recipient_address_data = cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));
        }

        $final_recipient_country_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->country, 'cdp_getCountry', $recipient_address_data->legacy_country ?? '')
            : '';
        $final_recipient_state_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->state, 'cdp_getState', $recipient_address_data->legacy_state ?? '')
            : '';
        $final_recipient_city_name = $recipient_address_data
            ? cdp_resolveAddressName($recipient_address_data->city, 'cdp_getCity', $recipient_address_data->legacy_city ?? '')
            : '';

        cdp_insertCourierShipmentAddresses(array(
            'order_id'           => $shipment_id,
            'order_track'        => $order_track,
            'sender_country'     => $final_sender_country_name,
            'sender_state'       => $final_sender_state_name,
            'sender_city'        => $final_sender_city_name,
            'sender_zip_code'    => $sender_address_data ? ($sender_address_data->zip_code ?? '') : '',
            'sender_address'     => $sender_address_data ? ($sender_address_data->address ?? '') : '',
            'recipient_country'  => $final_recipient_country_name,
            'recipient_state'    => $final_recipient_state_name,
            'recipient_city'     => $final_recipient_city_name,
            'recipient_zip_code' => $recipient_address_data ? ($recipient_address_data->zip_code ?? '') : '',
            'recipient_address'  => $recipient_address_data ? ($recipient_address_data->address ?? '') : '',
        ));

        // =======================
        // RESOLVE NEW LABELS + BUILD DIFF
        // =======================
        $sender_data   = cdp_getSenderCourier(intval($_POST["sender_id"]));
        // recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
        $receiver_data = ((cdp_sanitize($_POST['recipient_type'] ?? 'recipient')) === 'user')
            ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
            : cdp_getRecipientCourier(intval($_POST["recipient_id"]));
        $fullshipment  = $shipment->order_prefix . $shipment->order_no;
        $name_status   = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status    = $name_status->mod_style;
        $app_url       = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;

        $db_new = new Conexion;

        $db_new->cdp_query("SELECT name_com FROM cdb_courier_com WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)$_POST["order_courier"]);
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_courier_name = $r ? $r->name_com : 'N/A';

        $db_new->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)($_POST["order_service_options"] ?? 0));
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_service_type = $r ? $r->ship_mode : 'N/A';

        $db_new->cdp_query("SELECT delitime FROM cdb_delivery_time WHERE id = :id LIMIT 1");
        $db_new->bind(':id', (int)$_POST["order_deli_time"]);
        $db_new->cdp_execute();
        $r = $db_new->cdp_registro();
        $new_delivery_time = $r ? $r->delitime : 'N/A';

        $new_status_label = $add_status;
        $new_eta          = $estimated_eta;
        $new_tracking      = $tracking_number;
        $new_total        = $total_envio;

        // Build changed fields array
        $changed_fields = [];

        if ($old_shipment) {
            if ((int)$old_shipment->status_courier !== (int)$_POST["status_courier"])
                $changed_fields['Shipment Status'] = ['old' => $old_status_label,  'new' => $new_status_label];

            if ((int)$old_shipment->order_courier !== (int)$_POST["order_courier"])
                $changed_fields['Courier'] = ['old' => $old_courier_name,  'new' => $new_courier_name];

            if ((int)$old_shipment->order_service_options !== (int)($_POST["order_service_options"] ?? 0))
                $changed_fields['Service Type'] = ['old' => $old_service_type,  'new' => $new_service_type];

            if ((int)$old_shipment->order_deli_time !== (int)$_POST["order_deli_time"])
                $changed_fields['Estimated Delivery'] = ['old' => $old_delivery_time, 'new' => $new_delivery_time];

            if (trim($old_eta) !== trim($new_eta) && !empty($new_eta))
                $changed_fields['ETA'] = ['old' => $old_eta ?: 'N/A', 'new' => $new_eta];
            
            if (trim($old_tracking) !== trim($new_tracking) && !empty($new_tracking))
                $changed_fields['Tracking Number'] = ['old' => $old_tracking ?: 'N/A', 'new' => $new_tracking];

            if (isset($_POST["packages"]))
                $changed_fields['_packages_updated'] = true;

            // Order Total intentionally NOT tracked here — customer notifications
            // must never carry monetary values.
        }

        // Full, current package details (status, original weight, carrier tracking,
        // ETA, items + quantities — no money) re-sent on every update under the
        // "Shipment Update" header.
        require_once(__DIR__ . '/../../helpers/notify_placeholders.php');
        $enriched_ph = cdp_buildPackageNotifyPlaceholders($shipment_id);

        // =======================
        // EMAIL — HELPER CLOSURE
        // =======================
        $sendShipmentEmail = function($recipient_obj, $recipient_name_label) use (
            $changed_fields, $packages, $total_envio, $enriched_ph,
            $fullshipment, $app_url, $msite_url, $mlogo, $msnames,
            $site_email, $check_mail, $names_info,
            $smtphoste, $smtpuser, $smtppass, $lang
        ) {
            if (!$recipient_obj || empty($recipient_obj->email)) return;

            $email_template = cdp_getEmailTemplatesdg1i4(36);
            if (!$email_template) return;

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
                    <td colspan="2" style="font-size:13px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;padding:8px;">Your shipment details have been reviewed and updated.</td>
                </tr>';
            }

            // Build packages section (only if packages were updated)
            $packages_section_html = '';
            if (isset($changed_fields['_packages_updated']) && isset($packages) && is_array($packages) && count($packages) > 0) {
                $pkg_rows = '';
                foreach ($packages as $index => $pkg) {
                    // No monetary values (custom price / declared value) in customer
                    // notifications — show description + weight only.
                    $pkg_weight = isset($pkg->weight) ? $pkg->weight : 0;
                    $pkg_rows .= ($index + 1) . ". " . htmlspecialchars($pkg->description ?? '') . "\n" .
                        ((float) $pkg_weight != 0 ? "   Weight: " . $pkg_weight . " lbs\n" : "") .
                        "\n";
                }
                $packages_section_html = '
                <p style="margin:0 0 8px 0;font-size:14px;font-weight:700;color:#1a1a1a;font-family:Roboto,Arial,Helvetica,sans-serif;">Package Breakdown</p>
                <table border="0" cellpadding="8" cellspacing="0" width="100%" style="background:#fff8f0;border-left:3px solid #f5a800;border-radius:4px;margin-bottom:20px;">
                  <tr>
                    <td style="font-size:13px;color:#444444;line-height:22px;font-family:Roboto,Arial,Helvetica,sans-serif;white-space:pre-line;">' . trim($pkg_rows) . '</td>
                  </tr>
                </table>';
            }

            // Always attach the full current details (re-sent on every update),
            // regardless of whether the package list itself changed. Rendered
            // through the existing [PACKAGES_SECTION] slot so no template edit is
            // needed.
            $packages_section_html .= $enriched_ph['[SHIPMENT_DETAILS]'];

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
                    $recipient_name_label,
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
                $header  = "MIME-Version: 1.0\r\n";
                $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                $header .= "From: " . $site_email . " \r\n";
                try { mail($recipient_obj->email, $subject, $newbody, $header); } catch (Exception $e) {}

            } elseif ($check_mail == 'SMTP') {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtphoste;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpuser;
                $mail->Password   = $smtppass;
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom($site_email, $names_info);
                $mail->addAddress($recipient_obj->email);
                $mail->addCC($site_email, $msnames);
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = "<html><body><p>{$newbody}</p></body></html><br />";
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                try { $mail->Send(); } catch (Exception $e) {}
            }
        };

        // Send email to sender
        $sendShipmentEmail(
            $sender_data,
            $sender_data->fname . ' ' . $sender_data->lname
        );

        // Send email to receiver
        $sendShipmentEmail(
            $receiver_data,
            $receiver_data ? ($receiver_data->fname . ' ' . $receiver_data->lname) : ''
        );

        // =======================
        // SMS
        // =======================
        $notify_sms_sender   = isset($_POST['notify_sms_sender'])   && $_POST['notify_sms_sender']   == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        try {
            $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
            sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
        } catch (Exception $e) { error_log('SMS sender error: ' . $e->getMessage()); }

        try {
            $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);
            sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
        } catch (Exception $e) { error_log('SMS receiver error: ' . $e->getMessage()); }

        if (!empty($sender_data->phone)) {
            try {
                // Plain-text "what changed" list for WhatsApp. Money excluded.
                $wa_changes_lines = array();
                if (isset($changed_fields) && is_array($changed_fields)) {
                    foreach ($changed_fields as $label_cf => $diff_cf) {
                        if ($label_cf === '_packages_updated') {
                            $wa_changes_lines[] = '• Package item details (weight / custom price) updated';
                            continue;
                        }
                        if ($label_cf === 'Order Total') {
                            continue; // money is excluded from sender alerts
                        }
                        $wa_changes_lines[] = '• ' . $label_cf . ': ' . ($diff_cf['old'] ?? '') . ' ➜ ' . ($diff_cf['new'] ?? '');
                    }
                }

                // Send only when something actually changed.
                if (!empty($wa_changes_lines)) {

                    // Re-send the full current details under the changes. Status is
                    // omitted here — template 13 already shows Current Status distinctly.
                    $wa_changes_lines[] = '';
                    $wa_changes_lines[] = '*Updated Details*';
                    if ($enriched_ph['[WEIGHT]'] !== 'N/A')          { $wa_changes_lines[] = '• Total Weight: ' . $enriched_ph['[WEIGHT]']; }
                    if ($enriched_ph['[ITEMS]'] !== 'N/A') {
                        $wa_changes_lines[] = '• Items:';
                        foreach (explode("\n", $enriched_ph['[ITEMS]']) as $il) { $wa_changes_lines[] = '   - ' . $il; }
                    }
                    if ($enriched_ph['[POSTAL_TRACKING]'] !== 'N/A') { $wa_changes_lines[] = '• Carrier Tracking #: *' . $enriched_ph['[POSTAL_TRACKING]'] . '*'; }
                    if ($enriched_ph['[ETA]'] !== 'N/A')             { $wa_changes_lines[] = '• Estimated Arrival: ' . $enriched_ph['[ETA]']; }

                    $tpl = getTemplateWhatsApp(13);
                    if ($tpl) {
                        $current_status_name = $add_status;
                        $order_date_fmt      = date('M d, Y', strtotime($shipment->order_datetime));
                        $recipient_name      = $receiver_data ? ($receiver_data->fname . ' ' . $receiver_data->lname) : 'N/A';
                        $origin              = $final_sender_city_name . ', ' . $final_sender_state_name;
                        $destination         = $final_recipient_city_name . ', ' . $final_recipient_state_name;

                        $whatsapp_body = str_replace(
                            ['[CUSTOMER_FULLNAME]','[TRACKING_NUMBER]','[PREV_STATUS]','[CURR_STATUS]','[CHANGES]','[ORD_DATE]','[RECIPIENT]','[ORIGIN]','[DESTINATION]','[APP_URL]','[COMPANY_NAME]'],
                            [ucfirst("{$sender_data->fname} {$sender_data->lname}"), $fullshipment, $old_status_label ?: $current_status_name, $current_status_name, implode("\n", $wa_changes_lines), $order_date_fmt, $recipient_name, $origin, $destination, $app_url, $settings->site_name],
                            $tpl->body
                        );
                        sendNotificationWhatsApp_v2($sender_data, $whatsapp_body);
                    }
                }
            } catch (Exception $e) { error_log('WhatsApp edit error: ' . $e->getMessage()); }
        }

        $messages[] = $lang['message_ajax_success_add_update'];

    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
} else {
    echo json_encode(['success' => true, 'messages' => $messages, 'shipment_id' => $shipment_id]);
}