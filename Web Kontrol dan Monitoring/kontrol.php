<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $input  = $_POST['threshold'] ?? '';
        $errors = validateThreshold($input);

        if (!$errors) {
            $newThreshold = round((float) $input, 2);

            if (changeThreshold($newThreshold)) {
                setFlash('success', 'Threshold berhasil diubah menjadi ' . formatGram($newThreshold) . '.');
                header('Location: kontrol.php');
                exit;
            }

            $errors[] = 'Gagal menyimpan threshold ke database.';
        }
    }
}

$successMessage = flash('success');
$threshold      = getThreshold();
$logs           = getThresholdLog(5);

// Posisi threshold dalam rentang MIN..MAX (32 tick)
$range  = MAX_THRESHOLD - MIN_THRESHOLD;
$tickOn = $threshold === null ? 0 : (int) round(max(0, min(1, ($threshold - MIN_THRESHOLD) / $range)) * 32);

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Kontrol</p>
        <h1>Kontrol Threshold</h1>
        <p class="page-sub">Atur batas berat untuk keputusan sortir gate pada siklus berikutnya.</p>
    </div>
</section>

<?php if ($successMessage): ?>
    <div class="alert alert-success" role="status">
        <?php echo icon('check'); ?>
        <?php echo h($successMessage); ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <?php echo icon('alert'); ?>
        <div>
            <?php foreach ($errors as $error): ?>
                <p><?php echo h($error); ?></p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<section class="split">
    <div class="stack">
        <article class="card card-ocean">
            <header class="card-label">Threshold saat ini</header>
            <span class="current-number mono"><?php echo h(formatGram($threshold)); ?></span>
            <div class="ticks" role="img"
                 aria-label="Posisi threshold dalam rentang <?php echo MIN_THRESHOLD; ?> sampai <?php echo MAX_THRESHOLD; ?> gram">
                <?php for ($i = 0; $i < 32; $i++): ?><i<?php echo $i < $tickOn ? ' class="on"' : ''; ?>></i><?php endfor; ?>
            </div>
            <footer class="range-note mono">
                <span><?php echo MIN_THRESHOLD; ?> g</span>
                <span><?php echo MAX_THRESHOLD; ?> g</span>
            </footer>
        </article>

        <section class="panel panel-form">
            <header class="panel-head">
                <h2>Pengaturan Threshold Sortir</h2>
            </header>

            <form method="post" action="kontrol.php" class="form" autocomplete="off">
                <?php echo csrfField(); ?>

                <label class="form-label" for="threshold">Threshold baru (gram)</label>
                <div class="form-row">
                    <input
                        type="number"
                        id="threshold"
                        name="threshold"
                        class="form-input mono"
                        min="<?php echo MIN_THRESHOLD; ?>"
                        max="<?php echo MAX_THRESHOLD; ?>"
                        step="0.01"
                        required
                        value="<?php echo h($_POST['threshold'] ?? ($threshold !== null ? rtrim(rtrim(number_format($threshold, 2, '.', ''), '0'), '.') : '')); ?>"
                        placeholder="contoh: 500">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>

                <p class="form-hint">
                    Berat &ge; threshold &rarr; <span class="text-reject">REJECT</span>,
                    berat &lt; threshold &rarr; <span class="text-ok">OK</span>.
                    Rentang valid: <span class="mono"><?php echo MIN_THRESHOLD; ?> &ndash; <?php echo MAX_THRESHOLD; ?> g</span>.
                </p>
            </form>
        </section>
    </div>

    <section class="panel">
        <header class="panel-head">
            <h2>Riwayat Perubahan <span class="muted">(5 terakhir)</span></h2>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th class="col-weight">Lama</th>
                        <th class="col-weight">Baru</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs): ?>
                        <tr>
                            <td colspan="3" class="empty-row">Belum ada perubahan threshold.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="mono"><?php echo h(formatTime($log['waktu'])); ?></td>
                                <td class="mono col-weight muted"><?php echo h(formatGram($log['threshold_lama'])); ?></td>
                                <td class="mono col-weight"><strong><?php echo h(formatGram($log['threshold_baru'])); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
