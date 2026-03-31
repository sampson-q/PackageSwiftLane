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
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;

// Limpia la búsqueda
$search = cdp_sanitize($_REQUEST['search']);

// Configuración de paginación
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 10; // Registros por página
$adjacents  = 4; // Número de páginas adyacentes
$offset = ($page - 1) * $per_page;

// Consulta optimizada con INNER JOIN
$sql = "
    SELECT 
        r.role_id, 
        r.role_name, 
        r.description, 
        r.rol_active, 
        COUNT(p.id) AS total_permissions
    FROM 
        cdb_user_roles r
    LEFT JOIN 
        cdb_user_role_permissions p 
    ON 
        r.role_id = p.role_id
    WHERE 
        r.role_name LIKE '%" . $search . "%'
    GROUP BY 
        r.role_id, r.role_name, r.description, r.rol_active
    ORDER BY 
        r.role_name DESC
    LIMIT 
        $offset, $per_page";

// Ejecuta la consulta
$db->cdp_query($sql);
$data = $db->cdp_registros();

// Calcula el total de registros para la paginación
$query_count = "SELECT COUNT(*) FROM cdb_user_roles WHERE role_name LIKE '%" . $search . "%'";
$db->cdp_query($query_count);

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>

<div class="table-responsive">

	<table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
		<thead>
			<tr>
				<th data-sort-initial="true" data-toggle="true"><b><?php echo $lang['rolesp3'] ?></b></th>
				<th data-sort-initial="true" data-toggle="true"><b><?php echo $lang['rolesp4'] ?></b></th>
				<th data-sort-initial="true" data-toggle="true"><b><?php echo $lang['rolesp46'] ?></b></th>
				<th data-sort-initial="true" data-toggle="true"><b><?php echo $lang['rolesp5'] ?></b></th>
				<th data-sort-ignore="true" class="text-center"><b><?php echo $lang['tools-office19'] ?></b></th>
			</tr>
		</thead>


		<?php if (!$data) { ?>
			<tr>
				<td colspan="4">
					<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>								
				", false; ?>
				</td>
			</tr>
		<?php } else { ?>
			<?php foreach ($data as $row) { ?>
				<tr>
					<td><?php echo isset($lang['role_'.$row->role_id]) ? $lang['role_'.$row->role_id] : $row->role_name; ?></td>
					<td><?php echo isset($lang[$row->description]) ? $lang[$row->description] : $row->description; ?></td>
					<td><?php echo $row->total_permissions; ?></td>
					<td>
					    <?php if ($row->rol_active == 1): ?>
					        <span class="badge badgeactive-success"><?php echo $lang['rolesp11'] ?></span>
					    <?php else: ?>
					        <span class="badge badgeactive-danger"><?php echo $lang['rolesp12'] ?></span>
					    <?php endif; ?>
					</td>
					<td class="text-center">
					    <a href="asingpermissions_edit.php?id=<?php echo $row->role_id; ?>" data-toggle="tooltip" data-original-title="<?php echo $lang['rolesp7'] ?>">
					        <div class="icon-holder"><i class="ti-pencil" aria-hidden="true"></i></div>
					    </a>
					    <?php if ((int)$row->role_id !== 9) : ?>
					    <a onclick="cdp_eliminar('<?php echo $row->role_id; ?>')" id="item_<?php echo $row->role_id; ?>" class="delete" data-rel="<?php echo isset($lang['role_'.$row->role_id]) ? $lang['role_'.$row->role_id] : $row->role_name; ?>" data-toggle="tooltip" data-original-title="<?php echo $lang['rolesp8'] ?>">
					        <div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
					    </a>
					    <?php else : ?>
					    <a data-toggle="tooltip" data-original-title="<?php echo isset($lang['super_admin_role_no_delete']) ? $lang['super_admin_role_no_delete'] : 'Super Admin role cannot be deleted'; ?>"><i style="color:#343a40" class="ti-lock"></i></a>
					    <?php endif; ?>
					</td>

				</tr>
			<?php } ?>

		<?php } ?>

	</table>


	<div class="pull-right">
		<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'permissions_list');	?>
	</div>
	</div>
<?php } ?>