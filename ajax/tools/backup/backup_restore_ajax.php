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



require_once("../../../loader.php");
require_once("../../../helpers/backups_function.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

/* == Delete SQL Backup == */
if (isset($_POST['restoreBackup'])) :

  $ok = doRestore($_POST['restoreBackup']);


  if ($ok == 1) { ?>

    <div class="alert alert-info" id="success-alert">
      <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
        Database restored successfully!
      </p>
    </div>
  <?php
  } else {  ?>

    <div class="alert alert-info" id="danger-alert">
      <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
        <?php echo $lang['message_ajax_error2']; ?>
      </p>
    </div>
<?php
  }

endif;
