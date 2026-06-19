<?php
require_once('helpers/querys.php');

$db          = new Conexion;
$user        = new User;
$core        = new Core;
$userData    = $user->cdp_getUserData();

$search         = cdp_sanitize($_REQUEST['search'] ?? '');
$status_courier = intval($_REQUEST['status_courier'] ?? 0);
$filterby       = intval($_REQUEST['filterby'] ?? 0);
$range          = cdp_sanitize($_REQUEST['range'] ?? '');

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

$range_label = '';
if (!empty($range)) {
    $parts      = explode(' - ', $range);
    $start_date = date('Y-m-d', strtotime(str_replace('/', '-', $parts[0])));
    $end_date   = date('Y-m-d', strtotime(str_replace('/', '-', $parts[1])));
    $sWhere    .= " and DATE(a.order_date) BETWEEN '$start_date' AND '$end_date'";
    $range_label = $start_date . ' - ' . $end_date;
}

if ($filterby > 0) {
    $is_pickup_filter = ($filterby == 1) ? 1 : 0;
    $sWhere .= " and a.is_pickup = '" . $is_pickup_filter . "'";
}

if ($filterby == 3) {
    $sWhere .= " and a.is_consolidate = '1'";
}

$warehouse_statuses = implode(',', [1, 4, 8, 15, 16, 23, 32, 33]);

$sql = "SELECT a.order_incomplete, a.status_invoice, a.is_consolidate, a.is_pickup, a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options, b.mod_style, b.color
        FROM cdb_add_order AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        $sWhere
        and a.status_courier IN ($warehouse_statuses)
        ORDER BY order_id DESC";

$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();

$db->cdp_query($sql);
$data = $db->cdp_registros();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $direction_layout; ?>">

<head>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo 'Warehouse'; ?> | <?php echo $core->site_name; ?></title>
    <link href="assets/custom_dependencies/print_report.css" rel="stylesheet">
    <style type="text/css">
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 12px;
            word-wrap: break-word;
        }
        thead th {
            background: #f1f1f1;
        }
        h2 {
            text-align: center;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>

<body>
    <div id="page-wrap">

        <h2>
            <?php echo h($core->site_name); ?><br>
            <?php echo 'Warehouse'; ?>
            <?php if ($range_label !== '') : ?>
                <br>[<?php echo h($range_label); ?>]
            <?php endif; ?>
        </h2>

        <table>
            <thead>
                <tr>
                    <th><b>#</b></th>
                    <th><b><?php echo 'Swift ' . $lang['ltracking']; ?></b></th>
                    <th><b><?php echo $lang['ltracking']; ?></b></th>
                    <th><b><?php echo $lang['ddate']; ?></b></th>
                    <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                        <th><b><?php echo $lang['left498']; ?></b></th>
                    <?php } ?>
                    <th><b><?php echo $lang['lstatusshipment']; ?></b></th>
                    <th><b><?php echo $lang['global-3']; ?></b></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($numrows > 0) :
                    $count = 0;
                    foreach ($data as $row) :
                        $count++;

                        $db->cdp_query("SELECT * FROM cdb_users WHERE id = '" . $row->sender_id . "'");
                        $sender_data = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '13'");
                        $status_style_consolidate = $db->cdp_registro();

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '14'");
                        $status_style_pickup = $db->cdp_registro();

                        $db->cdp_query("SELECT consolidate_id FROM cdb_consolidate_detail WHERE order_no = '" . $row->order_no . "'");
                        $consolidate_id = $db->cdp_registro()->consolidate_id;

                        $db->cdp_query("SELECT status_courier FROM cdb_consolidate WHERE consolidate_id = '" . $consolidate_id . "'");
                        $consolidate_status_courier = $db->cdp_registro()->status_courier;

                        $db->cdp_query("SELECT * FROM cdb_styles WHERE id = '" . $consolidate_status_courier . "'");
                        $consolidate_style = $db->cdp_registro();

                        if ($row->status_invoice == 1) {
                            $text_status = $lang['invoice_paid'];
                        } elseif ($row->status_invoice == 2) {
                            $text_status = $lang['invoice_pending'];
                        } elseif ($row->status_invoice == 3) {
                            $text_status = $lang['verify_payment'];
                        } else {
                            $text_status = '';
                        }

                        $postal_tracking = cdp_getPackageTrackingLegacyAware($row->order_id);
                        $status_label = $row->is_consolidate ? $consolidate_style->mod_style . 'd' : $row->mod_style;
                ?>
                    <tr>
                        <td><b><?php echo $count; ?></b></td>
                        <td><b><?php echo h($row->order_prefix . $row->order_no); ?></b></td>
                        <td><?php echo h($postal_tracking->tracking_number ?? ''); ?></td>
                        <td><?php echo h($row->order_date); ?></td>
                        <?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>
                            <td><?php echo h(trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? ''))); ?></td>
                        <?php } ?>
                        <td>
                            <?php echo h($status_label); ?>
                            <?php if ($row->is_pickup) echo '<br>' . h($status_style_pickup->mod_style); ?>
                            <?php if ($row->is_consolidate) echo '<br>' . h($status_style_consolidate->mod_style . 'd'); ?>
                        </td>
                        <td><?php echo h($text_status); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No records</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <button class='button -dark center no-print' onClick="window.print();" style="font-size:16px; margin-top: 20px;"><?php echo $lang['report-text5']; ?> &nbsp;&nbsp; <i class="fa fa-print"></i></button>
    </div>

</body>

</html>
