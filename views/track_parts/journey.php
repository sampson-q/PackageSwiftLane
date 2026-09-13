<?php
/**
 * Shipment Journey card — shared by views/track.php and
 * views/track_online_shopping.php. Expects in scope:
 *   $e (escaper), $progress (cdp_trackProgress / cdp_airJourney result),
 *   $status_name, $mode ('air'|'sea'), $current_step.
 *
 * Air steps carry desc / at / flight_no; sea steps do not. Copy rules: state
 * facts about the shipment, never instruct the customer.
 */
$fmtDate = function ($d) { return $d ? date('M d, Y · h:i A', strtotime($d)) : ''; };
?>
<div class="trk-card" data-reveal>
	<div class="trk-card__head">
		<span class="ico"><i class="mdi mdi-transit-connection-variant"></i></span> Shipment Journey
		<?php if ($mode === 'air') : ?>
			<span class="trk-card__hint"><?php echo $e($progress['route']['origin']['code']); ?> → <?php echo $e($progress['route']['transit']['code']); ?> → <?php echo $e($progress['route']['destination']['code']); ?></span>
		<?php endif; ?>
	</div>
	<div class="trk-journey <?php echo $mode === 'air' ? 'trk-journey--air' : ''; ?>">
		<?php foreach ($progress['steps'] as $i => $s) :
			$cls = $i < $progress['index'] ? 'is-done' : ($i === $progress['index'] ? 'is-current' : '');
			$at  = !empty($s['at']) ? $fmtDate($s['at']) : ''; ?>
			<div class="trk-stage <?php echo $cls; ?>">
				<div class="trk-stage__dot"><?php echo $i < $progress['index'] ? '<i class="mdi mdi-check"></i>' : $s['icon']; ?></div>
				<div class="trk-stage__body">
					<div class="trk-stage__label">
						<?php echo $e($s['label']); ?>
						<?php if ($i === $progress['index']) : ?><span class="trk-now">Current Stage</span><?php endif; ?>
					</div>
					<?php if (!empty($s['desc'])) : ?>
						<div class="trk-stage__desc"><?php echo $e($s['desc']); ?></div>
					<?php endif; ?>
					<?php if ($at || !empty($s['flight_no'])) : ?>
						<div class="trk-stage__meta">
							<?php if ($at) : ?><span class="trk-stage__time"><i class="mdi mdi-clock-outline"></i> <?php echo $e($at); ?></span><?php endif; ?>
							<?php if (!empty($s['flight_no'])) : ?><span class="trk-stage__flight"><i class="mdi mdi-airplane"></i> Flight <?php echo $e($s['flight_no']); ?></span><?php endif; ?>
						</div>
					<?php elseif ($mode === 'air' && $i <= $progress['index']) : ?>
						<div class="trk-stage__meta"><span class="trk-stage__time is-missing">Date and time not recorded</span></div>
					<?php endif; ?>
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
