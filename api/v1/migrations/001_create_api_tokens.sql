-- ─────────────────────────────────────────────────────────────────────────────
-- Migration 001: Create cdb_api_tokens table
-- Run once against the production database before enabling the REST API.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `cdb_api_tokens` (
    `id`           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`      INT               NOT NULL COMMENT 'References cdb_users.id',
    `token_hash`   VARCHAR(128)      NOT NULL COMMENT 'SHA-256 hash of the raw token',
    `name`         VARCHAR(100)      NULL     COMMENT 'Optional human-readable label',
    `expires_at`   DATETIME          NOT NULL,
    `last_used_at` DATETIME          NULL,
    `created_at`   DATETIME          NOT NULL,
    `revoked_at`   DATETIME          NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash`  (`token_hash`),
    KEY `idx_user_id`           (`user_id`),
    KEY `idx_expires`           (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stateless API Bearer tokens for REST API authentication';
