<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

// Pickup codes (helpers/pickup_code.php) for the staff modal
// (dataJs/pickup_code.js):
//   state     module, order_ids[]            verified? live code?
//   generate  module, order_ids[], context   send a new code to the owner
//   verify    code_id, code                  staff enter what the owner read out
//   list                                     codes alive right now (the modal)
// Staff only — a customer account is refused.

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/pickup_code.php');

header('Content-Type: application/json; charset=utf-8');

$respond = function ($ok, $message, array $extra = []) {
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => $message], $extra));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respond(false, 'Method Not Allowed.');
}
if (!cdp_pickupCodeCanUse($user)) {
    http_response_code(403);
    $respond(false, 'You do not have permission to use pickup codes.');
}

$action   = (string) ($_POST['action'] ?? '');
$uid      = (int) ($_SESSION['userid'] ?? 0);
$module   = cdp_pickupCodeModule((string) ($_POST['module'] ?? 'air'));
$orderIds = array_values(array_filter(array_map('intval', (array) ($_POST['order_ids'] ?? []))));

if ($action === 'state') {
    if (!$orderIds) {
        $respond(false, 'No package specified.');
    }
    $pkgs = cdp_pickupCodePackages($module, $orderIds);
    $owners = array_values(array_unique(array_map(function ($p) { return (int) $p->sender_id; }, $pkgs)));
    $owner  = (count($owners) === 1 && $owners[0] > 0) ? cdp_getSenderCourier($owners[0]) : null;
    $respond(true, '', cdp_pickupCodeState($module, $orderIds) + [
        'owner'     => $owner ? cdp_nameWithLocker($owner) : '',
        'trackings' => array_map(function ($p) { return $p->order_prefix . $p->order_no; }, $pkgs),
        'ttl'       => CDP_PICKUP_CODE_TTL,
    ]);
}

if ($action === 'generate') {
    if (!$orderIds) {
        $respond(false, 'No package specified.');
    }
    $res = cdp_pickupCodeGenerate($module, $orderIds, (string) ($_POST['context'] ?? ''), $uid);
    $respond(!empty($res['success']), $res['message'], $res);
}

if ($action === 'verify') {
    $res = cdp_pickupCodeVerify((int) ($_POST['code_id'] ?? 0), (string) ($_POST['code'] ?? ''), $uid);
    $respond(!empty($res['success']), $res['message']);
}

if ($action === 'list') {
    $now  = time();
    $rows = array_map(function ($r) use ($now) {
        $verified = $r->status === 'verified';
        return [
            'id'         => (int) $r->id,
            'code'       => (string) $r->code,
            'module'     => $r->module === 'sea' ? 'Package' : 'Shipment',
            'owner'      => cdp_nameWithLocker($r),
            'trackings'  => (string) $r->trackings,
            'status'     => $verified ? 'Verified' : 'Waiting',
            'channels'   => (string) $r->channels,
            'sent_by'    => trim((string) $r->staff_fname . ' ' . (string) $r->staff_lname),
            'created_at' => (string) $r->created_at,
            // Waiting: time left to enter it. Verified: time left to hand over.
            'seconds'    => $verified
                ? max(0, strtotime($r->verified_at) + CDP_PICKUP_VERIFIED_TTL - $now)
                : max(0, strtotime($r->expires_at) - $now),
        ];
    }, cdp_pickupCodeActiveList());
    $respond(true, '', ['codes' => $rows]);
}

http_response_code(400);
$respond(false, 'Unknown action.');
