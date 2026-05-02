<?php
/**
 * Import Excel/CSV courier: preview (parse + tarifas) y creación masiva.
 * Reutiliza lógica de add_courier_ajax (mismas tablas, cdp_calculateTariffServerSide).
 */
ini_set('display_errors', 0);

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once("../../helpers/querys.php");
require_once("../../helpers/vendor/autoload.php");
require_login();
require_permission('view_shipment_list');

$user  = new User;
$core  = new Core;
$db    = new Conexion;

$action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');

if ($action !== 'template' && $action !== 'reference') {
    header('Content-Type: application/json; charset=UTF-8');
}

// ---------- PREVIEW: parsear archivo y devolver filas con tarifa por fila ----------
if ($action === 'preview') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No se subió ningún archivo o hubo error.']);
        exit;
    }
    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
        echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos Excel (xls, xlsx) o CSV.']);
        exit;
    }
    if ($file['size'] > 5242880) {
        echo json_encode(['success' => false, 'error' => 'El archivo no debe superar 5MB.']);
        exit;
    }

    $rows = [];
    if ($ext === 'csv') {
        $fp = fopen($file['tmp_name'], 'r');
        if ($fp === false) {
            echo json_encode(['success' => false, 'error' => 'No se pudo abrir el archivo CSV.']);
            exit;
        }
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }
        while (($line = fgetcsv($fp, 0, ',', '"', '')) !== false) {
            $rows[] = $line;
        }
        fclose($fp);
    } else {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            echo json_encode(['success' => false, 'error' => 'Para archivos Excel (xls/xlsx) instale PhpSpreadsheet: composer require phpoffice/phpspreadsheet. Use la plantilla CSV mientras tanto.']);
            exit;
        }
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al leer el archivo: ' . $e->getMessage()]);
            exit;
        }
    }

    $settings   = cdp_getSettingsCourier();
    $meter      = isset($settings->meter) ? (float)$settings->meter : 0.0;
    $infoShip   = cdp_getInfoShipDefault();
    $defAgency  = cdp_getDefaultAgencyOrigin();
    $defaults   = [
        'agency'               => $defAgency['agency'],
        'origin_off'           => $defAgency['origin_off'],
        'order_item_category'  => $infoShip ? (int)$infoShip->logistics_default1 : 0,
        'order_package'        => $infoShip ? (int)$infoShip->packaging_default2 : 0,
        'order_courier'        => $infoShip ? (int)$infoShip->courier_default3 : 0,
        'order_deli_time'      => $infoShip ? (int)$infoShip->time_default5 : 0,
        'order_payment_method' => $infoShip ? (int)$infoShip->payment_default7 : 0,
        'status_courier'       => $infoShip ? (int)$infoShip->status_default8 : 0,
    ];

    $tariffNotFoundMsg = isset($lang['tariff_no_configured']) ? $lang['tariff_no_configured'] : 'No hay tarifa configurada para la ruta/modo/peso.';
    $validCategories   = cdp_getAllCategories();
    $errMissingSender  = isset($lang['courier_import_err_sender_id']) ? $lang['courier_import_err_sender_id'] : 'Falta sender_id';
    $errMissingSenderAddr = isset($lang['courier_import_err_sender_address']) ? $lang['courier_import_err_sender_address'] : 'Falta sender_address_id';
    $errMissingRecipient = isset($lang['courier_import_err_recipient_id']) ? $lang['courier_import_err_recipient_id'] : 'Falta recipient_id';
    $errMissingRecipientAddr = isset($lang['courier_import_err_recipient_address']) ? $lang['courier_import_err_recipient_address'] : 'Falta recipient_address_id';
    $errMissingService  = isset($lang['courier_import_err_service_options']) ? $lang['courier_import_err_service_options'] : 'Falta order_service_options (modo envío)';
    $errWeightRequired = isset($lang['courier_import_err_weight']) ? $lang['courier_import_err_weight'] : 'Peso debe ser mayor que 0';
    $errSenderNotExist  = isset($lang['courier_import_err_sender_not_found']) ? $lang['courier_import_err_sender_not_found'] : 'sender_id no existe';
    $errSenderAddrNotExist = isset($lang['courier_import_err_sender_address_not_found']) ? $lang['courier_import_err_sender_address_not_found'] : 'sender_address_id no existe';
    $errRecipientNotExist = isset($lang['courier_import_err_recipient_not_found']) ? $lang['courier_import_err_recipient_not_found'] : 'recipient_id no existe';
    $errRecipientAddrNotExist = isset($lang['courier_import_err_recipient_address_not_found']) ? $lang['courier_import_err_recipient_address_not_found'] : 'recipient_address_id no existe';
    $errCategoryInvalid = isset($lang['courier_import_err_category_invalid']) ? $lang['courier_import_err_category_invalid'] : 'order_service_options no coincide con cdb_category';
    $validOptionsLabel  = isset($lang['courier_import_valid_options']) ? $lang['courier_import_valid_options'] : 'Opciones válidas';

    $outRows = [];
    $headers = null;
    $colMap  = null;
    $idx     = 0;

    foreach ($rows as $row) {
        if ($idx === 0 && isset($row[0]) && is_string($row[0]) && (strpos($row[0], 'sender') !== false || strpos($row[0], 'remitente') !== false)) {
            $headers = $row;
            $colMap = [];
            foreach ($headers as $ci => $h) {
                $key = strtolower(trim(str_replace([' ', '-'], '_', preg_replace('/[^a-z0-9_\s-]/i', '', $h))));
                if ($key !== '') {
                    $colMap[$key] = $ci;
                }
            }
            $idx++;
            continue;
        }
        $idx++;
        $get = function($key, $default = '') use ($row, $colMap) {
            if ($colMap !== null && isset($colMap[$key])) {
                $i = $colMap[$key];
                return isset($row[$i]) ? $row[$i] : $default;
            }
            $idxMap = ['sender_id' => 0, 'sender_address_id' => 1, 'recipient_id' => 2, 'recipient_address_id' => 3, 'order_service_options' => 4, 'quantity' => 5, 'weight' => 6, 'length' => 7, 'width' => 8, 'height' => 9, 'description' => 10, 'declared_value' => 11, 'distance_miles' => 12, 'manual_tariff' => 13, 'price_lb' => 14, 'country_origin_id' => 15, 'country_destination_id' => 16];
            $i = $idxMap[$key] ?? null;
            return ($i !== null && isset($row[$i])) ? $row[$i] : $default;
        };

        $sender_id            = (int)$get('sender_id', 0);
        $sender_address_id    = (int)$get('sender_address_id', 0);
        $recipient_id         = (int)$get('recipient_id', 0);
        $recipient_address_id = (int)$get('recipient_address_id', 0);
        $order_service_options= (int)$get('order_service_options', 0);
        $quantity             = (float)$get('quantity', 1);
        $weight               = (float)$get('weight', 0);
        $length               = (float)$get('length', 0);
        $width                = (float)$get('width', 0);
        $height               = (float)$get('height', 0);
        $description          = trim((string)$get('description', ''));
        $declared_value       = (float)$get('declared_value', 0);
        $distance_miles       = (float)$get('distance_miles', 0);
        $manual_tariff        = (int)$get('manual_tariff', 0);
        $price_lb             = (float)$get('price_lb', 0);
        $country_origin_id    = (int)$get('country_origin_id', 0);
        $country_destination_id = (int)$get('country_destination_id', 0);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $sender_name = '';
        $sender_address_label = '';
        $recipient_name = '';
        $recipient_address_label = '';
        $order_service_options_name = '';
        $s = $sender_id ? cdp_getSenderCourier($sender_id) : null;
        if ($s) {
            $sender_name = trim(($s->fname ?? '') . ' ' . ($s->lname ?? ''));
            if ($sender_name === '') {
                $sender_name = isset($s->username) ? (string)$s->username : 'ID ' . $sender_id;
            }
        }
        $sa = $sender_address_id ? cdp_getSenderAddress($sender_address_id) : null;
        if ($sa) {
            $sender_address_label = trim(($sa->city ?? '') . ', ' . ($sa->address ?? ''));
            if ($sender_address_label === ',') {
                $sender_address_label = 'ID ' . $sender_address_id;
            }
        }
        $r = $recipient_id ? cdp_getRecipientCourier($recipient_id) : null;
        if ($r) {
            $recipient_name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
            if ($recipient_name === '') {
                $recipient_name = isset($r->email) ? (string)$r->email : 'ID ' . $recipient_id;
            }
        }
        $ra = $recipient_address_id ? cdp_getRecipientAddress($recipient_address_id) : null;
        if ($ra) {
            $recipient_address_label = trim(($ra->city ?? '') . ', ' . ($ra->address ?? ''));
            if ($recipient_address_label === ',') {
                $recipient_address_label = 'ID ' . $recipient_address_id;
            }
        }
        $cat = $order_service_options ? cdp_getorderitemcategory($order_service_options) : null;
        if ($cat && isset($cat->name_item)) {
            $order_service_options_name = $cat->name_item;
        }
        $country_origin_name = '';
        $country_destination_name = '';
        if ($country_origin_id > 0) {
            $co = cdp_getCountry($country_origin_id);
            $country_origin_name = isset($co['data']->name) ? $co['data']->name : '';
        } elseif ($sa && !empty($sa->country)) {
            $co = cdp_getCountry($sa->country);
            $country_origin_name = isset($co['data']->name) ? $co['data']->name : '';
        }
        if ($country_destination_id > 0) {
            $cd = cdp_getCountry($country_destination_id);
            $country_destination_name = isset($cd['data']->name) ? $cd['data']->name : '';
        } elseif ($ra && !empty($ra->country)) {
            $cd = cdp_getCountry($ra->country);
            $country_destination_name = isset($cd['data']->name) ? $cd['data']->name : '';
        }

        $rowError = '';
        if (!$sender_id) {
            $rowError = $errMissingSender;
        } elseif (!$sender_address_id) {
            $rowError = $errMissingSenderAddr;
        } elseif (!$recipient_id) {
            $rowError = $errMissingRecipient;
        } elseif (!$recipient_address_id) {
            $rowError = $errMissingRecipientAddr;
        } elseif (!$order_service_options) {
            $rowError = $errMissingService;
        } elseif ($weight < 1) {
            $rowError = isset($lang['courier_import_err_weight_min']) ? $lang['courier_import_err_weight_min'] : 'El peso debe ser al menos 1 libra.';
        } else {
            if (!cdp_getSenderCourier($sender_id)) {
                $rowError = $errSenderNotExist;
            } elseif (!cdp_getSenderAddress($sender_address_id)) {
                $rowError = $errSenderAddrNotExist;
            } elseif (!cdp_getRecipientCourier($recipient_id)) {
                $rowError = $errRecipientNotExist;
            } elseif (!cdp_getRecipientAddress($recipient_address_id)) {
                $rowError = $errRecipientAddrNotExist;
            } else {
                $dbCat = new Conexion;
                $dbCat->cdp_query('SELECT id FROM cdb_category WHERE id = :id LIMIT 1');
                $dbCat->bind(':id', $order_service_options);
                $dbCat->cdp_execute();
                if (!$dbCat->cdp_registro()) {
                    $rowError = $errCategoryInvalid;
                }
            }
        }

        if ($rowError) {
            $outRows[] = [
                'sender_id'             => $sender_id,
                'sender_address_id'     => $sender_address_id,
                'recipient_id'          => $recipient_id,
                'recipient_address_id'  => $recipient_address_id,
                'order_service_options' => $order_service_options,
                'quantity'              => $quantity,
                'weight'                => $weight,
                'length'                => $length,
                'width'                 => $width,
                'height'                => $height,
                'description'           => $description,
                'declared_value'        => $declared_value,
                'distance_miles'        => $distance_miles,
                'manual_tariff'         => $manual_tariff,
                'price_lb'              => $price_lb,
                'row_error'             => $rowError,
                'tariff_error'          => '',
                'chargeable_weight'     => null,
                'price_base'            => null,
                'cargo_millas'          => null,
                'total_tarifa'          => null,
                'price_lb_derived'      => null,
                'sender_name'           => $sender_name,
                'sender_address_label'  => $sender_address_label,
                'recipient_name'        => $recipient_name,
                'recipient_address_label' => $recipient_address_label,
                'order_service_options_name' => $order_service_options_name,
                'country_origin_id'       => $country_origin_id,
                'country_destination_id'  => $country_destination_id,
                'country_origin_name'     => $country_origin_name,
                'country_destination_name'=> $country_destination_name,
            ];
            continue;
        }

        $packages = [[
            'qty'            => $quantity,
            'weight'         => $weight,
            'length'         => $length,
            'width'          => $width,
            'height'         => $height,
            'description'    => $description,
            'declared_value' => $declared_value,
            'fixed_value'    => 0,
        ]];

        $chargeable_weight = null;
        $price_base        = null;
        $cargo_millas      = null;
        $total_tarifa      = null;
        $price_lb_derived  = null;
        $tariff_error      = '';

        if ($manual_tariff === 0) {
            $tariffResult = cdp_calculateTariffServerSide(
                $sender_id,
                $sender_address_id,
                $recipient_id,
                $recipient_address_id,
                $order_service_options,
                $packages,
                $distance_miles,
                $meter
            );
            if ($tariffResult === null) {
                $tariff_error = $tariffNotFoundMsg;
            } else {
                $chargeable_weight = $tariffResult['chargeable_weight'];
                $price_base        = $tariffResult['price_base'];
                $cargo_millas      = $tariffResult['cargo_millas'];
                $total_tarifa      = $tariffResult['total_tarifa'];
                $price_lb_derived   = isset($tariffResult['price_lb_derived']) ? $tariffResult['price_lb_derived'] : null;
            }
        } else {
            if ($price_lb <= 0) {
                $tariff_error = 'Con tarifa manual debe indicar price_lb.';
            } else {
                $chargeable_weight = $weight * $quantity;
                $total_tarifa      = round($chargeable_weight * $price_lb, 2);
                $price_base        = $total_tarifa;
                $cargo_millas      = 0;
                $price_lb_derived  = $price_lb;
            }
        }

        $outRows[] = [
            'sender_id'             => $sender_id,
            'sender_address_id'     => $sender_address_id,
            'recipient_id'          => $recipient_id,
            'recipient_address_id'  => $recipient_address_id,
            'order_service_options' => $order_service_options,
            'quantity'              => $quantity,
            'weight'                => $weight,
            'length'                => $length,
            'width'                 => $width,
            'height'                => $height,
            'description'           => $description,
            'declared_value'        => $declared_value,
            'distance_miles'        => $distance_miles,
            'manual_tariff'         => $manual_tariff,
            'price_lb'              => $price_lb,
            'row_error'             => '',
            'tariff_error'          => $tariff_error,
            'chargeable_weight'     => $chargeable_weight,
            'price_base'            => $price_base,
            'cargo_millas'          => $cargo_millas,
            'total_tarifa'          => $total_tarifa,
            'price_lb_derived'      => $price_lb_derived,
            'sender_name'           => $sender_name,
            'sender_address_label'  => $sender_address_label,
            'recipient_name'        => $recipient_name,
            'recipient_address_label' => $recipient_address_label,
            'order_service_options_name' => $order_service_options_name,
            'country_origin_id'       => $country_origin_id,
            'country_destination_id'  => $country_destination_id,
            'country_origin_name'     => $country_origin_name,
            'country_destination_name'=> $country_destination_name,
        ];
    }

    $validCategoriesForJson = [];
    foreach ($validCategories as $vc) {
        $validCategoriesForJson[] = ['id' => (int)$vc->id, 'name_item' => isset($vc->name_item) ? $vc->name_item : ''];
    }

    // Lista de remitentes (userlevel=1) para select
    $sendersForJson = [];
    $dbS = new Conexion;
    $dbS->cdp_query("SELECT id, fname, lname FROM cdb_users WHERE userlevel = '1' ORDER BY fname, lname");
    $dbS->cdp_execute();
    $sendersList = $dbS->cdp_registros();
    if (is_array($sendersList)) {
        foreach ($sendersList as $u) {
            $sendersForJson[] = ['id' => (int)$u->id, 'text' => trim(($u->fname ?? '') . ' ' . ($u->lname ?? '')) ?: 'ID ' . $u->id];
        }
    }

    // Opciones por fila: direcciones por sender, destinatarios por sender, direcciones por recipient
    $uniqueSenderIds = [];
    $uniqueRecipientIds = [];
    foreach ($outRows as $r) {
        if (!empty($r['sender_id'])) {
            $uniqueSenderIds[$r['sender_id']] = true;
        }
        if (!empty($r['recipient_id'])) {
            $uniqueRecipientIds[$r['recipient_id']] = true;
        }
    }
    $uniqueSenderIds = array_keys($uniqueSenderIds);
    $uniqueRecipientIds = array_keys($uniqueRecipientIds);

    $sender_addresses = [];
    $recipientsBySender = [];
    $recipient_addresses = [];

    foreach ($uniqueSenderIds as $sid) {
        $dbA = new Conexion;
        $dbA->cdp_query('SELECT id_addresses, address, city FROM cdb_senders_addresses WHERE user_id = :uid');
        $dbA->bind(':uid', $sid);
        $dbA->cdp_execute();
        $addrs = $dbA->cdp_registros();
        $sender_addresses[(string)$sid] = [];
        if (is_array($addrs)) {
            foreach ($addrs as $a) {
                $label = trim(($a->city ?? '') . ', ' . ($a->address ?? ''));
                if ($label === ',') {
                    $label = 'ID ' . $a->id_addresses;
                }
                $sender_addresses[(string)$sid][] = ['id' => (int)$a->id_addresses, 'text' => $label];
            }
        }

        $dbR = new Conexion;
        $dbR->cdp_query('SELECT id, fname, lname FROM cdb_recipients WHERE sender_id = :sid ORDER BY fname, lname');
        $dbR->bind(':sid', $sid);
        $dbR->cdp_execute();
        $recs = $dbR->cdp_registros();
        $recipientsBySender[(string)$sid] = [];
        if (is_array($recs)) {
            foreach ($recs as $rec) {
                $recipientsBySender[(string)$sid][] = ['id' => (int)$rec->id, 'text' => trim(($rec->fname ?? '') . ' ' . ($rec->lname ?? '')) ?: 'ID ' . $rec->id];
            }
        }
    }

    foreach ($uniqueRecipientIds as $rid) {
        $dbRA = new Conexion;
        $dbRA->cdp_query('SELECT id_addresses, address, city FROM cdb_recipients_addresses WHERE recipient_id = :rid');
        $dbRA->bind(':rid', $rid);
        $dbRA->cdp_execute();
        $addrs = $dbRA->cdp_registros();
        $recipient_addresses[(string)$rid] = [];
        if (is_array($addrs)) {
            foreach ($addrs as $a) {
                $label = trim(($a->city ?? '') . ', ' . ($a->address ?? ''));
                if ($label === ',') {
                    $label = 'ID ' . $a->id_addresses;
                }
                $recipient_addresses[(string)$rid][] = ['id' => (int)$a->id_addresses, 'text' => $label];
            }
        }
    }

    echo json_encode([
        'success'             => true,
        'rows'                => $outRows,
        'defaults'            => $defaults,
        'valid_categories'    => $validCategoriesForJson,
        'senders'              => $sendersForJson,
        'sender_addresses'     => $sender_addresses,
        'recipients_by_sender' => $recipientsBySender,
        'recipient_addresses'  => $recipient_addresses,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- CREATE: crear envíos uno por uno con la misma lógica que add_courier_ajax ----------
if ($action === 'create') {
    $jsonRows = isset($_POST['rows']) ? $_POST['rows'] : '';
    if (is_string($jsonRows)) {
        $decoded = json_decode($jsonRows, true);
        $rows    = is_array($decoded) ? $decoded : [];
    } else {
        $rows = is_array($jsonRows) ? $jsonRows : [];
    }

    if (empty($rows)) {
        echo json_encode(['success' => false, 'error' => 'No hay filas para crear.']);
        exit;
    }

    $settings  = cdp_getSettingsCourier();
    $meter     = (float)($settings->meter ?? 0);
    $infoShip  = cdp_getInfoShipDefault();
    $defAgency = cdp_getDefaultAgencyOrigin();
    $min_cost_tax          = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;
    $order_prefix          = $settings->prefix ?? '';
    $code_prefix           = $order_prefix;

    $default_agency               = $defAgency['agency'];
    $default_origin_off           = $defAgency['origin_off'];
    $default_order_item_category   = $infoShip ? (int)$infoShip->logistics_default1 : 0;
    $default_order_package         = $infoShip ? (int)$infoShip->packaging_default2 : 0;
    $default_order_courier         = $infoShip ? (int)$infoShip->courier_default3 : 0;
    $default_order_deli_time       = $infoShip ? (int)$infoShip->time_default5 : 0;
    $default_order_payment_method  = $infoShip ? (int)$infoShip->payment_default7 : 0;
    $default_status_courier        = $infoShip ? (int)$infoShip->status_default8 : 0;

    $payment_methods = $default_order_payment_method ? cdp_getPaymentMethodCourier($default_order_payment_method) : null;
    $days            = $payment_methods ? intval($payment_methods->days) : 0;
    $sale_date       = date("Y-m-d H:i:s");
    $due_date        = $days === 0 ? $sale_date : cdp_sumardias($sale_date, $days);
    $status_invoice  = ($days === 0) ? 1 : 2;

    $created = 0;
    $failed  = 0;
    $errors  = [];

    $tariffNotFoundMsg = isset($lang['tariff_no_configured']) ? $lang['tariff_no_configured'] : 'No hay tarifa configurada.';

    $errInvalidService = isset($lang['courier_import_err_category_invalid']) ? $lang['courier_import_err_category_invalid'] : 'Modo de envío inválido.';
    $errIncomplete = isset($lang['courier_import_err_incomplete']) ? $lang['courier_import_err_incomplete'] : 'Datos incompletos.';
    $errWeightMin = isset($lang['courier_import_err_weight_min']) ? $lang['courier_import_err_weight_min'] : 'El peso debe ser al menos 1 libra.';

    foreach ($rows as $i => $row) {
        $manual_tariff = (int)(isset($row['manual_tariff']) ? $row['manual_tariff'] : 0);
        $sender_id             = (int)($row['sender_id'] ?? 0);
        $sender_address_id     = (int)($row['sender_address_id'] ?? 0);
        $recipient_id          = (int)($row['recipient_id'] ?? 0);
        $recipient_address_id  = (int)($row['recipient_address_id'] ?? 0);
        $order_service_options = (int)($row['order_service_options'] ?? 0);
        $weight                = (float)($row['weight'] ?? 0);

        if (!$sender_id || !$sender_address_id || !$recipient_id || !$recipient_address_id || !$order_service_options) {
            $failed++;
            $errors[] = 'Fila ' . ($i + 1) . ': ' . $errIncomplete;
            continue;
        }
        if ($weight < 1) {
            $failed++;
            $errors[] = 'Fila ' . ($i + 1) . ': ' . $errWeightMin;
            continue;
        }
        $catCheck = cdp_getorderitemcategory($order_service_options);
        if (!$catCheck || !isset($catCheck->id)) {
            $failed++;
            $errors[] = 'Fila ' . ($i + 1) . ': ' . $errInvalidService;
            continue;
        }
        $tariffErr = isset($row['tariff_error']) ? trim($row['tariff_error']) : '';
        if ($manual_tariff === 0 && $tariffErr !== '') {
            $failed++;
            $errors[] = 'Fila ' . ($i + 1) . ': ' . $tariffNotFoundMsg;
            continue;
        }
        $quantity              = (float)($row['quantity'] ?? 1);
        $length                = (float)($row['length'] ?? 0);
        $width                 = (float)($row['width'] ?? 0);
        $height                = (float)($row['height'] ?? 0);
        $description           = trim($row['description'] ?? '');
        $declared_value        = (float)($row['declared_value'] ?? 0);
        $distance_miles        = (float)($row['distance_miles'] ?? 0);
        $price_lb              = (float)($row['price_lb'] ?? 0);
        $country_origin_id     = (int)($row['country_origin_id'] ?? 0);
        $country_destination_id = (int)($row['country_destination_id'] ?? 0);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $packages = [[
            'qty'            => $quantity,
            'weight'         => $weight,
            'length'         => $length,
            'width'          => $width,
            'height'         => $height,
            'description'    => $description,
            'declared_value' => $declared_value,
            'fixed_value'    => 0,
        ]];

        if ($manual_tariff === 0) {
            $tariffResult = cdp_calculateTariffServerSide(
                $sender_id,
                $sender_address_id,
                $recipient_id,
                $recipient_address_id,
                $order_service_options,
                $packages,
                $distance_miles,
                $meter
            );
            if ($tariffResult === null) {
                $failed++;
                $errors[] = 'Fila ' . ($i + 1) . ': ' . $tariffNotFoundMsg;
                continue;
            }
            $sumador_total = $tariffResult['total_tarifa'];
            $price_lb      = isset($tariffResult['price_lb_derived']) ? $tariffResult['price_lb_derived'] : ($tariffResult['total_tarifa'] / max(0.01, $tariffResult['chargeable_weight']));
        } else {
            $sumador_total = (float)($row['total_tarifa'] ?? 0);
            if ($sumador_total <= 0 && $price_lb > 0) {
                $cw = $weight * $quantity;
                $sumador_total = round($cw * $price_lb, 2);
            }
        }

        $order_no = $core->cdp_order_track();
        $date     = date('Y-m-d H:i:s');

        $dataShipment = [
            'user_id'               => $_SESSION['userid'],
            'order_prefix'           => $code_prefix,
            'is_pickup'              => false,
            'order_incomplete'       => 1,
            'order_no'               => $order_no,
            'order_datetime'         => $date,
            'sender_id'              => $sender_id,
            'recipient_id'           => $recipient_id,
            'sender_address_id'      => $sender_address_id,
            'recipient_address_id'   => $recipient_address_id,
            'order_date'             => $date,
            'agency'                 => $default_agency,
            'origin_off'             => $default_origin_off,
            'order_package'          => $default_order_package,
            'order_item_category'    => $default_order_item_category,
            'order_courier'          => $default_order_courier,
            'order_service_options'  => $order_service_options,
            'order_deli_time'        => $default_order_deli_time,
            'order_payment_method'   => $default_order_payment_method,
            'status_courier'         => $default_status_courier,
            'driver_id'              => $_SESSION['userid'],
            'due_date'               => $due_date,
            'status_invoice'         => $status_invoice,
            'volumetric_percentage'  => $meter,
            'manual_tariff'          => $manual_tariff,
        ];

        $shipment_id = cdp_insertCourierShipment($dataShipment);
        if ($shipment_id === null) {
            $failed++;
            $errors[] = 'Fila ' . ($i + 1) . ': Error al insertar envío.';
            continue;
        }

        $pkg = $packages[0];
        $dataAddresses = [
            'order_id'       => $shipment_id,
            'qty'            => $pkg['qty'],
            'description'    => $pkg['description'],
            'length'         => $pkg['length'],
            'width'          => $pkg['width'],
            'height'         => $pkg['height'],
            'weight'         => $pkg['weight'],
            'declared_value' => $pkg['declared_value'],
            'fixed_value'    => $pkg['fixed_value'],
        ];
        cdp_insertCourierShipmentPackages($dataAddresses);

        $sender_address_data  = cdp_getSenderAddress($sender_address_id);
        $recipient_address_data = cdp_getRecipientAddress($recipient_address_id);
        $sender_country_id   = $country_origin_id > 0 ? $country_origin_id : (isset($sender_address_data->country) ? (int)$sender_address_data->country : 0);
        $recipient_country_id = $country_destination_id > 0 ? $country_destination_id : (isset($recipient_address_data->country) ? (int)$recipient_address_data->country : 0);
        $_sender_country     = $sender_country_id ? cdp_getCountry($sender_country_id) : ['data' => null];
        $_recipient_country  = $recipient_country_id ? cdp_getCountry($recipient_country_id) : ['data' => null];
        $sender_country_name  = isset($_sender_country['data']->name) ? $_sender_country['data']->name : '';
        $recipient_country_name = isset($_recipient_country['data']->name) ? $_recipient_country['data']->name : '';
        if ($sender_country_name === '' && $sender_address_data && !empty($sender_address_data->country)) {
            $cx = cdp_getCountry($sender_address_data->country);
            $sender_country_name = isset($cx['data']->name) ? $cx['data']->name : '';
        }
        if ($recipient_country_name === '' && $recipient_address_data && !empty($recipient_address_data->country)) {
            $cx = cdp_getCountry($recipient_address_data->country);
            $recipient_country_name = isset($cx['data']->name) ? $cx['data']->name : '';
        }
        $sender_state_name   = '';
        $sender_city_name    = '';
        $sender_zip_code     = isset($sender_address_data->zip_code) ? $sender_address_data->zip_code : '';
        $sender_address      = isset($sender_address_data->address) ? $sender_address_data->address : '';
        if ($sender_address_data && !empty($sender_address_data->state)) {
            $sst = cdp_getState($sender_address_data->state);
            $sender_state_name = isset($sst['data']->name) ? $sst['data']->name : '';
        }
        if ($sender_address_data && !empty($sender_address_data->city)) {
            $sct = cdp_getCity($sender_address_data->city);
            $sender_city_name = isset($sct['data']->name) ? $sct['data']->name : '';
        }
        $recipient_state_name = '';
        $recipient_city_name  = '';
        $recipient_zip_code   = isset($recipient_address_data->zip_code) ? $recipient_address_data->zip_code : '';
        $recipient_address    = isset($recipient_address_data->address) ? $recipient_address_data->address : '';
        if ($recipient_address_data && !empty($recipient_address_data->state)) {
            $rst = cdp_getState($recipient_address_data->state);
            $recipient_state_name = isset($rst['data']->name) ? $rst['data']->name : '';
        }
        if ($recipient_address_data && !empty($recipient_address_data->city)) {
            $rct = cdp_getCity($recipient_address_data->city);
            $recipient_city_name = isset($rct['data']->name) ? $rct['data']->name : '';
        }
        $dataAddressesShip = [
            'order_id'           => $shipment_id,
            'order_track'        => $code_prefix . $order_no,
            'sender_country'    => $sender_country_name,
            'sender_state'      => $sender_state_name,
            'sender_city'       => $sender_city_name,
            'sender_zip_code'   => $sender_zip_code,
            'sender_address'   => $sender_address,
            'recipient_country' => $recipient_country_name,
            'recipient_state'   => $recipient_state_name,
            'recipient_city'    => $recipient_city_name,
            'recipient_zip_code'=> $recipient_zip_code,
            'recipient_address' => $recipient_address,
        ];
        cdp_insertCourierShipmentAddresses($dataAddressesShip);

        $sumador_libras     = $weight * $quantity;
        $total_metric       = ($meter > 0) ? (($length * $width * $height) / $meter) * $quantity : 0;
        $sumador_volumetric = round($total_metric, 2);
        $sumador_libras     = round($sumador_libras, 2);
        $calculate_weight   = max($sumador_libras, $sumador_volumetric);
        $sumador_valor_declarado = $declared_value * $quantity;
        $max_fixed_charge    = 0;

        $tax_value          = 0;
        $declared_value_tax = 0;
        $insurance_value    = 0;
        $discount_value     = 0;
        $tariffs_value      = 0;
        $reexpedicion_value = 0;
        $insured_value      = 0;

        $total_impuesto            = 0;
        $total_valor_declarado     = 0;
        $total_descuento           = 0;
        $total_seguro              = 0;
        $total_peso                = $sumador_libras + $sumador_volumetric;
        $total_impuesto_aduanero   = 0;
        $total_envio               = $sumador_total;

        $dataShipmentUpdateTotals = [
            'order_id'                    => $shipment_id,
            'value_weight'                => (float)$price_lb,
            'sub_total'                   => (float)$sumador_total,
            'tax_discount'                => (float)$discount_value,
            'total_insured_value'         => (float)$insured_value,
            'tax_insurance_value'         => (float)$insurance_value,
            'tax_custom_tariffis_value'   => (float)$tariffs_value,
            'tax_value'                   => (float)$tax_value,
            'declared_value'              => (float)$declared_value_tax,
            'total_reexp'                 => (float)$reexpedicion_value,
            'total_declared_value'        => (float)$total_valor_declarado,
            'total_fixed_value'           => (float)$max_fixed_charge,
            'total_tax_discount'          => (float)$total_descuento,
            'total_tax_insurance'         => (float)$total_seguro,
            'total_tax_custom_tariffis'   => (float)$total_impuesto_aduanero,
            'total_tax'                   => (float)$total_impuesto,
            'total_weight'                => (float)$total_peso,
            'total_order'                 => (float)$total_envio,
        ];
        cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);

        $order_track = $code_prefix . $order_no;
        $dataTrack   = [
            'user_id'        => $_SESSION['userid'],
            'order_id'       => $shipment_id,
            'order_track'    => $order_track,
            't_date'         => date('Y-m-d H:i:s'),
            'status_courier' => $default_status_courier,
            'comments'       => isset($lang['notification_shipment8']) ? $lang['notification_shipment8'] : 'Envío creado',
            'office'         => $default_origin_off,
        ];
        cdp_insertCourierShipmentTrack($dataTrack);

        $created++;
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'failed'  => $failed,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- DOWNLOAD TEMPLATE (CSV con país origen/destino y columnas opcionales) ----------
if ($action === 'template') {
    $headers = [
        'sender_id', 'sender_address_id', 'recipient_id', 'recipient_address_id',
        'order_service_options', 'quantity', 'weight', 'length', 'width', 'height',
        'description', 'declared_value', 'distance_miles', 'manual_tariff', 'price_lb',
        'country_origin_id', 'country_destination_id'
    ];
    $example = [1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 'Ejemplo', 0, 0, 0, 0, '', ''];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="courier_import_template.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF"); // UTF-8 BOM para Excel
    fputcsv($fp, $headers);
    fputcsv($fp, $example);
    fclose($fp);
    exit;
}

// ---------- DOWNLOAD REFERENCE (IDs de remitentes, direcciones, destinatarios, países, modos) ----------
if ($action === 'reference') {
    $allCountries = function_exists('cdp_getAllCountries') ? cdp_getAllCountries() : [];
    $allCategories = cdp_getAllCategories();
    $db = new Conexion;
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="referencia_ids_importacion.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['TIPO', 'ID', 'NOMBRE_O_ETIQUETA', 'DETALLE']);
    $db->cdp_query("SELECT id, fname, lname, email FROM cdb_users WHERE userlevel = '1' ORDER BY fname, lname");
    $db->cdp_execute();
    $senders = $db->cdp_registros();
    if (is_array($senders)) {
        foreach ($senders as $s) {
            $name = trim(($s->fname ?? '') . ' ' . ($s->lname ?? ''));
            fputcsv($fp, ['REMITENTE', $s->id, $name ?: 'ID ' . $s->id, $s->email ?? '']);
        }
    }
    $db->cdp_query("SELECT sa.id_addresses, sa.user_id, sa.address, sa.city, sa.country FROM cdb_senders_addresses sa ORDER BY sa.user_id, sa.id_addresses");
    $db->cdp_execute();
    $addrs = $db->cdp_registros();
    if (is_array($addrs)) {
        foreach ($addrs as $a) {
            $countryName = '';
            if (!empty($a->country)) {
                $c = cdp_getCountry($a->country);
                $countryName = isset($c['data']->name) ? $c['data']->name : '';
            }
            $label = trim(($a->city ?? '') . ' - ' . ($a->address ?? '') . ($countryName ? ' (' . $countryName . ')' : ''));
            fputcsv($fp, ['DIR_REMITENTE', $a->id_addresses, $label ?: 'ID ' . $a->id_addresses, 'sender_id=' . $a->user_id]);
        }
    }
    $db->cdp_query("SELECT id, sender_id, fname, lname, email FROM cdb_recipients ORDER BY sender_id, fname, lname");
    $db->cdp_execute();
    $recs = $db->cdp_registros();
    if (is_array($recs)) {
        foreach ($recs as $r) {
            $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
            fputcsv($fp, ['DESTINATARIO', $r->id, $name ?: 'ID ' . $r->id, 'sender_id=' . $r->sender_id . ' | ' . ($r->email ?? '')]);
        }
    }
    $db->cdp_query("SELECT ra.id_addresses, ra.recipient_id, ra.address, ra.city, ra.country FROM cdb_recipients_addresses ra ORDER BY ra.recipient_id, ra.id_addresses");
    $db->cdp_execute();
    $raddrs = $db->cdp_registros();
    if (is_array($raddrs)) {
        foreach ($raddrs as $a) {
            $countryName = '';
            if (!empty($a->country)) {
                $c = cdp_getCountry($a->country);
                $countryName = isset($c['data']->name) ? $c['data']->name : '';
            }
            $label = trim(($a->city ?? '') . ' - ' . ($a->address ?? '') . ($countryName ? ' (' . $countryName . ')' : ''));
            fputcsv($fp, ['DIR_DESTINATARIO', $a->id_addresses, $label ?: 'ID ' . $a->id_addresses, 'recipient_id=' . $a->recipient_id]);
        }
    }
    foreach ($allCountries as $co) {
        fputcsv($fp, ['PAIS', $co->id, isset($co->name) ? $co->name : '', '']);
    }
    foreach ($allCategories as $vc) {
        fputcsv($fp, ['MODO_ENVIO', $vc->id, isset($vc->name_item) ? $vc->name_item : '', '']);
    }
    fclose($fp);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
exit;
