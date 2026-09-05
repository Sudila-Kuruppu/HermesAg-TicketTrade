<?php

/**
 * TicketTrade — Support\Audit (Phase 4 stub)
 *
 * Per AD-12: forward-compatible stub for the audit log. Phase 4 ships
 * plain INSERTs into audit_log; Phase 8 wraps the same INSERT with a
 * SHA-256 hash chain (adds prev_hash CHAR(64) column). The signature
 * does not change across the swap.
 *
 * Contract (per D-04):
 *   - Support\Audit::log() NEVER throws (a logging failure returns 0
 *     and emits an error_log line). The business operation that called
 *     the log() must complete even when the audit write fails.
 *   - Returns the new audit_id on success, 0 on logging failure.
 *   - The action names are namespaced (e.g. ticket.created,
 *     ticket.redeemed, ticket.session_confirmed, ticket.dispute_filed).
 *   - metadata is server-controlled (no PII like password_hash, email,
 *     or session tokens).
 */

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

class Audit
{
    /**
     * Write a single audit_log row. Returns the new audit_id on
     * success, 0 on failure. Never throws.
     *
     * @param int|null $actorUserId The authenticated user that triggered the action (null for system).
     * @param string $action        Namespaced action name (e.g. 'ticket.created').
     * @param string $targetType    The entity type (e.g. 'ticket', 'listing', 'user').
     * @param int $targetId         The entity id.
     * @param array|null $metadata  Optional metadata (sanitized before passing).
     * @return int The new audit_id or 0 on failure.
     */
    public static function log(
        ?int $actorUserId,
        string $action,
        string $targetType,
        int $targetId,
        ?array $metadata = null
    ): int {
        try {
            $pdo = Db::pdo();
            $metadataJson = $metadata === null
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE);
            if ($metadataJson === false) {
                $metadataJson = null;
            }
            // CR-05 fix: explicitly bind $actorUserId as PARAM_NULL when
            // null and PARAM_INT otherwise. PDO's default behavior on
            // null is implementation-defined; for a BIGINT UNSIGNED FK
            // column an empty-string bind would break the never-throw
            // contract when the table is referenced. Normalizing
            // non-positive ids to null at the Audit boundary also
            // protects callers that forget the `> 0 ? x : null` cast.
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, metadata_json, event_at) '
                . 'VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $bindActor = ($actorUserId !== null && $actorUserId > 0)
                ? $actorUserId
                : null;
            $stmt->bindValue(1, $bindActor, $bindActor === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(2, $action, PDO::PARAM_STR);
            $stmt->bindValue(3, $targetType, PDO::PARAM_STR);
            $stmt->bindValue(4, $targetId, PDO::PARAM_INT);
            $stmt->bindValue(5, $metadataJson, $metadataJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            // D-04: a failed audit write MUST NOT block the business operation.
            error_log('[audit] write failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build the canonical key-sorted associative array for a row.
     *
     * Phase 8 wraps the INSERT with a hash chain; the canonical row
     * shape is fixed here so the chain produces identical hashes
     * across environments. This method is exposed so future hash-
     * chain tests can call it directly.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function canonicalRow(array $row): array
    {
        ksort($row);
        return $row;
    }
}
