-- LAFFICO-MPC Loaning System: client portal schema
-- Same tables that `php spark migrate` creates. Use this file only if you want to
-- create them by hand (phpMyAdmin -> select the database -> Import).
-- If you already ran `php spark migrate`, the tables exist and you do not need this.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Client login credentials: one row per client.
-- username = the client's name (e.g. "Reyes, Marilou"), not case-sensitive.
-- password_hash = bcrypt hash; the real password is never stored.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `client_users` (
  `id`                   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id`            INT(11) UNSIGNED NOT NULL,
  `username`             VARCHAR(150)     NOT NULL,
  `password_hash`        VARCHAR(255)     NOT NULL,
  `is_active`            TINYINT(1)       NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1)       NOT NULL DEFAULT 1,
  `failed_attempts`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`         DATETIME         NULL,
  `last_login`           DATETIME         NULL,
  `created_by`           VARCHAR(120)     NULL,
  `created_at`           DATETIME         NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username`  (`username`),
  UNIQUE KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Online payments started from the portal (one row per checkout attempt).
-- status: pending | paid | failed | cancelled
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `online_payments` (
  `id`             INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pay_ref`        VARCHAR(40)      NOT NULL,
  `client_id`      INT(11) UNSIGNED NOT NULL,
  `loan_id`        INT(11) UNSIGNED NOT NULL,
  `amount`         DECIMAL(14,2)    NOT NULL DEFAULT 0.00,
  `driver`         VARCHAR(20)      NOT NULL,
  `provider_id`    VARCHAR(80)      NULL,
  `checkout_url`   TEXT             NULL,
  `status`         VARCHAR(12)      NOT NULL DEFAULT 'pending',
  `transaction_id` INT(11) UNSIGNED NULL,
  `fail_reason`    VARCHAR(255)     NULL,
  `created_at`     DATETIME         NULL,
  `paid_at`        DATETIME         NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pay_ref`     (`pay_ref`),
  KEY `client_id`   (`client_id`),
  KEY `provider_id` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
