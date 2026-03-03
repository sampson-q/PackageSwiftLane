<?php
// ini_set('display_errors', 1);
require_once("../../helpers/querys.php");
require_once("../../helpers/vendor/autoload.php");

use ClickSend\Api\SMSApi;
use ClickSend\Model\SmsMessage;
use ClickSend\Model\SmsMessageCollection;
use GuzzleHttp\Client as GuzzleClient;

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

// Función para enviar la notificación SMS
function sendNotificationSMS($user, $sms_body, $notify)
{
    $settings = cdp_getSettingsCourier();

    if (!$notify || intval($settings->active_sms) != 1) {
        return [
            'success' => false,
            'message' => 'Notification not enabled'
        ];
    }

    $result = [
        'success' => false,
        'message' => ''
    ];

    if ($sms_body !== null) {
        try {
            // Configurar la autenticación básica de HTTP: BasicAuth
            $config = ClickSend\Configuration::getDefaultConfiguration()
                          ->setUsername($settings->twilio_sms_sid) // Asegúrate de que estos campos estén configurados correctamente en tu configuración
                          ->setPassword($settings->twilio_sms_token);

            $apiInstance = new SMSApi(new GuzzleClient(), $config);
            $msg = new SmsMessage();
            $msg->setBody(htmlentities($sms_body));
            $msg->setTo($user->phone);
            $msg->setSource("sdk");

            // \ClickSend\Model\SmsMessageCollection | SmsMessageCollection model
            $sms_messages = new SmsMessageCollection();
            $sms_messages->setMessages([$msg]);

            // Enviar el SMS
            $resultAPI = $apiInstance->smsSendPost($sms_messages);
            $result['success'] = true;
            $result['message'] = "Notification sent successfully";
        } catch (Exception $e) {
            $result['message'] = 'Exception when calling SMSApi->smsSendPost: ' . $e->getMessage();
        }
    } else {
        $result['message'] = "Error: No body defined for the SMS";
    }

    return $result;
}