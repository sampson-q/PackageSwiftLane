<?php
// ============================================================================
// Save Hubtel credentials into cdb_met_payment (id 6), reusing the shared
// key columns: public_key = Client ID, secret_key = Client Secret,
// paypal_client_id = Merchant Account Number.
// ============================================================================

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

require_once("../../../helpers/querys.php");

header('Content-type: application/json; charset=UTF-8');

$response = array();

if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'This is a demo version, this action is not allowed.']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method.']);
    exit;
}

// Required fields.
foreach (['name_pay', 'detail_pay', 'public_key', 'secret_key', 'paypal_client_id'] as $f) {
    if (trim((string) ($_POST[$f] ?? '')) === '') {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }
}

$is_active = isset($_POST['is_active']) ? 1 : 0;

// Name-uniqueness (mirror the other gateway handlers) if the helper exists.
if (function_exists('cdp_paymentMethodExists') && cdp_paymentMethodExists($_POST['name_pay'], $id)) {
    echo json_encode(['status' => 'error', 'message' => 'A payment method with that name already exists.']);
    exit;
}

try {
    $db = new Conexion;
    $db->cdp_query("UPDATE cdb_met_payment
                    SET name_pay = :name_pay, detail_pay = :detail_pay,
                        public_key = :public_key, secret_key = :secret_key,
                        paypal_client_id = :merchant, is_active = :is_active
                    WHERE id = :id");
    $db->bind(':name_pay', cdp_sanitize($_POST['name_pay']));
    $db->bind(':detail_pay', cdp_sanitize($_POST['detail_pay']));
    $db->bind(':public_key', cdp_sanitize($_POST['public_key']));
    $db->bind(':secret_key', cdp_sanitize($_POST['secret_key']));
    $db->bind(':merchant', cdp_sanitize($_POST['paypal_client_id']));
    $db->bind(':is_active', $is_active);
    $db->bind(':id', $id);
    $db->cdp_execute();

    echo json_encode(['status' => 'success', 'message' => $lang['message_ajax_success_add'] ?? 'Saved.']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not save the Hubtel settings.']);
}
