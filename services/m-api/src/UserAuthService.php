<?php

declare(strict_types=1);

namespace LSE\Services\MApi;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class UserAuthService
{
    private const MIN_PASSWORD_LENGTH = 8;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,email:string,displayName:?string,createdAt:string}
     */
    public function register(string $email, string $password, ?string $displayName = null): array
    {
        $normalizedEmail = strtolower(trim($email));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $this->assertValidPassword($password);

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cms_users (email, password_hash, display_name) 
                 VALUES (:email, :password_hash, :display_name) 
                 RETURNING id, email, display_name, created_at'
            );
            $stmt->execute([
                'email' => $normalizedEmail,
                'password_hash' => $passwordHash,
                'display_name' => $displayName,
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user === false) {
                throw new RuntimeException('Failed to create user.');
            }

            $this->assignDefaultBillingPlan((int) $user['id']);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() === '23505') {
                throw new RuntimeException('A user with this email already exists.');
            }

            throw $e;
        }

        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'displayName' => $user['display_name'] !== null ? (string) $user['display_name'] : null,
            'createdAt' => (string) $user['created_at'],
        ];
    }

    /**
     * @return array{apiKey:string,user:array{id:int,email:string,displayName:?string}}
     */
    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));

        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, password_hash, is_active FROM cms_users WHERE email = :email'
        );
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials.');
        }

        if (!(bool) $user['is_active']) {
            throw new RuntimeException('Account is inactive.');
        }

        $apiKey = $this->createApiKey((int) $user['id'], 'session-' . gmdate('Ymd\THis\Z'));

        return [
            'apiKey' => $apiKey['apiKey'],
            'user' => [
                'id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'displayName' => $user['display_name'] !== null ? (string) $user['display_name'] : null,
            ],
        ];
    }

    /**
     * @return array{user_id:int,email:string,api_key_id:int}
     */
    public function authenticateApiKey(string $apiKey): array
    {
        $hashedKey = $this->hashApiKey($apiKey);

        $sql = 'SELECT k.id AS api_key_id, k.user_id, u.email 
                FROM cms_api_keys k 
                JOIN cms_users u ON u.id = k.user_id 
                WHERE k.hashed_key = :hashed_key AND k.revoked_at IS NULL AND u.is_active = TRUE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['hashed_key' => $hashedKey]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            throw new RuntimeException('Invalid or expired API key.');
        }

        $update = $this->pdo->prepare('UPDATE cms_api_keys SET last_used_at = NOW() WHERE id = :id');
        $update->execute(['id' => (int) $record['api_key_id']]);

        return [
            'user_id' => (int) $record['user_id'],
            'email' => (string) $record['email'],
            'api_key_id' => (int) $record['api_key_id'],
        ];
    }

    /**
     * @return array{id:int,email:string,displayName:?string,createdAt:string,updatedAt:string}
     */
    public function getUserProfile(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, created_at, updated_at FROM cms_users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'displayName' => $user['display_name'] !== null ? (string) $user['display_name'] : null,
            'createdAt' => (string) $user['created_at'],
            'updatedAt' => (string) $user['updated_at'],
        ];
    }

    /**
     * @param array{displayName?:?string,password?:?string,currentPassword?:?string} $changes
     * @return array{id:int,email:string,displayName:?string,createdAt:string,updatedAt:string}
     */
    public function updateUserProfile(int $userId, array $changes): array
    {
        $fields = [];
        $params = ['user_id' => $userId];

        if (array_key_exists('displayName', $changes)) {
            $fields[] = 'display_name = :display_name';
            $params['display_name'] = $changes['displayName'];
        }

        if (array_key_exists('password', $changes) && $changes['password'] !== null) {
            $this->assertValidPassword((string) $changes['password']);
            $currentPassword = $changes['currentPassword'] ?? null;
            if ($currentPassword === null || $currentPassword === '') {
                throw new InvalidArgumentException('Current password is required to set a new password.');
            }

            $stmt = $this->pdo->prepare('SELECT password_hash FROM cms_users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing === false || !password_verify((string) $currentPassword, (string) $existing['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            $hash = password_hash((string) $changes['password'], PASSWORD_DEFAULT);
            if ($hash === false) {
                throw new RuntimeException('Failed to hash password.');
            }

            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = $hash;
        }

        if ($fields === []) {
            return $this->getUserProfile($userId);
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE cms_users SET ' . implode(', ', $fields) . ' WHERE id = :user_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->getUserProfile($userId);
    }

    /**
     * @return array{id:int,name:string,lastFour:string,createdAt:string,lastUsedAt:?string,revokedAt:?string,apiKey:string}
     */
    public function createApiKey(int $userId, ?string $name = null): array
    {
        $plainKey = $this->generateApiKey();
        $hashedKey = $this->hashApiKey($plainKey);
        $keyName = $name !== null && $name !== '' ? $name : 'key-' . gmdate('Ymd\THis\Z');
        $lastFour = substr($plainKey, -4);

        $stmt = $this->pdo->prepare(
            'INSERT INTO cms_api_keys (user_id, name, hashed_key, last_four) 
             VALUES (:user_id, :name, :hashed_key, :last_four) 
             RETURNING id, created_at'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $keyName,
            'hashed_key' => $hashedKey,
            'last_four' => $lastFour,
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record === false) {
            throw new RuntimeException('Failed to create API key.');
        }

        return [
            'id' => (int) $record['id'],
            'name' => $keyName,
            'lastFour' => $lastFour,
            'createdAt' => (string) $record['created_at'],
            'lastUsedAt' => null,
            'revokedAt' => null,
            'apiKey' => $plainKey,
        ];
    }

    /**
     * @return list<array{id:int,name:string,lastFour:string,createdAt:string,lastUsedAt:?string,revokedAt:?string}>
     */
    public function listApiKeys(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, last_four, created_at, last_used_at, revoked_at 
             FROM cms_api_keys WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $record): array {
            return [
                'id' => (int) $record['id'],
                'name' => (string) $record['name'],
                'lastFour' => (string) $record['last_four'],
                'createdAt' => (string) $record['created_at'],
                'lastUsedAt' => $record['last_used_at'] !== null ? (string) $record['last_used_at'] : null,
                'revokedAt' => $record['revoked_at'] !== null ? (string) $record['revoked_at'] : null,
            ];
        }, $records);
    }

    public function revokeApiKey(int $userId, int $apiKeyId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cms_api_keys 
             SET revoked_at = NOW() 
             WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute([
            'id' => $apiKeyId,
            'user_id' => $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('API key not found or already revoked.');
        }
    }

    private function assignDefaultBillingPlan(int $userId): void
    {
        $planStmt = $this->pdo->prepare('SELECT id FROM cms_billing_plans WHERE plan_code = :code LIMIT 1');
        $planStmt->execute(['code' => 'starter']);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if ($plan === false) {
            return;
        }

        $existingStmt = $this->pdo->prepare(
            'SELECT 1 FROM cms_user_billing_plans WHERE user_id = :user_id AND active = TRUE LIMIT 1'
        );
        $existingStmt->execute(['user_id' => $userId]);
        if ($existingStmt->fetch(PDO::FETCH_ASSOC) !== false) {
            return;
        }

        $assignmentStmt = $this->pdo->prepare(
            'INSERT INTO cms_user_billing_plans (user_id, billing_plan_id, active) 
             VALUES (:user_id, :plan_id, TRUE)'
        );
        $assignmentStmt->execute([
            'user_id' => $userId,
            'plan_id' => (int) $plan['id'],
        ]);
    }

    private function assertValidPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Password must be at least 8 characters long.');
        }
    }

    private function generateApiKey(): string
    {
        $bytes = random_bytes(32);
        return bin2hex($bytes);
    }

    private function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
