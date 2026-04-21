<?php
ini_set('display_errors', 0);
require_once("../../../loader.php");
require_once("../../../helpers/querys.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipping_tariffs');
require_csrf();

$user   = new User;
$core   = new Core;
$errors = array();

// Validaciones básicas
$tariff_id_edit = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($tariff_id_edit <= 0) {
    $errors[] = 'Tariff ID is required for update.';
}
if (empty($_POST['country_destiny']))
    $errors[] = $lang['validate_field_ajax1'];
if (empty($_POST['state_destinystates']))
    $errors[] = $lang['validate_field_ajax3'];
if (empty($_POST['city_destinycities']))
    $errors[] = $lang['validate_field_ajax2'];
if (empty($_POST['country_origin']))
    $errors[] = $lang['validate_field_ajax4'];
if (empty($_POST['initial_range']))
    $errors[] = $lang['validate_field_ajax5'];
if (empty($_POST['final_range']))
    $errors[] = $lang['validate_field_ajax6'];
if (empty($_POST['tariff_price']))
    $errors[] = $lang['validate_field_ajax7'];
if (empty($_POST['ship_mode']))
    $errors[] = 'Select shipping mode';

// Comprobar demo
if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'This is a demo version, this action is not allowed.'
    ]);
    exit;
}

// Función para normalizar números
function _norm_num($v) {
    $v = trim((string)$v);
    $v = str_replace(',', '.', $v);
    $v = preg_replace('/[^0-9.]/', '', $v);
    return $v;
}

// Validar que el rango final sea mayor o igual al inicial
if (isset($_POST['initial_range'], $_POST['final_range'])) {
    $initial_range_chk = (float)_norm_num($_POST['initial_range']);
    $final_range_chk   = (float)_norm_num($_POST['final_range']);
    if ($final_range_chk < $initial_range_chk) {
        echo json_encode([
            'status'  => 'error',
            'message' => $lang['validate_field_ajax8']
        ]);
        exit;
    }
}

if (!empty($errors)) {
    echo json_encode([
        'status'  => 'error',
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Preparar datos para actualizar, incluyendo los nuevos campos
$ship_mode_id = (isset($_POST['ship_mode']) && ctype_digit((string)$_POST['ship_mode']))
    ? (int)$_POST['ship_mode']
    : 0;

$client_id_val = (isset($_POST['client_id']) && $_POST['client_id'] !== '' && ctype_digit((string)$_POST['client_id']))
    ? (int)$_POST['client_id']
    : null;

$data = [
    'tariff_price'        => _norm_num($_POST['tariff_price']),
    'initial_range'       => _norm_num($_POST['initial_range']),
    'final_range'         => _norm_num($_POST['final_range']),
    'country_origin'      => cdp_sanitize($_POST['country_origin']),
    'country_destiny'     => cdp_sanitize($_POST['country_destiny']),
    'state_destinystates' => cdp_sanitize($_POST['state_destinystates']),
    'city_destinycities'  => cdp_sanitize($_POST['city_destinycities']),
    'ship_mode'           => $ship_mode_id,
    'client_id'           => $client_id_val,
    'price_mile'          => _norm_num($_POST['price_mile'] ?? 0),
    'volumetric_percentage' => _norm_num($_POST['volumetric_percentage'] ?? 0),
    'id'                  => $tariff_id_edit
];

$overlap = cdp_verifyRangeTariffsExist(
    $data['country_origin'],
    $data['country_destiny'],
    (float)$data['initial_range'],
    (float)$data['final_range'],
    $data['id'],
    $data['state_destinystates'],
    $data['city_destinycities'],
    $data['ship_mode'],
    $data['client_id']
);
if ($overlap) {
    echo json_encode([
        'status'  => 'error',
        'message' => isset($lang['tariff_overlap_error']) ? $lang['tariff_overlap_error'] : 'Existe una tarifa que se cruza con este rango.'
    ]);
    exit;
}

$insert = cdp_updateTariffs($data);

if ($insert) {
    echo json_encode([
        'status'  => 'success',
        'message' => $lang['message_ajax_success_updated']
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => $lang['message_ajax_error1']
    ]);
}
?>
