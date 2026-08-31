<?php
/**
 * TicketTrade — Auth\Model\student_id_allowlist_model
 *
 * Read-only lookups against the seeded allowlist.
 */

declare(strict_types=1);

namespace App\Auth\Model;

use PDO;

class student_id_allowlist_model
{
    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM student_id_allowlist WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function findByStudentId(PDO $pdo, string $studentId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM student_id_allowlist WHERE student_id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
}
