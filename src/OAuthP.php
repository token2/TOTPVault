<?php
// src/OAuthP.php

class OAuthP  {
    private array $provider;
    private string $providerName;

public function __construct(string $providerName) {
    $cfg = require __DIR__ . '/../config/config.php';
    if (!isset($cfg['oauth'][$providerName])) {
        throw new InvalidArgumentException("Unknown OAuth provider: {$providerName}");
    }
    $this->providerName = $providerName;
    $this->provider     = $cfg['oauth'][$providerName];
    $this->configureDiscoveredProvider();
}

    public function getAuthUrl(): string {
        foreach (['auth_url', 'client_id', 'redirect_uri', 'scope'] as $requiredKey) {
            if (empty($this->provider[$requiredKey])) {
                throw new RuntimeException("OAuth provider is missing required setting: {$requiredKey}");
            }
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_provider'] = $this->providerName;

        $params = [
            'client_id'     => $this->provider['client_id'],
            'redirect_uri'  => $this->provider['redirect_uri'],
            'response_type' => 'code',
            'scope'         => $this->provider['scope'],
            'state'         => $state,
        ];

        if ($this->providerName === 'google') {
            $params['access_type'] = 'online';
        }

        return $this->provider['auth_url'] . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): array {
        if ($state !== ($_SESSION['oauth_state'] ?? '')) {
            throw new RuntimeException('OAuth state mismatch — possible CSRF attack.');
        }
        unset($_SESSION['oauth_state']);

        $token = $this->exchangeCode($code);
        try {
            $userInfo = $this->fetchUserInfo($token['access_token']);
        } catch (RuntimeException $e) {
            if (empty($token['id_token'])) {
                throw $e;
            }
            $userInfo = $this->decodeJwtPayload($token['id_token']);
        }
        return $this->normalizeUser($userInfo);
    }

    private function configureDiscoveredProvider(): void {
        if ($this->providerName !== 'keycloak') {
            return;
        }

        $baseUrl = rtrim($this->provider['base_url'] ?? '', '/');
        $internalBaseUrl = rtrim($this->provider['internal_base_url'] ?? '', '/');
        $discoveryBaseUrl = $internalBaseUrl !== '' ? $internalBaseUrl : $baseUrl;
        $realm   = trim($this->provider['realm'] ?? '');
        if ($baseUrl === '' || $realm === '') {
            return;
        }

        $encodedRealm = rawurlencode($realm);
        $discoveryUrl = "{$discoveryBaseUrl}/realms/{$encodedRealm}/.well-known/openid-configuration";
        $response = $this->httpGet($discoveryUrl, ['Accept: application/json']);
        $discovery = json_decode($response, true);
        if (!is_array($discovery)) {
            throw new RuntimeException('Failed to read Keycloak OpenID Connect discovery document.');
        }

        $this->provider['auth_url'] = "{$baseUrl}/realms/{$encodedRealm}/protocol/openid-connect/auth";
        foreach ([
            'token_url'    => 'token_endpoint',
            'userinfo_url' => 'userinfo_endpoint',
        ] as $configKey => $discoveryKey) {
            if (empty($this->provider[$configKey]) && !empty($discovery[$discoveryKey])) {
                $this->provider[$configKey] = $discovery[$discoveryKey];
            }
        }
    }

    private function exchangeCode(string $code): array {
        foreach (['token_url', 'client_id', 'redirect_uri'] as $requiredKey) {
            if (empty($this->provider[$requiredKey])) {
                throw new RuntimeException("OAuth provider is missing required setting: {$requiredKey}");
            }
        }

        $body = [
            'client_id'     => $this->provider['client_id'],
            'client_secret' => $this->provider['client_secret'],
            'code'          => $code,
            'redirect_uri'  => $this->provider['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ];

        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($this->providerName === 'github') {
            $headers[] = 'Accept: application/json';
        }

        $response = $this->httpPost($this->provider['token_url'], http_build_query($body), $headers);
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('Failed to obtain access token: ' . $response);
        }
        return $data;
    }

private function fetchUserInfo(string $accessToken): array {
    if (empty($this->provider['userinfo_url'])) {
        throw new RuntimeException("OAuth provider is missing required setting: userinfo_url");
    }

    $headers = [
        "Authorization: Bearer {$accessToken}",
        'Accept: application/json',
        'User-Agent: OTPVault/1.0',
    ];
    $response = $this->httpGet($this->provider['userinfo_url'], $headers);
    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Failed to fetch user info: ' . $response);
    }

    // GitHub: always fetch emails separately since profile email is often null
    if ($this->providerName === 'github') {
        $emailsJson = $this->httpGet('https://api.github.com/user/emails', $headers);
        $emails = json_decode($emailsJson, true) ?? [];


        // First try: primary + verified
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['verified'])) {
                $data['email'] = $e['email'];
                break;
            }
        }
        // Second try: any verified
        if (empty($data['email'])) {
            foreach ($emails as $e) {
                if (!empty($e['verified'])) {
                    $data['email'] = $e['email'];
                    break;
                }
            }
        }
        // Last resort: any email
        if (empty($data['email']) && !empty($emails[0]['email'])) {
            $data['email'] = $emails[0]['email'];
        }
    }

    return $data;
}

    private function normalizeUser(array $raw): array {
        $user = match($this->providerName) {
            'google' => [
                'provider'    => 'google',
                'provider_id' => $raw['sub'],
                'email'       => $raw['email'],
                'name'        => $raw['name'] ?? null,
                'avatar_url'  => $raw['picture'] ?? null,
            ],
            'microsoft' => [
                'provider'    => 'microsoft',
                'provider_id' => $raw['id'],
                'email'       => $raw['mail'] ?? $raw['userPrincipalName'] ?? null,
                'name'        => $raw['displayName'] ?? null,
                'avatar_url'  => null,
            ],
            'github' => [
                'provider'    => 'github',
                'provider_id' => (string)$raw['id'],
                'email'       => $raw['email'] ?? null,
                'name'        => $raw['name'] ?? $raw['login'] ?? null,
                'avatar_url'  => $raw['avatar_url'] ?? null,
            ],
            'keycloak' => [
                'provider'    => 'keycloak',
                'provider_id' => $raw['sub'] ?? null,
                'email'       => $raw['email'] ?? null,
                'name'        => $raw['name'] ?? $raw['preferred_username'] ?? null,
                'avatar_url'  => $raw['picture'] ?? null,
            ],
            default => throw new RuntimeException('Unknown provider'),
        };

        if (empty($user['provider_id'])) {
            throw new RuntimeException("OAuth provider did not return a stable user identifier.");
        }
        if (empty($user['email'])) {
            throw new RuntimeException("OAuth provider did not return an email address.");
        }

        return $user;
    }

    private function httpPost(string $url, string $body, array $headers): string {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        return file_get_contents($url, false, $ctx) ?: throw new RuntimeException("HTTP POST to {$url} failed.");
    }

    private function httpGet(string $url, array $headers): string {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        return file_get_contents($url, false, $ctx) ?: throw new RuntimeException("HTTP GET to {$url} failed.");
    }

    private function decodeJwtPayload(string $jwt): array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid OIDC ID token.');
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Failed to decode OIDC ID token.');
        }

        return $data;
    }
}
