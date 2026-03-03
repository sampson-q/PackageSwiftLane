<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $direction_layout; ?>">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link type='text/css' href='assets/custom_dependencies/print_consolidate.css' rel='stylesheet' />

	<title><?php echo $lang['inv-label9'] ?></title>

</head>

<body onload="window.print();">
	<div id="page-wrap">

		<?php

		$userData = $user->cdp_getUserData();

		require_once('helpers/querys.php');

		if (!isset($_GET['data'])) {

			cdp_redirect_to("consolidate_list.php");
		}


		if (isset($_GET['data'])) {

			$data = json_decode($_GET['data']);

			foreach ($data as $key) {

				$data_key = cdp_getConsolidatePrintMultiple($key);


				$row_order = $data_key['data'];


				$db->cdp_query("SELECT * FROM cdb_shipping_mode where id= '" . $row_order->order_service_options . "'");
				$order_service_options = $db->cdp_registro();

				$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
				$sender_data = $db->cdp_registro();

				$db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
				$receiver_data = $db->cdp_registro();


				$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->c_prefix . $row_order->c_no . "'");
				$address_order = $db->cdp_registro();

				$db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $row_order->order_deli_time . "'");
				$delivery_time = $db->cdp_registro();

				$db->cdp_query("SELECT * FROM cdb_styles where id= '" . $row_order->status_courier . "'");
				$status_courier = $db->cdp_registro();

				$db->cdp_query("SELECT * FROM cdb_courier_com where id= '" . $row_order->order_courier . "'");
				$courier_com = $db->cdp_registro();
		?>


			<div class="container">
		        <div class="header">
		            <?php echo ($core->logo) ? '<img src="assets/' . $core->logo . '" class="logo" alt="' . $core->site_name . '" width="' . $core->thumb_w . '" height="' . $core->thumb_h . '"/>' : $core->site_name; ?>
		            <h2><strong><?php echo $order_service_options->ship_mode; ?></strong></h2>
		        </div>
		        <div class="address">
		            <p><strong><?php echo $lang['report-text37'] ?>:</strong> <b><?php echo $core->site_name; ?></b></p>
		            <p><strong><?php echo $lang['lphone'] ?>:</strong> <?php echo $core->c_phone; ?></p>
		            <p><strong><?php echo $lang['laddress'] ?>:</strong> <strong><?php echo $core->c_address; ?></strong></p>
		            <p><strong><?php echo $lang['lorigin'] ?>:</strong> <?php echo $core->c_country; ?>-<?php echo $core->c_city; ?></p>
		        </div>
		        <div class="address">
		            <p><strong><?php echo $lang['report-text388'] ?>:</strong> <b><?php echo $receiver_data->fname . ' ' . $receiver_data->lname; ?></b></p>
		            <p><strong><?php echo $lang['lphone'] ?>:</strong> <?php echo $receiver_data->phone; ?></p>
		            <p><strong><?php echo $lang['laddress'] ?>:</strong> <?php echo $address_order->recipient_address; ?></p>
		            <p><strong><?php echo $lang['ldestination'] ?>:</strong> <?php echo $address_order->recipient_country; ?></p>
		        </div>
		        <div class="address">
		            <p><strong><?php echo $lang['inv-label4'] ?>:</strong> <?php echo $delivery_time->delitime; ?></p>
		            <p><strong><?php echo $lang['inv-label5'] ?>:</strong> <?php echo $courier_com->name_com; ?></p>
		        </div>
		        <div class="barcode">
		            <img src='https://barcode.tec-it.com/barcode.ashx?data=<?php echo $row_order->c_prefix . $row_order->c_no; ?>&code=Code39&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=150&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50' alt='<?php echo $row_order->c_prefix . $row_order->c_no; ?>' />
		        </div>
		        <div class="qr-code">
		            <img src='https://barcode.tec-it.com/barcode.ashx?data=<?php echo $row_order->c_prefix . $row_order->c_no; ?> &code=QRCode&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=120&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=120&eclevel=L' alt='<?php echo $row_order->c_prefix . $row_order->c_no; ?> ' />
		        </div>
		        <div class="print-button">
		            <button onclick="window.print()"><?php echo $lang['inv-label9'] ?></button>
		        </div>
		    </div>


		<?php
			}
		} ?>
	</div>


</body>

</html>