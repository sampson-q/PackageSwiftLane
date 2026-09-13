<?php
/**
 * Air journey — the customer-facing JFK → London → Accra flow.
 *
 * The public tracking pages (views/track.php, views/track_online_shopping.php)
 * render an air shipment as a 16-stage journey:
 *
 *   1  Received at <Company> Warehouse   9  Processing at London Hub
 *   2  Prepared for Airline             10  Scheduled for Accra
 *   3  Delivered to Airline             11  Departed London
 *   4  Accepted by Airline              12  In Transit to Accra
 *   5  Scheduled for Departure          13  Arrived in Accra
 *   6  Departed JFK                     14  Customs / Destination Processing
 *   7  In Transit to London             15  Cleared / Released
 *   8  Arrived in London                16  Ready for Collection
 *
 * The stage a shipment sits at is derived from its (effective) cdb_styles
 * status; the date/time of each stage comes from cdb_courier_track events.
 * Flight number and AWB number ride on those events (optional columns
 * flight_no / awb_no — see sql/air_journey_tracking.sql). Everything degrades
 * gracefully when the columns or the history table are absent.
 *
 * Hard rule from the business: "Scheduled for Departure" / "Scheduled for
 * Accra" are NOT departures. Only an explicit departed/in-transit status moves
 * a shipment past a "Scheduled" stage.
 *
 * The company name is never hard-coded here — it is passed in from settings
 * (cdb_settings.site_name via Core) by the caller.
 */

/**
 * The lane's fixed points. One lane is in service today; keep the shape so a
 * second lane can be added without touching the views.
 *
 * @return array{origin:array,transit:array,destination:array}
 */
function cdp_airRoute()
{
    return [
        'origin'      => ['code' => 'JFK',     'city' => 'New York', 'label' => 'JFK, New York'],
        'transit'     => ['code' => 'LHR/LGW', 'city' => 'London',   'label' => 'LHR/LGW, London'],
        'destination' => ['code' => 'ACC',     'city' => 'Accra',    'label' => 'ACC, Accra'],
    ];
}

/**
 * The 16 stages, with the company name from settings substituted in.
 *
 * @param string $company cdb_settings.site_name
 * @return array<int,array{key:string,label:string,desc:string,icon:string}>
 */
function cdp_airStages($company = '')
{
    $company = trim((string) $company);
    $warehouse = $company !== '' ? $company . ' Warehouse' : 'Warehouse';

    return [
        ['key' => 'received',        'label' => 'Received at ' . $warehouse,        'icon' => '📥', 'desc' => 'Package received and processed at our U.S. facility.'],
        ['key' => 'prepared',        'label' => 'Prepared for Airline',             'icon' => '📦', 'desc' => 'Shipment consolidated, packed and prepared for airline handover.'],
        ['key' => 'delivered_air',   'label' => 'Delivered to Airline',             'icon' => '🚚', 'desc' => 'Cargo delivered to the airline/cargo handling facility at JFK.'],
        ['key' => 'accepted_air',    'label' => 'Accepted by Airline',              'icon' => '✅', 'desc' => 'Airline has officially received and accepted the cargo for transportation.'],
        ['key' => 'sched_departure', 'label' => 'Scheduled for Departure',          'icon' => '🗓️', 'desc' => 'Shipment assigned/scheduled for its outbound flight.'],
        ['key' => 'departed_jfk',    'label' => 'Departed JFK',                     'icon' => '🛫', 'desc' => 'Shipment has departed New York.'],
        ['key' => 'transit_london',  'label' => 'In Transit to London',             'icon' => '✈️', 'desc' => 'Shipment is currently en route to the London transit hub.'],
        ['key' => 'arrived_london',  'label' => 'Arrived in London',                'icon' => '🛬', 'desc' => 'Shipment has arrived at London Heathrow/Gatwick.'],
        ['key' => 'london_hub',      'label' => 'Processing at London Hub',         'icon' => '🔄', 'desc' => 'Cargo is being transferred/processed for the connecting flight to Accra.'],
        ['key' => 'sched_accra',     'label' => 'Scheduled for Accra',              'icon' => '🗓️', 'desc' => 'Shipment has been assigned to the Accra-bound flight.'],
        ['key' => 'departed_london', 'label' => 'Departed London',                  'icon' => '🛫', 'desc' => 'Shipment has departed London for Accra.'],
        ['key' => 'transit_accra',   'label' => 'In Transit to Accra',              'icon' => '✈️', 'desc' => 'Shipment is currently en route to Ghana.'],
        ['key' => 'arrived_accra',   'label' => 'Arrived in Accra',                 'icon' => '🛬', 'desc' => 'Shipment has arrived at Kotoka International Airport.'],
        ['key' => 'customs',         'label' => 'Customs / Destination Processing', 'icon' => '🛃', 'desc' => 'Shipment is undergoing customs and destination handling.'],
        ['key' => 'cleared',         'label' => 'Cleared / Released',               'icon' => '🏢', 'desc' => 'Shipment has completed the required clearance and has been released.'],
        ['key' => 'collection',      'label' => 'Ready for Collection',             'icon' => '🎉', 'desc' => 'Shipment is ready for customer pickup/collection.'],
    ];
}

/**
 * Normalise a status label for matching: lowercase, letters/digits only.
 */
function cdp_airNormalizeLabel($s)
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $s));
}

/**
 * Map a cdb_styles status onto a stage index (0..15), or null when the status
 * says nothing about the air journey.
 *
 * Resolution order:
 *   1. exact status name — the 12 air statuses seeded by
 *      sql/air_journey_tracking.sql plus the pre-existing house statuses that
 *      already mean one of the stages (works on any install, whatever the ids);
 *   2. cdb_styles.id for this install's legacy statuses;
 *   3. keyword fallback for operator-edited names. "scheduled" is tested
 *      BEFORE "departed"/"transit" so a scheduled flight never reads as flown.
 *
 * @param int    $statusId
 * @param string $statusName
 * @return int|null
 */
function cdp_airStageIndex($statusId, $statusName = '')
{
    static $byName = null;
    if ($byName === null) {
        $byName = [];
        foreach (cdp_airStages('') as $i => $s) {
            $byName[cdp_airNormalizeLabel($s['label'])] = $i;
        }
        // Pre-existing statuses that already mean one of the stages.
        $aliases = [
            'receivedoffice' => 0, 'receivedatwarehouse' => 0, 'inwarehouse' => 0,
            'consolidate' => 1, 'consolidated' => 1, 'approved' => 1,
            'rejectedbyairlineawaitingnewshipment' => 2,
            'acceptedbyairline' => 3,
            'intransit' => 6,
            'arrivedincustomsclearance' => 13,
            'sortingataccraoffice' => 14, 'distribution' => 14, 'invoiced' => 14, 'pendingpayment' => 14,
            'readyforpickup' => 15, 'readyforcollection' => 15,
        ];
        $byName += $aliases;
    }

    $key = cdp_airNormalizeLabel($statusName);
    if ($key !== '' && isset($byName[$key])) {
        return $byName[$key];
    }

    // Legacy ids on this install (mirrors helpers/track_progress.php).
    static $byId = [
        1 => 0, 2 => 0, 4 => 0, 11 => 0, 12 => 0, 17 => 0, 18 => 0, 21 => 0, 25 => 0, 27 => 0, 29 => 0,
        10 => 1, 13 => 1,
        28 => 2,
        31 => 3,
        3 => 6,
        30 => 13,
        5 => 14, 19 => 14, 23 => 14, 33 => 14, 6 => 14, 7 => 14,
        8 => 15, 14 => 15, 15 => 15, 16 => 15, 32 => 15, 35 => 15,
    ];
    $id = (int) $statusId;
    if ($id > 0 && isset($byId[$id])) {
        return $byId[$id];
    }

    // Keyword fallback. Order matters — see the docblock.
    $t = ' ' . strtolower((string) $statusName) . ' ';
    $has = function ($needles) use ($t) {
        foreach ((array) $needles as $n) {
            if (strpos($t, $n) !== false) return true;
        }
        return false;
    };
    if ($t === '  ') return null;
    if ($has(['pickup', 'pick up', 'picked', 'delivered', 'collect', 'auction'])) return 15;
    if ($has(['cleared', 'released', 'sorting', 'invoic', 'billed', 'payment', 'distribution', 'available', 'on route'])) return 14;
    if ($has(['customs', 'clearance', 'clearing'])) return 13;
    if ($has(['scheduled for accra', 'scheduled accra'])) return 9;
    if ($has(['scheduled'])) return 4;
    if ($has(['arrived in accra', 'arrived accra', 'kotoka'])) return 12;
    if ($has(['transit to accra', 'en route to accra', 'en route to ghana'])) return 11;
    if ($has(['departed london', 'departed lhr', 'departed lgw'])) return 10;
    if ($has(['london hub', 'processing at london', 'heathrow', 'gatwick'])) return 8;
    if ($has(['arrived in london', 'arrived london'])) return 7;
    if ($has(['departed jfk', 'departed new york'])) return 5;
    if ($has(['transit', 'en route', 'in flight', 'shipped', 'departed'])) return 6;
    if ($has(['accepted by airline', 'accepted'])) return 3;
    if ($has(['delivered to airline', 'rejected by airline', 'handed to airline'])) return 2;
    if ($has(['prepared', 'consolidat', 'approved', 'packed', 'booked'])) return 1;
    if ($has(['received', 'warehouse', 'pending', 'held', 'quot', 'cancel', 'reject', 'returned', 'not shipped'])) return 0;
    return null;
}

/**
 * Build the journey for the tracking page.
 *
 * @param int      $statusId    effective cdb_styles.id
 * @param string   $statusName  effective status label
 * @param object[] $events      cdb_courier_track rows (+ mod_style), oldest first.
 *                              Optional props: flight_no, awb_no.
 * @param string   $company     cdb_settings.site_name
 * @return array{index:int,count:int,steps:array,route:array,flights:array,awb:string,last_updated:?string}
 */
function cdp_airJourney($statusId, $statusName, array $events, $company = '')
{
    $steps = cdp_airStages($company);
    $n     = count($steps);

    $index = cdp_airStageIndex($statusId, $statusName);
    if ($index === null) {
        $index = 0;
    }

    foreach ($steps as $i => $s) {
        $steps[$i]['at']        = null;   // datetime string of the update
        $steps[$i]['flight_no'] = '';
    }

    $flights = [];
    $awb     = '';
    $last    = null;

    foreach ($events as $ev) {
        $when = isset($ev->t_date) ? (string) $ev->t_date : '';
        if ($when !== '' && ($last === null || strtotime($when) >= strtotime($last))) {
            $last = $when;
        }

        $fl = trim((string) ($ev->flight_no ?? ''));
        $aw = trim((string) ($ev->awb_no ?? ''));
        if ($fl !== '' && !in_array($fl, $flights, true)) {
            $flights[] = $fl;
        }
        if ($aw !== '') {
            $awb = $aw; // latest wins (events are oldest-first)
        }

        $si = cdp_airStageIndex((int) ($ev->status_courier ?? 0), (string) ($ev->mod_style ?? ''));
        if ($si === null || $si > $index) {
            // A stage the shipment has not (or no longer) reached is never
            // stamped — the rail must not contradict the current status.
            continue;
        }
        if ($when !== '') {
            $steps[$si]['at'] = $when; // latest update for that stage wins
        }
        if ($fl !== '') {
            $steps[$si]['flight_no'] = $fl;
        }
    }

    return [
        'index'        => $index,
        'count'        => $n,
        'steps'        => $steps,
        'route'        => cdp_airRoute(),
        'flights'      => $flights,
        'awb'          => $awb,
        'last_updated' => $last,
    ];
}

/**
 * Customer-facing label for a history event on an air shipment: the stage
 * label (company-aware) when the status maps to a stage, else the raw status.
 */
function cdp_airEventLabel($event, $company = '')
{
    $si = cdp_airStageIndex((int) ($event->status_courier ?? 0), (string) ($event->mod_style ?? ''));
    if ($si === null) {
        return (string) ($event->mod_style ?? '');
    }
    $stages = cdp_airStages($company);
    return $stages[$si]['label'];
}

/* cdp_courierTrackHasFlightColumns() lives in helpers/querys.php (writers need it too). */

/**
 * Tracking history for one or more tracking codes, oldest first. Used by the
 * public pages to merge a package's own events with its consolidation's.
 *
 * @param string[] $codes order_track values
 * @return object[]
 */
function cdp_getTrackEvents(array $codes)
{
    $codes = array_values(array_unique(array_filter(array_map('strval', $codes), 'strlen')));
    if (!$codes) {
        return [];
    }
    $ph = [];
    foreach ($codes as $i => $c) {
        $ph[] = ':c' . $i;
    }
    $db = new Conexion;
    $db->cdp_query("SELECT a.*, b.mod_style, b.color FROM cdb_courier_track AS a
        INNER JOIN cdb_styles AS b ON a.status_courier = b.id
        WHERE a.order_track IN (" . implode(',', $ph) . ")
        ORDER BY a.t_date ASC, a.id ASC");
    foreach ($codes as $i => $c) {
        $db->bind(':c' . $i, $c);
    }
    $rows = $db->cdp_registros();
    return is_array($rows) ? $rows : [];
}
