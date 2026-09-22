<?php
/**
 * ============================================================================
 * Package videos — the clips staff record on the add/edit shipment and add/edit
 * package pages (dataJs/video_capture.js → cdp_saveShipmentVideos()).
 *
 * Rule: a video exists to show the package's condition while it is in our
 * hands. Once the package is delivered it is deleted — the file on disk and
 * its row — and never comes back. "Delivered" is the package's EFFECTIVE status
 * (helpers/querys.php CONSOLIDATION INHERITANCE), so a package that is
 * delivered through its consolidation counts too, while one that already left
 * the consolidation on its own (Ready for PickUp, Picked Up, ...) keeps its
 * clip until it is really delivered.
 *
 *   cdp_purgeDeliveredVideos()          sweep every delivered package (cron +
 *                                       every delivery endpoint, idempotent)
 *   cdp_purgeShipmentVideos($id, $pkg)  one package, no status check (callers
 *                                       that already know it is delivered)
 * ============================================================================
 */

require_once __DIR__ . '/querys.php';

if (!function_exists('cdp_videoExts')) {
    /** Extensions cdp_saveShipmentVideos() accepts; the galleries use the same list. */
    function cdp_videoExts()
    {
        return array('webm', 'mp4', 'm4v', 'mov', 'ogg', 'ogv', '3gp', '3gpp', 'mkv', 'avi');
    }
}

if (!function_exists('cdp_videoFileTables')) {
    /** @return array{files:string,orders:string,files_where:string} */
    function cdp_videoFileTables($isPackage)
    {
        return $isPackage
            ? array('files' => 'cdb_customer_package_files', 'orders' => 'cdb_customers_packages', 'files_where' => '')
            : array('files' => 'cdb_order_files',            'orders' => 'cdb_add_order',          'files_where' => ' AND f.is_consolidate = 0');
    }
}

if (!function_exists('cdp_videoUnlink')) {
    /** Delete the file behind a files-table row. True when it is gone (or never existed). */
    function cdp_videoUnlink($url)
    {
        $rel = ltrim(str_replace('\\', '/', (string) $url), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return false;
        }
        $path = dirname(__DIR__) . '/' . $rel;
        if (!is_file($path)) {
            return true;
        }
        return @unlink($path);
    }
}

if (!function_exists('cdp_purgeVideoRows')) {
    /**
     * Delete these files-table rows and their files.
     *
     * @param object[] $rows  rows with id, url
     * @return int rows deleted
     */
    function cdp_purgeVideoRows(array $rows, $isPackage)
    {
        $t  = cdp_videoFileTables($isPackage);
        $db = new Conexion;
        $n  = 0;
        foreach ($rows as $r) {
            if (!cdp_videoUnlink($r->url)) {
                error_log('[video_files] could not delete ' . $r->url);
                continue;   // keep the row so the sweep tries again
            }
            $db->cdp_query("DELETE FROM {$t['files']} WHERE id = :id");
            $db->bind(':id', (int) $r->id);
            if ($db->cdp_execute()) {
                $n++;
            }
        }
        return $n;
    }
}

if (!function_exists('cdp_purgeShipmentVideos')) {
    /** Delete every video of one package. No status check — the caller decides. */
    function cdp_purgeShipmentVideos($order_id, $isPackage = false)
    {
        $t  = cdp_videoFileTables($isPackage);
        $in = "'" . implode("','", cdp_videoExts()) . "'";
        $db = new Conexion;
        $db->cdp_query("SELECT f.id, f.url FROM {$t['files']} f
            WHERE f.order_id = :oid AND LOWER(f.file_type) IN ({$in}){$t['files_where']}");
        $db->bind(':oid', (int) $order_id);
        $db->cdp_execute();
        return cdp_purgeVideoRows((array) $db->cdp_registros(), $isPackage);
    }
}

if (!function_exists('cdp_purgeDeliveredVideos')) {
    /**
     * Delete the videos of every delivered package (shipments and locker
     * packages). Only video rows are read, so this is cheap enough to run on
     * every delivery and from cron.
     *
     * @return array{shipments:int,packages:int}
     */
    function cdp_purgeDeliveredVideos()
    {
        $out = array('shipments' => 0, 'packages' => 0);
        $in  = "'" . implode("','", cdp_videoExts()) . "'";

        foreach (array(false => 'shipments', true => 'packages') as $isPackage => $key) {
            $isPackage = (bool) $isPackage;
            $t  = cdp_videoFileTables($isPackage);
            $db = new Conexion;
            $db->cdp_query("SELECT f.id, f.url, o.order_no, o.status_courier, o.is_consolidate
                FROM {$t['files']} f
                INNER JOIN {$t['orders']} o ON o.order_id = f.order_id
                WHERE LOWER(f.file_type) IN ({$in}){$t['files_where']}");
            $db->cdp_execute();
            $rows = (array) $db->cdp_registros();
            if (!$rows) {
                continue;
            }

            $nos = array();
            foreach ($rows as $r) {
                $nos[] = $r->order_no;
            }
            if (function_exists('cdp_prefetchConsolidations')) {
                cdp_prefetchConsolidations($nos, $isPackage);
            }

            $delivered = array();
            foreach ($rows as $r) {
                $eff = cdp_getEffectiveStatus($r->order_no, $r->status_courier, $r->is_consolidate, $isPackage);
                if ((int) $eff->status_id === 8) {
                    $delivered[] = $r;
                }
            }
            $out[$key] = cdp_purgeVideoRows($delivered, $isPackage);
        }
        return $out;
    }
}

if (!function_exists('cdp_purgeDeliveredVideosLater')) {
    /**
     * Queue the sweep for after the response. Every endpoint that can move a
     * package to Delivered calls this once; the sweep runs at shutdown, after
     * the status has been written, and the staff member never waits for it.
     */
    function cdp_purgeDeliveredVideosLater()
    {
        require_once __DIR__ . '/after_response.php';
        cdp_afterResponse(function () {
            try {
                cdp_purgeDeliveredVideos();
            } catch (\Throwable $e) {
                error_log('[video_files] sweep failed: ' . $e->getMessage());
            }
        });
    }
}
