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
require_login();
require_permission('view_tools');

  /* == Delete SQL Backup == */
  if (isset($_POST['deleteBackup'])):
      $action = @unlink('../../../backups/' . trim($_POST['deleteBackup']));

      ?>

	<div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            Backup deleted successfully!
       </p>
    </div>
    <?php

      
  endif;