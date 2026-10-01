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

// Tools > SMS: save the Hubtel SMS settings, or send a test SMS.
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

if ($action === 'save') {
    $clientId = trim((string) ($_POST['hubtel_client_id'] ?? ''));
    $secret   = trim((string) ($_POST['hubtel_client_secret'] ?? ''));
    $sender   = trim((string) ($_POST['hubtel_sender_id'] ?? ''));
    $active   = isset($_POST['active_sms']) ? 1 : 0;

    $errors = [];
    if ($clientId !== '' && !preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $clientId)) {
        $errors['hubtel_client_id'] = 'The Client ID can only hold letters, digits, - and _.';
    }
    if ($secret !== '' && !preg_match('/^[\x21-\x7E]{1,128}$/', $secret)) {
        $errors['hubtel_client_secret'] = 'The Client Secret has characters Hubtel does not issue.';
    }
    if ($sender !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $sender)) {
        $errors['hubtel_sender_id'] = 'The Sender ID is up to 11 letters, digits or spaces.';
    }

    // A blank secret keeps the stored one (the field is never sent back to
    // the browser, so blank is what an untouched form submits).
    $current     = cdp_hubtelSmsConfig();
    $finalSecret = $secret !== '' ? $secret : $current['client_secret'];

    if ($active === 1 && ($clientId === '' || $finalSecret === '' || $sender === '')) {
        $errors['active_sms'] = 'Enter the Client ID, Client Secret and Sender ID before switching SMS on.';
    }
    if ($errors) {
        $respond(false, reset($errors), ['errors' => $errors]);
    }

    if (!cdp_smsSettingsEnsureTable()) {
        $respond(false, 'The SMS settings table could not be created.');
    }
    $ok = cdp_smsSettingSet('hubtel_client_id', $clientId, $uid)
        && cdp_smsSettingSet('hubtel_sender_id', $sender, $uid)
        && ($secret === '' || cdp_smsSettingSet('hubtel_client_secret', $secret, $uid));

    $db = new Conexion;
    $db->cdp_query("UPDATE cdb_settings SET active_sms = :a");
    $db->bind(':a', $active);
    $ok = $ok && ($db->cdp_execute() !== false);

    $respond($ok, $ok ? 'SMS Settings Saved.' : 'The SMS settings could not be saved.',
        ['configured' => cdp_hubtelSmsReady(), 'active_sms' => $active]);
}

if ($action === 'test') {
    if (!cdp_hubtelSmsReady()) {
        $respond(false, 'Save the Client ID, Client Secret and Sender ID first.');
    }
    $raw = trim((string) ($_POST['sms_test_phone'] ?? ''));
    // Hubtel is a Ghanaian provider: a number typed in local form (024...)
    // is read as Ghanaian. +... or 00... is taken as already international.
    $to  = $raw !== '' ? (string) cdp_normalizePhone($raw, 'GH') : '';
    if ($to === '' || strlen($to) < 9 || strlen($to) > 15) {
        $respond(false, 'Enter a valid phone number.', ['errors' => ['sms_test_phone' => 'Enter a valid phone number.']]);
    }
    $core = new Core;
    $res  = cdp_sendHubtelSms($to, 'Test SMS from ' . $core->site_name . '. Your SMS settings are working.',
        ['id' => 0, 'name' => 'SMS Settings Test']);
    $respond(!empty($res['success']), !empty($res['success']) ? 'Test SMS Sent To +' . $to . '.' : $res['message']);
}

http_response_code(400);
$respond(false, 'Unknown action.');
