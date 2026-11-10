<?php
// includes/footer.php
?>
</main>
<footer style="text-align: center; padding: 2rem 1rem; color: #94a3b8; font-size: 0.85rem; border-top: 1px solid #e2e8f0; background: #ffffff; margin-top: auto;">
    <p>&copy; <?= date('Y') ?> Brass Note (名古屋大原学園吹奏楽団)</p>
</footer>

<!-- PWA Service Worker 登録 -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= isset($base_path) ? $base_path : "./" ?>sw.js').catch(function(err) {
            console.log('SW registration error:', err);
        });
    });
}
</script>
</body>
</html>
