<?php
/**
 * Master switch for the financial module (Financial Sheet, Financial Overview,
 * Transactions, Accounts Receivable, global payments, the Accounts Receivable
 * reports and the customer's My Bills).
 *
 * The module is switched OFF for now. Nothing is deleted: the pages, endpoints,
 * tables and ledgers all stay, they are only hidden and refused. To bring the
 * module back, set CDP_FINANCE_ENABLED to true — here, or per environment in
 * config/config.php (a define there wins because it runs first).
 *
 * While it is off:
 *  - every finance permission reads as "not granted", for every role including
 *    the superadmin, so menus, buttons, pages and AJAX endpoints that already
 *    check those permissions disappear or refuse on their own
 *    (User::cdp_hasPermission);
 *  - the dashboards hide their money panels (cdp_canViewMoney);
 *  - "cleared for delivery" — which only a recorded payment could set — no
 *    longer holds packages back: warehouse delivery, the deliver actions and
 *    the pickup-aging clock treat every package as cleared
 *    (cdp_fsClearedSql / cdp_fsIsCleared);
 *  - the Paystack webhook keeps running, so a payment already under way is
 *    still booked.
 */

if (!defined('CDP_FINANCE_ENABLED')) {
    define('CDP_FINANCE_ENABLED', false);
}

if (!function_exists('cdp_financeEnabled')) {

    function cdp_financeEnabled(): bool
    {
        return (bool) CDP_FINANCE_ENABLED;
    }

    /** Permission keys that belong to the financial module. */
    function cdp_financePermissions(): array
    {
        return [
            'financial_sheet',
            'fs_price_items',
            'fs_bill_customer',
            'fs_record_payment',
            'fs_apply_discount',
            'fs_clear_delivery',
            'fs_check_payment',
            'view_financial_overview',
            'view_transactions',
            'view_receivable_accounts',
            'view_global_payments',
            'view_my_bills',
            'pay_my_bills',
            'view_client_balance',
            'view_accounts_summary',
            'view_received_payments',
            'view_module_accounts_receivable_reports',
        ];
    }

    function cdp_isFinancePermission($permission): bool
    {
        return is_string($permission) && in_array($permission, cdp_financePermissions(), true);
    }

    /**
     * SQL condition for "this package may be handed over". With the module on it
     * is the payment clearance flag; with it off, every package qualifies.
     *
     * @param string $alias table alias of cdb_add_order ('' for none)
     */
    function cdp_fsClearedSql(string $alias = ''): string
    {
        if (!cdp_financeEnabled()) {
            return '1 = 1';
        }
        $col = ($alias !== '' ? $alias . '.' : '') . 'fs_cleared_for_delivery';
        return $col . ' = 1';
    }

    /**
     * SQL condition for the Warehouse Delivery queue (list cards + sidebar badge).
     * With the module on the queue is "paid and cleared". With it off, clearance
     * cannot happen, so the queue falls back to "physically here in Ghana":
     * Pending Collection (1), In Warehouse (4), Ready for PickUp (32) and
     * Sorting at Accra Office (33) — otherwise every in-transit package would
     * flood it.
     */
    function cdp_wdQueueSql(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        if (!cdp_financeEnabled()) {
            return $p . 'status_courier IN (1, 4, 32, 33)';
        }
        return $p . 'fs_cleared_for_delivery = 1';
    }

    /** PHP-side twin of cdp_fsClearedSql() for a fetched row value. */
    function cdp_fsIsCleared($flag): bool
    {
        return !cdp_financeEnabled() || (int) $flag === 1;
    }

    /**
     * Stop a finance page when the module is off: the visitor lands on their
     * dashboard instead of an empty or half-working screen.
     */
    function cdp_financeGuardPage(string $home = 'index.php'): void
    {
        if (cdp_financeEnabled()) {
            return;
        }
        if (!headers_sent()) {
            header('Location: ' . $home);
        }
        exit;
    }

    /** Same guard for AJAX endpoints: a JSON refusal. */
    function cdp_financeGuardAjax(): void
    {
        if (cdp_financeEnabled()) {
            return;
        }
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'finance_disabled', 'message' => 'The financial module is currently switched off.']);
        exit;
    }
}
