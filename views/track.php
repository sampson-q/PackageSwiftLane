<?php
require_once('helpers/querys.php');

if (isset($_GET['order_track'])) {
    $results = cdp_getCourierTrack($_GET['order_track']);
    $track   = $results['data'];
} else {
    cdp_redirect_to("tracking.php");
}

$db->cdp_query("
    SELECT a.id, a.order_track, a.t_dest, a.t_date, a.t_city, a.comments, a.status_courier, b.mod_style, b.color
    FROM cdb_courier_track as a
    INNER JOIN cdb_styles as b ON a.status_courier = b.id
    WHERE a.order_track='" . $_GET['order_track'] . "' ORDER BY a.t_date DESC");
$courier_track = $db->cdp_registros();

if ($track != null) {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $track->sender_id . "'");
    $sender_data = $db->cdp_registro();

    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $track->receiver_id . "'");
    $receiver_data = $db->cdp_registro();

    $db->cdp_query("SELECT * FROM cdb_delivery_time where id= '" . $track->order_deli_time . "'");
    $delivery_time = $db->cdp_registro();

    $db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $track->order_prefix . $track->order_no . "'");
    $address_order = $db->cdp_registro();

    $db->cdp_query("SELECT * FROM cdb_order_files where order_id='" . $track->order_id . "' ORDER BY date_file");
    $files_order   = $db->cdp_registros();
    $files_count   = $db->cdp_rowCount();

    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id='" . $track->order_id . "'");
    $order_items = $db->cdp_registros();

    $sumador_libras = 0;
    $sumador_volumetric = 0;
    $count = 0;
    $item_description = '';
    foreach ($order_items as $row_item) {
        $weight_item     = $row_item->order_item_weight;
        $item_description = $row_item->order_item_description;
        $total_metric    = ($track->volumetric_percentage > 0)
            ? $row_item->order_item_length * $row_item->order_item_width * $row_item->order_item_height / $track->volumetric_percentage
            : 0;
        if ($weight_item > $total_metric) {
            $sumador_libras    += $weight_item;
        } else {
            $sumador_volumetric += $total_metric;
        }
        $count++;
    }
    $total_weight = $sumador_libras > $sumador_volumetric
        ? cdp_round_out($sumador_libras) : cdp_round_out($sumador_volumetric);

    // Status color mapping
    $status_color_map = [
        '#28a745' => '#28a745', '#34e89e' => '#17b26a', '#17b26a' => '#17b26a',
        '#ffc107' => '#e6a817', '#fd7e14' => '#e07012', '#dc3545' => '#dc3545',
    ];
    $raw_color    = isset($track->color) ? $track->color : '#2a5298';
    $status_color = $status_color_map[$raw_color] ?? $raw_color;
    $status_name  = $track->mod_style;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang['left127'] ?> <?php echo isset($_GET['order_track']) ? '— ' . htmlspecialchars($_GET['order_track'], ENT_QUOTES, 'UTF-8') : '' ?> | <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/<?php echo $core->favicon ?>">
    <link href="assets/custom_dependencies/bootstrap.min.css" rel="stylesheet">
    <link href="assets/custom_dependencies/css/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #1a3a6b;
            --brand2: #2a5298;
            --brand-grad: linear-gradient(135deg, #1a3a6b 0%, #2a5298 100%);
            --status-color: <?php echo $track ? $status_color : '#2a5298' ?>;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; font-size: 14px; background: #f0f4ff; }

        /* ── NAV ─────────────────────────── */
        .pub-nav {
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            position: sticky; top: 0; z-index: 1030;
        }
        .pub-nav .inner {
            max-width: 1200px; margin: 0 auto;
            height: 64px; padding: 0 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link-item {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            font-size: .855rem; font-weight: 600;
            color: #374151; text-decoration: none;
            transition: background .15s, color .15s;
        }
        .nav-link-item:hover { background: #f0f4ff; color: var(--brand); }
        .nav-link-item.active { color: var(--brand2); }
        .btn-nav-login {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 8px;
            background: var(--brand-grad);
            color: #fff; font-size: .855rem; font-weight: 600;
            text-decoration: none; transition: opacity .15s; border: none;
        }
        .btn-nav-login:hover { opacity: .88; color: #fff; }

        /* ── STATUS HERO ─────────────────── */
        .status-hero {
            background: var(--brand-grad);
            padding: 36px 24px 48px;
            position: relative;
            overflow: hidden;
        }
        .status-hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url('assets/css_main_deprixa/images/user/bg.jpg') center/cover no-repeat;
            opacity: .06;
        }
        .status-hero .inner {
            max-width: 1200px; margin: 0 auto;
            position: relative; z-index: 2;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 16px;
        }
        .status-hero .track-id {
            color: rgba(255,255,255,.7);
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 6px;
        }
        .status-hero h2 {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,.15);
            border: 2px solid rgba(255,255,255,.35);
            color: #fff;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: .9rem;
            font-weight: 700;
            backdrop-filter: blur(4px);
        }
        .status-badge .dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--status-color);
            box-shadow: 0 0 0 3px rgba(255,255,255,.35);
            flex-shrink: 0;
        }
        .status-hero-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-hero-print {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.18);
            border: 2px solid rgba(255,255,255,.4);
            color: #fff; font-weight: 600; font-size: .855rem;
            padding: 8px 18px; border-radius: 8px;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-hero-print:hover { background: rgba(255,255,255,.3); color: #fff; }
        .btn-hero-back {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.95);
            border: none;
            color: var(--brand); font-weight: 600; font-size: .855rem;
            padding: 8px 18px; border-radius: 8px;
            text-decoration: none;
            transition: opacity .15s;
        }
        .btn-hero-back:hover { opacity: .88; color: var(--brand); }

        /* ── PROGRESS STEPPER ────────────── */
        .progress-strip {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 0 24px;
        }
        .progress-strip .inner {
            max-width: 1200px; margin: 0 auto;
            padding: 20px 0;
        }
        .stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 18px; left: 20px; right: 20px;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
        }
        .step {
            display: flex; flex-direction: column; align-items: center;
            flex: 1;
            text-align: center;
            position: relative; z-index: 1;
        }
        .step-icon {
            width: 38px; height: 38px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            border: 3px solid #e9ecef;
            background: #fff;
            margin-bottom: 6px;
            color: #9ca3af;
            transition: all .3s;
        }
        .step.done .step-icon {
            background: var(--brand-grad);
            border-color: var(--brand2);
            color: #fff;
        }
        .step.active .step-icon {
            background: var(--status-color);
            border-color: var(--status-color);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(42,82,152,.15);
        }
        .step-label {
            font-size: .72rem;
            font-weight: 600;
            color: #9ca3af;
            max-width: 80px;
        }
        .step.done .step-label, .step.active .step-label { color: var(--brand); }

        /* ── MAIN CONTENT ────────────────── */
        .main-content {
            max-width: 1200px;
            margin: -28px auto 40px;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            align-items: start;
        }

        /* ── CARDS ───────────────────────── */
        .info-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(26,58,107,.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header-band {
            background: var(--brand-grad);
            padding: 14px 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header-band h5 {
            color: #fff; margin: 0;
            font-size: .95rem; font-weight: 700;
        }
        .card-header-band i { color: rgba(255,255,255,.85); font-size: 18px; }
        .card-body-inner { padding: 20px; }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .info-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
            border-right: 1px solid #f3f4f6;
        }
        .info-item:nth-child(2n) { border-right: none; }
        .info-item:nth-last-child(-n+2) { border-bottom: none; }
        .info-item .label {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #9ca3af;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .info-item .label i { font-size: 13px; }
        .info-item .value {
            font-size: .9rem;
            font-weight: 600;
            color: #111827;
        }
        .info-item.full {
            grid-column: 1 / -1;
            border-right: none;
        }

        /* Route strip */
        .route-strip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: #f8faff;
            border-bottom: 1px solid #e9ecef;
        }
        .route-point {
            flex: 1;
            text-align: center;
        }
        .route-point .rp-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #9ca3af;
            margin-bottom: 2px;
        }
        .route-point .rp-city {
            font-size: .95rem;
            font-weight: 700;
            color: var(--brand);
        }
        .route-point .rp-country {
            font-size: .78rem;
            color: #6b7280;
        }
        .route-arrow {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--brand2);
            flex-shrink: 0;
        }
        .route-arrow i { font-size: 22px; }
        .route-arrow span { font-size: .65rem; color: #9ca3af; font-weight: 600; }

        /* ── TIMELINE ────────────────────── */
        .timeline-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(26,58,107,.08);
            overflow: hidden;
            position: sticky;
            top: 84px;
        }
        .timeline-header {
            background: var(--brand-grad);
            padding: 14px 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .timeline-header h5 { color: #fff; margin: 0; font-size: .95rem; font-weight: 700; }
        .timeline-header i { color: rgba(255,255,255,.85); font-size: 18px; }

        .timeline-body { padding: 20px; max-height: 600px; overflow-y: auto; }
        .timeline-empty {
            text-align: center;
            padding: 32px 20px;
            color: #9ca3af;
        }
        .timeline-empty i { font-size: 36px; display: block; margin-bottom: 8px; }

        .tl-event {
            display: flex;
            gap: 12px;
            position: relative;
            padding-bottom: 20px;
        }
        .tl-event:last-child { padding-bottom: 0; }
        .tl-event:last-child .tl-line { display: none; }

        .tl-dot-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
            width: 36px;
        }
        .tl-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            color: #fff;
            flex-shrink: 0;
        }
        .tl-line {
            flex: 1;
            width: 2px;
            background: #e9ecef;
            margin-top: 4px;
            min-height: 20px;
        }
        .tl-content {
            flex: 1;
            padding-top: 4px;
        }
        .tl-status {
            font-weight: 700;
            font-size: .88rem;
            color: #111827;
            margin-bottom: 2px;
        }
        .tl-location {
            font-size: .78rem;
            color: #6b7280;
            margin-bottom: 3px;
        }
        .tl-comment {
            font-size: .78rem;
            color: #374151;
            background: #f8faff;
            border-left: 3px solid var(--brand2);
            padding: 4px 8px;
            border-radius: 0 4px 4px 0;
            margin-bottom: 4px;
        }
        .tl-date {
            font-size: .72rem;
            color: #9ca3af;
            font-weight: 600;
        }

        /* ── FILES TABLE ─────────────────── */
        .files-table { font-size: .86rem; }
        .files-table a { color: var(--brand2); font-weight: 600; }

        /* ── NOT FOUND ───────────────────── */
        .not-found-wrap {
            max-width: 600px;
            margin: 60px auto;
            text-align: center;
            padding: 0 24px;
        }
        .not-found-wrap img { max-width: 200px; margin-bottom: 20px; }
        .not-found-wrap h2 { font-size: 1.3rem; font-weight: 700; color: var(--brand); }

        /* ── RESPONSIVE ──────────────────── */
        @media (max-width: 991px) {
            .main-content { grid-template-columns: 1fr; margin-top: 20px; }
            .timeline-card { position: static; }
            .stepper { display: none; }
        }
        @media (max-width: 575px) {
            .info-grid { grid-template-columns: 1fr; }
            .info-item { border-right: none; }
            .info-item:nth-last-child(-n+2) { border-bottom: 1px solid #f3f4f6; }
            .info-item:last-child { border-bottom: none; }
            .status-hero .inner { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="pub-nav">
    <div class="inner">
        <a class="nav-logo" href="index.php">
            <?php if ($core->logo_web): ?>
                <img src="assets/<?php echo htmlspecialchars((string)$core->logo_web, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?>"
                     style="height:36px;width:auto">
            <?php else: ?>
                <span style="font-weight:800;font-size:1.1rem;color:var(--brand)"><?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <div class="nav-links">
            <a href="tracking.php" class="nav-link-item active">
                <i class="bi bi-geo-alt"></i>
                <?php echo isset($lang['langs_06']) ? $lang['langs_06'] : 'Track & Trace'; ?>
            </a>
            <a href="cotizar.php" class="nav-link-item">
                <i class="bi bi-calculator"></i>
                <?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Get a Quote'; ?>
            </a>
            <a href="login.php" class="btn-nav-login">
                <i class="bi bi-box-arrow-in-right"></i>
                <?php echo isset($lang['left121']) ? $lang['left121'] : 'Login'; ?>
            </a>
        </div>
    </div>
</nav>

<?php if (!$track): ?>
<!-- ── NOT FOUND ─────────────────────────────────────────── -->
<div class="not-found-wrap">
    <img src="assets/images/alert/ohh_shipment_rate.png" class="img-fluid" alt="">
    <h2>Oh! Not found</h2>
    <p class="text-muted mb-4">
        <?php echo $lang['track-shipment1'] ?> <strong style="color:#dc3545"><?php echo htmlspecialchars($_GET['order_track'], ENT_QUOTES, 'UTF-8') ?></strong>
    </p>
    <p class="text-muted"><?php echo $lang['track-shipment2'] ?></p>
    <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="tracking.php" class="btn btn-primary"><?php echo $lang['left182'] ?></a>
        <a href="login.php" class="btn btn-outline-secondary"><?php echo $lang['left183'] ?></a>
    </div>
</div>

<?php else: ?>

<!-- ── STATUS HERO ─────────────────────────────────────────── -->
<section class="status-hero">
    <div class="inner">
        <div>
            <div class="track-id"><?php echo $lang['track-shipment4'] ?></div>
            <h2><?php echo htmlspecialchars($track->order_prefix . $track->order_no, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="status-badge">
                <span class="dot"></span>
                <i class="bi bi-box-seam"></i>
                <?php echo htmlspecialchars((string)$status_name, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <div class="status-hero-actions">
            <a href="tracking.php" class="btn-hero-back">
                <i class="bi bi-search"></i>
                <?php echo $lang['left182'] ?>
            </a>
            <a href="print_inv_ship_track.php?id=<?php echo (int)$track->order_id ?>" target="_blank" class="btn-hero-print">
                <i class="bi bi-printer"></i>
                <?php echo $lang['toolprint'] ?>
            </a>
        </div>
    </div>
</section>

<!-- ── PROGRESS STEPPER ─────────────────────────────────────── -->
<div class="progress-strip">
    <div class="inner">
        <?php
        // Determine approximate progress step from status name
        $sn  = mb_strtolower((string)$status_name);
        $step = 1;
        if (strpos($sn,'creat') !== false || strpos($sn,'pend') !== false) $step = 1;
        elseif (strpos($sn,'warehouse') !== false || strpos($sn,'bodega') !== false || strpos($sn,'recib') !== false) $step = 2;
        elseif (strpos($sn,'transit') !== false || strpos($sn,'camino') !== false || strpos($sn,'pick') !== false || strpos($sn,'dispatch') !== false) $step = 3;
        elseif (strpos($sn,'entrega') !== false || strpos($sn,'deliver') !== false || strpos($sn,'complet') !== false) $step = 4;
        $steps = [
            ['icon' => 'bi-box', 'label' => isset($lang['track_step1']) ? $lang['track_step1'] : 'Order Created'],
            ['icon' => 'bi-building', 'label' => isset($lang['track_step2']) ? $lang['track_step2'] : 'In Warehouse'],
            ['icon' => 'bi-truck', 'label' => isset($lang['track_step3']) ? $lang['track_step3'] : 'In Transit'],
            ['icon' => 'bi-house-check', 'label' => isset($lang['track_step4']) ? $lang['track_step4'] : 'Delivered'],
        ];
        ?>
        <div class="stepper">
            <?php foreach ($steps as $i => $s): ?>
                <?php
                $n = $i + 1;
                $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
                ?>
                <div class="step <?php echo $cls ?>">
                    <div class="step-icon"><i class="bi <?php echo $s['icon'] ?>"></i></div>
                    <div class="step-label"><?php echo htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── MAIN CONTENT ─────────────────────────────────────────── -->
<div class="main-content">

    <div class="left-col">

        <!-- Route -->
        <div class="info-card">
            <div class="route-strip">
                <div class="route-point">
                    <div class="rp-label"><i class="bi bi-geo-alt"></i> <?php echo $lang['track-shipment6'] ?></div>
                    <div class="rp-country"><?php echo htmlspecialchars((string)($address_order->sender_country ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="rp-city"><?php echo htmlspecialchars((string)($address_order->sender_city ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="route-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                    <span><?php echo isset($delivery_time->delitime) ? htmlspecialchars($delivery_time->delitime, ENT_QUOTES, 'UTF-8') : '' ?></span>
                </div>
                <div class="route-point">
                    <div class="rp-label"><i class="bi bi-geo-fill"></i> <?php echo $lang['track-shipment16'] ?></div>
                    <div class="rp-country"><?php echo htmlspecialchars((string)($address_order->recipient_country ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="rp-city"><?php echo htmlspecialchars((string)($address_order->recipient_city ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label"><i class="bi bi-calendar3"></i><?php echo $lang['track-shipment8'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)$track->order_date, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-clock"></i><?php echo $lang['track-shipment9'] ?></div>
                    <div class="value"><?php echo isset($delivery_time->delitime) ? htmlspecialchars($delivery_time->delitime, ENT_QUOTES, 'UTF-8') : '—' ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-boxes"></i><?php echo $lang['track-shipment11'] ?></div>
                    <div class="value"><?php echo (int)$count ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-weight"></i><?php echo $lang['track-shipment13'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)$total_weight, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if ($item_description): ?>
                <div class="info-item full">
                    <div class="label"><i class="bi bi-chat-left-text"></i><?php echo $lang['message_title_track4'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)$item_description, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sender -->
        <div class="info-card">
            <div class="card-header-band">
                <i class="bi bi-person-fill"></i>
                <h5><?php echo $lang['track-shipment5'] ?></h5>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label"><i class="bi bi-person"></i><?php echo $lang['track-shipment20'] ?></div>
                    <div class="value"><?php echo htmlspecialchars(trim(($sender_data->fname ?? '') . ' ' . ($sender_data->lname ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-globe"></i><?php echo $lang['track-shipment6'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->sender_country ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-geo-alt"></i><?php echo $lang['track-shipment7'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->sender_city ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-signpost-2"></i><?php echo $lang['track-shipment10'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->sender_address ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>

        <!-- Recipient -->
        <div class="info-card">
            <div class="card-header-band">
                <i class="bi bi-person-check-fill"></i>
                <h5><?php echo $lang['track-shipment15'] ?></h5>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label"><i class="bi bi-person"></i><?php echo $lang['track-shipment20'] ?></div>
                    <div class="value"><?php echo htmlspecialchars(trim(($receiver_data->fname ?? '') . ' ' . ($receiver_data->lname ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-globe"></i><?php echo $lang['track-shipment16'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->recipient_country ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-geo-alt"></i><?php echo $lang['track-shipment17'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->recipient_city ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="info-item">
                    <div class="label"><i class="bi bi-signpost-2"></i><?php echo $lang['track-shipment10'] ?></div>
                    <div class="value"><?php echo htmlspecialchars((string)($address_order->recipient_address ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>

        <!-- Files -->
        <?php if (isset($files_count) && $files_count > 0): ?>
        <div class="info-card">
            <div class="card-header-band">
                <i class="bi bi-paperclip"></i>
                <h5><?php echo $lang['message_title_track1'] ?></h5>
            </div>
            <div class="card-body-inner">
                <?php if (!empty($track->photo_delivered)): ?>
                    <img src="<?php echo htmlspecialchars((string)$track->photo_delivered, ENT_QUOTES, 'UTF-8') ?>"
                         style="max-width:100%;border-radius:8px;margin-bottom:12px">
                <?php endif; ?>
                <table class="table table-sm files-table mb-0">
                    <thead><tr>
                        <th>#</th>
                        <th><?php echo $lang['message_title_track2'] ?></th>
                        <th><?php echo $lang['message_title_track3'] ?></th>
                    </tr></thead>
                    <tbody>
                    <?php $fc = 0; foreach ($files_order as $file): $fc++; ?>
                        <tr>
                            <td><?php echo $fc ?></td>
                            <td><a href="<?php echo htmlspecialchars((string)$file->url, ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i><?php echo htmlspecialchars((string)$file->name, ENT_QUOTES, 'UTF-8') ?>
                            </a></td>
                            <td><?php echo date("Y-m-d h:i A", strtotime($file->date_file)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <!-- /.left-col -->

    <!-- ── TIMELINE ─────────────────────── -->
    <div class="timeline-card">
        <div class="timeline-header">
            <i class="bi bi-clock-history"></i>
            <h5><?php echo $lang['track-shipment22'] ?></h5>
        </div>
        <div class="timeline-body">
            <?php if (!$courier_track): ?>
                <div class="timeline-empty">
                    <i class="bi bi-hourglass"></i>
                    <p>No tracking events yet.</p>
                </div>
            <?php else: ?>
                <?php
                // Color palette for dots
                $dot_colors = ['#1a3a6b','#2a5298','#17b26a','#e6a817','#e07012','#dc3545'];
                $di = 0;
                foreach ($courier_track as $rows):
                    $dot_bg = isset($rows->color) ? $rows->color : $dot_colors[$di % count($dot_colors)];
                    $di++;
                ?>
                <div class="tl-event">
                    <div class="tl-dot-wrap">
                        <div class="tl-dot" style="background:<?php echo htmlspecialchars((string)$dot_bg, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-circle-fill" style="font-size:8px"></i>
                        </div>
                        <div class="tl-line"></div>
                    </div>
                    <div class="tl-content">
                        <div class="tl-status"><?php echo htmlspecialchars((string)$rows->mod_style, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($rows->t_dest || $rows->t_city): ?>
                            <div class="tl-location">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?php
                                $parts = array_filter([(string)($rows->t_dest ?? ''), (string)($rows->t_city ?? '')]);
                                echo htmlspecialchars(implode(', ', $parts), ENT_QUOTES, 'UTF-8');
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rows->comments)): ?>
                            <div class="tl-comment">
                                <i class="bi bi-chat-left-text me-1"></i><?php echo htmlspecialchars((string)$rows->comments, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <div class="tl-date">
                            <i class="bi bi-calendar3 me-1"></i><?php echo date('Y/m/d', strtotime($rows->t_date)) ?>
                            <span class="ms-2"><i class="bi bi-clock me-1"></i><?php echo date('h:i a', strtotime($rows->t_date)) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
<!-- /.main-content -->

<?php endif; // $track ?>

<!-- FOOTER -->
<footer style="text-align:center;padding:20px 24px;font-size:.8rem;color:#9ca3af;border-top:1px solid #f3f4f6">
    &copy; <?php echo date('Y') ?> <?php echo htmlspecialchars((string)$core->site_name, ENT_QUOTES, 'UTF-8') ?> &nbsp;&middot;&nbsp;
    <a href="tracking.php" style="color:#6b7280;text-decoration:none;font-weight:600"><?php echo isset($lang['langs_06']) ? $lang['langs_06'] : 'Track & Trace'; ?></a>
    &nbsp;&middot;&nbsp;
    <a href="cotizar.php" style="color:#6b7280;text-decoration:none;font-weight:600"><?php echo isset($lang['cotizador_nav']) ? $lang['cotizador_nav'] : 'Get a Quote'; ?></a>
</footer>

<script src="assets/custom_dependencies/jquery-3.6.0.min.js"></script>
<script src="assets/custom_dependencies/js/bootstrap.min.js"></script>
</body>
</html>
