<?php
/**
 * Screen-only label toolbar: the Normal / Small size switch + print button.
 * Fixed to the top of the page and hidden by @media print (see styles partial).
 *
 * Printing is deliberately on demand (the Print button) — no auto-print — so a
 * user who cancels the browser dialog does not have to navigate back and
 * reselect the shipment just to try again.
 *
 * Each switch link reloads the SAME url with size=normal|small so the @page
 * size in the styles partial is emitted correctly for the chosen size.
 *
 * @var string $label_size 'normal' | 'small'
 */
$label_size = (isset($label_size) && $label_size === 'small') ? 'small' : 'normal';

$self = strtok((string) $_SERVER['REQUEST_URI'], '?');
$params = $_GET;
$href_normal = $self . '?' . http_build_query(array_merge($params, ['size' => 'normal']));
$href_small  = $self . '?' . http_build_query(array_merge($params, ['size' => 'small']));
?>
<div class="print-toolbar">
    <span class="size-switch">
        <a class="<?php echo $label_size === 'normal' ? 'active' : ''; ?>" href="<?php echo h($href_normal); ?>">Normal &middot; 4&times;6&Prime;</a>
        <a class="<?php echo $label_size === 'small'  ? 'active' : ''; ?>" href="<?php echo h($href_small); ?>">Small &middot; 2&times;1&Prime;</a>
    </span>
    <button type="button" onclick="window.print();">&#128424;&#65039; Print Label</button>
</div>
