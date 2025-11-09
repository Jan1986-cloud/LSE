<?php

declare(strict_types=1);

namespace LSE\Services\MApi;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

class UserAuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,email:string,createdAt:string}
     */
    public function register(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        $sql = 'INSERT INTO cms_users (email, password_hash) 
                VALUES (:email, :password_hash) 
                RETURNING id, email, created_at';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'email' => $normalizedEmail,
                'password_hash' => $passwordHash,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new RuntimeException('A user with this email already exists.');
            }

            throw $e;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            throw new RuntimeException('Failed to create user.');
        }

        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'createdAt' => (string) $user['created_at'],
        ];
    }

    /**
     * @return array{apiKey:string,user:array{id:int,email:string}}
     */
    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));

        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, is_active FROM cms_users WHERE email = :email');
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials.');
        }

        if (!(bool) $user['is_active']) {
            throw new RuntimeException('Account is inactive.');
        }

        $apiKey = bin2hex(random_bytes(32));
        $hashedKey = $this->hashApiKey($apiKey);
        $name = 'session-' . gmdate('Ymd\THis\Z');
        $lastFour = substr($apiKey, -4);

        $insertKeySql = 'INSERT INTO cms_api_keys (user_id, name, hashed_key, last_four) 
                         VALUES (:user_id, :name, :hashed_key, :last_four) 
                         RETURNING id';
        $stmt = $this->pdo->prepare($insertKeySql);
        $stmt->execute([
            'user_id' => (int) $user['id'],
            'name' => $name,
            'hashed_key' => $hashedKey,
            'last_four' => $lastFour,
        ]);

        return [
            'apiKey' => $apiKey,
            'user' => [
                'id' => (int) $user['id'],
                'email' => (string) $user['email'],
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
                WHERE k.hashed_key = :hashed_key AND u.is_active = TRUE';
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

    private function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
