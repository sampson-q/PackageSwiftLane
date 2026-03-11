<?php
require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('view_warehouse');


$db          = new Conexion;
$user        = new User;
$core        = new Core;
$userData    = $user->cdp_getUserData();
$permissions = $user->cdp_getUserPermissions();

$search         = cdp_sanitize($_REQUEST['search']);
$status_courier = intval($_REQUEST['status_courier']);

$sWhere = "";

if ($userData->userlevel == 3) {
    $sWhere .= " and a.driver_id = '" . $_SESSION['userid'] . "'";
} elseif ($userData->userlevel == 1) {
    $sWhere .= " and a.sender_id = '" . $_SESSION['userid'] . "'";
} elseif ($userData->userlevel == 6) {
    $agency_branch_id = cdp_getAgencyBranchIdForUser($userData->name_off);
    $sWhere .= " and a.agency = '" . (int)$agency_branch_id . "'";
}

if ($search != null) {
    $sWhere .= " and CONCAT(a.order_prefix, a.order_no) LIKE '%" . $search . "%'";
}

if ($status_courier > 0) {
    $sWhere .= " and a.status_courier = '" . $status_courier . "'";
}

// Date range filter — same approach as report_top_users_ajax_air.php
$range = cdp_sanitize($_REQUEST['range'] ?? '');
if (!empty($range)) {
    $parts      = explode(' - ', $range);
    $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $parts[0])));
    $end_date   = date('Y-m-d', strtotime(str_replace('/', '-', $parts[1])));
    $sWhere    .= " and DATE(a.order_date) BETWEEN '$start_date' AND '$end_date'";
}

$filterby = intval($_REQUEST['filterby']);

if ($filterby > 0) {
    $is_pickup_filter = ($filterby == 1) ? 1 : 0;
    $sWhere .= " and a.is_pickup = '" . $is_pickup_filter . "'";
}

if ($filterby == 3) {
    $sWhere .= " and a.is_consolidate = '1'";
}

// Pagination
$page      = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page  = 10;
$adjacents = 4;
$offset    = ($page - 1) * $per_page;

$db->cdp_query("UPDATE cdb_add_order SET status_invoice = 3 WHERE due_date < now() and status_invoice != 1 and order_payment_method > 1");
$db->cdp_execute();


// Warehouse-relevant statuses only:
// 2  = Received Office    (arrived at facility)
// 4  = In_Warehouse       (explicitly in warehouse)
// 6  = Available          (available for collection at office)
// 10 = Approved           (reserve approved)
// 12 = Rejected           (booking cancelled)
// 13 = Consolidate        (consolidated shipments held in warehouse)
// 19 = Invoiced           (quotation approved)
// 21 = Cancelled
// 23 = Pending_payment
// 25 = Not Shipped
$warehouse_statuses = implode(',', [2, 4, 6, 10, 12, 13, 19, 21, 23, 25]);

$sql = "SELECT a.order_incomplete, a.status_invoice, a.is_consolidate, a.is_pickup, a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options, b.mod_style, b.color
        FROM cdb_add_order AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        $sWhere
        and a.status_courier IN ($warehouse_statuses)
        ORDER BY order_id DESC
        ";

$db->cdp_query($sql);
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
                    <th><b><?php echo $lang['ltracking'] ?></b></th>
                    <th><b><?php echo $lang['ddate'] ?></b></th>
                    <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                        <th><b><?php echo $lang['left498'] ?></b></th>
                    <?php } ?>
                    <th><b><?php echo $lang['left499'] ?></b></th>
                    <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                        <th><b><?php echo $lang['lorigin'] ?></b></th>
                    <?php } ?>
                    <th><b><?php echo $lang['ldestination'] ?></b></th>
                    <th><b><?php echo $lang['lpayment'] ?></b></th>
                    <th><b><?php echo $lang['lstatusshipment'] ?></b></th>
                    <th><b><?php echo $lang['ship-all5'] ?></b></th>
                    <th></th>
                    <th><b><?php echo $lang['global-3'] ?></b></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="projects-tbl">
                <?php if (!$data) { ?>
                    <tr>
                        <td colspan="6">
                            <?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150'/></i>", false; ?>
                        </td>
                    </tr>
                <?php } else { ?>

                    <?php
                    $count = 0;
                    foreach ($data as $row) {

                        $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->sender_id . "'");
                        $sender_data = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_recipients WHERE id = '" . $row->receiver_id . "'");
                        $receiver_data = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id = '" . $row->order_pay_mode . "'");
                        $met_payment = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '14'");
                        $status_style_pickup = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '13'");
                        $status_style_consolidate = $db->cdp_registro();

                        $db->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail where order_no='" . $row->order_no . "'");
						$consolidate_id = $db->cdp_registro() -> consolidate_id;
						
                        $db->cdp_query("SELECT status_courier FROM cdb_consolidate where consolidate_id='" . $consolidate_id . "'");
						$consolidate_status_courier = $db->cdp_registro() -> status_courier;
                        
                        $db->cdp_query("SELECT * FROM cdb_styles where id='" . $consolidate_status_courier . "'");
						$consolidate_style = $db->cdp_registro();

                        if ($row->status_invoice == 1) {
                            $text_status = $lang['invoice_paid'];
                            $label_class = "label-success";
                        } elseif ($row->status_invoice == 2) {
                            $text_status = $lang['invoice_pending'];
                            $label_class = "label-warning";
                        } elseif ($row->status_invoice == 3) {
                            $text_status = $lang['verify_payment'];
                            $label_class = "label-info";
                        }

                        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track = '" . $row->order_prefix . $row->order_no . "'");
                        $address_order = $db->cdp_registro();

                        $db->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail WHERE order_no = '" . $row->order_no . "'");
                        $consolidate_id = $db->cdp_registro()->consolidate_id;

                        $db->cdp_query("SELECT status_courier FROM cdb_consolidate WHERE consolidate_id = '" . $consolidate_id . "'");
                        $consolidate_status_courier = $db->cdp_registro()->status_courier;

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '" . $consolidate_status_courier . "'");
                        $consolidate_style = $db->cdp_registro();
                    ?>

                        <tr class="card-hovera">

                            <td><b><a href="courier_view.php?id=<?php echo $row->order_id; ?>"><?php echo $row->order_prefix . $row->order_no; ?></a></b></td>

                            <td><?php echo $row->order_date; ?></td>

                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                <td><?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?></td>
                            <?php } ?>

                            <td><?php echo $receiver_data->fname; ?> <?php echo $receiver_data->lname; ?></td>

                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                <td><?php echo $address_order->sender_country; ?>-<?php echo $address_order->sender_city; ?></td>
                            <?php } ?>

                            <td><?php echo $address_order->recipient_country; ?>-<?php echo $address_order->recipient_city; ?></td>

                            <td><?php echo isset($met_payment->name_pay) ? $met_payment->name_pay : 'N/A'; ?></td>

                            <td>
                                <span style="background: <?php echo $row->is_consolidate ? $consolidate_style->color : $row->color; ?>;" class="label label-large">
                                    <?php echo $row->is_consolidate ? $consolidate_style->mod_style . 'd' : $row->mod_style; ?>
                                </span>
                                <br>

                                <?php if ($row->is_pickup == true) { ?>
                                    <span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large"><?php echo $status_style_pickup->mod_style; ?></span>
                                <?php } ?>

                                <?php if ($row->is_consolidate == true) { ?>
                                    <span style="background: <?php echo $status_style_consolidate->color; ?>;" class="label label-large"><?php echo $status_style_consolidate->mod_style . 'd'; ?></span>
                                <?php } ?>

                                <br>

                                <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0) { ?>
                                    <?php if ($userData->userlevel != 1) { ?>
                                        <span style="background: #5BE472;" class="label label-large"><?php echo $lang['leftorder34'] ?></span>
                                    <?php } ?>
                                    <?php if ($userData->userlevel == 1) { ?>
                                        <span style="background: #FC5239;" class="label label-large"><?php echo $lang['left1018'] ?></span>
                                    <?php } ?>
                                <?php } ?>
                            </td>

                            <td>
                                <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
                            </td>

                            <td>
                                <?php if ($row->status_invoice == 2 && $userData->userlevel == 1) { ?>
                                    <a style="background: #34e89e;" class="label label" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
                                        <i style="color:#343a40" class="fas fa-dollar-sign"></i>
                                        &nbsp;<?php echo $lang['leftorder35'] ?>
                                    </a>
                                <?php } ?>
                            </td>

                            <td>
                                <span class="label label-large <?php echo $label_class; ?>"><?php echo $text_status; ?></span>
                            </td>

                        </tr>
                    <?php $count++; } ?>

                <?php } ?>
            </tbody>
        </table>

        <div class="pull-right">
            <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang); ?>
        </div>
        <script src="dataJs/courier_ajax.js"></script>
    </div>
<?php } ?>