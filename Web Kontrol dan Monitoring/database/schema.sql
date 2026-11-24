-- ============================================================
-- Smart Pallet Weight Sorting Gate — Layer 4 Dashboard
-- Skema tabel milik Layer 4 (prefix/berbeda dari tabel Layer 3
-- agar tidak bentrok dengan sorting_history & system_settings).
-- Jalankan: mysql -u root smart_pallet < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_pallet
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE smart_pallet;

-- Riwayat sortir yang dibaca dashboard
CREATE TABLE IF NOT EXISTS sorting_history_dashboard (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  berat       DECIMAL(8,2) NOT NULL COMMENT 'satuan gram',
  status      ENUM('OK','REJECT') NOT NULL,
  sudut_gate  SMALLINT NOT NULL COMMENT '0 (OK) atau 45 derajat (REJECT)',
  waktu       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Status antrean (satu baris)
CREATE TABLE IF NOT EXISTS system_state (
  id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  busy        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = IDLE, 1 = BUSY',
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO system_state (id, busy)
SELECT 1, 0
WHERE NOT EXISTS (SELECT 1 FROM system_state WHERE id = 1);

-- Konfigurasi threshold (satu baris)
CREATE TABLE IF NOT EXISTS config (
  id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  threshold   DECIMAL(8,2) NOT NULL DEFAULT 500.00 COMMENT 'gram (0.5 kg)',
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO config (id, threshold)
SELECT 1, 500.00
WHERE NOT EXISTS (SELECT 1 FROM config WHERE id = 1);

-- Log perubahan threshold
CREATE TABLE IF NOT EXISTS config_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  threshold_lama  DECIMAL(8,2) NOT NULL,
  threshold_baru  DECIMAL(8,2) NOT NULL,
  waktu           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
