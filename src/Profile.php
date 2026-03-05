<?php
// src/Profile.php

class Profile {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function create(int $userId, array $data): int {
        $secret = strtoupper(preg_replace('/\s+/', '', $data['secret']));
        $stmt = $this->db->prepare(
            'INSERT INTO otp_profiles (user_id, name, issuer, secret_encrypted, algorithm, digits, period, color, icon, hide_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            trim($data['name']),
            trim($data['issuer'] ?? ''),
            Crypto::encrypt($secret),
            $data['algorithm'] ?? 'SHA1',
            (int)($data['digits'] ?? 6),
            (int)($data['period'] ?? 30),
            $data['color']     ?? '#6366f1',
            $data['icon']      ?? 'fa-shield-halved',
            (int)($data['hide_code'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $profileId, int $userId, array $data): bool {
        if (!$this->canEdit($profileId, $userId)) return false;

        $fields = [];
        $params = [];

        foreach (['name', 'issuer', 'algorithm', 'color', 'icon'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        foreach (['digits', 'period', 'hide_code'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = (int)$data[$f];
            }
        }
        if (!empty($data['secret'])) {
            $fields[] = 'secret_encrypted = ?';
            $params[] = Crypto::encrypt(strtoupper(preg_replace('/\s+/', '', $data['secret'])));
        }

        if (empty($fields)) return true;
        $params[] = $profileId;

        $stmt = $this->db->prepare('UPDATE otp_profiles SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        return true;
    }

    public function delete(int $profileId, int $userId): bool {
        $stmt = $this->db->prepare('DELETE FROM otp_profiles WHERE id = ? AND user_id = ?');
        $stmt->execute([$profileId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function get(int $profileId, int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS owner_name, u.email AS owner_email,
                    IF(p.user_id = :uid, 1, 0) AS is_owner,
                    COALESCE(s.can_edit, IF(p.user_id = :uid2, 1, 0)) AS can_edit
             FROM otp_profiles p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN profile_shares s ON s.profile_id = p.id AND s.shared_with_user_id = :uid3
             WHERE p.id = :pid AND (p.user_id = :uid4 OR s.shared_with_user_id = :uid5)'
        );
        $stmt->execute([':uid'=>$userId,':uid2'=>$userId,':uid3'=>$userId,':pid'=>$profileId,':uid4'=>$userId,':uid5'=>$userId]);
        return $stmt->fetch() ?: null;
    }

    public function listForUser(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS owner_name, u.email AS owner_email,
                    IF(p.user_id = :uid, 1, 0) AS is_owner,
                    COALESCE(s.can_edit, IF(p.user_id = :uid2, 1, 0)) AS can_edit
             FROM otp_profiles p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN profile_shares s ON s.profile_id = p.id AND s.shared_with_user_id = :uid3
             WHERE p.user_id = :uid4 OR s.shared_with_user_id = :uid5
             ORDER BY p.user_id = :uid6 DESC, p.name ASC'
        );
        $stmt->execute([':uid'=>$userId,':uid2'=>$userId,':uid3'=>$userId,':uid4'=>$userId,':uid5'=>$userId,':uid6'=>$userId]);
        return $stmt->fetchAll();
    }

    // ── Sharing ───────────────────────────────────────────────────────────

    public function share(int $profileId, int $ownerUserId, string $email, bool $canEdit = false): bool {
        $stmt = $this->db->prepare('SELECT id FROM otp_profiles WHERE id = ? AND user_id = ?');
        $stmt->execute([$profileId, $ownerUserId]);
        if (!$stmt->fetch()) return false;

        $stmt2 = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt2->execute([$email]);
        $targetUser = $stmt2->fetch();
        $targetUserId = $targetUser ? $targetUser['id'] : null;

        $stmt3 = $this->db->prepare(
            'INSERT INTO profile_shares (profile_id, shared_by_user_id, shared_with_email, shared_with_user_id, can_edit)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE shared_with_user_id = VALUES(shared_with_user_id), can_edit = VALUES(can_edit)'
        );
        $stmt3->execute([$profileId, $ownerUserId, $email, $targetUserId, $canEdit ? 1 : 0]);
        return true;
    }

    public function unshare(int $profileId, int $ownerUserId, string $email): bool {
        $stmt = $this->db->prepare(
            'DELETE ps FROM profile_shares ps
             JOIN otp_profiles p ON p.id = ps.profile_id
             WHERE ps.profile_id = ? AND p.user_id = ? AND ps.shared_with_email = ?'
        );
        $stmt->execute([$profileId, $ownerUserId, $email]);
        return $stmt->rowCount() > 0;
    }

    public function getShares(int $profileId, int $ownerUserId): array {
        $stmt = $this->db->prepare(
            'SELECT ps.*, u.name AS user_name, u.avatar_url
             FROM profile_shares ps
             LEFT JOIN users u ON u.id = ps.shared_with_user_id
             WHERE ps.profile_id = ? AND ps.shared_by_user_id = ?
             ORDER BY ps.created_at DESC'
        );
        $stmt->execute([$profileId, $ownerUserId]);
        return $stmt->fetchAll();
    }

    // ── OTP generation (secret stays server-side) ─────────────────────────

    public function generateOTP(int $profileId, int $userId): array {
        $profile = $this->get($profileId, $userId);
        if (!$profile) throw new RuntimeException('Profile not found or access denied.');

        $secret = Crypto::decrypt($profile['secret_encrypted']);
        $now    = time();
        $period = (int)$profile['period'];

        return [
            'code'      => TOTP::generate($secret, $profile['algorithm'], (int)$profile['digits'], $period, $now),
            'remaining' => TOTP::secondsRemaining($period, $now),
            'progress'  => TOTP::progress($period, $now),
            'period'    => $period,
            'digits'    => (int)$profile['digits'],
            'algorithm' => $profile['algorithm'],
            'hide_code' => (int)$profile['hide_code'],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function canEdit(int $profileId, int $userId): bool {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM otp_profiles WHERE id = ? AND user_id = ?
             UNION
             SELECT 1 FROM profile_shares WHERE profile_id = ? AND shared_with_user_id = ? AND can_edit = 1'
        );
        $stmt->execute([$profileId, $userId, $profileId, $userId]);
        return (bool)$stmt->fetch();
    }
}