<?php
// tools/import-google-auth.php — Google Authenticator migration QR importer
// Included by index.php after auth check — $user, csrf_token(), verify_csrf(),
// jsonResponse(), BASE_DIR and all src classes are already available.

// ── Protobuf decoder ────────────────────────────────────────────────────────
// Decodes the Google Authenticator migration payload.
// The proto schema (MigrationPayload) is fixed and publicly documented.

function proto_read_varint(string $data, int &$pos): int {
    $result = 0;
    $shift  = 0;
    do {
        if ($pos >= strlen($data)) throw new RuntimeException('Unexpected end of protobuf data');
        $byte    = ord($data[$pos++]);
        $result |= ($byte & 0x7F) << $shift;
        $shift  += 7;
    } while ($byte & 0x80);
    return $result;
}

function proto_read_bytes(string $data, int &$pos): string {
    $len = proto_read_varint($data, $pos);
    $out = substr($data, $pos, $len);
    $pos += $len;
    return $out;
}

/**
 * Decode a Google Authenticator otpauth-migration://offline?data=... payload.
 * Returns an array of accounts, each with: name, issuer, secret (Base32), algorithm, digits, type.
 */
function decode_ga_migration(string $base64Data): array {
    // The data param is URL-encoded base64
    $raw = base64_decode($base64Data);
    if ($raw === false) throw new RuntimeException('Invalid base64 data in migration URI.');

    $accounts = [];
    $pos      = 0;
    $len      = strlen($raw);

    while ($pos < $len) {
        $tag       = proto_read_varint($raw, $pos);
        $fieldNum  = $tag >> 3;
        $wireType  = $tag & 0x07;

        // Field 1 = repeated OtpParameters (length-delimited)
        if ($fieldNum === 1 && $wireType === 2) {
            $paramBytes = proto_read_bytes($raw, $pos);
            $accounts[] = decode_otp_parameters($paramBytes);
        } else {
            // Skip other fields (version, batch_size, batch_index, batch_id)
            switch ($wireType) {
                case 0: proto_read_varint($raw, $pos); break;        // varint
                case 2: proto_read_bytes($raw, $pos);  break;        // length-delimited
                case 5: $pos += 4; break;                            // 32-bit
                case 1: $pos += 8; break;                            // 64-bit
                default: throw new RuntimeException("Unknown wire type {$wireType}");
            }
        }
    }

    return $accounts;
}

function decode_otp_parameters(string $data): array {
    $pos    = 0;
    $len    = strlen($data);
    $result = [
        'secret'    => '',
        'name'      => '',
        'issuer'    => '',
        'algorithm' => 'SHA1',
        'digits'    => 6,
        'type'      => 'totp',
    ];

    // Algorithm map: 0=unspecified(SHA1), 1=SHA1, 2=SHA256, 3=SHA512, 4=MD5
    $algoMap   = [0 => 'SHA1', 1 => 'SHA1', 2 => 'SHA256', 3 => 'SHA512', 4 => 'SHA1'];
    // Digits map: 0=unspecified(6), 1=6, 2=8
    $digitsMap = [0 => 6, 1 => 6, 2 => 8];
    // Type map: 0=unspecified, 1=HOTP, 2=TOTP
    $typeMap   = [0 => 'totp', 1 => 'hotp', 2 => 'totp'];

    while ($pos < $len) {
        $tag      = proto_read_varint($data, $pos);
        $fieldNum = $tag >> 3;
        $wireType = $tag & 0x07;

        switch ($fieldNum) {
            case 1: // secret (bytes → Base32)
                $secretBytes       = proto_read_bytes($data, $pos);
                $result['secret']  = TOTP::base32Encode($secretBytes);
                break;
            case 2: // name / label
                $result['name']    = proto_read_bytes($data, $pos);
                break;
            case 3: // issuer
                $result['issuer']  = proto_read_bytes($data, $pos);
                break;
            case 4: // algorithm
                $v = proto_read_varint($data, $pos);
                $result['algorithm'] = $algoMap[$v] ?? 'SHA1';
                break;
            case 5: // digits
                $v = proto_read_varint($data, $pos);
                $result['digits'] = $digitsMap[$v] ?? 6;
                break;
            case 6: // type
                $v = proto_read_varint($data, $pos);
                $result['type'] = $typeMap[$v] ?? 'totp';
                break;
            case 7: // counter (HOTP only — skip)
                proto_read_varint($data, $pos);
                break;
            default:
                // Skip unknown fields
                switch ($wireType) {
                    case 0: proto_read_varint($data, $pos); break;
                    case 2: proto_read_bytes($data, $pos);  break;
                    case 5: $pos += 4; break;
                    case 1: $pos += 8; break;
                }
        }
    }

    // If the label contains "Issuer:Account", split it
    if (empty($result['issuer']) && str_contains($result['name'], ':')) {
        [$result['issuer'], $result['name']] = explode(':', $result['name'], 2);
    }
    $result['name']   = trim($result['name']);
    $result['issuer'] = trim($result['issuer']);

    return $result;
}

// ── API endpoint: decode migration data ────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($input['data'])) {
        jsonResponse(['error' => 'Missing data parameter'], 400);
    }

    try {
        $accounts = decode_ga_migration($input['data']);
        if (empty($accounts)) {
            jsonResponse(['error' => 'No accounts found in the migration QR code.'], 400);
        }
        jsonResponse(['accounts' => $accounts]);
    } catch (RuntimeException $e) {
        jsonResponse(['error' => 'Failed to decode: ' . $e->getMessage()], 400);
    }
}

// ── Page ───────────────────────────────────────────────────────────────────
$pageTitle = 'Import from Google Authenticator — TOTPVault';
$csrfToken = csrf_token();
$avatar    = $user['avatar_url'] ? htmlspecialchars($user['avatar_url']) : null;
$initial   = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));

require BASE_DIR . '/templates/layout.php';
?>

<style>
.page{max-width:960px;margin:0 auto;padding:1.5rem}
@media(max-width:600px){.page{padding:1rem}}

.top-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;align-items:start}
@media(max-width:640px){.top-grid{grid-template-columns:1fr}}

.page-header{margin-bottom:.75rem}
.page-header h1{font-size:1.25rem;font-weight:700;letter-spacing:-.02em;margin-bottom:.25rem;display:flex;align-items:center;gap:.5rem}
.page-header p{font-size:.8125rem;color:var(--ink3)}

.step{display:flex;gap:.625rem;align-items:flex-start}
.step-num{width:20px;height:20px;border-radius:50%;background:var(--blue-l);color:var(--blue);font-size:.6875rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:.15rem}
.step-body{flex:1}
.step-title{font-weight:600;font-size:.8125rem}
.step-desc{font-size:.75rem;color:var(--ink3);margin-top:.125rem}

.steps{display:flex;flex-direction:column;gap:.875rem;padding:1rem;background:var(--bg2);border:1px solid var(--line);border-radius:var(--r2)}

.dropzone{border:2px dashed var(--line);border-radius:var(--r2);padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:160px}
.dropzone:hover,.dropzone.drag{border-color:var(--blue);background:var(--blue-l)}
.dropzone-icon{font-size:1.75rem;color:var(--ink4);margin-bottom:.5rem}
.dropzone-title{font-weight:600;font-size:.875rem;margin-bottom:.25rem}
.dropzone-hint{font-size:.75rem;color:var(--ink4)}

.warning-box{display:flex;gap:.625rem;padding:.625rem .875rem;background:var(--amber-l);border:1px solid #fde68a;border-radius:var(--r);margin-bottom:1.25rem;font-size:.75rem}
.warning-box i{color:var(--amber);margin-top:.1rem;flex-shrink:0}

/* Review table */
.review-wrap{margin-top:1.75rem}
.review-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem}
.review-header h2{font-size:1.125rem;font-weight:600}
.review-actions{display:flex;gap:.5rem;align-items:center}

.account-table{width:100%;border-collapse:collapse}
.account-table th{font-size:.75rem;font-weight:600;color:var(--ink4);text-transform:uppercase;letter-spacing:.05em;padding:.5rem .75rem;border-bottom:2px solid var(--line);text-align:left}
.account-table td{padding:.75rem;border-bottom:1px solid var(--line2);font-size:.875rem;vertical-align:middle}
.account-table tr:last-child td{border-bottom:none}
.account-table tr.skipped td{opacity:.4}
.account-table tr.imported td{background:var(--green-l)}

.account-name{font-weight:500}
.account-issuer{font-size:.75rem;color:var(--ink4);margin-top:.125rem}
.account-secret{font-family:'JetBrains Mono',monospace;font-size:.75rem;color:var(--ink4);letter-spacing:.05em}
.badge-hotp{background:var(--amber-l);color:var(--amber);font-size:.6875rem;padding:.15rem .5rem;border-radius:999px;font-weight:600}
.badge-totp{background:var(--blue-l);color:var(--blue);font-size:.6875rem;padding:.15rem .5rem;border-radius:999px;font-weight:600}

.import-footer{margin-top:1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.import-summary{font-size:.875rem;color:var(--ink3)}

#progress-bar-wrap{margin-top:1.5rem;display:none}
.progress-track{height:6px;background:var(--line);border-radius:3px;overflow:hidden;margin-top:.5rem}
.progress-fill{height:100%;background:var(--blue);border-radius:3px;transition:width .3s}
</style>

<!-- Nav -->
<nav class="nav">
  <div class="nav-brand">
    <div class="nav-logo"><img src="/favicon.png" width="18"></div>
    TOTPVault
  </div>
  <div class="nav-spacer"></div>
  <a href="/dashboard" class="btn btn-ghost btn-sm">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to dashboard
  </a>
</nav>

<div class="page">

  <div class="page-header">
    <h1><i class="fa-solid fa-file-import" style="color:var(--blue)"></i>Import from Google Authenticator</h1>
    <p>Bulk-import tokens from a Google Authenticator export QR code.</p>
  </div>

  <div class="top-grid">
    <!-- Left: instructions + warning -->
    <div>
      <div class="steps">
        <div class="step">
          <div class="step-num">1</div>
          <div class="step-body">
            <div class="step-title">Open Google Authenticator on your phone</div>
            <div class="step-desc">Tap the three-dot menu (⋮) → <strong>Transfer accounts</strong> → <strong>Export accounts</strong>.</div>
          </div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div class="step-body">
            <div class="step-title">Screenshot the export QR code</div>
            <div class="step-desc">Google Authenticator shows one or more QR codes. Screenshot each one.</div>
          </div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div class="step-body">
            <div class="step-title">Drop or paste it on the right</div>
            <div class="step-desc">Drop the screenshot into the zone, or paste with <kbd style="font-size:.7rem;background:var(--bg);border:1px solid var(--line);border-radius:3px;padding:.1rem .3rem">Ctrl+V</kbd>.</div>
          </div>
        </div>
      </div>

      <div class="warning-box" style="margin-top:.875rem;margin-bottom:0">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Keep export QRs private.</strong> They contain unencrypted secrets. Delete screenshots after import.</div>
      </div>
    </div>

    <!-- Right: dropzone -->
    <div id="dropzone" class="dropzone"
      onclick="document.getElementById('file-input').click()"
      ondragover="event.preventDefault();this.classList.add('drag')"
      ondragleave="this.classList.remove('drag')"
      ondrop="handleDrop(event)">
      <div class="dropzone-icon"><i class="fa-solid fa-qrcode"></i></div>
      <div class="dropzone-title">Drop export QR here</div>
      <div class="dropzone-hint">or click to choose · Ctrl+V to paste</div>
    </div>
  </div>

  <input type="file" id="file-input" accept="image/*" style="display:none" onchange="handleFile(this.files[0])">
  <canvas id="decode-canvas" style="display:none;position:absolute;opacity:0;pointer-events:none"></canvas>

  <!-- Review area (shown after decode) -->
  <div id="review-wrap" class="review-wrap" style="display:none">
    <div class="review-header">
      <h2 id="review-title">Accounts found</h2>
      <div class="review-actions">
        <button class="btn btn-ghost btn-sm" onclick="toggleAll(true)">Select all</button>
        <button class="btn btn-ghost btn-sm" onclick="toggleAll(false)">Deselect all</button>
      </div>
    </div>

    <div class="card" style="overflow:hidden">
      <table class="account-table" id="account-table">
        <thead>
          <tr>
            <th style="width:36px"></th>
            <th>Account</th>
            <th>Type</th>
            <th>Algorithm</th>
            <th>Digits</th>
            <th style="width:80px">Status</th>
          </tr>
        </thead>
        <tbody id="account-tbody"></tbody>
      </table>
    </div>

    <div id="progress-bar-wrap">
      <div class="import-summary" id="progress-label">Importing…</div>
      <div class="progress-track"><div class="progress-fill" id="progress-fill" style="width:0%"></div></div>
    </div>

    <div class="import-footer">
      <div class="import-summary" id="import-summary"></div>
      <button class="btn btn-primary" id="import-btn" onclick="importSelected()">
        <i class="fa-solid fa-file-import"></i>
        Import selected
      </button>
    </div>
  </div>

</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;
let decodedAccounts = [];

// ── Drop / paste / file handling ──────────────────────────────────────────

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropzone').classList.remove('drag');
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) handleFile(file);
}

document.addEventListener('paste', e => {
  const items = e.clipboardData?.items;
  if (!items) return;
  for (const item of items) {
    if (item.type.startsWith('image/')) { handleFile(item.getAsFile()); return; }
  }
});

function handleFile(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => decodeQR(e.target.result);
  reader.readAsDataURL(file);
}

// ── QR decoding ───────────────────────────────────────────────────────────

function decodeQR(dataUrl) {
  const img = new Image();
  img.onload = () => {
    const canvas = document.getElementById('decode-canvas');
    const ctx    = canvas.getContext('2d');

    // Try multiple scales — GA export QRs are dense and jsQR works better
    // at certain resolutions. Try native size first, then upscaled.
    const scales = [1, 2, 1.5, 3, 0.75];
    for (const scale of scales) {
      const w = Math.round(img.width  * scale);
      const h = Math.round(img.height * scale);
      canvas.width  = w;
      canvas.height = h;
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(img, 0, 0, w, h);
      const imageData = ctx.getImageData(0, 0, w, h);
      const result    = jsQR(imageData.data, w, h, { inversionAttempts: 'attemptBoth' });
      if (result) { handleQRData(result.data); return; }
    }

    toast('No QR code found. Make sure the entire QR is visible and try a higher-resolution screenshot.', 'error');
  };
  img.src = dataUrl;
}

function handleQRData(uri) {
  if (!uri.startsWith('otpauth-migration://')) {
    if (uri.startsWith('otpauth://')) {
      toast('This looks like a single-account QR. Use the regular Add Token button on the dashboard instead.', 'error');
    } else {
      toast('Not a Google Authenticator export QR code.', 'error');
    }
    return;
  }

  let dataParam;
  try {
    dataParam = new URL(uri).searchParams.get('data');
    if (!dataParam) throw new Error('Missing data parameter');
  } catch {
    toast('Could not parse migration URI.', 'error'); return;
  }

  // Send to PHP for protobuf decoding
  fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ data: dataParam }),
  })
  .then(r => r.json())
  .then(d => {
    if (d.error) { toast(d.error, 'error'); return; }
    appendAccounts(d.accounts);
  })
  .catch(() => toast('Request failed. Please try again.', 'error'));
}

// ── Review table ──────────────────────────────────────────────────────────

function appendAccounts(accounts) {
  // Append to existing list (support multi-QR exports)
  const startIdx = decodedAccounts.length;
  decodedAccounts = decodedAccounts.concat(accounts);

  const tbody = document.getElementById('account-tbody');
  accounts.forEach((a, i) => {
    const idx = startIdx + i;
    const isHotp = a.type === 'hotp';
    const tr = document.createElement('tr');
    tr.id = `row-${idx}`;
    tr.innerHTML = `
      <td><input type="checkbox" id="chk-${idx}" ${isHotp ? '' : 'checked'} style="width:15px;height:15px;cursor:pointer" onchange="updateSummary()"></td>
      <td>
        <div class="account-name">${esc(a.name || '(unnamed)')}</div>
        ${a.issuer ? `<div class="account-issuer">${esc(a.issuer)}</div>` : ''}
      </td>
      <td><span class="badge-${a.type}">${a.type.toUpperCase()}</span>${isHotp ? ' <span style="font-size:.7rem;color:var(--amber)">⚠ HOTP not supported</span>' : ''}</td>
      <td>${esc(a.algorithm)}</td>
      <td>${a.digits}</td>
      <td id="status-${idx}"><span style="color:var(--ink4);font-size:.75rem">Pending</span></td>
    `;
    // Disable HOTP rows
    if (isHotp) {
      tr.querySelector('input').disabled = true;
      tr.classList.add('skipped');
    }
    tbody.appendChild(tr);
  });

  document.getElementById('review-wrap').style.display = '';
  updateSummary();
  toast(`Found ${accounts.length} account${accounts.length !== 1 ? 's' : ''}. Review and click Import.`, 'success');
}

function updateSummary() {
  const total    = decodedAccounts.length;
  const selected = decodedAccounts.filter((_, i) => {
    const chk = document.getElementById(`chk-${i}`);
    return chk && !chk.disabled && chk.checked;
  }).length;
  document.getElementById('review-title').textContent = `${total} account${total !== 1 ? 's' : ''} found`;
  document.getElementById('import-summary').textContent = `${selected} of ${total} selected`;
}

function toggleAll(state) {
  decodedAccounts.forEach((_, i) => {
    const chk = document.getElementById(`chk-${i}`);
    if (chk && !chk.disabled) chk.checked = state;
  });
  updateSummary();
}

// ── Import ────────────────────────────────────────────────────────────────

async function importSelected() {
  const toImport = decodedAccounts
    .map((a, i) => ({ account: a, idx: i }))
    .filter(({ idx }) => {
      const chk = document.getElementById(`chk-${idx}`);
      return chk && !chk.disabled && chk.checked;
    });

  if (!toImport.length) { toast('No accounts selected.', 'error'); return; }

  const btn = document.getElementById('import-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing…';

  const progressWrap = document.getElementById('progress-bar-wrap');
  const progressFill = document.getElementById('progress-fill');
  const progressLabel = document.getElementById('progress-label');
  progressWrap.style.display = '';

  let done = 0, failed = 0;

  for (const { account: a, idx } of toImport) {
    const statusEl = document.getElementById(`status-${idx}`);
    statusEl.innerHTML = '<span style="color:var(--ink4);font-size:.75rem">Importing…</span>';

    const body = {
      name:      a.issuer ? `${a.issuer} (${a.name})` : a.name,
      issuer:    a.issuer || '',
      secret:    a.secret,
      algorithm: a.algorithm,
      digits:    String(a.digits),
      period:    '30',
      color:     '#6366f1',
      icon:      'fa-shield-halved',
      hide_code: 0,
    };

    try {
      const r = await fetch('/api/profiles', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body),
      });
      const d = await r.json();
      if (d.error) throw new Error(d.error);
      statusEl.innerHTML = '<span style="color:var(--green);font-size:.75rem"><i class="fa-solid fa-check"></i> Imported</span>';
      document.getElementById(`row-${idx}`).classList.add('imported');
      done++;
    } catch (err) {
      statusEl.innerHTML = `<span style="color:var(--red);font-size:.75rem" title="${esc(err.message)}"><i class="fa-solid fa-xmark"></i> Failed</span>`;
      failed++;
    }

    progressFill.style.width = `${Math.round(((done + failed) / toImport.length) * 100)}%`;
    progressLabel.textContent = `Imported ${done} of ${toImport.length}…`;
  }

  progressLabel.textContent = `Done — ${done} imported${failed ? `, ${failed} failed` : ''}.`;
  btn.disabled = false;
  btn.innerHTML = '<i class="fa-solid fa-check"></i> Import complete';

  if (done > 0) {
    toast(`${done} token${done !== 1 ? 's' : ''} imported successfully.`, 'success');
    setTimeout(() => { window.location.href = '/dashboard'; }, 2000);
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, type = 'success') {
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => { el.style.animation = 'toastOut .2s ease forwards'; setTimeout(() => el.remove(), 200); }, 4000);
}

updateSummary();
</script>
</body>
</html>
