<?php
/**
 * Screen-only label toolbar: the Normal / Small size switch + print button,
 * plus the auto-print bootstrap. Hidden by @media print (see styles partial).
 *
 * Each switch link reloads the SAME url with size=normal|small, so the @page
 * size in the styles partial is emitted correctly for the chosen physical size.
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
        <a class="<?php echo $label_size === 'normal' ? 'active' : ''; ?>" href="<?php echo h($href_normal); ?>">Normal (4&times;6&Prime;)</a>
        <a class="<?php echo $label_size === 'small'  ? 'active' : ''; ?>" href="<?php echo h($href_small); ?>">Small (2&times;1&Prime;)</a>
    </span>
    <button class="btn btn-primary" onclick="window.print();">&#128424; Print Label</button>
</div>
<script>window.onload = function () { setTimeout(function () { window.print(); }, 300); };</script>
