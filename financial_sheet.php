<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Financial Sheet entry point                               *
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

require_once("loader.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('financial_sheet')) {
        header("location: error403.php");
        exit;
    }

    include('views/reports/financial_sheet/financial_sheet.php');

} else {
    header("location: login.php");
    exit;
}
?>
