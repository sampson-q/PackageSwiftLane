<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************


function cdp_cleanOutx($text)
{
  $text =  strtr($text, array('\r\n' => "", '\r' => "", '\n' => ""));
  $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
  $text = str_replace('<br>', '<br />', $text);
  return stripslashes($text);
}

/**
 * Short HTML-escape helper for templates (used by print views such as
 * views/courier/warehouse_view_print.php and views/print/print_label_ship.php).
 * Without it those pages fatal with "Call to undefined function h()".
 */
if (!function_exists('h')) {
  function h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Customer display name with their locker id appended — e.g. "John Doe (LK123)".
 * Falls back to just the name when no locker is set. Used in customer-facing
 * notifications (email [NAME] / WhatsApp [CUSTOMER_FULLNAME]). Expects a
 * cdb_users row (fname / lname / locker).
 */
if (!function_exists('cdp_nameWithLocker')) {
  function cdp_nameWithLocker($user)
  {
    if (!$user) {
      return '';
    }
    $name   = trim((string) ($user->fname ?? '') . ' ' . (string) ($user->lname ?? ''));
    $locker = trim((string) ($user->locker ?? ''));
    return ($locker !== '') ? $name . ' (' . $locker . ')' : $name;
  }
}


/**
     * validate track()
     */
  function cdp_validateTrack($value)
  {

      $valid_uname = "/^[A-Z-a-z0-9_-]{4,55}$/"; 
        if (!preg_match($valid_uname, $value))
            return 2;
      
  }   


function cdp_email_users_notificationsx($array)
{

  $email = "";
  $contador = 0;

  while ($contador < count($array)) {

    $email .= $array[$contador] . ",";
    $contador++;
  }

  $email = substr($email, 0, -1);

  return $email;
}



function cdb_m_format($amount)
{
  $amount = (float) $amount;
  $db = new Conexion;
  $db->cdp_query('SELECT * FROM cdb_settings');
  $data_currency = $db->cdp_registro();
  if (!$data_currency) {
    return number_format($amount, 2, '.', ',');
  }
  $currency_decimal_digits = $data_currency->for_decimal ?? 'true';
  $currency_symbol_position = $data_currency->for_currency ?? 's';
  $curr_point = $data_currency->dec_point ?? '.';
  $curr_sep = $data_currency->thousands_sep ?? ',';
  $currency_code = !empty($data_currency->for_symbol) ? $data_currency->for_symbol : ($data_currency->currency ?? 'USD');

  $dec_digit = ($currency_decimal_digits === 'true' || $currency_decimal_digits === true) ? 2 : 0;

  if ($currency_symbol_position === 's') {
    $retval =
      number_format($amount, $dec_digit, $curr_point, $curr_sep) . ' ' . $currency_code;
  } else {
    $retval =
      $currency_code .
      ' ' .
      number_format($amount, $dec_digit, $curr_point, $curr_sep);
  }

  return $retval;
}


function cdb__forma($amount)
{
  $amount = (float) $amount;
  $db = new Conexion;
  $db->cdp_query('SELECT * FROM cdb_settings');
  $data_currency = $db->cdp_registro();
  if (!$data_currency) {
    return number_format($amount, 2, '.', ',');
  }
  $curr_symbol = $data_currency->for_symbol ?? '';
  $curr_money = $data_currency->currency ?? 'USD';
  $curr_decimal = $data_currency->for_decimal ?? 'true';
  $curr_point = $data_currency->dec_point ?? '.';
  $curr_sep = $data_currency->thousands_sep ?? ',';

  $currency_code = ($curr_symbol !== '') ? $curr_symbol : $curr_money;
  $dec_digit = ($curr_decimal === 'true' || $curr_decimal === true) ? 2 : 0;

  return number_format($amount, $dec_digit, $curr_point, $curr_sep);
}


/**
 * getSize()
 * 
 * @param mixed $size
 * @param integer $precision
 * @param bool $long_name
 * @param bool $real_size
 * @return
 */
function getSizex($size, $precision = 2, $long_name = false, $real_size = true)
{
  if ($size == 0) {
    return '-/-';
  } else {
    $base = $real_size ? 1024 : 1000;
    $pos = 0;
    while ($size > $base) {
      $size /= $base;
      $pos++;
    }
    $prefix = _getSizePrefix($pos);
    $size_name = $long_name ? $prefix . "bytes" : $prefix[0] . 'B';
    return round($size, $precision) . ' ' . ucfirst($size_name);
  }
}


/**
 * _getSizePrefix()
 * 
 * @param mixed $pos
 * @return
 */
function _getSizePrefixx($pos)
{
  switch ($pos) {
    case 00:
      return "";
    case 01:
      return "kilo";

    case 02:
      return "mega";
    case 03:
      return "giga";
    default:
      return "?-";
  }
}


function obtenerNombreMes($numeroMes) {
    // Array con los nombres de los meses en español
    $meses = array(
        1 => "Jan", 
        2 => "Feb", 
        3 => "Mar", 
        4 => "Apr", 
        5 => "may", 
        6 => "Jun", 
        7 => "Jul", 
        8 => "Aug", 
        9 => "Sept", 
        10 => "Oct", 
        11 => "Nov", 
        12 => "Dec"
    );

    // Verificar si el número de mes está dentro del rango válido
    if ($numeroMes >= 1 && $numeroMes <= 12) {
        return $meses[$numeroMes];
    } else {
        return "Invalid month";
    }
}

// Función para formatear fechas y evitar errores cuando el valor es null
function formatDate($date, $format = 'Y-m-d h:i A') {
    return $date ? date($format, strtotime($date)) : '';
}

// Función para obtener texto si el valor es null
function getTextOrDefault($text) {
    return $text ?? ''; // Retorna un string vacío si es null
}



function cdp_round_outx($valor)
{
  $float_redondeado = round($valor * 100) / 100;
  return $float_redondeado;
}

/**
 * Company address and phone numbers printed on shipment receipts and box
 * labels.
 *
 * Deliberately kept out of Settings: cdb_settings.c_address holds the US
 * receiving address, while the receipts and labels have to carry the Ghana
 * office. Edit here to change every receipt and every box label at once.
 *
 * @param bool $multiline true => the address split into display lines,
 *                        false => the same address as one comma-separated line
 * @return string[]|string
 */
function cdp_printBrandAddress($multiline = false)
{
  $lines = ['#01, Adaman Crescent, Behind The Allied Filling Station', 'Tesano Abeka Junction'];

  return $multiline ? $lines : implode(', ', $lines);
}

/**
 * Phone numbers printed beside cdp_printBrandAddress() on receipts and labels.
 */
function cdp_printBrandPhones()
{
  return '+233(0)243438799 || +233(0)342292798';
}
