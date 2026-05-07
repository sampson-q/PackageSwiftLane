<?php
require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_shipment_list');

require_once("../../helpers/querys.php");

header('Content-Type: application/json; charset=UTF-8');

try {
    // ============================
    // 1. ENTRADAS BÁSICAS
    // ============================
    $sender_id            = isset($_POST['sender_id']) ? (int)$_POST['sender_id'] : 0;
    $sender_address_id    = isset($_POST['sender_address']) ? (int)$_POST['sender_address'] : (int)($_POST['sender_address_id'] ?? 0);
    $recipient_id         = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
    $recipient_address_id = isset($_POST['recipient_address']) ? (int)$_POST['recipient_address'] : (int)($_POST['recipient_address_id'] ?? 0);
    $rate_provider        = isset($_POST['rate_provider']) ? trim($_POST['rate_provider']) : 'internal';

    // packages puede venir como JSON string o array
    $packages = $_POST['packages'] ?? [];
    if (is_string($packages)) {
        $decoded  = json_decode($packages, true);
        $packages = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($packages)) {
        $packages = [];
    }

    // Validaciones básicas
    $err = '';
    if (!$sender_id)            $err = 'Sender is required';
    if (!$sender_address_id)    $err = 'Sender address is required';
    if (!count($packages))      $err = 'Packages are required';

    if ($err) {
        echo json_encode(['success' => false, 'error' => $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================
    // 2. COMPLEMENTARIOS
    // ============================
    // En tu lógica, cliente = remitente
    $client_id = $sender_id;

    $order_service_options = (isset($_POST['order_service_options']) && $_POST['order_service_options'] !== '')
        ? (int)$_POST['order_service_options']
        : null;

    $distance_miles = (isset($_POST['distance_miles']) && is_numeric($_POST['distance_miles']))
        ? (float)$_POST['distance_miles']
        : 0.0;

    // Try to resolve addresses from both possible tables (sender addresses OR recipient addresses).
    // Prefer the one indicated by recipient_type (sent by frontend), but fallback if not found.

    $recipient_type = isset($_POST['recipient_type']) ? trim($_POST['recipient_type']) : 'recipient';

    // Resolve sender address (sender addresses table is primary)
    // If not found, try recipient addresses table as a fallback (unlikely but safe).
    $sender_address = cdp_getSenderAddress($sender_address_id);
    if (!$sender_address) {
        $sender_address = cdp_getRecipientAddress($sender_address_id);
    }

    // Resolve recipient address according to declared type, then fallback to the other table.
    $recipient_address = null;
    if ($recipient_type === 'user') {
        // recipient selected is actually a user -> try sender addresses table first
        $recipient_address = cdp_getSenderAddress($recipient_address_id);
        if (!$recipient_address) {
            $recipient_address = cdp_getRecipientAddress($recipient_address_id);
        }
    } else {
        // normal recipient -> try recipients_addresses table first
        $recipient_address = cdp_getRecipientAddress($recipient_address_id);
        if (!$recipient_address) {
            $recipient_address = cdp_getSenderAddress($recipient_address_id);
        }
    }

    $settings = cdp_getSettingsCourier();
    $meter             = isset($settings->meter) ? (float)$settings->meter : 0.0;

    if (!$sender_address) {
        echo json_encode([
            'success' => false,
            'error'   => 'Could not resolve sender or recipient addresses.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $meter = (float)$meter;

    $origin  = (int)$sender_address->country;
    $destiny = (int)$recipient_address->country;
    $state   = (int)$recipient_address->state;
    $city    = (int)$recipient_address->city;

    // ============================
    // 3. ¿MODO AÉREO? (PESO VOLUMÉTRICO)
    // ============================
    $is_air = false;
    if (!is_null($order_service_options)) {
        $dbm = new Conexion;
        // Tomamos el nombre desde cdb_category (Air Freight, etc.)
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

    // ============================
    // 4. CALCULAR PESO COBRABLE
    // ============================
    $peso_real_total  = 0.0;
    $volumetric_total = 0.0;

    foreach ($packages as $p) {
        $qty    = isset($p['qty'])    ? (float)$p['qty']    : 1.0;
        $length = isset($p['length']) ? (float)$p['length'] : 0.0;
        $width  = isset($p['width'])  ? (float)$p['width']  : 0.0;
        $height = isset($p['height']) ? (float)$p['height'] : 0.0;
        $weight = isset($p['weight']) ? (float)$p['weight'] : 0.0;

        if ($qty <= 0) {
            $qty = 1.0;
        }

        // Peso volumétrico: (L * W * H) / meter
        $vw = ($meter > 0) ? (($length * $width * $height) / $meter) : 0.0;

        $peso_real_total  += $weight * $qty;
        $volumetric_total += $vw * $qty;
    }

    $peso_real_total   = round($peso_real_total, 2);
    $volumetric_total  = round($volumetric_total, 2);
    $chargeable_weight = $is_air ? max($peso_real_total, $volumetric_total) : $peso_real_total;
    $chargeable_weight = round($chargeable_weight, 2);

    if ($chargeable_weight <= 0) {
        echo json_encode([
            'success'           => true,
            'data'              => ['price' => 0],
            'price_lb'          => 0,
            'initial_range'     => 0,
            'final_range'       => 0,
            'chargeable_weight' => 0,
            'subtotal_peso'     => 0,
            'cargo_millas'      => 0,
            'total_tarifa'      => 0,
            'source'            => 'internal'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================
    // 5. TARIFA INTERNA POR RANGO (acepta cdb_category y cdb_shipping_mode por nombre; fallback state/city 0)
    // ============================
    $order_svc = ($order_service_options !== null && $order_service_options !== '') ? (int)$order_service_options : 0;
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
                    if ($id > 0 && !in_array($id, $order_svc_ids, true)) {
                        $order_svc_ids[] = $id;
                    }
                }
            }
        }
    }
    $order_svc_ids[] = 0;

    $db = new Conexion;
    $placeholders = array_map(function ($i) { return ':order_svc_' . $i; }, array_keys($order_svc_ids));
    $order_svc_in = implode(', ', $placeholders);
    $sql = "
        SELECT *
        FROM cdb_shipping_fees
        WHERE
            (client_id = :cid OR client_id = 0 OR client_id IS NULL)
            AND origin = :origin AND destiny = :destiny
            AND (state IS NULL OR state = 0 OR state = :state)
            AND (city IS NULL OR city = 0 OR city = :city)
            AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
            AND :cw BETWEEN initial_range AND final_range
        ORDER BY (CASE WHEN client_id = :cid THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact THEN 0 ELSE 1 END), id DESC
        LIMIT 1
    ";
    $db->cdp_query($sql);
    $db->bind(':cid', $client_id);
    $db->bind(':origin', $origin);
    $db->bind(':destiny', $destiny);
    $db->bind(':state', $state);
    $db->bind(':city', $city);
    $db->bind(':order_svc_exact', $order_svc);
    foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) {
        $db->bind($k, $v);
    }
    $db->bind(':cw', $chargeable_weight);
    $db->cdp_execute();
    $tariff = $db->cdp_registro();

    if (!$tariff) {
        $sql2 = "
            SELECT *
            FROM cdb_shipping_fees
            WHERE
                (client_id = :cid2 OR client_id = 0 OR client_id IS NULL)
                AND origin = :origin2 AND destiny = :destiny2
                AND (state IS NULL OR state = 0)
                AND (city IS NULL OR city = 0)
                AND (order_service_options IS NULL OR order_service_options IN ($order_svc_in))
                AND :cw2 BETWEEN initial_range AND final_range
            ORDER BY (CASE WHEN client_id = :cid2 THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact2 THEN 0 ELSE 1 END), id DESC
            LIMIT 1
        ";
        $db->cdp_query($sql2);
        $db->bind(':cid2', $client_id);
        $db->bind(':origin2', $origin);
        $db->bind(':destiny2', $destiny);
        $db->bind(':order_svc_exact2', $order_svc);
        foreach (array_combine($placeholders, $order_svc_ids) as $k => $v) {
            $db->bind($k, $v);
        }
        $db->bind(':cw2', $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }
    if (!$tariff) {
        $sql3 = "
            SELECT *
            FROM cdb_shipping_fees
            WHERE
                (client_id = :cid3 OR client_id = 0 OR client_id IS NULL)
                AND origin = :origin3 AND destiny = :destiny3
                AND :cw3 BETWEEN initial_range AND final_range
            ORDER BY (CASE WHEN client_id = :cid3 THEN 0 ELSE 1 END), (CASE WHEN order_service_options = :order_svc_exact3 THEN 0 ELSE 1 END), id DESC
            LIMIT 1
        ";
        $db->cdp_query($sql3);
        $db->bind(':cid3', $client_id);
        $db->bind(':origin3', $origin);
        $db->bind(':destiny3', $destiny);
        $db->bind(':order_svc_exact3', $order_svc);
        $db->bind(':cw3', $chargeable_weight);
        $db->cdp_execute();
        $tariff = $db->cdp_registro();
    }

    if (!$tariff) {
        $errMsg = (isset($lang['tariff_no_configured']) ? $lang['tariff_no_configured'] : 'No hay tarifas configuradas para la ruta/modo seleccionados');
        $errPayload = [
            'success' => false,
            'error'   => $errMsg,
            'message' => 'No hay tarifa para el peso y la ruta (origen, destino, estado, ciudad, modo de servicio) indicados.',
        ];
        if (defined('CDP_DEBUG_TARIFFS') && CDP_DEBUG_TARIFFS) {
            $errPayload['debug'] = [
                'client_id' => $client_id,
                'origin' => $origin,
                'destiny' => $destiny,
                'state' => $state,
                'city' => $city,
                'order_service_options' => $order_service_options,
                'chargeable_weight' => $chargeable_weight,
                'tariff_id' => null,
            ];
        }
        echo json_encode($errPayload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ============================
    // 6. price = TARIFA FIJA DEL RANGO
    // ============================
    // Ejemplo: rango 1–34, price = 22 → siempre 22 USD dentro de ese rango
    $flat_price = round((float)$tariff->price, 2);

    // Recargo por millas (si lo usas)
    $price_mile   = (float)($tariff->price_mile ?? 0);
    $cargo_millas = ($price_mile > 0 && $distance_miles > 0)
        ? round($price_mile * $distance_miles, 2)
        : 0.0;

    // Total real de la tarifa = rango fijo + millas
    // Ejemplo: 22 + (12 * 10) = 142
    $total = round($flat_price + $cargo_millas, 2);

    // Precio unitario SOLO informativo (para el campo "Precio kg")
    // No se usa para recalcular el total, solo para mostrar algo coherente.
    $price_unit = $chargeable_weight > 0
        ? round($flat_price / $chargeable_weight, 4)
        : $flat_price;

    // ============================
    // 7. RESPUESTA PARA EL FRONTEND
    // ============================
    $response = [
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
        'matched'           => ['id' => (int)$tariff->id],
    ];
    if (defined('CDP_DEBUG_TARIFFS') && CDP_DEBUG_TARIFFS) {
        $response['debug'] = [
            'origin' => $origin,
            'destiny' => $destiny,
            'state' => $state,
            'city' => $city,
            'order_service_options' => $order_service_options,
            'chargeable_weight' => $chargeable_weight,
            'client_id' => $client_id,
            'tariff_id' => (int)$tariff->id,
        ];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Excepción: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
