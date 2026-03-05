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
}

    public function getAuthUrl(): string {
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

        $token    = $this->exchangeCode($code);
        $userInfo = $this->fetchUserInfo($token['access_token']);
        return $this->normalizeUser($userInfo);
    }

    private function exchangeCode(string $code): array {
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
        return match($this->providerName) {
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
            default => throw new RuntimeException('Unknown provider'),
        };
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
}
