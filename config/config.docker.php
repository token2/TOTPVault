<?php
// config/config.docker.php
//
// Docker-specific configuration that reads ALL settings from environment
// variables. This file is mounted as config/config.php inside the container
// by docker-compose.yml. It is never used in a non-Docker deployment.
//
// Do NOT commit this file with real secrets. All values come from .env.

$appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');

return [

    // ── Application ────────────────────────────────────────────────────────
    'app_url'  => $appUrl,
    'app_name' => 'TOTPVault',
    'app_env'  => getenv('APP_ENV') ?: 'production',

    // ── Database ───────────────────────────────────────────────────────────
    // DB_HOST should be the Compose service name (default: "db").
    'db' => [
        'host'     => getenv('DB_HOST')     ?: 'db',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'dbname'   => getenv('DB_NAME')     ?: 'totpvault',
        'charset'  => 'utf8mb4',
        'user'     => getenv('DB_USER')     ?: 'totpvault',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],

    // ── Encryption ─────────────────────────────────────────────────────────
    // Must be exactly 32 bytes, base64-encoded. Generate with:
    //   php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
    'encryption_key' => getenv('ENCRYPTION_KEY') ?: '',

    // ── Session ────────────────────────────────────────────────────────────
    'session' => [
        'cookie_name' => getenv('SESSION_COOKIE_NAME') ?: 'totpvault_session',
        'lifetime'    => (int)(getenv('SESSION_LIFETIME') ?: 2592000),
    ],

    // ── Mail (MailerSend) ──────────────────────────────────────────────────
    'mail' => [
        'mailersend_key' => getenv('MAILERSEND_KEY')    ?: '',
        'from_email'     => getenv('MAIL_FROM_EMAIL')   ?: 'noreply@localhost',
        'from_name'      => getenv('MAIL_FROM_NAME')    ?: 'TOTPVault',
    ],

    // ── OAuth providers ────────────────────────────────────────────────────
    // Redirect URIs are derived from APP_URL automatically.
    // Leave client_id empty to effectively disable a provider.
    'oauth' => [

        'google' => [
            'client_id'     => getenv('GOOGLE_CLIENT_ID')     ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri'  => $appUrl . '/auth/callback/google',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'userinfo_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope'         => 'openid email profile',
        ],

        'microsoft' => [
            'client_id'     => getenv('MICROSOFT_CLIENT_ID')     ?: '',
            'client_secret' => getenv('MICROSOFT_CLIENT_SECRET') ?: '',
            'redirect_uri'  => $appUrl . '/auth/callback/microsoft',
            'auth_url'      => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_url'  => 'https://graph.microsoft.com/v1.0/me',
            'scope'         => 'openid email profile User.Read',
        ],

        'github' => [
            'client_id'     => getenv('GITHUB_CLIENT_ID')     ?: '',
            'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
            'redirect_uri'  => $appUrl . '/auth/callback/github',
            'auth_url'      => 'https://github.com/login/oauth/authorize',
            'token_url'     => 'https://github.com/login/oauth/access_token',
            'userinfo_url'  => 'https://api.github.com/user',
            'scope'         => 'read:user user:email',
        ],

    ],
];
