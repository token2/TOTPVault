<?php
$pageTitle = 'Dashboard — TOTPVault';
require __DIR__ . '/layout.php';
$csrfToken = csrf_token();
$avatar = $user['avatar_url'] ? htmlspecialchars($user['avatar_url']) : null;
$initial = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));


$FA_BRAND_ICONS = [
  'fa-github','fa-google','fa-microsoft','fa-apple','fa-amazon','fa-facebook',
  'fa-twitter','fa-instagram','fa-linkedin','fa-slack','fa-discord','fa-telegram',
  'fa-whatsapp','fa-dropbox','fa-spotify','fa-twitch','fa-youtube','fa-reddit',
  'fa-gitlab','fa-bitbucket','fa-docker','fa-aws','fa-cloudflare','fa-digital-ocean',
  'fa-stripe','fa-paypal','fa-shopify','fa-wordpress','fa-jenkins','fa-jira',
  'fa-confluence','fa-trello','fa-npm','fa-node','fa-react','fa-vuejs','fa-angular',
  'fa-laravel','fa-php','fa-python','fa-java','fa-swift','fa-android','fa-windows',
  'fa-linux','fa-ubuntu','fa-firefox','fa-chrome','fa-safari','fa-steam',
  'fa-playstation','fa-xbox',
];

$FA_SOLID_ICONS = [
  'fa-shield-halved','fa-key','fa-lock','fa-lock-open','fa-user','fa-users',
  'fa-building','fa-globe','fa-server','fa-database','fa-cloud','fa-code',
  'fa-terminal','fa-mobile','fa-laptop','fa-desktop','fa-wifi','fa-envelope',
  'fa-bell','fa-star','fa-bolt','fa-fire','fa-gear','fa-wrench',
  'fa-briefcase','fa-chart-bar','fa-credit-card','fa-wallet','fa-shop',
  'fa-robot','fa-microchip',
];


?>

<style>
/* ── Layout ── */
.dash-layout{display:flex;min-height:calc(100vh - 60px)}
.sidebar{width:260px;flex-shrink:0;border-right:1px solid var(--line);padding:1.5rem 1rem;display:flex;flex-direction:column;gap:.25rem;background:var(--bg)}
.main{flex:1;padding:2rem 2.5rem;max-width:100%;overflow:auto}
@media(max-width:768px){.sidebar{display:none}.main{padding:1.25rem 1rem}}

/* ── Sidebar ── */
.sidebar-section{font-size:.6875rem;font-weight:600;color:var(--ink4);letter-spacing:.08em;text-transform:uppercase;padding:.5rem .75rem .25rem;margin-top:.75rem}
.sidebar-link{display:flex;align-items:center;gap:.625rem;padding:.5rem .75rem;border-radius:var(--r);font-size:.875rem;color:var(--ink3);cursor:pointer;transition:all .15s;border:none;background:none;width:100%;text-align:left;text-decoration:none}
.sidebar-link:hover,.sidebar-link.active{background:var(--bg2);color:var(--ink)}
.sidebar-link svg{width:16px;height:16px;flex-shrink:0}
.sidebar-profile-count{margin-left:auto;font-size:.75rem;background:var(--line);color:var(--ink4);border-radius:999px;padding:.1rem .5rem}

/* ── Topbar ── */
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.topbar h1{font-size:1.5rem;font-weight:700;letter-spacing:-.025em}

/* ── Grid ── */
.profiles-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem}

/* ── OTP Card ── */
.otp-card{background:var(--bg);border:1px solid var(--line);border-radius:var(--r3);overflow:hidden;transition:box-shadow .2s,transform .2s}
.otp-card:hover{box-shadow:var(--shadow3);transform:translateY(-1px)}
.otp-card-header{padding:1rem 1.25rem .75rem;display:flex;align-items:center;gap:.75rem;border-bottom:1px solid var(--line2)}
.otp-card-color{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.otp-card-name{font-weight:600;font-size:.9375rem;flex:1;min-width:0}
.otp-card-issuer{font-size:.75rem;color:var(--ink4);truncate:true}
.otp-card-body{padding:1.25rem}
.otp-code{font-family:'JetBrains Mono',monospace;font-size:2rem;font-weight:500;letter-spacing:.15em;color:var(--ink);cursor:pointer;transition:color .15s;line-height:1}
.otp-code:hover{color:var(--blue)}
.otp-code.refreshing{color:var(--ink4)}
.otp-code-wrap{display:flex;align-items:center;gap:.75rem;margin-bottom:.875rem}
.otp-copy-btn{padding:.375rem .625rem;font-size:.75rem;border:1px solid var(--line);border-radius:var(--r);background:var(--bg2);color:var(--ink3);cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.25rem}
.otp-copy-btn:hover{background:var(--blue-l);color:var(--blue);border-color:transparent}

/* ── Progress bar ── */
.progress-wrap{display:flex;align-items:center;gap:.75rem}
.progress-bar{flex:1;height:4px;background:var(--line);border-radius:2px;overflow:hidden}
.progress-fill{height:100%;border-radius:2px;background:var(--blue);transition:width .5s linear}
.progress-fill.warning{background:var(--amber)}
.progress-fill.danger{background:var(--red)}
.progress-time{font-size:.75rem;color:var(--ink4);width:32px;text-align:right;font-family:'JetBrains Mono',monospace}

/* ── Card footer ── */
.otp-card-footer{padding:.75rem 1.25rem;border-top:1px solid var(--line2);display:flex;align-items:center;gap:.5rem}
.otp-meta{font-size:.6875rem;color:var(--ink4);flex:1;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}
.otp-meta span{display:flex;align-items:center;gap:.2rem}
.otp-actions{display:flex;gap:.25rem}

/* ── Shared badge ── */
.shared-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;background:var(--amber-l);color:var(--amber);border-radius:999px;font-size:.6875rem;font-weight:600}

/* ── Empty ── */
.empty-card{text-align:center;padding:4rem 2rem;border:2px dashed var(--line);border-radius:var(--r3);color:var(--ink3)}
.empty-card .icon{font-size:3rem;margin-bottom:1rem}
.empty-card h2{font-size:1.25rem;font-weight:600;color:var(--ink);margin-bottom:.5rem}
.empty-card p{font-size:.9375rem;max-width:360px;margin:0 auto 1.5rem}

/* ── Modal wider ── */
.modal{background:var(--bg);border-radius:var(--r3);box-shadow:var(--shadow4);width:100%;max-width:740px;max-height:90vh;display:flex;flex-direction:column;animation:slideUp .2s ease}
.modal-body{padding:1.5rem;overflow-y:auto;flex:1;-webkit-overflow-scrolling:touch}
@media(max-width:580px){
  .modal{max-height:100vh;border-radius:var(--r3) var(--r3) 0 0;position:fixed;bottom:0;left:0;right:0;width:100%}
  .modal-backdrop{align-items:flex-end}
  .modal-body{padding:1rem}
}

/* ── 2-col form grid ── */
.form-2col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem 1.75rem;align-items:start}
.form-2col .col{display:flex;flex-direction:column;gap:.875rem}

/* ── Mobile tabs (hidden on desktop) ── */
.mob-tabs{display:none}
@media(max-width:580px){
  .form-2col{display:block}
  .form-2col .col{display:none}
  .form-2col .col.tab-active{display:flex;flex-direction:column;gap:.875rem}
  .mob-tabs{display:flex;border-bottom:1px solid var(--line);margin:-1rem -1rem .875rem;padding:0 1rem}
  .mob-tab{flex:1;padding:.625rem .5rem;font-size:.875rem;font-weight:500;color:var(--ink3);background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-1px;cursor:pointer;transition:color .15s,border-color .15s}
  .mob-tab.active{color:var(--blue);border-bottom-color:var(--blue)}
}

/* ── Form section label ── */
.form-section{font-size:.6875rem;font-weight:700;color:var(--ink4);letter-spacing:.07em;text-transform:uppercase;padding-bottom:.375rem;border-bottom:1px solid var(--line);margin-bottom:.125rem}

/* ── Modal specifics ── */
.color-grid{display:grid;grid-template-columns:repeat(8,1fr);gap:.4rem}
.color-swatch{width:26px;height:26px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:transform .15s,border-color .15s}
.color-swatch.selected,.color-swatch:hover{transform:scale(1.2);border-color:var(--ink)}
.secret-row{display:flex;gap:.5rem;align-items:center}
.secret-row .input{font-family:'JetBrains Mono',monospace;font-size:.8125rem;letter-spacing:.05em}

/* ── Share panel ── */
.share-list{margin-top:1rem;display:flex;flex-direction:column;gap:.5rem}
.share-row{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border:1px solid var(--line);border-radius:var(--r2);font-size:.875rem}
.share-email{flex:1;min-width:0;truncate:true;font-weight:500}
.share-status{font-size:.75rem;color:var(--ink4)}

/* ── User menu ── */
.user-menu-wrap{position:relative}
.user-menu{position:absolute;top:calc(100% + .5rem);right:0;background:var(--bg);border:1px solid var(--line);border-radius:var(--r2);box-shadow:var(--shadow4);width:220px;z-index:200;overflow:hidden;animation:slideUp .15s ease}
.user-menu-item{display:flex;align-items:center;gap:.625rem;padding:.625rem 1rem;font-size:.875rem;color:var(--ink2);cursor:pointer;transition:background .1s}
.user-menu-item:hover{background:var(--bg2)}
.user-menu-item svg{width:15px;height:15px;color:var(--ink4)}
.user-info{padding:.875rem 1rem;border-bottom:1px solid var(--line)}
.user-info-name{font-weight:600;font-size:.875rem;truncate:true}
.user-info-email{font-size:.75rem;color:var(--ink4);truncate:true}

/* ── Toggle ── */
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;border-radius:34px;background:var(--line);cursor:pointer;transition:background .2s}
.toggle-slider:before{content:'';position:absolute;left:2px;top:2px;width:20px;height:20px;border-radius:50%;background:white;transition:transform .2s;box-shadow:var(--shadow)}
.toggle input:checked + .toggle-slider{background:var(--blue)}
.toggle input:checked + .toggle-slider:before{transform:translateX(20px)}

/* ── Reveal hint ── */
.reveal-hint{font-size:.7rem;color:var(--ink4);margin-top:4px;display:flex;align-items:center;gap:.25rem}
</style>

<!-- Nav -->
<nav class="nav">
  <div class="nav-brand">
    <div class="nav-logo"><img src=favicon.png width=30></div>
    TOTPVault
  </div>
  <div class="nav-spacer"></div>
  <div class="user-menu-wrap">
    <button onclick="toggleUserMenu()" style="display:flex;align-items:center;gap:.625rem;background:none;border:1px solid var(--line);border-radius:var(--r2);padding:.375rem .75rem .375rem .375rem;cursor:pointer;font-size:.875rem;font-weight:500;color:var(--ink)">
      <div class="avatar">
        <?php if ($avatar): ?>
          <img src="<?= $avatar ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= $initial ?>
        <?php endif; ?>
      </div>
      <?= htmlspecialchars(explode(' ', $user['name'] ?? $user['email'])[0]) ?>
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="user-menu hidden" id="user-menu">
      <div class="user-info">
        <div class="user-info-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
        <div class="user-info-email"><?= htmlspecialchars($user['email']) ?></div>
      </div>
      <a href="/auth/logout" class="user-menu-item">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign out
      </a>
    </div>
  </div>
</nav>

<!-- Dashboard layout -->
<div class="dash-layout">
  <aside class="sidebar">
    <div class="sidebar-section">Tokens</div>
    <button class="sidebar-link active" onclick="filterProfiles('all')">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      All tokens
      <span class="sidebar-profile-count" id="count-all"><?= count($profiles) ?></span>
    </button>
    <button class="sidebar-link" onclick="filterProfiles('mine')">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      My tokens
      <span class="sidebar-profile-count" id="count-mine"><?= count(array_filter($profiles, fn($p)=>$p['is_owner'])) ?></span>
    </button>
    <button class="sidebar-link" onclick="filterProfiles('shared')">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Shared with me
      <span class="sidebar-profile-count" id="count-shared"><?= count(array_filter($profiles, fn($p)=>!$p['is_owner'])) ?></span>
    </button>
    <div style="margin-top:auto;padding:.5rem .75rem">
      <button class="btn btn-primary w-full" onclick="openAddModal()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add token
      </button>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1 id="view-title">All tokens</h1>
      <button class="btn btn-primary" onclick="openAddModal()" style="display:none" id="mob-add-btn">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add token
      </button>
    </div>

    <?php if (empty($profiles)): ?>
    <div class="empty-card">
      <div class="icon">🔒</div>
      <h2>No tokens yet</h2>
      <p>Add your first TOTP token to get started. Your secret keys are encrypted and never leave the server.</p>
      <button class="btn btn-primary btn-lg" onclick="openAddModal()">Add your first token</button>
    </div>
    <?php else: ?>
    <div class="profiles-grid" id="profiles-grid">
      <?php foreach ($profiles as $p): ?>
      <?php
        $pId     = (int)$p['id'];
        $pName   = htmlspecialchars($p['name']);
        $pIssuer = htmlspecialchars($p['issuer'] ?? '');
        $pColor  = htmlspecialchars($p['color']);
        $pAlgo   = htmlspecialchars($p['algorithm']);
        $pDigits = (int)$p['digits'];
        $pPeriod = (int)$p['period'];
        $isOwner = (bool)$p['is_owner'];
        $canEdit = (bool)$p['can_edit'];
        $pHide   = (int)($p['hide_code'] ?? 0);
        $pIcon   = htmlspecialchars($p['icon'] ?? 'fa-shield-halved');
      ?>
      <div class="otp-card" data-id="<?= $pId ?>" data-owner="<?= $isOwner?'1':'0' ?>" data-hide="<?= $pHide ?>">
        <div class="otp-card-header">
          <div class="otp-card-color" style="background:<?= $pColor ?>"></div>
          <div style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:<?= $pColor ?>">
            <i class="<?php echo (in_array($pIcon, $FA_BRAND_ICONS) ? 'fab ' : 'fa ') . $pIcon; ?> <?= $pIcon ?>"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div class="otp-card-name truncate"><?= $pName ?></div>
            <?php if ($pIssuer): ?>
            <div class="otp-card-issuer truncate"><?= $pIssuer ?></div>
            <?php endif; ?>
          </div>
          <?php if (!$isOwner): ?>
          <span class="shared-chip">
            <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Shared
          </span>
          <?php endif; ?>
        </div>
        <div class="otp-card-body">
          <div class="otp-code-wrap">
            <div class="otp-code" id="code-<?= $pId ?>" onclick="copyCode(<?= $pId ?>)" title="<?= $pHide ? 'Click to reveal' : 'Click to copy' ?>">
              <span class="dots">••••••</span>
            </div>
            <button class="otp-copy-btn" onclick="copyCode(<?= $pId ?>)">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
              <?= $pHide ? 'Reveal' : 'Copy' ?>
            </button>
          </div>
          <?php if ($pHide): ?>
          <div class="reveal-hint" id="hint-<?= $pId ?>">
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            hides after 10 seconds
          </div>
          <?php endif; ?>
          <div class="progress-wrap" style="<?= $pHide ? 'margin-top:8px' : '' ?>">
            <div class="progress-bar"><div class="progress-fill" id="prog-<?= $pId ?>"></div></div>
            <div class="progress-time" id="time-<?= $pId ?>">—</div>
          </div>
        </div>
        <div class="otp-card-footer">
          <div class="otp-meta">
            <span><?= $pAlgo ?></span>
            <span><?= $pDigits ?> digits</span>
            <span><?= $pPeriod ?>s</span>
          </div>
          <div class="otp-actions">
            <?php if ($isOwner): ?>
            <button class="btn btn-ghost btn-icon btn-sm" title="Share" onclick="openShareModal(<?= $pId ?>, '<?= $pName ?>')">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <button class="btn btn-ghost btn-icon btn-sm" title="Edit" onclick="openEditModal(<?= $pId ?>)">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <?php endif; ?>
            <?php if ($isOwner): ?>
            <button class="btn btn-ghost btn-icon btn-sm" title="Delete" onclick="deleteProfile(<?= $pId ?>, '<?= $pName ?>')" style="color:var(--red)">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- ── Add/Edit Modal ── -->
<div id="profile-modal" class="modal-backdrop hidden">
  <div class="modal">
    <div class="modal-header">
      <div>
        <h2 class="text-xl font-bold" id="modal-title">Add token</h2>
        <p class="text-sm text-ink3" style="margin-top:.25rem">Enter your TOTP secret key and settings</p>
      </div>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('profile-modal')">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <input type="hidden" id="edit-id" value="">

      <!-- Mobile tabs (hidden on desktop) -->
      <div class="mob-tabs">
        <button class="mob-tab active" onclick="switchTab(0)"><i class="fa-solid fa-key" style="margin-right:.375rem"></i>Token</button>
        <button class="mob-tab" onclick="switchTab(1)"><i class="fa-solid fa-palette" style="margin-right:.375rem"></i>Appearance</button>
      </div>

      <div class="form-2col">

        <!-- LEFT column -->
        <div class="col tab-active" id="form-col-0">
          <div class="form-section">Identity</div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
            <div class="field">
              <label class="label">Name *</label>
              <input class="input" id="f-name" placeholder="e.g. GitHub…">
            </div>
            <div class="field">
              <label class="label">Issuer / Service</label>
              <input class="input" id="f-issuer" placeholder="e.g. GitHub">
            </div>
          </div>

          <div class="form-section">Secret key</div>
<div class="field">
            <div class="secret-row">
              <input class="input" id="f-secret" placeholder="Base32 secret…" style="flex:1">
              <button class="btn btn-secondary btn-sm" onclick="generateSecret()" title="Generate random secret">
                <i class="fa-solid fa-rotate"></i>
              </button>
              <button class="btn btn-secondary btn-sm" onclick="toggleQrPanel()" title="Scan QR code">
                <i class="fa-solid fa-qrcode"></i>
              </button>
            </div>
            <p class="hint" style="margin-top:.375rem">Enter manually or scan a QR code from your authenticator app.</p>
          </div>

          <!-- QR panel -->
          <div id="qr-panel" style="display:none;max-height:200px;overflow-y:auto;padding:.875rem;background:var(--bg2);border:1px solid var(--line);border-radius:var(--r2)">
            <div style="display:flex;flex-direction:column;gap:.625rem">
              <div id="qr-dropzone"
                onclick="document.getElementById('qr-file').click()"
                ondragover="event.preventDefault();this.style.borderColor='var(--blue)'"
                ondragleave="this.style.borderColor='var(--line)'"
                ondrop="handleQrDrop(event)"
                style="width:100%;padding:.625rem 1rem;border:2px dashed var(--line);border-radius:var(--r2);cursor:pointer;transition:border-color .15s;display:flex;align-items:center;justify-content:center;gap:.625rem">
                <i class="fa-solid fa-qrcode" style="color:var(--ink4);font-size:1.1rem"></i>
                <div>
                  <span style="font-size:.875rem;font-weight:500;color:var(--ink2)">Drop QR or click to upload</span>
                  <span style="font-size:.75rem;color:var(--ink4);margin-left:.375rem">· Ctrl+V to paste</span>
                </div>
              </div>
              <canvas id="qr-canvas" style="display:none;position:absolute;pointer-events:none;opacity:0"></canvas>
              <div id="qr-result" style="display:none;padding:.625rem .875rem;border-radius:var(--r);font-size:.8125rem"></div>
            </div>
            <input type="file" id="qr-file" accept="image/*" style="display:none" onchange="handleQrFile(this.files[0])">
          </div>

          <div class="form-section">TOTP settings</div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem">
            <div class="field">
              <label class="label">Algorithm</label>
              <select class="input" id="f-algorithm">
                <option value="SHA1">SHA1</option>
                <option value="SHA256">SHA256</option>
                <option value="SHA512">SHA512</option>
              </select>
            </div>
            <div class="field">
              <label class="label">Digits</label>
              <select class="input" id="f-digits">
                <option value="6">6 digits</option>
                <option value="8">8 digits</option>
                <option value="10">10 digits</option>
              </select>
            </div>
            <div class="field">
              <label class="label">Period (s)</label>
              <input class="input" id="f-period" type="number" value="30" min="15" max="300">
            </div>
          </div>

          <!-- Hide toggle -->
          <div class="field" style="flex-direction:row;align-items:center;gap:1rem;padding:.75rem;background:var(--bg2);border:1px solid var(--line);border-radius:var(--r2)">
            <label class="toggle" style="flex-shrink:0">
              <input type="checkbox" id="f-hide">
              <span class="toggle-slider"></span>
            </label>
            <div>
              <div class="label" style="margin:0 0 .125rem">Hide code until clicked</div>
              <div class="hint">Reveals for 10 seconds when tapped, then hides again</div>
            </div>
          </div>
        </div>

        <!-- RIGHT column -->
        <div class="col" id="form-col-1">
          <div class="form-section">Appearance</div>

          <div class="field">
            <label class="label">Colour</label>
            <div class="color-grid" id="color-grid"></div>
            <input type="hidden" id="f-color" value="#6366f1">
          </div>

          <div class="field">
            <label class="label">Icon</label>
            <input class="input" id="icon-search" placeholder="Search: google, github, apple..." oninput="filterIcons(this.value)" autocomplete="off">
            <div id="icon-grid" style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;max-height:260px;overflow-y:auto;padding:2px 0"></div>
            <input type="hidden" id="f-icon" value="fa-shield-halved">
            <input type="hidden" id="f-icon-prefix" value="fa-brands">
          </div>
        </div>

      </div><!-- /form-2col -->
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('profile-modal')">Cancel</button>
      <button class="btn btn-primary" id="save-btn" onclick="saveProfile()">Save token</button>
    </div>
  </div>
</div>

<!-- ── Share Modal ── -->
<div id="share-modal" class="modal-backdrop hidden">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div>
        <h2 class="text-xl font-bold">Share token</h2>
        <p class="text-sm text-ink3" style="margin-top:.25rem" id="share-modal-sub">Collaborators can view OTP codes without seeing the secret.</p>
      </div>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('share-modal')">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="share-profile-id">
      <div style="display:flex;gap:.5rem;margin-bottom:.75rem">
        <input class="input" id="share-email" type="email" placeholder="colleague@company.com" style="flex:1">
        <button class="btn btn-primary" onclick="addShare()">Invite</button>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;cursor:pointer;margin-bottom:1rem">
        <input type="checkbox" id="share-can-edit" style="width:16px;height:16px">
        Allow editing token settings
      </label>
      <div id="share-list-wrap">
        <div class="text-sm text-ink4">Loading shares…</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('share-modal')">Done</button>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
const COLORS = ['#6366f1','#3b82f6','#06b6d4','#10b981','#84cc16','#f59e0b','#f97316','#ef4444','#ec4899','#8b5cf6','#0ea5e9','#14b8a6','#22c55e','#eab308','#f43f5e','#6b7280'];

// ── Color grid ──
function initColorGrid(selected) {
  const g = document.getElementById('color-grid');
  g.innerHTML = COLORS.map(c =>
    `<div class="color-swatch ${c===selected?'selected':''}" style="background:${c}" data-color="${c}" onclick="selectColor('${c}')"></div>`
  ).join('');
}
function selectColor(c) {
  document.getElementById('f-color').value = c;
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.toggle('selected', s.dataset.color===c));
}

// ── OTP refresh engine ──
const REFRESH_INTERVAL = 1000;

async function fetchOTP(id) {
  const r = await fetch(`/api/otp?id=${id}`);
  const d = await r.json();
  if (d.error) return null;
  return d;
}

function formatCode(code, digits) {
  if (digits === 6) return code.slice(0,3) + ' ' + code.slice(3);
  if (digits === 8) return code.slice(0,4) + ' ' + code.slice(4);
  return code.slice(0,3) + ' ' + code.slice(3,6) + ' ' + code.slice(6);
}

function updateCard(id, data) {
  const codeEl = document.getElementById(`code-${id}`);
  const progEl = document.getElementById(`prog-${id}`);
  const timeEl = document.getElementById(`time-${id}`);
  if (!codeEl) return;

  const card     = codeEl.closest('.otp-card');
  const digits   = parseInt(card.querySelector('.otp-meta').textContent.match(/(\d+) digit/)[1]);
  const isHidden = card.dataset.hide === '1';

  codeEl.dataset.rawCode = data.code;

  if (isHidden && !codeEl.dataset.revealed) {
    const dotMap = {6:'••• •••', 8:'•••• ••••', 10:'••• ••• ••••'};
    codeEl.textContent = dotMap[digits] || '••• •••';
    codeEl.style.color = 'var(--ink4)';
    codeEl.title = 'Click to reveal';
  } else {
    codeEl.textContent = formatCode(data.code, digits);
    codeEl.style.color = '';
  }

  const pct = 100 - data.progress;
  progEl.style.width = pct + '%';
  progEl.className = 'progress-fill' + (data.remaining <= 5 ? ' danger' : data.remaining <= 10 ? ' warning' : '');
  timeEl.textContent = data.remaining + 's';
  if (data.remaining <= 2) codeEl.classList.add('refreshing');
  else codeEl.classList.remove('refreshing');
}

let refreshTimers = {};
async function startRefreshing(id) {
  const data = await fetchOTP(id);
  if (!data) return;
  updateCard(id, data);
  clearInterval(refreshTimers[id]);
  refreshTimers[id] = setInterval(async () => {
    const d = await fetchOTP(id);
    if (d) updateCard(id, d);
  }, REFRESH_INTERVAL);
}

function initAllCards() {
  document.querySelectorAll('.otp-card').forEach(card => startRefreshing(parseInt(card.dataset.id)));
}

// ── Copy / Reveal ──
async function copyCode(id) {
  const el   = document.getElementById(`code-${id}`);
  const card = el.closest('.otp-card');
  const raw  = el.dataset.rawCode;
  if (!raw) { toast('Code is loading…', 'info'); return; }
  if (card.dataset.hide === '1' && !el.dataset.revealed) { revealCode(id); return; }
  try {
    await navigator.clipboard.writeText(raw);
    toast('Code copied!', 'success');
    const btn = card.querySelector('.otp-copy-btn');
    const orig = btn.innerHTML;
    btn.innerHTML = '✓ Copied';
    setTimeout(() => btn.innerHTML = orig, 1500);
  } catch { toast('Copy failed', 'error'); }
}

function revealCode(id) {
  const el     = document.getElementById(`code-${id}`);
  const card   = el.closest('.otp-card');
  const raw    = el.dataset.rawCode;
  const digits = parseInt(card.querySelector('.otp-meta').textContent.match(/(\d+) digit/)[1]);
  const hint   = document.getElementById(`hint-${id}`);
  const btn    = card.querySelector('.otp-copy-btn');
  el.dataset.revealed = '1';
  el.textContent = formatCode(raw, digits);
  el.style.color = '';
  el.title = 'Click to copy';
  if (hint) hint.style.display = 'none';
  if (btn) btn.innerHTML = '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy';
  resetHideTimer(id, digits);
}

function resetHideTimer(id, digits) {
  const el   = document.getElementById(`code-${id}`);
  const hint = document.getElementById(`hint-${id}`);
  const card = el.closest('.otp-card');
  const btn  = card.querySelector('.otp-copy-btn');
  clearTimeout(el._hideTimer);
  el._hideTimer = setTimeout(() => {
    delete el.dataset.revealed;
    const dotMap = {6:'••• •••', 8:'•••• ••••', 10:'••• ••• ••••'};
    el.textContent = dotMap[digits] || '••• •••';
    el.style.color = 'var(--ink4)';
    el.title = 'Click to reveal';
    if (hint) hint.style.display = '';
    if (btn) btn.innerHTML = '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Reveal';
  }, 10000);
}

// ── Modals ──
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function openAddModal() {
  document.getElementById('edit-id').value = '';
  document.getElementById('modal-title').textContent = 'Add token';
  document.getElementById('save-btn').textContent = 'Save token';
  document.getElementById('f-name').value = '';
  document.getElementById('f-issuer').value = '';
  document.getElementById('f-secret').value = '';
  document.getElementById('f-algorithm').value = 'SHA1';
  document.getElementById('f-digits').value = '6';
  document.getElementById('f-period').value = '30';
  document.getElementById('f-color').value = '#6366f1';
  document.getElementById('f-hide').checked = false;
  document.getElementById('qr-panel').style.display = 'none';
  document.getElementById('qr-result').style.display = 'none';
  switchTab(0);
  initColorGrid('#6366f1');
  document.getElementById('f-icon').value = 'fa-shield-halved';
  document.getElementById('f-icon-prefix').value = 'fa-solid';
  document.getElementById('icon-search').value = '';
  renderIcons('');
  document.getElementById('profile-modal').classList.remove('hidden');
  document.getElementById('f-name').focus();
}

async function openEditModal(id) {
  const r = await fetch(`/api/profiles/${id}`);
  const p = await r.json();
  if (p.error) { toast(p.error, 'error'); return; }
  document.getElementById('edit-id').value = id;
  document.getElementById('modal-title').textContent = 'Edit token';
  document.getElementById('save-btn').textContent = 'Update token';
  document.getElementById('f-name').value = p.name;
  document.getElementById('f-issuer').value = p.issuer || '';
  document.getElementById('f-secret').value = '';
  document.getElementById('f-algorithm').value = p.algorithm;
  document.getElementById('f-digits').value = p.digits;
  document.getElementById('f-period').value = p.period;
  document.getElementById('f-color').value = p.color;
  document.getElementById('f-hide').checked = !!parseInt(p.hide_code || 0);
  document.getElementById('qr-panel').style.display = 'none';
  document.getElementById('qr-result').style.display = 'none';
  switchTab(0);
  initColorGrid(p.color);
  document.getElementById('f-icon').value = p.icon || 'fa-shield-halved';
  document.getElementById('f-icon-prefix').value = p.icon_prefix || 'fa-solid';
  document.getElementById('icon-search').value = '';
  renderIcons('');
  document.getElementById('profile-modal').classList.remove('hidden');
}

async function saveProfile() {
  const id = document.getElementById('edit-id').value;
  const body = {
    name:      document.getElementById('f-name').value.trim(),
    issuer:    document.getElementById('f-issuer').value.trim(),
    secret:    document.getElementById('f-secret').value.trim(),
    algorithm: document.getElementById('f-algorithm').value,
    digits:    document.getElementById('f-digits').value,
    period:    document.getElementById('f-period').value,
    color:     document.getElementById('f-color').value,
    icon:      document.getElementById('f-icon').value,
    hide_code: document.getElementById('f-hide').checked ? 1 : 0,
  };
  if (!body.name) { toast('Name is required', 'error'); return; }
  if (!id && !body.secret) { toast('Secret key is required', 'error'); return; }
  const url    = id ? `/api/profiles/${id}` : '/api/profiles';
  const method = id ? 'PUT' : 'POST';
  const r = await apiFetch(url, method, body);
  if (r.error) { toast(r.error, 'error'); return; }
  toast(id ? 'Token updated' : 'Token added', 'success');
  closeModal('profile-modal');
  setTimeout(() => location.reload(), 500);
}

async function deleteProfile(id, name) {
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  const r = await apiFetch(`/api/profiles/${id}`, 'DELETE', {});
  if (r.error) { toast(r.error, 'error'); return; }
  document.querySelector(`.otp-card[data-id="${id}"]`)?.remove();
  toast('Token deleted', 'success');
  updateCounts();
}

async function generateSecret() {
  const r = await fetch('/api/generate-secret');
  const d = await r.json();
  document.getElementById('f-secret').value = d.secret;
}

// ── Share modal ──
async function openShareModal(id, name) {
  document.getElementById('share-profile-id').value = id;
  document.getElementById('share-modal-sub').textContent = `Sharing: ${name}`;
  document.getElementById('share-email').value = '';
  document.getElementById('share-modal').classList.remove('hidden');
  await loadShares(id);
}

async function loadShares(id) {
  const wrap = document.getElementById('share-list-wrap');
  const r = await fetch(`/api/profiles/${id}/shares`);
  const shares = await r.json();
  if (!shares.length) {
    wrap.innerHTML = '<div class="text-sm text-ink4" style="padding:.5rem 0">No shares yet. Invite someone above.</div>';
    return;
  }
  wrap.innerHTML = `<div class="share-list">${shares.map(s => `
    <div class="share-row">
      <div class="avatar" style="width:28px;height:28px;font-size:.6875rem">${(s.user_name||s.shared_with_email).charAt(0).toUpperCase()}</div>
      <div style="flex:1;min-width:0">
        <div class="share-email truncate">${s.shared_with_email}</div>
        <div class="share-status">${s.can_edit ? 'Can edit' : 'View only'} · ${s.shared_with_user_id ? 'Active' : 'Pending'}</div>
      </div>
      <button class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="removeShare(${id},'${s.shared_with_email}')">Remove</button>
    </div>`).join('')}</div>`;
}

async function addShare() {
  const id      = document.getElementById('share-profile-id').value;
  const email   = document.getElementById('share-email').value.trim();
  const canEdit = document.getElementById('share-can-edit').checked;
  if (!email) { toast('Enter an email address', 'error'); return; }
  const r = await apiFetch(`/api/profiles/${id}/shares`, 'POST', { email, can_edit: canEdit });
  if (r.error) { toast(r.error, 'error'); return; }
  toast(`Shared with ${email}`, 'success');
  document.getElementById('share-email').value = '';
  await loadShares(id);
}

async function removeShare(id, email) {
  const r = await apiFetch(`/api/profiles/${id}/shares`, 'DELETE', { email });
  if (r.error) { toast(r.error, 'error'); return; }
  toast('Share removed', 'success');
  await loadShares(id);
}

// ── Filtering ──
function filterProfiles(type) {
  document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
  document.querySelectorAll('.sidebar-link')[['all','mine','shared'].indexOf(type)]?.classList.add('active');
  const titles = {all:'All tokens', mine:'My tokens', shared:'Shared with me'};
  document.getElementById('view-title').textContent = titles[type];
  document.querySelectorAll('.otp-card').forEach(card => {
    const owner = card.dataset.owner === '1';
    const show  = type==='all' || (type==='mine' && owner) || (type==='shared' && !owner);
    card.style.display = show ? '' : 'none';
  });
}

function updateCounts() {
  const cards = [...document.querySelectorAll('.otp-card')];
  document.getElementById('count-all').textContent    = cards.length;
  document.getElementById('count-mine').textContent   = cards.filter(c=>c.dataset.owner==='1').length;
  document.getElementById('count-shared').textContent = cards.filter(c=>c.dataset.owner!=='1').length;
}

// ── User menu ──
function toggleUserMenu() { document.getElementById('user-menu').classList.toggle('hidden'); }
document.addEventListener('click', e => {
  if (!e.target.closest('.user-menu-wrap')) document.getElementById('user-menu')?.classList.add('hidden');
});

// ── API helper ──
async function apiFetch(url, method, body) {
  const r = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify(body),
  });
  return r.json();
}

// ── Toast ──
function toast(msg, type='success') {
  const icons = { success:'✓', error:'✕', info:'ℹ' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ'}</span><span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => { el.style.animation='toastOut .2s ease forwards'; setTimeout(()=>el.remove(),200); }, 3000);
}

document.querySelectorAll('.modal-backdrop').forEach(b => {
  b.addEventListener('click', e => { if (e.target === b) b.classList.add('hidden'); });
});

if (window.innerWidth < 768) document.getElementById('mob-add-btn').style.display='';

// ── Icon picker ──
const FA_BRAND_ICONS = <?= json_encode($FA_BRAND_ICONS) ?>;
const FA_SOLID_ICONS = <?= json_encode($FA_SOLID_ICONS) ?>;

function renderIcons(filter) {
  const grid    = document.getElementById('icon-grid');
  const current = document.getElementById('f-icon').value;
  const f       = (filter || '').toLowerCase();
  const brands  = FA_BRAND_ICONS.filter(i => !f || i.includes(f)).map(i => iconBtn(i, 'fa-brands', current));
  const solids  = FA_SOLID_ICONS.filter(i => !f || i.includes(f)).map(i => iconBtn(i, 'fa-solid', current));
  grid.innerHTML =
    (brands.length ? `<div style="width:100%;font-size:.7rem;color:var(--ink4);padding:2px 0">Brands</div>${brands.join('')}` : '') +
    (solids.length ? `<div style="width:100%;font-size:.7rem;color:var(--ink4);padding:6px 0 2px">General</div>${solids.join('')}` : '');
}

function iconBtn(icon, prefix, current) {
  const sel = current === icon;
  return `<button type="button" onclick="selectIcon('${icon}','${prefix}')" title="${icon.replace('fa-','')}"
    style="width:34px;height:34px;border-radius:7px;border:1px solid var(--line);
           background:${sel?'var(--blue-l)':'var(--bg2)'};color:${sel?'var(--blue)':'var(--ink3)'};
           cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.95rem;transition:all .15s">
    <i class="${prefix} ${icon}"></i>
  </button>`;
}

function filterIcons(val) { renderIcons(val); }

function selectIcon(icon, prefix) {
  document.getElementById('f-icon').value = icon;
  document.getElementById('f-icon-prefix').value = prefix || 'fa-brands';
  renderIcons(document.getElementById('icon-search').value);
}

// ── QR Scanner ──
function toggleQrPanel() {
  const panel = document.getElementById('qr-panel');
  const isHidden = panel.style.display === 'none';
  panel.style.display = isHidden ? '' : 'none';
  if (isHidden) {
    document.getElementById('qr-canvas').style.display = 'none';
    document.getElementById('qr-result').style.display = 'none';
  }
}

function handleQrDrop(e) {
  e.preventDefault();
  document.getElementById('qr-dropzone').style.borderColor = 'var(--line)';
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) handleQrFile(file);
}

function handleQrFile(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => decodeQrFromDataUrl(e.target.result);
  reader.readAsDataURL(file);
}

document.addEventListener('paste', e => {
  const modal = document.getElementById('profile-modal');
  if (modal.classList.contains('hidden')) return;
  const items = e.clipboardData?.items;
  if (!items) return;
  for (const item of items) {
    if (item.type.startsWith('image/')) {
      document.getElementById('qr-panel').style.display = '';
      handleQrFile(item.getAsFile());
      return;
    }
  }
});

function decodeQrFromDataUrl(dataUrl) {
  const img = new Image();
  img.onload = () => {
    const canvas  = document.getElementById('qr-canvas');
    const ctx     = canvas.getContext('2d');
    canvas.width  = img.width;
    canvas.height = img.height;
    ctx.drawImage(img, 0, 0);
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code      = jsQR(imageData.data, imageData.width, imageData.height);
    if (!code) { showQrResult('error', 'No QR code found. Try a clearer or larger image.'); return; }
    parseOtpAuth(code.data);
  };
  img.src = dataUrl;
}

function parseOtpAuth(uri) {
  if (!uri.startsWith('otpauth://')) { showQrResult('error', `Not an OTP QR code. Got: ${uri.slice(0,60)}`); return; }
  try {
    const url    = new URL(uri);
    const secret = url.searchParams.get('secret');
    const issuer = url.searchParams.get('issuer') || '';
    const algo   = (url.searchParams.get('algorithm') || 'SHA1').toUpperCase();
    const digits = url.searchParams.get('digits') || '6';
    const period = url.searchParams.get('period') || '30';
    let label    = decodeURIComponent(url.pathname.replace(/^\//, ''));
    const colonIdx     = label.indexOf(':');
    const name         = colonIdx > -1 ? label.slice(colonIdx + 1) : label;
    const issuerFromLabel = colonIdx > -1 ? label.slice(0, colonIdx) : '';
    const finalIssuer  = issuer || issuerFromLabel;
    if (!secret) { showQrResult('error', 'QR code found but no secret key present.'); return; }
    if (document.getElementById('f-name').value === '') document.getElementById('f-name').value = finalIssuer || name;
    if (document.getElementById('f-issuer').value === '') document.getElementById('f-issuer').value = finalIssuer;
    document.getElementById('f-secret').value    = secret;
    document.getElementById('f-algorithm').value = ['SHA1','SHA256','SHA512'].includes(algo) ? algo : 'SHA1';
    document.getElementById('f-digits').value    = ['6','8','10'].includes(digits) ? digits : '6';
    document.getElementById('f-period').value    = period;
    showQrResult('success', `✓ Secret extracted${finalIssuer ? ` from ${finalIssuer}` : ''}. Review and save.`);
    setTimeout(() => { document.getElementById('qr-panel').style.display = 'none'; }, 2000);
  } catch (err) { showQrResult('error', 'Failed to parse OTP URI: ' + err.message); }
}

function showQrResult(type, msg) {
  const el = document.getElementById('qr-result');
  el.style.display    = '';
  el.style.background = type === 'success' ? 'var(--green-l)' : 'var(--red-l)';
  el.style.color      = type === 'success' ? 'var(--green)'   : 'var(--red)';
  el.textContent      = msg;
}

// ── Mobile tab switcher ──
function switchTab(idx) {
  document.querySelectorAll('.form-2col .col').forEach((c,i) => c.classList.toggle('tab-active', i===idx));
  document.querySelectorAll('.mob-tab').forEach((t,i) => t.classList.toggle('active', i===idx));
}

// Init
initColorGrid('#6366f1');
initAllCards();
</script>

</body>
</html>