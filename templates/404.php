<?php $pageTitle = '404 — AuthVault'; require __DIR__ . '/layout.php'; ?>
<nav class="nav">
  <div class="nav-brand"><div class="nav-logo">T</div> TOTPVault</div>
  <div class="nav-spacer"></div>
  <?php if ($user): ?><a href="/dashboard" class="btn btn-secondary btn-sm">Dashboard</a><?php endif; ?>
</nav>
<div style="min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;padding:2rem">
  <div style="text-align:center">
    <div style="font-size:5rem;font-weight:700;letter-spacing:-.05em;color:var(--line)">404</div>
    <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:.5rem">Page not found</h1>
    <p style="color:var(--ink3);margin-bottom:1.5rem">The page you're looking for doesn't exist.</p>
    <a href="<?= $user ? '/dashboard' : '/' ?>" class="btn btn-primary">Go home</a>
  </div>
</div>
</body></html>
