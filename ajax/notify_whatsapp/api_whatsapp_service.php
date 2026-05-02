<?php
// ini_set('display_errors', 1);
if (!defined('DEPRIXAPRO_LOADER_LOADED')) {
    require_once(__DIR__ . '/../../loader.php');
    require_once(__DIR__ . '/../../helpers/ajax_guard.php');
    require_login();
    require_permission('view_dashboard');
}
require_once(__DIR__ . "/../../helpers/querys.php");
require_once __DIR__ . '/../../helpers/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

function sendNotificationWhatsApp($sender, $notification_template, $template_whatsapp_body, $tracking_number = null)
{

    $settings = cdp_getSettingsCourier();

    $result = [
        'success' => false,
        'message' => ''
    ];

    if (intval($settings->active_whatsapp) == 1) {

        if ($notification_template !== null) {

            $default_notification_templates = getDefaultTemplateActiveWhatsApp($notification_template);

            if (intval($default_notification_templates->active) == 1 && $default_notification_templates->id_template !== null) {
                $whatsapp_template = getTemplateWhatsApp($default_notification_templates->id_template);
                $whatsapp_body = $whatsapp_template->body;
            }
        } else if ($template_whatsapp_body !== null) {
            $whatsapp_body = $template_whatsapp_body;
        }

        if ($whatsapp_body !== null) {

            $final_template_whatsapp_description = str_replace(
                array(
                    '[CUSTOMER_FULLNAME]',
                    '[COMPANY_NAME]',
                    '[COMPANY_SITE_URL]',
                    '[TRACKING_NUMBER]',
                ),
                array(
                    $sender->fname . ' ' . $sender->lname,
                    $settings->site_name,
                    $settings->site_url,
                    $tracking_number
                ),
                $whatsapp_body
            );

            $params = array(
                'token' => $settings->api_ws_token,
                'to' =>  $sender->phone,
                'body' => $final_template_whatsapp_description,
            );


            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL =>  $settings->api_ws_url . "messages/chat",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                $result['message'] = "Error: cURL Error #" . $err;
            } else {
                $result['success'] = true;
                $result['message'] = "Notificación enviada correctamente";
            }
        } else {
            $result['message'] = "Error: La API de WhatsApp no está activada";
        }
    }

    return $result;
}

function sendNotificationWhatsAppWithPDF($sender, $package_id, $notification_template)
{

    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_settings");

    $db->cdp_execute();
    $settings = $db->cdp_registro();
    $numrows = $db->cdp_rowCount();

    if ($numrows > 0) {

        $config_lang = $settings->language;

        switch ($config_lang) {
            case "fr":
                //echo "PAGE FR";
                include("../../helpers/languages/$config_lang.php"); //include check session FR
                break;
            case "br":
                //echo "PAGE BRAZIL";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "ar":

                include("../../helpers/languages/$config_lang.php");
                break;
            case "es":
                //echo "PAGE ES";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "en":
                //echo "PAGE EN";
                include("../../helpers/languages/$config_lang.php");
                break;
            default:
                //echo "PAGE EN - Setting Default";
                include("../../helpers/languages/$config_lang.php"); //include EN in all other cases of different lang detection
                break;
        }
    }



    $core = new Core;
    $db = new Conexion;

    $settings = cdp_getSettingsCourier();

    $result = [
        'success' => false,
        'message' => ''
    ];


    if (intval($settings->active_whatsapp) == 1) {


        if ($notification_template !== null) {

            $default_notification_templates = getDefaultTemplateActiveWhatsApp($notification_template);

            if (intval($default_notification_templates->active) == 1 && $default_notification_templates->id_template !== null) {
                $whatsapp_template = getTemplateWhatsApp($default_notification_templates->id_template);
                $whatsapp_body = $whatsapp_template->body;

                if ($whatsapp_body !== null) {

                    $db->cdp_query("SELECT * FROM cdb_add_order WHERE order_id='" . $package_id . "'");
                    $row = $db->cdp_registro();

                    $tracking = trim($row->order_prefix . $row->order_no);


                    $db->cdp_query("SELECT * FROM cdb_styles where id= '" . $row->status_courier . "'");
                    $status_courier = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row->receiver_id . "'");
                    $receiver_data = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row->order_courier . "'");
                    $courier_com = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $row->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row->order_service_options . "'");
                    $order_service_options = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $row->order_package . "'");
                    $packaging = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $row->order_deli_time . "'");
                    $delivery_time = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_branchoffices where id= '" . $row->agency . "'");
                    $branchoffices = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_offices where id= '" . $row->origin_off . "' ");
                    $offices = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
                    $address_order = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $row->order_id . "'");
                    $order_items = $db->cdp_registros();


                    $fecha = date("Y-m-d :h:i A", strtotime($row->order_datetime));


                    $logo_src = "../../assets/";

                    $final_template_whatsapp_description = str_replace(
                        array(
                            '[CUSTOMER_FULLNAME]',
                            '[COMPANY_NAME]',
                            '[COMPANY_SITE_URL]',
                            '[TRACKING_NUMBER]',
                        ),
                        array(
                            $sender->fname . ' ' . $sender->lname,
                            $settings->site_name,
                            $settings->site_url,
                            $tracking
                        ),
                        $whatsapp_body
                    );

                    try {

                        ob_start();
                        include('../../pdf/documentos/html/shipment_print.php');
                        $content = ob_get_clean();

                        $html2pdf = new Html2Pdf('P', 'A4', 'es', true, 'UTF-8', 0);
                        $html2pdf->pdf->SetDisplayMode('fullpage');
                        $html2pdf->writeHTML($content);
                        $contenidoPDF = $html2pdf->Output('', 'S');
                        $file_base64 = base64_encode($contenidoPDF);

                        $params = array(
                            'token' => $settings->api_ws_token,
                            'to' =>  $sender->phone,
                            'document' => $file_base64,
                            'caption' => $final_template_whatsapp_description,
                            'filename' => 'Factura_' . $tracking . '.pdf',

                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL =>  $settings->api_ws_url . "messages/document",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYHOST => 0,
                            CURLOPT_SSL_VERIFYPEER => 0,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => http_build_query($params),
                            CURLOPT_HTTPHEADER => array(
                                "content-type: application/x-www-form-urlencoded"
                            ),
                        ));

                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        curl_close($curl);

                        if ($err) {
                            $result['message'] = "Error: cURL Error #" . $err;
                        } else {
                            $result['success'] = true;
                            $result['message'] = "Notificación enviada correctamente";
                        }
                    } catch (Html2PdfException $e) {
                        $html2pdf->clean();
                        $formatter = new ExceptionFormatter($e);
                        echo $formatter->getHtmlMessage();
                    }
                }
            }
        }
    } else {
        $result['message'] = "Error: La API de WhatsApp no está activada";
    }
}

















function sendNotificationWhatsAppWithPDFPackages($sender, $package_id, $notification_template)
{

    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_settings");

    $db->cdp_execute();
    $settings = $db->cdp_registro();
    $numrows = $db->cdp_rowCount();

    if ($numrows > 0) {

        $config_lang = $settings->language;

        switch ($config_lang) {
            case "fr":
                //echo "PAGE FR";
                include("../../helpers/languages/$config_lang.php"); //include check session FR
                break;
            case "br":
                //echo "PAGE BRAZIL";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "ar":

                include("../../helpers/languages/$config_lang.php");
                break;
            case "es":
                //echo "PAGE ES";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "en":
                //echo "PAGE EN";
                include("../../helpers/languages/$config_lang.php");
                break;
            default:
                //echo "PAGE EN - Setting Default";
                include("../../helpers/languages/$config_lang.php"); //include EN in all other cases of different lang detection
                break;
        }
    }



    $core = new Core;
    $db = new Conexion;

    $settings = cdp_getSettingsCourier();

    $result = [
        'success' => false,
        'message' => ''
    ];


    if (intval($settings->active_whatsapp) == 1) {


        if ($notification_template !== null) {

            $default_notification_templates = getDefaultTemplateActiveWhatsApp($notification_template);

            if (intval($default_notification_templates->active) == 1 && $default_notification_templates->id_template !== null) {
                $whatsapp_template = getTemplateWhatsApp($default_notification_templates->id_template);
                $whatsapp_body = $whatsapp_template->body;

                if ($whatsapp_body !== null) {

                     $db->cdp_query("SELECT * FROM cdb_customers_packages WHERE order_id='" . $package_id . "'");
                    $row = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_users WHERE id= '" . $row->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id= '" . $row->order_courier . "'");
                    $courier_com = $db->cdp_registro();
                    
                    $db->cdp_query("SELECT * FROM cdb_category where id= '" . $row->order_item_category . "'");
                    $category = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id= '" . $row->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
                    $address_order = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_customers_packages_detail WHERE order_id='" .  $row->order_id . "'");
                    $order_items = $db->cdp_registros();

                    $dias_ = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
                    $meses_ = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec');


                    $fecha = date("Y-m-d :h:i A", strtotime($row->order_datetime));
                    $tracking = $row->order_prefix . $row->order_no;

                    $logo_src = "../../assets/";

                    $final_template_whatsapp_description = str_replace(
                        array(
                            '[CUSTOMER_FULLNAME]',
                            '[COMPANY_NAME]',
                            '[COMPANY_SITE_URL]',
                            '[TRACKING_NUMBER]',
                        ),
                        array(
                            $sender->fname . ' ' . $sender->lname,
                            $settings->site_name,
                            $settings->site_url,
                            $tracking
                        ),
                        $whatsapp_body
                    );

                    try {

                        ob_start();
                        include('../../pdf/documentos/html/package_print.php');
                        $content = ob_get_clean();

                        $html2pdf = new Html2Pdf('P', 'A4', 'es', true, 'UTF-8', 0);
                        $html2pdf->pdf->SetDisplayMode('fullpage');
                        $html2pdf->writeHTML($content);
                        $contenidoPDF = $html2pdf->Output('', 'S');
                        $file_base64 = base64_encode($contenidoPDF);

                        $params = array(
                            'token' => $settings->api_ws_token,
                            'to' =>  $sender->phone,
                            'document' => $file_base64,
                            'caption' => $final_template_whatsapp_description,
                            'filename' => 'Factura_' . $tracking . '.pdf',

                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL =>  $settings->api_ws_url . "messages/document",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYHOST => 0,
                            CURLOPT_SSL_VERIFYPEER => 0,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => http_build_query($params),
                            CURLOPT_HTTPHEADER => array(
                                "content-type: application/x-www-form-urlencoded"
                            ),
                        ));

                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        curl_close($curl);

                        if ($err) {
                            $result['message'] = "Error: cURL Error #" . $err;
                        } else {
                            $result['success'] = true;
                            $result['message'] = "Notificación enviada correctamente";
                        }
                    } catch (Html2PdfException $e) {
                        $html2pdf->clean();
                        $formatter = new ExceptionFormatter($e);
                        echo $formatter->getHtmlMessage();
                    }
                }
            }
        }
    } else {
        $result['message'] = "Error: La API de WhatsApp no está activada";
    }
}





function sendNotificationWhatsAppWithPDFPackagess($sender, $package_id, $notification_template)
{

    $db = new Conexion;
    $db->cdp_query("SELECT * FROM cdb_settings");

    $db->cdp_execute();
    $settings = $db->cdp_registro();
    $numrows = $db->cdp_rowCount();

    if ($numrows > 0) {

        $config_lang = $settings->language;

        switch ($config_lang) {
            case "fr":
                //echo "PAGE FR";
                include("../../helpers/languages/$config_lang.php"); //include check session FR
                break;
            case "br":
                //echo "PAGE BRAZIL";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "ar":

                include("../../helpers/languages/$config_lang.php");
                break;
            case "es":
                //echo "PAGE ES";
                include("../../helpers/languages/$config_lang.php");
                break;
            case "en":
                //echo "PAGE EN";
                include("../../helpers/languages/$config_lang.php");
                break;
            default:
                //echo "PAGE EN - Setting Default";
                include("../../helpers/languages/$config_lang.php"); //include EN in all other cases of different lang detection
                break;
        }
    }



    $core = new Core;
    $db = new Conexion;

    $settings = cdp_getSettingsCourier();

    $result = [
        'success' => false,
        'message' => ''
    ];


    if (intval($settings->active_whatsapp) == 1) {


        if ($notification_template !== null) {

            $default_notification_templates = getDefaultTemplateActiveWhatsApp($notification_template);

            if (intval($default_notification_templates->active) == 1 && $default_notification_templates->id_template !== null) {
                $whatsapp_template = getTemplateWhatsApp($default_notification_templates->id_template);
                $whatsapp_body = $whatsapp_template->body;

                if ($whatsapp_body !== null) {
                    $db->cdp_query("SELECT * FROM cdb_customers_packages WHERE order_id='" . $package_id . "'");
                    $package = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_styles where id= '" . $package->status_courier . "'");
                    $status_courier = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $package->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $package->order_courier . "'");
                    $courier_com = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $package->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $package->order_service_options . "'");
                    $order_service_options = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_packaging where id= '" . $package->order_package . "'");
                    $packaging = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $package->order_deli_time . "'");
                    $delivery_time = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_branchoffices where id= '" . $package->agency . "'");
                    $branchoffices = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_offices where id= '" . $package->origin_off . "'");
                    $offices = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $package->order_prefix . $package->order_no . "'");
                    $address_order = $db->cdp_registro();


                    $db->cdp_query("SELECT * FROM cdb_customers_packages_detail WHERE  order_id='" .  $package->order_id . "'");
                    $order_items = $db->cdp_registros();

                    $dias_ = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
                    $meses_ = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec');


                    $fecha = date("Y-m-d :h:i A", strtotime($package->order_datetime));
                    $tracking = $package->order_prefix . $package->order_no;

                    $logo_src = "../../assets/";
                    $final_template_whatsapp_description = str_replace(
                        array(
                            '[CUSTOMER_FULLNAME]',
                            '[COMPANY_NAME]',
                            '[COMPANY_SITE_URL]',
                            '[TRACKING_NUMBER]',
                        ),
                        array(
                            $sender->fname . ' ' . $sender->lname,
                            $settings->site_name,
                            $settings->site_url,
                            $tracking
                        ),
                        $whatsapp_body
                    );

                    try {

                        ob_start();
                        include('../../pdf/documentos/html/shipment_print.php');
                        $content = ob_get_clean();

                        $html2pdf = new Html2Pdf('P', 'A4', 'es', true, 'UTF-8', 0);
                        $html2pdf->pdf->SetDisplayMode('fullpage');
                        $html2pdf->writeHTML($content);
                        $contenidoPDF = $html2pdf->Output('', 'S');
                        $file_base64 = base64_encode($contenidoPDF);

                        $params = array(
                            'token' => $settings->api_ws_token,
                            'to' =>  $sender->phone,
                            'document' => $file_base64,
                            'caption' => $final_template_whatsapp_description,
                            'filename' => 'Factura_' . $tracking . '.pdf',

                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL =>  $settings->api_ws_url . "messages/document",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYHOST => 0,
                            CURLOPT_SSL_VERIFYPEER => 0,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => http_build_query($params),
                            CURLOPT_HTTPHEADER => array(
                                "content-type: application/x-www-form-urlencoded"
                            ),
                        ));

                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        curl_close($curl);

                        if ($err) {
                            $result['message'] = "Error: cURL Error #" . $err;
                        } else {
                            $result['success'] = true;
                            $result['message'] = "Notificación enviada correctamente";
                        }
                    } catch (Html2PdfException $e) {
                        $html2pdf->clean();
                        $formatter = new ExceptionFormatter($e);
                        echo $formatter->getHtmlMessage();
                    }
                }
            }
        }
    } else {
        $result['message'] = "Error: La API de WhatsApp no está activada";
    }
}



























function sendNotificationWhatsAppWithPDFConsolidate($sender, $consolidate_id, $notification_template)
{
    $core = new Core;
    $db = new Conexion;

    $settings = cdp_getSettingsCourier();

    $result = [
        'success' => false,
        'message' => ''
    ];


    if (intval($settings->active_whatsapp) == 1) {


        if ($notification_template !== null) {

            $default_notification_templates = getDefaultTemplateActiveWhatsApp($notification_template);

            if (intval($default_notification_templates->active) == 1 && $default_notification_templates->id_template !== null) {
                $whatsapp_template = getTemplateWhatsApp($default_notification_templates->id_template);
                $whatsapp_body = $whatsapp_template->body;

                if ($whatsapp_body !== null) {

                    $db->cdp_query("SELECT * FROM cdb_consolidate WHERE consolidate_id='" . $consolidate_id . "'");
                    $package = $db->cdp_registro();

                    $tracking = $package->order_prefix . $package->order_no;

                    $db->cdp_query("SELECT * FROM cdb_consolidate_detail WHERE consolidate_id='" .  $package->consolidate_id . "'");
                    $paquetes_detalles = $db->cdp_registros();

                    $db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $package->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $package->order_courier . "'");
                    $courier_com = $db->cdp_registro();

                    $fecha = date("Y-m-d :h:i A", strtotime($package->order_datetime));

                    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $package->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $package->recipient_id . "'");
                    $recipients_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $tracking . "'");
                    $address_order = $db->cdp_registro();

                    $logo_src = "../../assets/";

                    $final_template_whatsapp_description = str_replace(
                        array(
                            '[CUSTOMER_FULLNAME]',
                            '[COMPANY_NAME]',
                            '[COMPANY_SITE_URL]',
                            '[TRACKING_NUMBER]',
                        ),
                        array(
                            $sender->fname . ' ' . $sender->lname,
                            $settings->site_name,
                            $settings->site_url,
                            $tracking
                        ),
                        $whatsapp_body
                    );


                    try {

                        ob_start();
                        include('../../pdf/documentos/html/shipment_export_pdf.php');
                        $content = ob_get_clean();

                        $html2pdf = new Html2Pdf('P', 'A4', 'es', true, 'UTF-8', 0);
                        $html2pdf->pdf->SetDisplayMode('fullpage');
                        $html2pdf->writeHTML($content);
                        $contenidoPDF = $html2pdf->Output('', 'S');
                        $file_base64 = base64_encode($contenidoPDF);

                        $params = array(
                            'token' => $settings->api_ws_token,
                            'to' =>  $sender->phone,
                            'document' => $file_base64,
                            'caption' => $final_template_whatsapp_description,
                            'filename' => 'Factura_' . $tracking . '.pdf',

                        );

                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL =>  $settings->api_ws_url . "messages/document",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_SSL_VERIFYHOST => 0,
                            CURLOPT_SSL_VERIFYPEER => 0,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => http_build_query($params),
                            CURLOPT_HTTPHEADER => array(
                                "content-type: application/x-www-form-urlencoded"
                            ),
                        ));

                        $response = curl_exec($curl);
                        $err = curl_error($curl);
                        curl_close($curl);

                        if ($err) {
                            $result['message'] = "Error: cURL Error #" . $err;
                        } else {
                            $result['success'] = true;
                            $result['message'] = "Notificación enviada correctamente";
                        }
                    } catch (Html2PdfException $e) {
                        $html2pdf->clean();
                        $formatter = new ExceptionFormatter($e);
                        echo $formatter->getHtmlMessage();
                    }
                }
            }
        }
    } else {
        $result['message'] = "Error: La API de WhatsApp no está activada";
    }
}
