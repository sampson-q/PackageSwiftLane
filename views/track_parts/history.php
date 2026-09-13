<?php
/**
 * Shipping History card. Expects $e, $lang, $courier_track (oldest first),
 * $hist_count, $mode, $core. On air shipments a status that maps to a journey
 * stage is shown with the stage's customer-facing label.
 */
?>
<div class="trk-card" data-reveal>
	<div class="trk-card__head"><span class="ico"><i class="mdi mdi-history"></i></span> <?php echo $e($lang['track-shipment22'] ?? 'Shipping History'); ?></div>
	<div class="trk-card__body">
		<?php if ($hist_count > 0) :
			$reversed = array_reverse($courier_track); ?>
			<ul class="trk-timeline">
				<?php foreach ($reversed as $idx => $rows) :
					$loc   = trim(($rows->t_dest ?? '') . (!empty($rows->t_city) ? ', ' . $rows->t_city : ''));
					$label = $mode === 'air' ? cdp_airEventLabel($rows, $core->site_name) : (string) $rows->mod_style;
					$fl    = trim((string) ($rows->flight_no ?? ''));
					$aw    = trim((string) ($rows->awb_no ?? '')); ?>
					<li class="trk-tl <?php echo $idx === 0 ? 'is-latest' : ''; ?>">
						<span class="trk-tl__dot"></span>
						<div class="trk-tl__date"><?php echo $e(date('M d, Y · h:i A', strtotime($rows->t_date))); ?></div>
						<div class="trk-tl__status"><?php echo $e($label); ?></div>
						<?php if ($loc) : ?><div class="trk-tl__loc"><i class="mdi mdi-map-marker-outline"></i> <?php echo $e($loc); ?></div><?php endif; ?>
						<?php if ($fl || $aw) : ?>
							<div class="trk-tl__loc">
								<?php if ($fl) : ?><i class="mdi mdi-airplane"></i> Flight <?php echo $e($fl); ?><?php endif; ?>
								<?php if ($fl && $aw) : ?> · <?php endif; ?>
								<?php if ($aw) : ?>AWB <?php echo $e($aw); ?><?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($rows->comments)) : ?><div class="trk-tl__note"><?php echo $e($rows->comments); ?></div><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="trk-note">No tracking updates have been logged for this shipment yet.</p>
		<?php endif; ?>
	</div>
</div>
