<?php
/**
 * TicketTrade — Auth\Model\user_model
 *
 * Skeleton user CRUD. Phase 02-02 wires the register flow; this file
 * ships the findBy* / insert methods so Plan 02-02 can build on it.
 */

declare(strict_types=1);

namespace App\Auth\Model;

use PDO;

class user_model
{
    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function findById(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function findByStudentId(PDO $pdo, string $studentId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE student_id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function findByNickname(PDO $pdo, string $nickname): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE nickname = ? LIMIT 1');
        $stmt->execute([$nickname]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Insert a new user row. The caller (the Service, per AD-18) is
     * responsible for hashing the password before passing it here.
     *
     * @param array{email:string,student_id:string,nickname:string,password_hash:string,full_name?:string,avatar_id?:int} $data
     * @return int New user_id
     */
    public static function insert(PDO $pdo, array $data): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, student_id, nickname, password_hash, full_name, avatar_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            strtolower(trim($data['email'])),
            $data['student_id'],
            $data['nickname'],
            $data['password_hash'],
            $data['full_name'] ?? '',
            (int) ($data['avatar_id'] ?? 1),
            $now,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
