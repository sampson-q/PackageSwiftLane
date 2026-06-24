<?php
require_once('../../loader.php');
require_once('../../helpers/querys.php');

$id       = isset($_GET['id'])       ? (int)$_GET['id']       : 0;
$page     = isset($_GET['page'])     ? (int)$_GET['page']     : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

if (!$id) exit;

$offset = ($page - 1) * $per_page;

$db          = new Conexion;
$db->cdp_query("SELECT * FROM cdb_consolidate_detail WHERE consolidate_id='" . $id . "' LIMIT $offset, $per_page");
$order_items = $db->cdp_registros();

// also need the parent row for value_weight etc.
$data       = cdp_getConsolidatePrint($id);
$row_order  = $data['data'];

if ($order_items):
    $sumador_total     = 0;
    $sumador_libras    = 0;
    $sumador_volumetric = 0;

    foreach ($order_items as $row_order_item):
        $weight_item = (float)$row_order_item->weight;
        $length_item = (float)$row_order_item->length;
        $width_item  = (float)$row_order_item->width;
        $height_item = (float)$row_order_item->height;
        $meter       = (float)$row_order->volumetric_percentage;

        $total_metric = ($length_item * $width_item * $height_item) / $meter;
        $total_metric = round($total_metric, 2);

        if ($weight_item > $total_metric) {
            $calculate_weight    = $weight_item;
            $sumador_libras     += $weight_item;
        } else {
            $calculate_weight    = $total_metric;
            $sumador_volumetric += $total_metric;
        }

        $precio_total   = $calculate_weight * (float)$row_order->value_weight;
        $sumador_total += $precio_total;

        $db->cdp_query("SELECT user_id, sender_id FROM cdb_add_order WHERE order_no = '" . $row_order_item->order_no . "'");
        $package_owners = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $package_owners->sender_id . "'");
        $sender      = $db->cdp_registro();
        $sender_name = $sender->fname . ' ' . $sender->lname;

        $db->cdp_query("SELECT total_order, order_id, status_courier FROM cdb_add_order WHERE order_no='" . $row_order_item->order_no . "'");
        $order_details = $db->cdp_registro();

        $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id = '" . $order_details->order_id . "'");
        $items = $db->cdp_registros();

        $db->cdp_query("SELECT * FROM cdb_styles where id='" . $order_details->status_courier . "'");
        $package_style = $db->cdp_registro();

        $postal_tracking = cdp_getPackageTrackingLegacyAware($row_order_item->order_id);
        ?>
        <tr class="card-hover">
            <td><b><?php echo $sender_name; ?></b></td>
            <td><span class="label" style="background-color: <?php echo $package_style->color; ?>"><?php echo $package_style->mod_style; ?></span></td>
            <td><b><?php echo $row_order_item->order_prefix . $row_order_item->order_no; ?></b></td>
            <td><b><?php echo $postal_tracking->tracking_number; ?></b></td>
            <td class="text-right">
                <?php foreach ($items as $item) { ?>
                    <b><?php echo (int) $item->order_item_quantity; ?></b><br>
                <?php } ?>
            </td>
            <td>
                <?php foreach ($items as $item) { ?>
                    <b><?php echo $item->order_item_description; ?></b><br>
                <?php } ?>
            </td>
            
            <td colspan="3"><?php echo number_format($row_order_item->weight, 2, '.', ''); ?></td>
            <td colspan="3"><?php echo cdb_money_format($order_details->total_order); ?></td>
        </tr>
        <?php
    endforeach;
endif;