/**
 * ============================================================
 * Smart Pallet Weight Sorting Gate - ESP32 Edge Device
 * Arsitektur: Thin Client (Server-Centric Logic)
 * ============================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>
#include <HX711.h> // Menggunakan library HX711 standar (bogde)

// ============================================================
// KONFIGURASI WIFI & SERVER
// ============================================================
const char* WIFI_SSID     = "MSI ya MSI";        // Ganti dengan SSID WiFi Anda
const char* WIFI_PASSWORD = "bayardulu2000";    // Ganti dengan password WiFi Anda
const char* SERVER_IP     = "192.168.137.1";        // Ganti dengan IP laptop Anda
const char* API_KEY       = "sp2026_secret_key_change_me"; 

String URL_API_SORT    = "http://" + String(SERVER_IP) + "/projectIOT/api/api_sort.php";
String URL_API_TRIGGER = "http://" + String(SERVER_IP) + "/projectIOT/api/api_trigger.php";

// ============================================================
// KONFIGURASI PIN GPIO
// ============================================================
const int HX711_DT         = 27;
const int HX711_SCK        = 14;
const int SERVO_PALLET_PIN = 13;
const int SERVO_GATE_PIN   = 4;
const int RELAY_PIN        = 16;
const int IR_SENSOR_PIN    = 15;
const int LED_PIN          = 12;

// ============================================================
// KONFIGURASI SISTEM
// ============================================================
const float MIN_WEIGHT_TRIGGER_GRAM = 5.0; // ⚠️ Server masih 50g (config.php) — kiriman 5-49g akan ditolak server!
const unsigned long COUNTDOWN_DURATION_MS = 2000;

// ============================================================
// KONFIGURASI STABILISASI BEBAN
// Sistem tidak langsung aktif saat beban terdeteksi, melainkan
// menunggu pembacaan stabil (sampel berurutan dalam toleransi)
// selama jendela waktu minimum sebelum memulai sortir.
// ============================================================
const unsigned long STABILITY_WINDOW_MS   = 1500; // Waktu tunggu minimal sejak beban terdeteksi
const int          STABILITY_MIN_SAMPLES  = 5;    // Jumlah pembacaan stabil berurutan yang diperlukan
const float        STABILITY_TOLERANCE_GRAM = 8.0; // Beda maks antar pembacaan dianggap stabil
const float        STABILITY_HYSTERESIS_GRAM = 15.0; // Jika beban turun lebih dari ini, proses dibatalkan
const unsigned long MAX_STABILIZE_MS      = 10000; // Batas maks menunggu stabil (anti stuck)

// ============================================================
// WATCHDOG ANTI-STUCK (STATE BUSY)
// Jika BUSY lebih dari 10 detik tanpa trigger IR (mis. sensor
// tidak sejajar / ESP32 restart di tengah siklus), ESP32
// otomatis mengirim FINISH ke server agar kembali ke IDLE.
// ============================================================
const unsigned long MAX_BUSY_WATCHDOG_MS = 10000;
const unsigned long WATCHDOG_RETRY_INTERVAL_MS = 2000; // Jeda antar percobaan reset ulang

// ============================================================
// URUTAN AKTUATOR (PALLET vs GATE/RELAY)
// 1. Beban diterima -> palet TIDAK langsung bergerak.
// 2. Relay + gate digeser lebih dulu (fase pertama).
// 3. Setelah jeda, baru palet turun; tunggu 1 detik,
//    lalu kembali ke posisi awal (90°).
// ============================================================
const unsigned long PALLET_START_DELAY_MS = 1500; // Jeda setelah gate+relay sebelum palet bergerak
const unsigned long PALLET_HOLD_MS = 1000;        // Lama palet bertahan di posisi turun (TILT)

const int SERVO_PALLET_UP   = 90; // Posisi awal palet (istirahat/menunggu beban)
const int SERVO_PALLET_TILT = 45;  // Saat beban diterima, palet menurun ke 45°
const int SERVO_GATE_NEUTRAL = 0;
const int SERVO_GATE_ACTIVE  = 45;

// ============================================================
// STATE MACHINE
// ============================================================
enum SystemState {
  STATE_IDLE,
  STATE_STABILIZING,
  STATE_BUSY,
  STATE_COUNTDOWN
};

SystemState currentState = STATE_IDLE;

// ============================================================
// VARIABEL GLOBAL
// ============================================================
HX711 scale;
float calibration_factor = 1000.0; // Faktor kalibrasi
String inputString = "";           // Untuk perintah Serial Monitor

float baselineWeight = 0.0;
float currentWeight = 0.0;

Servo servoPallet;
Servo servoGate;

unsigned long countdownStartTime = 0;
unsigned long countdownDurationMs = COUNTDOWN_DURATION_MS; // Durasi bisa di-override oleh server
bool countdownActive = false;
bool irTriggered = false;

// Variabel state STABILIZING
unsigned long stabStartTime = 0;
int          stableSamples = 0;
float        lastSampleWeight = 0.0;

// Watchdog anti-stuck (STATE BUSY)
unsigned long busyStartTime = 0;
unsigned long lastWatchdogTry = 0;

// ============================================================
// FUNGSI: KONEKSI WIFI
// ============================================================
void connectWiFi() {
  Serial.println("Menghubungkan ke WiFi...");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[OK] WiFi Terhubung!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n[ERROR] Gagal konek WiFi. Restart...");
    delay(3000);
    ESP.restart();
  }
}

// ============================================================
// FUNGSI: INISIALISASI HARDWARE
// ============================================================
void initHardware() {
  servoPallet.attach(SERVO_PALLET_PIN);
  servoGate.attach(SERVO_GATE_PIN);
  servoPallet.write(SERVO_PALLET_UP);
  servoGate.write(SERVO_GATE_NEUTRAL);
  
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  
  pinMode(IR_SENSOR_PIN, INPUT);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);
  
  Serial.println("[OK] Hardware terinisialisasi");
}

// ============================================================
// FUNGSI: INISIALISASI LOAD CELL
// ============================================================
void initLoadCell() {
  Serial.println("Menginisialisasi Load Cell...");
  scale.begin(HX711_DT, HX711_SCK);
  
  Serial.println("Menstabilkan sensor...");
  delay(2000);
  
  scale.tare(); // Tare otomatis saat pertama kali nyala
  scale.set_scale(calibration_factor);
  
  baselineWeight = scale.get_units();
  Serial.print("Baseline awal: ");
  Serial.print(baselineWeight, 2);
  Serial.println(" g");
  
  Serial.println("[OK] Load Cell siap");
}

// ============================================================
// FUNGSI: BACA LOAD CELL
// ============================================================
float readStableWeight() {
  if (scale.is_ready()) {
    currentWeight = scale.get_units();
    return currentWeight;
  }
  return currentWeight;
}

// ============================================================
// FUNGSI: CEK DELTA TRIGGER
// ============================================================
bool checkDeltaTrigger() {
  float weight = readStableWeight();
  float delta = weight - baselineWeight;
  
  Serial.print("Raw Weight: ");
  Serial.print(weight, 2);
  Serial.print(" | Baseline: ");
  Serial.print(baselineWeight, 2);
  Serial.print(" | Delta: ");
  Serial.println(delta, 2);
  
  return (delta >= MIN_WEIGHT_TRIGGER_GRAM);
}

// ============================================================
// FUNGSI: KIRIM HTTP POST KE API
// ============================================================
String sendHttpPost(String url, String jsonPayload) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[ERROR] WiFi tidak terhubung");
    return "";
  }
  
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", API_KEY);
  http.setTimeout(5000);
  
  Serial.print("POST ke: ");
  Serial.println(url);
  
  int httpCode = http.POST(jsonPayload);
  String response = "";
  
  if (httpCode > 0) {
    if (httpCode == HTTP_CODE_OK) {
      response = http.getString();
      Serial.print("Response: ");
      Serial.println(response);
    } else {
      Serial.print("HTTP Error: ");
      Serial.println(http.errorToString(httpCode));
    }
  } else {
    Serial.print("HTTP Failed: ");
    Serial.println(http.errorToString(httpCode));
  }
  
  http.end();
  return response;
}

// ============================================================
// FUNGSI: PARSE JSON RESPONSE
// ============================================================
bool parseJsonResponse(String json, DynamicJsonDocument& doc) {
  DeserializationError error = deserializeJson(doc, json);
  
  if (error) {
    Serial.print("JSON Parse Error: ");
    Serial.println(error.c_str());
    return false;
  }
  
  if (!doc["success"]) {
    Serial.print("API Error: ");
    Serial.println(doc["error"].as<String>());
    return false;
  }
  
  return true;
}

// ============================================================
// FUNGSI: PROSES SORTING
// ============================================================
void processSorting() {
  Serial.println("\n=== PROSES SORTING DIMULAI ===");
  
  float weight = readStableWeight();
  Serial.print("Berat terukur: ");
  Serial.print(weight, 2);
  Serial.println(" g");
  
  String payload = "{\"weight\":" + String(weight, 2) + "}";
  String response = sendHttpPost(URL_API_SORT, payload);
  
  if (response == "") return;
  
  DynamicJsonDocument doc(1024);
  if (!parseJsonResponse(response, doc)) return;
  
  int gateAngle = doc["gate_angle"] | 0;
  String motorAction = doc["motor_action"] | "OFF";
  String ledAction = doc["led"] | "OFF";
  
  Serial.print("Gate Angle: "); Serial.println(gateAngle);
  Serial.print("Motor: "); Serial.println(motorAction);
  Serial.print("LED: "); Serial.println(ledAction);
  
  // ------------------------------------------------------------
  // URUTAN AKTUATOR:
  //  1. Palet TIDAK bergerak dulu.
  //  2. Relay motor dinyalakan & gate digeser (fase pertama).
  //  3. Setelah jeda PALLET_START_DELAY_MS, palet turun,
  //     bertahan PALLET_HOLD_MS (1 dtk), lalu kembali ke 90°.
  // ------------------------------------------------------------

  // [Fase 1] Relay + LED + gate
  if (motorAction == "ON") {
    digitalWrite(RELAY_PIN, LOW);
    Serial.println("[OK] Relay ON (Motor berjalan)");
  }

  if (ledAction == "ON") {
    digitalWrite(LED_PIN, HIGH);
    Serial.println("[OK] LED ON (Status BUSY)");
  }

  servoGate.write(gateAngle);
  Serial.print("Gate digeser ke: "); Serial.println(gateAngle);

  // [Jeda] Tunggu sesaat sebelum palet bergerak
  delay(PALLET_START_DELAY_MS);

  // [Fase 2] Palet turun ke posisi TILT (0°)
  servoPallet.write(SERVO_PALLET_TILT);
  Serial.println("Palet diturunkan (TILT)");

  // [Jeda] Tunggu 1 detik, lalu kembali ke posisi awal (90°)
  delay(PALLET_HOLD_MS);
  servoPallet.write(SERVO_PALLET_UP);
  Serial.println("Palet kembali ke posisi awal (UP)");
  
  currentState = STATE_BUSY;
  busyStartTime = millis(); // Mulai hitung watchdog anti-stuck
  Serial.println("=== State: BUSY ===\n");
}

// ============================================================
// FUNGSI: PROSES IR TRIGGER
// ============================================================
void processIRTrigger() {
  Serial.println("\n=== IR SENSOR TERPICU ===");
  
  String payload = "{\"ir_status\":\"ON\"}";
  String response = sendHttpPost(URL_API_TRIGGER, payload);
  
  if (response == "") return;
  
  DynamicJsonDocument doc(1024);
  if (!parseJsonResponse(response, doc)) return;
  
  if (doc["start_countdown"] == true) {
    unsigned long countdownTime = doc["time"] | COUNTDOWN_DURATION_MS;
    Serial.print("Mulai countdown: ");
    Serial.print(countdownTime);
    Serial.println(" ms");
    
    countdownStartTime = millis();
    countdownDurationMs = countdownTime; // Pakai durasi dari server, bukan konstanta lokal
    countdownActive = true;
    currentState = STATE_COUNTDOWN;
    Serial.println("=== State: COUNTDOWN ===\n");
  }
}

// ============================================================
// FUNGSI: PROSES COUNTDOWN FINISH
// ============================================================
void processCountdownFinish() {
  Serial.println("\n=== COUNTDOWN SELESAI ===");
  
  String payload = "{\"ir_status\":\"FINISH\"}";
  String response = sendHttpPost(URL_API_TRIGGER, payload);
  
  if (response == "") return;
  
  DynamicJsonDocument doc(1024);
  if (!parseJsonResponse(response, doc)) return;
  
  int gateAngle = doc["gate_angle"] | 0;
  String motorAction = doc["motor_action"] | "OFF";
  String ledAction = doc["led"] | "OFF";
  
  servoGate.write(gateAngle);
  Serial.print("Gate reset ke: "); Serial.println(gateAngle);
  
  if (motorAction == "OFF") {
    digitalWrite(RELAY_PIN, HIGH);
    Serial.println("[OK] Relay OFF (Motor berhenti)");
  }
  
  if (ledAction == "OFF") {
    digitalWrite(LED_PIN, LOW);
    Serial.println("[OK] LED OFF (Status IDLE)");
  }
  
  delay(500);
  baselineWeight = scale.get_units(); // Re-taring baseline untuk siklus berikutnya
  Serial.print("Baseline baru: ");
  Serial.print(baselineWeight, 2);
  Serial.println(" g");
  
  currentState = STATE_IDLE;
  countdownActive = false;
  Serial.println("=== State: IDLE ===\n");
}

// ============================================================
// FUNGSI: PROSES PERINTAH SERIAL MONITOR (INTERAKTIF)
// ============================================================
void processSerialCommand(String cmd) {
  cmd.trim();
  if (cmd.length() == 0) return;

  if (cmd.equalsIgnoreCase("t")) {
    Serial.println("\n>>> Melakukan TARE... Pastikan tidak ada beban!");
    delay(500);
    scale.tare();
    baselineWeight = 0.0; // Reset baseline setelah tare
    Serial.println(">>> TARE Berhasil! Berat sekarang 0.00 g\n");
  } else {
    float newFactor = cmd.toFloat();
    if (newFactor != 0) {
      calibration_factor = newFactor;
      scale.set_scale(calibration_factor);
      Serial.print("\n>>> Faktor Kalibrasi berhasil diubah menjadi: ");
      Serial.println(calibration_factor);
      Serial.println(">>> Silakan cek berat benda sekarang.\n");
    } else {
      Serial.println("\n>>> [ERROR] Input tidak valid!");
      Serial.println(">>> Ketik 't' untuk Tare, atau masukkan angka (contoh: 1250.5)\n");
    }
  }
}

// ============================================================
// FUNGSI: MULAI STABILISASI BEBAN
// ============================================================
void beginStabilizing() {
  stabStartTime = millis();
  stableSamples = 0;
  lastSampleWeight = readStableWeight();
  Serial.println("=== State: STABILIZING (menunggu beban stabil) ===");
}

// ============================================================
// FUNGSI: CEK KESTABILAN BEBAN
// Kembali true jika beban sudah stabil (sampel berurutan dalam
// toleransi dan waktu minimum terpenuhi). Jika beban hilang atau
// timeout, kembali ke IDLE.
// ============================================================
bool checkWeightStable() {
  float weight = readStableWeight();
  float delta = weight - baselineWeight;

  // Jika beban hilang sebelum stabil, batalkan proses
  if (delta < MIN_WEIGHT_TRIGGER_GRAM - STABILITY_HYSTERESIS_GRAM) {
    Serial.println("[BATAL] Beban hilang sebelum stabil. Kembali ke IDLE.");
    currentState = STATE_IDLE;
    return false;
  }

  // Timeout anti-stuck
  if (millis() - stabStartTime > MAX_STABILIZE_MS) {
    Serial.println("[TIMEOUT] Beban tidak pernah stabil. Kembali ke IDLE.");
    currentState = STATE_IDLE;
    return false;
  }

  // Hitung sampel stabil berurutan (manual abs, tanpa math.h)
  float diff = weight - lastSampleWeight;
  if (diff < 0) diff = -diff;
  if (diff <= STABILITY_TOLERANCE_GRAM) {
    stableSamples++;
  } else {
    stableSamples = 0;
  }
  lastSampleWeight = weight;

  bool cukupWaktu = (millis() - stabStartTime) >= STABILITY_WINDOW_MS;
  bool cukupSampel = stableSamples >= STABILITY_MIN_SAMPLES;

  Serial.print("Stabilisasi: sampel stabil ");
  Serial.print(stableSamples);
  Serial.print("/");
  Serial.print(STABILITY_MIN_SAMPLES);
  Serial.print(" | berat: ");
  Serial.print(weight, 2);
  Serial.println(" g");

  return (cukupWaktu && cukupSampel);
}

// ============================================================
// SETUP & LOOP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n========================================");
  Serial.println("Smart Pallet Weight Sorting Gate");
  Serial.println("Perintah Serial: [t] = Tare, [angka] = Kalibrasi");
  Serial.println("========================================\n");
  
  connectWiFi();
  initHardware();
  initLoadCell();
  
  Serial.println("\n[OK] Sistem siap! State: IDLE\n");
}

void loop() {
  // 1. Proses perintah Serial Monitor (Interaktif)
  while (Serial.available() > 0) {
    char inChar = (char)Serial.read();
    if (inChar == '\n' || inChar == '\r') {
      processSerialCommand(inputString);
      inputString = "";
    } else {
      inputString += inChar;
    }
  }

  // 2. State Machine Utama
  switch (currentState) {
    case STATE_IDLE:
      if (checkDeltaTrigger()) {
        beginStabilizing();
        currentState = STATE_STABILIZING;
      }
      break;

    case STATE_STABILIZING:
      if (checkWeightStable()) {
        processSorting(); // Beban stabil -> mulai sortir (pindah ke BUSY)
      }
      break;
    
    case STATE_BUSY:
      // Trigger normal lewat sensor IR
      if (digitalRead(IR_SENSOR_PIN) == LOW && !irTriggered) {
        irTriggered = true;
        processIRTrigger();
      }
      // Watchdog: BUSY terlalu lama tanpa IR -> reset paksa
      else if (millis() - busyStartTime > MAX_BUSY_WATCHDOG_MS &&
               millis() - lastWatchdogTry >= WATCHDOG_RETRY_INTERVAL_MS) {
        lastWatchdogTry = millis();
        Serial.println("[WATCHDOG] BUSY > 10 dtk tanpa IR. Kirim FINISH (reset)...");
        processCountdownFinish();
      }
      break;
    
    case STATE_COUNTDOWN:
      if (countdownActive) {
        unsigned long elapsed = millis() - countdownStartTime;
        if (elapsed >= countdownDurationMs) {
          processCountdownFinish();
          irTriggered = false;
        }
      }
      break;
  }
  
  delay(100); // Delay kecil agar Serial Monitor tidak spamming dan sistem stabil
}