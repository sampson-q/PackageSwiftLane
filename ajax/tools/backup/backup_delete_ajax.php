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

require_once(__DIR__ . '/../../../loader.php');
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_post();
require_login();
require_permission('view_tools');

if (!$user->cdp_is_Admin()) { http_response_code(403); exit; }
$path = cdp_securityConfinedFile(__DIR__ . '/../../../backups', $_POST['deleteBackup'] ?? null);
if ($path === null) { http_response_code(404); exit('Backup not found.'); }
if (!unlink($path)) { http_response_code(500); exit('Unable to delete backup.'); }
echo '<div class="alert alert-info">Backup deleted successfully!</div>';
