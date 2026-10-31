<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * AI Assistant — ask anything about the system and get answers drawn    *
// * from the data this user is allowed to see. See helpers/ai_assistant.php *
// *                                                                       *
// *************************************************************************

    require_once("loader.php");
    require_once("helpers/ai_assistant.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        // Super admins reach the page even while it is switched off, to set it up.
        if (!cdp_aiCanUse($user) && !cdp_aiCanManage($user)) {
            header("location: error403.php");
            exit;
        }

        include('views/ai_assistant/ai_assistant.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
