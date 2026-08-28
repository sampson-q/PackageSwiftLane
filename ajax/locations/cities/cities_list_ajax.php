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

$db = new Conexion;


$search = cdp_sanitize($_REQUEST['search']);

$tables = "cdb_cities";
$fields = "*";

$sWhere = "";


$sWhere .= " name LIKE '%" . $search . "%'";



// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$sql = "SELECT $fields FROM  $tables where $sWhere";
$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>


<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <th><b><?php echo $lang['leftorder319']; ?></b></th>
                <th data-sort-initial="true" data-toggle="true"><b><?php echo $lang['leftorder320']; ?></b></th>
                <th class="text-center"><b><?php echo $lang['left367'] ?></b></th>
            </tr>
        </thead>


        <?php if (!$data) { ?>
            <tr>
                <td colspan="6">
                    <?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
                </td>
            </tr>
        <?php } else { ?>
            <?php foreach ($data as $row) {

                $db->cdp_query("SELECT * FROM cdb_states where id= '" . $row->state_id . "'");
                $state = $db->cdp_registro();

                $db->cdp_query("SELECT * FROM cdb_countries where id= '" . $state->country_id . "'");
                $country = $db->cdp_registro();
            ?>
                <tr>
                    <td><?php echo $row->name; ?></td>
                    <td><?php echo $state->name; ?></td>
                    <td class="text-center">
                        <a href="cities_edit.php?id=<?php echo $row->id; ?>"><i class="ti-pencil" aria-hidden="true"></i></a>
                        <a id="item_<?php echo $row->id; ?>" onclick="cdp_eliminar('<?php echo $row->id; ?>');" class="delete">
                            <div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
                    </td>
                </tr>
            <?php } ?>

        <?php } ?>

    </table>


    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'cities_list');    ?>
    </div>
    </div>
<?php } ?>