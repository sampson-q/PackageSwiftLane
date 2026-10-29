-- One-time deployment action after normal API and OTP table initialization.
-- Run during maintenance, after backup and staging validation. All users must sign in again.
START TRANSACTION;
UPDATE cdb_api_tokens SET revoked_at = NOW() WHERE revoked_at IS NULL;
UPDATE cdb_auth_trusted_devices SET revoked_at = NOW() WHERE revoked_at IS NULL;
UPDATE cdb_auth_otp_challenges SET status = 'replaced', updated_at = NOW() WHERE status = 'pending';
UPDATE cdb_password_reset_sessions SET consumed_at = NOW() WHERE consumed_at IS NULL;
COMMIT;
