            </main>
            <footer class="footer">
                <span>&copy; <?php echo date('Y'); ?> Smart Pallet Weight Sorting Gate &mdash; Layer 4 Dashboard</span>
                <span class="footer-meta">
                    <?php if (!empty($autoRefresh)): ?>
                        <span class="pill pill-live">Auto-refresh 2 detik</span>
                    <?php endif; ?>
                    <span class="mono"><?php echo h(date('Y-m-d H:i:s')); ?></span>
                </span>
            </footer>
        </div>
    </div>
</body>
</html>
