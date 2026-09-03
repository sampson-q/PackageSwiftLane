<?php
/**
 * System-wide One-Time Password (OTP) switch.
 *
 * cdb_settings.active_otp (TINYINT 1/0, default 1). When it is 0 the system
 * does not require a one-time code anywhere it is optional: login, sign-up
 * email verification, admin "add client" verification and WhatsApp number
 * changes on the profile. Password reset is deliberately NOT covered — the
 * emailed code is the only proof that the requester owns the mailbox, so it
 * stays on regardless of this switch.
 *
 * The column is created on demand so an environment that has not run the SQL
 * migration keeps working (OTP stays ON until the switch is turned off).
 */

if (!function_exists('cdp_otpEnabled')) {

    function cdp_otpColumnExists()
    {
        if (isset($GLOBALS['cdp_otp_column_exists'])) {
            return $GLOBALS['cdp_otp_column_exists'];
        }
        $db = new Conexion;
        $db->cdp_query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_settings' AND COLUMN_NAME = 'active_otp'");
        $row = $db->cdp_registro();
        $GLOBALS['cdp_otp_column_exists'] = ($row && (int) $row->c > 0);
        return $GLOBALS['cdp_otp_column_exists'];
    }

    /** Adds the column when missing. Returns true when it is present afterwards. */
    function cdp_otpEnsureColumn()
    {
        if (cdp_otpColumnExists()) {
            return true;
        }
        $db = new Conexion;
        $db->cdp_query("ALTER TABLE cdb_settings ADD COLUMN active_otp TINYINT(1) NOT NULL DEFAULT 1");
        $ok = $db->cdp_execute();
        if ($ok) {
            $GLOBALS['cdp_otp_column_exists'] = true;
        }
        return $ok ? true : false;
    }

    /** True when the system requires OTP codes (default when unset). */
    function cdp_otpEnabled()
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $enabled = true;
        if (!cdp_otpColumnExists()) {
            return $enabled;
        }
        $db = new Conexion;
        $db->cdp_query("SELECT active_otp FROM cdb_settings LIMIT 1");
        $row = $db->cdp_registro();
        if ($row && isset($row->active_otp)) {
            $enabled = ((int) $row->active_otp === 1);
        }
        return $enabled;
    }

    /** Persist the switch. Returns true on success. */
    function cdp_otpSetEnabled($on)
    {
        if (!cdp_otpEnsureColumn()) {
            return false;
        }
        $db = new Conexion;
        $db->cdp_query("UPDATE cdb_settings SET active_otp = :v");
        $db->bind(':v', $on ? 1 : 0);
        return $db->cdp_execute() ? true : false;
    }
}
