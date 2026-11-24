-- ============================================================
-- Data dummy untuk pengembangan tanpa hardware / Layer 3
-- Jalankan: mysql -u root smart_pallet < database/seed_dummy.sql
-- ============================================================

USE smart_pallet;

INSERT INTO system_state (id, busy) VALUES (1, 0)
ON DUPLICATE KEY UPDATE busy = 0;

INSERT INTO config (id, threshold) VALUES (1, 500.00)
ON DUPLICATE KEY UPDATE threshold = 500.00;

INSERT INTO sorting_history_dashboard (berat, status, sudut_gate, waktu) VALUES
  (310.00, 'OK',     0,  '2026-11-10 10:20:41'),
  (620.00, 'REJECT', 90, '2026-11-10 10:21:03'),
  (450.00, 'OK',     0,  '2026-11-10 10:21:15'),
  (780.00, 'REJECT', 90, '2026-11-10 10:21:30'),
  (210.00, 'OK',     0,  '2026-11-10 10:21:45'),
  (590.00, 'REJECT', 90, '2026-11-10 10:22:00'),
  (340.00, 'OK',     0,  '2026-11-10 10:22:15'),
  (480.00, 'OK',     0,  '2026-11-10 10:22:30'),
  (920.00, 'REJECT', 90, '2026-11-10 10:22:45'),
  (175.00, 'OK',     0,  '2026-11-10 10:23:00'),
  (630.00, 'REJECT', 90, '2026-11-10 10:23:15'),
  (505.00, 'OK',     0,  '2026-11-10 10:23:30');

INSERT INTO config_log (threshold_lama, threshold_baru, waktu) VALUES
  (400.00, 500.00, '2026-11-10 09:00:00');
