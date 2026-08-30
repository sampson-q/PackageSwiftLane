<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



require_once('helpers/querys.php');
require_once('helpers/track_progress.php');
require_once('helpers/asset.php');

if (isset($_GET['order_track'])) {
	$rawOrderTrackInput = trim((string)$_GET['order_track']);
	$sanitizedOrderTrack = preg_replace('/[^A-Za-z0-9-]/', '', $rawOrderTrackInput);
	$is_package_source = false;
	$results = cdp_getCourierTrack($sanitizedOrderTrack);
	$track = $results['data'];
	// Fallback: resolve customer package shipments (cdb_customers_packages) when
	// the number is not a courier shipment, so package tracking numbers also work.
	if (!$track) {
		$results = cdp_getCustomersPackagesTrack($sanitizedOrderTrack);
		$track = $results['data'];
		$is_package_source = true;
	}
} else {

	cdp_redirect_to("tracking.php");
}


$sender_data = null;
$receiver_data = null;
$delivery_time = null;
$address_order = null;
$order_items = [];
$item_description = '';
$sumador_libras = 0;
$sumador_volumetric = 0;
$count = 0;

$db->cdp_query("
	SELECT a.id, a.order_track, a.t_dest, a.t_date, a.t_city, a.comments, a.status_courier, b.mod_style FROM cdb_courier_track as a
	INNER JOIN cdb_styles as b ON a.status_courier = b.id
	where a.order_track=:order_track ORDER BY a.t_date");
$db->bind(':order_track', $sanitizedOrderTrack);
$db->cdp_execute();

$courier_track = $db->cdp_registros();
if ($track != null) {

	$db->cdp_query("SELECT * FROM cdb_users where id=:sender_id");
	$db->bind(':sender_id', (int)$track->sender_id);
	$db->cdp_execute();
	$sender_data = $db->cdp_registro();

	// recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
	if (($track->recipient_type ?? 'recipient') === 'user') {
		$db->cdp_query("SELECT * FROM cdb_users where id=:receiver_id");
	} else {
		$db->cdp_query("SELECT * FROM cdb_recipients where id=:receiver_id");
	}
	$db->bind(':receiver_id', (int)$track->receiver_id);
	$db->cdp_execute();
	$receiver_data = $db->cdp_registro();

	$db->cdp_query("SELECT * FROM cdb_delivery_time where id=:delivery_time_id");
	$db->bind(':delivery_time_id', (int)$track->order_deli_time);
	$db->cdp_execute();
	$delivery_time = $db->cdp_registro();


	$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track=:order_track");
	$db->bind(':order_track', (string)$track->order_prefix . (string)$track->order_no);
	$db->cdp_execute();
	$address_order = $db->cdp_registro();
	if (!$address_order) {
		$address_order = (object)[
			'sender_country' => '',
			'sender_city' => '',
			'sender_address' => '',
			'recipient_country' => '',
			'recipient_city' => '',
			'recipient_address' => ''
		];
	}


	$db->cdp_query("SELECT SUM(order_item_length) as total0 from cdb_add_order_item where order_id=:order_id");
	$db->bind(':order_id', (int)$track->order_id);
	$db->cdp_execute();
	$row0 = $db->cdp_registro();

	$rw_add0 = $row0->total0;

	// volumetric query of the box width

	$db->cdp_query("SELECT SUM(order_item_width) as total1 from cdb_add_order_item where order_id=:order_id");
	$db->bind(':order_id', (int)$track->order_id);
	$db->cdp_execute();
	$row1 = $db->cdp_registro();

	$rw_add1 = $row1->total1;

	// volumetric query of the box width

	$db->cdp_query("SELECT SUM(order_item_height) as total2 from cdb_add_order_item where order_id=:order_id");
	$db->bind(':order_id', (int)$track->order_id);
	$db->cdp_execute();
	$row2 = $db->cdp_registro();

	$rw_add2 = $row2->total2;

	$length = $rw_add0; //Length
	$width = $rw_add1; //Width
	$height = $rw_add2; //Height


	$volPercent = (float)$track->volumetric_percentage;
	$total_metric = $volPercent > 0 ? ($length * $width * $height / $volPercent) : 0;


	$db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id=:order_id");
	$db->bind(':order_id', (int)$track->order_id);
	$db->cdp_execute();
	$order_items = $db->cdp_registros();



	$sumador_libras = 0;
	$sumador_volumetric = 0;
	$count = 0;
	foreach ($order_items as $row_item) {

		$weight_item = $row_item->order_item_weight;

		$item_description = $row_item->order_item_description;

		$total_metric = $volPercent > 0 ? ($row_item->order_item_length * $row_item->order_item_width * $row_item->order_item_height / $volPercent) : 0;

		// calculate weight x price
		if ($weight_item > $total_metric) {

			$calculate_weight = $weight_item;
			$sumador_libras += $weight_item; //Sumador

		} else {

			$calculate_weight = $total_metric;
			$sumador_volumetric += $total_metric; //Sumador
		}

		$count++;
	}
}

// ── Presentation-layer derived values ───────────────────────────────────────
$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$initial = function ($name) { $name = trim((string)$name); return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?'; };

$mode = 'sea';
$status_name = $track ? (string)$track->mod_style : '';
$status_color = ($track && !empty($track->color)) ? $track->color : '#f2b21b';
$progress = null;
$print_href = 'print_inv_ship_track.php';

if ($track) {
	// Freight mode + consolidation flag live on the source table, not in the
	// track query — fetch them from whichever table resolved this shipment.
	$srcTable = $is_package_source ? 'cdb_customers_packages' : 'cdb_add_order';
	$db->cdp_query("SELECT order_item_category, is_consolidate FROM $srcTable WHERE order_id=:id LIMIT 1");
	$db->bind(':id', (int)$track->order_id);
	$db->cdp_execute();
	$meta = $db->cdp_registro();
	$order_item_category = $meta ? (int)$meta->order_item_category : 0;
	$is_consolidate_flag = $meta ? (int)$meta->is_consolidate : 0;

	$mode = in_array($order_item_category, cdp_shipCategoryIds('air'), true) ? 'air' : 'sea';

	// Effective status: a consolidated package shows its consolidation's status.
	$eff = cdp_getEffectiveTrackStatus($track->order_no, (int)$track->status_courier, $is_consolidate_flag, $is_package_source);
	$status_name  = $eff->mod_style !== '' ? $eff->mod_style : (string)$track->mod_style;
	$status_color = $eff->color;
	$progress     = cdp_trackProgress($eff->status_id, $status_name);


	$print_href = $is_package_source ? 'print_customer_package_track.php' : 'print_inv_ship_track.php';
}

$total_weight  = ($sumador_libras > $sumador_volumetric) ? $sumador_libras : $sumador_volumetric;
$sender_name   = ($track && $sender_data) ? trim($sender_data->fname . ' ' . $sender_data->lname) : '';
$receiver_name = ($track && $receiver_data) ? trim($receiver_data->fname . ' ' . $receiver_data->lname) : '';
$hist_count    = is_array($courier_track) ? count($courier_track) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="keywords" content="Swiftlane - Integrated Web Shipping System">
	<meta name="author" content="iSolveAfrica Ltd.">
	<meta name="description" content="">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="theme-color" content="#0d1b2a">
	<title><?php echo $e($lang['left127']); ?> | <?php echo $e($core->site_name); ?></title>
	<link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $e($core->favicon); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/css_main_swiftlane/css/materialdesignicons.min.css">
	<link rel="stylesheet" href="<?= cdp_asset('assets/css_main_swiftlane/css/track-details.css') ?>">
</head>

<body>
<div class="trk">

	<!-- ── Top bar ──────────────────────────────────────────────────────── -->
	<header class="trk-nav">
		<a class="trk-nav__logo" href="index.php">
			<?php echo ($core->logo_web)
				? '<img src="assets/' . $e($core->logo_web) . '" alt="' . $e($core->site_name) . '">'
				: '<span>' . $e($core->site_name) . '</span>'; ?>
		</a>
		<div class="trk-nav__actions">
			<a href="tracking.php" class="trk-btn trk-btn--ghost"><i class="mdi mdi-magnify"></i> <?php echo $e($lang['left127']); ?></a>
			<a href="index.php" class="trk-btn trk-btn--dark"><i class="mdi mdi-home-outline"></i> <?php echo $e($lang['left180'] ?? 'Home'); ?></a>
		</div>
	</header>

	<?php if (!$track) : ?>

	<!-- ══════════════════════════ NOT FOUND ══════════════════════════════ -->
	<section class="trk-empty">
		<div class="trk-empty__box">
			<div class="trk-empty__art">
				<span class="ring"></span>
				<span class="ring"></span>
				<span class="em">🔍</span>
			</div>
			<h1><?php echo $e($lang['track-shipment1'] ?? 'We couldn’t find that shipment'); ?></h1>
			<p>We looked everywhere but no shipment matches the tracking number you entered. It may have a typo, or it hasn’t been registered in our system yet.</p>
			<span class="trk-empty__code"><?php echo $e($rawOrderTrackInput); ?></span>
			<p class="mt-2"><?php echo $e($lang['track-shipment2'] ?? 'Please double-check the number, or reach out to our team and we’ll be glad to help.'); ?></p>
			<div class="trk-empty__actions">
				<a href="tracking.php" class="trk-btn trk-btn--grad"><i class="mdi mdi-magnify"></i> Try Another Number</a>
				<a href="index.php" class="trk-btn trk-btn--ghost"><i class="mdi mdi-home-outline"></i> <?php echo $e($lang['left183'] ?? 'Back to Home'); ?></a>
			</div>
		</div>
	</section>

	<?php else :
		$track_code   = $track->order_prefix . $track->order_no;
		$latest_entry = $hist_count > 0 ? $courier_track[$hist_count - 1] : null;
		$current_step = $progress['steps'][$progress['index']] ?? null;
	?>

	<!-- ══════════════════════════ FOUND ═════════════════════════════════ -->

	<!-- ── Header band ──────────────────────────────────────────────────── -->
	<section class="trk-head">
		<div class="trk-head__inner">
			<div>
				<p class="trk-eyebrow"><?php echo $e($lang['track-shipment4'] ?? 'Shipment Tracking'); ?></p>
				<h1 class="trk-code">
					<?php echo $e($track_code); ?>
					<button type="button" class="trk-copy" data-copy="<?php echo $e($track_code); ?>"><i class="mdi mdi-content-copy"></i> Copy</button>
				</h1>
				<div class="trk-tags">
					<span class="trk-tag"><i class="mdi <?php echo $mode === 'air' ? 'mdi-airplane' : 'mdi-ferry'; ?>"></i> <?php echo $mode === 'air' ? 'Air Freight' : 'Sea Freight'; ?></span>
					<span class="trk-tag"><i class="mdi mdi-calendar-blank-outline"></i> <?php echo $e($track->order_date); ?></span>
					<span class="trk-tag"><i class="mdi mdi-package-variant-closed"></i> <?php echo (int)$count; ?> <?php echo $e($lang['track-shipment11'] ?? 'Packages'); ?></span>
				</div>
			</div>
			<div class="trk-state">
				<span class="trk-status-badge" style="--c: <?php echo $e($status_color); ?>;">
					<span class="dot"></span><?php echo $e($status_name); ?>
				</span>
				<span class="trk-live">
					<span class="dot"></span>
					<?php echo $latest_entry
						? 'Last updated ' . $e(date('M d, Y · h:i A', strtotime($latest_entry->t_date)))
						: 'Live tracking'; ?>
				</span>
			</div>
		</div>
	</section>

	<main class="trk-main">
		<div class="trk-grid">

			<!-- ── Main column ──────────────────────────────────────────── -->
			<div class="trk-col">

				<!-- Journey -->
				<div class="trk-card" data-reveal>
					<div class="trk-card__head"><span class="ico"><i class="mdi mdi-transit-connection-variant"></i></span> Shipment Journey</div>
					<div class="trk-journey">
						<?php foreach ($progress['steps'] as $i => $s) :
							$cls = $i < $progress['index'] ? 'is-done' : ($i === $progress['index'] ? 'is-current' : ''); ?>
							<div class="trk-stage <?php echo $cls; ?>">
								<div class="trk-stage__dot"><?php echo $i < $progress['index'] ? '<i class="mdi mdi-check"></i>' : $s['icon']; ?></div>
								<div class="trk-stage__label">
									<?php echo $e($s['label']); ?>
									<?php if ($i === $progress['index']) : ?><span class="trk-now">Current Stage</span><?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>

						<div class="trk-journey__foot">
							<?php if ($progress['index'] >= count($progress['steps']) - 1) : ?>
								This shipment has reached the final stage of its journey.
							<?php else : ?>
								This shipment is at <b><?php echo $e($current_step['label'] ?? $status_name); ?></b>.
								The stages above update as it moves.
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Shipment contents -->
				<div class="trk-card" data-reveal>
					<div class="trk-card__head"><span class="ico"><i class="mdi mdi-cube-outline"></i></span> Shipment Details</div>
					<div class="trk-card__body">
						<div class="trk-metrics">
							<div class="trk-metric">
								<div class="trk-metric__v"><span data-countup="<?php echo (int)$count; ?>">0</span></div>
								<div class="trk-metric__k"><?php echo $e($lang['track-shipment11'] ?? 'Packages'); ?></div>
							</div>
							<div class="trk-metric">
								<div class="trk-metric__v"><span data-countup="<?php echo (float)cdp_round_out($total_weight); ?>">0</span> <small>kg</small></div>
								<div class="trk-metric__k"><?php echo $e($lang['track-shipment13'] ?? 'Total Weight'); ?></div>
							</div>
							<div class="trk-metric">
								<div class="trk-metric__v" style="font-size:1.05rem;"><?php echo $e($track->order_date); ?></div>
								<div class="trk-metric__k"><?php echo $e($lang['track-shipment8'] ?? 'Date of Shipment'); ?></div>
							</div>
							<div class="trk-metric">
								<div class="trk-metric__v" style="font-size:1.05rem;"><?php echo $e($delivery_time ? $delivery_time->delitime : '—'); ?></div>
								<div class="trk-metric__k"><?php echo $e($lang['track-shipment9'] ?? 'Shipping Time'); ?></div>
							</div>
						</div>

						<?php if (!empty($item_description)) : ?>
						<div class="trk-dl">
							<div class="trk-dl__row">
								<span class="trk-dl__k"><?php echo $e($lang['message_title_track4'] ?? 'Description'); ?></span>
								<span class="trk-dl__v"><?php echo $e($item_description); ?></span>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Sender & Recipient -->
				<div class="trk-two">
					<div class="trk-card" data-reveal>
						<div class="trk-card__body">
							<div class="trk-partyhead">
								<div class="trk-avatar trk-avatar--a"><?php echo $e($initial($sender_name)); ?></div>
								<div>
									<h4><?php echo $e($lang['track-shipment5'] ?? 'Sender'); ?></h4>
									<p><?php echo $e($sender_name ?: '—'); ?></p>
								</div>
							</div>
							<div class="trk-dl">
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment6'] ?? 'Collection City'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->sender_city ?: '—'); ?></span></div>
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment7'] ?? 'Origin'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->sender_country ?: '—'); ?></span></div>
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment10'] ?? 'Contact Address'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->sender_address ?: '—'); ?></span></div>
							</div>
						</div>
					</div>
					<div class="trk-card" data-reveal>
						<div class="trk-card__body">
							<div class="trk-partyhead">
								<div class="trk-avatar trk-avatar--b"><?php echo $e($initial($receiver_name)); ?></div>
								<div>
									<h4><?php echo $e($lang['track-shipment15'] ?? 'Recipient'); ?></h4>
									<p><?php echo $e($receiver_name ?: '—'); ?></p>
								</div>
							</div>
							<div class="trk-dl">
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment16'] ?? 'Delivery City'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->recipient_city ?: '—'); ?></span></div>
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment17'] ?? 'Destination'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->recipient_country ?: '—'); ?></span></div>
								<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment10'] ?? 'Contact Address'); ?></span><span class="trk-dl__v"><?php echo $e($address_order->recipient_address ?: '—'); ?></span></div>
							</div>
						</div>
					</div>
				</div>

				<?php
				$db->cdp_query("SELECT * FROM cdb_order_files where order_id=:order_id ORDER BY date_file");
				$db->bind(':order_id', (int)$track->order_id);
				$db->cdp_execute();
				$files_order = $db->cdp_registros();
				$numrows = $db->cdp_rowCount();
				if ($numrows > 0 || !empty($track->photo_delivered)) : ?>
				<!-- Proof of delivery & files -->
				<div class="trk-card" data-reveal>
					<div class="trk-card__head"><span class="ico"><i class="mdi mdi-image-outline"></i></span> <?php echo $e($lang['leftorder55'] ?? 'Proof of Delivery'); ?></div>
					<div class="trk-card__body">
						<?php if (!empty($track->photo_delivered)) : ?>
							<img src="<?php echo $e($track->photo_delivered); ?>" alt="Proof of delivery" class="trk-photo mb-3">
						<?php endif; ?>
						<?php if ($numrows > 0) : ?>
							<div class="trk-sub"><?php echo $e($lang['message_title_track1'] ?? 'Attached Files'); ?></div>
							<ul class="trk-files">
								<?php foreach ($files_order as $file) : ?>
									<li class="trk-file">
										<span class="trk-file__ico"><i class="mdi mdi-file-document-outline"></i></span>
										<a target="_blank" rel="noopener" href="<?php echo $e($file->url); ?>"><?php echo $e($file->name); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

			</div><!-- /.trk-col main -->

			<!-- ── Side column ──────────────────────────────────────────── -->
			<aside class="trk-col">

				<div class="trk-card" data-reveal>
					<div class="trk-card__head"><span class="ico"><i class="mdi mdi-clipboard-text-outline"></i></span> Summary</div>
					<div class="trk-card__body">
						<div class="trk-dl">
							<div class="trk-dl__row"><span class="trk-dl__k">Tracking Number</span><span class="trk-dl__v"><?php echo $e($track_code); ?></span></div>
							<div class="trk-dl__row"><span class="trk-dl__k">Status</span><span class="trk-dl__v"><?php echo $e($status_name); ?></span></div>
							<div class="trk-dl__row"><span class="trk-dl__k">Stage</span><span class="trk-dl__v"><?php echo $e($current_step['label'] ?? '—'); ?></span></div>
							<div class="trk-dl__row"><span class="trk-dl__k"><?php echo $e($lang['track-shipment19'] ?? 'Est. Delivery'); ?></span><span class="trk-dl__v"><?php echo $e($track->order_datetime ?: '—'); ?></span></div>
						</div>
						<a class="trk-btn trk-btn--grad mt-3" style="display:flex;justify-content:center;" target="_blank" href="<?php echo $e($print_href); ?>?id=<?php echo (int)$track->order_id; ?>">
							<i class="mdi mdi-printer"></i> <?php echo $e($lang['toolprint'] ?? 'Print'); ?>
						</a>
					</div>
				</div>

				<div class="trk-card" data-reveal>
					<div class="trk-card__head"><span class="ico"><i class="mdi mdi-history"></i></span> <?php echo $e($lang['track-shipment22'] ?? 'Shipping History'); ?></div>
					<div class="trk-card__body">
						<?php if ($hist_count > 0) :
							$reversed = array_reverse($courier_track); ?>
							<ul class="trk-timeline">
								<?php foreach ($reversed as $idx => $rows) :
									$loc = trim(($rows->t_dest ?? '') . ($rows->t_city ? ', ' . $rows->t_city : '')); ?>
									<li class="trk-tl <?php echo $idx === 0 ? 'is-latest' : ''; ?>">
										<span class="trk-tl__dot"></span>
										<div class="trk-tl__date"><?php echo $e(date('M d, Y · h:i A', strtotime($rows->t_date))); ?></div>
										<div class="trk-tl__status"><?php echo $e($rows->mod_style); ?></div>
										<?php if ($loc) : ?><div class="trk-tl__loc"><i class="mdi mdi-map-marker-outline"></i> <?php echo $e($loc); ?></div><?php endif; ?>
										<?php if (!empty($rows->comments)) : ?><div class="trk-tl__note"><?php echo $e($rows->comments); ?></div><?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="trk-note">No tracking updates have been logged for this shipment yet.</p>
						<?php endif; ?>
					</div>
				</div>

			</aside>

		</div><!-- /.trk-grid -->

		<div class="trk-foot">Powered by <?php echo $e($core->site_name); ?> · Live shipment tracking</div>

	</main>

	<?php endif; ?>

</div><!-- /.trk -->

	<script src="<?= cdp_asset('dataJs/track_details.js') ?>"></script>
</body>

</html>
