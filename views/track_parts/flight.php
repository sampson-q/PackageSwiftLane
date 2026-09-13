<?php
/**
 * Flight Details card — air shipments only. Expects $e, $mode, $progress
 * (from cdp_airJourney). Flight number and AWB rows appear once recorded.
 */
if ($mode !== 'air') return;
$route   = $progress['route'];
$lastUpd = !empty($progress['last_updated']) ? date('M d, Y · h:i A', strtotime($progress['last_updated'])) : '';
?>
<div class="trk-card" data-reveal>
	<div class="trk-card__head"><span class="ico"><i class="mdi mdi-airplane-takeoff"></i></span> Flight Details</div>
	<div class="trk-card__body">
		<div class="trk-route">
			<div class="trk-route__pt">
				<span class="trk-route__code"><?php echo $e($route['origin']['code']); ?></span>
				<span class="trk-route__city"><?php echo $e($route['origin']['city']); ?></span>
				<span class="trk-route__role">Origin</span>
			</div>
			<span class="trk-route__arrow"><i class="mdi mdi-airplane"></i></span>
			<div class="trk-route__pt">
				<span class="trk-route__code"><?php echo $e($route['transit']['code']); ?></span>
				<span class="trk-route__city"><?php echo $e($route['transit']['city']); ?></span>
				<span class="trk-route__role">Transit</span>
			</div>
			<span class="trk-route__arrow"><i class="mdi mdi-airplane"></i></span>
			<div class="trk-route__pt">
				<span class="trk-route__code"><?php echo $e($route['destination']['code']); ?></span>
				<span class="trk-route__city"><?php echo $e($route['destination']['city']); ?></span>
				<span class="trk-route__role">Destination</span>
			</div>
		</div>
		<div class="trk-dl">
			<div class="trk-dl__row"><span class="trk-dl__k">Origin</span><span class="trk-dl__v"><?php echo $e($route['origin']['label']); ?></span></div>
			<div class="trk-dl__row"><span class="trk-dl__k">Transit</span><span class="trk-dl__v"><?php echo $e($route['transit']['label']); ?></span></div>
			<div class="trk-dl__row"><span class="trk-dl__k">Destination</span><span class="trk-dl__v"><?php echo $e($route['destination']['label']); ?></span></div>
			<?php if (!empty($progress['flights'])) : ?>
				<div class="trk-dl__row"><span class="trk-dl__k">Flight Number</span><span class="trk-dl__v"><?php echo $e(implode(' · ', $progress['flights'])); ?></span></div>
			<?php endif; ?>
			<?php if (!empty($progress['awb'])) : ?>
				<div class="trk-dl__row"><span class="trk-dl__k">AWB Number</span><span class="trk-dl__v"><?php echo $e($progress['awb']); ?></span></div>
			<?php endif; ?>
			<div class="trk-dl__row"><span class="trk-dl__k">Last Updated</span><span class="trk-dl__v"><?php echo $lastUpd ? $e($lastUpd) : 'No update recorded yet'; ?></span></div>
		</div>
	</div>
</div>
