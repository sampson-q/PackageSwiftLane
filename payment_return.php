<?php
// ============================================================================
// Paystack callback landing — where the customer's browser comes back to after
// the mobile-money checkout.
//
// This page NEVER trusts the query string. Paystack appends ?reference=... and
// nothing else meaningful; the actual outcome is established by calling
// Paystack server-to-server (cdp_fsCompleteIntent -> /transaction/verify).
// A customer editing the URL to another reference gets nothing: the intent is
// checked against their own session id.
//
// Losing this callback is expected and harmless (browser closed, network
// dropped) — ajax/gateway/paystack_webhook.php completes the same intent
// through the same idempotent path.
// ============================================================================

require_once("loader.php");
require_once(__DIR__ . '/helpers/fs_payments.php');

$user = new User();

if ($user->cdp_loginCheck() !== true) {
    header("location: login.php");
    exit;
}

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$sid = (int) ($_SESSION['userid'] ?? 0);

$state = 'failed';
$message = 'We could not confirm that payment.';

if ($reference !== '') {
    $intent = cdp_fsIntentByRef($reference);
    if (!$intent || (int) $intent->sender_id !== $sid) {
        $state = 'failed';
        $message = 'That payment does not belong to your account.';
    } else {
        $res = cdp_fsCompleteIntent($reference);
        $state = $res['status'];
        $message = $res['message'];
        if ($state === 'already') {
            $state = 'success';
        }
    }
}

include('views/customer/payment_return.php');
