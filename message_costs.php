<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Message Costs — how many SMS, WhatsApp messages and e-mails went out  *
// * and what they cost, from the unit prices entered on the page.         *
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

        include('views/reports/message_costs/message_costs.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
