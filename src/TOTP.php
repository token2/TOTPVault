<?php
// src/TOTP.php
// Server-side TOTP generation — secret never leaves the server

class TOTP {
    /**
     * Generate a TOTP code.
     *
     * @param string $secret   Base32-encoded secret
     * @param string $algorithm SHA1 | SHA256 | SHA512
     * @param int    $digits    6 | 8 | 10
     * @param int    $period    Time step in seconds (default 30)
     * @param int|null $time   Unix timestamp (null = now)
     */
    public static function generate(
        string $secret,
        string $algorithm = 'SHA1',
        int    $digits     = 6,
        int    $period     = 30,
        ?int   $time       = null
    ): string {
        $time  = $time ?? time();
        $key   = self::base32Decode($secret);
        $counter = (int) floor($time / $period);
        $msg   = pack('J', $counter); // 8-byte big-endian

        $algo  = strtolower($algorithm); // sha1, sha256, sha512
        $hash  = hash_hmac($algo, $msg, $key, true);

        $offset = ord($hash[-1]) & 0x0F;
        $code   = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        );

        $otp = $code % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Return seconds remaining in the current period.
     */
    public static function secondsRemaining(int $period = 30, ?int $time = null): int {
        $time = $time ?? time();
        return $period - ($time % $period);
    }

    /**
     * Return progress percentage (0-100) through current period.
     */
    public static function progress(int $period = 30, ?int $time = null): float {
        $time = $time ?? time();
        return (($time % $period) / $period) * 100;
    }

    /**
     * Generate a cryptographically random Base32 secret.
     */
    public static function generateSecret(int $length = 32): string {
        $bytes = random_bytes((int) ceil($length * 5 / 8));
        return substr(self::base32Encode($bytes), 0, $length);
    }

    // ── Base32 ────────────────────────────────────────────────────────────────

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Decode(string $input): string {
        $input  = strtoupper(trim($input));
        $output = '';
        $buf    = 0;
        $bufLen = 0;
        $map    = array_flip(str_split(self::BASE32_CHARS));

        foreach (str_split($input) as $ch) {
            if ($ch === '=') break;
            if (!isset($map[$ch])) continue;
            $buf    = ($buf << 5) | $map[$ch];
            $bufLen += 5;
            if ($bufLen >= 8) {
                $bufLen -= 8;
                $output .= chr(($buf >> $bufLen) & 0xFF);
            }
        }
        return $output;
    }

    public static function base32Encode(string $input): string {
        $output = '';
        $buf    = 0;
        $bufLen = 0;
        foreach (str_split($input) as $byte) {
            $buf    = ($buf << 8) | ord($byte);
            $bufLen += 8;
            while ($bufLen >= 5) {
                $bufLen -= 5;
                $output .= self::BASE32_CHARS[($buf >> $bufLen) & 0x1F];
            }
        }
        if ($bufLen > 0) {
            $output .= self::BASE32_CHARS[($buf << (5 - $bufLen)) & 0x1F];
        }
        return $output;
    }

    /**
     * Build an otpauth:// URI for QR code generation.
     */
    public static function buildUri(
        string $secret,
        string $issuer,
        string $account,
        string $algorithm = 'SHA1',
        int    $digits     = 6,
        int    $period     = 30
    ): string {
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper($algorithm),
            'digits'    => $digits,
            'period'    => $period,
        ]);
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?' . $params;
    }
}
