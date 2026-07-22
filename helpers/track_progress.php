<?php
/**
 * Shipment tracking progress helper.
 *
 * Maps a shipment's current status onto SwiftLane's real journey pipeline so the
 * fancy tracking pages can render a progress stepper:
 *
 *   Registered (US)  →  Consolidated  →  In Transit  →  Accra Office  →  Delivered
 *
 * Packages are received at the US warehouse, grouped into a consolidation,
 * shipped/flown to Ghana, sorted at the Accra office, made ready for pickup,
 * then handed to the customer. When a package sits inside a consolidation the
 * consolidation's status is what should drive this (see cdp_getEffectiveTrackStatus).
 *
 * Detection is primarily by cdb_styles id (exact for this install) with a
 * keyword fallback so operator-added statuses still land somewhere sensible.
 *
 * @param int    $statusId    cdb_styles.id of the (effective) current status
 * @param string $statusName  mod_style text, used only as a keyword fallback
 * @return array{percent:float,index:int,steps:array<int,array{key:string,label:string,icon:string}>}
 */
function cdp_trackProgress($statusId, $statusName = '')
{
    $steps = [
        ['key' => 'registered',   'label' => 'Registered',   'icon' => '📦'],
        ['key' => 'consolidated', 'label' => 'Consolidated', 'icon' => '🗃️'],
        ['key' => 'transit',      'label' => 'In Transit',   'icon' => '🛫'],
        ['key' => 'office',       'label' => 'Accra Office', 'icon' => '🏢'],
        ['key' => 'delivered',    'label' => 'Delivered',    'icon' => '✅'],
    ];

    // cdb_styles.id → stage index (0..4). Precise for the current install.
    $byId = [
        // 0 — Registered / at US warehouse / pre-shipment admin states
        1 => 0, 2 => 0, 4 => 0, 10 => 0, 11 => 0, 12 => 0, 17 => 0, 18 => 0,
        19 => 0, 21 => 0, 23 => 0, 25 => 0, 27 => 0, 29 => 0, 35 => 0,
        // 1 — Consolidated
        13 => 1,
        // 2 — In transit to Ghana (booked, flying/sailing, customs)
        3 => 2, 5 => 2, 28 => 2, 30 => 2, 31 => 2,
        // 3 — Arrived / sorting / ready for pickup / out for local delivery
        6 => 3, 7 => 3, 14 => 3, 16 => 3, 32 => 3, 33 => 3,
        // 4 — Completed
        8 => 4, 15 => 4,
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
        if ($has(['delivered', 'picked up', 'completed', 'collected'])) {
            $index = 4;
        } elseif ($has(['pickup', 'pick up', 'sorting', 'available', 'accra office', 'on route', 'out for'])) {
            $index = 3;
        } elseif ($has(['transit', 'airline', 'customs', 'shipped', 'sailing', 'in flight', 'distribution', 'en route'])) {
            $index = 2;
        } elseif ($has(['consolidat'])) {
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
