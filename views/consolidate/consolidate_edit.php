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

require_once('helpers/phpmailer/class.phpmailer.php');
require_once('helpers/phpmailer/class.smtp.php');
require_once('ajax/notify_whatsapp/api_whatsapp_service_v2.php');

$userData = $user->cdp_getUserData();
if ($userData->userlevel == 1)
    cdp_redirect_to("login.php");


if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("consolidate_list.php");
}

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($data['data']->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$row_order = $data['data'];

$db->cdp_query("SELECT * FROM cdb_consolidate_detail WHERE consolidate_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

$db->cdp_query("SELECT estimated_eta FROM cdb_package_tracking_number WHERE order_id='" . $row_order->consolidate_id . "'");
$package_tracking_data = $db->cdp_registro();

if ($row_order->recipient_type == 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->receiver_id . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
}
$receiver_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->c_prefix . $row_order->c_no . "'");
$address_order = $db->cdp_registro();

$_before_edit = null;

if (isset($_POST["total_item"])) {

    // ─────────────────────────────────────────────────────────────────────
    // DANGEROUS-GOODS GUARD: this consolidation already has a fixed kind
    // (dangerous vs normal). Every package kept/added must match it — the edit
    // screen offers no kind toggle, so any mismatch is tampering and is refused
    // before a single row is rewritten.
    // ─────────────────────────────────────────────────────────────────────
    $consolidation_dg_edit = (int) ($row_order->is_dangerous_good ?? 0);
    $posted_ids_edit = array_values(array_filter(
        array_map('intval', (array) ($_POST['order_id'] ?? [])),
        function ($v) { return $v > 0; }
    ));
    $dg_mismatch = false;
    if (!empty($posted_ids_edit)) {
        $in_e = implode(',', $posted_ids_edit);
        $db_dg = new Conexion;
        $db_dg->cdp_query("SELECT COUNT(*) AS c FROM cdb_add_order WHERE order_id IN ($in_e) AND is_dangerous_good <> $consolidation_dg_edit");
        $db_dg->cdp_execute();
        $dg_row = $db_dg->cdp_registro();
        $dg_mismatch = ($dg_row && (int) $dg_row->c > 0);
    }

    if ($dg_mismatch) {
        $error_script = 'swal("Cannot consolidate", "This consolidation holds ' . ($consolidation_dg_edit ? 'dangerous goods' : 'normal goods') . ' only. One or more selected packages are the other kind and were not saved.", "error");';
    } else {

    // Capture before-edit state for change tracking
    try {
        $db_snap = new Conexion;
        $db_snap->cdp_query(
            "SELECT
                cp.sender_id,
                cp.receiver_id,
                cp.value_weight,
                cp.sub_total,
                cp.total_tax_insurance,
                cp.total_insured_value,
                cp.total_tax_discount,
                cp.total_tax_custom_tariffis,
                cp.total_tax,
                cp.total_order,
                cp.total_weight,
                cp.order_datetime,
                cp.agency,
                cp.origin_off,
                cp.order_package,
                cp.order_item_category,
                cp.order_courier,
                cp.order_service_options,
                cp.order_deli_time,
                cp.order_pay_mode,
                cp.status_courier,
                cp.driver_id,
                cp.seals_package,
                ptn.estimated_eta
             FROM cdb_consolidate cp
             LEFT JOIN cdb_package_tracking_number ptn ON ptn.order_id = cp.consolidate_id
             WHERE cp.consolidate_id = :cid
             LIMIT 1"
        );
        $db_snap->bind(':cid', (int) $_GET['id']);
        $db_snap->cdp_execute();
        $_before_edit = $db_snap->cdp_registro();
    } catch (Exception $e) {
        // Continue with empty before state
    }

    // ═════════════════════════════════════════════════════════════════════
    // MAIN UPDATE
    // ═════════════════════════════════════════════════════════════════════

    try {
        $db = new Conexion;

        $db->cdp_query("
            UPDATE cdb_consolidate SET
                order_pay_mode = :order_pay_mode,
                status_courier = :status_courier,
                driver_id = :driver_id,
                seals_package = :seals_package
            WHERE consolidate_id = :consolidate_id
        ");

        $db->bind(':order_pay_mode', cdp_sanitize($_POST["order_pay_mode"] ?? ''));
        $db->bind(':status_courier', cdp_sanitize($_POST["status_courier"] ?? ''));
        $db->bind(':driver_id', cdp_sanitize($_POST["driver_id"] ?? ''));
        $db->bind(':seals_package', cdp_sanitize($_POST["seals"] ?? ''));
        $db->bind(':consolidate_id', (int) $_GET['id']);

        // Capture main UPDATE result immediately
        $main_update_result = $db->cdp_execute();

    } catch (Exception $e) {
        $main_update_result = false;
    }

    // ═════════════════════════════════════════════════════════════════════
    // EMAIL NOTIFICATION
    // ═════════════════════════════════════════════════════════════════════

    try {
        if ($_before_edit) {
            
            // Payment method labels
            $db_lbl = new Conexion;
            $db_lbl->cdp_query("SELECT met_payment FROM cdb_met_payment WHERE id = :id");
            $db_lbl->bind(':id', (int) $_before_edit->order_pay_mode);
            $db_lbl->cdp_execute();
            $_old_pay = $db_lbl->cdp_registro();
            $old_pay_label = $_old_pay ? $_old_pay->met_payment : (string) $_before_edit->order_pay_mode;

            $db_lbl->cdp_query("SELECT met_payment FROM cdb_met_payment WHERE id = :id");
            $db_lbl->bind(':id', (int) cdp_sanitize($_POST["order_pay_mode"]));
            $db_lbl->cdp_execute();
            $_new_pay = $db_lbl->cdp_registro();
            $new_pay_label = $_new_pay ? $_new_pay->met_payment : cdp_sanitize($_POST["order_pay_mode"]);

            // Status labels
            $db_lbl->cdp_query("SELECT mod_style FROM cdb_styles WHERE id = :id");
            $db_lbl->bind(':id', (int) $_before_edit->status_courier);
            $db_lbl->cdp_execute();
            $_old_status = $db_lbl->cdp_registro();
            $old_status_label = $_old_status ? $_old_status->mod_style : (string) $_before_edit->status_courier;

            $db_lbl->cdp_query("SELECT mod_style FROM cdb_styles WHERE id = :id");
            $db_lbl->bind(':id', (int) cdp_sanitize($_POST["status_courier"]));
            $db_lbl->cdp_execute();
            $_new_status = $db_lbl->cdp_registro();
            $new_status_label = $_new_status ? $_new_status->mod_style : cdp_sanitize($_POST["status_courier"]);

            // Driver labels
            $db_lbl->cdp_query("SELECT fname, lname FROM cdb_users WHERE id = :id");
            $db_lbl->bind(':id', (int) $_before_edit->driver_id);
            $db_lbl->cdp_execute();
            $_old_drv = $db_lbl->cdp_registro();
            $old_driver_label = $_old_drv ? trim($_old_drv->fname . ' ' . $_old_drv->lname) : 'None';

            $db_lbl->cdp_query("SELECT fname, lname FROM cdb_users WHERE id = :id");
            $db_lbl->bind(':id', (int) cdp_sanitize($_POST["driver_id"]));
            $db_lbl->cdp_execute();
            $_new_drv = $db_lbl->cdp_registro();
            $new_driver_label = $_new_drv ? trim($_new_drv->fname . ' ' . $_new_drv->lname) : 'None';

            // Build diff array
            $diff_map = [
                'Payment Method' => [$old_pay_label, $new_pay_label],
                'Status' => [$old_status_label, $new_status_label],
                'Driver' => [$old_driver_label, $new_driver_label],
                'Seals / Package No.' => [(string) $_before_edit->seals_package, cdp_sanitize($_POST["seals"])],
                'Estimated ETA' => [(string) ($_before_edit->estimated_eta ?? ''), cdp_sanitize($_POST["estimated_eta"])],
            ];

            $changed_rows = [];
            foreach ($diff_map as $label => [$old_val, $new_val]) {
                if ($old_val !== $new_val) {
                    $changed_rows[] = [
                        'label' => $label,
                        'old' => $old_val ?: '—',
                        'new' => $new_val ?: '—',
                    ];
                }
            }

            if (!empty($changed_rows)) {

                $rows_html = '';
                foreach ($changed_rows as $cr) {
                    $rows_html .= '
                    <tr>
                        <td style="padding:8px 12px;font-size:13px;color:#555555;font-family:Roboto,Arial,Helvetica,sans-serif;border-bottom:1px solid #eeeeee;">
                            <strong>' . htmlspecialchars($cr['label']) . '</strong>
                        </td>
                        <td style="padding:8px 12px;font-size:13px;color:#c0392b;font-family:Roboto,Arial,Helvetica,sans-serif;border-bottom:1px solid #eeeeee;text-decoration:line-through;">
                            ' . htmlspecialchars($cr['old']) . '
                        </td>
                        <td style="padding:8px 12px;font-size:13px;color:#27ae60;font-family:Roboto,Arial,Helvetica,sans-serif;border-bottom:1px solid #eeeeee;">
                            → ' . htmlspecialchars($cr['new']) . '
                        </td>
                    </tr>';
                }

                $consolidate_number = $row_order->c_prefix . $row_order->c_no;

                $message_html = '
                <p style="margin:0 0 16px 0;font-size:14px;color:#444444;line-height:24px;font-family:Roboto,Arial,Helvetica,sans-serif;">
                    The following changes were made to consolidation
                    <strong style="color:#1a1a1a;">' . htmlspecialchars($consolidate_number) . '</strong>:
                </p>
                <table border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="border:1px solid #eeeeee;border-radius:4px;border-collapse:collapse;margin-bottom:16px;">
                    <thead>
                        <tr style="background:#f7f7f7;">
                            <th style="padding:8px 12px;font-size:12px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;text-align:left;border-bottom:2px solid #eeeeee;">Field</th>
                            <th style="padding:8px 12px;font-size:12px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;text-align:left;border-bottom:2px solid #eeeeee;">Previous Value</th>
                            <th style="padding:8px 12px;font-size:12px;color:#888888;font-family:Roboto,Arial,Helvetica,sans-serif;text-align:left;border-bottom:2px solid #eeeeee;">New Value</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows_html . '</tbody>
                </table>';

                // Re-send the full current consolidation details (status + the
                // shipments folded into it — no money), not just the diff.
                $consol_items_edit = '';
                $db_ci = new Conexion;
                $db_ci->cdp_query("SELECT o.order_prefix, o.order_no
                    FROM cdb_consolidate_detail d
                    INNER JOIN cdb_add_order o ON o.order_id = d.order_id
                    WHERE d.consolidate_id = :cid");
                $db_ci->bind(':cid', (int) $order_id);
                $ci_rows = $db_ci->cdp_registros();
                if ($ci_rows) {
                    foreach ($ci_rows as $cir) {
                        $consol_items_edit .= '<li>' . htmlspecialchars($cir->order_prefix . $cir->order_no, ENT_QUOTES, 'UTF-8') . '</li>';
                    }
                }
                $message_html .= '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:8px 0 16px 0;background:#fafafa;border-left:4px solid #f5a800;font-family:Roboto,Arial,Helvetica,sans-serif;"><tr><td style="padding:12px 18px;">'
                    . '<p style="margin:0 0 4px 0;font-size:10px;color:#aaaaaa;text-transform:uppercase;letter-spacing:1px;">Consolidation Status</p>'
                    . '<p style="margin:0 0 10px 0;font-size:15px;font-weight:700;color:#1a1a1a;">' . htmlspecialchars($new_status_label, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p style="margin:0 0 4px 0;font-size:10px;color:#aaaaaa;text-transform:uppercase;letter-spacing:1px;">Consolidated Shipments</p>'
                    . ($consol_items_edit !== '' ? '<ul style="margin:0;padding-left:18px;font-size:14px;color:#1a1a1a;">' . $consol_items_edit . '</ul>' : '<p style="margin:0;font-size:14px;">N/A</p>')
                    . '</td></tr></table>';

                $db_tpl = new Conexion;
                $db_tpl->cdp_query("SELECT * FROM cdb_email_templates WHERE id = 12 LIMIT 1");
                $db_tpl->cdp_execute();
                $email_tpl_12 = $db_tpl->cdp_registro();

                if ($email_tpl_12) {
                    $db_cfg = new Conexion;
                    $db_cfg->cdp_query("SELECT * FROM cdb_settings LIMIT 1");
                    $db_cfg->cdp_execute();
                    $mail_cfg = $db_cfg->cdp_registro();

                    $db_sender_email = new Conexion;
                    $db_sender_email->cdp_query("SELECT fname, lname, email, locker FROM cdb_users WHERE id = :id LIMIT 1");
                    $db_sender_email->bind(':id', (int) $_before_edit->sender_id);
                    $db_sender_email->cdp_execute();
                    $email_recipient_user = $db_sender_email->cdp_registro();

                    if ($email_recipient_user && !empty($email_recipient_user->email)) {
                        
                        $sender_full_name = cdp_nameWithLocker($email_recipient_user);

                        $email_body_12 = str_replace(
                            ['[SITE_NAME]', '[NAME]', '[MESSAGE]', '[URL]'],
                            [
                                $mail_cfg->site_name,
                                htmlspecialchars($sender_full_name),
                                $message_html,
                                rtrim($mail_cfg->site_url, '/'),
                            ],
                            $email_tpl_12->body
                        );

                        $email_body_12 = cdp_cleanOutx($email_body_12);
                        $edit_subject = 'Consolidation Updated: ' . $consolidate_number;

                        if ($mail_cfg->mailer === 'PHP') {
                            $mail_headers = "MIME-Version: 1.0\r\n";
                            $mail_headers .= "Content-type: text/html; charset=UTF-8\r\n";
                            $mail_headers .= "From: " . $mail_cfg->site_email . "\r\n";
                            mail($email_recipient_user->email, $edit_subject, $email_body_12, $mail_headers);

                        } elseif ($mail_cfg->mailer === 'SMTP') {
                            $edit_mail = new PHPMailer();
                            $edit_mail->IsSMTP();
                            $edit_mail->SMTPAuth = true;
                            $edit_mail->Port = $mail_cfg->smtp_port;
                            $edit_mail->IsHTML(true);
                            $edit_mail->CharSet = 'utf-8';
                            $edit_mail->Host = $mail_cfg->smtp_host;
                            $edit_mail->Username = $mail_cfg->smtp_user;
                            $edit_mail->Password = $mail_cfg->smtp_password;
                            $edit_mail->From = $mail_cfg->site_email;
                            $edit_mail->FromName = $mail_cfg->smtp_names;
                            $edit_mail->AddAddress($email_recipient_user->email);
                            $edit_mail->Subject = $edit_subject;
                            $edit_mail->Body = '<html><body>' . $email_body_12 . '</body></html>';
                            $edit_mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true,
                                ],
                            ];
                            $edit_mail->Send();
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('consolidate_edit.php – email notification error: ' . $e->getMessage());
    }

    $order_id = $row_order->consolidate_id;

    // ═════════════════════════════════════════════════════════════════════
    // ETA UPDATE
    // ═════════════════════════════════════════════════════════════════════

    $db->cdp_query("UPDATE cdb_package_tracking_number SET estimated_eta = :estimated_eta WHERE order_id = :order_id");
    $db->bind(':estimated_eta', cdp_sanitize($_POST["estimated_eta"]));
    $db->bind(':order_id', $order_id);
    $db->cdp_execute();

    // ═════════════════════════════════════════════════════════════════════
    // DELETE AND INSERT DETAIL ROWS
    // ═════════════════════════════════════════════════════════════════════

    // Snapshot BEFORE rewriting details: which packages were already in the
    // consolidation, and which are still eligible for alerts (their PRIOR own
    // status doesn't put them out of the consolidation). Needed because the
    // status-32 carve-out below mutates package state before the fan-out runs.
    $db->cdp_query("SELECT order_id FROM cdb_consolidate_detail WHERE consolidate_id = :cid_wa");
    $db->bind(':cid_wa', (int) $order_id);
    $previous_detail_ids_wa = array_map(function ($r) { return (int) $r->order_id; }, $db->cdp_registros());

    $posted_ids_wa = array_map('intval', (array) ($_POST['order_id'] ?? array()));
    $prior_eligible_wa = array();
    if (!empty($posted_ids_wa)) {
        $in_wa = implode(',', $posted_ids_wa);
        $db->cdp_query("SELECT order_id, status_courier FROM cdb_add_order WHERE order_id IN ({$in_wa})");
        foreach ($db->cdp_registros() as $r_wa) {
            if (!in_array((int) $r_wa->status_courier, array(8, 15, 16, 21, 27, 32), true)) {
                $prior_eligible_wa[] = (int) $r_wa->order_id;
            }
        }
    }

    $db->cdp_query("DELETE FROM cdb_consolidate_detail WHERE consolidate_id='" . $order_id . "'");
    $db->cdp_execute();

    for ($count = 0; $count < $_POST["total_item"]; $count++) {

        $db->cdp_query("
            INSERT INTO cdb_consolidate_detail 
            (consolidate_id, order_id, order_prefix, order_no, weight, weight_vol, length, width, height)
            VALUES 
            (:consolidate_id, :order_id, :order_prefix, :order_no, :weight, :weight_vol, :length, :width, :height)
        ");

        $db->bind(':consolidate_id', $order_id);
        $db->bind(':order_prefix', cdp_sanitize($_POST["prefix"][$count]));
        $db->bind(':order_no', cdp_sanitize($_POST["order_no_item"][$count]));
        $db->bind(':weight', cdp_sanitize($_POST["weight"][$count]));
        $db->bind(':length', cdp_sanitize($_POST["length"][$count]));
        $db->bind(':width', cdp_sanitize($_POST["width"][$count]));
        $db->bind(':height', cdp_sanitize($_POST["height"][$count]));
        $db->bind(':weight_vol', cdp_sanitize($_POST["weight_vol"][$count]));
        $db->bind(':order_id', cdp_sanitize($_POST["order_id"][$count]));

        $db->cdp_execute();

        // Mark as consolidate
        $db->cdp_query("UPDATE cdb_add_order SET is_consolidate=:is_consolidate WHERE order_id=:order_id");
        $db->bind(':order_id', cdp_sanitize($_POST["order_id"][$count]));
        $db->bind(':is_consolidate', 1);
        $db->cdp_execute();

        if ($_POST['status_courier'] == 32) {
            $db->cdp_query("UPDATE cdb_add_order SET status_courier = '32', is_consolidate = '0' WHERE order_no = :order_no");
            $db->bind(':order_no', cdp_sanitize($_POST["order_no_item"][$count]));
            $db->cdp_execute();
        }

        if ($_POST['status_courier'] == 33) {
            $db->cdp_query("UPDATE cdb_add_order SET status_courier = '33', is_consolidate = '0' WHERE order_no = :order_no");
            $db->bind(':order_no', cdp_sanitize($_POST["order_no_item"][$count]));
            $db->cdp_execute();
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // WhatsApp fan-out — only when something actually changed.
    //  - status changed: alert every still-eligible package owner (prior-state
    //    filtered, so a 32 "Ready for PickUp" transition still notifies)
    //  - status unchanged: alert only the owners of newly added packages
    // ═════════════════════════════════════════════════════════════════════
    $new_status_id_wa = intval(cdp_sanitize($_POST["status_courier"] ?? 0));
    $old_status_id_wa = isset($_before_edit->status_courier) ? (int) $_before_edit->status_courier : null;
    $added_ids_wa = array_values(array_diff($posted_ids_wa, $previous_detail_ids_wa));
    $consolidation_tracking_wa = $row_order->c_prefix . $row_order->c_no;
    $new_status_label_wa = cdp_wa_lookupName('cdb_styles', 'mod_style', $new_status_id_wa);

    if ($new_status_id_wa > 0 && $old_status_id_wa !== null && $new_status_id_wa !== $old_status_id_wa) {
        cdp_notifyConsolidationPackageSenders('consolidate', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, $prior_eligible_wa, false);
    } elseif (!empty($added_ids_wa)) {
        cdp_notifyConsolidationPackageSenders('consolidate', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, array_values(array_intersect($added_ids_wa, $prior_eligible_wa)), false);
    }

    // ═════════════════════════════════════════════════════════════════════
    // HISTORY LOG
    // ═════════════════════════════════════════════════════════════════════

    $date = date("Y-m-d H:i:s");
    $db->cdp_query("
        INSERT INTO cdb_order_user_history 
        (user_id, order_id, action, date_history, is_consolidate)
        VALUES (:user_id, :order_id, :action, :date_history, :is_consolidate)
    ");
    $db->bind(':order_id', $order_id);
    $db->bind(':is_consolidate', '1');
    $db->bind(':user_id', $_SESSION['userid']);
    $db->bind(':action', $lang['notification_shipment30']);
    $db->bind(':date_history', $date);
    $db->cdp_execute();

    // ═════════════════════════════════════════════════════════════════════
    // ADDRESS UPDATES
    // ═════════════════════════════════════════════════════════════════════

    $db->cdp_query("SELECT * FROM cdb_senders_addresses where id_addresses= '" . $_POST["sender_address_id"] . "'");
    $sender_address_data = $db->cdp_registro();

    if ($sender_address_data) {
        $sender_country = $sender_address_data->country;
        $sender_state = $sender_address_data->state;
        $sender_city = $sender_address_data->city;
        $sender_zip_code = $sender_address_data->zip_code;
        $sender_address = $sender_address_data->address;

        $_sender_country = cdp_getCountry($sender_country);
        $final_sender_country = $_sender_country['data'];

        $_sender_state = cdp_getState($sender_state);
        $final_sender_state = $_sender_state['data'];

        $sender_city = cdp_getCity($sender_city);
        $final_sender_city = $sender_city['data'];
    }

    $db->cdp_query("SELECT * FROM cdb_recipients_addresses where id_addresses= '" . $_POST["recipient_address_id"] . "'");
    $recipient_address_data = $db->cdp_registro();

    if ($recipient_address_data) {
        $recipient_address = $recipient_address_data->address;
        $recipient_country = $recipient_address_data->country;
        $recipient_city = $recipient_address_data->city;
        $recipient_state = $recipient_address_data->state;
        $recipient_zip_code = $recipient_address_data->zip_code;

        $_recipient_country = cdp_getCountry($recipient_country);
        $final_recipient_country = $_recipient_country['data'];

        $_recipient_state = cdp_getState($recipient_state);
        $final_recipient_state = $_recipient_state['data'];

        $recipient_city = cdp_getCity($recipient_city);
        $final_recipient_city = $recipient_city['data'];
    }

    cdp_deleteCourierAddress($order_id);

    if ($sender_address_data && $recipient_address_data) {
        $db->cdp_query("
            INSERT INTO cdb_address_shipments
            (order_id, order_track, sender_country, sender_state, sender_city, sender_zip_code, sender_address,
             recipient_country, recipient_state, recipient_city, recipient_zip_code, recipient_address)
            VALUES
            (:order_id, :order_track, :sender_country, :sender_state, :sender_city, :sender_zip_code, :sender_address,
             :recipient_country, :recipient_state, :recipient_city, :recipient_zip_code, :recipient_address)
        ");

        $db->bind(':order_id', $order_id);
        $db->bind(':order_track', $row_order->c_prefix . $row_order->c_no);
        $db->bind(':sender_country', $final_sender_country->name ?? 'N/A');
        $db->bind(':sender_state', $final_sender_state->name ?? 'N/A');
        $db->bind(':sender_city', $final_sender_city->name ?? 'N/A');
        $db->bind(':sender_zip_code', $sender_zip_code ?? '');
        $db->bind(':sender_address', $sender_address ?? '');
        $db->bind(':recipient_country', $final_recipient_country->name ?? 'N/A');
        $db->bind(':recipient_state', $final_recipient_state->name ?? 'N/A');
        $db->bind(':recipient_city', $final_recipient_city->name ?? 'N/A');
        $db->bind(':recipient_zip_code', $recipient_zip_code ?? '');
        $db->bind(':recipient_address', $recipient_address ?? '');

        $db->cdp_execute();
    }

    // ═════════════════════════════════════════════════════════════════════
    // FILE UPLOADS
    // ═════════════════════════════════════════════════════════════════════

    if (isset($_FILES['filesMultiple']['name']) && count($_FILES['filesMultiple']['name']) > 0) {
        $target_dir = "order_files/";
        $deleted_file_ids = !empty($_POST['deleted_file_ids']) ? explode(",", $_POST['deleted_file_ids']) : [];

        foreach ($_FILES["filesMultiple"]["tmp_name"] as $key => $tmp_name) {
            if (!in_array($key, $deleted_file_ids)) {
                $image_name = time() . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                $target_file = $target_dir . $image_name;
                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                $imageFileSize = $_FILES["filesMultiple"]["size"][$key];

                if ($imageFileSize > 0 && move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file)) {
                    cdp_insertOrdersFiles($order_id, $target_file, $image_name, date("Y-m-d H:i:s"), '1', $imageFileType);
                }
            }
        }
    }


    if ($main_update_result) {
        $consolidate_number = $row_order->c_prefix . $row_order->c_no;
        $message = "Consolidation #" . $consolidate_number . " has been updated successfully";
        $success_script = 'swal("Success", "' . $message . '", "success").then(function() { window.location.href = "consolidate_view.php?id=' . $row_order->consolidate_id . '"; });';
    } else {
        $message = "There was an error processing the data";
        $error_script = 'swal("Error", "' . $message . '", "error");';
    }

    } // end DANGEROUS-GOODS guard (else of $dg_mismatch)
}

?>

<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CODDINGPRO">
    <meta name="keywords" content="<?php echo htmlspecialchars($core->meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($core->og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($core->og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($core->og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($core->og_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($core->og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['langs_01'] ?> | <?php echo $core->site_name ?></title>

    <link rel="stylesheet" href="assets/template/assets/libs/intlTelInput/intlTelInput.css">
    <link rel="stylesheet" href="assets/template/assets/libs/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/template/assets/libs/select2/dist/css/select2.min.css">
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <?php $office = $core->cdp_getOffices(); ?>
        <?php $agencyrow = $core->cdp_getBranchoffices();
        if (!function_exists('cdp_getAgencyBranchIdForUser')) { require_once(__DIR__ . '/../../helpers/querys.php'); }
        $agency_default_id = (isset($userData->userlevel) && (int)$userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '') : 0; ?>
        <?php $courierrow = $core->cdp_getCouriercom(); ?>
        <?php $statusrow = $core->cdp_getStatusByType(2); ?>
        <?php $packrow = $core->cdp_getPack(); ?>
        <?php $payrow = $core->cdp_getPayment(); ?>
        <?php $itemrow = $core->cdp_getItem(); ?>
        <?php $moderow = $core->cdp_getShipmode(); ?>
        <?php $driverrow = $user->cdp_userAllDriver(); ?>
        <?php $delitimerow = $core->cdp_getDelitime(); ?>
        <?php $track = $core->cdp_order_track(); ?>
        <?php $categories = $core->cdp_getCategories(); ?>

        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-12 align-self-center">
                        <h4 class="page-title"><i class="ti-package" aria-hidden="true"></i> <?php echo $lang['langs_01']; ?></h4>
                        <br>
                    </div>
                </div>
            </div>

            <form method="post" id="invoice_form" name="invoice_form">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="inputcom" class="control-label col-form-label"><?php echo $lang['langs_020'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><span style="color:#ff0000"><b><?php echo $lang['langs_020'] ?></b></span></div>
                                                </div>
                                                <input type="text" class="form-control" name="order_no" id="order_no" value="<?php echo $row_order->c_prefix . $row_order->c_no; ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="field-2" class="control-label col-form-label"><?php echo $lang['langs_021'] ?></label>
                                                <input name="seals" id="seals" value="<?php echo $row_order->seals_package; ?>" class="form-control" placeholder="00-00000">
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label for="inputcontact" class="control-label col-form-label"><?php echo $lang['langs_039'] ?> <i style="color:#ff0000" class="fas fa-shipping-fast"></i></label>
                                            <div class="input-group">
                                                <select class="custom-select col-12" id="status_courier" name="status_courier">
                                                    <option value="0">--<?php echo $lang['left210'] ?>--</option>
                                                    <?php foreach ($statusrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php if ($row_order->status_courier == $row->id) { echo 'selected'; } ?>><?php echo $row->mod_style; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="control-label col-form-label"><?php echo 'Estimated Time of Arrival' ?></label>
                                            <input type='date' class="form-control" id="estimated_eta" name="estimated_eta" value="<?php echo isset($package_tracking_data->estimated_eta) ? $package_tracking_data->estimated_eta : ''; ?>" />
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div>
                                                <label class="control-label" id="selectItem">
                                                    <?php echo $lang['leftorder15']; ?>
                                                </label>
                                            </div>
    
                                            <input class="custom-file-input" id="filesMultiple" name="filesMultiple[]" multiple="multiple" type="file" style="display: none;" onchange="cdp_validateZiseFiles(); cdp_preview_images();" />
                                            <button type="button" id="openMultiFile" class="btn btn-default pull-left mb-4">
                                                <i class='fa fa-paperclip' style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder16']; ?>
                                            </button>
                                        </div>
                                    </div>
    
                                    <div class="col-md-12 row" id="image_preview"></div>
    
                                    <div class="col-md-4 mt-4">
                                        <div id="clean_files" class="hide">
                                            <button type="button" id="clean_file_button" class="btn btn-danger ml-3">
                                                <i class='fa fa-trash' style="font-size:18px; cursor:pointer;"></i>
                                                <?php echo $lang['leftorder17']; ?>
                                            </button>
                                        </div>
                                    </div>
    
                                    <div class="row">
                                        <div class="resultados_file col-md-4 pull-right mt-4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="display: none;">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title"><i class="mdi mdi-book-multiple" style="color:#20c997"></i> <?php echo $lang['add-title13'] ?></h4>
                                    <br>

                                    <div class="row">
                                        <div class="form-group col-md-3">
                                            <label for="inputEmail3" class="control-label col-form-label"><?php echo $lang['add-title23'] ?> <i style="color:#ff0000" class="fas fa-donate"></i></label>
                                            <div class="input-group">
                                                <select class="custom-select col-12" id="order_pay_mode" name="order_pay_mode">
                                                    <option value="0">--<?php echo $lang['left243'] ?>--</option>
                                                    <?php foreach ($payrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php if ($row_order->order_pay_mode == $row->id) { echo 'selected'; } ?>>
                                                            <?php echo $row->met_payment; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label for="inputname" class="control-label col-form-label"><?php echo $lang['left208'] ?></label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text" style="color:#ff0000"><i class="fas fa-car"></i></span>
                                                </div>
                                                <select class="custom-select col-12" id="driver_id" name="driver_id">
                                                    <option value="0">--<?php echo $lang['left209'] ?>--</option>
                                                    <?php foreach ($driverrow as $row) : ?>
                                                        <option value="<?php echo $row->id; ?>" <?php if ($row_order->driver_id == $row->id) { echo 'selected'; } ?>><?php echo $row->fname . ' ' . $row->lname; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    $db->cdp_query("SELECT * FROM cdb_order_files where order_id='" . $_GET['id'] . "' and is_consolidate='1' ORDER BY date_file");
                    $files_order = $db->cdp_registros();
                    $numrows = $db->cdp_rowCount();

                    if ($numrows > 0) {
                    ?>
                        <div class="row">
                            <div class="col-lg-12">
                                <div id="resultados_ajax_delete_file"></div>
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fa fa-paperclip"></i><?php echo $lang['leftorder147'] ?></h5>
                                        <hr>
                                        <div class="col-md-12 row">
                                            <?php
                                            foreach ($files_order as $file) {
                                                $date_add = date("Y-m-d h:i A", strtotime($file->date_file));
                                                $src = 'assets/images/no-preview.jpeg';

                                                if (in_array($file->file_type, ['jpg', 'jpeg', 'png', 'ico'])) {
                                                    $src = $file->url;
                                                }
                                            ?>
                                                <div class="col-md-3" id="file_delete_item_<?php echo $file->id; ?>">
                                                    <img style="width: 180px; height: 180px;" class="img-thumbnail" src="<?php echo $src; ?>">
                                                    <div class="row">
                                                        <div class="col-md-12 mb-3 mt-2">
                                                            <p class="text-justify"><a style="color:#7460ee;" target="_blank" href="<?php echo $file->url; ?>"><?php echo $file->name; ?></a></p>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="mb-2">
                                                            <button type="button" class="btn btn-danger btn-sm" onclick="cdp_deleteImgAttached('<?php echo $file->id; ?>');"><i class="fa fa-trash"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    } ?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div id="resultados_ajax"></div>
                                    <script>
                                        var selected = [];
                                    </script>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h4 class="card-title">
                                                <i class="fas fas fa-boxes" style="color:#20c997"></i>
                                                <?php echo $lang['left212'] ?>
                                            </h4>
                                        </div>
                                        <!-- Adicionar envios al consolidado de envios-->
                                        <div class="col-md-6 text-right">
                                            <button type="button" data-toggle="modal" data-target="#myModalConsolidate" class="btn btn-outline-dark">
                                                <span class="fa fa-search"></span>
                                                <?php echo $lang['leftorder148']; ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div><br></div>
                                    
                                    <div class="table-responsive">
                                        <table id="invoice-item-table" class="table">
                                            <thead class="bg-inverse text-white">
                                                <tr>
                                                    <th><b><?php echo $lang['packager'] ?></b></th>
                                                    <th><b><?php echo $lang['ltracking'] ?></b></th>
                                                    <th class="text-right"><b>Qty</b></th>
                                                    <th><b><?php echo $lang['contents'] ?></b></th>
                                                    <th><b><?php echo $lang['left215'] ?></b></th>
                                                    <th></th>
                                                    <th><b><?php echo $lang['ship-all5'] ?></b></th>
                                                    <th></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="projects-tbl">
                                                <?php
                                                if ($order_items) {
                                                    $sumador_total = 0;
                                                    $sumador_libras = 0;
                                                    $sumador_volumetric = 0;
                                                    $precio_total = 0;
                                                    $total_impuesto = 0;
                                                    $total_seguro = 0;
                                                    $total_peso = 0;
                                                    $total_descuento = 0;
                                                    $total_impuesto_aduanero = 0;
                                                    $count_item = 0;

                                                    foreach ($order_items as $row_order_item) {
                                                        $db->cdp_query("SELECT total_order, sender_id, order_id FROM cdb_add_order WHERE order_no='" . $row_order_item->order_no . "'");
                                                        $order_details = $db->cdp_registro();

                                                        $db->cdp_query("SELECT order_item_description FROM cdb_add_order_item WHERE order_id = '" . $row_order_item->order_id . "'");
                                                        $description = $db->cdp_registro();

                                                        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $order_details->sender_id . "'");
                                                        $sender = $db->cdp_registro();

                                                        $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id = '" . $order_details->order_id . "'");
                                                        $items = $db->cdp_registros();

                                                        $weight_item = $row_order_item->weight;
                                                        $total_metric = $row_order_item->length * $row_order_item->width * $row_order_item->height / $row_order->volumetric_percentage;

                                                        if ($weight_item > $total_metric) {
                                                            $calculate_weight = $weight_item;
                                                            $sumador_libras += $weight_item;
                                                        } else {
                                                            $calculate_weight = $total_metric;
                                                            $sumador_volumetric += $total_metric;
                                                        }

                                                        $precio_total = $calculate_weight * $row_order->value_weight;
                                                        $sumador_total += $precio_total;

                                                        if ($sumador_total > $core->min_cost_tax) {
                                                            $total_impuesto = $sumador_total * $row_order->tax_value / 100;
                                                        }

                                                        $total_descuento = $sumador_total * $row_order->tax_discount / 100;
                                                        $total_peso = $sumador_libras + $sumador_volumetric;
                                                        $total_seguro = $row_order->tax_insurance_value * $row_order->total_insured_value / 100;
                                                        $total_impuesto_aduanero = $total_peso * $row_order->tax_custom_tariffis_value;
                                                        $total_envio = ($sumador_total - $total_descuento) + $total_impuesto + $total_seguro + $total_impuesto_aduanero;

                                                        $count_item++;
                                                ?>
                                                        <tr class="card-hover" id="row_id_<?php echo $row_order_item->order_id; ?>">
                                                            <td><b><?php echo $sender->fname . ' ' . $sender->lname; ?></b></td>
                                                            <td><b><?php echo $row_order_item->order_prefix . $row_order_item->order_no; ?></b></td>
                                                            <td class="text-right">
                                                                <?php foreach ($items as $item) { ?>
                                                                    <b><?php echo (int) $item->order_item_quantity; ?></b><br>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <?php foreach ($items as $item) { ?>
                                                                    <b><?php echo $item->order_item_description; ?></b><br>
                                                                <?php } ?>
                                                            </td>
                                                            <td><b><?php echo $weight_item; ?></b></td>
                                                            <td></td>
                                                            <td><b><?php echo cdb_money_format((float)$order_details->total_order); ?></b></td>
                                                            <td class="text-center">
                                                                <button type="button" name="remove_row" id="<?php echo $row_order_item->order_id; ?>" class="btn btn-danger btn-xs remove_row mt-2"><i class="fa fa-trash"></i></button>
                                                            </td>

                                                            <script>
                                                                selected.push('<?php echo $row_order_item->order_id; ?>');
                                                            </script>

                                                            <input type="hidden" id="total_vol_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->weight_vol; ?>" name="weight_vol[]">
                                                            <input type="hidden" id="order_prefix_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->order_prefix; ?>" name="prefix[]">
                                                            <input type="hidden" id="order_no_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->order_no; ?>" name="order_no_item[]">
                                                            <input type="hidden" id="weight_<?php echo $row_order_item->order_id; ?>" value="<?php echo $weight_item; ?>" name="weight[]">
                                                            <input type="hidden" id="length_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->length; ?>" name="length[]">
                                                            <input type="hidden" id="height_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->height; ?>" name="height[]">
                                                            <input type="hidden" id="width_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->width; ?>" name="width[]">
                                                            <input type="hidden" id="order_id_<?php echo $row_order_item->order_id; ?>" value="<?php echo $row_order_item->order_id; ?>" name="order_id[]">
                                                        </tr>
                                                    <?php
                                                    }

                                                    $sumador_total = cdb_money_format_bar($sumador_total);
                                                    $total_envio = cdb_money_format($total_envio);
                                                    $total_seguro = cdb_money_format_bar($total_seguro);
                                                    $total_impuesto_aduanero = cdb_money_format_bar($total_impuesto_aduanero);
                                                    $total_impuesto = cdb_money_format_bar($total_impuesto);
                                                    $total_descuento = cdb_money_format_bar($total_descuento);
                                                    ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="">
                                                    <td colspan="4" class="text-right"><b>Total Weight:</b></td>
                                                    <td class="" id="total_weight_sum">0.00</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                                <tr class="">
                                                    <td></td>
                                                    <td colspan="5" class="text-right"><b>Total Cost:</b></td>
                                                    <td id="total_cost_sum">0.00</td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                            <input type="hidden" name="total_item" id="total_item" value="<?php echo $count_item; ?>" />
                                        <?php
                                                } ?>
                                        </table>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-actions">
                                                <div class="card-body">
                                                    <div class="text-right">
                                                        <input type="hidden" name="subtotal_input" id="subtotal_input" />
                                                        <input type="hidden" name="impuesto_input" id="impuesto_input" />
                                                        <input type="hidden" name="discount_input" id="discount_input" />
                                                        <input type="hidden" name="insurance_input" id="insurance_input" />
                                                        <input type="hidden" name="insured_input" id="insured_input" value="<?php echo $row_order->total_insured_value; ?>" />
                                                        <input type="hidden" name="total_impuesto_aduanero_input" id="total_impuesto_aduanero_input" />
                                                        <input type="hidden" name="total_envio_input" id="total_envio_input" />
                                                        <input type="hidden" name="total_weight_input" id="total_weight_input" />

                                                        <input type="submit" name="create_invoice" id="create_invoice" class="btn btn-danger" value="<?php echo $lang['langs_10084']; ?>" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="order_volumetric_percentage" id="order_volumetric_percentage" value="<?php echo $row_order->volumetric_percentage; ?>" />
                    <input type="hidden" name="order_value_weight" id="order_value_weight" value="<?php echo $row_order->value_weight; ?>" />
                    <input type="hidden" name="order_min_cost_tax" id="order_min_cost_tax" value="<?php echo $core->min_cost_tax; ?>" />
                    <input type="hidden" name="order_tax_value" id="order_tax_value" value="<?php echo $row_order->tax_value; ?>" />
                    <input type="hidden" name="order_tax_discount" id="order_tax_discount" value="<?php echo $row_order->tax_discount; ?>" />
                    <input type="hidden" name="order_tax_insurance_value" id="order_tax_insurance_value" value="<?php echo $row_order->tax_insurance_value; ?>" />
                    <input type="hidden" name="order_insured_value" id="order_insured_value" value="<?php echo $row_order->total_insured_value; ?>" />
                    <input type="hidden" name="order_tax_custom_tariffis_value" id="order_tax_custom_tariffis_value" value="<?php echo $row_order->tax_custom_tariffis_value; ?>" />
            </form>

            <?php include('views/modals/modal_add_user_shipment.php'); ?>
            <?php include('views/modals/modal_add_recipient_shipment.php'); ?>
            <?php include('views/modals/modal_add_addresses_user.php'); ?>
            <?php include('views/modals/modal_add_addresses_recipient.php'); ?>
        </div>
        <?php include 'views/inc/footer.php'; ?>
    </div>

    <?php $cdp_show_dg_filter = true; // air courier consolidation: show dangerous-goods filter ?>
    <?php include('views/modals/modal_add_ship_consolidate.php'); ?>

    <?php include('helpers/languages/translate_to_js.php'); ?>

    <script src="assets/template/assets/libs/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
    <script src="assets/template/assets/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="assets/template/assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/template/assets/libs/intlTelInput/intlTelInput.js"></script>
    <?php if (isset($success_script)): ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script><?php echo $success_script; ?></script>
    <?php elseif (isset($error_script)): ?>
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
        <script><?php echo $error_script; ?></script>
    <?php endif; ?>
    <!-- Dangerous-goods mode for the Find Shipments filter. EDIT is locked to the
         consolidation's own kind; the filter cannot be toggled here. -->
    <script>
        window.CDP_DG_MODE = 'edit';
        window.CDP_DG_LOCK = <?php echo (int)($row_order->is_dangerous_good ?? 0); ?>;
    </script>
    <script src="dataJs/consolidate_edit.js"></script>
</body>
</html>