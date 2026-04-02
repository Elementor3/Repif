</div><!-- /.container -->
<footer class="text-center text-muted py-4 mt-5 border-top">
    <small>&copy; <?= date('Y') ?> WeatherStation</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/friends.js"></script>
<?php if (basename($_SERVER['PHP_SELF']) === 'chat.php'): ?>
<script src="/assets/js/chat.js"></script>
<?php endif; ?>
</body>
</html>
