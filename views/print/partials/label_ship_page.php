<?php
/**
 * Full single-label HTML page: <head> + one label body + the size toolbar.
 * Used by every single (non-multiple) box-label view.
 *
 * @var array  $L           normalized label model
 * @var string $label_size  'normal' | 'small'
 * @var string $page_title  browser/tab title
 */
$label_size = (isset($label_size) && $label_size === 'small') ? 'small' : 'normal';
$page_title = $page_title ?? ('Package Label - ' . ($L['sys_tracking'] ?? ''));
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo h($core->favicon); ?>">
    <title><?php echo h($page_title); ?></title>
    <link rel="stylesheet" href="assets/vendor/fonts/fontawesome.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/tabler-icons.css" />
    <link rel="stylesheet" href="assets/vendor/fonts/flag-icons.css" />
    <?php include 'views/print/partials/label_ship_styles.php'; ?>
</head>
<body class="size-<?php echo $label_size; ?>">
    <?php include 'views/print/partials/label_ship_body.php'; ?>
    <?php include 'views/print/partials/label_ship_toolbar.php'; ?>
</body>
</html>
