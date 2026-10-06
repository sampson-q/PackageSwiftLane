<?php
/**
 * ============================================================================
 * "Consolidation Updated" e-mail — sent when a consolidation is edited.
 *
 * Used by views/consolidate/consolidate_edit.php (air) and
 * views/consolidate_packages/consolidate_edit.php (sea). It goes to the
 * consolidation's own sender (often the company's house account, which ships
 * in its own name) and names the consolidation. The owners of the packages
 * inside are told separately, about their shipment only, by
 * cdp_notifyConsolidationPackageSenders() (helpers/whatsapp.php).
 *
 * Template 29 ("Push Notifications") carries [USERNAME] and [MESSAGE].
 * Template 12 ("Single Email") must not be used here: it is the starter text
 * of the manual single-user e-mail page and has no [MESSAGE] placeholder, so
 * every mail sent through it read "Your message goes here...".
 * ============================================================================
 */

require_once __DIR__ . '/querys.php';
require_once __DIR__ . '/after_response.php';
require_once __DIR__ . '/phpmailer/class.phpmailer.php';
require_once __DIR__ . '/phpmailer/class.smtp.php';

/**
 * Queue the e-mail to run after the response.
 *
 * The consolidated-shipments list is read when the mail is built, after the
 * edit has rewritten the detail rows, so it shows the consolidation as saved.
 *
 * @param string $module       'consolidate' (air) | 'consolidate_packages' (sea)
 * @param int    $consolidateId
 * @param int    $senderId     the consolidation's sender (cdb_users.id)
 * @param string $consolRef    cdp_consolidationRef() of the consolidation
 * @param string $subject
 * @param string $changesHtml  intro + changed-fields table, no money
 * @param string $statusLabel  the consolidation's status after the edit
 */
function cdp_mailConsolidationEdit($module, $consolidateId, $senderId, $consolRef, $subject, $changesHtml, $statusLabel)
{
    $args = [(string) $module, (int) $consolidateId, (int) $senderId, (string) $consolRef, (string) $subject, (string) $changesHtml, (string) $statusLabel];
    cdp_afterResponse(function () use ($args) {
        try {
            cdp_sendConsolidationEditMail(...$args);
        } catch (Throwable $e) {
            error_log('cdp_mailConsolidationEdit: ' . $e->getMessage());
        }
    });
}

function cdp_sendConsolidationEditMail($module, $consolidateId, $senderId, $consolRef, $subject, $changesHtml, $statusLabel)
{
    $built = cdp_buildConsolidationEditMail($module, $consolidateId, $senderId, $changesHtml, $statusLabel);
    if (!$built) {
        return;
    }
    list($recipient, $to, $body) = $built;
    $cfg = cdp_getSettingsCourier();

    $ctx = [
        'source'            => 'consolidation_edit',
        'source_label'      => 'Consolidation Edit · ' . $consolRef,
        'entity_type'       => 'consolidation',
        'entity_id'         => (string) $consolidateId,
        'entity_label'      => $consolRef,
        'subject'           => $subject,
        'recipient_user_id' => (int) $recipient->id,
        'recipient_name'    => trim((string) $recipient->fname . ' ' . (string) $recipient->lname),
        'template_id'       => 29,
    ];
    cdp_msgSetContext($ctx);
    try {
        if ($cfg->mailer === 'PHP') {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . $cfg->site_email . "\r\n";
            $ok = @mail($to, $subject, $body, $headers);
            cdp_msgLogPhpMail($ok, $to, $subject, $body);
        } elseif ($cfg->mailer === 'SMTP') {
            // PHPMailer logs itself (helpers/message_log.php hooks).
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->SMTPAuth = true;
            $mail->Port     = $cfg->smtp_port;
            $mail->Timeout  = 15;
            $mail->IsHTML(true);
            $mail->CharSet  = 'utf-8';
            $mail->Host     = $cfg->smtp_host;
            $mail->Username = $cfg->smtp_user;
            $mail->Password = $cfg->smtp_password;
            $mail->From     = $cfg->site_email;
            $mail->FromName = $cfg->smtp_names;
            $mail->AddAddress($to);
            $mail->Subject  = $subject;
            $mail->Body     = '<html><body>' . $body . '</body></html>';
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->Send();
        }
    } finally {
        cdp_msgClearContext(array_keys($ctx));
    }
}

/**
 * The recipient and the rendered body, or null when there is nobody to mail.
 *
 * @return array{0:object,1:string,2:string}|null [cdb_users row, address, html body]
 */
function cdp_buildConsolidationEditMail($module, $consolidateId, $senderId, $changesHtml, $statusLabel)
{
    $tables = [
        'consolidate'          => ['detail' => 'cdb_consolidate_detail',          'orders' => 'cdb_add_order'],
        'consolidate_packages' => ['detail' => 'cdb_consolidate_packages_detail', 'orders' => 'cdb_customers_packages'],
    ];
    if (!isset($tables[$module]) || $senderId <= 0) {
        return null;
    }

    $db = new Conexion;
    $db->cdp_query("SELECT id, fname, lname, email, locker FROM cdb_users WHERE id = :id LIMIT 1");
    $db->bind(':id', $senderId);
    $db->cdp_execute();
    $recipient = $db->cdp_registro();
    $to = $recipient ? trim((string) $recipient->email) : '';
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $db->cdp_query("SELECT o.order_prefix, o.order_no
        FROM {$tables[$module]['detail']} d
        INNER JOIN {$tables[$module]['orders']} o ON o.order_id = d.order_id
        WHERE d.consolidate_id = :cid
        ORDER BY o.order_id");
    $db->bind(':cid', $consolidateId);
    $items = '';
    foreach ((array) $db->cdp_registros() as $r) {
        $items .= '<li>' . htmlspecialchars($r->order_prefix . $r->order_no, ENT_QUOTES, 'UTF-8') . '</li>';
    }

    $message = $changesHtml
        . '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:8px 0 16px 0;background:#fafafa;border-left:4px solid #f5a800;font-family:Roboto,Arial,Helvetica,sans-serif;"><tr><td style="padding:12px 18px;">'
        . '<p style="margin:0 0 4px 0;font-size:10px;color:#aaaaaa;text-transform:uppercase;letter-spacing:1px;">Consolidation Status</p>'
        . '<p style="margin:0 0 10px 0;font-size:15px;font-weight:700;color:#1a1a1a;">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 4px 0;font-size:10px;color:#aaaaaa;text-transform:uppercase;letter-spacing:1px;">Consolidated Shipments</p>'
        . ($items !== '' ? '<ul style="margin:0;padding-left:18px;font-size:14px;color:#1a1a1a;">' . $items . '</ul>' : '<p style="margin:0;font-size:14px;">N/A</p>')
        . '</td></tr></table>';

    $tpl = cdp_getEmailTemplatesdg1i4(29);
    if (!$tpl) {
        error_log('cdp_mailConsolidationEdit: e-mail template 29 not found');
        return null;
    }
    $cfg = cdp_getSettingsCourier();
    $body = cdp_cleanOutx(str_replace(
        ['[SITE_NAME]', '[USERNAME]', '[MESSAGE]', '[URL]'],
        [(string) $cfg->site_name, htmlspecialchars(cdp_nameWithLocker($recipient), ENT_QUOTES, 'UTF-8'), $message, rtrim((string) $cfg->site_url, '/')],
        (string) $tpl->body
    ));

    return [$recipient, $to, $body];
}
