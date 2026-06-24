<?php
/**
 * Mark overdue invoices as "verify payment" (status_invoice = 3) — THROTTLED.
 *
 * This UPDATE used to run on EVERY list / report / dashboard page load as a
 * full scan-and-write over cdb_add_order. It only needs to run periodically, so
 * this helper runs it at most once per $minIntervalSec across all requests
 * (tracked via a timestamp file in the system temp dir). Newly-overdue invoices
 * still surface within the interval — the same effect a cron job would have,
 * with no cron infrastructure required.
 *
 * If you later add a real cron entry, call cdp_markOverdueInvoices($db, 0) there
 * to force the run.
 */
if (!function_exists('cdp_markOverdueInvoices')) {
    function cdp_markOverdueInvoices($db, int $minIntervalSec = 300): void
    {
        $marker = sys_get_temp_dir() . '/cdp_overdue_invoices.ts';
        $now  = time();
        $last = is_file($marker) ? (int) @file_get_contents($marker) : 0;
        if ($minIntervalSec > 0 && ($now - $last) < $minIntervalSec) {
            return; // ran recently — skip the scan-and-write
        }
        // Stamp BEFORE running so concurrent requests don't pile up on the UPDATE.
        @file_put_contents($marker, (string) $now, LOCK_EX);
        $db->cdp_query("UPDATE cdb_add_order SET status_invoice = 3 WHERE due_date < now() AND status_invoice != 1 AND order_payment_method > 1");
        $db->cdp_execute();
    }
}
