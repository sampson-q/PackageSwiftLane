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

 


//Function to add days
function cdp_sumardias($fecha, $dias)
{
    $nuevafecha = strtotime($dias . " day", strtotime($fecha));
    $nuevafecha = date('Y-m-d', $nuevafecha); //formatea nueva fecha 
    return $nuevafecha; //retorna valor de la fecha 
}



function formato($valor)
{
    return number_format($valor, 2, '.', ',');
}


function cdp_getChecked($row, $status)
{
    if ($row == $status) {
        echo "checked=\"checked\"";
    }
}

function cdp_getCheckedlocker($row, $status)
{
    if ($row == $status) {
        echo "checked=\"checked\"";
    }
}

function cdp_userStatus($status, $id, $lang)
{
    switch ($status) {
        case 1:
            $display = '<span style="font-size:15px;color:#48CFAD;" class="ti-check" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage16'] . '"></span> ' . $lang['user_manage16'] . '';
            break;

        case 0:
            $display = '<a style="font-size:15px;color:orange;" class="activate" id="act_' . $id . '"><i class="icon-adjust text-orange" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage17'] . '"></i> ' . $lang['user_manage17'] . '</a>';
            break;
    }

    return $display;
}


function cdp_paymentStatus($status, $id, $lang)
{
    switch ($status) {
        case 1:
            $display = '<span style="font-size:15px;color:#48CFAD;" class="ti-check" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage16'] . '"></span> ' . $lang['user_manage16'] . '';
            break;

        case 0:
            $display = '<a style="font-size:15px;color:orange;" class="activate" id="act_' . $id . '"><i class="icon-adjust text-orange" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage17'] . '"></i> ' . $lang['user_manage17'] . '</a>';
            break;
    }

    return $display;
}

function cdp_locationStatus($status, $id, $lang)
{
    switch ($status) {
        case 1:
            $display = '<span style="font-size:15px;color:#48CFAD;" class="ti-check" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage16'] . '"></span> ' . $lang['user_manage16'] . '';
            break;

        case 0:
            $display = '<a style="font-size:15px;color:orange;" class="activate" id="act_' . $id . '"><i class="icon-adjust text-orange" data-toggle="tooltip" data-placement="top" title="' . $lang['user_manage17'] . '"></i> ' . $lang['user_manage17'] . '</a>';
            break;
    }

    return $display;
}

function cdp_isAdmin($userlevel, $lang)
{
    switch ($userlevel) {
        case 9:
            $display = '<span style="font-size:20px;color:red;" class="ti-user" data-toggle="tooltip" data-placement="top" title="' . $lang['leftorder001791'] . '"></span>';
            break;

        case 7:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792221'] . '"></span>';
            break;

        case 6:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792222'] . '"></span>';
            break;

        case 5:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792223'] . '"></span>';
            break;

        case 4:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792224'] . '"></span>';
            break;

        case 3:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792225'] . '"></span>';
            break;

        case 2:
            $display = '<span style="font-size:20px;color:#FFB973;" class="ti-user" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792227'] . '"></span>';
            break;

        case 1:
            $display = '<span style="font-size:20px;color:#48CFAD;" class="ti-face-smile" data-toggle="tooltip" data-placement="top"  title="' . $lang['leftorder001792226'] . '"></span>';
            break;
        default:
            $title = isset($lang['role_' . $userlevel]) ? $lang['role_' . $userlevel] : 'User';
            $display = '<span style="font-size:20px;color:#697a8d;" class="ti-user" data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($title) . '"></span>';
            break;
    }

    return $display;
}






function cdp_generarCodigo($longitud)
{
    $key = '';
    $pattern = '1234567890';
    $max = strlen($pattern) - 1;
    for ($i = 0; $i < $longitud; $i++) {
        $key .= $pattern[mt_rand(0, $max)];
    }
    return $key;
}


function cdp_cleanOut($text) {
    // Asegurarse de que la entrada sea una cadena
    if (!is_string($text)) {
        return $text;
    }

    // Reemplazar secuencias de escape de saltos de línea con un espacio
    $text = strtr($text, array('\r\n' => ' ', '\r' => ' ', '\n' => ' '));
    
    // Convertir entidades HTML a sus caracteres correspondientes
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Normalizar etiquetas de salto de línea
    $text = str_replace(array('<br>', '<br/>', '<br />'), "\n", $text);
    
    // Eliminar barras invertidas añadidas por addslashes o similares
    $text = stripslashes($text);

    // Eliminar espacios innecesarios
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Trim para eliminar espacios en blanco al principio y al final
    $text = trim($text);

    return $text;
}



function cdp_email_users_notifications($array)
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




/**
 * getSize()
 */
function getSize($size, $precision = 2, $long_name = false, $real_size = true)
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
 */
function _getSizePrefix($pos)
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



function cdp_round_out($valor)
{
    $float_redondeado = round($valor * 100) / 100;
    return $float_redondeado;
}
