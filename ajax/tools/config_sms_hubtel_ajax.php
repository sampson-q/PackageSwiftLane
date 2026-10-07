<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

// Tools > SMS: save the SMS settings (Hubtel and mNotify credentials, each
// provider's on/off switch, the default provider, fall-back), send a test SMS
// through one provider, or check the mNotify balance.
// Super admins only — the page and this endpoint check the same rule.

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/whatsapp.php');
require_once(__DIR__ . '/../../helpers/hubtel_sms.php');

header('Content-Type: application/json; charset=utf-8');

$respond = function ($ok, $message, array $extra = []) {
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => $message], $extra));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respond(false, 'Method Not Allowed.');
}
if (!cdp_smsCanManage($user)) {
    http_response_code(403);
    $respond(false, 'Only a Super Admin can change the SMS settings.');
}

$action = (string) ($_POST['action'] ?? '');
$uid    = (int) ($_SESSION['userid'] ?? 0);

/** Status of every provider, for the badges on the page. */
$providerStatus = function () {
    $out = [];
    foreach (cdp_smsProviders() as $p => $label) {
        $out[$p] = ['configured' => cdp_smsProviderConfigured($p), 'enabled' => cdp_smsProviderEnabled($p)];
    }
    return $out;
};

if ($action === 'save') {
    $providers = cdp_smsProviders();
    $active    = isset($_POST['active_sms']) ? 1 : 0;
    $fallback  = isset($_POST['sms_fallback']) ? '1' : '0';
    $default   = (string) ($_POST['sms_default_provider'] ?? 'hubtel');

    $in = [
        'hubtel_client_id'     => trim((string) ($_POST['hubtel_client_id'] ?? '')),
        'hubtel_client_secret' => trim((string) ($_POST['hubtel_client_secret'] ?? '')),
        'hubtel_sender_id'     => trim((string) ($_POST['hubtel_sender_id'] ?? '')),
        'mnotify_api_key'      => trim((string) ($_POST['mnotify_api_key'] ?? '')),
        'mnotify_sender_id'    => trim((string) ($_POST['mnotify_sender_id'] ?? '')),
    ];
    $enabled = [];
    foreach (array_keys($providers) as $p) {
        $enabled[$p] = isset($_POST[$p . '_enabled']) ? '1' : '0';
    }

    $errors = [];
    if ($in['hubtel_client_id'] !== '' && !preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $in['hubtel_client_id'])) {
        $errors['hubtel_client_id'] = 'The Client ID can only hold letters, digits, - and _.';
    }
    if ($in['hubtel_client_secret'] !== '' && !preg_match('/^[\x21-\x7E]{1,128}$/', $in['hubtel_client_secret'])) {
        $errors['hubtel_client_secret'] = 'The Client Secret has characters Hubtel does not issue.';
    }
    if ($in['mnotify_api_key'] !== '' && !preg_match('/^[A-Za-z0-9_\-]{8,128}$/', $in['mnotify_api_key'])) {
        $errors['mnotify_api_key'] = 'The API key can only hold letters, digits, - and _.';
    }
    foreach (['hubtel_sender_id', 'mnotify_sender_id'] as $f) {
        if ($in[$f] !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $in[$f])) {
            $errors[$f] = 'The Sender ID is up to 11 letters, digits or spaces.';
        }
    }
    if (!array_key_exists($default, $providers)) {
        $errors['sms_default_provider'] = 'Choose the default SMS provider.';
    }

    // A blank secret keeps the stored one (secrets are never sent back to the
    // browser, so blank is what an untouched form submits).
    $hub = cdp_hubtelSmsConfig();
    $mno = cdp_mnotifySmsConfig();
    $final = [
        'hubtel'  => $in['hubtel_client_id'] !== '' && ($in['hubtel_client_secret'] !== '' || $hub['client_secret'] !== '') && $in['hubtel_sender_id'] !== '',
        'mnotify' => ($in['mnotify_api_key'] !== '' || $mno['api_key'] !== '') && $in['mnotify_sender_id'] !== '',
    ];
    foreach ($providers as $p => $label) {
        if ($enabled[$p] === '1' && !$final[$p]) {
            $errors[$p . '_enabled'] = 'Enter every ' . $label . ' credential before switching ' . $label . ' on.';
        }
    }
    if (!isset($errors['sms_default_provider']) && $enabled[$default] !== '1') {
        $errors['sms_default_provider'] = 'The default provider must be switched on.';
    }
    if ($active === 1 && !in_array('1', $enabled, true)) {
        $errors['active_sms'] = 'Switch on at least one SMS provider before switching SMS on.';
    }
    if ($errors) {
        $respond(false, reset($errors), ['errors' => $errors]);
    }

    if (!cdp_smsSettingsEnsureTable()) {
        $respond(false, 'The SMS settings table could not be created.');
    }
    $ok = true;
    foreach (['hubtel_client_id', 'hubtel_sender_id', 'mnotify_sender_id'] as $k) {
        $ok = $ok && cdp_smsSettingSet($k, $in[$k], $uid);
    }
    foreach (['hubtel_client_secret', 'mnotify_api_key'] as $k) {
        if ($in[$k] !== '') {
            $ok = $ok && cdp_smsSettingSet($k, $in[$k], $uid);
        }
    }
    foreach ($enabled as $p => $v) {
        $ok = $ok && cdp_smsSettingSet($p . '_enabled', $v, $uid);
    }
    $ok = $ok && cdp_smsSettingSet('sms_default_provider', $default, $uid)
              && cdp_smsSettingSet('sms_fallback', $fallback, $uid);

    $db = new Conexion;
    $db->cdp_query("UPDATE cdb_settings SET active_sms = :a");
    $db->bind(':a', $active);
    $ok = $ok && ($db->cdp_execute() !== false);

    $respond($ok, $ok ? 'SMS Settings Saved.' : 'The SMS settings could not be saved.',
        ['providers' => $providerStatus(), 'active_sms' => $active]);
}

if ($action === 'test') {
    $provider = (string) ($_POST['sms_test_provider'] ?? '');
    $label    = cdp_smsProviders()[$provider] ?? null;
    if ($label === null) {
        $respond(false, 'Choose the provider to test.', ['errors' => ['sms_test_provider' => 'Choose the provider to test.']]);
    }
    if (!cdp_smsProviderConfigured($provider)) {
        $respond(false, 'Save the ' . $label . ' credentials first.');
    }
    $raw = trim((string) ($_POST['sms_test_phone'] ?? ''));
    // Both providers are Ghanaian: a number typed in local form (024...) is
    // read as Ghanaian. +... or 00... is taken as already international.
    $to  = $raw !== '' ? (string) cdp_normalizePhone($raw, 'GH') : '';
    if ($to === '' || strlen($to) < 9 || strlen($to) > 15) {
        $respond(false, 'Enter a valid phone number.', ['errors' => ['sms_test_phone' => 'Enter a valid phone number.']]);
    }
    $core = new Core;
    // A test goes through the chosen provider even while it is switched off,
    // so its credentials can be checked before it is switched on.
    $res  = cdp_sendSmsVia($provider, $to, 'Test SMS from ' . $core->site_name . ' via ' . $label . '. Your SMS settings are working.',
        ['id' => 0, 'name' => 'SMS Settings Test']);
    $respond(!empty($res['success']), !empty($res['success']) ? 'Test SMS Sent To +' . $to . ' Via ' . $label . '.' : $res['message']);
}

if ($action === 'mnotify_balance') {
    // The typed key when one is entered, otherwise the stored one: checks a
    // key without saving it and without sending an SMS.
    $typed = trim((string) ($_POST['mnotify_api_key'] ?? ''));
    $res   = cdp_mnotifySmsBalance($typed !== '' ? $typed : null);
    if (empty($res['success'])) {
        $respond(false, $res['message']);
    }
    $respond(true, 'mNotify Key Works. SMS Balance: ' . number_format($res['balance']) . ' (Bonus ' . number_format($res['bonus']) . ').',
        ['balance' => $res['balance'], 'bonus' => $res['bonus']]);
}

http_response_code(400);
$respond(false, 'Unknown action.');
