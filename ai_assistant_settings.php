<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * AI Assistant settings (Tools > AI Assistant): provider, API key and    *
// * model (super admins); what the assistant knows, what people ask and    *
// * the page ideas drawn from it (administrators).                         *
// *                                                                       *
// *************************************************************************

    require_once("loader.php");
    require_once("helpers/ai_assistant.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        if (!cdp_aiCanManage($user) && !cdp_aiCanSeeInsights($user)) {
            header("location: error403.php");
            exit;
        }

        include('views/tools/ai_assistant_settings.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
