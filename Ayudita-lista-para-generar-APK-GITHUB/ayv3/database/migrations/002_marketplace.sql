-- =====================================================================
-- Ayudita - Migración 002: marketplace (categorías, prestadores, trabajos)
-- =====================================================================
USE ayudita;

CREATE TABLE categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(90)  NOT NULL,
  icon        VARCHAR(16)  NOT NULL DEFAULT '💼',
  description VARCHAR(255) NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  deleted_at  DATETIME     NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

-- Perfil extendido de prestador (1:1 con users)
CREATE TABLE provider_profiles (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id          BIGINT UNSIGNED NOT NULL,
  bio              TEXT          NULL,
  experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rate_hour        DECIMAL(10,2) NOT NULL DEFAULT 0,
  rate_day         DECIMAL(10,2) NOT NULL DEFAULT 0,
  radius_km        SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  available        TINYINT(1)    NOT NULL DEFAULT 1,
  verified         TINYINT(1)    NOT NULL DEFAULT 0,
  balance          DECIMAL(12,2) NOT NULL DEFAULT 0,
  rating_avg       DECIMAL(3,2)  NOT NULL DEFAULT 0,
  rating_count     INT UNSIGNED  NOT NULL DEFAULT 0,
  jobs_done        INT UNSIGNED  NOT NULL DEFAULT 0,
  deleted_at       DATETIME      NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_user (user_id),
  KEY idx_provider_rating (rating_avg),
  KEY idx_provider_available (available, verified),
  CONSTRAINT fk_provider_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_categories (
  provider_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED    NOT NULL,
  PRIMARY KEY (provider_id, category_id),
  CONSTRAINT fk_pc_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_photos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  url         VARCHAR(500) NOT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_photos_provider (provider_id),
  CONSTRAINT fk_photos_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE provider_certificates (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  url         VARCHAR(500) NULL,
  verified    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_certs_provider (provider_id),
  CONSTRAINT fk_certs_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Disponibilidad semanal (0 = domingo ... 6 = sábado)
CREATE TABLE provider_availability (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  weekday     TINYINT UNSIGNED NOT NULL,
  from_time   TIME NOT NULL,
  to_time     TIME NOT NULL,
  KEY idx_avail_provider (provider_id),
  CONSTRAINT fk_avail_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE,
  CONSTRAINT chk_avail_weekday CHECK (weekday BETWEEN 0 AND 6)
) ENGINE=InnoDB;

CREATE TABLE favorites (
  user_id     BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, provider_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contrataciones
CREATE TABLE bookings (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code           CHAR(10)        NOT NULL,
  client_id      BIGINT UNSIGNED NOT NULL,
  provider_id    BIGINT UNSIGNED NOT NULL,
  category_id    INT UNSIGNED    NOT NULL,
  unit           ENUM('hour','day','week','month') NOT NULL,
  quantity       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  rate           DECIMAL(10,2)   NOT NULL,
  amount_total   DECIMAL(12,2)   NOT NULL,
  description    TEXT            NULL,
  address        VARCHAR(255)    NULL,
  lat            DECIMAL(10,7)   NULL,
  lng            DECIMAL(10,7)   NULL,
  start_at       DATETIME        NOT NULL,
  end_at         DATETIME        NOT NULL,
  status         ENUM('pending','accepted','on_way','in_progress','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','paid','released','refunded') NOT NULL DEFAULT 'unpaid',
  cancel_reason  VARCHAR(255)    NULL,
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bookings_code (code),
  KEY idx_bookings_client (client_id),
  KEY idx_bookings_provider (provider_id),
  KEY idx_bookings_status (status),
  KEY idx_bookings_start (start_at),
  CONSTRAINT fk_book_client   FOREIGN KEY (client_id)   REFERENCES users(id),
  CONSTRAINT fk_book_provider FOREIGN KEY (provider_id) REFERENCES provider_profiles(id),
  CONSTRAINT fk_book_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Extensiones de contratación
CREATE TABLE booking_extensions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id     BIGINT UNSIGNED NOT NULL,
  extra_quantity SMALLINT UNSIGNED NOT NULL,
  amount         DECIMAL(12,2)   NOT NULL,
  new_end_at     DATETIME        NOT NULL,
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ext_booking (booking_id),
  CONSTRAINT fk_ext_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de estados (trazabilidad completa)
CREATE TABLE booking_status_history (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  status     VARCHAR(20)     NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_booking (booking_id),
  CONSTRAINT fk_hist_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Calificaciones bidireccionales (cliente <-> prestador)
CREATE TABLE ratings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  rater_id   BIGINT UNSIGNED NOT NULL,
  rated_id   BIGINT UNSIGNED NOT NULL,
  stars      TINYINT UNSIGNED NOT NULL,
  comment    VARCHAR(500)    NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rating_once (booking_id, rater_id),
  KEY idx_ratings_rated (rated_id),
  CONSTRAINT fk_rating_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT chk_rating_stars CHECK (stars BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE disputes (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id  BIGINT UNSIGNED NOT NULL,
  opened_by   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(500)    NOT NULL,
  status      ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
  resolution  VARCHAR(500)    NULL,
  resolved_by BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME        NULL,
  KEY idx_disputes_status (status),
  CONSTRAINT fk_disp_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Trigger: mantiene actualizado el promedio de calificación del prestador
DELIMITER //
CREATE TRIGGER trg_ratings_after_insert
AFTER INSERT ON ratings
FOR EACH ROW
BEGIN
  UPDATE provider_profiles pp
  JOIN users u ON u.id = pp.user_id
  SET pp.rating_avg = (
        SELECT AVG(r.stars) FROM ratings r WHERE r.rated_id = u.id
      ),
      pp.rating_count = (
        SELECT COUNT(*) FROM ratings r WHERE r.rated_id = u.id
      )
  WHERE u.id = NEW.rated_id;
END //
DELIMITER ;
