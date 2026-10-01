<?php
// ini_set('display_errors', 1);
if (!defined('SWIFTLANE_LOADER_LOADED')) {
    require_once(__DIR__ . '/../../loader.php');
    require_once(__DIR__ . '/../../helpers/ajax_guard.php');
    require_login();
    require_permission('view_dashboard');
}
require_once(__DIR__ . "/../../helpers/hubtel_sms.php");

// Configuración de la plantilla
function generateSMSBody($user_data, $fullshipment, $add_status, $app_url, $template_id) {
    $subjectVal = cdp_getsmsTemplates($template_id);

    $body = str_replace(
        array(
            '[NAME]', 
            '[TRACK]', 
            '[STATUS]', 
            '[LINK]'
        ),
        array(
            $user_data->fname . ' ' . $user_data->lname, 
            $fullshipment, 
            $add_status,
            $app_url
        ),
        $subjectVal->body
    );

    $newbody = cdp_cleanOut($body);

    return $newbody;
}

// The SMS the "notify by SMS" toggles ask for. Hubtel sends it (Tools > SMS);
// the shared body lives in helpers/hubtel_sms.php so both SMS services and the
// WhatsApp copy clean the text the same way (plain text, no emoji).
function sendNotificationSMS($user, $sms_body, $notify)
{
    return cdp_smsSendNotification($user, $sms_body, $notify);
}
