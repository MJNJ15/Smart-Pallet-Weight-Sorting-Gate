<?php
/**
 * ============================================================
 * API Helper Functions
 * Fungsi-fungsi khusus untuk Layer 3 (API Endpoints)
 * ============================================================
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Koneksi Database (khusus API, terpisah dari dashboard)
 */
function getDbConnection(): PDO
{
    static $dbh = null;
    if ($dbh === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $dbh = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sendJsonError('Database connection failed: ' . $e->getMessage(), 'DB_ERROR', 500);
        }
    }
    return $dbh;
}

/**
 * Kirim response JSON sukses
 */
function sendJsonSuccess(array $data = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Kirim response JSON error
 */
function sendJsonError(string $message, string $code = 'ERROR', int $httpCode = 400): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'code'    => $code,
    ]);
    exit;
}

/**
 * Validasi API Key dari header atau query parameter
 */
function validateApiKey(): void
{
    // Cek dari header X-API-Key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    
    if (empty($apiKey) || !hash_equals(API_KEY, (string) $apiKey)) {
        sendJsonError('Invalid or missing API key', 'UNAUTHORIZED', 401);
    }
}

/**
 * Baca dan parse JSON body dari request
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        sendJsonError('Empty request body', 'INVALID_PAYLOAD', 400);
    }
    
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonError('Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON', 400);
    }
    
    return $data;
}

/**
 * Ambil threshold aktif dari database
 */
function getActiveThreshold(): float
{
    $dbh = getDbConnection();
    $stmt = $dbh->prepare('SELECT threshold FROM config WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? (float) $row['threshold'] : (float) DEFAULT_THRESHOLD;
}

/**
 * Ambil status sistem (busy/idle)
 */
function getSystemState(): array
{
    $dbh = getDbConnection();
    $stmt = $dbh->prepare('SELECT busy, updated_at FROM system_state WHERE id = 1');
    $stmt->execute();
    return $stmt->fetch() ?: ['busy' => 0, 'updated_at' => null];
}

/**
 * Update status sistem
 */
function setSystemBusy(bool $busy): bool
{
    $dbh = getDbConnection();
    $stmt = $dbh->prepare('UPDATE system_state SET busy = ? WHERE id = 1');
    return $stmt->execute([(int) $busy]);
}

/**
 * Catat riwayat sortir ke database
 */
function insertSortingHistory(float $berat, string $status, int $sudutGate): bool
{
    $dbh = getDbConnection();
    $stmt = $dbh->prepare(
        'INSERT INTO sorting_history_dashboard (berat, status, sudut_gate, waktu)
         VALUES (?, ?, ?, NOW())'
    );
    return $stmt->execute([$berat, $status, $sudutGate]);
}

/**
 * Log aktivitas API (sederhana, bisa dikembangkan ke file logging)
 */
function logApiActivity(string $endpoint, array $data): void
{
    // Bisa dikembangkan untuk menulis ke file log
    // error_log("[" . date('Y-m-d H:i:s') . "] $endpoint: " . json_encode($data));
}