<?php
/**
 * ============================================================
 * Konfigurasi Utama Aplikasi Smart Pallet
 * File ini berisi credentials dan konstanta global.
 * ============================================================
 */

// === Database Configuration ===
define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'smart_pallet');
define('DB_USER',    'root');
define('DB_PASS',    '');  // ⚠️ Kosongkan untuk development, ISI untuk production
define('DB_CHARSET', 'utf8mb4');

// === API Configuration ===
define('API_KEY', 'sp2026_secret_key_change_me'); // 🔑 Ganti dengan key yang lebih aman
define('API_VERSION', '1.0');

// === System Constants ===
define('MIN_THRESHOLD', 1);      // gram
define('MAX_THRESHOLD', 1000);   // gram (kapasitas load cell 1kg)
define('DEFAULT_THRESHOLD', 500); // gram (0.5 kg)
define('COUNTDOWN_DURATION', 2000); // ms (2 detik)
define('MIN_WEIGHT_TRIGGER', 5);    // gram (minimum delta untuk trigger — sinkron dgn .ino)

// === Paths ===
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH', BASE_PATH . '/logs/');

// === Timezone ===
date_default_timezone_set('Asia/Jakarta');
