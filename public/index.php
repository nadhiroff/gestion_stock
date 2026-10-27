<?php $EXTRA_BODY_CLASS = 'no-scroll'; ?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<?php require_once __DIR__ . '/../templates/topbar.php'; ?>

<main class="container py-0 public-home-page">
  <div class="row align-items-center mb-1">
    <div class="col-md-7">
      <h1 class="display-6 fw-bold mb-1">Solution de gestion pour FAI</h1>
      <p class="lead text-secondary mb-2">Gérez vos stocks (équipements & SIM), vos abonnements Internet, vos ventes, factures et paiements dans une interface moderne et sécurisée.</p>
      <a href="<?= e(BASE_URL) ?>/app/auth/login.php" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Se connecter</a>
    </div>
    <div class="col-md-5 text-center">
      <img src="<?= e(BASE_URL) ?>/assets/img/hero.svg" class="img-fluid" alt="Dashboard preview" onerror="this.style.display='none'">
    </div>
  </div>

  <div class="row g-1">
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <i class="bi bi-box-seam text-primary fs-5 me-2"></i>
            <div>
              <div class="fw-bold">Produits en stock</div>
              <div class="text-muted"><span class="countup" data-countto="1280">0</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <i class="bi bi-receipt text-success fs-5 me-2"></i>
            <div>
              <div class="fw-bold">Ventes ce mois</div>
              <div class="text-muted"><span class="countup" data-countto="420">0</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <i class="bi bi-wifi text-warning fs-5 me-2"></i>
            <div>
              <div class="fw-bold">Abonnements actifs</div>
              <div class="text-muted"><span class="countup" data-countto="2150">0</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <i class="bi bi-cash-coin text-danger fs-5 me-2"></i>
            <div>
              <div class="fw-bold">Impays</div>
              <div class="text-muted"><span class="countup" data-countto="80">0</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info alert-sm mt-1 mb-0" role="alert">
    Ceci est une <strong>démo publique en lecture seule</strong>. Connectez-vous pour accéder aux données réelles.
  </div>
</main>

<script>
  // Fallback: si l'animation n'a pas démarré, afficher les valeurs finales
  (function(){
    function applyFallback(){
      try {
        document.querySelectorAll('.countup[data-countto]').forEach(el => {
          if (el.dataset.counted !== '1' && (el.textContent || '').trim() === '0') {
            const target = parseFloat(el.getAttribute('data-countto')) || 0;
            el.textContent = Math.round(target).toLocaleString();
            el.dataset.counted = '1';
          }
        });
      } catch(_){}
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => setTimeout(applyFallback, 2500));
    } else {
      setTimeout(applyFallback, 2500);
    }
  })();
  </script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
