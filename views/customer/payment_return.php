<?php
// ============================================================================
// Payment outcome — shown when the customer returns from the MoMo checkout.
// $state / $message are set by payment_return.php from a server-side verify.
// ============================================================================
$userData = $user->cdp_getUserData();

$map = [
    'success' => ['icon' => 'solar:check-circle-bold', 'color' => '#0aa699', 'title' => 'Payment Confirmed',
                  'body' => 'Thank you. Your packages are now cleared for delivery.'],
    'pending' => ['icon' => 'solar:clock-circle-bold', 'color' => '#ffbc34', 'title' => 'Payment Pending',
                  'body' => 'We have not had confirmation yet. If you completed the prompt on your phone, this will clear shortly on its own — you do not need to pay again.'],
    'failed'  => ['icon' => 'solar:close-circle-bold', 'color' => '#f62d51', 'title' => 'Payment Not Confirmed',
                  'body' => 'No money has been taken for this attempt. You can try again from My Bills.'],
];
$v = $map[$state] ?? $map['failed'];
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $v['title']; ?> | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
</head>

<body>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="container-fluid">
                <div class="row justify-content-center" style="margin-top:4rem">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center p-5">
                                <iconify-icon icon="<?php echo $v['icon']; ?>"
                                              style="font-size:64px;color:<?php echo $v['color']; ?>"></iconify-icon>
                                <h3 class="mt-3"><?php echo $v['title']; ?></h3>
                                <p class="text-muted"><?php echo $v['body']; ?></p>
                                <?php if (!empty($message) && $state !== 'success') { ?>
                                    <p class="text-muted" style="font-size:.85rem">
                                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php } ?>
                                <?php if (!empty($reference)) { ?>
                                    <p class="text-muted" style="font-size:.8rem">
                                        Reference: <b><?php echo htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'); ?></b>
                                    </p>
                                <?php } ?>
                                <a href="my_bills.php" class="btn btn-primary mt-2">Back To My Bills</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>
</body>

</html>
