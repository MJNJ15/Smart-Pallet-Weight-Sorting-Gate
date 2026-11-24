<?php
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportHistoryAsCsv();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Token CSRF tidak valid. Riwayat tidak dihapus.');
        } elseif (clearHistory()) {
            setFlash('success', 'Seluruh riwayat sortir berhasil dihapus.');
        } else {
            setFlash('error', 'Gagal menghapus riwayat sortir.');
        }

        header('Location: riwayat.php');
        exit;
    }
}

$successMessage = flash('success');
$errorMessage   = flash('error');

$perPage    = 20;
$totalCount = getHistoryCount();
$pager      = paginate($_GET['page'] ?? null, $perPage, $totalCount);
$rows       = getHistoryPage($pager['perPage'], $pager['offset']);
$rangeFrom  = $totalCount === 0 ? 0 : $pager['offset'] + 1;
$rangeTo    = min($pager['offset'] + $perPage, $totalCount);

require __DIR__ . '/includes/header.php';
?>

<section class="page-head">
    <div>
        <p class="eyebrow">Riwayat</p>
        <h1>Riwayat Sortir</h1>
        <p class="page-sub">Seluruh data sortir tersimpan, terbaru di atas. <?php echo $totalCount; ?> baris.</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-ghost" href="riwayat.php?export=csv"><?php echo icon('download'); ?> Ekspor CSV</a>
        <?php if ($totalCount > 0): ?>
            <form method="post" action="riwayat.php" class="inline-form"
                  onsubmit="return confirm('Hapus seluruh riwayat sortir? Tindakan ini tidak dapat dibatalkan.');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-danger"><?php echo icon('trash'); ?> Hapus Semua</button>
            </form>
        <?php endif; ?>
    </div>
</section>

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

<section class="panel">
    <header class="panel-head">
        <h2>Data Sortir</h2>
        <span class="muted mono">
            <?php echo $rangeFrom; ?>&ndash;<?php echo $rangeTo; ?> dari <?php echo $totalCount; ?>
        </span>
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
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5" class="empty-row">Belum ada data sortir.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
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

    <?php echo renderPagination($pager['page'], $pager['totalPages'], 'riwayat.php'); ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
