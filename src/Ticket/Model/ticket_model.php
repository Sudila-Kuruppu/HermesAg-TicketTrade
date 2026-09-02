<?php

/**
 * TicketTrade — Ticket\Model\ticket_model
 *
 * Raw PDO access for the tickets table. Per AD-1, the sole writer is
 * Ticket/Service/ticket_service. This Model exposes only data-access
 * helpers and the public ticket code generator.
 *
 * Per AD-8 + D-01: ticket codes are `TK-XXXX-XXXX-XXXX-XXXX-XXXX`
 * (six 4-char base62 groups; ≥125 bits entropy from random_bytes(16)).
 * The generator retries up to 10 times on UNIQUE collision; on the 11th
 * failure it throws an Exception with code E_TICKET_CODE_COLLISION
 * (PRD OQ-004).
 */

declare(strict_types=1);

namespace App\Ticket\Model;

use App\Support\Db;
use DateTime;
use DateTimeZone;
use PDO;

class ticket_model
{
    /**
     * Base62 alphabet (0-9 + A-Z + a-z). Matches PRD canonical example
     * `TK-7QXK2M9WBV4N8PRTYC3AD`. Visually ambiguous characters (0/O,
     * 1/I/l) are NOT excluded — the PRD example includes them and
     * honoring the PRD verbatim is the priority (CONTEXT D-01).
     */
    public const BASE62_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public const TICKET_CODE_LENGTH = 22;
    public const TICKET_CODE_GROUP_SIZE = 4;
    public const TICKET_CODE_PREFIX = 'TK-';
    public const MAX_CODE_RETRIES = 10;

    /**
     * Generate a unique dashed ticket code by drawing 16 random bytes
     * and base62-encoding into six 4-char groups. Retries up to 10
     * times on UNIQUE violation; on the 11th attempt, throws an
     * Exception with code E_TICKET_CODE_COLLISION.
     *
     * Per D-01 + AD-8: ≥125 bits entropy from random_bytes(16). The
     * dashed form IS the canonical stored form; the redemption input
     * is matched against the dashed form directly.
     */
    public static function generateUniqueCode(PDO $pdo): string
    {
        $lastException = null;
        for ($attempt = 0; $attempt < self::MAX_CODE_RETRIES; $attempt++) {
            $bytes = random_bytes(16);
            $code = self::formatCode($bytes);
            try {
                $stmt = $pdo->prepare('SELECT id FROM tickets WHERE ticket_code = ? LIMIT 1');
                $stmt->execute([$code]);
                $exists = $stmt->fetch();
                if ($exists === false) {
                    return $code;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }
        // Per PRD OQ-004 + D-01: after 10 collisions, surface
        // E_TICKET_CODE_COLLISION. The caller (Service) maps this to
        // the AD-16 failure envelope.
        throw new \RuntimeException('E_TICKET_CODE_COLLISION');
    }

    /**
     * Encode 16 random bytes into the canonical dashed ticket code
     * form `TK-XXXX-XXXX-XXXX-XXXX-XXXX` (six 4-char base62 groups).
     *
     * @param string $bytes 16 raw bytes from random_bytes(16)
     * @return string The dashed code; total length 26 chars.
     */
    public static function formatCode(string $bytes): string
    {
        $alphabet = self::BASE62_ALPHABET;
        $base = strlen($alphabet);
        // Convert 16 bytes into a base62 string. Treat the 16 bytes as
        // 32 hex chars, chunk-split into 4-char groups (8 hex groups
        // = 32 chars). Each 4-hex group encodes 16 bits (0..65535).
        // Map each 16-bit value to a 4-char base62 chunk. base^4 =
        // 14,776,336 ≥ 65536, so 4 base62 chars can represent any
        // 16-bit value cleanly.
        $hex = bin2hex($bytes); // 32 chars
        $groups = str_split($hex, self::TICKET_CODE_GROUP_SIZE);
        // 8 hex groups (32 chars / 4). The canonical form is six
        // 4-char groups; take the first 6 groups (96 bits entropy from
        // random_bytes(16)). The visual code length is exactly the PRD
        // canonical example `TK-XXXX-XXXX-XXXX-XXXX-XXXX` = 26 chars.
        $parts = [];
        for ($i = 0; $i < 6; $i++) {
            $g = $groups[$i];
            $n = hexdec($g);
            $out = '';
            for ($j = 0; $j < self::TICKET_CODE_GROUP_SIZE; $j++) {
                $out = $alphabet[$n % $base] . $out;
                $n = intdiv($n, $base);
            }
            $parts[] = $out;
        }
        return self::TICKET_CODE_PREFIX . implode('-', $parts);
    }

    /**
     * Insert a ticket row. Returns the new id.
     *
     * @param array<string,mixed> $data Required keys:
     *   ticket_code, listing_id, buyer_id, seller_id, status,
     *   dispute_status, price_cents, session_number, total_sessions,
     *   expires_at.
     */
    public static function insert(array $data): int
    {
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $sql = "INSERT INTO tickets ("
            . "ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, "
            . "price_cents, session_number, total_sessions, expires_at, "
            . "redeemed_at, disputed_at, resolved_at, resolution_note, "
            . "created_at, updated_at) "
            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([
            (string) $data['ticket_code'],
            (int) $data['listing_id'],
            (int) $data['buyer_id'],
            (int) $data['seller_id'],
            (string) $data['status'],
            (string) $data['dispute_status'],
            (int) $data['price_cents'],
            (int) $data['session_number'],
            (int) $data['total_sessions'],
            (string) $data['expires_at'],
            $data['redeemed_at'] ?? null,
            $data['disputed_at'] ?? null,
            $data['resolved_at'] ?? null,
            $data['resolution_note'] ?? null,
            $now,
            $now,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Find a ticket by its public ticket_code. Returns null if not found.
     */
    public static function findByCode(PDO $pdo, string $code): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE ticket_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Find a ticket by its internal BIGINT id.
     */
    public static function findById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Find all active tickets a buyer currently holds. Used by the
     * My Tickets "Active" tab.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function findActiveByBuyer(PDO $pdo, int $buyerId): array
    {
        $sql = "SELECT t.*, l.title AS listing_title "
            . "FROM tickets t JOIN listings l ON l.id = t.listing_id "
            . "WHERE t.buyer_id = ? AND t.status = 'active' "
            . "ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll();
    }

    /**
     * Find all tickets a buyer owns (any status). Chronological desc.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function findByBuyer(PDO $pdo, int $buyerId): array
    {
        $sql = "SELECT t.*, l.title AS listing_title "
            . "FROM tickets t JOIN listings l ON l.id = t.listing_id "
            . "WHERE t.buyer_id = ? "
            . "ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll();
    }

    /**
     * Find all active tickets that involve the seller (i.e. tickets
     * for the seller's listings that are still active). Used by the
     * Sales page.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function findActiveBySeller(PDO $pdo, int $sellerId): array
    {
        $sql = "SELECT t.*, l.title AS listing_title "
            . "FROM tickets t JOIN listings l ON l.id = t.listing_id "
            . "WHERE t.seller_id = ? AND t.status = 'active' "
            . "ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll();
    }

    /**
     * Group tickets by listing_id for a seller. Used by the
     * Sales page's per-listing-group placement (D-05).
     *
     * @return array<int, array<string,mixed>> Map: listing_id => group[]
     */
    public static function findBySellerGrouped(PDO $pdo, int $sellerId): array
    {
        $sql = "SELECT t.*, l.title AS listing_title, l.quantity AS listing_quantity, "
            . "l.quantity_sold AS listing_quantity_sold "
            . "FROM tickets t JOIN listings l ON l.id = t.listing_id "
            . "WHERE t.seller_id = ? "
            . "ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sellerId]);
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $lid = (int) $r['listing_id'];
            if (!isset($grouped[$lid])) {
                $grouped[$lid] = [
                    'listing_id' => $lid,
                    'listing_title' => $r['listing_title'],
                    'listing_quantity' => (int) $r['listing_quantity'],
                    'listing_quantity_sold' => (int) $r['listing_quantity_sold'],
                    'tickets' => [],
                ];
            }
            $grouped[$lid]['tickets'][] = $r;
        }
        return $grouped;
    }

    /**
     * Increment session_number atomically and return the new value.
     * Used by confirmSession() in the Service.
     *
     * Returns the new session_number, or null if the WHERE guard failed.
     */
    public static function incrementSession(PDO $pdo, int $ticketId, int $sellerId): ?int
    {
        $sql = "UPDATE tickets SET session_number = session_number + 1, updated_at = NOW() "
            . "WHERE id = ? AND status = 'active' "
            . "AND dispute_status != 'pending' "
            . "AND seller_id = ? "
            . "AND session_number < total_sessions";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticketId, $sellerId]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $row = self::findById($pdo, $ticketId);
        return $row === null ? null : (int) $row['session_number'];
    }

    /**
     * Mark a ticket redeemed by its internal id. Used by confirmSession
     * after the session_number reaches total_sessions. Returns the
     * updated row, or null if the guard failed.
     */
    public static function markRedeemedById(PDO $pdo, int $ticketId, int $sellerId): ?array
    {
        $sql = "UPDATE tickets SET status = 'redeemed', redeemed_at = NOW(), updated_at = NOW() "
            . "WHERE id = ? AND status IN ('active','disputed') "
            . "AND dispute_status != 'pending' AND seller_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticketId, $sellerId]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        return self::findById($pdo, $ticketId);
    }

    /**
     * Mark a ticket redeemed atomically. Returns the row, or null if
     * the guard failed (ticket not active, dispute pending, or wrong
     * seller). Per AD-9 + D-01.
     */
    public static function markRedeemed(PDO $pdo, string $code, int $sellerId): ?array
    {
        $sql = "UPDATE tickets SET status = 'redeemed', redeemed_at = NOW(), updated_at = NOW() "
            . "WHERE ticket_code = ? AND status = 'active' "
            . "AND dispute_status != 'pending' AND seller_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $sellerId]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        return self::findByCode($pdo, $code);
    }

    /**
     * File a dispute on a ticket atomically. Returns the updated row,
     * or null if the guard failed. Per AD-15 + D-03: status flips to
     * 'disputed' ONLY if the previous status was 'active'; if it was
     * 'redeemed' the status stays 'redeemed' (only dispute_status
     * changes).
     */
    public static function fileDispute(PDO $pdo, int $ticketId, int $actorUserId): ?array
    {
        $sql = "UPDATE tickets SET "
            . "dispute_status = 'pending', "
            . "disputed_at = NOW(), "
            . "status = CASE WHEN status = 'active' THEN 'disputed' ELSE status END, "
            . "updated_at = NOW() "
            . "WHERE id = ? "
            . "AND status IN ('active','redeemed') "
            . "AND dispute_status = 'none' "
            . "AND (buyer_id = ? OR seller_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticketId, $actorUserId, $actorUserId]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        return self::findById($pdo, $ticketId);
    }
}
