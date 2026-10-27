  <?php
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $SHOW_FOOTER = (strpos($script, '/public/index.php') !== false);
  if ($SHOW_FOOTER):
?>
<footer class="app-footer">
  <div class="container py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <div class="text-muted small">
      &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Tous droits réservés
    </div>
    <ul class="nav small">
      <li class="nav-item"><a href="<?= e(BASE_URL) ?>/public/index.php" class="nav-link px-2 text-muted">Accueil</a></li>
      <?php if (is_logged_in()): ?>
        <li class="nav-item"><a href="<?= e(BASE_URL) ?>/app/dashboard.php" class="nav-link px-2 text-muted">Dashboard</a></li>
      <?php endif; ?>
      <li class="nav-item"><a href="https://www.rgz.bj" class="nav-link px-2 text-muted">Confidentialité</a></li>
      <li class="nav-item"><a href="https://www.rgz.bj" class="nav-link px-2 text-muted">Conditions</a></li>
      <li class="nav-item"><a href="mailto:rgz@rgz.bj" class="nav-link px-2 text-muted">Support</a></li>
    </ul>
  </div>
</footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/app.js?v=20250817"></script>
</body>
</html>
