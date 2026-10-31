<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * AI Assistant — settings, storage, access rules and learning.
 *
 * The assistant is a chat page (ai_assistant.php) where anyone allowed to use
 * it asks questions about the system: shipments, packages, consolidations,
 * pickups, pre-alerts, issues, tracking history, activity, finance and the
 * pages themselves. A language model (Claude, OpenAI or OpenRouter, chosen on
 * Tools > AI Assistant) answers by calling the read-only tools in
 * helpers/ai_assistant_tools.php. Every tool applies the asking user's own
 * permissions and data scope on the server, exactly like the list pages do:
 *
 *   staff / admin / super admin   every record (module permission required)
 *   agency                        records of their branch office
 *   driver                        records assigned to them
 *   customer                      their own records
 *
 * The model never sees a row the user could not open in the app itself.
 *
 * Learning: every question is logged (cdb_ai_questions) with the tools it
 * used. Administrators see the themes on the settings page and can ask the
 * model to turn them into page ideas (cdb_ai_page_ideas); the model also
 * files an idea by itself when a question has no page that answers it.
 * Administrators can teach the assistant facts about the business
 * (cdb_ai_knowledge); those notes go into every conversation.
 *
 * Tables are created on first use, so no SQL needs to be run by hand.
 * Nothing here may break a page: every database path is wrapped.
 */

require_once __DIR__ . '/rbac.php';

// ---------------------------------------------------------------------------
// Providers and models
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiProviders')) {

    /**
     * The providers an administrator can pick. `models` are suggestions shown
     * in the model picker; "Load models" on the settings page asks the
     * provider for its live list, and any model id can be typed in.
     */
    function cdp_aiProviders()
    {
        return [
            'claude' => [
                'label'   => 'Claude (Anthropic)',
                'key_url' => 'https://console.anthropic.com/settings/keys',
                'key_hint'=> 'sk-ant-...',
                'default' => 'claude-opus-5-5',
                'models'  => [
                    'claude-opus-5-5'   => 'Claude Opus 5.5 (recommended)',
                    'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (faster, lower cost)',
                    'claude-haiku-5-5'  => 'Claude Haiku 5.5 (fastest, lowest cost)',
                    'claude-fable-5-1'  => 'Claude Fable 5.1 (most capable)',
                ],
            ],
            'openai' => [
                'label'   => 'OpenAI',
                'key_url' => 'https://platform.openai.com/api-keys',
                'key_hint'=> 'sk-...',
                'default' => 'gpt-5',
                'models'  => [
                    'gpt-5'      => 'GPT-5',
                    'gpt-5-mini' => 'GPT-5 mini',
                ],
            ],
            'openrouter' => [
                'label'   => 'OpenRouter',
                'key_url' => 'https://openrouter.ai/keys',
                'key_hint'=> 'sk-or-...',
                'default' => 'anthropic/claude-opus-5-5',
                'models'  => [
                    'anthropic/claude-opus-5-5'   => 'Claude Opus 5.5 via OpenRouter',
                    'anthropic/claude-sonnet-5-5' => 'Claude Sonnet 5.5 via OpenRouter',
                    'openai/gpt-5'                => 'GPT-5 via OpenRouter',
                ],
            ],
        ];
    }

    function cdp_aiProviderLabel($provider)
    {
        $p = cdp_aiProviders();
        return isset($p[$provider]) ? $p[$provider]['label'] : (string) $provider;
    }
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiEnsureTables')) {

    /** Creates the assistant's tables when missing. True when they exist. */
    function cdp_aiEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ddl = [
            "CREATE TABLE IF NOT EXISTS cdb_ai_settings (
                setting_key   VARCHAR(64) NOT NULL,
                setting_value TEXT NULL,
                updated_by    INT NULL,
                updated_at    DATETIME NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS cdb_ai_conversations (
                id          INT NOT NULL AUTO_INCREMENT,
                user_id     INT NOT NULL,
                title       VARCHAR(190) NOT NULL DEFAULT 'New chat',
                created_at  DATETIME NOT NULL,
                updated_at  DATETIME NOT NULL,
                is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_user (user_id, is_deleted, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS cdb_ai_messages (
                id              INT NOT NULL AUTO_INCREMENT,
                conversation_id INT NOT NULL,
                role            VARCHAR(16) NOT NULL,
                content         MEDIUMTEXT NULL,
                tools           TEXT NULL,
                provider        VARCHAR(20) NULL,
                model           VARCHAR(120) NULL,
                tokens_in       INT NULL,
                tokens_out      INT NULL,
                created_at      DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_conv (conversation_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS cdb_ai_questions (
                id          INT NOT NULL AUTO_INCREMENT,
                user_id     INT NOT NULL,
                role_id     INT NULL,
                role_name   VARCHAR(100) NULL,
                question    TEXT NOT NULL,
                tools_used  VARCHAR(500) NULL,
                outcome     VARCHAR(20) NOT NULL DEFAULT 'answered',
                created_at  DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS cdb_ai_knowledge (
                id          INT NOT NULL AUTO_INCREMENT,
                note        TEXT NOT NULL,
                source      VARCHAR(20) NOT NULL DEFAULT 'admin',
                active      TINYINT(1) NOT NULL DEFAULT 1,
                created_by  INT NULL,
                created_at  DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

            "CREATE TABLE IF NOT EXISTS cdb_ai_page_ideas (
                id                INT NOT NULL AUTO_INCREMENT,
                title             VARCHAR(190) NOT NULL,
                purpose           TEXT NULL,
                audience          VARCHAR(190) NULL,
                data_needed       TEXT NULL,
                rationale         TEXT NULL,
                example_questions TEXT NULL,
                source            VARCHAR(20) NOT NULL DEFAULT 'chat',
                status            VARCHAR(20) NOT NULL DEFAULT 'new',
                votes             INT NOT NULL DEFAULT 1,
                created_by        INT NULL,
                created_at        DATETIME NOT NULL,
                updated_at        DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];
        try {
            $db = new Conexion;
            foreach ($ddl as $sql) {
                $db->cdp_query($sql);
                if ($db->cdp_execute() === false) {
                    return $ready = false;
                }
            }
            $ready = true;
            cdp_aiTablesExist(true);
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** True when the assistant's tables exist (no DDL). Cached per request. */
    function cdp_aiTablesExist($known = null)
    {
        static $exists = null;
        if ($known !== null) {
            return $exists = (bool) $known;
        }
        if ($exists !== null) {
            return $exists;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS c FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_ai_settings'");
            $r = $db->cdp_registro();
            $exists = ($r && (int) $r->c > 0);
        } catch (Throwable $e) {
            $exists = false;
        }
        return $exists;
    }

    function cdp_aiNow()
    {
        return date('Y-m-d H:i:s');
    }
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiSettings')) {

    function cdp_aiSettingDefaults()
    {
        return [
            'enabled'        => '0',
            'provider'       => 'claude',
            'model'          => 'claude-opus-5-5',
            'key_claude'     => '',
            'key_openai'     => '',
            'key_openrouter' => '',
            'audience'       => 'all',     // all | staff
            'effort'         => 'medium',  // low | medium | high (Claude)
            'max_rounds'     => '8',       // tool rounds per question
            'instructions'   => '',        // extra house rules for the assistant
            'suggest_pages'  => '1',       // let the assistant file page ideas
        ];
    }

    /** All settings with defaults filled in. */
    function cdp_aiSettings($fresh = false)
    {
        static $cache = null;
        if ($cache !== null && !$fresh) {
            return $cache;
        }
        $cache = cdp_aiSettingDefaults();
        // Read-only check: the sidebar asks on every page, and DDL there would
        // take a metadata lock per request. The AI pages create the tables.
        if (!cdp_aiTablesExist()) {
            return $cache;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT setting_key, setting_value FROM cdb_ai_settings");
            foreach ((array) $db->cdp_registros() as $r) {
                $cache[(string) $r->setting_key] = (string) $r->setting_value;
            }
        } catch (Throwable $e) {
            // defaults stand
        }
        return $cache;
    }

    function cdp_aiSetting($key, $default = '')
    {
        $all = cdp_aiSettings();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    function cdp_aiSettingSet($key, $value, $userId = null)
    {
        if (!cdp_aiEnsureTables()) {
            return false;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("INSERT INTO cdb_ai_settings (setting_key, setting_value, updated_by, updated_at)
                VALUES (:k, :v, :u, :t)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
            $db->bind(':k', (string) $key);
            $db->bind(':v', (string) $value);
            $db->bind(':u', $userId === null ? null : (int) $userId);
            $db->bind(':t', cdp_aiNow());
            $ok = $db->cdp_execute() !== false;
            cdp_aiSettings(true);
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** The key for the active provider (or the one named). */
    function cdp_aiApiKey($provider = null)
    {
        $provider = $provider ?: cdp_aiSetting('provider', 'claude');
        return trim((string) cdp_aiSetting('key_' . $provider, ''));
    }

    /** "sk-ant-…a1b2" — enough to recognise a key, never enough to use it. */
    function cdp_aiMaskKey($key)
    {
        $key = (string) $key;
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len <= 10) {
            return str_repeat('•', $len);
        }
        return substr($key, 0, 6) . '…' . substr($key, -4);
    }

    /** Enabled and has a key for the chosen provider. */
    function cdp_aiConfigured()
    {
        return cdp_aiSetting('enabled') === '1' && cdp_aiApiKey() !== '' && trim((string) cdp_aiSetting('model')) !== '';
    }
}

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiCanManage')) {

    /** API keys live in the settings: super admins only (same rule as SMS keys). */
    function cdp_aiCanManage($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        return cdp_roleHasFlag((int) $user->userlevel, 'is_superadmin');
    }

    /** Question themes and page ideas: administrators and super admins. */
    function cdp_aiCanSeeInsights($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        return cdp_roleRankById((int) $user->userlevel) >= 3;
    }

    /** May this user open the chat? */
    function cdp_aiCanUse($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        if (cdp_aiSetting('enabled') !== '1') {
            return false;
        }
        if (cdp_aiSetting('audience', 'all') === 'staff') {
            return cdp_roleRankById((int) $user->userlevel) >= 2;
        }
        return true;
    }

    /**
     * The asking user's data scope, matching the list pages:
     * ['type' => all|agency|driver|client, 'user_id' => int, 'branch_id' => int].
     */
    function cdp_aiScope($user, $userData = null)
    {
        $level = (int) ($user->userlevel ?? 0);
        $uid   = (int) ($_SESSION['userid'] ?? ($user->uid ?? 0));
        if ($userData && isset($userData->id)) {
            $uid = (int) $userData->id;
        }
        if (cdp_roleHasFlag($level, 'is_superadmin') || cdp_roleHasFlag($level, 'is_admin') || cdp_roleHasFlag($level, 'is_staff')) {
            return ['type' => 'all', 'user_id' => $uid, 'branch_id' => 0];
        }
        if (cdp_roleHasFlag($level, 'is_driver')) {
            return ['type' => 'driver', 'user_id' => $uid, 'branch_id' => 0];
        }
        if (cdp_roleHasFlag($level, 'is_agency')) {
            $branch = 0;
            if ($userData && function_exists('cdp_getAgencyBranchIdForUser')) {
                $branch = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
            }
            return ['type' => 'agency', 'user_id' => $uid, 'branch_id' => $branch];
        }
        return ['type' => 'client', 'user_id' => $uid, 'branch_id' => 0];
    }

    function cdp_aiRoleName($roleId)
    {
        $map = cdp_roleFlagsMap();
        return isset($map[(int) $roleId]) ? (string) $map[(int) $roleId]->role_name : 'Role ' . (int) $roleId;
    }
}

// ---------------------------------------------------------------------------
// Pages the assistant can point people to
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiPageCatalogue')) {

    /**
     * The app's main pages: url, title, what it is for, and the permission
     * that opens it ('' = any signed-in user). The assistant only lists the
     * pages the asking user can open, and uses them to say where to go.
     */
    function cdp_aiPageCatalogue()
    {
        return [
            ['index.php', 'Dashboard', 'Home page with the key numbers for your role.', ''],
            ['courier_add.php', 'Create Shipment', 'Register a new air/sea shipment for a customer.', 'add_shipment'],
            ['courier_list.php', 'Shipments List', 'All shipments with status, payment state and actions. Filter by air (?mode=air) or sea (?mode=sea).', 'view_shipment_list'],
            ['courier_view.php?id=', 'Shipment Details', 'One shipment: sender, recipient, items, charges, tracking history.', 'view_shipment_list'],
            ['import_excel_add_courier.php', 'Import Shipments from Excel', 'Create many shipments at once from a spreadsheet.', 'add_multiple_shipments'],
            ['payments_gateways_courier_list.php', 'Shipment Payments', 'Online payments received for shipments.', 'view_payment_shipment_list'],
            ['dashboard_admin_shipments.php', 'Shipments Dashboard', 'Charts and counts for shipments.', 'view_dashboard_ship'],
            ['prealert_list.php', 'Pre-Alerts', 'Packages customers told us are on the way, before they arrive.', 'prealert_list'],
            ['customer_packages_add.php', 'Register Package', 'Receive a customer package into the system.', 'add_package'],
            ['customer_packages_list.php', 'Packages List', 'Customer packages (locker / online shopping) with status and payment.', 'view_package_list'],
            ['customer_packages_view.php?id=', 'Package Details', 'One package with its items, charges and history.', 'view_package_list'],
            ['dashboard_admin_packages_customers.php', 'Packages Dashboard', 'Charts and counts for customer packages.', 'view_dashboard_pack'],
            ['payments_gateways_list.php', 'Package Payments', 'Online payments received for packages.', 'view_payment_list'],
            ['pickup_add_full.php', 'Create Pickup', 'Book a pickup of goods from a customer.', 'add_full_pickup'],
            ['pickup_list.php', 'Pickups List', 'Pickup requests and their status.', 'view_pickup_list'],
            ['pickup_aging.php', 'Pickup Aging', 'Items waiting too long to be collected.', 'view_pickup_aging'],
            ['dashboard_admin_pickup.php', 'Pickups Dashboard', 'Charts and counts for pickups.', 'view_dashboard_pick'],
            ['consolidate_add.php', 'Create Consolidation', 'Group several shipments into one consolidated shipment (container / master airway bill).', 'add_consolidate_shipment'],
            ['consolidate_list.php', 'Consolidations List', 'Consolidated shipments with status.', 'view_consolidate_list'],
            ['consolidate_view.php?id=', 'Consolidation Details', 'One consolidation and the shipments inside it.', 'view_consolidate_list'],
            ['dashboard_admin_consolidated.php', 'Consolidations Dashboard', 'Charts and counts for consolidations.', 'view_dashboard_shipments'],
            ['consolidate_package_add.php', 'Create Package Consolidation', 'Group customer packages into one consolidation.', 'add_consolidate_package'],
            ['consolidate_package_list.php', 'Package Consolidations List', 'Consolidations of customer packages.', 'view_consolidate_package_list'],
            ['consolidate_package_view.php?id=', 'Package Consolidation Details', 'One package consolidation and its packages.', 'view_consolidate_package_list'],
            ['dashboard_admin_package_consolidated.php', 'Package Consolidations Dashboard', 'Charts and counts for package consolidations.', 'view_dashboard_packages'],
            ['warehouse.php', 'Warehouse', 'Receive and check goods in the warehouse.', 'warehouse_view'],
            ['warehouse_delivery.php', 'Warehouse Delivery', 'Hand goods over to customers from the warehouse.', 'view_warehouse_delivery'],
            ['dashboard_warehouse.php', 'Warehouse Dashboard', 'Warehouse counts.', 'view_warehouse_delivery'],
            ['locker_search.php', 'Locker Search', 'Find a customer by locker / virtual address.', 'view_shipment_list'],
            ['reports.php', 'Reports', 'General, agency, customer, driver, employee and pickup reports with Excel/print export.', 'view_general_reports'],
            ['financial_sheet.php', 'Financial Sheet', 'Income per shipment and consolidation.', 'financial_sheet'],
            ['dashboard_admin_account.php', 'Accounts Dashboard', 'Finance overview charts.', 'view_dashboard'],
            ['financial_overview.php', 'Financial Overview', 'Revenue, paid and unpaid totals.', 'view_financial_overview'],
            ['accounts_receivable.php', 'Accounts Receivable', 'Unpaid invoices and customer balances.', 'view_receivable_accounts'],
            ['transactions.php', 'Transactions', 'Payment transactions.', 'view_transactions'],
            ['global_payments_gateways.php', 'Global Payments', 'All online payments across modules.', 'view_global_payments'],
            ['customers_list.php', 'Customers', 'Customer accounts.', 'view_client_list'],
            ['recipients_admin_list.php', 'Recipients', 'Recipients of shipments.', 'view_recipients'],
            ['users_list.php', 'Users', 'Staff and other user accounts.', 'view_user_list'],
            ['drivers_list.php', 'Drivers', 'Driver accounts.', 'view_user_list'],
            ['permissions_list.php', 'Roles & Permissions', 'Who can do what.', 'view_role_assignment'],
            ['push_notifications.php', 'Push Notifications', 'Send notifications to customers.', 'push_notifications'],
            ['activity_logs.php', 'Activity Logs', 'Audit trail of every action by every user.', 'view_activity_logs'],
            ['system_logs.php', 'System Logs', 'Technical errors and events.', 'view_system_logs'],
            ['message_logs.php', 'Message Logs', 'Every SMS, WhatsApp and e-mail sent.', 'view_message_logs'],
            ['staff_productivity.php', 'Staff Productivity', 'Staff activity and active time.', 'view_activity_logs'],
            ['tools.php', 'Settings', 'Company, e-mail, SMS, WhatsApp and system settings.', 'view_tools'],
            ['status_courier_list.php', 'Statuses', 'The shipment statuses used across the system.', 'view_statuses'],
            ['shipping_tariffs_list.php', 'Shipping Tariffs', 'Prices per route and weight.', 'view_shipping_tariffs'],
            ['offices_list.php', 'Offices', 'Offices.', 'view_offices'],
            ['branchoffices_list.php', 'Branch Offices / Agencies', 'Branches and agencies.', 'view_branches'],
            ['courier_add_client.php', 'Book a Shipment', 'Customers book their own shipment.', 'courier_add_client'],
            ['prealert_add.php', 'Add Pre-Alert', 'Customers tell us a package is on the way.', 'prealert_add'],
            ['my_bills.php', 'My Bills', 'A customer\'s own invoices.', 'view_my_bills'],
            ['client_virtual_mail_box_addresses.php', 'My Virtual Addresses', 'A customer\'s overseas shipping addresses.', 'client_virtual_mail_box_addresses'],
            ['track.php', 'Public Tracking', 'Track any shipment by its tracking number.', ''],
            ['ai_assistant.php', 'AI Assistant', 'This assistant.', ''],
        ];
    }

    /** The catalogue filtered to what $user can open. */
    function cdp_aiPagesFor($user)
    {
        $out = [];
        foreach (cdp_aiPageCatalogue() as $p) {
            if ($p[3] === '' || $user->cdp_hasPermission($p[3])) {
                $out[] = ['url' => $p[0], 'title' => $p[1], 'purpose' => $p[2]];
            }
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Conversations
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiConversations')) {

    function cdp_aiConversations($userId, $limit = 60)
    {
        if (!cdp_aiEnsureTables()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT id, title, updated_at FROM cdb_ai_conversations
            WHERE user_id = :u AND is_deleted = 0 ORDER BY updated_at DESC, id DESC LIMIT " . (int) $limit);
        $db->bind(':u', (int) $userId);
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'title' => (string) $r->title, 'updated_at' => (string) $r->updated_at];
        }, (array) $db->cdp_registros());
    }

    /** The conversation row when it belongs to $userId, else null. */
    function cdp_aiConversation($id, $userId)
    {
        if (!cdp_aiEnsureTables()) {
            return null;
        }
        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_ai_conversations WHERE id = :i AND user_id = :u AND is_deleted = 0 LIMIT 1");
        $db->bind(':i', (int) $id);
        $db->bind(':u', (int) $userId);
        $r = $db->cdp_registro();
        return $r ?: null;
    }

    function cdp_aiConversationCreate($userId, $title)
    {
        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_ai_conversations (user_id, title, created_at, updated_at) VALUES (:u, :t, :c, :c2)");
        $db->bind(':u', (int) $userId);
        $db->bind(':t', cdp_aiTitleFrom($title));
        $db->bind(':c', cdp_aiNow());
        $db->bind(':c2', cdp_aiNow());
        $db->cdp_execute();
        $db->cdp_query("SELECT LAST_INSERT_ID() AS id");
        $r = $db->cdp_registro();
        return $r ? (int) $r->id : 0;
    }

    function cdp_aiTitleFrom($text)
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($t === '') {
            return 'New chat';
        }
        return mb_strlen($t) > 70 ? mb_substr($t, 0, 67) . '…' : $t;
    }

    function cdp_aiConversationTouch($id)
    {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_ai_conversations SET updated_at = :t WHERE id = :i");
        $db->bind(':t', cdp_aiNow());
        $db->bind(':i', (int) $id);
        $db->cdp_execute();
    }

    function cdp_aiConversationDelete($id, $userId)
    {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_ai_conversations SET is_deleted = 1 WHERE id = :i AND user_id = :u");
        $db->bind(':i', (int) $id);
        $db->bind(':u', (int) $userId);
        return $db->cdp_execute() !== false;
    }

    function cdp_aiConversationRename($id, $userId, $title)
    {
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_ai_conversations SET title = :t WHERE id = :i AND user_id = :u");
        $db->bind(':t', cdp_aiTitleFrom($title));
        $db->bind(':i', (int) $id);
        $db->bind(':u', (int) $userId);
        return $db->cdp_execute() !== false;
    }

    function cdp_aiMessages($conversationId, $limit = 200)
    {
        $db = new Conexion;
        $db->cdp_query("SELECT id, role, content, tools, model, created_at FROM cdb_ai_messages
            WHERE conversation_id = :c ORDER BY id ASC LIMIT " . (int) $limit);
        $db->bind(':c', (int) $conversationId);
        return array_map(function ($r) {
            return [
                'id'         => (int) $r->id,
                'role'       => (string) $r->role,
                'content'    => (string) $r->content,
                'tools'      => $r->tools ? (json_decode($r->tools, true) ?: []) : [],
                'model'      => (string) $r->model,
                'created_at' => (string) $r->created_at,
            ];
        }, (array) $db->cdp_registros());
    }

    function cdp_aiMessageAdd($conversationId, $role, $content, array $extra = [])
    {
        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_ai_messages
            (conversation_id, role, content, tools, provider, model, tokens_in, tokens_out, created_at)
            VALUES (:c, :r, :t, :tools, :p, :m, :ti, :to, :at)");
        $db->bind(':c', (int) $conversationId);
        $db->bind(':r', (string) $role);
        $db->bind(':t', (string) $content);
        $db->bind(':tools', !empty($extra['tools']) ? json_encode($extra['tools'], JSON_UNESCAPED_UNICODE) : null);
        $db->bind(':p', $extra['provider'] ?? null);
        $db->bind(':m', $extra['model'] ?? null);
        $db->bind(':ti', isset($extra['tokens_in']) ? (int) $extra['tokens_in'] : null);
        $db->bind(':to', isset($extra['tokens_out']) ? (int) $extra['tokens_out'] : null);
        $db->bind(':at', cdp_aiNow());
        $db->cdp_execute();
    }
}

// ---------------------------------------------------------------------------
// Learning: question log, knowledge notes, page ideas
// ---------------------------------------------------------------------------

if (!function_exists('cdp_aiLogQuestion')) {

    function cdp_aiLogQuestion($user, $question, array $toolsUsed, $outcome)
    {
        try {
            if (!cdp_aiEnsureTables()) {
                return;
            }
            $db = new Conexion;
            $db->cdp_query("INSERT INTO cdb_ai_questions (user_id, role_id, role_name, question, tools_used, outcome, created_at)
                VALUES (:u, :r, :rn, :q, :t, :o, :c)");
            $db->bind(':u', (int) ($_SESSION['userid'] ?? 0));
            $db->bind(':r', (int) $user->userlevel);
            $db->bind(':rn', mb_substr(cdp_aiRoleName($user->userlevel), 0, 100));
            $db->bind(':q', mb_substr((string) $question, 0, 4000));
            $db->bind(':t', mb_substr(implode(',', array_unique($toolsUsed)), 0, 500));
            $db->bind(':o', (string) $outcome);
            $db->bind(':c', cdp_aiNow());
            $db->cdp_execute();
        } catch (Throwable $e) {
            error_log('AI_ASSISTANT_LOG_FAIL ' . $e->getMessage());
        }
    }

    function cdp_aiKnowledge($activeOnly = true)
    {
        if (!cdp_aiEnsureTables()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT k.id, k.note, k.source, k.active, k.created_at,
                TRIM(CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(u.lname, ''))) AS author
            FROM cdb_ai_knowledge k LEFT JOIN cdb_users u ON u.id = k.created_by "
            . ($activeOnly ? "WHERE k.active = 1 " : "") . "ORDER BY k.id ASC LIMIT 300");
        return array_map(function ($r) {
            return [
                'id' => (int) $r->id, 'note' => (string) $r->note, 'source' => (string) $r->source,
                'active' => (int) $r->active, 'created_at' => (string) $r->created_at, 'author' => (string) $r->author,
            ];
        }, (array) $db->cdp_registros());
    }

    function cdp_aiKnowledgeAdd($note, $userId, $source = 'admin')
    {
        $note = trim((string) $note);
        if ($note === '' || !cdp_aiEnsureTables()) {
            return 0;
        }
        $db = new Conexion;
        $db->cdp_query("INSERT INTO cdb_ai_knowledge (note, source, active, created_by, created_at) VALUES (:n, :s, 1, :u, :c)");
        $db->bind(':n', mb_substr($note, 0, 2000));
        $db->bind(':s', (string) $source);
        $db->bind(':u', (int) $userId);
        $db->bind(':c', cdp_aiNow());
        $db->cdp_execute();
        $db->cdp_query("SELECT LAST_INSERT_ID() AS id");
        $r = $db->cdp_registro();
        return $r ? (int) $r->id : 0;
    }

    function cdp_aiKnowledgeDelete($id)
    {
        $db = new Conexion;
        $db->cdp_query("DELETE FROM cdb_ai_knowledge WHERE id = :i");
        $db->bind(':i', (int) $id);
        return $db->cdp_execute() !== false;
    }

    function cdp_aiIdeaStatuses()
    {
        return ['new' => 'New', 'planned' => 'Planned', 'built' => 'Built', 'dismissed' => 'Dismissed'];
    }

    /**
     * Files a page idea. When an idea with the same title already exists the
     * vote count goes up instead, so a gap many people hit rises to the top.
     */
    function cdp_aiIdeaAdd(array $idea, $userId, $source = 'chat')
    {
        $title = trim((string) ($idea['title'] ?? ''));
        if ($title === '' || !cdp_aiEnsureTables()) {
            return 0;
        }
        $title = mb_substr($title, 0, 190);
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM cdb_ai_page_ideas WHERE LOWER(title) = LOWER(:t) LIMIT 1");
        $db->bind(':t', $title);
        $hit = $db->cdp_registro();
        $examples = $idea['example_questions'] ?? '';
        if (is_array($examples)) {
            $examples = implode("\n", array_map('strval', $examples));
        }
        if ($hit) {
            $db->cdp_query("UPDATE cdb_ai_page_ideas SET votes = votes + 1, updated_at = :u,
                example_questions = CONCAT(COALESCE(example_questions, ''), :ex) WHERE id = :i");
            $db->bind(':u', cdp_aiNow());
            $db->bind(':ex', $examples !== '' ? "\n" . mb_substr((string) $examples, 0, 500) : '');
            $db->bind(':i', (int) $hit->id);
            $db->cdp_execute();
            return (int) $hit->id;
        }
        $db->cdp_query("INSERT INTO cdb_ai_page_ideas
            (title, purpose, audience, data_needed, rationale, example_questions, source, status, votes, created_by, created_at, updated_at)
            VALUES (:t, :p, :a, :d, :r, :e, :s, 'new', 1, :u, :c, :c2)");
        $db->bind(':t', $title);
        $db->bind(':p', mb_substr((string) ($idea['purpose'] ?? ''), 0, 2000));
        $db->bind(':a', mb_substr((string) ($idea['audience'] ?? ''), 0, 190));
        $db->bind(':d', mb_substr((string) ($idea['data_needed'] ?? ''), 0, 2000));
        $db->bind(':r', mb_substr((string) ($idea['rationale'] ?? ''), 0, 2000));
        $db->bind(':e', mb_substr((string) $examples, 0, 2000));
        $db->bind(':s', (string) $source);
        $db->bind(':u', (int) $userId);
        $db->bind(':c', cdp_aiNow());
        $db->bind(':c2', cdp_aiNow());
        $db->cdp_execute();
        $db->cdp_query("SELECT LAST_INSERT_ID() AS id");
        $r = $db->cdp_registro();
        return $r ? (int) $r->id : 0;
    }

    function cdp_aiIdeas()
    {
        if (!cdp_aiEnsureTables()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_ai_page_ideas
            ORDER BY FIELD(status, 'new', 'planned', 'built', 'dismissed'), votes DESC, id DESC LIMIT 200");
        return array_map(function ($r) {
            return [
                'id' => (int) $r->id, 'title' => (string) $r->title, 'purpose' => (string) $r->purpose,
                'audience' => (string) $r->audience, 'data_needed' => (string) $r->data_needed,
                'rationale' => (string) $r->rationale, 'example_questions' => (string) $r->example_questions,
                'source' => (string) $r->source, 'status' => (string) $r->status, 'votes' => (int) $r->votes,
                'created_at' => (string) $r->created_at,
            ];
        }, (array) $db->cdp_registros());
    }

    function cdp_aiIdeaSetStatus($id, $status)
    {
        if (!isset(cdp_aiIdeaStatuses()[$status])) {
            return false;
        }
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_ai_page_ideas SET status = :s, updated_at = :u WHERE id = :i");
        $db->bind(':s', $status);
        $db->bind(':u', cdp_aiNow());
        $db->bind(':i', (int) $id);
        return $db->cdp_execute() !== false;
    }

    function cdp_aiIdeaDelete($id)
    {
        $db = new Conexion;
        $db->cdp_query("DELETE FROM cdb_ai_page_ideas WHERE id = :i");
        $db->bind(':i', (int) $id);
        return $db->cdp_execute() !== false;
    }

    /**
     * Question statistics for the insights card: totals, by role, by tool
     * (= by area of the system), questions nothing could answer, and the
     * most recent questions.
     */
    function cdp_aiQuestionStats($days = 30)
    {
        $out = ['total' => 0, 'users' => 0, 'by_role' => [], 'by_area' => [], 'unanswered' => [], 'recent' => []];
        if (!cdp_aiEnsureTables()) {
            return $out;
        }
        $since = date('Y-m-d H:i:s', time() - 86400 * max(1, (int) $days));
        $db = new Conexion;

        $db->cdp_query("SELECT COUNT(*) AS n, COUNT(DISTINCT user_id) AS u FROM cdb_ai_questions WHERE created_at >= :s");
        $db->bind(':s', $since);
        $r = $db->cdp_registro();
        $out['total'] = $r ? (int) $r->n : 0;
        $out['users'] = $r ? (int) $r->u : 0;

        $db->cdp_query("SELECT role_name, COUNT(*) AS n FROM cdb_ai_questions WHERE created_at >= :s GROUP BY role_name ORDER BY n DESC LIMIT 10");
        $db->bind(':s', $since);
        foreach ((array) $db->cdp_registros() as $r) {
            $out['by_role'][] = ['label' => (string) ($r->role_name ?: 'Unknown'), 'count' => (int) $r->n];
        }

        $db->cdp_query("SELECT tools_used FROM cdb_ai_questions WHERE created_at >= :s ORDER BY id DESC LIMIT 2000");
        $db->bind(':s', $since);
        $areas = [];
        $labels = cdp_aiToolAreaLabels();
        foreach ((array) $db->cdp_registros() as $r) {
            $seen = [];
            foreach (array_filter(explode(',', (string) $r->tools_used)) as $t) {
                $area = $labels[$t] ?? null;
                if ($area && !isset($seen[$area])) {
                    $seen[$area] = true;
                    $areas[$area] = ($areas[$area] ?? 0) + 1;
                }
            }
            if (!$seen) {
                $areas['General / how-to'] = ($areas['General / how-to'] ?? 0) + 1;
            }
        }
        arsort($areas);
        foreach ($areas as $label => $n) {
            $out['by_area'][] = ['label' => $label, 'count' => $n];
        }

        $db->cdp_query("SELECT question, role_name, outcome, created_at FROM cdb_ai_questions
            WHERE created_at >= :s AND outcome <> 'answered' ORDER BY id DESC LIMIT 15");
        $db->bind(':s', $since);
        foreach ((array) $db->cdp_registros() as $r) {
            $out['unanswered'][] = ['question' => (string) $r->question, 'role' => (string) $r->role_name, 'outcome' => (string) $r->outcome, 'at' => (string) $r->created_at];
        }

        $db->cdp_query("SELECT question, role_name, created_at FROM cdb_ai_questions WHERE created_at >= :s ORDER BY id DESC LIMIT 25");
        $db->bind(':s', $since);
        foreach ((array) $db->cdp_registros() as $r) {
            $out['recent'][] = ['question' => (string) $r->question, 'role' => (string) $r->role_name, 'at' => (string) $r->created_at];
        }
        return $out;
    }

    /** Questions for the page-idea analysis: the latest N, newest first. */
    function cdp_aiRecentQuestions($limit = 300)
    {
        if (!cdp_aiEnsureTables()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT question, role_name, tools_used, outcome FROM cdb_ai_questions ORDER BY id DESC LIMIT " . (int) $limit);
        return (array) $db->cdp_registros();
    }

    /** Tool name → the area of the system it covers (for the insights card). */
    function cdp_aiToolAreaLabels()
    {
        return [
            'system_overview'   => 'Overview & counts',
            'count_records'     => 'Overview & counts',
            'list_records'      => 'Record lists',
            'find_record'       => 'Tracking & record details',
            'get_record'        => 'Tracking & record details',
            'list_issues'       => 'Issues & exceptions',
            'recent_activity'   => 'Activity & history',
            'finance_summary'   => 'Finance & payments',
            'find_customer'     => 'Customers',
            'list_pages'        => 'Pages & how-to',
            'list_statuses'     => 'Statuses',
            'suggest_page'      => 'Page ideas',
            'remember_fact'     => 'Teaching the assistant',
        ];
    }
}
