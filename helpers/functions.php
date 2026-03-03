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
