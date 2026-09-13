<?php
/**
 * ============================================================================
 * Push notifications — the one place an admin-typed message is delivered.
 *
 * Used by ajax/tools/push_notifications_ajax.php (broadcast / single user /
 * consolidation) and ajax/tools/push_notifications_consolidation_ajax.php (the
 * per-consolidation page). Both used to carry their own copy of this logic and
 * both reported "WhatsApp sent" without looking at the result; this helper
 * returns what actually happened per channel and per recipient, and every
 * attempt lands in cdb_message_log through the send primitives.
 *
 * Recipients of a consolidation push are the OWNERS of the packages inside it
 * (cdb_add_order.sender_id — the customer), never cdb_add_order.user_id, which
 * is the staff account that registered the order.
 * ============================================================================
 */

require_once __DIR__ . '/querys.php';
require_once __DIR__ . '/message_log.php';
require_once __DIR__ . '/phpmailer/class.phpmailer.php';
require_once __DIR__ . '/phpmailer/class.smtp.php';
require_once dirname(__DIR__) . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';

/**
 * Deliver one admin message to one user over WhatsApp and e-mail.
 *
 * @param object $user     cdb_users row
 * @param string $subject
 * @param string $message  plain text typed by the admin
 * @param object $settings cdp_getSettingsCourier()
 * @param array  $ctx      message-log context (source, batch_id, entity_*)
 * @return array{whatsapp:array,email:array}
 *         each: ['status' => 'sent'|'failed'|'skipped', 'detail' => string]
 */
function cdp_pushNotifyUser($user, $subject, $message, $settings, array $ctx = [])
{
    $out = [
        'whatsapp' => ['status' => 'skipped', 'detail' => ''],
        'email'    => ['status' => 'skipped', 'detail' => ''],
    ];
    $app_url = (string) ($settings->site_url ?? '');
    $name    = trim((string) ($user->fname ?? '') . ' ' . (string) ($user->lname ?? ''));

    cdp_msgSetContext(array_merge([
        'subject'           => $subject,
        'recipient_user_id' => (int) ($user->id ?? 0),
        'recipient_name'    => $name,
    ], $ctx));

    // ── WhatsApp (template 12 "Push Notifications") ─────────────────────────
    try {
        $tpl = getTemplateWhatsApp(12);
        if ($tpl) {
            $body = str_replace(
                ['[USERNAME]', '[SUBJECT]', '[SITE_NAME]', '[MESSAGE]', '[URL]'],
                [ucfirst($name), $subject, (string) $settings->site_name, $message, $app_url],
                (string) $tpl->body
            );
            cdp_msgSetContext(['template_id' => 12]);
            $r = sendNotificationWhatsApp_v2($user, $body);
            cdp_msgClearContext(['template_id']);
            if (!empty($r['success'])) {
                $out['whatsapp'] = ['status' => 'sent', 'detail' => ''];
            } else {
                $out['whatsapp'] = ['status' => !empty($r['skipped']) ? 'skipped' : 'failed', 'detail' => (string) ($r['message'] ?? '')];
            }
        } else {
            $out['whatsapp'] = ['status' => 'failed', 'detail' => 'WhatsApp template 12 not found.'];
            cdp_msgLog(['channel' => 'whatsapp', 'status' => 'failed', 'status_detail' => 'WhatsApp template 12 not found.',
                'recipient_to' => (string) ($user->phone ?? ''), 'body' => $message]);
        }
    } catch (Throwable $e) {
        $out['whatsapp'] = ['status' => 'failed', 'detail' => $e->getMessage()];
        cdp_msgLog(['channel' => 'whatsapp', 'status' => 'failed', 'status_detail' => $e->getMessage(),
            'recipient_to' => (string) ($user->phone ?? ''), 'body' => $message]);
    }

    // ── E-mail (template 29) ────────────────────────────────────────────────
    $to = trim((string) ($user->email ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $out['email'] = ['status' => 'skipped', 'detail' => 'No valid e-mail address.'];
        cdp_msgLog(['channel' => 'email', 'status' => 'skipped', 'status_detail' => 'No valid e-mail address.',
            'recipient_to' => $to, 'body' => $message]);
    } else {
        try {
            $email_template = cdp_getEmailTemplatesdg1i4(29);
            $tplBody = $email_template ? (string) $email_template->body : '[MESSAGE]';
            $body = str_replace(
                ['[USERNAME]', '[MESSAGE]', '[URL]', '[SITE_NAME]'],
                [$name, $message, $app_url, (string) $settings->site_name],
                $tplBody
            );
            $newbody = cdp_cleanOutx($body);
            cdp_msgSetContext(['template_id' => 29]);

            if (($settings->mailer ?? '') === 'PHP') {
                $from   = (string) $settings->email_address;
                $header = "MIME-Version: 1.0\r\n";
                $header .= "Content-type: text/html; charset=UTF-8\r\n";
                $header .= "From: {$from}\r\n";
                $ok = @mail($to, $subject, $newbody, $header);
                cdp_msgLogPhpMail($ok, $to, $subject, $newbody);
                $out['email'] = $ok ? ['status' => 'sent', 'detail' => ''] : ['status' => 'failed', 'detail' => 'PHP mail() returned false'];
            } else {
                // PHPMailer logs itself (helpers/message_log.php hooks).
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $settings->smtp_host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $settings->smtp_user;
                $mail->Password   = $settings->smtp_password;
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->Timeout    = 15;
                $mail->setFrom($settings->email_address, $settings->smtp_names);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = "<html><body><p>{$newbody}</p></body></html>";
                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                $mail->send();
                $out['email'] = ['status' => 'sent', 'detail' => ''];
            }
            cdp_msgClearContext(['template_id']);
        } catch (Throwable $e) {
            cdp_msgClearContext(['template_id']);
            $out['email'] = ['status' => 'failed', 'detail' => $e->getMessage()];
        }
    }

    cdp_msgClearContext(['subject', 'recipient_user_id', 'recipient_name']);
    return $out;
}

/**
 * Active cdb_users rows for a list of ids (deduped).
 *
 * @return object[]
 */
function cdp_pushFetchUsers(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) { return $v > 0; })));
    if (!$ids) {
        return [];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_users WHERE id IN (" . implode(',', $ids) . ") AND active = 1");
    $db->cdp_execute();
    return (array) $db->cdp_registros();
}

/**
 * Customer ids (package owners) inside a consolidation — air (cdb_consolidate)
 * or sea (cdb_consolidate_packages).
 *
 * @return int[]
 */
function cdp_pushConsolidationOwnerIds($consolidate_id, $module = 'consolidate')
{
    $tables = [
        'consolidate'          => ['detail' => 'cdb_consolidate_detail',          'orders' => 'cdb_add_order'],
        'consolidate_packages' => ['detail' => 'cdb_consolidate_packages_detail', 'orders' => 'cdb_customers_packages'],
    ];
    if (!isset($tables[$module])) {
        return [];
    }
    $db = new Conexion;
    $db->cdp_query("SELECT DISTINCT o.sender_id
        FROM {$tables[$module]['detail']} d
        INNER JOIN {$tables[$module]['orders']} o ON o.order_id = d.order_id
        WHERE d.consolidate_id = :cid AND o.sender_id > 0");
    $db->bind(':cid', (int) $consolidate_id);
    $db->cdp_execute();
    $ids = [];
    foreach ((array) $db->cdp_registros() as $r) {
        $ids[] = (int) $r->sender_id;
    }
    return array_values(array_unique($ids));
}

/**
 * Send to a list of users and summarise.
 *
 * @return array{recipients:int,whatsapp:array,email:array,lines:array,batch_id:string}
 */
function cdp_pushNotifyUsers(array $users, $subject, $message, $settings, array $ctx = [])
{
    $batch = $ctx['batch_id'] ?? cdp_msgNewBatchId('push');
    $ctx['batch_id'] = $batch;

    $sum = [
        'recipients' => 0,
        'whatsapp'   => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        'email'      => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        'lines'      => [],
        'batch_id'   => $batch,
    ];
    $done = [];
    foreach ($users as $u) {
        $uid = (int) ($u->id ?? 0);
        if ($uid <= 0 || isset($done[$uid])) {
            continue;
        }
        $done[$uid] = true;
        $sum['recipients']++;

        $r = cdp_pushNotifyUser($u, $subject, $message, $settings, $ctx);
        $sum['whatsapp'][$r['whatsapp']['status']]++;
        $sum['email'][$r['email']['status']]++;

        $name = trim((string) ($u->fname ?? '') . ' ' . (string) ($u->lname ?? ''));
        $sum['lines'][] = sprintf(
            '%s — WhatsApp: %s%s · E-mail: %s%s',
            $name !== '' ? $name : ('User #' . $uid),
            $r['whatsapp']['status'], $r['whatsapp']['detail'] !== '' ? ' (' . $r['whatsapp']['detail'] . ')' : '',
            $r['email']['status'],    $r['email']['detail'] !== ''    ? ' (' . $r['email']['detail'] . ')'    : ''
        );
    }
    return $sum;
}
