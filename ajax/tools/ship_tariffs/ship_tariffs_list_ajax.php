<?php
// *************************************************************************
// * DEPRIXA PRO - Integrated Web Shipping System                          *
// *************************************************************************

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipping_tariffs');


$db = new Conexion;

// Filtros
$origin   = isset($_REQUEST['origin'])   ? (int)$_REQUEST['origin']   : 0;
$destiny  = isset($_REQUEST['destiny'])  ? (int)$_REQUEST['destiny']  : 0;
$shipMode = isset($_REQUEST['shipMode']) ? $_REQUEST['shipMode']      : ''; // NO casteamos aún

$sWhere = " WHERE 1=1 ";
if ($origin > 0)  $sWhere .= " AND a.origin = ".$origin;
if ($destiny > 0) $sWhere .= " AND a.destiny = ".$destiny;

if ($shipMode !== '' && ctype_digit((string)$shipMode)) {
    // Filtra por ID (coincide con a.order_service_options)
    $sWhere .= " AND a.order_service_options = ".(int)$shipMode;
}


// Si deseas búsqueda por texto (país/estado/ciudad), descomenta:
/*
if ($search !== '') {
    $safe = cdp_sanitize($search);
    $sWhere .= " AND (oc.name LIKE '%$safe%' OR dc.name LIKE '%$safe%' OR s.name LIKE '%$safe%' OR ci.name LIKE '%$safe%')";
}
*/

// Paginación
$page       = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;
$per_page   = 10;
$adjacents  = 4;
$offset     = ($page - 1) * $per_page;

// COUNT
$sql_count = "SELECT COUNT(*) AS total
              FROM cdb_shipping_fees a
              LEFT JOIN cdb_countries oc ON oc.id = a.origin
              LEFT JOIN cdb_countries dc ON dc.id = a.destiny
              LEFT JOIN cdb_states s     ON s.id  = a.state
              LEFT JOIN cdb_cities ci    ON ci.id = a.city
              $sWhere";
$db->cdp_query($sql_count);
$db->cdp_execute();
$row_count   = $db->cdp_registro();
$numrows     = $row_count ? (int)$row_count->total : 0;
$total_pages = ($numrows > 0) ? ceil($numrows / $per_page) : 0;

// Query principal
$sql = "SELECT
            a.id,
            a.origin,
            a.destiny,
            a.state,
            a.city,
            a.order_service_options,
            a.initial_range,
            a.final_range,
            a.price,
            oc.name AS origin_name,
            dc.name AS destiny_country_name,
            s.name  AS state_name,
            ci.name AS city_name,
            cat.name_item AS mode_name  -- Obtener el nombre del modo de envío
        FROM cdb_shipping_fees a
        LEFT JOIN cdb_countries oc ON oc.id = a.origin
        LEFT JOIN cdb_countries dc ON dc.id = a.destiny
        LEFT JOIN cdb_states    s  ON s.id  = a.state
        LEFT JOIN cdb_cities    ci ON ci.id = a.city
        LEFT JOIN cdb_category  cat ON cat.id = a.order_service_options  -- Join con tabla de categorías
        $sWhere
        ORDER BY a.id DESC
        LIMIT $offset, $per_page";


$db->cdp_query($sql);
$data = $db->cdp_registros();

if ($numrows > 0) { ?>
<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <th><b><?php echo $lang['lorigin'] ?></b></th>
                <th><b><?php echo $lang['ldestination'] ?></b></th>
                <th><b><?php echo $lang['itemcategory'] ?></b></th>
                <th><b><?php echo $lang['leftorder290'] ?></b></th>
                <th><b><?php echo $lang['leftorder291'] ?></b></th>
                <th><b><?php echo $lang['leftorder292'] ?></b></th>
                <th><b><?php echo $lang['left367'] ?></b></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row->origin_name); ?></td>
                <td><?php
                    echo htmlspecialchars($row->destiny_country_name);
                    echo ' - ' . htmlspecialchars($row->state_name);
                    echo ' - ' . htmlspecialchars($row->city_name);
                ?></td>
                <td><?php echo htmlspecialchars($row->mode_name); ?></td>  <!-- mostrar nombre del modo -->
                <td><?php echo number_format((float)$row->initial_range, 2, '.', '.'); ?></td>
                <td><?php echo number_format((float)$row->final_range,   2, '.', '.'); ?></td>
                <td><?php echo number_format((float)$row->price,         2, '.', '.'); ?></td>
                <td class="text-center">
                    <a href="shipping_tariffs_edit.php?id=<?php echo (int)$row->id; ?>">
                        <i class="ti-pencil" aria-hidden="true"></i>
                    </a>
                    <a id="item_<?php echo (int)$row->id; ?>"
                       onclick="cdp_eliminar('<?php echo (int)$row->id; ?>');"
                       class="delete"
                       data-rel="<?php echo htmlspecialchars($row->order_service_options); ?>">
                        <div class="icon-holder"><i class="fi fi-rr-trash"></i></div>
                    </a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang); ?>
    </div>
</div>
<?php
} else { ?>
    <div class="text-center">
        <i class="display-3 text-warning d-block">
            <img src="assets/images/alert/ohh_shipment.png" width="150" />
        </i>
        <p class="mt-3"><?php echo $lang['message_ajax_error2']; ?></p>
    </div>
<?php
}
