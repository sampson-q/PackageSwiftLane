<?php
if (!function_exists('cdp_asset')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/asset.php')) { $d = dirname($d); } if (is_file($d . '/helpers/asset.php')) require_once $d . '/helpers/asset.php'; }
require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_permission('warehouse_view');


$db          = new Conexion;
$user        = new User;
$core        = new Core;
$userData    = $user->cdp_getUserData();
$permissions = $user->cdp_getUserPermissions();
// Row checkboxes (and the bulk-action bar) only need to render when at least
// one bulk action is actually available to this user.
$canBulkDeliver = $user->cdp_hasPermission('deliver_shipment');
$canBulkAny     = $canBulkDeliver
    || $user->cdp_hasPermission('select_change_status_courier')
    || $user->cdp_hasPermission('assign_drivers')
    || $user->cdp_hasPermission('print_label');

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
    $sWhere .= " and " . cdp_trackingSearchSql($search, 'a');
}

if ($status_courier > 0) {
    $sWhere .= " and a.status_courier = '" . $status_courier . "'";
}

// Date range filter Ã¢â‚¬â€ same approach as report_top_users_ajax_air.php
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
$adjacents = 4;

// Rows-per-page filter (25 / 50 / 100 / all)
$per_page_req = $_REQUEST['per_page'] ?? 25;
if ($per_page_req === 'all') {
    $per_page = 'all';
} else {
    $per_page = in_array((int)$per_page_req, [25, 50, 100], true) ? (int)$per_page_req : 25;
}
$offset = ($per_page === 'all') ? 0 : ($page - 1) * $per_page;

// Throttled (was a full-scan UPDATE on every page load):
if (!function_exists('cdp_markOverdueInvoices')) { $d = __DIR__; while ($d !== dirname($d) && !is_file($d . '/helpers/overdue_invoices.php')) { $d = dirname($d); } if (is_file($d . '/helpers/overdue_invoices.php')) require_once $d . '/helpers/overdue_invoices.php'; }
cdp_markOverdueInvoices($db);


$warehouse_statuses = implode(',', [1, 4, 8, 15, 16, 23,32, 33]);

$sql = "SELECT a.order_incomplete, a.status_invoice, a.is_consolidate, a.is_pickup, a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options, a.fs_cleared_for_delivery, b.mod_style, b.color
        FROM cdb_add_order AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        $sWhere
        and a.status_courier IN ($warehouse_statuses)
        ORDER BY order_id DESC
        ";

$db->cdp_query("SELECT COUNT(*) AS cdp_total FROM (" . $sql . ") AS cdp_cnt");
$cdp_cnt_row = $db->cdp_registro();
$numrows = $cdp_cnt_row ? (int) $cdp_cnt_row->cdp_total : 0;

if ($per_page === 'all') {
    $db->cdp_query($sql);
    $total_pages = 1;
} else {
    $db->cdp_query($sql . " LIMIT $offset, $per_page");
    $total_pages = ceil($numrows / $per_page);
}
$data = $db->cdp_registros();


if ($numrows > 0) { ?>
    <div class="table-responsive">
        <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
            <thead>
                <tr>
                    <?php if ($canBulkAny) { ?>
                        <th>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input sl-all" id="cstall">
                                <label class="custom-control-label" for="cstall"></label>
                            </div>
                        </th>
                    <?php } ?>
                    <th><b><?php echo 'Swift ' . $lang['ltracking'] ?></b></th>
                    <th><b><?php echo $lang['ltracking'] ?></b></th>
                    <th><b><?php echo $lang['ddate'] ?></b></th>
                    <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                        <th><b><?php echo $lang['left498'] ?></b></th>
                    <?php } ?>
                    <th><b><?php echo $lang['lstatusshipment']?></b></th>
                    
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

                        $postal_tracking = cdp_getPackageTrackingLegacyAware($row->order_id);
                    ?>

                        <tr class="card-hovera">

                            <?php if ($canBulkAny) { ?>
                                <td>
                                    <?php if ($row->status_courier != 8) { ?>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" value="<?php echo $row->order_no; ?>" name="checkbox[]" id="cst_<?php echo $count; ?>">
                                            <label class="custom-control-label" for="cst_<?php echo $count; ?>">&nbsp;</label>
                                        </div>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td><b><a href="courier_view.php?id=<?php echo $row->order_id; ?>"><?php echo $row->order_prefix . $row->order_no; ?></a></b></td>
                            <td><?php echo $postal_tracking->tracking_number; ?></td>

                            <td><?php echo $row->order_date; ?></td>

                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                <td><?php echo $sender_data->fname; ?> <?php echo $sender_data->lname; ?></td>
                            <?php } ?>

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

                            <?php if ($row->status_courier != 8) { ?>
                            <td align='center'>
							    <div class="btn-group">
							        <button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							            <i class="fas fa-ellipsis-v"></i> <!-- Utiliza el icono de puntos suspensivos -->
							        </button>
							        <div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">
							            <?php if ($canBulkDeliver) { ?>
							                <?php if ((int) $row->fs_cleared_for_delivery === 1) { ?><a class="dropdown-item" href="javascript:void(0)"
							                   onclick="cdpWarehouseDeliver(<?php echo htmlspecialchars(json_encode([(string) $row->order_no]), ENT_QUOTES); ?>)"
							                   title="<?php echo 'Deliver Package' ?>">
							                    <i style="color:#343a40" class="fa fa-box"></i>&nbsp;<?php echo 'Deliver Package' ?>
							                </a><?php } else { ?><span class="dropdown-item text-muted" title="Accounts has not cleared this package for delivery yet"><i class="fa fa-lock"></i>&nbsp;Awaiting clearance</span><?php } ?>
							            <?php } ?>
							        </div>
							    </div>
							</td>
                            <?php } ?>
                        </tr>
                    <?php $count++; } ?>

                <?php } ?>
            </tbody>
        </table>

        <div class="pull-right">
            <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'warehouse_view'); ?>
        </div>
        <script src="<?= cdp_asset('dataJs/courier_ajax.js') ?>"></script>
    </div>
<?php } ?>

<!-- this change is to track this very commit -->