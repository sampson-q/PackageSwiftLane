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



if (!isset($_GET['file']) || empty($_GET['file'])) {
 exit();
}
$root = "backups/";
$file = basename($_GET['file']);
$path = $root.$file;
$type = '';

if (is_file($path)) {
 $size = filesize($path);
 if (function_exists('mime_content_type')) {
 $type = mime_content_type($path);
 } else if (function_exists('finfo_file')) {
 $info = finfo_open(FILEINFO_MIME);
 $type = finfo_file($info, $path);
 finfo_close($info);
 }
 if ($type == '') {
 $type = "application/force-download";
 }
 // Audit: a database backup leaving the server is worth a row of its own.
 require_once(__DIR__ . "/loader.php");
 require_once(__DIR__ . "/helpers/activity_log.php");
 new User(); // opens the session the actor is read from
 cdp_activityLog([
     'module'      => 'system',
     'verb'        => 'export',
     'action'      => 'system.backup_download',
     'label'       => 'System · Backup Downloaded',
     'entity_type' => 'backup',
     'entity_id'   => $file,
     'summary'     => 'Downloaded backup file ' . $file,
     'meta'        => ['size' => $size],
 ]);

 // Definir headers
 header("Content-Type: $type");
 header("Content-Disposition: attachment; filename=$file");
 header("Content-Transfer-Encoding: binary");
 header("Content-Length: " . $size);
 // Descargar archivo
 readfile($path);
} else {
 die("El archivo no existe.");
}

?>