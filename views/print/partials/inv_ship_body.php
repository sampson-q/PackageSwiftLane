<?php
/**
 * Renders ONE receipt (.label-page) from a normalized $INV array — the exact
 * print_inv_ship.php design. Monetary totals are intentionally hidden (packing
 * slip style) UNLESS $INV['show_total'] is set (used by the charge/billing doc).
 *
 * $INV keys:
 *   sys_tracking     order/consolidation tracking (order_prefix.order_no)   [req]
 *   barcode_data     value for the top barcode (defaults to sys_tracking)
 *   phones           brand phone line       (defaults to reference literal)
 *   address          brand address line     (defaults to reference literal)
 *   sender_name / sender_address / sender_location / sender_phone
 *   carrier_tracking carrier/postal tracking number, or '' (adds 2nd barcode)
 *   courier_name     courier company
 *   category_name    item category
 *   financial_serial null | ['sn' => int]
 *   items            [ ['qty'=>int,'desc'=>str], ... ]
 *   total_weight     original package weight (string/number)
 *   total_qty        total item count (int)
 *   fs_payment       object|null  (PAID stamp)
 *   fs_paid_mode     string method label for the stamp
 *   show_total       bool  — show the total box (charge doc)
 *   total_label      string
 *   total_value      string (already money-formatted)
 *
 * @var array $INV
 * @var Core  $core  (global)
 * @var array $lang  (global)
 */
$sys_tracking     = (string) ($INV['sys_tracking'] ?? '');
$barcode_data     = (string) ($INV['barcode_data'] ?? $sys_tracking);
$brand_phones     = (string) ($INV['phones'] ?? cdp_printBrandPhones());
$brand_address    = (string) ($INV['address'] ?? cdp_printBrandAddress());
$sender_name      = (string) ($INV['sender_name'] ?? '');
$sender_address   = (string) ($INV['sender_address'] ?? 'N/A');
$sender_location  = (string) ($INV['sender_location'] ?? 'N/A');
$sender_phone     = (string) ($INV['sender_phone'] ?? '');
$carrier_tracking = trim((string) ($INV['carrier_tracking'] ?? ''));
$courier_name     = (string) ($INV['courier_name'] ?? 'N/A');
$category_name    = (string) ($INV['category_name'] ?? 'N/A');
$financial_serial = $INV['financial_serial'] ?? null;
$items            = is_array($INV['items'] ?? null) ? $INV['items'] : [];
$total_weight     = (string) ($INV['total_weight'] ?? '—');
$total_qty        = (int) ($INV['total_qty'] ?? 0);
$fs_payment       = $INV['fs_payment'] ?? null;
$fs_paid_mode     = (string) ($INV['fs_paid_mode'] ?? '');
$show_total       = !empty($INV['show_total']);
?>
    <div class="label-page">
        <div class="topbar">
            <div class="brand-wrap">
                <div class="logo">
                    <?php echo ($core->logo) ? '<img src="assets/uploads/SWIFT LOGO PNG-04.png" alt="' . h($core->site_name) . '"/>' : '<h3>' . h($core->site_name) . '</h3>'; ?>
                </div>
                <div class="brand-text" style="color:#000 !important;">
                    <p class="brand-name"><?php echo h($core->site_name); ?></p>
                    <p class="brand-lines">
                        <strong><?php echo $lang['inv-shipping2']; ?>:</strong> <?php echo h($brand_phones); ?><br>
                        <strong><?php echo $lang['inv-shipping3']; ?>:</strong> <?php echo h($core->site_email); ?><br>
                        <strong>Address:</strong> <?php echo h($brand_address); ?>
                    </p>
                </div>
            </div>

            <div class="barcode">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($barcode_data); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
            </div>
        </div>

        <div class="info-grid">
            <div class="panel">
                <div class="panel-title"><?php echo h($lang['inv-shipping5']); ?></div>

                <div class="kv">
                    <div class="k">Name:</div>
                    <div class="v"><strong><?php echo h($sender_name); ?></strong></div>
                </div>
                <div class="kv">
                    <div class="k">Address:</div>
                    <div class="v"><?php echo h($sender_address); ?></div>
                </div>
                <div class="kv">
                    <div class="k">Location:</div>
                    <div class="v"><?php echo h($sender_location); ?></div>
                </div>
                <div class="kv">
                    <div class="k">Phone:</div>
                    <div class="v"><?php echo h($sender_phone); ?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">Shipment Details</div>

            <div class="kv">
                <div class="k">Tracking #:</div>
                <div class="v">&nbsp;&nbsp;&nbsp;&nbsp;<strong><?php echo h($carrier_tracking !== '' ? $carrier_tracking : 'N/A'); ?></strong></div>
            </div>
            <div class="kv">
                <div class="k">Courier:</div>
                <div class="v"><?php echo h($courier_name); ?></div>
            </div>
            <div class="kv">
                <div class="k">Category:</div>
                <div class="v"><?php echo h($category_name); ?></div>
            </div>

            <?php if ($financial_serial) : ?>
                <div style="width:100%; text-align:right;">
                    <span class="financial-sn"><?php echo (int) $financial_serial['sn']; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($carrier_tracking !== '') : ?>
                <div class="barcode">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($carrier_tracking); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=92&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0&modulewidth=50" alt="">
                </div>
            <?php endif; ?>
        </div>

        <div class="items-panel">
            <div class="items-header">
                <h3><?php echo h('Items Details'); ?></h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 14mm;"><?php echo $lang['left214']; ?></th>
                        <th><?php echo $lang['left213']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it) : ?>
                        <tr>
                            <td class="qty"><?php echo (int) ($it['qty'] ?? 0); ?></td>
                            <td class="desc"><?php echo h($it['desc'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">
                            <strong>Original Package Weight</strong> :
                            <?php echo h($total_weight) . '<br><b>Total Items:</b> ' . $total_qty; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <?php if ($show_total) : ?>
                <div class="total-box">
                    <label><?php echo h($INV['total_label'] ?? 'Total'); ?></label>
                    <div class="value"><?php echo h($INV['total_value'] ?? ''); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($fs_payment) : ?>
                <div class="paid-stamp">
                    <div class="paid-mark">PAID</div>
                    <div class="paid-detail">
                        <?php echo h($fs_paid_mode); ?><br>
                        <?php if (!empty($fs_payment->reference)) : ?>
                            Ref: <b><?php echo h((string) $fs_payment->reference); ?></b><br>
                        <?php endif; ?>
                        <?php echo h(date('d M Y', strtotime((string) $fs_payment->recorded_at))); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="signature">
                <div class="line">Signature / Stamp</div>
            </div>

            <div class="credits text-center">
                Designed by <b>iSolveAfrica</b><br>
                +233 (0) 591 447 845 / +233 (0) 50 550 5009<br>
                Email: hello@isolveafrica.com<br>
                https://www.isolveafrica.com
            </div>
        </div>
    </div>
