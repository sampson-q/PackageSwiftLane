<?php
/**
 * Full single-receipt HTML page: <head> + one invoice body + print button.
 * Used by every single (non-multiple) invoice/receipt view.
 *
 * @var array  $INV        normalized invoice model
 * @var string $page_title browser/tab title
 */
$page_title = $page_title ?? (($lang['inv-shipping19'] ?? 'Invoice') . ' - ' . ($INV['sys_tracking'] ?? ''));
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon; ?>">
    <title><?php echo h($page_title); ?></title>
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link type="text/css" href="assets/custom_dependencies/print.css" rel="stylesheet" />
    <?php include 'views/print/partials/inv_ship_styles.php'; ?>
</head>
<body>
    <?php include 'views/print/partials/inv_ship_body.php'; ?>

    <div class="print-button">
        <button class="btn btn-primary" onclick="window.print();">
            <i class="fa fa-print"></i> <?php echo $lang['inv-shipping19']; ?>
        </button>
        <div class="print-info">Press Ctrl+P or click above to print</div>
    </div>
</body>
</html>
