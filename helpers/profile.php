<?php
/**
 * Shared helpers for profile self-service (avatar, ID document, WhatsApp
 * number) used by the customer profile page, the employee profile page and the
 * forced "account setup" modals.
 */

if (!function_exists('cdp_profileHistoryLog')) {

    /**
     * cdb_profile_update_history did not exist on every environment, and the
     * legacy endpoints crashed (fatal on a failed prepare) AFTER the avatar had
     * already been saved — the classic "error shown but the change went
     * through" symptom. Create it on demand and never let logging break a save.
     */
    function cdp_profileHistoryLog($userId, $updatedBy, $previous, $remarks)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_profile_update_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                update_by INT NOT NULL,
                prev_document VARCHAR(350) NULL,
                remarks VARCHAR(255) NULL,
                datetime DATETIME NOT NULL,
                INDEX idx_profile_history_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->cdp_execute();

            $db->cdp_query("INSERT INTO cdb_profile_update_history (user_id, update_by, prev_document, remarks, datetime)
                VALUES (:user_id, :update_by, :prev, :remarks, :dt)");
            $db->bind(':user_id', (int) $userId);
            $db->bind(':update_by', (int) $updatedBy);
            $db->bind(':prev', (string) $previous);
            $db->bind(':remarks', (string) $remarks);
            $db->bind(':dt', date('Y-m-d H:i:s'));
            $db->cdp_execute();
        } catch (Throwable $e) {
            error_log('[profile] history log failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate and store one uploaded image. Returns ['ok'=>bool, 'path'=>..,
     * 'error'=>..]. $path is relative to assets/ (e.g. "uploads/users/x.jpg"),
     * which is the format every avatar/document display path understands via
     * cdp_avatarUrl().
     */
    function cdp_profileStoreImage(array $file, $subdir, $namePrefix, $maxBytes = 5242880)
    {
        if (empty($file['name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'No file was selected.';
            if (isset($file['error']) && in_array((int) $file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $msg = 'The file is too large.';
            }
            return ['ok' => false, 'error' => $msg];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }
        if ((int) $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'The image must be under ' . round($maxBytes / 1048576) . 'MB.'];
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ($file['type'] ?? '');
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Only JPEG, PNG, GIF or WEBP images are allowed.'];
        }

        $subdir = trim($subdir, '/');
        $dir = dirname(__DIR__) . '/assets/uploads/' . ($subdir !== '' ? $subdir . '/' : '');
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Upload folder is not writable.'];
        }

        $name = $namePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
        }

        return ['ok' => true, 'path' => 'uploads/' . ($subdir !== '' ? $subdir . '/' : '') . $name];
    }

    /** Mark one step of the customer onboarding checklist as done. */
    function cdp_profileMarkStep($userId, $column)
    {
        if (!in_array($column, ['update_address', 'update_phone', 'update_document'], true)) {
            return;
        }
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM cdb_user_details_update_check WHERE user_id = :u LIMIT 1");
        $db->bind(':u', (int) $userId);
        if ($db->cdp_registro()) {
            $db->cdp_query("UPDATE cdb_user_details_update_check SET $column = 1 WHERE user_id = :u");
        } else {
            // A missing row means "nothing pending" for legacy accounts, so a new
            // row must not accidentally start forcing the other steps.
            $db->cdp_query("INSERT INTO cdb_user_details_update_check (user_id, update_address, update_phone, update_document)
                VALUES (:u, 1, 1, 1)");
        }
        $db->bind(':u', (int) $userId);
        $db->cdp_execute();
    }

    /**
     * Record that $phone was confirmed by $userId (by WhatsApp code, or saved
     * directly while OTP is switched off). Separate from the onboarding
     * checklist so a "Confirmed" badge can never appear for a number nobody
     * actually confirmed. Table created on demand.
     */
    function cdp_profileMarkPhoneVerified($userId, $phone, $method)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_profile_phone_verified (
                user_id INT NOT NULL PRIMARY KEY,
                phone VARCHAR(32) NOT NULL,
                method VARCHAR(20) NOT NULL,
                verified_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->cdp_execute();

            $db->cdp_query("INSERT INTO cdb_profile_phone_verified (user_id, phone, method, verified_at)
                VALUES (:u, :p, :m, NOW())
                ON DUPLICATE KEY UPDATE phone = VALUES(phone), method = VALUES(method), verified_at = NOW()");
            $db->bind(':u', (int) $userId);
            $db->bind(':p', (string) $phone);
            $db->bind(':m', (string) $method);
            $db->cdp_execute();
        } catch (Throwable $e) {
            error_log('[profile] phone verified log failed: ' . $e->getMessage());
        }
    }

    /** True when the number currently on file is the one the customer confirmed. */
    function cdp_profilePhoneVerified($userId, $currentPhone)
    {
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT phone FROM cdb_profile_phone_verified WHERE user_id = :u LIMIT 1");
            $db->bind(':u', (int) $userId);
            $row = $db->cdp_registro();
            if (!$row) {
                return false;
            }
            return preg_replace('/\D+/', '', (string) $row->phone) === preg_replace('/\D+/', '', (string) $currentPhone)
                && preg_replace('/\D+/', '', (string) $currentPhone) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Digits-only, E.164-ish normalisation: "+233 24 123 4567" -> "+233241234567". */
    function cdp_profileNormalizePhone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits === '' ? '' : '+' . $digits;
    }

    /** Can $viewer edit the profile of $targetId? Self, admin, or the given permission. */
    function cdp_profileCanEdit($viewer, $targetId, $permission)
    {
        if ((int) $targetId <= 0) {
            return false;
        }
        if ((int) $targetId === (int) $viewer->uid) {
            return true;
        }
        if ($viewer->cdp_is_Admin()) {
            return true;
        }
        $viewer->cdp_getUserPermissions();
        return $viewer->cdp_hasPermission($permission);
    }
}
