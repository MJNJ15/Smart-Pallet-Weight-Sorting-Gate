<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function h($str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/** Inline SVG icon (Lucide-style, 24px grid). Dekoratif: disembunyikan dari screen reader. */
function icon(string $name): string
{
    static $paths = [
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'sliders'  => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'refresh'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'trash'    => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'check'    => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'alert'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/>',
        'box'      => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    ];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

function flash(string $key, $default = null)
{
    if (!isset($_SESSION['flash'][$key])) {
        return $default;
    }
    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $value;
}

function setFlash(string $key, $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token): bool
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string) $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(generateCsrfToken()) . '">';
}

function getSystemState(): array
{
    global $dbh;
    $stmt = $dbh->prepare('SELECT busy, updated_at FROM system_state WHERE id = 1');
    $stmt->execute();
    return $stmt->fetch() ?: ['busy' => 0, 'updated_at' => null];
}

function getThreshold(): ?float
{
    global $dbh;
    $stmt = $dbh->prepare('SELECT threshold FROM config WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? (float) $row['threshold'] : null;
}

function getSummary(): array
{
    global $dbh;
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status = 'OK') AS total_ok,
                SUM(status = 'REJECT') AS total_reject
         FROM sorting_history_dashboard"
    );
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: ['total' => 0, 'total_ok' => 0, 'total_reject' => 0];
}

function getLastWeight(): ?float
{
    global $dbh;
    $stmt = $dbh->prepare('SELECT berat FROM sorting_history_dashboard ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? (float) $row['berat'] : null;
}

function getRecentSorts(int $limit = 20): array
{
    global $dbh;
    $stmt = $dbh->prepare(
        'SELECT id, berat, status, sudut_gate, waktu
         FROM sorting_history_dashboard
         ORDER BY id DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getHistoryCount(): int
{
    global $dbh;
    $stmt = $dbh->query('SELECT COUNT(*) FROM sorting_history_dashboard');
    return (int) $stmt->fetchColumn();
}

function getHistoryPage(int $limit, int $offset): array
{
    global $dbh;
    $stmt = $dbh->prepare(
        'SELECT id, berat, status, sudut_gate, waktu
         FROM sorting_history_dashboard
         ORDER BY id DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function formatStatus(string $status): string
{
    return strtoupper($status) === 'OK' ? 'OK' : 'REJECT';
}

function statusBadge(string $status): string
{
    $code = formatStatus($status);
    $class = $code === 'OK' ? 'badge-ok' : 'badge-reject';
    return '<span class="badge ' . $class . '"><span class="led"></span>' . h($code) . '</span>';
}

function formatGram($value, string $fallback = '-'): string
{
    if ($value === null) {
        return $fallback;
    }
    $value = (float) $value;
    $decimals = (floor($value) == $value) ? 0 : 2;
    return number_format($value, $decimals, ',', '.') . ' g';
}

function formatTime($time): string
{
    if (!$time) {
        return '-';
    }
    $ts = strtotime((string) $time);
    return $ts ? date('Y-m-d H:i:s', $ts) : '-';
}

function gateAngle($angle): string
{
    $angle = (int) $angle;
    if ($angle === 0) {
        return '0°';
    }
    if ($angle >= 90) {
        return '90°';
    }
    return $angle . '°';
}

function getThresholdLog(int $limit = 5): array
{
    global $dbh;
    $stmt = $dbh->prepare(
        'SELECT threshold_lama, threshold_baru, waktu
         FROM config_log
         ORDER BY id DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function changeThreshold(float $newThreshold): bool
{
    global $dbh;
    try {
        $dbh->beginTransaction();

        $stmtOld = $dbh->prepare('SELECT threshold FROM config WHERE id = 1 FOR UPDATE');
        $stmtOld->execute();
        $old = $stmtOld->fetch();

        $stmtUpdate = $dbh->prepare('UPDATE config SET threshold = ? WHERE id = 1');
        $stmtUpdate->execute([$newThreshold]);

        $stmtLog = $dbh->prepare(
            'INSERT INTO config_log (threshold_lama, threshold_baru, waktu)
             VALUES (?, ?, NOW())'
        );
        $stmtLog->execute([$old ? (float) $old['threshold'] : $newThreshold, $newThreshold]);

        $dbh->commit();
        return true;
    } catch (Throwable $e) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        return false;
    }
}

function clearHistory(): bool
{
    global $dbh;
    try {
        $dbh->exec('TRUNCATE TABLE sorting_history_dashboard');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function resetSystemBusy(): bool
{
    global $dbh;
    try {
        $stmt = $dbh->prepare('UPDATE system_state SET busy = 0 WHERE id = 1');
        return $stmt->execute();
    } catch (Throwable $e) {
        return false;
    }
}

function validateThreshold($value): array
{
    $errors = [];
    $value = trim((string) $value);

    if ($value === '') {
        $errors[] = 'Nilai threshold tidak boleh kosong.';
    } elseif (!is_numeric($value)) {
        $errors[] = 'Threshold harus berupa angka.';
    } elseif ((float) $value < MIN_THRESHOLD) {
        $errors[] = 'Threshold minimal ' . MIN_THRESHOLD . ' g.';
    } elseif ((float) $value > MAX_THRESHOLD) {
        $errors[] = 'Threshold maksimal ' . MAX_THRESHOLD . ' g (kapasitas load cell 1 kg).';
    }

    return $errors;
}

function paginate($page, int $perPage, int $totalCount): array
{
    $page = ($page === null || !ctype_digit((string) $page) || (int) $page < 1)
        ? 1
        : (int) $page;

    $totalPages = max(1, (int) ceil($totalCount / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    return [
        'page'       => $page,
        'totalPages' => $totalPages,
        'perPage'    => $perPage,
        'offset'     => ($page - 1) * $perPage,
    ];
}

function exportHistoryAsCsv(): void
{
    global $dbh;
    $stmt = $dbh->prepare(
        'SELECT id, berat, status, sudut_gate, waktu
         FROM sorting_history_dashboard
         ORDER BY id ASC'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $filename = 'riwayat_sortir_' . date('YmdHis') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['No', 'Waktu', 'Berat (g)', 'Status', 'Sudut Gate']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            formatTime($row['waktu']),
            number_format((float) $row['berat'], 2, '.', ''),
            formatStatus($row['status']),
            gateAngle($row['sudut_gate']),
        ]);
    }

    fclose($output);
}

function renderPagination(int $currentPage, int $totalPages, string $baseUrl): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $url = static function (int $page) use ($baseUrl): string {
        return $baseUrl . '?page=' . $page;
    };

    $html = '<nav class="pagination" aria-label="Navigasi halaman">';

    if ($currentPage > 1) {
        $html .= '<a class="page-item" href="' . h($url($currentPage - 1)) . '">&laquo; Prev</a>';
    }

    $start = max(1, $currentPage - 2);
    $end   = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<a class="page-item" href="' . h($url(1)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
    }

    for ($p = $start; $p <= $end; $p++) {
        $html .= ($p === $currentPage)
            ? '<span class="page-item active">' . $p . '</span>'
            : '<a class="page-item" href="' . h($url($p)) . '">' . $p . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
        $html .= '<a class="page-item" href="' . h($url($totalPages)) . '">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a class="page-item" href="' . h($url($currentPage + 1)) . '">Next &raquo;</a>';
    }

    return $html . '</nav>';
}
