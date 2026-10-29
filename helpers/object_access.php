<?php
require_once __DIR__ . '/querys.php';
require_once dirname(__DIR__) . '/api/v1/ApiAccess.php';
require_once dirname(__DIR__) . '/api/v1/ApiResponse.php';

function cdp_requestActor(): object
{
    $user = new User();
    if (!$user->logged_in) ApiResponse::unauthorized();
    return (object) ['id' => (int) $user->uid, 'userlevel' => (int) $user->userlevel, 'name_off' => $user->name_off];
}

function cdp_requireObjectAccess(string $resource, int $id): void
{
    ApiAccess::requireObject(cdp_requestActor(), $resource, $id);
}

function cdp_requireAddressAccess(string $resource, int $id, ?int $owner = null): void
{
    if (!in_array($resource, ['customer', 'recipient'], true)) throw new InvalidArgumentException('Invalid resource.');
    $table = $resource === 'customer' ? 'cdb_senders_addresses' : 'cdb_recipients_addresses';
    $column = $resource === 'customer' ? 'user_id' : 'recipient_id';
    $db = new Conexion();
    $db->cdp_query('SELECT ' . $column . ' AS owner FROM ' . $table . ' WHERE id_addresses = :id LIMIT 1');
    $db->bind(':id', $id);
    $row = $db->cdp_registro();
    if (!$row || ($owner !== null && (int) $row->owner !== $owner)) ApiResponse::notFound('Address not found.');
    cdp_requireObjectAccess($resource, (int) $row->owner);
}
