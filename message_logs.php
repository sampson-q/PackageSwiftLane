<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Message Logs — every WhatsApp / e-mail / SMS the system sent: what,    *
// * to whom, by whom, when, and whether it went.                           *
// *                                                                       *
// *************************************************************************

    require_once("loader.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        if (!$user->cdp_hasPermission('view_message_logs')) {
            header("location: error403.php");
            exit;
        }

        include('views/reports/message_logs/message_logs.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
