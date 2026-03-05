<?php
// src/Crypto.php — AES-256-GCM encryption for TOTP secrets

class Crypto {
private static function key(): string {
    $cfg = require __DIR__ . '/../config/config.php';
    $key = $cfg['encryption_key'];
    // Pad or trim to exactly 32 bytes
    return substr(str_pad($key, 32, "\0"), 0, 32);
}

    public static function encrypt(string $plaintext): string {
        $key    = self::key();
        $iv     = random_bytes(12); // 96-bit nonce for GCM
        $tag    = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) throw new RuntimeException('Encryption failed.');
        // Store: base64(iv || tag || ciphertext)
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string {
        $key  = self::key();
        $raw  = base64_decode($encoded);
        $iv   = substr($raw, 0, 12);
        $tag  = substr($raw, 12, 16);
        $data = substr($raw, 28);
        $plain = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) throw new RuntimeException('Decryption failed — data may be tampered.');
        return $plain;
    }
}
