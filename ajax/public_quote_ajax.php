<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// Public endpoint - NO authentication required
// Returns a shipping price estimate based on route + weight
// *************************************************************************

ob_start();

require_once('../loader.php');
require_once(__DIR__ . '/../helpers/querys.php');

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// -----------------------------------------------------------------------
// 1. Collect and sanitize input
// -----------------------------------------------------------------------
$origin_country  = (int)($_POST['origin_country']  ?? 0);
$origin_state    = (int)($_POST['origin_state']    ?? 0);
$origin_city     = (int)($_POST['origin_city']     ?? 0);
$dest_country    = (int)($_POST['dest_country']    ?? 0);
$dest_state      = (int)($_POST['dest_state']      ?? 0);
$dest_city       = (int)($_POST['dest_city']       ?? 0);
$weight          = abs((float)($_POST['weight']    ?? 0));
$qty             = max(1, (int)($_POST['qty']       ?? 1));
$length          = abs((float)($_POST['length']    ?? 0));
$width           = abs((float)($_POST['width']     ?? 0));
$height          = abs((float)($_POST['height']    ?? 0));
$service_mode    = (int)($_POST['service_mode']    ?? 0);

// Basic validation
if ($origin_country <= 0 || $dest_country <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Please select origin and destination countries. / Por favor seleccione país de origen y destino.']);
    exit;
}
if ($weight <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Weight must be greater than zero. / El peso debe ser mayor que cero.']);
    exit;
}

// -----------------------------------------------------------------------
// 2. Load system settings (meter for volumetric, currency)
// -----------------------------------------------------------------------
$settings = cdp_getSettingsCourier();
$meter    = ($settings && isset($settings->meter) && (float)$settings->meter > 0)
            ? (float)$settings->meter
            : 5000.0;
$currency      = ($settings && isset($settings->for_currency)) ? $settings->for_currency : 'USD';
$for_symbol    = ($settings && isset($settings->for_symbol))   ? $settings->for_symbol   : '$';
$for_decimal   = ($settings && isset($settings->for_decimal))  ? (int)$settings->for_decimal : 2;

// -----------------------------------------------------------------------
// 3. Determine chargeable weight (real vs volumetric)
// -----------------------------------------------------------------------
$peso_real_total  = (float)$weight * $qty;
$volumetric_total = 0.0;
if ($length > 0 && $width > 0 && $height > 0 && $meter > 0) {
    $volumetric_total = (($length * $width * $height) / $meter) * $qty;
}

// Detect air service (same logic as cdp_calculateTariffServerSide)
$is_air = false;
if ($service_mode > 0) {
    $dbCheck = new Conexion;
    $dbCheck->cdp_query("SELECT name_item FROM cdb_category WHERE id = :id LIMIT 1");
    $dbCheck->bind(':id', $service_mode);
    $dbCheck->cdp_execute();
    $catRow = $dbCheck->cdp_registro();
    if ($catRow && !empty($catRow->name_item)) {
        $sm = mb_strtolower($catRow->name_item, 'UTF-8');
        if (strpos($sm, 'aereo') !== false || strpos($sm, 'aéreo') !== false || strpos($sm, 'air') !== false) {
            $is_air = true;
        }
    }
}

// Also check cdb_shipping_mode name
if (!$is_air && $service_mode > 0) {
    $dbSm = new Conexion;
    $dbSm->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
    $dbSm->bind(':id', $service_mode);
    $dbSm->cdp_execute();
    $smRow = $dbSm->cdp_registro();
    if ($smRow && !empty($smRow->ship_mode)) {
        $smName = mb_strtolower($smRow->ship_mode, 'UTF-8');
        if (strpos($smName, 'aereo') !== false || strpos($smName, 'aéreo') !== false || strpos($smName, 'air') !== false) {
            $is_air = true;
        }
    }
}

$chargeable_weight = $is_air
    ? max($peso_real_total, $volumetric_total)
    : $peso_real_total;
$chargeable_weight = round($chargeable_weight, 2);

if ($chargeable_weight <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Could not calculate chargeable weight. / No se pudo calcular el peso facturable.']);
    exit;
}

// -----------------------------------------------------------------------
// 4. Build service-mode ID list (same fallback logic as server-side fn)
// -----------------------------------------------------------------------
$order_svc_ids = [$service_mode];

if ($service_mode > 0) {
    $dbMode = new Conexion;
    $dbMode->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
    $dbMode->bind(':id', $service_mode);
    $dbMode->cdp_execute();
    $modeRow = $dbMode->cdp_registro();
    if ($modeRow && !empty($modeRow->ship_mode)) {
        $modeTerm = '%' . mb_strtolower(trim($modeRow->ship_mode), 'UTF-8') . '%';
        $dbMode2 = new Conexion;
        $dbMode2->cdp_query("SELECT id FROM cdb_shipping_mode WHERE LOWER(TRIM(COALESCE(ship_mode,''))) LIKE :term");
        $dbMode2->bind(':term', $modeTerm);
        $dbMode2->cdp_execute();
        $relRows = $dbMode2->cdp_registros();
        if ($relRows) {
            foreach ($relRows as $rr) {
                $rid = (int)$rr->id;
                if ($rid > 0 && !in_array($rid, $order_svc_ids, true)) {
                    $order_svc_ids[] = $rid;
                }
            }
        }
    }
}
$order_svc_ids[] = 0; // always include catch-all

$placeholders    = array_map(fn($i) => ':osvc_' . $i, array_keys($order_svc_ids));
$order_svc_in    = implode(', ', $placeholders);

// -----------------------------------------------------------------------
// 5. Tariff lookup — mirrors cdp_calculateTariffServerSide() exactly
//    client_id = 0  (public / no user)
// -----------------------------------------------------------------------
$tariff = null;
$db = new Conexion;

// Pass 1: origin + destiny + state + city
$sql1 = "
    SELECT *
    FROM cdb_shipping_fees
    WHERE
        origin  = :origin  AND destiny = :destiny
        AND (state IS NULL OR state = 0 OR state = :state)
        AND (city  IS NULL OR city  = 0 OR city  = :city)
        AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
        AND :cw BETWEEN initial_range AND final_range
    ORDER BY
        (CASE WHEN order_service_options = :svc_exact THEN 0 ELSE 1 END),
        (CASE WHEN client_id = 0 OR client_id IS NULL THEN 0 ELSE 1 END),
        id DESC
    LIMIT 1
";
$db->cdp_query($sql1);
$db->bind(':origin',    $origin_country);
$db->bind(':destiny',   $dest_country);
$db->bind(':state',     $dest_state);
$db->bind(':city',      $dest_city);
$db->bind(':cw',        $chargeable_weight);
$db->bind(':svc_exact', $service_mode);
foreach (array_combine($placeholders, $order_svc_ids) as $ph => $val) {
    $db->bind($ph, $val);
}
$db->cdp_execute();
$tariff = $db->cdp_registro();

// Pass 2: no state/city filter
if (!$tariff) {
    $sql2 = "
        SELECT *
        FROM cdb_shipping_fees
        WHERE
            origin  = :origin2 AND destiny = :destiny2
            AND (state IS NULL OR state = 0)
            AND (city  IS NULL OR city  = 0)
            AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
            AND :cw2 BETWEEN initial_range AND final_range
        ORDER BY
            (CASE WHEN order_service_options = :svc_exact2 THEN 0 ELSE 1 END),
            (CASE WHEN client_id = 0 OR client_id IS NULL THEN 0 ELSE 1 END),
            id DESC
        LIMIT 1
    ";
    $db->cdp_query($sql2);
    $db->bind(':origin2',    $origin_country);
    $db->bind(':destiny2',   $dest_country);
    $db->bind(':cw2',        $chargeable_weight);
    $db->bind(':svc_exact2', $service_mode);
    foreach (array_combine($placeholders, $order_svc_ids) as $ph => $val) {
        $db->bind($ph, $val);
    }
    $db->cdp_execute();
    $tariff = $db->cdp_registro();
}

// Pass 3: origin + destiny only (any weight range)
if (!$tariff) {
    $sql3 = "
        SELECT *
        FROM cdb_shipping_fees
        WHERE
            origin  = :origin3 AND destiny = :destiny3
            AND :cw3 BETWEEN initial_range AND final_range
        ORDER BY
            (CASE WHEN order_service_options = :svc_exact3 THEN 0 ELSE 1 END),
            (CASE WHEN client_id = 0 OR client_id IS NULL THEN 0 ELSE 1 END),
            id DESC
        LIMIT 1
    ";
    $db->cdp_query($sql3);
    $db->bind(':origin3',    $origin_country);
    $db->bind(':destiny3',   $dest_country);
    $db->bind(':cw3',        $chargeable_weight);
    $db->bind(':svc_exact3', $service_mode);
    $db->cdp_execute();
    $tariff = $db->cdp_registro();
}

// -----------------------------------------------------------------------
// 6. No tariff found
// -----------------------------------------------------------------------
if (!$tariff) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'No tariff found for this route and weight. Please contact us for a custom quote. / No se encontró tarifa para esta ruta y peso. Contáctenos para una cotización personalizada.',
    ]);
    exit;
}

// -----------------------------------------------------------------------
// 7. Calculate price
// -----------------------------------------------------------------------
$price_base      = round((float)$tariff->price, $for_decimal);
$price_mile      = (float)($tariff->price_mile ?? 0);
$total_price     = round($price_base, $for_decimal);   // distance_miles = 0 for public quote
$price_lb        = $chargeable_weight > 0 ? round($total_price / $chargeable_weight, 4) : $total_price;

// -----------------------------------------------------------------------
// 8. Resolve service name
// -----------------------------------------------------------------------
$service_name = '';
$svc_id = (int)($tariff->order_service_options ?? 0);
if ($svc_id > 0) {
    $dbSvc = new Conexion;
    $dbSvc->cdp_query("SELECT ship_mode FROM cdb_shipping_mode WHERE id = :id LIMIT 1");
    $dbSvc->bind(':id', $svc_id);
    $dbSvc->cdp_execute();
    $svcRow = $dbSvc->cdp_registro();
    if ($svcRow) {
        $service_name = $svcRow->ship_mode;
    }
}

// -----------------------------------------------------------------------
// 9. Build response
// -----------------------------------------------------------------------
ob_end_clean();
echo json_encode([
    'success'           => true,
    'price_per_lb'      => $price_lb,
    'total'             => $total_price,
    'chargeable_weight' => $chargeable_weight,
    'weight_real'       => round($peso_real_total, 2),
    'weight_vol'        => round($volumetric_total, 2),
    'is_air'            => $is_air,
    'service'           => $service_name,
    'currency'          => $currency,
    'symbol'            => $for_symbol,
    'decimals'          => $for_decimal,
    'tariff_id'         => (int)$tariff->id,
]);
exit;
