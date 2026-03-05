<?php
// src/Auth.php

class Auth {
    private PDO $db;
    private array $cfg;

    public function __construct() {
        $this->db  = Database::getInstance();
        $this->cfg = require __DIR__ . '/../config/config.php';
    }

    // ── Session management ─────────────────────────────────────────────────

    public function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieName = $this->cfg['session']['cookie_name'];
            session_name($cookieName);
            session_set_cookie_params([
                'lifetime' => $this->cfg['session']['lifetime'],
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function currentUser(): ?array {
        $this->startSession();
        if (empty($_SESSION['user_id'])) return null;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public function requireLogin(): array {
        $user = $this->currentUser();
        if (!$user) {
            header('Location: /');
            exit;
        }
        return $user;
    }

    public function loginUser(int $userId): void {
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public function logout(): void {
        $this->startSession();
        session_destroy();
        setcookie($this->cfg['session']['cookie_name'], '', time() - 3600, '/');
    }

    // ── User upsert ────────────────────────────────────────────────────────

    public function findOrCreateUser(array $data): array {
        // $data: email, name, avatar_url, provider (google|microsoft|github), provider_id
        $providerCol = $data['provider'] . '_id';

        // 1. Try by provider_id
        $stmt = $this->db->prepare("SELECT * FROM users WHERE {$providerCol} = ?");
        $stmt->execute([$data['provider_id']]);
        $user = $stmt->fetch();

        // 2. Try by email (links accounts with same email)
        if (!$user) {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
        }

        if ($user) {
            // Update provider id + refresh name/avatar
            $stmt = $this->db->prepare(
                "UPDATE users SET {$providerCol} = ?, name = COALESCE(?, name),
                 avatar_url = COALESCE(?, avatar_url), updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$data['provider_id'], $data['name'], $data['avatar_url'], $user['id']]);
            $user[$providerCol] = $data['provider_id'];
            return $user;
        }

        // Create new user
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, name, avatar_url, {$providerCol}) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$data['email'], $data['name'], $data['avatar_url'], $data['provider_id']]);
        $newId = (int)$this->db->lastInsertId();

        // Resolve pending shares
        $this->resolvePendingShares($data['email'], $newId);

        return $this->findById($newId);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function resolvePendingShares(string $email, int $userId): void {
        $stmt = $this->db->prepare(
            'UPDATE profile_shares SET shared_with_user_id = ? WHERE shared_with_email = ? AND shared_with_user_id IS NULL'
        );
        $stmt->execute([$userId, $email]);
    }
}
