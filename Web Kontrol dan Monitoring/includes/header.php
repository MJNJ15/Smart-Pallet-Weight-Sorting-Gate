<?php
$activePage = basename($_SERVER['PHP_SELF'], '.php');
$navLink = static function (string $page, string $href, string $label, string $icon) use ($activePage): string {
    $active = $page === $activePage;
    return '<li><a class="rail-link' . ($active ? ' active' : '') . '" href="' . $href . '"'
        . ' data-label="' . $label . '" aria-label="' . $label . '"'
        . ($active ? ' aria-current="page"' : '') . '>' . icon($icon) . '</a></li>';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Pallet Dashboard — Weight Sorting Gate</title>
    <?php if (!empty($autoRefresh)): ?>
    <meta http-equiv="refresh" content="2">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Instrument+Serif&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="shell">
        <aside class="rail">
            <div class="rail-brand" title="Smart Pallet — Weight Sorting Gate"><?php echo icon('box'); ?></div>
            <nav aria-label="Navigasi utama">
                <ul class="rail-links">
                    <?php echo $navLink('index', 'index.php', 'Monitoring', 'activity'); ?>
                    <?php echo $navLink('kontrol', 'kontrol.php', 'Kontrol', 'sliders'); ?>
                    <?php echo $navLink('riwayat', 'riwayat.php', 'Riwayat', 'history'); ?>
                </ul>
            </nav>
            <div class="rail-meta" title="Terhubung via LAN">
                <span class="dot-live" aria-hidden="true"></span>
                <span>LAN</span>
            </div>
        </aside>
        <div class="stage">
            <main class="container">
