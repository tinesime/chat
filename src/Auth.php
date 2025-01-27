<?php

declare(strict_types=1);

namespace Chat;

require_once dirname(__DIR__) . "/vendor/autoload.php";

use PDO;

class Auth
{
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function register($username, $password): array
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username already taken.'];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (:username, :pwd)");
        $stmt->execute([':username' => $username, ':pwd' => $hashedPassword]);

        return ['success' => true, 'message' => 'Registration successful!'];
    }

    public function login($username, $password): array
    {
        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                return ['success' => true, 'user_id' => $user['id']];
            }
        }
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

}