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




require_once("../loader.php");
require_once(__DIR__ . "/../helpers/unique_ids.php");

// Answers "is this number already taken?" for the add forms. Delegates to the
// same existence check the save handlers use (real table + reservations), so
// the form and the server can never disagree.
$search = isset($_REQUEST['track']) ? cdp_sanitize($_REQUEST['track']) : '';
echo json_encode(cdp_uidExists('order_no', $search));
