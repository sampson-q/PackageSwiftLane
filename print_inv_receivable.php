<?php
// *************************************************************************
// * Accounts Receivable - printable invoice, entry point.                 *
// * ?consolidate_id=<id>&sender_id=<id>                                   *
// *************************************************************************

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    // Same permission the Accounts Receivable page itself is gated on.
    if (!$user->cdp_hasPermission('view_receivable_accounts')) {
        header("location: error403.php");
        exit;
    }

    include('views/print/print_inv_receivable.php');

} else {
    header("location: login.php");
    exit;
}
?>
