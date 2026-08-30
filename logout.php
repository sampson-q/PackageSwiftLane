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
  
  require_once("loader.php");
  $user= new User;
?>
<?php
  require_once("helpers/activity_log.php");

  if ($user->logged_in) {
      // Log BEFORE cdp_logout(), which destroys the session the actor is read from.
      cdp_activityLog([
          'module'      => 'auth',
          'verb'        => 'logout',
          'entity_type' => 'user',
          'entity_id'   => (int) ($_SESSION['userid'] ?? 0),
          'summary'     => 'Signed out',
      ]);
  }

  if ($user->logged_in)
      $user->cdp_logout();
	  
   header("location: index.php");
?>