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



require_once("../../../loader.php");

$db = new Conexion;


$search = trim($_REQUEST['search']);

$tables = "whatsapp_templates";
$fields = "*";

$sWhere = "title LIKE '%" . $search . "%'";


// // pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 10; //how much records you want to show
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;

$sql = "SELECT $fields FROM  $tables WHERE $sWhere ORDER BY title ASC";
$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
<div class="table-responsive">

	<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
		<thead>
			<tr>
				<th class="header"><b><?php echo $lang['tools-template10'] ?></b></b></th>
				<th><b><?php echo $lang['tools-template11'] ?></b></th>
				<th class="text-center"><b><?php echo $lang['tools-template12'] ?></b></th>
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
			<?php foreach ($data as $row) { ?>
				<tr>
					<td class="nowrap"><?php echo $row->title; ?></td>
					<td><?php echo $row->description; ?></td>
					<td class="text-center">
						<a href="templates_whatsapp_edit.php?id=<?php echo $row->id; ?>"><span class="ti-pencil"></span></a>

						<a id="item_<?php echo $row->id; ?>" onclick="cdp_eliminar('<?php echo $row->id; ?>');" class="delete" data-rel="<?php echo $row->title; ?>">
							<div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
						</a>

					</td>
				</tr>
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'templates_whatsapp_ajax');	?>
	</div>
	</div>
<?php } ?>