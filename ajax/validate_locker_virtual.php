<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************





require_once("../loader.php");

$db = new Conexion;

$search = cdp_sanitize($_REQUEST['track']);
$search = intval($search);


$sql_digits = "SELECT * FROM cdb_settings";

$db->cdp_query($sql_digits);
$db->cdp_execute();
$verifylock = $db->cdp_registro();

$digitslock = $verifylock->digit_random_locker;

$format_track = str_pad($search, "" . $digitslock . "", "0", STR_PAD_LEFT);

$sql = "SELECT digitslockers FROM cdb_virtual_locker WHERE digitslockers = '" . $format_track . "'";

$db->cdp_query($sql);
$db->cdp_execute();

$data = $db->cdp_registro();

if ($data) {

	echo json_encode(true);
} else {

	echo json_encode(false);
}
