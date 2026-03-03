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



require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_recipients');


$db = new Conexion;
$user = new User;
$userData = $user->cdp_getUserData();
$ctx = cdp_getAgencyContext();

$search = cdp_sanitize($_REQUEST['search']);

$tables = "cdb_recipients AS r INNER JOIN cdb_users AS u ON r.sender_id = u.id";
$fields = "u.fname AS sender_fname, u.lname AS sender_lname, r.*, u.username";

$sWhere = "r.sender_id != ''";

if ((int)$userData->userlevel === 1) {
	$sWhere .= " AND r.sender_id = " . (int)$_SESSION['userid'];
} elseif ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
	$sWhere .= " AND (r.agency_id = " . (int)$ctx['agency_id'] . " OR u.agency_id = " . (int)$ctx['agency_id'] . ")";
} elseif ($ctx['is_restricted']) {
	$sWhere .= " AND 1=0";
}

if ($search != null) {
    $sWhere .= " AND (r.fname LIKE '%" . $search . "%' OR r.lname LIKE '%" . $search . "%' OR u.fname LIKE '%" . $search . "%' OR u.lname LIKE '%" . $search . "%')";
}

// // pagination variables
$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
$per_page = 15; //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$sql = "SELECT $fields FROM  $tables WHERE $sWhere ORDER BY u.fname, r.fname"; // Ordena por nombre del remitente y destinatario
$query_count = $db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();

$db->cdp_query($sql . " LIMIT $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);

if ($numrows > 0) { ?>
<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <th class="text-center"><b><?php echo $lang['report-text37'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['report-text388'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['messagesform59'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['messagesform60'] ?></b></th>
                <th class="text-center"><b><?php echo $lang['edit-clien43'] ?></b></th>
            </tr>
        </thead>

        <?php if (!$data) { ?>
            <tr>
                <td colspan="6">
                    <?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
                </td>
            </tr>
        <?php } else { ?>
            <?php foreach ($data as $recipient) { ?>
                <tr>
                    <td class="text-center"><?php echo $recipient->sender_fname . ' ' . $recipient->sender_lname; ?></td>
                    <td class="text-center"><?php echo $recipient->fname . ' ' . $recipient->lname; ?></td>
                    <td class="text-center"><?php echo $recipient->email; ?></td>
                    <td class="text-center"><?php echo $recipient->phone; ?></td>
                    <td align='center'>
                        <a onclick="cdp_eliminar('<?php echo $recipient->id; ?>')" id="item_<?php echo $recipient->id; ?>" class="delete" data-toggle="tooltip" data-placement="top" title="<?php echo $lang['edit-clien47'] ?>">
                            <div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
                        </a>
                    </td>
                </tr>
            <?php } ?>
        <?php } ?>
        </table>

        <div class="pull-right">
            <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang); ?>
        </div>
    </div>
<?php } ?>
