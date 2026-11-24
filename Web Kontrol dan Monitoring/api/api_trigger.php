<?php
/**
 * ============================================================
 * API Endpoint: api_trigger.php
 * Menangani sinyal IR Sensor dari ESP32
 * 
 * Request:  POST JSON {"ir_status": "ON"} atau {"ir_status": "FINISH"}
 * Response: JSON dengan perintah countdown atau reset
 * ============================================================
 */

require_once __DIR__ . '/api_helper.php';

// === 1. Validasi Method & API Key ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Method not allowed. Use POST.', 'METHOD_NOT_ALLOWED', 405);
}
validateApiKey();

// === 2. Parse Input ===
$input = getJsonInput();
logApiActivity('api_trigger', $input);

// === 3. Validasi Payload ===
if (!isset($input['ir_status']) || !is_string($input['ir_status'])) {
    sendJsonError('Missing or invalid "ir_status" field', 'INVALID_PAYLOAD', 400);
}

$irStatus = strtoupper(trim($input['ir_status']));

// Validasi nilai ir_status
if (!in_array($irStatus, ['ON', 'FINISH'], true)) {
    sendJsonError(
        'Invalid ir_status value. Allowed: "ON" or "FINISH"',
        'INVALID_IR_STATUS',
        400
    );
}

// === 4. Cek Status Sistem ===
$state = getSystemState();

// === 5. Proses Berdasarkan Status IR ===
if ($irStatus === 'ON') {
    // --- BARANG TERDETEKSI IR (Sebelum Gate) ---
    
    // Pastikan sistem sedang BUSY (harusnya iya, karena baru saja sortir)
    if ((int) $state['busy'] !== 1) {
        sendJsonError(
            'IR triggered but system is not BUSY. Possible desync.',
            'STATE_MISMATCH',
            409
        );
    }
    
    // Kirim perintah countdown ke ESP32
    sendJsonSuccess([
        'ir_status'       => 'ON',
        'start_countdown' => true,
        'time'            => COUNTDOWN_DURATION, // 2000 ms
        'message'         => 'Start countdown timer. Wait for item to pass gate.',
    ]);
    
} elseif ($irStatus === 'FINISH') {
    // --- COUNTDOWN SELESAI, RESET SISTEM ---
    
    // Pastikan sistem masih BUSY
    if ((int) $state['busy'] !== 1) {
        sendJsonError(
            'FINISH received but system already IDLE. Possible duplicate request.',
            'STATE_MISMATCH',
            409
        );
    }
    
    // Reset status sistem ke IDLE
    try {
        setSystemBusy(false);
    } catch (Throwable $e) {
        sendJsonError('Database error: ' . $e->getMessage(), 'DB_ERROR', 500);
    }
    
    // Kirim perintah reset ke ESP32
    sendJsonSuccess([
        'ir_status'      => 'FINISH',
        'gate_angle'     => 0,        // Reset gate ke netral
        'motor_action'   => 'OFF',    // Matikan relay motor
        'led'            => 'OFF',    // Matikan LED indikator
        'system_state'   => 'IDLE',
        'message'        => 'System reset to IDLE. Ready for next item.',
    ]);
}