<?php
$pageTitle = 'TOTPVault — Secure TOTP Manager';
require __DIR__ . '/layout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<style>
/* General hero section */
.hero {
  min-height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 1.5rem;
  text-align: center;
  background: radial-gradient(ellipse 80% 60% at 50% -10%, #dbeafe 0%, transparent 70%);
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .375rem .875rem;
  background: var(--blue-l);
  color: var(--blue);
  border-radius: 999px;
  font-size: .8125rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
  letter-spacing: .01em;
}
.hero h1 {
  font-size: clamp(2.25rem, 5vw, 3.75rem);
  font-weight: 700;
  letter-spacing: -.03em;
  line-height: 1.1;
  color: var(--ink);
  max-width: 720px;
}
.hero h1 span {
  color: var(--blue);
}
.hero-sub {
  font-size: 1.125rem;
  color: var(--ink3);
  max-width: 560px;
  margin: 1.25rem auto 2.5rem;
  line-height: 1.7;
}
.hero-ctas {
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
  justify-content: center;
}

/* Features */
.features {
  padding: 6rem 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
}
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 3rem;
}
.feature-card {
  padding: 1.5rem;
  border: 1px solid var(--line);
  border-radius: var(--r3);
  background: var(--bg);
  transition: box-shadow .2s, transform .2s;
}
.feature-card:hover {
  box-shadow: var(--shadow3);
  transform: translateY(-2px);
}
.feature-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--r2);
  background: var(--blue-l);
  color: var(--blue);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  font-size: 1.25rem;
}

/* Providers / Sign in */
.providers {
  padding: 5rem 1.5rem;
  background: var(--bg2);
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}
.providers-inner {
  max-width: 860px;
  margin: 0 auto;
  text-align: center;
}
.provider-btns {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 2rem;
}
.provider-btn {
  display: inline-flex;
  align-items: center;
  gap: .625rem;
  padding: .75rem 1.5rem;
  border: 1px solid var(--line);
  border-radius: var(--r2);
  background: var(--bg);
  font-size: .9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all .15s;
  color: var(--ink);
  text-decoration: none;
  box-shadow: var(--shadow);
}
.provider-btn:hover {
  box-shadow: var(--shadow2);
  transform: translateY(-1px);
}
.provider-btn svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* Footer */
.footer {
  padding: 2rem 1.5rem;
  text-align: center;
  color: var(--ink4);
  font-size: .875rem;
  border-top: 1px solid var(--line);
}

/* Section headings */
.section-label {
  font-size: .8125rem;
  font-weight: 600;
  color: var(--blue);
  letter-spacing: .08em;
  text-transform: uppercase;
  margin-bottom: .75rem;
}
.section-title {
  font-size: clamp(1.75rem, 3vw, 2.5rem);
  font-weight: 700;
  letter-spacing: -.02em;
  color: var(--ink);
}
.section-sub {
  font-size: 1rem;
  color: var(--ink3);
  margin-top: .75rem;
  max-width: 520px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.7;
}

/* Error banner */
.error-banner {
  max-width: 480px;
  margin: 1rem auto 0;
  padding: .875rem 1.25rem;
  background: var(--red-l);
  color: var(--red);
  border-radius: var(--r2);
  font-size: .875rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
</style>
</head>
<body>

<!-- Nav -->
<nav class="nav">
  <a href="/" class="nav-brand">
    <div class="nav-logo"><img src=favicon.png width=30></div>
    TOTPVault
  </a>
  <div class="nav-spacer"></div>
  <a href="#get-started" class="btn btn-primary btn-sm">Get started</a>
</nav>

<?php if (!empty($_GET['error'])): ?>
<div class="error-banner">
  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
    <circle cx="12" cy="12" r="10"/>
    <line x1="12" y1="8" x2="12" y2="12"/>
    <line x1="12" y1="16" x2="12.01" y2="16"/>
  </svg>
  <?= htmlspecialchars($_GET['error']) ?>
</div><br>
<?php endif; ?>

<?php if (!empty($_GET['magic']) && $_GET['magic'] === 'sent'): ?>
<div style="max-width:420px;margin:1.5rem auto 0;padding:1rem 1.25rem;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:var(--r2);font-size:.9rem">
  ✅ Check your inbox — we sent a sign-in link to <strong><?= htmlspecialchars($_GET['email'] ?? '') ?></strong>. It will expire in 15 minutes.
</div><br>
<?php endif; ?>

<!-- Hero -->
<section class="hero">
  <div class="hero-eyebrow">
    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
      <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
    </svg>
    Server-side secret protection
  </div>
  <h1>TOTP codes without <span>exposing secrets</span></h1>
  <p class="hero-sub">
    TOTPVault generates your one-time passwords on the server — your secret keys never reach the browser. 
    Supports SHA1, SHA256, & SHA512. Share OTP codes with teammates without exposing secrets.
  </p>
  <div class="hero-ctas">
    <a href="#get-started" class="btn btn-primary btn-lg">Start for free</a>
    <a href="#features" class="btn btn-secondary btn-lg">See how it works</a>
  </div>
</section>

<!-- Features -->
<section class="features" id="features">
  <div style="text-align:center">
    <div class="section-label">Features</div>
    <div class="section-title">Everything you need for secure 2FA</div>
    <p class="section-sub">
      Manage all your TOTP tokens in one place, with enterprise-grade security built from the ground up.
    </p>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">🔐</div>
      <div class="font-semibold" style="margin-bottom:.5rem">Server-side generation</div>
      <div class="text-sm text-ink3">
        OTP codes are generated on the server. Secret keys are AES-256-GCM encrypted at rest and never transmitted to the client.
      </div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⚙️</div>
      <div class="font-semibold" style="margin-bottom:.5rem">All TOTP standards</div>
      <div class="text-sm text-ink3">
        Full support for SHA1, SHA256, and SHA512. Configure 6, 8, or 10-digit codes. Custom periods supported.
      </div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">👥</div>
      <div class="font-semibold" style="margin-bottom:.5rem">Secure sharing</div>
      <div class="text-sm text-ink3">
        Share OTP profiles with colleagues via email. They receive codes — never the secrets. Works with Google, Microsoft, and GitHub, or just an email address using magic links.
      </div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🌐</div>
      <div class="font-semibold" style="margin-bottom:.5rem">Social login</div>
      <div class="text-sm text-ink3">
        Sign in with Google, Microsoft, or GitHub. Multiple providers link automatically when using the same email.
      </div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⚡</div>
      <div class="font-semibold" style="margin-bottom:.5rem">Live countdown</div>
      <div class="text-sm text-ink3">
        Real-time timer shows exactly when codes refresh. Auto-refresh before expiry. Copy codes with one click.
      </div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🎨</div>
      <div class="font-semibold" style="margin-bottom:.5rem">Organised & labelled</div>
      <div class="text-sm text-ink3">
        Color-code your profiles, add issuers, choose icons. Keep dozens of tokens organised at a glance.
      </div>
    </div>
  </div>
</section>

<!-- Providers / Sign in -->
<section class="providers" id="get-started">
  <div class="providers-inner">
    <div class="section-label">Get started</div>
    <div class="section-title">Sign in to TOTPVault</div>
    <p class="section-sub">Enter your email to receive a sign-in link, or continue with an existing account.</p>

    <div style="max-width:420px;margin:2rem auto 0">
      <form action="/auth/magic-request" method="POST" style="display:flex;gap:.625rem">
        <input type="email" name="email" placeholder="you@example.com" required
          style="flex:1;padding:.75rem 1rem;border:1px solid var(--line);border-radius:var(--r2);font-size:.9375rem;font-family:inherit;background:var(--bg);color:var(--ink);outline:none"
          onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--line)'" aria-label="Email">
        <button type="submit" class="btn btn-primary" style="white-space:nowrap">Send link</button>
      </form>
      <p style="text-align:center;color:var(--ink4);font-size:.8rem;margin:.75rem 0">— or continue with —</p>
    </div>

    <div class="provider-btns">
      <a href="/auth/login/google" class="provider-btn" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continue with Google
      </a>

      <a href="/auth/login/microsoft" class="provider-btn" rel="noopener noreferrer">
        <svg viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg">
          <path fill="#f25022" d="M0 0h11v11H0z"/>
          <path fill="#00a4ef" d="M12 0h11v11H12z"/>
          <path fill="#7fba00" d="M0 12h11v11H0z"/>
          <path fill="#ffb900" d="M12 12h11v11H12z"/>
        </svg>
        Continue with Microsoft
      </a>

      <a href="/auth/login/github" class="provider-btn" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
        </svg>
        Continue with GitHub
      </a>
    </div>
  </div>
</section>

<footer class="footer">
<p>© <?= date('Y') ?> Token2  —   Made in Switzerland  —  Secrets never leave the server  —  <a href="https://github.com/token2/TOTPVault"><i class="fa-brands fa-github"></i> Open Source</a></p>
</footer>

</body>
</html>