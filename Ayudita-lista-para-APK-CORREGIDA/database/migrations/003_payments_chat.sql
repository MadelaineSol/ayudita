-- =====================================================================
-- Ayudita - Migración 003: pagos, retiros, chat, notificaciones, banners
-- =====================================================================
USE ayudita;

-- Pagos del cliente hacia la plataforma
CREATE TABLE payments (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id         BIGINT UNSIGNED NOT NULL,
  payer_id           BIGINT UNSIGNED NOT NULL,
  amount             DECIMAL(12,2)   NOT NULL,
  commission_percent DECIMAL(5,2)    NOT NULL,
  commission_amount  DECIMAL(12,2)   NOT NULL,
  tax_percent        DECIMAL(5,2)    NOT NULL DEFAULT 0,
  tax_amount         DECIMAL(12,2)   NOT NULL DEFAULT 0,
  net_amount         DECIMAL(12,2)   NOT NULL,
  method             ENUM('card','transfer','mercadopago','wallet') NOT NULL,
  status             ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  external_ref       VARCHAR(120)    NULL,
  paid_at            DATETIME        NULL,
  created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payments_booking (booking_id),
  KEY idx_payments_status (status),
  KEY idx_payments_paid (paid_at),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_pay_payer   FOREIGN KEY (payer_id)   REFERENCES users(id)
) ENGINE=InnoDB;

-- Liberación de fondos al prestador (aprobada por un administrador)
CREATE TABLE payouts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  payment_id  BIGINT UNSIGNED NOT NULL,
  amount      DECIMAL(12,2)   NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME        NULL,
  notes       VARCHAR(255)    NULL,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payouts_provider (provider_id),
  KEY idx_payouts_status (status),
  CONSTRAINT fk_payout_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id),
  CONSTRAINT fk_payout_payment  FOREIGN KEY (payment_id)  REFERENCES payments(id)
) ENGINE=InnoDB;

-- Solicitudes de retiro de saldo del prestador
CREATE TABLE withdrawals (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id  BIGINT UNSIGNED NOT NULL,
  amount       DECIMAL(12,2)   NOT NULL,
  bank_info    JSON            NULL,
  status       ENUM('requested','approved','rejected','paid') NOT NULL DEFAULT 'requested',
  processed_by BIGINT UNSIGNED NULL,
  processed_at DATETIME        NULL,
  notes        VARCHAR(255)    NULL,
  created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_withdrawals_provider (provider_id),
  KEY idx_withdrawals_status (status),
  CONSTRAINT fk_wd_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id)
) ENGINE=InnoDB;

-- Conversaciones de chat (entre dos usuarios, opcionalmente ligadas a un trabajo)
CREATE TABLE conversations (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NULL,
  user_one   BIGINT UNSIGNED NOT NULL,
  user_two   BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conversation (user_one, user_two, booking_id),
  CONSTRAINT fk_conv_one FOREIGN KEY (user_one) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_two FOREIGN KEY (user_two) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id       BIGINT UNSIGNED NOT NULL,
  type            ENUM('text','image','location','file') NOT NULL DEFAULT 'text',
  body            TEXT          NULL,
  file_url        VARCHAR(500)  NULL,
  lat             DECIMAL(10,7) NULL,
  lng             DECIMAL(10,7) NULL,
  read_at         DATETIME      NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_messages_conv (conversation_id, id),
  CONSTRAINT fk_msg_conv   FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Notificaciones internas de la app
CREATE TABLE notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  type       VARCHAR(40)  NOT NULL,
  title      VARCHAR(150) NOT NULL,
  body       VARCHAR(500) NOT NULL,
  data       JSON         NULL,
  read_at    DATETIME     NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, read_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Banners promocionales administrables
CREATE TABLE banners (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(120) NOT NULL,
  image_url  VARCHAR(500) NULL,
  link       VARCHAR(500) NULL,
  emoji      VARCHAR(16)  NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
