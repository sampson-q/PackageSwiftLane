<?php
/**
 * Loader principal: solo lo usan las páginas del panel (index.php, etc.).
 * install/index.php y verify_purchase.php NO incluyen este loader, así el cliente
 * puede activar la licencia una sola vez desde cualquier servidor.
 */
$installFile = "install/install.deprixaprov75";

if (is_file($installFile)) {
    header('Location: install');
    exit();
}

require_once('config/config.php');
require_once('helpers/function_exist.php');
require_once('helpers/autoload_lang.php');
require_once('helpers/functions.php');
require_once('helpers/pagination.php');
require_once('helpers/backups_function.php');

// Verificación de licencia: si no hay .lic permite uso (primera activación); si servidor no responde no bloquea
if (function_exists('xs5wohkrfju1ffmcfvl238a5')) {
    xs5wohkrfju1ffmcfvl238a5();
}


// Mark loader as loaded so api_whatsapp_service.php skips its permission check
// when included as a dependency (not called directly).
if (!defined('DEPRIXAPRO_LOADER_LOADED')) {
    define('DEPRIXAPRO_LOADER_LOADED', true);
}

// Autoload PHP
spl_autoload_register(function ($className) {
    require_once('lib/' . $className . '.php');
});

// Incluye el archivo de configuración de idioma después de cargar las clases
require_once('helpers/config.lang.php');

// Carga ajax_guard para que csrf_token() esté disponible en todas las páginas del panel
// (necesario para el meta CSRF tag en head_scripts.php que habilita $.ajaxSetup)
require_once('helpers/ajax_guard.php');

