<?php
 // bootstrap.php - load project config and prepare environment for API
 
 // Load the project's main config that defines CDP_DB_* constants
 $projectConfig = __DIR__ . '/../../config/config.php';
 if (!file_exists($projectConfig)) {
     // Stop early if the project's config isn't found
     header('Content-Type: application/json', true, 500);
     echo json_encode(['status'=>'error','message'=>'Project config/config.php not found.']);
     exit;
 }
 require_once $projectConfig;

 // Load API config (jwt secret, cors)
 $apiConfig = require __DIR__ . '/config.php';

 // Load core library classes from project
 require_once __DIR__ . '/../../lib/Conexion.php';
 require_once __DIR__ . '/../../lib/Core.php';
 require_once __DIR__ . '/../../lib/User.php';
 require_once __DIR__ . '/../../lib/OtpService.php';

 // Create DB connection (Conexion uses project DB constants)
 try {
     $db = new Conexion();
 } catch (Exception $e) {
     header('Content-Type: application/json', true, 500);
     echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
     exit;
 }

 // Expose some globals the app uses
 $GLOBALS['api_config'] = $apiConfig;
 $GLOBALS['db'] = $db;

 // Helpers
 require_once __DIR__ . '/helpers/Response.php';
 require_once __DIR__ . '/helpers/Jwt.php';
 require_once __DIR__ . '/helpers/Request.php';
 require_once __DIR__ . '/helpers/Logger.php';

 // Project helper queries (for inserts/lookups)
 require_once __DIR__ . '/../../helpers/querys.php';
 
 // Middlewares (we'll include on-demand in index.php)
