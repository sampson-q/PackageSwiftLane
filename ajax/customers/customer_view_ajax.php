<?php
// ajax/courier_view_ajax.php
require_once("../../loader.php");
$db    = new Conexion;
$core  = new Core;

// Get and sanitize the locker id from the request
$locker = cdp_sanitize($_REQUEST['search']);

if (empty($locker) || !is_numeric($locker)) {
    echo '<div class="alert alert-info">Please enter a locker ID.</div>';
    exit;
}

// Check if the locker exists in cdb_users
$db->cdp_query("SELECT * FROM cdb_users WHERE id = '$locker'");
$userRecord = $db->cdp_registro();

if (!$userRecord) {
    echo '<div class="alert alert-danger">Locker ID not found.</div> ';
    exit;
}

$userId = $userRecord->id;

$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? (int)$_REQUEST['page'] : 1;
$per_page = (($_REQUEST['per_page'] ?? '') === 'all') ? 1000000000 : (in_array((int)($_REQUEST['per_page'] ?? 0), [25, 50, 100], true) ? (int)$_REQUEST['per_page'] : 25); //how much records you want to show
$offset = ($page - 1) * $per_page;
$adjacents = 4; // pages shown either side of the current page in the paginator

// Optional tracking-number filter (system, postal/carrier or legacy postal).
$track      = isset($_REQUEST['track']) ? cdp_sanitize($_REQUEST['track']) : '';
$trackWhere = ($track !== '') ? (" AND " . cdp_trackingSearchSql($track, 'a')) : '';

// Get orders for the sender (locker)
$sql = "SELECT
            a.order_incomplete,
            a.recipient_type,
            a.status_invoice,
            a.is_consolidate,
            a.is_pickup,
            a.total_order,
            a.order_id,
            a.order_prefix,
            a.order_no,
            a.order_date,
            a.sender_id,
            a.receiver_id,
            a.order_courier,
            a.order_pay_mode,
            a.status_courier,
            a.driver_id,
            a.order_service_options,
            b.mod_style,
            b.color,
            COALESCE(NULLIF(c.tracking_number, ''), a.tracking_num) AS tracking_number,
            c.estimated_eta
        FROM
            cdb_add_order AS a
        INNER JOIN 
            cdb_styles AS b ON a.status_courier = b.id
        LEFT JOIN 
            cdb_package_tracking_number AS c ON a.order_id = c.order_id WHERE a.sender_id = '$userId' $trackWhere
        ORDER BY
            a.order_id DESC";

$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();

$db->cdp_query($sql . " limit $offset, $per_page");
$orders = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);

if (!$orders) {
    echo '<div class="alert alert-warning">No orders found for this locker.</div>';
    exit;
}

// ---------------------------------------------------------------------------
// Total amount payable (GHS) — packages awaiting collection count, across all
// pages: "Ready for PickUp" (32), "Pending Collection" (1) and "Not Picked Up"
// (16), per cdp_isPayableStatus(). Each such package's stored USD total is converted to
// GHS and added; the tiered handling fee is added once per consolidation (on
// the consolidation's combined GHS total) and individually for non-consolidated
// packages.  e.g. A,B,C,D ready-for-pickup with B+C in one consolidation =>
//   (A + handling(A)) + (D + handling(D)) + (B + C + handling(B+C))
// ---------------------------------------------------------------------------
$rate_ghs = (float) ($core->exchange_rate ?? 1);

$db->cdp_query("SELECT order_id, total_order, status_courier, is_consolidate
                FROM cdb_add_order WHERE sender_id = :uid");
$db->bind(':uid', $userId);
$db->cdp_execute();
$all_pkgs = $db->cdp_registros();

$payable_ghs_subtotal   = 0.0;      // Σ GHS cost of ready-for-pickup packages
$payable_handling_total = 0.0;      // handling portion only
$consolidation_groups   = array();  // consolidate_id => combined GHS cost of its ready-for-pickup packages

$dbc = new Conexion;
foreach ($all_pkgs as $p) {
    // Collectible packages are payable here: Ready for PickUp, Pending Collection,
    // Not Picked Up (see cdp_isPayableStatus).
    if (!cdp_isPayableStatus($p->status_courier)) continue;

    $pkg_ghs = cdp_usdToGhs($p->total_order, $rate_ghs);
    $payable_ghs_subtotal += $pkg_ghs;

    if ((int) $p->is_consolidate === 1) {
        // Group by consolidation so the handling fee is charged once for B+C.
        $dbc->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail WHERE order_id = :oid LIMIT 1");
        $dbc->bind(':oid', $p->order_id);
        $dbc->cdp_execute();
        $crow = $dbc->cdp_registro();
        $key  = $crow ? ('c' . (int) $crow->consolidate_id) : ('o' . $p->order_id);
        if (!isset($consolidation_groups[$key])) $consolidation_groups[$key] = 0.0;
        $consolidation_groups[$key] += $pkg_ghs;
    } else {
        // Non-consolidated: handling fee on this package's own GHS total.
        $payable_handling_total += cdp_handlingFeeGhs($pkg_ghs);
    }
}
// One handling fee per consolidation, on its combined GHS total.
foreach ($consolidation_groups as $grp_total) {
    $payable_handling_total += cdp_handlingFeeGhs($grp_total);
}
$total_amount_payable_ghs = $payable_ghs_subtotal + $payable_handling_total;
?>

<div class="d-flex justify-content-end mb-2">
    <div class="text-right rate-box" style="background:#f8f9fa;border-radius:.5rem;padding:.5rem 1rem;border:1px solid #e3e6ea;">
        <small class="text-muted d-block">Total Amount Payable</small>
        <span class="h4 mb-0 text-success"><b>GHS <?php echo cdb_money_format_bar($total_amount_payable_ghs); ?></b></span>
        <?php if ($payable_handling_total > 0): ?>
            <small class="text-muted d-block">incl. handling fee GHS <?php echo cdb_money_format_bar($payable_handling_total); ?></small>
        <?php endif; ?>
    </div>
</div>

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
                <th><b><?php echo $lang['ltracking']; ?></b></th>
                <th><b><?php echo $lang['customTracking']; ?></b></th>
                <th><b><?php echo $lang['ship-all5']; ?></b></th>
                <th><b><?php echo $lang['lstatusshipment']; ?></b></th>
                <th><b><?php echo $lang['global-3']; ?></b></th>
                <th><b></b></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (!$orders) { ?>
                <tr>
                    <td colspan="6">
                        <?php echo "<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>", false; ?>
                    </td>
                </tr>
            <?php } else { 
                $count = 0;
                foreach ($orders as $row) {

                    // Get sender, recipient, driver, etc.
                    $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->sender_id . "'");
                    $sender_data = $db->cdp_registro();

                    // Recipient can live in cdb_users (recipient_type='user') or
                    // cdb_recipients. Resolve from the right table, with a fallback
                    // to the other so user-type recipients no longer show blank.
                    // if (isset($row->recipient_type) && $row->recipient_type === 'user') {
                    //     $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . intval($row->receiver_id) . "'");
                    //     $receiver_data = $db->cdp_registro();
                    //     if (!$receiver_data) {
                    //         $db->cdp_query("SELECT * FROM cdb_recipients WHERE id = '" . intval($row->receiver_id) . "'");
                    //         $receiver_data = $db->cdp_registro();
                    //     }
                    // } else {
                    //     $db->cdp_query("SELECT * FROM cdb_recipients WHERE id = '" . intval($row->receiver_id) . "'");
                    //     $receiver_data = $db->cdp_registro();
                    //     if (!$receiver_data) {
                    //         $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . intval($row->receiver_id) . "'");
                    //         $receiver_data = $db->cdp_registro();
                    //     }
                    // }

                    $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->driver_id . "'");
                    $driver_data = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id = '" . $row->order_courier . "'");
                    $courier_com = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id = '" . $row->order_pay_mode . "'");
                    $met_payment = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id = '" . $row->order_service_options . "'");
                    $order_service_options = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '14'");
                    $status_style_pickup = $db->cdp_registro();

                    $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '13'");
                    $status_style_consolidate = $db->cdp_registro();

                    if ($row->status_invoice == 1) {
                        $text_status = $lang['invoice_paid'];
                        $label_class = "label-success";
                    } else if ($row->status_invoice == 2) {
                        $text_status = $lang['invoice_pending'];
                        $label_class = "label-warning";
                    } else if ($row->status_invoice == 3) {
                        $text_status = $lang['verify_payment'];
                        $label_class = "label-info";
                    }

                    $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track='" . $row->order_prefix . $row->order_no . "'");
                    $address_order = $db->cdp_registro();
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
                            <br><small class="text-muted"><?php echo $row->order_date; ?></small>
                        </td>
                        <td><?php echo $row->tracking_number; ?></td>
                        <td>
                            <b><?php echo $core->currency; ?></b> <?php echo cdb_money_format($row->total_order); ?>
                            <?php if (cdp_isPayableStatus($row->status_courier)): ?>
                                <br><small class="text-muted" title="A handling fee is included in the total amount payable"><i class="fas fa-info-circle"></i> + handling fee</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background: <?php echo $row->color; ?>;" class="label label-large"><?php echo $row->mod_style; ?></span>
                            <br>
                            <?php if ($row->is_pickup) { ?>
                                <span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large">
                                    <?php echo $status_style_pickup->mod_style; ?>
                                </span>
                            <?php } ?>
                            <?php if ($row->is_consolidate) { ?>
                                <span style="background: <?php echo $status_style_consolidate->color; ?>;" class="label label-large">
                                    <?php echo $status_style_consolidate->mod_style; ?>
                                </span>
                            <?php } ?>
                            <br>
                            <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 1) { ?>
                                <span style="background: #5BE472;" class="label label-large">
                                    <?php echo $lang['leftorder34']; ?>
                                </span>
                            <?php } ?>
                            <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 9 && $userData->userlevel == 1) { ?>
                                <span style="background: #FC5239;" class="label label-large">
                                    <?php echo $lang['left1018']; ?>
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="label label-large <?php echo $label_class; ?>">
                                <?php echo $text_status; ?>
                            </span>
                        </td>
                        <td align='center'>
                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2 || $userData->userlevel == 3): ?>
                                <div class="btn-group mb-1" role="group">
                                    <a class="btn btn-outline-secondary btn-sm" href="print_inv_ship.php?id=<?php echo $row->order_id; ?>" target="_blank" title="Print receipt (verify payment)">
                                        <i class="ti-receipt"></i> <?php echo $lang['toolprint']; ?>
                                    </a>
                                    <a class="btn btn-outline-secondary btn-sm" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank" title="Print label">
                                        <i class="ti-printer"></i> <?php echo $lang['toollabel']; ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="btn-group">
                                <button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">
                                    <a class="dropdown-item" href="courier_view.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>" title="<?php echo $lang['tooledit']; ?>">
                                        <i style="color:#343a40" class="fa fa-search"></i>&nbsp;<?php echo $lang['leftorder266']; ?>
                                    </a>
                                    <?php if ($row->status_invoice == 2 && $userData->userlevel == 1) { ?>
                                        <a class="dropdown-item" href="add_payment_gateways_courier.php?id_order=<?php echo $row->order_id; ?>">
                                            <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder32']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->status_invoice == 3 && $userData->userlevel != 1) { ?>
                                        <a class="dropdown-item" data-toggle="modal" data-target="#detail_payment_packages" data-id="<?php echo $row->order_id; ?>" data-customer="<?php echo $row->sender_id; ?>">
                                            <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder33']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->order_incomplete == 0 && $row->is_pickup == 0 && $userData->userlevel != 1) { ?>
                                        <a class="dropdown-item" href="courier_accept.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooledit']; ?>">
                                            <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['leftorder34']; ?>
                                        </a>
                                        <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank">
                                            <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel']; ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row->order_incomplete == 1 && $userData->userlevel != 1 && $row->is_consolidate == 0) { ?>
                                        <?php if (($userData->userlevel == 9 || $userData->userlevel == 2) && $row->status_courier != 8) { ?>
                                            <a class="dropdown-item" href="courier_edit.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>" title="<?php echo $lang['tooledit']; ?>">
                                                <i style="color:#343a40" class="ti-pencil"></i>&nbsp;<?php echo $lang['tooledit']; ?>
                                            </a>
                                        <?php } ?>
                                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                            <?php if ($row->status_courier != 21 || $row->status_courier != 12) { ?>
                                                <?php if ($row->status_invoice != 1 && $row->status_invoice != 3) { ?>
                                                    <a class="dropdown-item" data-toggle="modal" data-target="#charges_list" data-id="<?php echo $row->order_id; ?>">
                                                        <i style="color:#343a40" class="fas fa-dollar-sign"></i>&nbsp;<?php echo $lang['leftorder35']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                            <?php if ($row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                <a class="dropdown-item" data-toggle="modal" data-target="#modalDriver" data-id_shipment="<?php echo $row->order_id; ?>">
                                                    <i style="color:#ff0000" class="fas fa-car"></i>&nbsp;<?php echo $lang['left208']; ?>
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($row->status_courier != 21 && $row->status_courier != 12) { ?>
                                            <a class="dropdown-item" target="_blank" href="print_inv_ship.php?id=<?php echo $row->order_id; ?>&tracking_number=<?php echo $row->tracking_number; ?>&eta=<?php echo $row->estimated_eta; ?>">
                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toolprint']; ?>
                                            </a>
                                            <a class="dropdown-item" href="print_label_ship.php?id=<?php echo $row->order_id; ?>" target="_blank">
                                                <i style="color:#343a40" class="ti-printer"></i>&nbsp;<?php echo $lang['toollabel']; ?>
                                            </a>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 3 || $userData->userlevel == 2) { ?>
                                                <?php if ($row->is_consolidate == 0 && $row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                    <a class="dropdown-item" href="courier_shipment_tracking.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['toolupdate']; ?>">
                                                        <i style="color:#20c997" class="ti-reload"></i>&nbsp;<?php echo $lang['toolupdate']; ?>
                                                    </a>
                                                    <a class="dropdown-item" href="courier_deliver_shipment.php?id=<?php echo $row->order_id; ?>" title="<?php echo $lang['tooldeliver']; ?>">
                                                        <i style="color:#2962FF" class="ti-package"></i>&nbsp;<?php echo $lang['tooldeliver']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                                                <?php if ($row->is_consolidate == 0 && $row->status_courier != 8 && $row->status_courier != 21 && $row->status_courier != 12) { ?>
                                                    <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalCancel">
                                                        <i style="color:#f62d51" class="fas fa-times-circle"></i>&nbsp;<?php echo $lang['leftorder34444']; ?>
                                                    </a>
                                                    <a class="dropdown-item" data-id="<?php echo $row->order_id; ?>" href="#" data-toggle="modal" data-target="#myModalDeletes">
                                                        <i style="color:#f62d51" class="ti-trash"></i>&nbsp;<?php echo $lang['leftorder34445']; ?>
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($userData->userlevel == 9 || $userData->userlevel == 2 || $userData->userlevel == 3) { ?>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-id="<?php echo $row->order_id; ?>" data-email="<?php echo $sender_data->email; ?>" data-order="<?php echo $row->order_prefix . $row->order_no; ?>" data-target="#myModal">
                                                    <i class="fas fa-envelope"></i>&nbsp;<?php echo $lang['leftorder36']; ?>
                                                </a>
                                            <?php } ?>
                                            <?php if ($row->status_courier != 8 || $row->status_courier != 32) { ?>
                                                <a class="dropdown-item" href="courier_deliver_shipment.php?id=<?php echo $row->order_id; ?>">
                                                    <i class="fas fa-envelope"></i>&nbsp;<?php echo $lang['tooldeliver']; ?>
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php $count++; } ?>
            <?php } ?>
        </tbody>
    </table>
    <div class="pull-right">
        <?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, 'courier_list');	?>
    </div>
    <script src="dataJs/customer_view_ajax.js"></script>
</div>