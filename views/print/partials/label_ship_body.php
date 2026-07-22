<?php
/**
 * Renders ONE box label from a normalized $L array. Included once per label by
 * the single/multiple label views so every label (shipment, consolidation,
 * customer package) is byte-for-byte the print_label_ship.php design.
 *
 * Expected $L keys (all strings unless noted):
 *   sys_tracking   system / package tracking (e.g. order_prefix.order_no)   [required]
 *   courier_track  carrier / postal tracking number, or '' / null           [optional]
 *   courier_name   courier company name                                     [optional]
 *   item_count     number of items (int)                                    [optional]
 *   weight         display weight (already formatted or numeric)            [optional]
 *   is_dangerous   truthy => show the Hazmat cell                           [optional]
 *   sender_name    sender full name                                         [optional]
 *   sender_line    sender location line                                     [optional]
 *   sender_locker  sender locker id                                         [optional]
 *   recip_name     recipient full name                                      [optional]
 *   recip_line     recipient address line                                   [optional]
 *   recip_phone    recipient phone, or '' / null                            [optional]
 *
 * @var array  $L
 * @var string $label_size 'normal' | 'small'
 * @var Core   $core   (global, from loader.php)
 * @var array  $lang   (global)
 */
$label_size = (isset($label_size) && $label_size === 'small') ? 'small' : 'normal';

$sys_tracking  = (string) ($L['sys_tracking'] ?? '');
$courier_track = trim((string) ($L['courier_track'] ?? ''));
$courier_name  = (string) ($L['courier_name'] ?? 'N/A');
$item_count    = $L['item_count'] ?? '';
$weight        = $L['weight'] ?? '';
$is_dangerous  = !empty($L['is_dangerous']);
$sender_name   = (string) ($L['sender_name'] ?? '');
$sender_line   = (string) ($L['sender_line'] ?? '');
$sender_locker = trim((string) ($L['sender_locker'] ?? ''));
$recip_name    = (string) ($L['recip_name'] ?? '');
$recip_line    = (string) ($L['recip_line'] ?? '');
$recip_phone   = trim((string) ($L['recip_phone'] ?? ''));

$sys_barcode_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($sys_tracking)
    . '&code=Code128&unit=Fit&dpi=96&imagetype=Gif&rotation=0&quiet=0&modulewidth=50';
$courier_barcode_url = $courier_track !== ''
    ? 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($courier_track)
        . '&code=Code128&unit=Fit&dpi=96&imagetype=Gif&rotation=0&quiet=0&modulewidth=50'
    : null;
?>
<?php if ($label_size === 'small') : ?>
    <?php
    // The one scannable code: carrier/postal tracking when we have it, else
    // the system tracking. The other identifier drops to the footer so the
    // sticker always carries barcode + system tracking + locker id.
    $small_is_courier = ($courier_track !== '');
    $small_barcode    = $small_is_courier ? $courier_barcode_url : $sys_barcode_url;
    $small_num        = $small_is_courier ? $courier_track : $sys_tracking;
    $small_num_label  = $small_is_courier ? 'Courier' : 'System';
    ?>
    <div class="label">
        <div class="panel">
            <div class="s-head">
                <div class="locker"><span class="lk">LKR</span> <?php echo h($sender_locker !== '' ? $sender_locker : 'N/A'); ?></div>
                <div class="brand"><?php echo h($core->site_name); ?></div>
            </div>
            <div class="s-bar">
                <img src="<?php echo h($small_barcode); ?>" alt="Tracking barcode">
                <div class="num"><span class="bk"><?php echo h($small_num_label); ?></span> <?php echo h($small_num); ?></div>
            </div>
            <div class="s-foot">
                <?php if ($small_is_courier) : ?>
                    <div><span class="lbl">Sys</span> <?php echo h($sys_tracking); ?></div>
                <?php else : ?>
                    <div><span class="lbl">Items</span> <?php echo h($item_count); ?></div>
                <?php endif; ?>
                <?php if ($weight !== '') : ?>
                    <div><span class="lbl">Wt</span> <?php echo h($weight); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else : ?>
    <div class="label">
        <div class="panel">
            <!-- Top: logo + company contact block -->
            <div class="top">
                <div class="logo">
                    <?php if (!empty($core->logo)) : ?>
                        <img src="assets/<?php echo h($core->logo); ?>" alt="<?php echo h($core->site_name); ?>">
                    <?php else : ?>
                        <span class="fallback"><?php echo h($core->site_name); ?></span>
                    <?php endif; ?>
                </div>
                <div class="contact">
                    <strong><?php echo h($core->site_name); ?></strong><br>
                    Ghana, 4 Nii Attram Mensah CL <br>GS-0211-5741<br>
                    <?php echo h($lang['print-text8'] ?? 'Tel'); ?>: +233 538 346 496
                </div>
            </div>

            <!-- Title band: system / package tracking -->
            <div class="title">
                <div class="k">Package Tracking</div>
                <div class="v"><?php echo h($sys_tracking); ?></div>
            </div>

            <!-- Shipment facts (no monetary values) -->
            <div class="facts">
                <?php if ($is_dangerous) : ?>
                    <div class="f"><div class="k">Hazmat</div><div class="v"><i class="fas fa-exclamation-triangle fa-lg"></i></div></div>
                <?php endif; ?>
                <div class="f"><div class="k">Courier</div><div class="v"><?php echo h($courier_name !== '' ? $courier_name : 'N/A'); ?></div></div>
                <div class="f"><div class="k">Items</div><div class="v"><?php echo h($item_count); ?></div></div>
                <div class="f"><div class="k">Weight</div><div class="v"><?php echo h($weight); ?></div></div>
            </div>

            <!-- Sender / Recipient -->
            <div class="addr-block">
                <div class="addr from">
                    <div class="role">Sender</div>
                    <div class="name"><?php echo h($sender_name) . ($sender_locker !== '' ? ' (' . h($sender_locker) . ')' : ''); ?></div>
                    <div class="line"><?php echo h($sender_line); ?></div>
                </div>
                <div class="addr to">
                    <div class="role">Recipient</div>
                    <div class="name"><?php echo h($recip_name); ?></div>
                    <div class="line"><?php echo h($recip_line); ?></div>
                    <?php if ($recip_phone !== '') : ?>
                        <div class="line"><?php echo h($recip_phone); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom: system + courier barcodes, stacked, filling the space -->
            <div class="bar">
                <?php if ($courier_barcode_url) : ?>
                    <div class="track-row">
                        <div class="track">
                            <div class="k">System Tracking</div>
                            <img src="<?php echo h($sys_barcode_url); ?>" alt="System barcode">
                        </div>
                        <div class="divider"></div>
                        <div class="track">
                            <div class="k">Courier Tracking</div>
                            <img src="<?php echo h($courier_barcode_url); ?>" alt="Courier barcode">
                        </div>
                    </div>
                <?php else : ?>
                    <div class="track">
                        <div class="k">System Tracking</div>
                        <img src="<?php echo h($sys_barcode_url); ?>" alt="System barcode">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
