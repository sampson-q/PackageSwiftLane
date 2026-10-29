<?php
require_once dirname(__DIR__, 2) . '/helpers/rbac.php';

/** Object authorization shared by list, read, update and delete routes. */
class ApiAccess
{
    public static function client(object $actor): bool
    {
        return cdp_roleHasFlag((int) $actor->userlevel, 'is_client');
    }

    public static function scope(object $actor, string $resource, string $alias = ''): string
    {
        if ($alias !== '' && !preg_match('/\A[a-z][a-z0-9_]*\z/i', $alias)) {
            throw new InvalidArgumentException('Invalid table alias.');
        }
        if (!in_array($resource, ['customer', 'recipient', 'shipment', 'prealert', 'package', 'consolidation', 'consolidated_package'], true)) {
            throw new InvalidArgumentException('Invalid resource.');
        }
        if ((int) $actor->id <= 0) return ' AND 1=0';
        $knownType = false;
        foreach (['is_superadmin', 'is_admin', 'is_staff', 'is_client', 'is_driver', 'is_agency'] as $flag) {
            if (cdp_roleHasFlag((int) $actor->userlevel, $flag)) $knownType = true;
        }
        if (!$knownType) return ' AND 1=0';
        $prefix = $alias === '' ? '' : $alias . '.';
        $id = (int) $actor->id;
        $where = '';
        if ($resource === 'customer') {
            $roles = [1];
            foreach (array_keys(cdp_roleFlagsMap()) as $role) {
                if (cdp_roleHasFlag((int) $role, 'is_client')) $roles[] = (int) $role;
            }
            $where .= ' AND ' . $prefix . 'userlevel IN (' . implode(',', array_unique($roles)) . ')';
        }

        if (self::client($actor)) {
            $columns = ['customer' => 'id', 'recipient' => 'sender_id', 'shipment' => 'sender_id', 'prealert' => 'customer_id', 'package' => 'sender_id', 'consolidation' => 'sender_id', 'consolidated_package' => 'sender_id'];
            if (!isset($columns[$resource])) throw new InvalidArgumentException('Invalid resource.');
            $where .= ' AND ' . $prefix . $columns[$resource] . ' = ' . $id;
        }
        if (in_array($resource, ['shipment', 'package', 'consolidation', 'consolidated_package'], true) && cdp_roleHasFlag((int) $actor->userlevel, 'is_driver')) {
            $where .= ' AND ' . $prefix . 'driver_id = ' . $id;
        }
        $context = cdp_getAgencyContext();
        if (!empty($context['is_restricted'])) {
            $agency = (int) ($context['agency_id'] ?? 0);
            if ($agency <= 0) return $where . ' AND 1=0';
            if (in_array($resource, ['shipment', 'package', 'consolidation', 'consolidated_package'], true)) {
                $branch = (int) cdp_getAgencyBranchIdForUser($actor->name_off ?? '');
                $where .= $branch > 0 ? ' AND ' . $prefix . 'agency = ' . $branch : ' AND 1=0';
            } elseif ($resource === 'prealert') {
                $where .= ' AND ' . $prefix . 'customer_id IN (SELECT id FROM cdb_users WHERE agency_id = ' . $agency . ')';
            } else {
                $where .= ' AND ' . $prefix . 'agency_id = ' . $agency;
            }
        }
        return $where;
    }

    public static function requireObject(object $actor, string $resource, int $id): void
    {
        $tables = [
            'customer' => ['cdb_users', 'id'], 'recipient' => ['cdb_recipients', 'id'],
            'shipment' => ['cdb_add_order', 'order_id'], 'prealert' => ['cdb_pre_alert', 'pre_alert_id'],
            'package' => ['cdb_customers_packages', 'order_id'],
            'consolidation' => ['cdb_consolidate', 'consolidate_id'],
            'consolidated_package' => ['cdb_consolidate_packages', 'consolidate_id'],
        ];
        if (!isset($tables[$resource])) throw new InvalidArgumentException('Invalid resource.');
        [$table, $column] = $tables[$resource];
        $db = new Conexion();
        $db->cdp_query('SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' = :id' . self::scope($actor, $resource) . ' LIMIT 1');
        $db->bind(':id', $id);
        if (!$db->cdp_registro()) {
            error_log('[security] ' . json_encode(['event' => 'object_access_denied', 'actor' => (int) $actor->id, 'resource' => $resource, 'record' => $id]));
            ApiResponse::notFound('Record not found.');
        }
    }
}
