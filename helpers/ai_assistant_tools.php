<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * AI Assistant — the tools the model may call.
 *
 * Every tool is read-only (except suggest_page / remember_fact, which only
 * write to the assistant's own tables) and every query goes through
 * cdp_aiScopeWhere(), so the rows a tool returns are exactly the rows the
 * asking user would see on the matching list page:
 *
 *   - a module is reachable only with its list permission (customers always
 *     reach their own records, as on the list pages);
 *   - staff see everything, agencies their branch, drivers what is assigned
 *     to them, customers their own.
 *
 * The model chooses which tool to call; this file decides what comes back.
 * A tool never trusts an id or a filter coming from the model beyond using it
 * as a bound parameter inside the user's scope.
 */

require_once __DIR__ . '/ai_assistant.php';

if (!function_exists('cdp_aiModules')) {

    /** The record types the assistant can read, and how each maps to SQL. */
    function cdp_aiModules()
    {
        $order = [
            'id' => 'order_id', 'ref' => "CONCAT(COALESCE(a.order_prefix,''), COALESCE(a.order_no,''))",
            'no' => 'order_no', 'date' => 'order_date', 'datetime' => 'order_datetime', 'status' => 'status_courier',
            'owner' => 'sender_id', 'driver' => 'driver_id', 'agency' => 'agency', 'total' => 'total_order',
            'weight' => 'total_weight', 'invoice' => 'status_invoice', 'mode' => 'order_service_options',
        ];
        $cons = [
            'id' => 'consolidate_id', 'ref' => "CONCAT(COALESCE(a.c_prefix,''), COALESCE(a.c_no,''))",
            'no' => 'c_no', 'date' => 'c_date', 'datetime' => 'order_datetime', 'status' => 'status_courier',
            'owner' => 'sender_id', 'driver' => 'driver_id', 'agency' => 'agency', 'total' => 'total_order',
            'weight' => 'total_weight', 'invoice' => 'status_invoice', 'mode' => 'order_service_options',
        ];
        return [
            'shipments' => $order + [
                'label' => 'Shipments', 'table' => 'cdb_add_order', 'perm' => 'view_shipment_list',
                'where' => 'COALESCE(a.is_pickup,0) = 0 AND COALESCE(a.is_deleted,0) = 0', 'url' => 'courier_view.php?id=',
            ],
            'pickups' => $order + [
                'label' => 'Pickups', 'table' => 'cdb_add_order', 'perm' => 'view_pickup_list',
                'where' => 'a.is_pickup = 1 AND COALESCE(a.is_deleted,0) = 0', 'url' => 'courier_view.php?id=',
            ],
            'packages' => $order + [
                'label' => 'Customer packages', 'table' => 'cdb_customers_packages', 'perm' => 'view_package_list',
                'where' => '1 = 1', 'url' => 'customer_packages_view.php?id=',
            ],
            'consolidations' => $cons + [
                'label' => 'Consolidations (shipments)', 'table' => 'cdb_consolidate', 'perm' => 'view_consolidate_list',
                'where' => '1 = 1', 'url' => 'consolidate_view.php?id=',
            ],
            'package_consolidations' => $cons + [
                'label' => 'Consolidations (packages)', 'table' => 'cdb_consolidate_packages', 'perm' => 'view_consolidate_package_list',
                'where' => '1 = 1', 'url' => 'consolidate_package_view.php?id=',
            ],
            'prealerts' => [
                'label' => 'Pre-alerts', 'table' => 'cdb_pre_alert', 'perm' => 'prealert_list',
                'id' => 'pre_alert_id', 'ref' => 'a.tracking', 'no' => 'tracking', 'date' => 'prealert_date',
                'datetime' => 'prealert_date', 'status' => null, 'owner' => 'customer_id', 'driver' => null,
                'agency' => null, 'total' => 'purchase_price', 'weight' => null, 'invoice' => null, 'mode' => null,
                'where' => '1 = 1', 'url' => 'prealert_list.php?id=',
            ],
        ];
    }

    /** Statuses the business treats as a problem. */
    function cdp_aiIssueStatuses()
    {
        return [12 => 'Rejected', 16 => 'Not picked up', 21 => 'Cancelled', 25 => 'Not shipped / shipping issue'];
    }

    /** Final statuses: the record is finished. */
    function cdp_aiFinalStatuses()
    {
        return [8, 15, 21];
    }

    /** Context passed to every tool: who is asking and what they may see. */
    function cdp_aiContext($user, $userData)
    {
        return [
            'user'     => $user,
            'userData' => $userData,
            'scope'    => cdp_aiScope($user, $userData),
            'uid'      => (int) ($userData->id ?? ($_SESSION['userid'] ?? 0)),
        ];
    }

    /** May the user read this module at all? */
    function cdp_aiModuleAllowed($ctx, $key)
    {
        $mods = cdp_aiModules();
        if (!isset($mods[$key])) {
            return false;
        }
        $m = $mods[$key];
        $scope = $ctx['scope']['type'];
        if ($scope === 'client') {
            return true; // customers always reach their own records
        }
        if ($key === 'prealerts' && in_array($scope, ['driver', 'agency'], true)) {
            return false; // pre-alerts have no driver / branch
        }
        return $ctx['user']->cdp_hasPermission($m['perm']);
    }

    function cdp_aiAllowedModules($ctx)
    {
        $out = [];
        foreach (array_keys(cdp_aiModules()) as $k) {
            if (cdp_aiModuleAllowed($ctx, $k)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    /**
     * The scope condition for module $m on alias `a`, with its bindings.
     * Returns null when the user has no rows in this module at all.
     */
    function cdp_aiScopeWhere($ctx, array $m, array &$bind)
    {
        $s = $ctx['scope'];
        switch ($s['type']) {
            case 'all':
                return '1 = 1';
            case 'client':
                $bind[':scope_uid'] = (int) $s['user_id'];
                return 'a.' . $m['owner'] . ' = :scope_uid';
            case 'driver':
                if (!$m['driver']) {
                    return null;
                }
                $bind[':scope_uid'] = (int) $s['user_id'];
                return 'a.' . $m['driver'] . ' = :scope_uid';
            case 'agency':
                if (!$m['agency'] || (int) $s['branch_id'] <= 0) {
                    return null;
                }
                $bind[':scope_branch'] = (string) (int) $s['branch_id'];
                return 'a.' . $m['agency'] . ' = :scope_branch';
        }
        return null;
    }

    function cdp_aiDate($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }

    function cdp_aiClip($v, $n = 160)
    {
        $v = trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)));
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . '…' : $v;
    }

    function cdp_aiStatusName($row)
    {
        $s = isset($row->status_name) ? (string) $row->status_name : '';
        return $s !== '' ? str_replace('_', ' ', $s) : (isset($row->status_id) && $row->status_id !== null ? 'Status ' . $row->status_id : '');
    }

    function cdp_aiInvoiceName($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = (int) $v;
        return $v === 1 ? 'Paid' : ($v === 3 ? 'Payment to verify' : 'Unpaid');
    }

    function cdp_aiTableExists($table)
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
            $db->bind(':t', $table);
            $r = $db->cdp_registro();
            return $cache[$table] = ($r && (int) $r->c > 0);
        } catch (Throwable $e) {
            return $cache[$table] = false;
        }
    }

    /**
     * Builds the FROM … WHERE for a module with the user's scope and the
     * common filters. Returns [sql, bind] or null when nothing is visible.
     */
    function cdp_aiBaseQuery($ctx, $key, array $f = [])
    {
        $mods = cdp_aiModules();
        if (!isset($mods[$key]) || !cdp_aiModuleAllowed($ctx, $key)) {
            return null;
        }
        $m = $mods[$key];
        $bind = [];
        $scope = cdp_aiScopeWhere($ctx, $m, $bind);
        if ($scope === null) {
            return null;
        }
        $where = ['(' . $m['where'] . ')', '(' . $scope . ')'];

        if (($d = cdp_aiDate($f['date_from'] ?? '')) !== '') {
            $where[] = 'DATE(a.' . $m['date'] . ') >= :df';
            $bind[':df'] = $d;
        }
        if (($d = cdp_aiDate($f['date_to'] ?? '')) !== '') {
            $where[] = 'DATE(a.' . $m['date'] . ') <= :dt';
            $bind[':dt'] = $d;
        }
        if (!empty($f['status']) && $m['status']) {
            $st = $f['status'];
            if (is_numeric($st)) {
                $where[] = 'a.' . $m['status'] . ' = :st';
                $bind[':st'] = (string) (int) $st;
            } else {
                $where[] = "REPLACE(LOWER(st.mod_style), '_', ' ') LIKE :stn";
                $bind[':stn'] = '%' . str_replace('_', ' ', mb_strtolower(trim((string) $st))) . '%';
            }
        }
        if (!empty($f['search'])) {
            $where[] = '(' . $m['ref'] . ' LIKE :q'
                . ($key === 'packages' ? ' OR a.tracking_purchase LIKE :q2' : '')
                . ($key === 'prealerts' ? ' OR a.provider_shop LIKE :q2 OR a.package_description LIKE :q3' : '') . ')';
            $q = '%' . trim((string) $f['search']) . '%';
            $bind[':q'] = $q;
            if ($key === 'packages') {
                $bind[':q2'] = $q;
            }
            if ($key === 'prealerts') {
                $bind[':q2'] = $q;
                $bind[':q3'] = $q;
            }
        }
        if (!empty($f['customer']) && $ctx['scope']['type'] !== 'client') {
            $where[] = "(CONCAT(COALESCE(cu.fname,''),' ',COALESCE(cu.lname,'')) LIKE :cust OR cu.email LIKE :cust2 OR cu.locker LIKE :cust3 OR cu.phone LIKE :cust4)";
            $c = '%' . trim((string) $f['customer']) . '%';
            $bind[':cust'] = $c;
            $bind[':cust2'] = $c;
            $bind[':cust3'] = $c;
            $bind[':cust4'] = $c;
        }
        if (isset($f['payment']) && $f['payment'] !== '' && $m['invoice']) {
            if ($f['payment'] === 'paid') {
                $where[] = 'a.' . $m['invoice'] . ' = 1';
            } elseif ($f['payment'] === 'unpaid') {
                $where[] = '(a.' . $m['invoice'] . ' IN (0, 2) OR a.' . $m['invoice'] . ' IS NULL)';
            } elseif ($f['payment'] === 'verify') {
                $where[] = 'a.' . $m['invoice'] . ' = 3';
            }
        }
        if (!empty($f['open_only']) && $m['status']) {
            $where[] = 'a.' . $m['status'] . ' NOT IN (' . implode(',', cdp_aiFinalStatuses()) . ')';
        }

        $from = $m['table'] . ' a'
            . ' LEFT JOIN cdb_users cu ON cu.id = a.' . $m['owner']
            . ($m['status'] ? ' LEFT JOIN cdb_styles st ON st.id = a.' . $m['status'] : '')
            . ($m['driver'] ? ' LEFT JOIN cdb_users dr ON dr.id = a.' . $m['driver'] : '')
            . ($m['agency'] ? ' LEFT JOIN cdb_branchoffices br ON br.id = a.' . $m['agency'] : '');

        return ['from' => $from, 'where' => implode(' AND ', $where), 'bind' => $bind, 'm' => $m];
    }

    function cdp_aiRun($sql, array $bind)
    {
        $db = new Conexion;
        $db->cdp_query($sql);
        foreach ($bind as $k => $v) {
            $db->bind($k, $v);
        }
        $rows = $db->cdp_registros();
        return is_array($rows) ? $rows : [];
    }

    /** The common SELECT list for record rows. */
    function cdp_aiRowSelect(array $m)
    {
        return 'a.' . $m['id'] . ' AS rid, ' . $m['ref'] . ' AS ref, a.' . $m['date'] . ' AS rdate, '
            . ($m['status'] ? 'a.' . $m['status'] . ' AS status_id, st.mod_style AS status_name, ' : 'NULL AS status_id, NULL AS status_name, ')
            . "TRIM(CONCAT(COALESCE(cu.fname,''),' ',COALESCE(cu.lname,''))) AS customer, "
            . ($m['driver'] ? "TRIM(CONCAT(COALESCE(dr.fname,''),' ',COALESCE(dr.lname,''))) AS driver, " : 'NULL AS driver, ')
            . ($m['agency'] ? 'br.name_branch AS branch, ' : 'NULL AS branch, ')
            . ($m['total'] ? 'a.' . $m['total'] . ' AS total, ' : 'NULL AS total, ')
            . ($m['weight'] ? 'a.' . $m['weight'] . ' AS weight, ' : 'NULL AS weight, ')
            . ($m['invoice'] ? 'a.' . $m['invoice'] . ' AS invoice' : 'NULL AS invoice');
    }

    function cdp_aiRowOut($key, array $m, $r, $ctx)
    {
        $row = [
            'type'     => $key,
            'tracking' => (string) $r->ref,
            'date'     => (string) $r->rdate,
            'status'   => cdp_aiStatusName($r),
        ];
        if ($ctx['scope']['type'] !== 'client' && (string) $r->customer !== '') {
            $row['customer'] = (string) $r->customer;
        }
        if (!empty($r->driver)) {
            $row['driver'] = (string) $r->driver;
        }
        if (!empty($r->branch)) {
            $row['branch'] = (string) $r->branch;
        }
        if ($r->total !== null) {
            $row['total'] = round((float) $r->total, 2);
        }
        if ($r->weight !== null) {
            $row['weight'] = round((float) $r->weight, 2);
        }
        if (($p = cdp_aiInvoiceName($r->invoice)) !== null) {
            $row['payment'] = $p;
        }
        if ($m['url'] && $key !== 'prealerts') {
            $row['link'] = $m['url'] . (int) $r->rid;
        } elseif ($key === 'prealerts') {
            $row['link'] = 'prealert_list.php';
        }
        return $row;
    }

    // -----------------------------------------------------------------------
    // Tool definitions (provider-neutral JSON schema)
    // -----------------------------------------------------------------------

    function cdp_aiToolDefinitions($ctx)
    {
        $modules = cdp_aiAllowedModules($ctx);
        $moduleEnum = $modules ?: ['shipments'];
        $dateProps = [
            'date_from' => ['type' => 'string', 'description' => 'Start date, YYYY-MM-DD (inclusive). Optional.'],
            'date_to'   => ['type' => 'string', 'description' => 'End date, YYYY-MM-DD (inclusive). Optional.'],
        ];
        $tools = [];

        $tools[] = [
            'name' => 'system_overview',
            'description' => 'Counts of every kind of record the user can see (shipments, pickups, customer packages, consolidations, package consolidations, pre-alerts) with totals by status and how many were created in the period. Start here for broad questions like "how are we doing" or "what is happening this week".',
            'parameters' => ['type' => 'object', 'properties' => $dateProps, 'required' => [], 'additionalProperties' => false],
        ];
        $tools[] = [
            'name' => 'count_records',
            'description' => 'Count records of one kind, grouped by status, day, month, branch/agency, driver, customer, shipping mode or payment state, with the sum of their value and weight. Use for "how many", trends and breakdowns.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'module'   => ['type' => 'string', 'enum' => $moduleEnum, 'description' => 'Kind of record.'],
                    'group_by' => ['type' => 'string', 'enum' => ['status', 'day', 'month', 'branch', 'driver', 'customer', 'shipping_mode', 'payment', 'none']],
                    'status'   => ['type' => 'string', 'description' => 'Only this status (name like "In Transit" or numeric id). Optional.'],
                    'payment'  => ['type' => 'string', 'enum' => ['', 'paid', 'unpaid', 'verify']],
                    'customer' => ['type' => 'string', 'description' => 'Customer name, e-mail, phone or locker. Optional.'],
                ] + $dateProps,
                'required' => ['module', 'group_by'],
                'additionalProperties' => false,
            ],
        ];
        $tools[] = [
            'name' => 'list_records',
            'description' => 'List records of one kind with tracking number, date, status, customer, driver, branch, value, weight, payment state and a link to open it. Filters are optional. Max 50 rows.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'module'    => ['type' => 'string', 'enum' => $moduleEnum],
                    'status'    => ['type' => 'string', 'description' => 'Status name or id. Optional.'],
                    'search'    => ['type' => 'string', 'description' => 'Part of a tracking number. Optional.'],
                    'customer'  => ['type' => 'string', 'description' => 'Customer name, e-mail, phone or locker. Optional.'],
                    'payment'   => ['type' => 'string', 'enum' => ['', 'paid', 'unpaid', 'verify']],
                    'open_only' => ['type' => 'boolean', 'description' => 'Only records that are not delivered / picked up / cancelled.'],
                    'sort'      => ['type' => 'string', 'enum' => ['newest', 'oldest', 'highest_value']],
                    'limit'     => ['type' => 'integer', 'description' => '1 to 50, default 20.'],
                ] + $dateProps,
                'required' => ['module'],
                'additionalProperties' => false,
            ],
        ];
        $tools[] = [
            'name' => 'get_record',
            'description' => 'Look up one shipment, package, pickup, consolidation or pre-alert by its tracking number (or part of it) and return its details, its full status/tracking history, who changed it, and the consolidation it belongs to. Use for "where is X", "what happened to X", "history of X".',
            'parameters' => [
                'type' => 'object',
                'properties' => ['tracking' => ['type' => 'string', 'description' => 'Tracking / reference number, full or partial.']],
                'required' => ['tracking'],
                'additionalProperties' => false,
            ],
        ];
        $tools[] = [
            'name' => 'list_issues',
            'description' => 'Find problems: records in a problem status (not shipped / shipping issue, not picked up, cancelled, rejected), records that have not moved for a number of days, and unpaid records older than a number of days. Use for "issues", "problems", "delays", "stuck", "overdue".',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'kind'   => ['type' => 'string', 'enum' => ['all', 'problem_status', 'not_moving', 'unpaid']],
                    'module' => ['type' => 'string', 'enum' => array_merge(['all'], $moduleEnum)],
                    'days'   => ['type' => 'integer', 'description' => 'For not_moving / unpaid: older than this many days. Default 7.'],
                    'limit'  => ['type' => 'integer', 'description' => '1 to 50, default 25.'],
                ],
                'required' => ['kind'],
                'additionalProperties' => false,
            ],
        ];
        $tools[] = [
            'name' => 'recent_activity',
            'description' => 'The audit trail: who did what and when (created, updated, status changes, payments, deliveries, logins). Filter by person, area, record or text. People without the Activity Logs permission only see their own actions.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'person' => ['type' => 'string', 'description' => 'Name or username of who acted. Optional.'],
                    'area'   => ['type' => 'string', 'enum' => array_merge([''], array_keys(cdp_aiActivityModules()))],
                    'record' => ['type' => 'string', 'description' => 'Tracking number or record label. Optional.'],
                    'text'   => ['type' => 'string', 'description' => 'Words in the summary. Optional.'],
                    'verb'   => ['type' => 'string', 'description' => 'create, update, delete, status, assign, deliver, payment, notify, login … Optional.'],
                    'limit'  => ['type' => 'integer', 'description' => '1 to 50, default 25.'],
                ] + $dateProps,
                'required' => [],
                'additionalProperties' => false,
            ],
        ];
        $tools[] = [
            'name' => 'finance_summary',
            'description' => 'Money totals for shipments, packages and consolidations in a period: invoiced, paid, unpaid and awaiting verification, plus the customers who owe the most. Customers get their own bills only.',
            'parameters' => ['type' => 'object', 'properties' => $dateProps, 'required' => [], 'additionalProperties' => false],
        ];
        if ($ctx['scope']['type'] === 'all' && $ctx['user']->cdp_hasPermission('view_client_list')) {
            $tools[] = [
                'name' => 'find_customer',
                'description' => 'Find customers by name, e-mail, phone or locker number and see how many shipments and packages each has and what they owe.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string']],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ];
        }
        $tools[] = [
            'name' => 'list_pages',
            'description' => 'The pages of the system this user can open, with what each is for and its link. Use to answer "where do I…", "how do I…" and to point people to the right screen.',
            'parameters' => ['type' => 'object', 'properties' => new stdClass(), 'required' => [], 'additionalProperties' => false],
        ];
        $tools[] = [
            'name' => 'list_statuses',
            'description' => 'All shipment statuses (id, name, meaning).',
            'parameters' => ['type' => 'object', 'properties' => new stdClass(), 'required' => [], 'additionalProperties' => false],
        ];
        if (cdp_aiSetting('suggest_pages', '1') === '1') {
            $tools[] = [
                'name' => 'suggest_page',
                'description' => 'File an idea for a new page or report when the user asked for something no existing page shows well (you had to combine several lookups, or the answer would be useful as a permanent screen). Administrators review these ideas. Do not tell the user the idea was approved; say it was passed on.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title'             => ['type' => 'string', 'description' => 'Short page name, e.g. "Delayed Shipments Board".'],
                        'purpose'           => ['type' => 'string', 'description' => 'What the page shows and lets people do.'],
                        'audience'          => ['type' => 'string', 'description' => 'Which roles would use it.'],
                        'data_needed'       => ['type' => 'string', 'description' => 'Which records / fields / filters it needs.'],
                        'rationale'         => ['type' => 'string', 'description' => 'Why it is worth building.'],
                        'example_questions' => ['type' => 'string', 'description' => 'The question(s) that led to the idea.'],
                    ],
                    'required' => ['title', 'purpose'],
                    'additionalProperties' => false,
                ],
            ];
        }
        if (cdp_aiCanSeeInsights($ctx['user'])) {
            $tools[] = [
                'name' => 'remember_fact',
                'description' => 'Save a lasting fact about this business that an administrator just taught you (a rule, a definition, a process), so every future conversation knows it. Only when the administrator clearly asks you to remember something.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['fact' => ['type' => 'string']],
                    'required' => ['fact'],
                    'additionalProperties' => false,
                ],
            ];
        }
        return $tools;
    }

    function cdp_aiActivityModules()
    {
        if (!function_exists('cdp_activityModules')) {
            require_once __DIR__ . '/activity_log.php';
        }
        return cdp_activityModules();
    }

    // -----------------------------------------------------------------------
    // Dispatcher
    // -----------------------------------------------------------------------

    /** Runs a tool. Always returns an array (an `error` key on failure). */
    function cdp_aiRunTool($name, $args, $ctx)
    {
        $args = is_array($args) ? $args : [];
        try {
            switch ($name) {
                case 'system_overview': return cdp_aiToolOverview($args, $ctx);
                case 'count_records':   return cdp_aiToolCount($args, $ctx);
                case 'list_records':    return cdp_aiToolList($args, $ctx);
                case 'get_record':      return cdp_aiToolGetRecord($args, $ctx);
                case 'list_issues':     return cdp_aiToolIssues($args, $ctx);
                case 'recent_activity': return cdp_aiToolActivity($args, $ctx);
                case 'finance_summary': return cdp_aiToolFinance($args, $ctx);
                case 'find_customer':   return cdp_aiToolFindCustomer($args, $ctx);
                case 'list_pages':      return ['pages' => cdp_aiPagesFor($ctx['user'])];
                case 'list_statuses':   return cdp_aiToolStatuses();
                case 'suggest_page':    return cdp_aiToolSuggestPage($args, $ctx);
                case 'remember_fact':   return cdp_aiToolRemember($args, $ctx);
            }
            return ['error' => 'Unknown tool ' . $name];
        } catch (Throwable $e) {
            error_log('AI_ASSISTANT_TOOL_FAIL ' . $name . ' ' . $e->getMessage());
            return ['error' => 'The lookup failed on the server. Try a narrower question.'];
        }
    }

    // -----------------------------------------------------------------------
    // Tools
    // -----------------------------------------------------------------------

    function cdp_aiToolOverview(array $a, $ctx)
    {
        $mods = cdp_aiModules();
        $out = ['period' => ['from' => cdp_aiDate($a['date_from'] ?? '') ?: 'all time', 'to' => cdp_aiDate($a['date_to'] ?? '') ?: 'today'], 'modules' => []];
        foreach (cdp_aiAllowedModules($ctx) as $key) {
            $q = cdp_aiBaseQuery($ctx, $key, $a);
            if (!$q) {
                continue;
            }
            $m = $q['m'];
            $tot = cdp_aiRun('SELECT COUNT(*) AS n' . ($m['total'] ? ', SUM(a.' . $m['total'] . ') AS v' : '')
                . ' FROM ' . $q['from'] . ' WHERE ' . $q['where'], $q['bind']);
            $entry = ['label' => $m['label'], 'count' => (int) ($tot[0]->n ?? 0)];
            if ($m['total']) {
                $entry['total_value'] = round((float) ($tot[0]->v ?? 0), 2);
            }
            if ($m['status']) {
                $rows = cdp_aiRun('SELECT st.mod_style AS status_name, a.' . $m['status'] . ' AS status_id, COUNT(*) AS n FROM ' . $q['from']
                    . ' WHERE ' . $q['where'] . ' GROUP BY a.' . $m['status'] . ', st.mod_style ORDER BY n DESC LIMIT 15', $q['bind']);
                $entry['by_status'] = array_map(function ($r) {
                    return ['status' => cdp_aiStatusName($r), 'count' => (int) $r->n];
                }, $rows);
            }
            $out['modules'][$key] = $entry;
        }
        if (!$out['modules']) {
            $out['note'] = 'This user has no records they are allowed to see.';
        }
        return $out;
    }

    function cdp_aiToolCount(array $a, $ctx)
    {
        $key = (string) ($a['module'] ?? '');
        $q = cdp_aiBaseQuery($ctx, $key, $a);
        if (!$q) {
            return ['error' => 'No access to ' . $key . ' for this user.'];
        }
        $m = $q['m'];
        $group = (string) ($a['group_by'] ?? 'none');
        $label = null;
        switch ($group) {
            case 'status':
                if ($m['status']) { $label = "COALESCE(REPLACE(st.mod_style,'_',' '), CONCAT('Status ', a." . $m['status'] . "))"; }
                break;
            case 'day':
                $label = 'DATE(a.' . $m['date'] . ')';
                break;
            case 'month':
                $label = "DATE_FORMAT(a." . $m['date'] . ", '%Y-%m')";
                break;
            case 'branch':
                if ($m['agency']) { $label = "COALESCE(br.name_branch, 'No branch')"; }
                break;
            case 'driver':
                if ($m['driver']) { $label = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(dr.fname,''),' ',COALESCE(dr.lname,''))),''), 'No driver')"; }
                break;
            case 'customer':
                if ($ctx['scope']['type'] !== 'client') { $label = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cu.fname,''),' ',COALESCE(cu.lname,''))),''), 'Unknown')"; }
                break;
            case 'shipping_mode':
                if ($m['mode']) {
                    $q['from'] .= ' LEFT JOIN cdb_shipping_mode sm ON sm.id = a.' . $m['mode'];
                    $label = "COALESCE(sm.ship_mode, 'Not set')";
                }
                break;
            case 'payment':
                if ($m['invoice']) { $label = "CASE WHEN a." . $m['invoice'] . " = 1 THEN 'Paid' WHEN a." . $m['invoice'] . " = 3 THEN 'Payment to verify' ELSE 'Unpaid' END"; }
                break;
        }
        $agg = 'COUNT(*) AS n'
            . ($m['total'] ? ', SUM(a.' . $m['total'] . ') AS v' : '')
            . ($m['weight'] ? ', SUM(a.' . $m['weight'] . ') AS w' : '');
        $fmt = function ($r) use ($m) {
            $o = ['count' => (int) $r->n];
            if ($m['total']) { $o['total_value'] = round((float) $r->v, 2); }
            if ($m['weight']) { $o['total_weight'] = round((float) $r->w, 2); }
            return $o;
        };
        $total = cdp_aiRun('SELECT ' . $agg . ' FROM ' . $q['from'] . ' WHERE ' . $q['where'], $q['bind']);
        $out = ['module' => $m['label'], 'total' => $fmt($total[0])];
        if ($label) {
            $order = in_array($group, ['day', 'month'], true) ? 'g ASC' : 'n DESC';
            $rows = cdp_aiRun('SELECT ' . $label . ' AS g, ' . $agg . ' FROM ' . $q['from'] . ' WHERE ' . $q['where']
                . ' GROUP BY g ORDER BY ' . $order . ' LIMIT 60', $q['bind']);
            $out['groups'] = array_map(function ($r) use ($fmt) {
                return ['group' => (string) $r->g] + $fmt($r);
            }, $rows);
        } elseif ($group !== 'none') {
            $out['note'] = 'Grouping by ' . $group . ' is not available for this kind of record.';
        }
        return $out;
    }

    function cdp_aiToolList(array $a, $ctx)
    {
        $key = (string) ($a['module'] ?? '');
        $q = cdp_aiBaseQuery($ctx, $key, $a);
        if (!$q) {
            return ['error' => 'No access to ' . $key . ' for this user.'];
        }
        $m = $q['m'];
        $limit = max(1, min(50, (int) ($a['limit'] ?? 20)));
        $sort = (string) ($a['sort'] ?? 'newest');
        $order = $sort === 'oldest' ? 'a.' . $m['id'] . ' ASC'
            : ($sort === 'highest_value' && $m['total'] ? 'a.' . $m['total'] . ' DESC' : 'a.' . $m['id'] . ' DESC');
        $count = cdp_aiRun('SELECT COUNT(*) AS n FROM ' . $q['from'] . ' WHERE ' . $q['where'], $q['bind']);
        $rows = cdp_aiRun('SELECT ' . cdp_aiRowSelect($m) . ' FROM ' . $q['from'] . ' WHERE ' . $q['where']
            . ' ORDER BY ' . $order . ' LIMIT ' . $limit, $q['bind']);
        return [
            'module'   => $m['label'],
            'matching' => (int) ($count[0]->n ?? 0),
            'showing'  => count($rows),
            'rows'     => array_map(function ($r) use ($key, $m, $ctx) { return cdp_aiRowOut($key, $m, $r, $ctx); }, $rows),
        ];
    }

    function cdp_aiToolGetRecord(array $a, $ctx)
    {
        $t = trim((string) ($a['tracking'] ?? ''));
        if (mb_strlen($t) < 3) {
            return ['error' => 'Give at least 3 characters of the tracking number.'];
        }
        $matches = [];
        foreach (cdp_aiAllowedModules($ctx) as $key) {
            $q = cdp_aiBaseQuery($ctx, $key, ['search' => $t]);
            if (!$q) {
                continue;
            }
            $m = $q['m'];
            $extra = '';
            if (in_array($key, ['shipments', 'pickups', 'packages'], true)) {
                $extra = ', a.notes AS notes, a.person_receives AS person_receives, a.order_datetime AS created';
            } elseif ($key !== 'prealerts') {
                $extra = ', a.notes AS notes, a.person_receives AS person_receives, a.order_datetime AS created';
            } else {
                $extra = ', a.provider_shop AS shop, a.package_description AS description, a.estimated_date AS eta, a.is_package AS arrived';
            }
            $bind = $q['bind'];
            $bind[':exact'] = $t;
            $rows = cdp_aiRun('SELECT ' . cdp_aiRowSelect($m) . $extra . ', (' . $m['ref'] . ' = :exact) AS is_exact FROM ' . $q['from']
                . ' WHERE ' . $q['where'] . ' ORDER BY is_exact DESC, a.' . $m['id'] . ' DESC LIMIT 5', $bind);
            foreach ($rows as $r) {
                $matches[] = [$key, $m, $r];
            }
        }
        if (!$matches) {
            return ['found' => 0, 'note' => 'No record with that tracking number that this user can see.'];
        }
        $exact = array_values(array_filter($matches, function ($x) { return (int) $x[2]->is_exact === 1; }));
        if (count($exact) !== 1 && count($matches) > 1) {
            return [
                'found'   => count($matches),
                'note'    => 'Several records match; ask which one, or call again with the full number.',
                'matches' => array_map(function ($x) use ($ctx) { return cdp_aiRowOut($x[0], $x[1], $x[2], $ctx); }, array_slice($matches, 0, 15)),
            ];
        }
        list($key, $m, $r) = $exact ? $exact[0] : $matches[0];
        $rec = cdp_aiRowOut($key, $m, $r, $ctx);
        foreach (['notes', 'person_receives', 'created', 'shop', 'description', 'eta'] as $f) {
            if (isset($r->$f) && trim((string) $r->$f) !== '') {
                $rec[$f] = cdp_aiClip($r->$f, 400);
            }
        }
        if ($key === 'prealerts') {
            $rec['arrived'] = !empty($r->arrived);
        }
        $ref = (string) $r->ref;

        // Tracking history (status moves with place and comment).
        $hist = cdp_aiRun("SELECT t.t_date, t.status_courier, st.mod_style AS status_name, t.comments, t.t_city, t.t_dest, o.name_off AS office,
                TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS by_user
            FROM cdb_courier_track t
            LEFT JOIN cdb_styles st ON st.id = t.status_courier
            LEFT JOIN cdb_offices o ON o.id = t.office_id
            LEFT JOIN cdb_users u ON u.id = t.user_id
            WHERE t.order_track = :r ORDER BY t.t_date ASC, t.id ASC LIMIT 60", [':r' => $ref]);
        $rec['tracking_history'] = array_map(function ($h) use ($ctx) {
            $o = ['when' => (string) $h->t_date, 'status' => str_replace('_', ' ', (string) $h->status_name)];
            foreach (['office' => 'office', 't_city' => 'city', 't_dest' => 'destination', 'comments' => 'comment'] as $col => $k) {
                if (trim((string) $h->$col) !== '') {
                    $o[$k] = cdp_aiClip($h->$col, 200);
                }
            }
            if ($ctx['scope']['type'] === 'all' && trim((string) $h->by_user) !== '') {
                $o['by'] = (string) $h->by_user;
            }
            return $o;
        }, $hist);

        // Who did what to it (staff only: names of staff are internal).
        if ($ctx['scope']['type'] === 'all') {
            $uh = cdp_aiRun("SELECT h.date_history, h.action, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS by_user
                FROM cdb_order_user_history h LEFT JOIN cdb_users u ON u.id = h.user_id
                WHERE h.order_track = :r ORDER BY h.date_history ASC LIMIT 40", [':r' => $ref]);
            if ($uh) {
                $rec['change_log'] = array_map(function ($h) {
                    return ['when' => (string) $h->date_history, 'action' => cdp_aiClip($h->action, 200), 'by' => (string) $h->by_user];
                }, $uh);
            }
        }

        // Consolidation it sits in.
        if (in_array($key, ['shipments', 'packages'], true)) {
            $isPkg = $key === 'packages';
            $detail = $isPkg ? 'cdb_consolidate_packages_detail' : 'cdb_consolidate_detail';
            $parent = $isPkg ? 'cdb_consolidate_packages' : 'cdb_consolidate';
            $c = cdp_aiRun("SELECT c.consolidate_id, CONCAT(COALESCE(c.c_prefix,''), COALESCE(c.c_no,'')) AS ref, st.mod_style AS status_name, c.status_courier AS status_id
                FROM $detail d INNER JOIN $parent c ON c.consolidate_id = d.consolidate_id
                LEFT JOIN cdb_styles st ON st.id = c.status_courier
                WHERE d.order_no = (SELECT x.order_no FROM " . $m['table'] . " x WHERE x." . $m['id'] . " = :id LIMIT 1)
                ORDER BY d.detail_id DESC LIMIT 1", [':id' => (int) $r->rid]);
            if ($c) {
                $rec['consolidation'] = [
                    'tracking' => (string) $c[0]->ref,
                    'status'   => cdp_aiStatusName($c[0]),
                    'note'     => 'While inside a consolidation the shipment moves with it; the consolidation status is the current one.',
                ];
                if (cdp_aiModuleAllowed($ctx, $isPkg ? 'package_consolidations' : 'consolidations') && $ctx['scope']['type'] === 'all') {
                    $rec['consolidation']['link'] = ($isPkg ? 'consolidate_package_view.php?id=' : 'consolidate_view.php?id=') . (int) $c[0]->consolidate_id;
                }
            }
        }

        // What is inside a consolidation.
        if (in_array($key, ['consolidations', 'package_consolidations'], true)) {
            $detail = $key === 'package_consolidations' ? 'cdb_consolidate_packages_detail' : 'cdb_consolidate_detail';
            $items = cdp_aiRun("SELECT d.order_no FROM $detail d WHERE d.consolidate_id = :id ORDER BY d.detail_id ASC LIMIT 100", [':id' => (int) $r->rid]);
            $rec['contains'] = array_map(function ($x) { return (string) $x->order_no; }, $items);
            $rec['contains_count'] = count($items);
        }
        return ['found' => 1, 'record' => $rec];
    }

    function cdp_aiToolIssues(array $a, $ctx)
    {
        $kind  = (string) ($a['kind'] ?? 'all');
        $days  = max(1, min(365, (int) ($a['days'] ?? 7)));
        $limit = max(1, min(50, (int) ($a['limit'] ?? 25)));
        $want  = (string) ($a['module'] ?? 'all');
        $mods  = array_values(array_filter(cdp_aiAllowedModules($ctx), function ($k) use ($want) {
            return $k !== 'prealerts' && ($want === 'all' || $want === $k);
        }));
        $cut = date('Y-m-d', time() - 86400 * $days);
        $out = ['days' => $days, 'sections' => []];

        foreach ($mods as $key) {
            $q = cdp_aiBaseQuery($ctx, $key, []);
            if (!$q) {
                continue;
            }
            $m = $q['m'];
            $sections = [];
            if ($kind === 'all' || $kind === 'problem_status') {
                $sections['problem_status'] = 'a.' . $m['status'] . ' IN (' . implode(',', array_keys(cdp_aiIssueStatuses())) . ')';
            }
            if ($kind === 'all' || $kind === 'not_moving') {
                $sections['not_moving'] = 'a.' . $m['status'] . ' NOT IN (' . implode(',', array_merge(cdp_aiFinalStatuses(), array_keys(cdp_aiIssueStatuses()))) . ')'
                    . " AND DATE(a." . $m['date'] . ") <= :cut1"
                    . " AND NOT EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = " . $m['ref'] . " AND DATE(t.t_date) > :cut2)";
            }
            if (($kind === 'all' || $kind === 'unpaid') && $m['invoice']) {
                $sections['unpaid'] = '(a.' . $m['invoice'] . ' IN (0, 2) OR a.' . $m['invoice'] . ' IS NULL) AND a.' . $m['status'] . ' <> 21'
                    . ' AND DATE(a.' . $m['date'] . ') <= :cut1';
            }
            foreach ($sections as $sec => $cond) {
                $bind = $q['bind'];
                if (strpos($cond, ':cut1') !== false) { $bind[':cut1'] = $cut; }
                if (strpos($cond, ':cut2') !== false) { $bind[':cut2'] = $cut; }
                $n = cdp_aiRun('SELECT COUNT(*) AS n FROM ' . $q['from'] . ' WHERE ' . $q['where'] . ' AND ' . $cond, $bind);
                $cnt = (int) ($n[0]->n ?? 0);
                if ($cnt === 0) {
                    continue;
                }
                $rows = cdp_aiRun('SELECT ' . cdp_aiRowSelect($m) . ' FROM ' . $q['from'] . ' WHERE ' . $q['where'] . ' AND ' . $cond
                    . ' ORDER BY a.' . $m['id'] . ' ASC LIMIT ' . $limit, $bind);
                $out['sections'][] = [
                    'module' => $m['label'],
                    'issue'  => ['problem_status' => 'In a problem status', 'not_moving' => 'No movement for ' . $days . '+ days', 'unpaid' => 'Unpaid for ' . $days . '+ days'][$sec],
                    'count'  => $cnt,
                    'oldest_first' => array_map(function ($r) use ($key, $m, $ctx) { return cdp_aiRowOut($key, $m, $r, $ctx); }, $rows),
                ];
            }
        }
        $out['problem_statuses'] = array_values(cdp_aiIssueStatuses());
        if (!$out['sections']) {
            $out['note'] = 'No issues found in what this user can see.';
        }
        return $out;
    }

    function cdp_aiToolActivity(array $a, $ctx)
    {
        if (!cdp_aiTableExists('cdb_activity_log')) {
            return ['error' => 'The activity log is not set up on this system.'];
        }
        $limit = max(1, min(50, (int) ($a['limit'] ?? 25)));
        $where = ['1 = 1'];
        $bind = [];
        $all = $ctx['scope']['type'] === 'all' && $ctx['user']->cdp_hasPermission('view_activity_logs');
        if (!$all) {
            $where[] = 'l.user_id = :me';
            $bind[':me'] = (int) $ctx['uid'];
        }
        if (!empty($a['person']) && $all) {
            $where[] = '(l.actor_name LIKE :p OR l.actor_username LIKE :p2)';
            $bind[':p'] = '%' . $a['person'] . '%';
            $bind[':p2'] = '%' . $a['person'] . '%';
        }
        if (!empty($a['area'])) {
            $where[] = 'l.module = :mod';
            $bind[':mod'] = (string) $a['area'];
        }
        if (!empty($a['verb'])) {
            $where[] = 'l.verb = :verb';
            $bind[':verb'] = (string) $a['verb'];
        } else {
            $where[] = "l.verb <> 'view'"; // page views drown everything else
        }
        if (!empty($a['record'])) {
            $where[] = '(l.entity_label LIKE :rec OR l.entity_id = :rec2 OR l.summary LIKE :rec3)';
            $bind[':rec'] = '%' . $a['record'] . '%';
            $bind[':rec2'] = (string) $a['record'];
            $bind[':rec3'] = '%' . $a['record'] . '%';
        }
        if (!empty($a['text'])) {
            $where[] = 'l.summary LIKE :txt';
            $bind[':txt'] = '%' . $a['text'] . '%';
        }
        if (($d = cdp_aiDate($a['date_from'] ?? '')) !== '') {
            $where[] = 'l.created_at >= :df';
            $bind[':df'] = $d . ' 00:00:00';
        }
        if (($d = cdp_aiDate($a['date_to'] ?? '')) !== '') {
            $where[] = 'l.created_at <= :dt';
            $bind[':dt'] = $d . ' 23:59:59';
        }
        $w = implode(' AND ', $where);
        $n = cdp_aiRun("SELECT COUNT(*) AS n FROM cdb_activity_log l WHERE $w", $bind);
        $rows = cdp_aiRun("SELECT l.created_at, l.actor_name, l.role_name, l.module, l.verb, l.action_label, l.outcome, l.summary, l.entity_label, l.status_name
            FROM cdb_activity_log l WHERE $w ORDER BY l.id DESC LIMIT $limit", $bind);
        return [
            'scope'    => $all ? 'everyone' : 'only this user\'s own actions',
            'matching' => (int) ($n[0]->n ?? 0),
            'entries'  => array_map(function ($r) {
                $o = ['when' => (string) $r->created_at, 'who' => (string) $r->actor_name, 'role' => (string) $r->role_name,
                      'area' => (string) $r->module, 'action' => (string) $r->action_label, 'summary' => cdp_aiClip($r->summary, 240)];
                if ((string) $r->entity_label !== '') { $o['record'] = (string) $r->entity_label; }
                if ((string) $r->status_name !== '') { $o['status'] = (string) $r->status_name; }
                if ((string) $r->outcome !== '' && $r->outcome !== 'success') { $o['outcome'] = (string) $r->outcome; }
                return $o;
            }, $rows),
        ];
    }

    function cdp_aiToolFinance(array $a, $ctx)
    {
        $u = $ctx['user'];
        $client = $ctx['scope']['type'] === 'client';
        if (!$client && !$u->cdp_hasPermission(['view_financial_overview', 'view_receivable_accounts', 'financial_sheet', 'view_dashboard'])) {
            return ['error' => 'This user has no access to finance figures.'];
        }
        $out = ['period' => ['from' => cdp_aiDate($a['date_from'] ?? '') ?: 'all time', 'to' => cdp_aiDate($a['date_to'] ?? '') ?: 'today'], 'by_module' => []];
        $grand = ['invoiced' => 0, 'paid' => 0, 'unpaid' => 0, 'to_verify' => 0];
        $owing = [];
        foreach (['shipments', 'packages', 'consolidations', 'package_consolidations'] as $key) {
            $q = cdp_aiBaseQuery($ctx, $key, $a);
            if (!$q) {
                continue;
            }
            $m = $q['m'];
            $t = 'a.' . $m['total'];
            $i = 'a.' . $m['invoice'];
            $r = cdp_aiRun("SELECT COUNT(*) AS n, SUM($t) AS inv, SUM(CASE WHEN $i = 1 THEN $t ELSE 0 END) AS paid,
                    SUM(CASE WHEN $i = 3 THEN $t ELSE 0 END) AS ver,
                    SUM(CASE WHEN ($i IN (0,2) OR $i IS NULL) THEN $t ELSE 0 END) AS unpaid,
                    SUM(CASE WHEN ($i IN (0,2) OR $i IS NULL) THEN 1 ELSE 0 END) AS unpaid_n
                FROM " . $q['from'] . ' WHERE ' . $q['where'] . ' AND a.' . $m['status'] . ' <> 21', $q['bind']);
            $row = $r[0];
            $e = [
                'records' => (int) $row->n, 'invoiced' => round((float) $row->inv, 2), 'paid' => round((float) $row->paid, 2),
                'unpaid' => round((float) $row->unpaid, 2), 'unpaid_records' => (int) $row->unpaid_n, 'to_verify' => round((float) $row->ver, 2),
            ];
            $out['by_module'][$m['label']] = $e;
            $grand['invoiced'] += $e['invoiced'];
            $grand['paid'] += $e['paid'];
            $grand['unpaid'] += $e['unpaid'];
            $grand['to_verify'] += $e['to_verify'];
            if (!$client) {
                $top = cdp_aiRun("SELECT TRIM(CONCAT(COALESCE(cu.fname,''),' ',COALESCE(cu.lname,''))) AS who, SUM($t) AS owed, COUNT(*) AS n
                    FROM " . $q['from'] . ' WHERE ' . $q['where'] . " AND ($i IN (0,2) OR $i IS NULL) AND a." . $m['status'] . " <> 21
                    GROUP BY a." . $m['owner'] . ", who ORDER BY owed DESC LIMIT 10", $q['bind']);
                foreach ($top as $tr) {
                    $k = (string) $tr->who ?: 'Unknown';
                    $owing[$k] = ['owed' => round(($owing[$k]['owed'] ?? 0) + (float) $tr->owed, 2), 'records' => ($owing[$k]['records'] ?? 0) + (int) $tr->n];
                }
            }
        }
        $out['totals'] = array_map(function ($v) { return round($v, 2); }, $grand);
        if ($owing) {
            uasort($owing, function ($x, $y) { return $y['owed'] <=> $x['owed']; });
            $out['top_owing_customers'] = array_slice(array_map(function ($k, $v) { return ['customer' => $k] + $v; }, array_keys($owing), $owing), 0, 10);
        }
        $out['note'] = 'Amounts are in the company currency. Cancelled records are left out.';
        return $out;
    }

    function cdp_aiToolFindCustomer(array $a, $ctx)
    {
        if ($ctx['scope']['type'] !== 'all' || !$ctx['user']->cdp_hasPermission('view_client_list')) {
            return ['error' => 'This user cannot search customers.'];
        }
        $q = trim((string) ($a['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            return ['error' => 'Give at least 2 characters.'];
        }
        $like = '%' . $q . '%';
        $rows = cdp_aiRun("SELECT u.id, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS name, u.email, u.phone, u.locker, u.userlevel, u.active, u.created,
                (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND COALESCE(o.is_deleted,0) = 0) AS shipments,
                (SELECT COUNT(*) FROM cdb_customers_packages p WHERE p.sender_id = u.id) AS packages,
                (SELECT COALESCE(SUM(o2.total_order),0) FROM cdb_add_order o2 WHERE o2.sender_id = u.id AND (o2.status_invoice IN (0,2) OR o2.status_invoice IS NULL) AND o2.status_courier <> 21 AND COALESCE(o2.is_deleted,0) = 0) AS owed_shipments,
                (SELECT COALESCE(SUM(p2.total_order),0) FROM cdb_customers_packages p2 WHERE p2.sender_id = u.id AND (p2.status_invoice IN (0,2) OR p2.status_invoice IS NULL) AND p2.status_courier <> 21) AS owed_packages
            FROM cdb_users u
            WHERE (CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :a OR u.email LIKE :b OR u.phone LIKE :c OR u.locker LIKE :d OR u.username LIKE :e)
            ORDER BY u.id DESC LIMIT 15", [':a' => $like, ':b' => $like, ':c' => $like, ':d' => $like, ':e' => $like]);
        $out = [];
        foreach ($rows as $r) {
            if (!cdp_roleHasFlag((int) $r->userlevel, 'is_client')) {
                continue;
            }
            $out[] = [
                'name' => (string) $r->name, 'email' => (string) $r->email, 'phone' => (string) $r->phone,
                'locker' => (string) $r->locker, 'active' => (int) $r->active === 1, 'since' => substr((string) $r->created, 0, 10),
                'shipments' => (int) $r->shipments, 'packages' => (int) $r->packages,
                'owes' => round((float) $r->owed_shipments + (float) $r->owed_packages, 2),
                'link' => 'customer_view.php?user=' . (int) $r->id,
            ];
        }
        return ['found' => count($out), 'customers' => $out];
    }

    function cdp_aiToolStatuses()
    {
        $rows = cdp_aiRun("SELECT id, mod_style, detail FROM cdb_styles ORDER BY id ASC", []);
        return ['statuses' => array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => str_replace('_', ' ', (string) $r->mod_style), 'meaning' => (string) $r->detail];
        }, $rows), 'problem_statuses' => cdp_aiIssueStatuses(), 'final_statuses' => cdp_aiFinalStatuses()];
    }

    function cdp_aiToolSuggestPage(array $a, $ctx)
    {
        if (cdp_aiSetting('suggest_pages', '1') !== '1') {
            return ['error' => 'Page ideas are switched off.'];
        }
        $id = cdp_aiIdeaAdd($a, $ctx['uid'], 'chat');
        return $id ? ['ok' => true, 'note' => 'Idea passed on to the administrators.'] : ['error' => 'Could not save the idea.'];
    }

    function cdp_aiToolRemember(array $a, $ctx)
    {
        if (!cdp_aiCanSeeInsights($ctx['user'])) {
            return ['error' => 'Only administrators can teach the assistant.'];
        }
        $id = cdp_aiKnowledgeAdd((string) ($a['fact'] ?? ''), $ctx['uid'], 'chat');
        return $id ? ['ok' => true, 'note' => 'Saved. It is listed under Tools > AI Assistant > What the assistant knows.'] : ['error' => 'Nothing to save.'];
    }
}
