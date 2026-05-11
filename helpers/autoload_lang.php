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

        // if ($curr_currency == 's') {
        $retval = $currency_code . ' ' . $retval;
        // } else {
        //     $retval .= ' ' . $currency_code;
        // }

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
