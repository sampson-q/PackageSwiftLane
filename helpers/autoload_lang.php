<?php


function cdb_money_format($amount)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_settings');

	$db->cdp_execute();

	$data_currency = $db->cdp_registro();

    if ($data_currency) {
        $curr_money = $data_currency->currency ?? 'USD';
        $curr_currency = $data_currency->for_currency ?? 's';
        $curr_symbol = $data_currency->for_symbol ?? '';
        $curr_decimal = $data_currency->for_decimal ?? 'true';
        $curr_point = $data_currency->dec_point ?? '.';
        $curr_sep = $data_currency->thousands_sep ?? ',';

        $amount = (float) $amount;
        $currency_code = ($curr_symbol == '') ? $curr_money : $curr_symbol;
        $dec_digit = ($curr_decimal == 'true') ? 2 : 0;

        $retval = number_format($amount, $dec_digit, $curr_point, $curr_sep);

        if (strlen($curr_symbol) > 1) {
            $retval = $currency_code . ' ' . $retval;
        } else {
            $retval = $currency_code . $retval;
        }

        return $retval;
    } else {
        return "Error: No se pudo obtener la configuración de la moneda";
    }
}


function cdb_money_format_bar($amount)
{
    $db = new Conexion;

    $db->cdp_query('SELECT * FROM cdb_settings');

	$db->cdp_execute();

	$data_currency = $db->cdp_registro();

    if ($data_currency) {
        $curr_money = $data_currency->currency ?? 'USD';
        $curr_symbol = $data_currency->for_symbol ?? '';
        $curr_decimal = $data_currency->for_decimal ?? 'true';
        $curr_point = $data_currency->dec_point ?? '.';
        $curr_sep = $data_currency->thousands_sep ?? ',';

        $amount = (float) $amount;
        $currency_code = ($curr_symbol == '') ? $curr_money : $curr_symbol;
        $dec_digit = ($curr_decimal == 'true') ? 2 : 0;

        $retval = number_format($amount, $dec_digit, $curr_point, $curr_sep);

        return $retval;
    } else {
        return "Error: No se pudo obtener la configuración de la moneda";
    }
}



/**
 * Status id for "Ready for PickUp" (cdb_styles). The customer handling fee is
 * only charged once a package/consolidation reaches this stage.
 */
if (!defined('CDP_STATUS_READY_FOR_PICKUP')) {
    define('CDP_STATUS_READY_FOR_PICKUP', 32);
}

/**
 * Other collectible statuses that also count toward the customer's amount payable:
 * "Pending Collection" (1) and "Not Picked Up" (16). A package at any of these
 * stages is awaiting collection at destination, so it is billable.
 */
if (!defined('CDP_STATUS_PENDING_COLLECTION')) {
    define('CDP_STATUS_PENDING_COLLECTION', 1);
}
if (!defined('CDP_STATUS_NOT_PICKED_UP')) {
    define('CDP_STATUS_NOT_PICKED_UP', 16);
}

/**
 * True when a package's status counts toward the customer's amount payable
 * (collectible at destination): Ready for PickUp, Pending Collection, Not Picked Up.
 * Use this everywhere the payable total / handling fee is decided so the set of
 * billable statuses stays in one place.
 */
if (!function_exists('cdp_isPayableStatus')) {
    function cdp_isPayableStatus($status)
    {
        $status = (int) $status;
        return $status === CDP_STATUS_READY_FOR_PICKUP
            || $status === CDP_STATUS_PENDING_COLLECTION
            || $status === CDP_STATUS_NOT_PICKED_UP;
    }
}

/**
 * Build a SQL boolean expression matching a tracking search term against ALL
 * three tracking identifiers an order can have, so one search box finds any:
 *   1. system tracking  = CONCAT(order_prefix, order_no)
 *   2. postal/carrier   = cdb_package_tracking_number.tracking_number (current)
 *   3. legacy postal    = cdb_add_order.tracking_num (old-system column, ~40k rows)
 *
 * $search must already be escaped/sanitized by the caller (the list endpoints run
 * it through cdp_sanitize). $alias is the cdb_add_order alias in the outer query
 * ('' for an un-aliased single-table query). Returns a parenthesized expression
 * with no leading AND. Lives here (not querys.php) so it is available wherever
 * loader.php is included.
 */
if (!function_exists('cdp_trackingSearchSql')) {
    function cdp_trackingSearchSql($search, $alias = 'a')
    {
        $p = ($alias !== '') ? ($alias . '.') : '';
        $s = $search;
        return "("
            . "CONCAT({$p}order_prefix, {$p}order_no) LIKE '%{$s}%'"
            . " OR {$p}tracking_num LIKE '%{$s}%'"
            . " OR EXISTS (SELECT 1 FROM cdb_package_tracking_number cptn"
            . " WHERE cptn.order_id = {$p}order_id AND cptn.tracking_number LIKE '%{$s}%')"
            . ")";
    }
}

/**
 * Convert a USD amount to GHS using the system exchange rate.
 * courier_add/edit/view stay in USD; conversion only happens here, at the
 * customer/payment/messaging stage.
 *
 * @param float      $usd
 * @param float|null $rate  Pass cdb_settings.exchange_rate when you already have
 *                          it; null fetches it once.
 */
function cdp_usdToGhs($usd, $rate = null)
{
    if ($rate === null) {
        $db = new Conexion;
        $db->cdp_query('SELECT exchange_rate FROM cdb_settings LIMIT 1');
        $db->cdp_execute();
        $row  = $db->cdp_registro();
        $rate = $row ? (float) $row->exchange_rate : 1.0;
    }
    $rate = ((float) $rate > 0) ? (float) $rate : 1.0;
    return (float) $usd * $rate;
}

/**
 * Convert a GHS amount to USD using the system exchange rate. Inverse of
 * cdp_usdToGhs(). Used when an operator enters a value in cedis (e.g. the
 * Financial Sheet custom-price input) but storage stays canonical USD.
 *
 * @param float      $ghs
 * @param float|null $rate  Pass cdb_settings.exchange_rate when you already have
 *                          it; null fetches it once.
 */
function cdp_ghsToUsd($ghs, $rate = null)
{
    if ($rate === null) {
        $db = new Conexion;
        $db->cdp_query('SELECT exchange_rate FROM cdb_settings LIMIT 1');
        $db->cdp_execute();
        $row  = $db->cdp_registro();
        $rate = $row ? (float) $row->exchange_rate : 1.0;
    }
    $rate = ((float) $rate > 0) ? (float) $rate : 1.0;
    return (float) $ghs / $rate;
}

/**
 * Handling fee (GHS) for a given GHS amount, per the tiered schedule.
 * Computed on the fly — never stored.
 */
function cdp_handlingFeeGhs($ghs_amount)
{
    $a = (float) $ghs_amount;
    if ($a < 300)   return 20.0;
    if ($a < 2000)  return 50.0;
    if ($a < 4000)  return 100.0;
    if ($a < 6000)  return 150.0;
    if ($a < 8000)  return 200.0;
    if ($a < 9000)  return 300.0;
    if ($a < 11000) return 400.0;
    if ($a < 13000) return 450.0;
    if ($a < 15000) return 500.0;
    if ($a < 17000) return 600.0;
    if ($a < 20000) return 800.0;
    return 1000.0;
}

/**
 * Customer amount payable in GHS for a single shipment.
 * Converts the stored USD total to GHS and, when $apply_handling_fee is true
 * (i.e. status = Ready for PickUp and the fee has not already been counted for
 * this consolidation), adds the tiered handling fee.
 *
 * @return array{ghs:float, handling_fee:float, total:float}
 */
function cdp_customerPayableGhs($usd_total, $apply_handling_fee, $rate = null)
{
    $ghs = cdp_usdToGhs($usd_total, $rate);
    $fee = $apply_handling_fee ? cdp_handlingFeeGhs($ghs) : 0.0;
    return ['ghs' => $ghs, 'handling_fee' => $fee, 'total' => $ghs + $fee];
}

function cdp_redirect_to($location)
{
	if (!headers_sent()) {
		header('Location: ' . $location);
		exit;
	} else
		echo '<script type="text/javascript">';
	echo 'window.location.href="' . $location . '";';
	echo '</script>';
	echo '<noscript>';
	echo '<meta http-equiv="refresh" content="0;url=' . $location . '" />';
	echo '</noscript>';
}


function cdp_sanitize($string, $trim = false, $int = false, $str = false)
{
	$string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$string = trim($string);
	$string = stripslashes($string);
	$string = strip_tags($string);
	$string = str_replace(array('‘', '’', '“', '”'), array("'", "'", '"', '"'), $string);

	if ($trim)
		$string = substr($string, 0, $trim);
	if ($int)
		$string = preg_replace("/[^0-9\s]/", "", $string);
	if ($str)
		$string = preg_replace("/[^a-zA-Z\s]/", "", $string);

	return $string;
}

/**
 * Browser URL for a stored avatar / document photo, whatever format the row
 * carries. Historic rows hold "uploads/x.jpg", "../assets/uploads/users/x.jpg",
 * "assets/uploads/x.jpg", "../" (a broken signup) or nothing at all; the topbar
 * and sidebar used to print the raw value (a 404 for every customer photo).
 */
if (!function_exists('cdp_avatarUrl')) {
    function cdp_avatarUrl($stored, $blank = 'uploads/blank.png')
    {
        $p = trim((string) $stored);
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#^(\.\./)+#', '', $p);   // "../assets/uploads/…" -> "assets/uploads/…"
        $p = preg_replace('#^/+#', '', $p);
        if (strpos($p, 'assets/') === 0) {
            $p = substr($p, 7);
        }
        if ($p === '' || $p === '/' || strpos($p, 'uploads/') !== 0 || substr($p, -1) === '/') {
            $p = $blank;
        }
        return 'assets/' . $p;
    }
}
