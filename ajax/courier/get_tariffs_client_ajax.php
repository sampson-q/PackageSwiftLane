<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// Client-safe tariff lookup — requires login only.
// client_id is always the session user (cannot be spoofed).

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

require_once("../../helpers/querys.php");

header('Content-Type: application/json; charset=UTF-8');

try {
    // ── Inputs ──────────────────────────────────────────────────────────
    // client_id is always the logged-in user — not from POST.
    $client_id = (int)$_SESSION['userid'];

    $sender_address_id    = isset($_POST['sender_address']) ? (int)$_POST['sender_address'] : (int)($_POST['sender_address_id'] ?? 0);
    $recipient_id         = isset($_POST['recipient_id'])   ? (int)$_POST['recipient_id']   : 0;
    $recipient_address_id = isset($_POST['recipient_address']) ? (int)$_POST['recipient_address'] : (int)($_POST['recipient_address_id'] ?? 0);

    $packages = $_POST['packages'] ?? [];
    if (is_string($packages)) {
        $decoded  = json_decode($packages, true);
        $packages = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($packages)) {
        $packages = [];
    }

    // Basic validation
    if (!$sender_address_id)    { echo json_encode(['success' => false, 'error' => 'Falta dirección remitente']); exit; }
    if (!$recipient_id)         { echo json_encode(['success' => false, 'error' => 'Falta destinatario']); exit; }
    if (!$recipient_address_id) { echo json_encode(['success' => false, 'error' => 'Falta dirección destinatario']); exit; }
    if (!count($packages))      { echo json_encode(['success' => false, 'error' => 'Faltan paquetes']); exit; }

    $order_service_options = (isset($_POST['order_service_options']) && $_POST['order_service_options'] !== '')
        ? (int)$_POST['order_service_options']
        : null;

    $distance_miles = (isset($_POST['distance_miles']) && is_numeric($_POST['distance_miles']))
        ? (float)$_POST['distance_miles']
        : 0.0;

    $sender_address    = cdp_getSenderAddress($sender_address_id);
    $recipient_address = cdp_getRecipientAddress($recipient_address_id);
    $settings          = cdp_getSettingsCourier();
    $meter             = isset($settings->meter) ? (float)$settings->meter : 0.0;

    if (!$sender_address || !$recipient_address) {
        echo json_encode(['success' => false, 'error' => 'No se pudo resolver direcciones.']);
        exit;
    }

    $origin  = (int)$sender_address->country;
    $destiny = (int)$recipient_address->country;
    $state   = (int)$recipient_address->state;
    $city    = (int)$recipient_address->city;

    // ── Is Air? (volumetric weight) ──────────────────────────────────────
    $is_air = false;
    if (!is_null($order_service_options)) {
        $dbm = new Conexion;
        $dbm->cdp_query("SELECT name_item FROM cdb_category WHERE id = :id LIMIT 1");
        $dbm->bind(':id', $order_service_options);
        $dbm->cdp_execute();
        $catRow = $dbm->cdp_registro();
        if ($catRow && !empty($catRow->name_item)) {
            $sm = mb_strtolower($catRow->name_item, 'UTF-8');
            if (strpos($sm, 'aereo') !== false || strpos($sm, 'aéreo') !== false || strpos($sm, 'air') !== false) {
                $is_air = true;
            }
        }
    }

    // ── Chargeable weight ────────────────────────────────────────────────
    $peso_real_total  = 0.0;
    $volumetric_total = 0.0;
    foreach ($packages as $p) {
        $qty    = max(1.0, (float)($p['qty']    ?? 1));
        $length = (float)($p['length'] ?? 0);
        $width  = (float)($p['width']  ?? 0);
        $height = (float)($p['height'] ?? 0);
        $weight = (float)($p['weight'] ?? 0);
        $vw = ($meter > 0) ? (($length * $width * $height) / $meter) : 0.0;
        $peso_real_total  += $weight * $qty;
        $volumetric_total += $vw    * $qty;
    }
    $peso_real_total   = round($peso_real_total,  2);
    $volumetric_total  = round($volumetric_total, 2);
    $chargeable_weight = $is_air ? max($peso_real_total, $volumetric_total) : $peso_real_total;
    $chargeable_weight = round($chargeable_weight, 2);

    if ($chargeable_weight <= 0) {
        echo json_encode(['success' => true, 'data' => ['price' => 0], 'price_lb' => 0,
            'initial_range' => 0, 'final_range' => 0, 'chargeable_weight' => 0,
            'subtotal_peso' => 0, 'cargo_millas' => 0, 'total_tarifa' => 0, 'source' => 'internal']);
        exit;
    }

    // ── Build service-option id list ─────────────────────────────────────
    $order_svc    = ($order_service_options !== null) ? (int)$order_service_options : 0;
    $order_svc_ids = [$order_svc];
    if ($order_svc > 0) {
        $dbm = new Conexion;
        $dbm->cdp_query("SELECT name_item FROM cdb_category WHERE id = :id LIMIT 1");
        $dbm->bind(':id', $order_svc);
        $dbm->cdp_execute();
        $catRow = $dbm->cdp_registro();
        if ($catRow && !empty($catRow->name_item)) {
            $name = mb_strtolower(trim($catRow->name_item), 'UTF-8');
            $term = '%' . $name . '%';
            $dbm->cdp_query("SELECT id FROM cdb_shipping_mode WHERE LOWER(TRIM(COALESCE(ship_mode,''))) LIKE :term");
            $dbm->bind(':term', $term);
            $dbm->cdp_execute();
            $rows = $dbm->cdp_registros();
            if ($rows) {
                foreach ($rows as $r) {
                    $id = (int)$r->id;
                    if ($id > 0 && !in_array($id, $order_svc_ids, true)) $order_svc_ids[] = $id;
                }
            }
        }
    }
    $order_svc_ids[] = 0;

    $db           = new Conexion;
    $placeholders = array_map(function ($i) { return ':order_svc_' . $i; }, array_keys($order_svc_ids));
    $order_svc_in = implode(', ', $placeholders);

    // ── Tariff lookup (3 passes, same as admin) ──────────────────────────
    $tariff = null;

    // Pass 1 — exact state + city
    $sql = "SELECT * FROM cdb_shipping_fees
            WHERE (client_id = :cid OR client_id = 0 OR client_id IS NULL)
              AND origin = :origin AND destiny = :destiny
              AND (state IS NULL OR state = 0 OR state = :state)
              AND (city  IS NULL OR city  = 0 OR city  = :city)
              AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
              AND :cw BETWEEN initial_range AND final_range
            ORDER BY (CASE WHEN client_id = :cid  THEN 0 ELSE 1 END),
                     (CASE WHEN order_service_options = :order_svc_exact THEN 0 ELSE 1 END),
                     id DESC
            LIMIT 1";
    $db->cdp_query($sql);
    $db->bind(':cid',            $client_id);
    $db->bind(':origin',         $origin);
    $db->bind(':destiny',        $destiny);
    $db->bind(':state',          $state);
    $db->bind(':city',           $city);
    $db->bind(':order_svc_exact', $order_svc);
    foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) $db->bind($k, $v);
    $db->bind(':cw', $chargeable_weight);
    $db->cdp_execute();
    $tariff = $db->cdp_registro();

    // Pass 2 — ignore city
    if (!$tariff) {
        $sql2 = "SELECT * FROM cdb_shipping_fees
                 WHERE (client_id = :cid2 OR client_id = 0 OR client_id IS NULL)
                   AND origin = :origin2 AND destiny = :destiny2
                   AND (state IS NULL OR state = 0)
                   AND (city  IS NULL OR city  = 0)
                   AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
                   AND :cw2 BETWEEN initial_range AND final_range
                 ORDER BY (CASE WHEN client_id = :cid2 THEN 0 ELSE 1 END),
                          (CASE WHEN order_service_options = :order_svc_exact2 THEN 0 ELSE 1 END),
                          id DESC
                 LIMIT 1";
        $db->cdp_query($sql2);
        $db->bind(':cid2',             $client_id);
        $db->bind(':origin2',          $origin);
        $db->bind(':destiny2',         $destiny);
        $db->bind(':order_svc_exact2', $order_svc);
        foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) $db->bind($k, $v);
        $db->bind(':cw2', $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }

    // Pass 3 — any state/city
    if (!$tariff) {
        $sql3 = "SELECT * FROM cdb_shipping_fees
                 WHERE (client_id = :cid3 OR client_id = 0 OR client_id IS NULL)
                   AND origin = :origin3 AND destiny = :destiny3
                   AND :cw3 BETWEEN initial_range AND final_range
                 ORDER BY (CASE WHEN client_id = :cid3 THEN 0 ELSE 1 END),
                          (CASE WHEN order_service_options = :order_svc_exact3 THEN 0 ELSE 1 END),
                          id DESC
                 LIMIT 1";
        $db->cdp_query($sql3);
        $db->bind(':cid3',             $client_id);
        $db->bind(':origin3',          $origin);
        $db->bind(':destiny3',         $destiny);
        $db->bind(':order_svc_exact3', $order_svc);
        $db->bind(':cw3',              $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }

    if (!$tariff) {
        echo json_encode([
            'success'           => false,
            'error'             => isset($lang['tariff_no_configured'])
                                   ? $lang['tariff_no_configured']
                                   : 'No tariff found for this route and weight.',
            'chargeable_weight' => $chargeable_weight,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Build response ───────────────────────────────────────────────────
    $flat_price   = round((float)$tariff->price, 2);
    $price_mile   = (float)($tariff->price_mile ?? 0);
    $cargo_millas = ($price_mile > 0 && $distance_miles > 0)
                    ? round($price_mile * $distance_miles, 2)
                    : 0.0;
    $total        = round($flat_price + $cargo_millas, 2);
    $price_unit   = $chargeable_weight > 0
                    ? round($flat_price / $chargeable_weight, 4)
                    : $flat_price;

    echo json_encode([
        'success'           => true,
        'data'              => ['price' => $price_unit],
        'price_lb'          => $price_unit,
        'price_base'        => $flat_price,
        'price_total'       => $total,
        'tariff_id'         => (int)$tariff->id,
        'initial_range'     => (float)$tariff->initial_range,
        'final_range'       => (float)$tariff->final_range,
        'chargeable_weight' => $chargeable_weight,
        'subtotal_peso'     => $flat_price,
        'cargo_millas'      => $cargo_millas,
        'total_tarifa'      => $total,
        'source'            => 'internal',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
