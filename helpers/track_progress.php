<?php
/**
 * Shipment tracking progress helper.
 *
 * Maps a shipment's current status onto SwiftLane's customer-facing journey so
 * the public tracking pages can render a progress stepper:
 *
 *   Received Office → Approved For Shipping → Transit → Clearing At Customs
 *   → Accra Sorting Office → Billed → Ready For Pickup
 *
 * Consolidation is deliberately NOT a step: it is an internal warehouse
 * operation and means nothing to the customer, so consolidated packages sit at
 * "Approved For Shipping" until they actually move. (A consolidated package
 * still inherits its consolidation's status — see cdp_getEffectiveTrackStatus.)
 *
 * Detection is primarily by cdb_styles id (exact for this install) with a
 * keyword fallback so operator-added statuses still land somewhere sensible.
 *
 * @param int    $statusId    cdb_styles.id of the (effective) current status
 * @param string $statusName  mod_style text, used only as a keyword fallback
 * @return array{percent:float,inset:float,index:int,count:int,steps:array<int,array{key:string,label:string,icon:string}>}
 */
function cdp_trackProgress($statusId, $statusName = '')
{
    $steps = [
        ['key' => 'received',  'label' => 'Received Office',      'icon' => '📥'],
        ['key' => 'approved',  'label' => 'Approved For Shipping', 'icon' => '✅'],
        ['key' => 'transit',   'label' => 'Transit',              'icon' => '🛫'],
        ['key' => 'customs',   'label' => 'Clearing At Customs',  'icon' => '🛃'],
        ['key' => 'sorting',   'label' => 'Accra Sorting Office', 'icon' => '🏢'],
        ['key' => 'billed',    'label' => 'Billed',               'icon' => '🧾'],
        ['key' => 'pickup',    'label' => 'Ready For Pickup',     'icon' => '📦'],
    ];

    // cdb_styles.id → stage index (0..6). Precise for the current install.
    $byId = [
        // 0 — Received at the office / pre-shipment admin states
        1 => 0, 2 => 0, 4 => 0, 11 => 0, 12 => 0, 17 => 0, 18 => 0,
        21 => 0, 25 => 0, 27 => 0, 29 => 0,
        // 1 — Approved / booked for shipping (consolidation lives here too)
        10 => 1, 13 => 1, 28 => 1, 31 => 1,
        // 2 — Moving to Ghana
        3 => 2, 5 => 2,
        // 3 — Customs clearance
        30 => 3,
        // 4 — Sorting at the Accra office
        33 => 4,
        // 5 — Billed / awaiting payment
        19 => 5, 23 => 5,
        // 6 — Ready for the customer (and everything after)
        6 => 6, 7 => 6, 8 => 6, 14 => 6, 15 => 6, 16 => 6, 32 => 6, 35 => 6,
    ];

    $index = null;
    $id = (int) $statusId;
    if ($id > 0 && isset($byId[$id])) {
        $index = $byId[$id];
    }

    // Keyword fallback for unknown/edited statuses.
    if ($index === null) {
        $t = ' ' . strtolower((string) $statusName) . ' ';
        $has = function ($needles) use ($t) {
            foreach ((array) $needles as $n) {
                if (strpos($t, $n) !== false) return true;
            }
            return false;
        };
        if ($has(['pickup', 'pick up', 'picked', 'delivered', 'available', 'on route', 'out for', 'collected', 'auction'])) {
            $index = 6;
        } elseif ($has(['invoic', 'billed', 'payment', 'charge'])) {
            $index = 5;
        } elseif ($has(['sorting', 'accra office', 'accra sorting', 'arrived'])) {
            $index = 4;
        } elseif ($has(['customs', 'clearance', 'clearing'])) {
            $index = 3;
        } elseif ($has(['transit', 'distribution', 'sailing', 'in flight', 'en route', 'shipped'])) {
            $index = 2;
        } elseif ($has(['approved', 'accepted', 'consolidat', 'booked'])) {
            $index = 1;
        } else {
            $index = 0;
        }
    }

    $n = count($steps);
    $inset = 50 / $n;                       // half a column, as a %
    $frac = $n > 1 ? $index / ($n - 1) : 0; // 0..1 along the rail
    $percent = round($frac * (100 - 2 * $inset), 2);

    return [
        'percent' => $percent, // absolute fill width, as a % of the stepper
        'inset'   => $inset,   // rail/fill left inset, as a %
        'index'   => $index,
        'count'   => $n,
        'steps'   => $steps,
    ];
}
