<?php

require_once("../loader.php");

$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = cdp_sanitize($_REQUEST['search']);
$status_courier = intval($_REQUEST['status_courier']);

// If no search is provided, output message and exit.
if (empty($search)) {
    echo '<div class="alert alert-info">Please enter a tracking number to search.</div>';
    exit;
}

$sWhere = "";

// Add search criteria to query
$sWhere .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE '%" . $search . "%' OR c.tracking_number LIKE '%" . $search . "%')";

if ($status_courier > 0) {
    $sWhere .= " AND a.status_courier = '" . $status_courier . "'";
}

// Build the main query
$sql = "SELECT 
            a.order_id,
            a.order_prefix,
            a.order_no,
            a.order_date,
            a.status_courier,
            a.person_receives,
            a.order_datetime,
            b.order_item_description,
            c.tracking_number,
            c.estimated_eta
        FROM
            cdb_add_order AS a
        INNER JOIN 
            cdb_add_order_item AS b ON a.order_id = b.order_id
        LEFT JOIN 
            cdb_package_tracking_number AS c ON a.order_id = c.order_id
        WHERE 
            a.status_courier != 14
        $sWhere
        ORDER BY 
            a.order_id DESC";

// Count results
$query_count = $db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();

// If no records found, display message and exit.
if ($numrows <= 0) {
    echo '<div class="alert alert-warning">No results found for the given tracking number.</div>';
    exit;
}

// Get records for current page
$db->cdp_query($sql);
$data = $db->cdp_registros();
?>

<div class="table-responsive">
    <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
        <thead>
            <tr>
                <?php if ($userData->userlevel == 9) { ?>
                    <th>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input sl-all" id="cstall">
                            <label class="custom-control-label" for="cstall"></label>
                        </div>
                    </th>
                <?php } ?>
                <th><b><?php echo $lang['ltracking'] ?></b></th>
                <th><b><?php echo $lang['customTracking'] ?></b></th>
                <th><b><?php echo $lang['contents'] ?></b></th>
                <th><b><?php echo $lang['pickupBy'] ?></b></th>
                <th><b><?php echo $lang['dateTime'] ?></b></th>
                <th><b></b></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (!$data) { ?>
                <tr>
                    <td colspan="6">
                        <?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
                    </td>
                </tr>
            <?php } else { 
                $count = 0;
                foreach ($data as $row) {
            ?>
                    <tr class="card-hovera">
                        <?php if ($userData->userlevel == 9) { ?>
                            <td class="chb">
                                <?php if ($row->status_courier != 8 && $row->status_courier != 21) { ?>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" value="<?php echo $row->order_no; ?>" name="checkbox[]" id="cst_<?php echo $count; ?>">
                                        <label class="custom-control-label" for="cst_<?php echo $count; ?>">&nbsp;</label>
                                    </div>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td>
                            <b>
                                <a href="courier_view.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>">
                                    <?php echo $row->order_prefix . $row->order_no; ?>
                                </a>
                            </b>
                        </td>
                        <td><?php echo !empty($row->tracking_number) ? $row->tracking_number : 'N/A'; ?></td>
                        <td><?php echo $row->order_item_description; ?></td>
                        <td><?php echo $row->person_receives ? $row->person_receives : 'N/A'; ?></td>
                        <td><?php echo $row->order_datetime; ?></td>
                        <td align='center'>
                            <button class="btn btn-block btn-outline-dark btn-sm" aria-haspopup="true">
                                <a href="courier_deliver_shipment.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooldeliver']; ?>">
                                    <i style="color:#2962FF" class="ti-package"></i>&nbsp;<?php echo $lang['tooldeliver']; ?>
                                </a>
                            </button>
                        </td>
                    </tr>
                <?php $count++; } ?>
            <?php } ?>
        </tbody>
    </table>
    <script src="dataJs/pickup_client_ajax.js"></script>
</div>
