<?php
require_once __DIR__ . '/includes/functions.php';

$autoRefresh = true;

// Handle aksi reset status dari tombol dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_status') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.');
        } elseif (resetSystemBusy()) {
            setFlash('success', 'Status sistem berhasil di-reset ke IDLE.');
        } else {
            setFlash('error', 'Gagal mereset status sistem.');
        }

        header('Location: index.php');
        exit;
    }
}

$successMessage = flash('success');
$errorMessage   = flash('error');

$state      = getSystemState();
$threshold  = getThreshold();
$summary    = getSummary();
$lastWeight = getLastWeight();
$recent     = getRecentSorts(20);

$busy       = (int) $state['busy'] === 1;
$total      = (int) ($summary['total'] ?? 0);
$totalOk    = (int) ($summary['total_ok'] ?? 0);
$totalRej   = (int) ($summary['total_reject'] ?? 0);
$updatedAt  = formatTime($state['updated_at']);

// --- Data tampilan (dihitung di server, tanpa JS) ---
$hour     = (int) date('G');
$greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));

$okPct   = $total > 0 ? round($totalOk / $total * 100, 1) : 0.0;
$tickOn  = (int) round($okPct / 100 * 32);

// Bar chart: 12 sortir terakhir, urut lama -> baru
$chartRows = array_reverse(array_slice($recent, 0, 12));
$weights   = array_map(static fn($r) => (float) $r['berat'], $chartRows);
$scale     = max(1.0, (float) $threshold, ...$weights);
$thrBottom = $threshold ? min(100, $threshold / $scale * 100) : null;

// Sparkline: 20 sortir terakhir
$spark   = array_reverse(array_map(static fn($r) => (float) $r['berat'], $recent));
$sparkN  = count($spark);
$sparkAvg = $sparkN ? array_sum($spark) / $sparkN : null;
$sparkPts = [];
if ($sparkN >= 2) {
    $lo = min($spark);
    $hi = max($spark);
    $span = ($hi - $lo) ?: 1;
    foreach ($spark as $i => $w) {
        $sparkPts[] = [round($i / ($sparkN - 1) * 200, 1), round(52 - ($w - $lo) / $span * 44, 1)];
    }
}
$sparkLine = implode(' ', array_map(static fn($p) => $p[0] . ',' . $p[1], $sparkPts));

// Berat terakhir vs threshold
$overLimit = $lastWeight !== null && $threshold && $lastWeight >= $threshold;
$lastPct   = ($lastWeight !== null && $threshold) ? min(100, $lastWeight / $threshold * 100) : 0;

require __DIR__ . '/includes/header.php';
?>

<?php if ($successMessage): ?>
    <div class="alert alert-success" role="status">
        <?php echo icon('check'); ?>
        <?php echo h($successMessage); ?>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-error" role="alert">
        <?php echo icon('alert'); ?>
        <?php echo h($errorMessage); ?>
    </div>
<?php endif; ?>

<section class="page-head">
    <div>
        <p class="eyebrow">Monitoring Sistem</p>
        <h1><?php echo h($greeting); ?>, Operator</h1>
        <p class="page-sub">Status antrean, threshold aktif, dan riwayat sortir terbaru.</p>
    </div>
    <div class="head-actions">
        <?php if ($busy): ?>
            <form method="post" action="index.php" class="inline-form"
                  onsubmit="return confirm('Reset status sistem ke IDLE?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="reset_status">
                <button type="submit" class="btn btn-danger"><?php echo icon('refresh'); ?> Reset Status</button>
            </form>
        <?php endif; ?>
        <span class="pill pill-<?php echo $busy ? 'busy' : 'idle'; ?>">
            <span class="led"></span><?php echo $busy ? 'SISTEM BUSY' : 'SISTEM IDLE'; ?>
        </span>
        <span class="pill pill-sync">
            <?php echo icon('refresh'); ?>
            <span>Sinkron terakhir <strong class="mono"><?php echo h($updatedAt); ?></strong></span>
        </span>
    </div>
</section>

<section class="dash">
    <div class="dash-main">
        <article class="card card-status">
            <header class="card-label">Status Sistem</header>
            <div class="card-main">
                <span class="led led-lg <?php echo $busy ? 'led-busy' : 'led-idle'; ?>"></span>
                <span class="card-value"><?php echo $busy ? 'BUSY' : 'IDLE'; ?></span>
            </div>
            <footer class="card-foot">
                <?php echo $busy ? 'Gate sedang memproses sortir.' : 'Gate siap menerima sortir berikutnya.'; ?>
            </footer>
        </article>

        <article class="card card-rate">
            <header class="card-label">Rasio Lolos (OK)</header>
            <div class="card-main">
                <span class="card-value mono"><?php echo h(number_format($okPct, 1, ',', '.')); ?>%</span>
            </div>
            <div class="ticks" role="img" aria-label="Rasio OK <?php echo h(number_format($okPct, 1, ',', '.')); ?> persen">
                <?php for ($i = 0; $i < 32; $i++): ?><i<?php echo $i < $tickOn ? ' class="on"' : ''; ?>></i><?php endfor; ?>
            </div>
            <footer class="card-foot card-split">
                <span class="chip chip-ok">OK <?php echo $totalOk; ?></span>
                <span class="chip chip-reject">REJECT <?php echo $totalRej; ?></span>
            </footer>
        </article>

        <article class="card card-summary">
            <header class="card-label">Ringkasan Sortir</header>
            <div class="donut-wrap">
                <div class="donut" role="img"
                     aria-label="<?php echo $totalOk; ?> OK dan <?php echo $totalRej; ?> REJECT dari <?php echo $total; ?> sortir">
                    <svg viewBox="0 0 36 36" aria-hidden="true">
                        <circle class="ring-empty" cx="18" cy="18" r="15.9155"/>
                        <?php if ($total > 0): ?>
                            <circle class="ring-reject" cx="18" cy="18" r="15.9155"
                                    stroke-dasharray="<?php echo h(round($totalRej / $total * 100, 2)); ?> 100"
                                    stroke-dashoffset="-<?php echo h(round($totalOk / $total * 100, 2)); ?>"/>
                            <circle class="ring-ok" cx="18" cy="18" r="15.9155"
                                    stroke-dasharray="<?php echo h(round($totalOk / $total * 100, 2)); ?> 100"/>
                        <?php endif; ?>
                    </svg>
                    <div class="donut-center"><span class="mono"><?php echo $total; ?></span><small>sortir</small></div>
                </div>
                <ul class="legend">
                    <li><span class="sw"></span>OK <strong class="mono"><?php echo $totalOk; ?></strong></li>
                    <li><span class="sw sw-reject"></span>REJECT <strong class="mono"><?php echo $totalRej; ?></strong></li>
                </ul>
            </div>
        </article>

        <article class="card card-last">
            <header class="card-label">Berat Terakhir</header>
            <div class="card-main">
                <span class="card-value mono"><?php echo h(formatGram($lastWeight)); ?></span>
            </div>
            <div class="progress<?php echo $overLimit ? ' is-over' : ''; ?>" role="img"
                 aria-label="Berat terakhir <?php echo h(round($lastPct)); ?> persen dari threshold">
                <span style="width: <?php echo h(round($lastPct, 1)); ?>%"></span>
            </div>
            <footer class="card-foot">
                <?php if ($lastWeight === null): ?>
                    Belum ada data sortir
                <?php else: ?>
                    Sortir #<?php echo (int) $recent[0]['id']; ?> &middot;
                    <?php echo $overLimit ? 'di atas threshold' : 'di bawah threshold'; ?>
                <?php endif; ?>
            </footer>
        </article>
    </div>

    <aside class="dash-side">
        <article class="card card-ocean">
            <header class="card-label">Berat <?php echo count($chartRows); ?> Sortir Terakhir</header>
            <div class="card-main">
                <span class="card-value mono"><?php echo $total; ?> <small>total sortir</small></span>
            </div>
            <div class="bars" role="img"
                 aria-label="Grafik batang berat <?php echo count($chartRows); ?> sortir terakhir">
                <?php if ($thrBottom !== null && $chartRows): ?>
                    <span class="thr-line" style="bottom: <?php echo h(round($thrBottom, 1)); ?>%"></span>
                <?php endif; ?>
                <?php foreach ($chartRows as $i => $row):
                    $isReject = formatStatus($row['status']) === 'REJECT';
                    $isLatest = $i === count($chartRows) - 1;
                    $height   = max(6, round((float) $row['berat'] / $scale * 100, 1));
                    ?>
                    <span class="bar<?php echo $isReject ? ' reject' : ''; ?><?php echo $isLatest ? ' latest' : ''; ?>"
                          style="height: <?php echo h($height); ?>%"
                          title="#<?php echo (int) $row['id']; ?> &middot; <?php echo h(formatGram($row['berat'])); ?> &middot; <?php echo h(formatStatus($row['status'])); ?>"></span>
                <?php endforeach; ?>
            </div>
            <footer class="chart-note">
                <span class="key"><i></i>OK</span>
                <span class="key"><i class="hatch"></i>REJECT</span>
                <span>--- threshold</span>
            </footer>
        </article>

        <article class="card card-edit">
            <div>
                <header class="card-label">Threshold Aktif</header>
                <span class="card-value mono"><?php echo h(formatGram($threshold)); ?></span>
                <p class="card-foot">Berat &ge; threshold &rarr; REJECT</p>
            </div>
            <a class="icon-btn" href="kontrol.php" aria-label="Ubah threshold" title="Ubah threshold">
                <?php echo icon('sliders'); ?>
            </a>
        </article>

        <article class="card card-dark">
            <header class="card-label">Tren Berat</header>
            <div class="card-main">
                <span class="card-value mono"><?php echo h(formatGram($sparkAvg !== null ? round($sparkAvg, 1) : null)); ?></span>
                <small class="card-foot">rata-rata <?php echo $sparkN; ?> sortir</small>
            </div>
            <?php if ($sparkPts): ?>
                <svg class="spark" viewBox="0 0 200 60" role="img" aria-label="Tren berat <?php echo $sparkN; ?> sortir terakhir">
                    <defs>
                        <linearGradient id="sparkFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#fff" stop-opacity="0.28"/>
                            <stop offset="1" stop-color="#fff" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <polygon points="0,60 <?php echo h($sparkLine); ?> 200,60" fill="url(#sparkFill)"/>
                    <polyline class="line" points="<?php echo h($sparkLine); ?>"/>
                    <circle class="dot" cx="<?php echo h(end($sparkPts)[0]); ?>" cy="<?php echo h(end($sparkPts)[1]); ?>" r="3.5"/>
                </svg>
            <?php else: ?>
                <p class="card-foot">Butuh minimal 2 data sortir.</p>
            <?php endif; ?>
        </article>
    </aside>
</section>

<section class="panel">
    <header class="panel-head">
        <h2>Riwayat Sortir <span class="muted">(20 terbaru)</span></h2>
        <span class="refresh-note"><span class="dot-live"></span> refresh otomatis 2 detik</span>
    </header>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th>Waktu</th>
                    <th class="col-weight">Berat</th>
                    <th>Status</th>
                    <th class="col-angle">Sudut Gate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recent): ?>
                    <tr>
                        <td colspan="5" class="empty-row">Belum ada data sortir.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td class="mono muted"><?php echo (int) $row['id']; ?></td>
                            <td class="mono"><?php echo h(formatTime($row['waktu'])); ?></td>
                            <td class="mono col-weight"><strong><?php echo h(formatGram($row['berat'])); ?></strong></td>
                            <td><?php echo statusBadge($row['status']); ?></td>
                            <td class="mono"><?php echo h(gateAngle($row['sudut_gate'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
