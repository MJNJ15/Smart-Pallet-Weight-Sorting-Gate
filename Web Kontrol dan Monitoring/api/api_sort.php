<?php
/**
 * ============================================================
 * API Endpoint: api_sort.php
 * Menangani penimbangan barang dari ESP32 (Layer 1)
 * 
 * Request:  POST JSON {"weight": 620.5}
 * Response: JSON dengan keputusan sortir & perintah aktuator
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
logApiActivity('api_sort', $input);

// === 3. Validasi Payload ===
if (!isset($input['weight']) || !is_numeric($input['weight'])) {
    sendJsonError('Missing or invalid "weight" field', 'INVALID_PAYLOAD', 400);
}

$weight = round((float) $input['weight'], 2);

// Validasi range berat (0 - 1000g sesuai kapasitas load cell)
if ($weight < 0 || $weight > MAX_THRESHOLD) {
    sendJsonError(
        "Weight out of range (0 - " . MAX_THRESHOLD . "g). Received: {$weight}g",
        'WEIGHT_OUT_OF_RANGE',
        400
    );
}

// Validasi minimum trigger (untuk menghindari noise)
if ($weight < MIN_WEIGHT_TRIGGER) {
    sendJsonError(
        "Weight below minimum trigger (" . MIN_WEIGHT_TRIGGER . "g). Possible noise.",
        'BELOW_MINIMUM_TRIGGER',
        400
    );
}

// === 4. Cek Status Sistem (Mutual Exclusion) ===
$state = getSystemState();
if ((int) $state['busy'] === 1) {
    sendJsonError(
        'System is BUSY. Wait until current sorting cycle completes.',
        'SYSTEM_BUSY',
        409 // Conflict
    );
}

// === 5. Eksekusi Logika Bisnis (Rule Engine) ===
$threshold = getActiveThreshold();

// Tentukan status sortir dan sudut gate
if ($weight >= $threshold) {
    $status     = 'REJECT';
    $gateAngle  = 45;  // Gate aktif, belokkan barang ke jalur reject (45°)
} else {
    $status     = 'OK';
    $gateAngle  = 0;   // Gate netral, barang lurus ke jalur OK
}

// === 6. Simpan ke Database ===
try {
    $dbh = getDbConnection();
    $dbh->beginTransaction();
    
    // Insert riwayat sortir
    $stmtInsert = $dbh->prepare(
        'INSERT INTO sorting_history_dashboard (berat, status, sudut_gate, waktu)
         VALUES (?, ?, ?, NOW())'
    );
    $stmtInsert->execute([$weight, $status, $gateAngle]);
    
    // Update status sistem menjadi BUSY
    $stmtState = $dbh->prepare('UPDATE system_state SET busy = 1 WHERE id = 1');
    $stmtState->execute();
    
    $dbh->commit();
} catch (Throwable $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    sendJsonError('Database error: ' . $e->getMessage(), 'DB_ERROR', 500);
}

// === 7. Kirim Response ke ESP32 ===
sendJsonSuccess([
    'status'         => $status,
    'weight'         => $weight,
    'threshold'      => $threshold,
    'gate_angle'     => $gateAngle,
    'pallet_action'  => 'tilt',     // ESP32 miringkan palet
    'motor_action'   => 'ON',       // ESP32 nyalakan relay motor
    'led'            => 'ON',       // ESP32 nyalakan LED indikator
    'message'        => "Sorting initiated: {$status}",
]);