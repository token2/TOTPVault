<?php
// config/config.php

return [
    'db' => [
        'host'     => getenv('DB_HOST')     ?: 'localhost',
        'port'     => getenv('DB_PORT')     ?: 3306,
        'dbname'   => getenv('DB_NAME')     ?: 'totp',
        'user'     => getenv('DB_USER')     ?: 'user',
        'password' => getenv('DB_PASSWORD') ?: 'password',
        'charset'  => 'utf8mb4',
    ],
    'encryption_key' => getenv('ENCRYPTION_KEY') ?: 'ThisIsA32ByteKeyForLocalTesting!', //Change this!
    'app_name'   => 'TOTPVault',
    'app_url' => getenv('APP_URL') ?: 'https://totp.token2.swiss',
    'app_env'    => getenv('APP_ENV') ?: 'production',
	'mail' => [
		'from_email'   => getenv('MAIL_FROM')        ?: 'noreply@totp.token2.swiss',
		'from_name'    => getenv('MAIL_FROM_NAME')   ?: 'TOTPVault',
		'mailersend_key' => getenv('MAILERSEND_KEY') ?: 'mlsn.xxxx',
	],
    'oauth' => [
        'google' => [
            'client_id'     => getenv('GOOGLE_CLIENT_ID')     ?: '',
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri'  => (getenv('APP_URL') ?: 'https://totp.token2.swiss') . '/auth/callback/google',
            'auth_url'      => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'     => 'https://oauth2.googleapis.com/token',
            'userinfo_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope'         => 'openid email profile',
        ],
        'microsoft' => [
            'client_id'     => getenv('MICROSOFT_CLIENT_ID')     ?: '',
            'client_secret' => getenv('MICROSOFT_CLIENT_SECRET') ?: '',
            'redirect_uri'  => (getenv('APP_URL') ?: 'https://totp.token2.swiss') . '/auth/callback/microsoft',
            'auth_url'      => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'userinfo_url'  => 'https://graph.microsoft.com/v1.0/me',
            'scope'         => 'openid email profile User.Read',
        ],
        'github' => [
            'client_id'     => getenv('GITHUB_CLIENT_ID')     ?: '',
            'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
            'redirect_uri'  => (getenv('APP_URL') ?: 'https://totp.token2.swiss') . '/auth/callback/github',
            'auth_url'      => 'https://github.com/login/oauth/authorize',
            'token_url'     => 'https://github.com/login/oauth/access_token',
            'userinfo_url'  => 'https://api.github.com/user',
            'scope'         => 'read:user user:email',
        ],
    ],
    'session' => [
        'lifetime'    => 86400 * 30,
        'cookie_name' => 'authvault_session',
    ],
];
