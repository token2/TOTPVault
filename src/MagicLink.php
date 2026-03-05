<?php
class MagicLink {
    private PDO   $db;
    private array $cfg;
    private const TOKEN_BYTES = 32;
    private const TTL_SECONDS = 900;

    public function __construct() {
        $this->db  = Database::getInstance();
        $this->cfg = require __DIR__ . '/../config/config.php';
    }

    public function create(string $email): string {
        $email = strtolower(trim($email));
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM magic_links WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn() >= 3) {
            throw new RuntimeException('Too many requests. Please wait a few minutes and try again.');
        }
        $this->db->prepare('DELETE FROM magic_links WHERE email = ? AND used = 0')->execute([$email]);
        $token     = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $this->db->prepare(
            'INSERT INTO magic_links (email, token_hash, expires_at) VALUES (?, ?, ?)'
        )->execute([$email, $tokenHash, $expiresAt]);
        $appUrl = rtrim($this->cfg['app_url'], '/');
        return "{$appUrl}/auth/magic?token={$token}&email=" . urlencode($email);
    }

    public function verify(string $token, string $email): string {
        $email     = strtolower(trim($email));
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT * FROM magic_links WHERE email = ? AND token_hash = ? AND expires_at > NOW() AND used = 0'
        );
        $stmt->execute([$email, $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('This login link is invalid or has expired. Please request a new one.');
        }
        $this->db->prepare('UPDATE magic_links SET used = 1 WHERE id = ?')->execute([$row['id']]);
        return $email;
    }

    public function findOrCreateUser(string $email, Auth $auth): array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $this->db->prepare('INSERT INTO users (email, name) VALUES (?, ?)')->execute([$email, $email]);
            $newId = (int)$this->db->lastInsertId();
            $this->db->prepare(
                'UPDATE profile_shares SET shared_with_user_id = ? WHERE shared_with_email = ? AND shared_with_user_id IS NULL'
            )->execute([$newId, $email]);
            return $auth->findById($newId);
        }
        return $user;
    }
}