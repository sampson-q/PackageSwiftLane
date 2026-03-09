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

$db       = new Conexion;
$user     = new User;
$core     = new Core;
$userData = $user->cdp_getUserData();

$range = cdp_sanitize($_REQUEST['range'] ?? '');
$page  = cdp_sanitize($_REQUEST['page']  ?? '');


// Build date filter if a range is provided
$sWhere = '';
if (!empty($range)) {
    // “range” is something like "2025/05/29 - 2025/05/31" (as provided by the JS picker)
    $parts = explode(' - ', $range);

    // Normalize to YYYY-MM-DD
    $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $parts[0])));
    $end_date   = date('Y-m-d', strtotime(str_replace('/', '-', $parts[1])));
    $sWhere = " AND DATE(a.order_date) BETWEEN '$start_date' AND '$end_date'";
}

switch ($page) {
    case 'topshipper':
        $sql = "SELECT
                    b.id,
                    b.lname,
                    b.fname,
                    b.avatar,
                    b.username,
                    b.email,
                    b.locker,
                    b.phone,
                    b.created,
                    b.lastlogin,
                    a.sender_id,
                    COUNT(*) AS total_shipments
                FROM cdb_customers_packages AS a
                INNER JOIN cdb_users AS b
                    ON b.id = a.sender_id
                WHERE
                    a.status_courier IN (27, 15)
                    $sWhere
                GROUP BY
                    b.id, b.lname, b.fname, b.avatar, b.username, b.email, b.locker, b.phone, b.created, b.lastlogin, a.sender_id
                ORDER BY
                    total_shipments DESC
                LIMIT 1
                ";
        break;

    case 'topinvoice':
        // (unchanged; if you only care about topshipper you can remove this branch)
        $sql = "SELECT
                    b.id,
                    b.lname,
                    b.fname,
                    b.avatar,
                    b.username,
                    b.email,
                    b.locker,
                    b.phone,
                    b.created,
                    b.lastlogin,
                    a.sender_id
                FROM cdb_customers_packages AS a
                INNER JOIN cdb_users AS b
                    ON b.id = a.sender_id
                WHERE
                    a.status_invoice = 1
                    $sWhere
                ORDER BY
                    a.total_order DESC
                LIMIT 1
               ";
        break;

    default:
        echo '<div class="alert alert-info text-center">'
             . $lang['report-text91'] .
             '</div>';
        exit;
}

// Execute the chosen query
$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();

if ($numrows < 1) {
    echo '<div class="alert alert-info text-center">'
         . $lang['report-text91'] .
         '</div>';
    exit;
}

$topShipper = $db->cdp_registro();

function renderUserCard($row, $lang) {
    ?>
    <div>
        <div class="card-body">
            <a class="btn btn-primary"
               href="customers_edit.php?user=<?php echo htmlspecialchars($row->id, ENT_QUOTES); ?>">
                <?php echo $lang['report-general038']; ?>
            </a>
            <div class="mb-30"
                 style="display: flex; justify-content: center; align-items: center; gap: 100px; margin-top: 30px;">
                <div style="text-align: center;">
                    <label for="avatarInput" style="cursor: pointer;">
                        <img
                            id="avatarPreview"
                            src="assets/<?php echo htmlspecialchars($row->avatar ?: 'uploads/blank.png', ENT_QUOTES); ?>"
                            class="rounded-circle"
                            width="120" height="120"
                            alt="Avatar preview"
                        />
                    </label>
                </div>
            </div>

            <div style="text-align: center;">
                <h4 class="card-title m-t-10">
                    <?php echo htmlspecialchars($row->fname . ' ' . $row->lname, ENT_QUOTES); ?>
                </h4>
                
                <h6 class="card-subtitle">               
                    <div class="badge badge-pill badge-light font-16">
                        <!-- <span class="ti-mobile text-warning"></span> -->
                        <?php echo htmlspecialchars($row->phone, ENT_QUOTES); ?>
                    </div>
                </h6>

                <h6 class="card-subtitle">
                    <div class="badge badge-pill badge-light font-16">
                        <!-- <span class="ti-user text-warning"></span> -->
                        <?php echo htmlspecialchars($row->email, ENT_QUOTES); ?>
                    </div>
                </h6>
            </div>
        </div>
    </div>
    <?php
}

function renderShipmentDetails($table_page, $total_pages, $adjacents, $shipments, $lang, $core, $userData) {
    ?>
    <div class="table-responsive">
        <table id="zero_config" class="table table-condensed table-hover table-striped custom-table-checkbox">
            <thead>
                <tr>
                    <th><b><?php echo $lang['ltracking'];?></b></th>
                    <th><b><?php echo $lang['left46'];?></b></th>
                    <th><b><?php echo $lang['ddate'];?></b></th>
                    <th><b><?php echo $lang['ldestination'];?></b></th>
                    <th><b><?php echo $lang['ship-all5'];?></b></th>
                    <th><b><?php echo $lang['global-3'];?></b></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="projects-tbl">
                <?php
                if (empty($shipments)) {
                    echo '<tr><td colspan="7" class="text-center">'
                         . $lang['report-text91'] .
                         '</td></tr>';
                } else {
                    foreach ($shipments as $row) {
                        
                        global $db;
                        $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->sender_id . "'");
                        $sender_data = $db->cdp_registro();

                        // Fetch driver, if any
                        if (!empty($row->driver_id)) {
                            $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->driver_id . "'");
                            $driver_data = $db->cdp_registro();
                        }

                        // Fetch courier company, if any
                        if (!empty($row->order_courier)) {
                            $db->cdp_query("SELECT * FROM cdb_courier_com WHERE id = '" . $row->order_courier . "'");
                            $courier_com = $db->cdp_registro();
                        }

                        // Fetch payment method, if any
                        if (!empty($row->order_pay_mode)) {
                            $db->cdp_query("SELECT * FROM cdb_met_payment WHERE id = '" . $row->order_pay_mode . "'");
                            $met_payment = $db->cdp_registro();
                        }

                        // Fetch shipping mode, if any
                        if (!empty($row->order_service_options)) {
                            $db->cdp_query("SELECT * FROM cdb_shipping_mode WHERE id = '" . $row->order_service_options . "'");
                            $order_service_options = $db->cdp_registro();
                        }

                        // Fetch pre-alert, if any
                        if (!empty($row->tracking_purchase)) {
                            $db->cdp_query("SELECT * FROM cdb_pre_alert WHERE tracking = '" . $row->tracking_purchase . "'");
                            $package_prealert = $db->cdp_registro();
                        }

                        // Fetch status style (ID = 13)
                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '13'");
                        $status_style_consolidate = $db->cdp_registro();

                        // Determine invoice status text/label
                        if ($row->status_invoice == 1) {
                            $text_status = $lang['invoice_paid'];
                            $label_class = "label-success";
                        } elseif ($row->status_invoice == 0 || $row->status_invoice == 2) {
                            $text_status = $lang['invoice_pending'];
                            $label_class = "label-warning";
                        } elseif ($row->status_invoice == 3) {
                            $text_status = $lang['verify_payment'];
                            $label_class = "label-info";
                        } else {
                            $text_status = $lang['invoice_unknown'];
                            $label_class = "label-default";
                        }

                        // Fetch destination address
                        $order_track_full = $row->order_prefix . $row->order_no;
                        $db->cdp_query("SELECT * FROM cdb_address_shipments WHERE order_track = '$order_track_full'");
                        $address_order = $db->cdp_registro();
                        ?>
                        <tr class="card-hovera">
                            <td>
                                <b>
                                    <a href="customer_packages_view.php?id=<?php echo $row->order_id; ?>">
                                        <?php echo htmlspecialchars($row->order_prefix . $row->order_no, ENT_QUOTES); ?>
                                    </a>
                                </b>
                            </td>
                            
                            <td><?php echo htmlspecialchars($row->tracking_purchase, ENT_QUOTES); ?></td>
                            
                            <td><?php echo htmlspecialchars($row->order_date, ENT_QUOTES); ?></td>
                            
                            <td>
                                <?php
                                    echo htmlspecialchars(
                                        $address_order->sender_country . ' - ' . $address_order->sender_city,
                                        ENT_QUOTES
                                    );
                                ?>
                            </td>
                            
                            <td>
                                <b><?php echo $core->currency; ?></b>
                                <?php echo cdb_money_format($row->total_order); ?>
                            </td>
                            
                            <td>
                                <span class="label label-large <?php echo $label_class; ?>">
                                    <?php echo $text_status; ?>
                                </span>
                            </td>
                            
                            <td class="text-right">
                                <div class="btn-group">
                                    <button class="btn btn-block btn-outline-dark btn-sm dropdown-toggle"
                                            type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu" style="overflow-y: auto; max-height: 200px;">

                                        <!-- “View Details” -->
                                        <a class="dropdown-item"
                                           href="customer_packages_view.php?id=<?php echo $row->order_id; ?>">
                                            <i style="color:#343a40" class="fa fa-search"></i>
                                            &nbsp;<?php echo $lang['leftorder266']; ?>
                                        </a>

                                        <!-- “Add Payment” (if status_invoice == 2 and user is admin) -->
                                        <?php if ($row->status_invoice == 2 && $userData->userlevel == 1): ?>
                                            <a class="dropdown-item"
                                               href="add_payment_gateways_package.php?id_order=<?php echo $row->order_id; ?>">
                                                <i style="color:#343a40" class="fas fa-dollar-sign"></i>
                                                &nbsp;<?php echo $lang['left533020015']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Verify Payment” (if status_invoice == 3 and not admin) -->
                                        <?php if ($row->status_invoice == 3 && $userData->userlevel != 1): ?>
                                            <a class="dropdown-item"
                                               data-toggle="modal"
                                               data-target="#detail_payment_packages"
                                               data-id="<?php echo $row->order_id; ?>"
                                               data-customer="<?php echo $row->sender_id; ?>">
                                                <i style="color:#343a40" class="fas fa-dollar-sign"></i>
                                                &nbsp;<?php echo $lang['left533020016']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Edit Shipment” (if allowed and userlevel 9 or 2) -->
                                        <?php if ( ($userData->userlevel == 9 || $userData->userlevel == 2)
                                                 && $row->is_consolidate != 1
                                                 && $row->status_courier != 8
                                                 && $row->status_invoice != 1 ): ?>
                                            <a class="dropdown-item"
                                               href="customer_package_edit.php?id=<?php echo $row->order_id; ?>">
                                                <i style="color:#343a40" class="ti-pencil"></i>
                                                &nbsp;<?php echo $lang['left533020036']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Delete Shipment” (if same conditions as “Edit”) -->
                                        <?php if ( ($userData->userlevel == 9 || $userData->userlevel == 2)
                                                 && $row->is_consolidate != 1
                                                 && $row->status_courier != 8
                                                 && $row->status_invoice != 1 ): ?>
                                            <a class="dropdown-item"
                                               data-id="<?php echo $row->order_id; ?>"
                                               href="#"
                                               data-toggle="modal"
                                               data-target="#myModalDeletesPa">
                                                <i style="color:#f62d51" class="ti-trash"></i>
                                                &nbsp;<?php echo $lang['leftorder34445']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Assign Driver” (if not delivered/cancelled and userlevel 9 or 2) -->
                                        <?php if ( $row->status_courier != 21
                                               && $row->status_courier != 12
                                               && ($userData->userlevel == 9 || $userData->userlevel == 2)
                                               && $userData->userlevel != 1
                                               && $row->is_consolidate != 1
                                               && $row->status_courier != 8 ): ?>
                                            <a class="dropdown-item"
                                               data-toggle="modal"
                                               data-target="#modalDriver"
                                               data-id_shipment="<?php echo $row->order_id; ?>">
                                                <i style="color:#ff0000" class="fas fa-car"></i>
                                                &nbsp;<?php echo $lang['left208']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Print Invoice” -->
                                        <a class="dropdown-item"
                                           target="blank"
                                           href="print_invoice_package.php?id=<?php echo $row->order_id; ?>">
                                            <i style="color:#343a40" class="ti-printer"></i>
                                            &nbsp;<?php echo $lang['toolprint']; ?>
                                        </a>

                                        <!-- “Print Label” -->
                                        <a class="dropdown-item"
                                           href="print_label_package.php?id=<?php echo $row->order_id; ?>"
                                           target="_blank">
                                            <i style="color:#343a40" class="ti-printer"></i>
                                            &nbsp;<?php echo $lang['toollabel']; ?>
                                        </a>

                                        <!-- “Update Tracking” & “Deliver” (if paid, not admin, etc.) -->
                                        <?php if ($row->status_invoice == 1
                                               && $userData->userlevel != 1
                                               && $row->is_consolidate != 1
                                               && $row->status_courier != 8
                                               && $row->status_courier != 21
                                               && $row->status_courier != 12): ?>

                                            <a class="dropdown-item"
                                               href="customer_package_tracking.php?id=<?php echo $row->order_id; ?>">
                                                <i style="color:#20c997" class="ti-reload"></i>
                                                &nbsp;<?php echo $lang['toolupdate']; ?>
                                            </a>

                                            <a class="dropdown-item"
                                               href="customer_package_deliver.php?id=<?php echo $row->order_id; ?>">
                                                <i style="color:#2962FF" class="ti-package"></i>
                                                &nbsp;<?php echo $lang['tooldeliver']; ?>
                                            </a>
                                        <?php endif; ?>

                                        <!-- “Send Email to Customer” (commented out) -->
                                        <?php if ($userData->userlevel != 1): ?>
                                            <a class="dropdown-item"
                                               href="#"
                                               data-toggle="modal"
                                               data-id="<?php echo $row->order_id; ?>"
                                               data-email="<?php echo $sender_data->email; ?>"
                                               data-order="<?php echo htmlspecialchars($row->order_prefix . $row->order_no, ENT_QUOTES); ?>"
                                               data-target="#myModal">
                                                <i class="fas fa-envelope"></i>
                                                &nbsp;<?php echo $lang['leftorder36']; ?>
                                            </a>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                    } // end foreach
                }
                ?>
            </tbody>
        </table>

        <!-- If you want to re-enable pagination later, make sure $total_pages and $adjacents are defined -->
        <div class="pull-right">
            <?php echo cdp_paginate($table_page, $total_pages, $adjacents, $lang); ?>
        </div>
    </div>
    <?php
}

renderUserCard($topShipper, $lang);

// 1) Define pagination parameters
$per_page   = 4;
$table_page = isset($_REQUEST['table_page']) ? intval($_REQUEST['table_page']) : 1;
$offset     = ($table_page - 1) * $per_page;
$adjacents  = 4;

// 2) Count total matching shipments (no LIMIT)
$countSQL = "SELECT COUNT(*) AS total_count
    FROM cdb_customers_packages AS cp
    WHERE
      cp.sender_id       = $topShipper->sender_id
      AND cp.status_courier IN (27, 15)
";
$db->cdp_query($countSQL);
$db->cdp_execute();
$rowCount     = $db->cdp_registro();
$total_count  = (int) $rowCount->total_count;
$total_pages  = ceil($total_count / $per_page);

// 3) Fetch only the current page of shipments
$innerSQL = "SELECT *
    FROM cdb_customers_packages AS cp
    WHERE
      cp.sender_id       = $topShipper->sender_id
      AND cp.status_courier IN (27, 15)
    ORDER BY cp.order_date DESC
    LIMIT $offset, $per_page
";

$db->cdp_query($innerSQL);
$db->cdp_execute();
$shipments = $db->cdp_registros();

// 4) Render the table with pagination controls
renderShipmentDetails($table_page, $total_pages, $adjacents, $shipments, $lang, $core, $userData);

?>
