# TOTPVault

A self-hosted TOTP (Time-based One-Time Password) manager built with PHP. Secret keys are AES-256-GCM encrypted on the server and **never transmitted to the client**. OTP codes are generated server-side and can be shared with teammates without ever exposing the underlying secrets.

---

## Features

- **Server-side code generation** — secrets are decrypted in memory only at generation time and never sent to the browser
- **AES-256-GCM encryption** — every secret is encrypted at rest with a unique 96-bit nonce per record
- **Multiple login methods** — OAuth 2.0 via Google, Microsoft, or GitHub; passwordless magic links via email
- **Token sharing** — share individual OTP profiles with colleagues by email; they can view codes (or optionally edit settings) without ever seeing the secret
- **QR code import** — paste or drag an `otpauth://` QR image directly in the browser to auto-fill all token fields
- **Hide mode** — mask a token's code until clicked; it reveals for 10 seconds then hides itself again
- **Full RFC 6238 support** — SHA1, SHA256, SHA512 · 6, 8, or 10 digit codes · configurable time periods
- **Icon & colour tagging** — assign any Font Awesome brand or solid icon and one of 16 accent colours to each token for quick visual identification

---

## Requirements

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled (`AllowOverride All`)
- A [MailerSend](https://www.mailersend.com) account (for magic link emails)
- OpenSSL PHP extension (for AES-256-GCM)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/youruser/totpvault.git
cd totpvault
```

### 2. Create the database

```sql
CREATE DATABASE totpvault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema:

```bash
mysql -u youruser -p totpvault < schema.sql
```

### 3. Configure the application

```bash
cp config/config.example.php config/config.php
```

Edit `config/config.php` and fill in all values. See [Configuration](#configuration) below.

### 4. Set directory permissions

```bash
chmod 750 sessions/
chmod 750 config/
```

### 5. Web server

Point your virtual host document root to the project directory. All requests are routed through `index.php` via `.htaccess`. The `config/`, `src/`, `sessions/`, and `templates/` directories are individually protected by `.htaccess` rules that deny direct HTTP access.

---

## Configuration

`config/config.php` must return a PHP array. All keys are required unless marked optional.

```php
<?php
return [

    // ── Application ────────────────────────────────────────────────────────
    'app_url' => 'https://yourdomain.com',          // No trailing slash

    // ── Database ───────────────────────────────────────────────────────────
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'dbname'   => 'totpvault',
        'charset'  => 'utf8mb4',
        'user'     => 'db_user',
        'password' => 'db_password',
    ],

    // ── Encryption ─────────────────────────────────────────────────────────
    // Must be exactly 32 bytes. Generate a safe value with:
    //   php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
    'encryption_key' => 'base64-encoded-32-byte-key',

    // ── Session ────────────────────────────────────────────────────────────
    'session' => [
        'cookie_name' => 'totpvault_session',
        'lifetime'    => 86400,                     // seconds (default: 24 h)
    ],

    // ── Mail (MailerSend) ──────────────────────────────────────────────────
    'mail' => [
        'mailersend_key' => 'your-mailersend-api-key',
        'from_email'     => 'noreply@yourdomain.com',
        'from_name'      => 'TOTPVault',
    ],

    // ── OAuth providers (remove any you don't need) ────────────────────────
    'oauth' => [

        'google' => [
            'client_id'     => '',
            'client_secret' => '',
            'redirect_uri'  => 'https://yourdomain.com/auth/callback/google',
            'scope'         => 'openid email profile',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'userinfo_url'  => 'https://openidconnect.googleapis.com/v1/userinfo',
        ],

        'microsoft' => [
            'client_id'     => '',
            'client_secret' => '',
            'redirect_uri'  => 'https://yourdomain.com/auth/callback/microsoft',
            'scope'         => 'openid email profile',
            'auth_url'      => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_url'  => 'https://graph.microsoft.com/v1.0/me',
        ],

        'github' => [
            'client_id'     => '',
            'client_secret' => '',
            'redirect_uri'  => 'https://yourdomain.com/auth/callback/github',
            'scope'         => 'user:email',
            'auth_url'      => 'https://github.com/login/oauth/authorize',
            'token_url'     => 'https://github.com/login/oauth/access_token',
            'userinfo_url'  => 'https://api.github.com/user',
        ],

    ],
];
```

---

## Login methods

### Magic link (email)

No password required. The user enters their email address and receives a time-limited sign-in link delivered via MailerSend.

- Tokens are 32 random bytes; only the SHA-256 hash is stored in the database — the raw token only ever exists in the email
- Links expire after **15 minutes**
- Rate-limited to **3 requests per email per 10 minutes**
- Each new request invalidates all previous unused links for that address
- If the email has never been seen before, a new account is created automatically on first use
- Any token shares pending for that email are linked to the new account immediately

### OAuth 2.0

One-click sign-in via a third-party identity provider. The OAuth state parameter is verified on callback to prevent CSRF. Three providers are supported out of the box:

| Provider | Notes |
|---|---|
| **Google** | OpenID Connect; fetches name, email, and profile picture |
| **Microsoft** | Microsoft Graph; supports personal and work/school accounts |
| **GitHub** | Fetches the primary verified email separately via `/user/emails` since the profile endpoint may return `null` |

Each provider stores its own `provider_id` column in the `users` table. A user who signs in with Google and later via magic link to the same email address shares the same account row.

---

## TOTP profiles

Each token belongs to one user and stores the following fields:

| Field | Values | Default |
|---|---|---|
| `name` | Any string | — |
| `issuer` | Any string (optional) | — |
| `secret_encrypted` | AES-256-GCM ciphertext | — |
| `algorithm` | `SHA1`, `SHA256`, `SHA512` | `SHA1` |
| `digits` | `6`, `8`, `10` | `6` |
| `period` | 15–300 seconds | `30` |
| `color` | Hex colour from the palette | `#6366f1` |
| `icon` | Font Awesome class name | `fa-shield-halved` |
| `hide_code` | `0` or `1` | `0` |

### Code generation (RFC 6238)

TOTP codes are generated entirely in PHP. The HMAC is computed with the chosen algorithm, dynamic truncation extracts a 4-byte slice, and the result is taken modulo 10^digits and zero-padded to the required length:

```
counter = floor(unix_timestamp / period)
hmac    = HMAC-{algo}(base32_decode(secret), pack('J', counter))
offset  = last_byte(hmac) & 0x0F
code    = (hmac[offset..offset+3] & 0x7FFFFFFF) % 10^digits
```

Supported algorithms:

| Algorithm | HMAC output | Compatibility |
|---|---|---|
| `SHA1` | 20 bytes | Universal — Google Authenticator, Authy, hardware tokens |
| `SHA256` | 32 bytes | Supported by some hardware tokens and newer apps |
| `SHA512` | 64 bytes | Maximum security; fewer app implementations |

### Code display format

Codes are visually grouped with a space for readability:

| Digits | Format |
|---|---|
| 6 | `123 456` |
| 8 | `1234 5678` |
| 10 | `123 456 789 0` |

### Hide mode

When `hide_code = 1`, the code is replaced with dots (e.g. `••• •••`) on the dashboard. Clicking the card or the Reveal button displays the code for **10 seconds**, then it re-hides automatically. The raw code is held only in a JS `dataset` attribute during the reveal window and is never rendered to the DOM at rest.

### Secret generation

The built-in generator creates secrets server-side using `random_bytes()` and Base32-encodes them to a 32-character string (160 bits of entropy).

### QR code import

The browser-side QR scanner uses [jsQR](https://github.com/cozmo/jsQR) to decode an `otpauth://totp/` URI from a dropped, uploaded, or clipboard-pasted image — entirely client-side with no image data sent to the server. All parsed fields (secret, issuer, algorithm, digits, period) are populated directly into the form.

---

## Colours

Each profile can be tagged with one of 16 preset accent colours used for the card indicator dot and icon tint:

| Name | Hex | | Name | Hex |
|---|---|---|---|---|
| Indigo | `#6366f1` | | Sky | `#0ea5e9` |
| Blue | `#3b82f6` | | Teal | `#14b8a6` |
| Cyan | `#06b6d4` | | Green | `#22c55e` |
| Emerald | `#10b981` | | Yellow | `#eab308` |
| Lime | `#84cc16` | | Rose | `#f43f5e` |
| Amber | `#f59e0b` | | Slate | `#6b7280` |
| Orange | `#f97316` | | Violet | `#8b5cf6` |
| Red | `#ef4444` | | Pink | `#ec4899` |

---

## Icons

Icons use [Font Awesome 6](https://fontawesome.com) and are split into two groups. The icon list is defined once in PHP and injected into the JavaScript via `json_encode()` — no duplication in the codebase.

**Brands** (`fa-brands`) — service and platform logos:

`github` · `google` · `microsoft` · `apple` · `amazon` · `facebook` · `twitter` · `instagram` · `linkedin` · `slack` · `discord` · `telegram` · `whatsapp` · `dropbox` · `spotify` · `twitch` · `youtube` · `reddit` · `gitlab` · `bitbucket` · `docker` · `aws` · `cloudflare` · `digital-ocean` · `stripe` · `paypal` · `shopify` · `wordpress` · `jenkins` · `jira` · `confluence` · `trello` · `npm` · `node` · `react` · `vuejs` · `angular` · `laravel` · `php` · `python` · `java` · `swift` · `android` · `windows` · `linux` · `ubuntu` · `firefox` · `chrome` · `safari` · `steam` · `playstation` · `xbox`

**General** (`fa-solid`) — generic categories:

`shield-halved` · `key` · `lock` · `lock-open` · `user` · `users` · `building` · `globe` · `server` · `database` · `cloud` · `code` · `terminal` · `mobile` · `laptop` · `desktop` · `wifi` · `envelope` · `bell` · `star` · `bolt` · `fire` · `gear` · `wrench` · `briefcase` · `chart-bar` · `credit-card` · `wallet` · `shop` · `robot` · `microchip`

---

## Token sharing

An owner can share any profile with any email address. The recipient can view live OTP codes from their own dashboard without access to the encrypted secret.

- **View-only** (default) — recipient sees the code but cannot modify the token
- **Can edit** — recipient can update name, settings, icon, and colour, but still cannot read the secret

If the invited email does not yet have an account, the share is stored as pending and activates automatically the first time that address signs in. Owners can revoke shares at any time.

---

## Security notes

| Concern | Approach |
|---|---|
| Secret storage | AES-256-GCM with a random 96-bit nonce per encryption; IV + auth tag + ciphertext stored as a single base64 blob |
| Secret transmission | Never sent to the client; decrypted in PHP memory only during code generation |
| CSRF | All state-changing API endpoints verify a session-bound token sent as `X-CSRF-Token` header |
| Session cookies | `HttpOnly`, `SameSite=Lax`, `Secure` (when HTTPS is present) |
| OAuth state | Random 32-byte hex state verified on callback |
| Magic link tokens | 32 random bytes; only the SHA-256 hash is stored in the database |
| Rate limiting | Magic link requests capped at 3 per email per 10 minutes |
| Directory access | `config/`, `src/`, `sessions/`, `templates/` all deny direct HTTP access via `.htaccess` |
| `config.php` | Excluded from version control via `.gitignore` |

---

## Database schema

```sql
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    name            VARCHAR(255),
    avatar_url      VARCHAR(512),
    google_id       VARCHAR(128),
    microsoft_id    VARCHAR(128),
    github_id       VARCHAR(128),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE otp_profiles (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    name              VARCHAR(255) NOT NULL,
    issuer            VARCHAR(255),
    secret_encrypted  TEXT NOT NULL,
    algorithm         ENUM('SHA1','SHA256','SHA512') DEFAULT 'SHA1',
    digits            TINYINT DEFAULT 6,
    period            SMALLINT DEFAULT 30,
    color             VARCHAR(16) DEFAULT '#6366f1',
    icon              VARCHAR(64) DEFAULT 'fa-shield-halved',
    hide_code         TINYINT(1) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE profile_shares (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id            INT UNSIGNED NOT NULL,
    shared_by_user_id     INT UNSIGNED NOT NULL,
    shared_with_email     VARCHAR(255) NOT NULL,
    shared_with_user_id   INT UNSIGNED,
    can_edit              TINYINT(1) DEFAULT 0,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES otp_profiles(id) ON DELETE CASCADE
);

CREATE TABLE magic_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    token_hash  VARCHAR(128) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Project structure

```
├── config/
│   ├── config.php            # Your local config — not committed
│   ├── config.example.php    # Template to copy
│   └── .htaccess             # Deny all HTTP access
├── sessions/                 # PHP session files — not committed
├── src/
│   ├── Auth.php              # Session management, OAuth user lookup
│   ├── Crypto.php            # AES-256-GCM encrypt / decrypt
│   ├── Database.php          # PDO singleton
│   ├── MagicLink.php         # Passwordless email login
│   ├── OAuthP.php            # OAuth 2.0 — Google / Microsoft / GitHub
│   ├── Profile.php           # TOTP profile CRUD and sharing
│   └── TOTP.php              # RFC 6238 code generation and Base32
├── templates/
│   ├── layout.php            # Shared HTML shell and CSS variables
│   ├── landing.php           # Public marketing / login page
│   ├── dashboard.php         # Authenticated token manager
│   └── 404.php
├── favicon.png
├── index.php                 # Front controller and router
├── schema.sql                # Database schema
└── .htaccess                 # Rewrites all requests to index.php
```

---

## License

GPL
