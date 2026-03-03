<?php
/**
 * TEMPORAL: debug permisos y multi-tenant. Solo uso local. Borrar después.
 * Abrir en navegador estando logueado para ver userlevel, agency_id, is_restricted, permisos.
 */
require_once __DIR__ . '/loader.php';
require_once __DIR__ . '/helpers/querys.php';

header('Content-Type: text/plain; charset=UTF-8');

$db = new Conexion();

echo "========== 1) SHOW COLUMNS FROM cdb_user_roles ==========\n";
$db->cdp_query('SHOW COLUMNS FROM cdb_user_roles');
$db->cdp_execute();
$cols = $db->cdp_registros();
foreach ($cols ?: [] as $c) {
    echo (is_object($c) ? implode("\t", (array)$c) : json_encode($c)) . "\n";
}

echo "\n========== 2) SHOW COLUMNS FROM cdb_user_role_permissions ==========\n";
$db->cdp_query('SHOW COLUMNS FROM cdb_user_role_permissions');
$db->cdp_execute();
$cols = $db->cdp_registros();
foreach ($cols ?: [] as $c) {
    echo (is_object($c) ? implode("\t", (array)$c) : json_encode($c)) . "\n";
}

echo "\n========== 3) SHOW COLUMNS FROM cdb_users LIKE 'agency_id' ==========\n";
$db->cdp_query("SHOW COLUMNS FROM cdb_users LIKE 'agency_id'");
$db->cdp_execute();
$cols = $db->cdp_registros();
foreach ($cols ?: [] as $c) {
    echo (is_object($c) ? implode("\t", (array)$c) : json_encode($c)) . "\n";
}

echo "\n========== 4) SHOW COLUMNS FROM cdb_recipients LIKE 'agency_id' ==========\n";
$db->cdp_query("SHOW COLUMNS FROM cdb_recipients LIKE 'agency_id'");
$db->cdp_execute();
$cols = $db->cdp_registros();
foreach ($cols ?: [] as $c) {
    echo (is_object($c) ? implode("\t", (array)$c) : json_encode($c)) . "\n";
}

echo "\n========== 5) Usuarios userlevel=6 (últimos 10) ==========\n";
$db->cdp_query('SELECT id, username, userlevel, agency_id, name_off FROM cdb_users WHERE userlevel=6 ORDER BY id DESC LIMIT 10');
$db->cdp_execute();
$rows = $db->cdp_registros();
foreach ($rows ?: [] as $r) {
    echo (is_object($r) ? $r->id . "\t" . $r->username . "\t" . $r->userlevel . "\t" . ($r->agency_id ?? 'NULL') . "\t" . ($r->name_off ?? '') : json_encode($r)) . "\n";
}

echo "\n========== Permisos role_id=6 (para verificar 017) ==========\n";
$db->cdp_query("
    SELECT rp.role_id, ma.action_name, rp.permitted
    FROM cdb_user_role_permissions rp
    JOIN cdb_user_module_actions ma ON ma.id = rp.module_action_id
    WHERE rp.role_id = 6 AND rp.permitted = 1
    ORDER BY ma.action_name
");
$db->cdp_execute();
$perms6 = $db->cdp_registros();
foreach ($perms6 ?: [] as $p) {
    echo (is_object($p) ? ($p->role_id . "\t" . $p->action_name . "\t" . $p->permitted) : json_encode($p)) . "\n";
}

$user = new User();
$ctx = cdp_getAgencyContext();
$user->cdp_getUserPermissions();

echo "\n========== DEBUG USUARIO LOGUEADO (OBLIGATORIO PARA PRUEBAS) ==========\n";
if (!$user->logged_in) {
    echo "No hay sesión. Inicia sesión como Agencia (userlevel 6) y recarga.\n";
} else {
    echo "user_id=" . $user->uid . "\n";
    echo "username=" . $user->username . "\n";
    echo "userlevel=" . $user->userlevel . "\n";
    $ud = $user->cdp_getUserData();
    echo "agency_id=" . (isset($ud->agency_id) ? (int)$ud->agency_id : 'NULL') . "\n";
    echo "is_restricted=" . ($ctx['is_restricted'] ? 'true' : 'false') . "\n";
    echo "permissions_count=" . count($user->permissions) . "\n";
    echo "permissions_list=" . json_encode($user->permissions, JSON_UNESCAPED_UNICODE) . "\n";
    echo "edit_user=" . ($user->cdp_hasPermission('edit_user') ? 'yes' : 'no') . "\n";
    echo "view_client_list=" . ($user->cdp_hasPermission('view_client_list') ? 'yes' : 'no') . "\n";
    echo "view_recipients=" . ($user->cdp_hasPermission('view_recipients') ? 'yes' : 'no') . "\n";
    echo "add_shipment=" . ($user->cdp_hasPermission('add_shipment') ? 'yes' : 'no') . "\n";
}
