-- =====================================================================
-- Ayudita - Migración 001: núcleo (usuarios, autenticación, seguridad)
-- MySQL 8 / utf8mb4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ayudita
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ayudita;

-- Configuración global editable desde el panel admin
CREATE TABLE settings (
  setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
  setting_value VARCHAR(500) NOT NULL,
  description   VARCHAR(255) NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuarios (los 3 roles conviven en una sola tabla)
CREATE TABLE users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role            ENUM('admin','provider','client') NOT NULL DEFAULT 'client',
  name            VARCHAR(120)  NOT NULL,
  email           VARCHAR(190)  NOT NULL,
  phone           VARCHAR(30)   NULL,
  password_hash   VARCHAR(255)  NULL,
  auth_provider   ENUM('email','google','apple','phone') NOT NULL DEFAULT 'email',
  avatar_url      VARCHAR(500)  NULL,
  address         VARCHAR(255)  NULL,
  city            VARCHAR(120)  NULL,
  lat             DECIMAL(10,7) NULL,
  lng             DECIMAL(10,7) NULL,
  status          ENUM('active','pending','blocked') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME    NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME      NULL,
  deleted_at      DATETIME      NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status),
  KEY idx_users_geo (lat, lng)
) ENGINE=InnoDB;

-- Refresh tokens (rotativos, almacenados hasheados)
CREATE TABLE refresh_tokens (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64)        NOT NULL,
  user_agent VARCHAR(255)    NULL,
  ip         VARCHAR(45)     NULL,
  expires_at DATETIME        NOT NULL,
  revoked_at DATETIME        NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refresh_hash (token_hash),
  KEY idx_refresh_user (user_id),
  CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recuperación de contraseña
CREATE TABLE password_resets (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_resets_email (email)
) ENGINE=InnoDB;

-- Verificación de email por código
CREATE TABLE email_verifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  code       CHAR(6)   NOT NULL,
  expires_at DATETIME  NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_verif_user (user_id),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rate limiting (ventana fija)
CREATE TABLE rate_limits (
  rate_key     CHAR(40) NOT NULL PRIMARY KEY,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

-- Auditoría de acciones sensibles
CREATE TABLE audit_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NULL,
  action     VARCHAR(80)  NOT NULL,
  entity     VARCHAR(60)  NULL,
  entity_id  BIGINT UNSIGNED NULL,
  ip         VARCHAR(45)  NULL,
  details    JSON         NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB;
